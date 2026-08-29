<?php
declare(strict_types=1);

/**
 * install/migrate.php — Instalador/migrador idempotente do GuinchaFácil
 * =====================================================================
 * Pode ser executado MÚLTIPLAS VEZES com segurança: cada operação
 * verifica antes de executar. Sem erros de duplicação.
 *
 * CLI (terminal do cPanel):
 *   php install/migrate.php
 *
 * Browser:
 *   https://seusite.com/install/migrate.php?key=SUA_CHAVE
 *   (defina INSTALL_KEY=SUA_CHAVE no arquivo .env)
 *
 * ⚠ Após instalar com sucesso, proteja esta pasta:
 *   - Remova /install/ do servidor, OU
 *   - Mantenha o .htaccess que bloqueia acesso direto
 */

// ─── Proteção de acesso ──────────────────────────────────────────────────────
$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');

    $expectedKey = '';
    $envFiles = [
        __DIR__ . '/../.env.local',
        __DIR__ . '/../.env',
    ];
    foreach ($envFiles as $envFile) {
        if (!is_file($envFile)) {
            continue;
        }
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), 'INSTALL_KEY=')) {
                $expectedKey = trim(substr($line, strpos($line, '=') + 1));
                break 2;
            }
        }
    }
    if ($expectedKey === '') {
        $expectedKey = getenv('INSTALL_KEY') ?: '';
    }

    $providedKey = trim($_GET['key'] ?? '');
    if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
        http_response_code(403);
        echo "403 — Acesso negado.\n";
        echo "Adicione INSTALL_KEY=sua_chave_secreta ao .env.local/.env e acesse ?key=sua_chave_secreta\n";
        exit;
    }
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/migration_runtime.php';

// ─── Log ─────────────────────────────────────────────────────────────────────
$erros = 0;
function out(string $linha): void
{
    echo $linha . "\n";
    if (PHP_SAPI !== 'cli') {
        flush();
        ob_flush();
    }
}
function fail(string $linha): void
{
    global $erros;
    $erros++;
    out('[ERRO] ' . $linha);
}

function auditSqlMigrations(PDO $pdo, string $installDir): void
{
    try {
        MigrationRuntime::ensureSchemaMigrationsTable($pdo, $installDir);
        $count = MigrationRuntime::countAppliedMigrations($pdo);

        if ($count === 0) {
            $adopted = MigrationRuntime::adoptExistingSqlMigrations($pdo, $installDir);
            $totalAdopted = 0;
            foreach ($adopted as $row) {
                if (($row['status'] ?? '') === 'adopted') {
                    $totalAdopted++;
                }
            }
            out("[AUDIT] schema_migrations inicializada. {$totalAdopted} migration(s) SQL existentes foram adotadas pelo baseline canônico.");
        } else {
            out("[AUDIT] schema_migrations já possui {$count} registro(s).");
        }

        $results = MigrationRuntime::applyPendingSqlMigrations($pdo, $installDir, 'migrate.php');
        foreach ($results as $result) {
            $status = (string)($result['status'] ?? 'unknown');
            $filename = (string)($result['filename'] ?? 'migration');
            $message = (string)($result['message'] ?? '');

            if ($status === 'success') {
                out("[MIG]  {$filename} aplicada.");
            } elseif ($status === 'skipped') {
                out("[OK]   {$filename} já auditada.");
            } elseif ($status === 'drift') {
                fail("Drift detectado em {$filename}: {$message}");
            } else {
                fail("Migration SQL {$filename}: {$message}");
            }
        }
    } catch (Throwable $e) {
        fail('Governança de migration: ' . $e->getMessage());
    }
}

out('');
out('╔══════════════════════════════════════════════════╗');
out('║     GuinchaFácil — Migrador Idempotente          ║');
out('╚══════════════════════════════════════════════════╝');
out('');

// ─── Conexão ─────────────────────────────────────────────────────────────────
try {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db = DB_NAME;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db}`");
    out("[OK]   Conectado: " . DB_HOST . " / " . $db);
} catch (Throwable $e) {
    out('[FALHA] Conexão com banco: ' . $e->getMessage());
    exit(1);
}

// ─── Lock contra execução concorrente (Pacote L1.2) ───────────────────────────
// Evita que dois processos rodem migrate.php ao mesmo tempo (ex: deploy
// automático + execução manual, ou dois requests HTTP simultâneos).
$migrationLockName = 'guinchafacil_migrate_' . DB_NAME;
$migrationLockAcquired = (int)$pdo->query(
    "SELECT GET_LOCK(" . $pdo->quote($migrationLockName) . ", 10)"
)->fetchColumn();

if ($migrationLockAcquired !== 1) {
    out('[FALHA] Não foi possível obter o lock de migração (outra execução em andamento?). Tente novamente em instantes.');
    exit(1);
}

register_shutdown_function(function () use ($pdo, $migrationLockName): void {
    try {
        $pdo->exec("SELECT RELEASE_LOCK(" . $pdo->quote($migrationLockName) . ")");
    } catch (Throwable $e) {
        // conexão pode já ter caído no shutdown; nada a fazer.
    }
});

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// ─── Helpers ─────────────────────────────────────────────────────────────────
function tableExists(PDO $pdo, string $db, string $tbl): bool
{
    return (bool)$pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$tbl'"
    )->fetchColumn();
}

function columnExists(PDO $pdo, string $db, string $tbl, string $col): bool
{
    return (bool)$pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$tbl' AND COLUMN_NAME = '$col'"
    )->fetchColumn();
}

function indexExists(PDO $pdo, string $db, string $tbl, string $idx): bool
{
    return (bool)$pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$tbl' AND INDEX_NAME = '$idx'"
    )->fetchColumn();
}

function uniqueIndexExists(PDO $pdo, string $db, string $tbl, string $idx): bool
{
    return (bool)$pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$tbl' AND INDEX_NAME = '$idx' AND NON_UNIQUE = 0"
    )->fetchColumn();
}

function fkExists(PDO $pdo, string $db, string $tbl, string $fk): bool
{
    return (bool)$pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$tbl' AND CONSTRAINT_NAME = '$fk'"
    )->fetchColumn();
}

function enumContains(PDO $pdo, string $db, string $tbl, string $col, string $val): bool
{
    $type = $pdo->query(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$tbl' AND COLUMN_NAME = '$col'"
    )->fetchColumn();
    return $type && str_contains($type, "'$val'");
}

function createTable(PDO $pdo, string $db, string $tbl, string $sql): void
{
    if (tableExists($pdo, $db, $tbl)) {
        out("[OK]   Tabela `$tbl` já existe");
        return;
    }
    try {
        $pdo->exec($sql);
        out("[NOVO] Tabela `$tbl` criada");
    } catch (Throwable $e) {
        fail("Criar `$tbl`: " . $e->getMessage());
    }
}

function addCol(PDO $pdo, string $db, string $tbl, string $col, string $def, string $after = ''): void
{
    if (columnExists($pdo, $db, $tbl, $col)) {
        out("[OK]   `$tbl`.`$col` já existe");
        return;
    }
    try {
        $afterSql = $after ? " AFTER `$after`" : '';
        $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN `$col` $def$afterSql");
        out("[NOVO] `$tbl`.`$col` adicionado");
    } catch (Throwable $e) {
        fail("`$tbl`.`$col`: " . $e->getMessage());
    }
}

function renameCol(PDO $pdo, string $db, string $tbl, string $antigo, string $novo, string $def): void
{
    if (!columnExists($pdo, $db, $tbl, $antigo)) {
        out("[OK]   `$tbl`.`$antigo` não existe (rename desnecessário)");
        return;
    }
    if (columnExists($pdo, $db, $tbl, $novo)) {
        out("[OK]   `$tbl`.`$novo` já existe (rename já feito)");
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `$tbl` CHANGE COLUMN `$antigo` `$novo` $def");
        out("[REN]  `$tbl`.`$antigo` → `$novo`");
    } catch (Throwable $e) {
        fail("Rename `$tbl`.`$antigo`: " . $e->getMessage());
    }
}

function addIndex(PDO $pdo, string $db, string $tbl, string $idx, string $cols): void
{
    if (indexExists($pdo, $db, $tbl, $idx)) {
        out("[OK]   Índice `$idx` em `$tbl` já existe");
        return;
    }
    try {
        $pdo->exec("CREATE INDEX `$idx` ON `$tbl` ($cols)");
        out("[NOVO] Índice `$idx` em `$tbl`");
    } catch (Throwable $e) {
        fail("Índice `$idx`: " . $e->getMessage());
    }
}

function addUniqueIndex(PDO $pdo, string $db, string $tbl, string $idx, string $cols): void
{
    if (uniqueIndexExists($pdo, $db, $tbl, $idx)) {
        out("[OK]   Índice UNIQUE `$idx` em `$tbl` já existe");
        return;
    }
    try {
        $pdo->exec("CREATE UNIQUE INDEX `$idx` ON `$tbl` ($cols)");
        out("[NOVO] Índice UNIQUE `$idx` em `$tbl`");
    } catch (Throwable $e) {
        fail("Índice UNIQUE `$idx`: " . $e->getMessage());
    }
}

function dedupeRateLimit(PDO $pdo, string $db): void
{
    if (!tableExists($pdo, $db, 'rate_limit')) return;
    if (!columnExists($pdo, $db, 'rate_limit', 'ip') || !columnExists($pdo, $db, 'rate_limit', 'rota')) return;

    // Se já existe UNIQUE, não precisa deduplicar.
    if (uniqueIndexExists($pdo, $db, 'rate_limit', 'uk_ip_rota')) {
        out("[OK]   rate_limit já está deduplicada por UNIQUE (ip,rota)");
        return;
    }

    try {
        $pdo->exec("DROP TABLE IF EXISTS rate_limit_tmp");
        $pdo->exec(
            "CREATE TABLE rate_limit_tmp AS
             SELECT
               MIN(id)                 AS id,
               ip,
               rota,
               MAX(tentativas)         AS tentativas,
               MIN(primeira_tentativa) AS primeira_tentativa,
               MAX(bloqueado_ate)      AS bloqueado_ate,
               MIN(criado_em)          AS criado_em,
               MAX(atualizado_em)      AS atualizado_em
             FROM rate_limit
             GROUP BY ip, rota"
        );
        $pdo->exec("TRUNCATE TABLE rate_limit");
        $pdo->exec(
            "INSERT INTO rate_limit (id, ip, rota, tentativas, primeira_tentativa, bloqueado_ate, criado_em, atualizado_em)
             SELECT id, ip, rota, tentativas, primeira_tentativa, bloqueado_ate, criado_em, atualizado_em
             FROM rate_limit_tmp"
        );
        $pdo->exec("DROP TABLE IF EXISTS rate_limit_tmp");
        out("[FIX]  rate_limit deduplicada por (ip, rota)");
    } catch (Throwable $e) {
        fail("Deduplicar rate_limit: " . $e->getMessage());
    }
}

function addFk(PDO $pdo, string $db, string $tbl, string $fkName, string $fkSql): void
{
    if (fkExists($pdo, $db, $tbl, $fkName)) {
        out("[OK]   FK `$fkName` já existe");
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `$tbl` ADD CONSTRAINT `$fkName` $fkSql");
        out("[NOVO] FK `$fkName` em `$tbl`");
    } catch (Throwable $e) {
        // FK pode falhar se dados inválidos existirem — não fatal
        out("[WARN] FK `$fkName`: " . $e->getMessage());
    }
}

function seed(PDO $pdo, string $tbl, string $where, array $whereParams, string $insert, array $insertParams): void
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE $where");
    $stmt->execute($whereParams);
    if ((int)$stmt->fetchColumn() > 0) {
        out("[OK]   Seed em `$tbl` [$where] já existe");
        return;
    }
    try {
        $pdo->prepare($insert)->execute($insertParams);
        out("[SEED] Inserido em `$tbl`");
    } catch (Throwable $e) {
        fail("Seed `$tbl`: " . $e->getMessage());
    }
}

// ═════════════════════════════════════════════════════════════════════════════
out('');
out('── FASE 1: Tabelas ──────────────────────────────────────────────────────');

// ── usuarios ─────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'usuarios', "
    CREATE TABLE `usuarios` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `nome`         VARCHAR(100)  NOT NULL,
        `email`        VARCHAR(150)  NOT NULL UNIQUE,
        `senha_hash`   VARCHAR(255)  NOT NULL,
        `telefone`     VARCHAR(20)   NOT NULL,
        `cpf`          VARCHAR(14)   NOT NULL UNIQUE,
        `tipo`         ENUM('admin','guincho','cliente') NOT NULL,
        `ativo`        TINYINT(1)    DEFAULT 1,
        `criado_em`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
        `ultimo_login` DATETIME      NULL,
        `atualizado_em` DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_cpf   (cpf),
        INDEX idx_tipo  (tipo),
        INDEX idx_ativo (ativo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── enderecos ─────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'enderecos', "
    CREATE TABLE `enderecos` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `usuario_id`  INT           NOT NULL,
        `cep`         VARCHAR(10)   NOT NULL,
        `logradouro`  VARCHAR(200)  NOT NULL,
        `numero`      VARCHAR(20)   NOT NULL,
        `complemento` VARCHAR(100)  NULL,
        `bairro`      VARCHAR(100)  NOT NULL,
        `cidade`      VARCHAR(100)  NOT NULL,
        `estado`      CHAR(2)       NOT NULL,
        `latitude`    DECIMAL(10,8) NULL,
        `longitude`   DECIMAL(11,8) NULL,
        `principal`   TINYINT(1)    DEFAULT 0,
        `criado_em`   DATETIME      DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario_id  (usuario_id),
        INDEX idx_principal   (principal),
        INDEX idx_coordenadas (latitude, longitude)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── veiculos ──────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'veiculos', "
    CREATE TABLE `veiculos` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `usuario_id`   INT          NOT NULL,
        `placa`        VARCHAR(8)   NOT NULL,
        `marca`        VARCHAR(50)  NOT NULL,
        `modelo`       VARCHAR(100) NOT NULL,
        `ano`          INT          NOT NULL,
        `cor`          VARCHAR(30)  NOT NULL,
        `tipo`         ENUM('carro','moto','caminhao','van','onibus','outro') NOT NULL,
        `ativo`        TINYINT(1)   DEFAULT 1,
        `criado_em`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_placa      (placa),
        INDEX idx_ativo      (ativo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── oficinas_favoritas ────────────────────────────────────────────────────────
createTable($pdo, $db, 'oficinas_favoritas', "
    CREATE TABLE `oficinas_favoritas` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `usuario_id`   INT           NOT NULL,
        `nome`         VARCHAR(150)  NOT NULL,
        `telefone`     VARCHAR(20)   NOT NULL DEFAULT '',
        `endereco`     TEXT          NOT NULL,
        `lat`          DECIMAL(10,8) NULL,
        `lng`          DECIMAL(11,8) NULL,
        `criado_em`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario_id  (usuario_id),
        INDEX idx_coordenadas (lat, lng)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── guinchos ──────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'guinchos', "
    CREATE TABLE `guinchos` (
        `id`                  INT AUTO_INCREMENT PRIMARY KEY,
        `usuario_id`          INT            NOT NULL UNIQUE,
        `cnh_numero`          VARCHAR(20)    NOT NULL,
        `cnh_validade`        DATE           NOT NULL,
        `placa_guincho`       VARCHAR(8)     NOT NULL,
        `modelo_veiculo`      VARCHAR(120)   NULL,
        `ano_veiculo`         INT            NULL,
        `capacidade_ton`      DECIMAL(5,2)   NOT NULL DEFAULT 0,
        `raio_cobertura_km`   INT            NOT NULL DEFAULT 20,
        `chave_pix`           VARCHAR(500)   NOT NULL DEFAULT '',
        `chave_pix_tipo`      ENUM('cpf','email','telefone','aleatoria') NOT NULL DEFAULT 'aleatoria',
        `lat_atual`           DECIMAL(10,8)  NULL,
        `lng_atual`           DECIMAL(11,8)  NULL,
        `disponivel`          TINYINT(1)     DEFAULT 1,
        `aprovado`            TINYINT(1)     DEFAULT 0,
        `foto_veiculo`        VARCHAR(255)   NULL,
        `cnh_frente`          VARCHAR(255)   NULL,
        `cnh_verso`           VARCHAR(255)   NULL,
        `reputacao`           DECIMAL(3,2)   DEFAULT 0.00,
        `total_avaliacoes`    INT            DEFAULT 0,
        `criado_em`           DATETIME       DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`       DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_disponivel (disponivel),
        INDEX idx_aprovado   (aprovado),
        INDEX idx_reputacao  (reputacao),
        INDEX idx_coordenadas (lat_atual, lng_atual)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── pedidos ───────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'pedidos', "
    CREATE TABLE `pedidos` (
        `id`                  INT AUTO_INCREMENT PRIMARY KEY,
        `cliente_id`          INT            NOT NULL,
        `veiculo_id`          INT            NOT NULL,
        `guincho_id`          INT            NULL,
        `tipo_problema`       ENUM('mecanico','eletrico','pneu','bateria','combustivel','acidente','colisao','outro') NOT NULL,
        `descricao_problema`  TEXT           NULL,
        `lat_origem`          DECIMAL(10,8)  NOT NULL,
        `lng_origem`          DECIMAL(11,8)  NOT NULL,
        `endereco_origem`     TEXT           NOT NULL,
        `lat_destino`         DECIMAL(10,8)  NOT NULL,
        `lng_destino`         DECIMAL(11,8)  NOT NULL,
        `endereco_destino`    TEXT           NOT NULL,
        `distancia_km`        DECIMAL(8,2)   NOT NULL DEFAULT 0,
        `custo_estimado`      DECIMAL(10,2)  NOT NULL DEFAULT 0,
        `custo_final`         DECIMAL(10,2)  NULL,
        `status`              ENUM('aguardando_pagamento','aguardando_guincho','a_caminho','no_local','em_reboque','concluido','cancelado') NOT NULL DEFAULT 'aguardando_pagamento',
        `raio_atual_km`       INT            DEFAULT 10,
        `score_minimo_atual`  DECIMAL(5,4)   DEFAULT 0.5000,
        `expiracao_aceite`    DATETIME       NULL,
        `criado_em`           DATETIME       DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`       DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cliente_id          (cliente_id),
        INDEX idx_guincho_id          (guincho_id),
        INDEX idx_status              (status),
        INDEX idx_criado_em           (criado_em),
        INDEX idx_coordenadas_origem  (lat_origem, lng_origem),
        INDEX idx_expiracao           (expiracao_aceite)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── pagamentos ────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'pagamentos', "
    CREATE TABLE `pagamentos` (
        `id`                     INT AUTO_INCREMENT PRIMARY KEY,
        `pedido_id`              INT            NOT NULL,
        `metodo`                 ENUM('mercadopago','pagseguro') NOT NULL DEFAULT 'mercadopago',
        `valor_total`            DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
        `valor_guincho`          DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
        `valor_plataforma`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
        `status`                 ENUM('pendente','aprovado','recusado','estornado') NOT NULL DEFAULT 'pendente',
        `id_externo`             VARCHAR(100)   NULL,
        `pago_guincho`           TINYINT(1)     NOT NULL DEFAULT 0,
        `data_pagamento`         DATETIME       NULL,
        `data_pagamento_guincho` DATETIME       NULL,
        `id_transacao_pix`       VARCHAR(100)   NULL,
        `status_pix`             ENUM('pendente','processando','concluido','falha','falha_permanente') NOT NULL DEFAULT 'pendente',
        `webhook_payload`        TEXT           NULL,
        `criado_em`              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pedido_id           (pedido_id),
        INDEX idx_status              (status),
        INDEX idx_pagamentos_id_externo (id_externo),
        INDEX idx_pagamentos_status   (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── avaliacoes ────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'avaliacoes', "
    CREATE TABLE `avaliacoes` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `pedido_id`   INT        NOT NULL UNIQUE,
        `cliente_id`  INT        NOT NULL,
        `guincho_id`  INT        NOT NULL,
        `estrelas`    TINYINT    NOT NULL CHECK (estrelas >= 1 AND estrelas <= 5),
        `comentario`  TEXT       NULL,
        `criado_em`   DATETIME   DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_guincho_id (guincho_id),
        INDEX idx_estrelas   (estrelas)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── chat_mensagens ────────────────────────────────────────────────────────────
createTable($pdo, $db, 'chat_mensagens', "
    CREATE TABLE `chat_mensagens` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `pedido_id`   INT        NOT NULL,
        `usuario_id`  INT        NOT NULL,
        `mensagem`    TEXT       NOT NULL,
        `lida`        TINYINT(1) DEFAULT 0,
        `criado_em`   DATETIME   DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pedido_id  (pedido_id),
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_lida       (lida),
        INDEX idx_criado_em  (criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── configuracoes ─────────────────────────────────────────────────────────────
createTable($pdo, $db, 'configuracoes', "
    CREATE TABLE `configuracoes` (
        `chave`        VARCHAR(100) NOT NULL PRIMARY KEY,
        `valor`        TEXT         NOT NULL,
        `descricao`    VARCHAR(255) NULL,
        `atualizado_em` DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── rate_limit ────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'rate_limit', "
    CREATE TABLE `rate_limit` (
        `id`                INT AUTO_INCREMENT PRIMARY KEY,
        `ip`                VARCHAR(45)  NOT NULL,
        `rota`              VARCHAR(255) NOT NULL,
        `tentativas`        INT          DEFAULT 1,
        `primeira_tentativa` DATETIME   DEFAULT CURRENT_TIMESTAMP,
        `bloqueado_ate`     DATETIME     NULL,
        `criado_em`         DATETIME     DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`     DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ip_rota      (ip, rota),
        INDEX idx_bloqueado_ate (bloqueado_ate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── logs_webhook ──────────────────────────────────────────────────────────────
createTable($pdo, $db, 'logs_webhook', "
    CREATE TABLE `logs_webhook` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `fonte`       VARCHAR(30)  NOT NULL,
        `evento`      VARCHAR(100) NOT NULL,
        `payload`     MEDIUMTEXT   NULL,
        `status_http` SMALLINT     NOT NULL DEFAULT 200,
        `criado_em`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logs_webhook_fonte     (fonte),
        INDEX idx_logs_webhook_criado_em (criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── password_resets ───────────────────────────────────────────────────────────
createTable($pdo, $db, 'password_resets', "
    CREATE TABLE `password_resets` (
        `id`        INT AUTO_INCREMENT PRIMARY KEY,
        `email`     VARCHAR(150) NOT NULL,
        `token`     VARCHAR(64)  NOT NULL UNIQUE,
        `expira_em` DATETIME     NOT NULL,
        `usado`     TINYINT(1)   DEFAULT 0,
        `criado_em` DATETIME     DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token  (token),
        INDEX idx_email  (email),
        INDEX idx_expira (expira_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── app_logs ──────────────────────────────────────────────────────────────────
createTable($pdo, $db, 'app_logs', "
    CREATE TABLE `app_logs` (
        `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `criado_em`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `level`        VARCHAR(16)  NOT NULL,
        `application`  VARCHAR(32)  NOT NULL DEFAULT 'GUINCHAFACIL',
        `system`       VARCHAR(96)  NOT NULL,
        `cls`          VARCHAR(96)  NOT NULL,
        `func`         VARCHAR(96)  NOT NULL,
        `file`         VARCHAR(255) NULL,
        `phase`        VARCHAR(120) NULL,
        `code`         VARCHAR(64)  NULL,
        `request_id`   VARCHAR(64)  NULL,
        `run_id`       VARCHAR(64)  NULL,
        `pedido_id`    BIGINT NULL,
        `usuario_id`   BIGINT NULL,
        `guincho_id`   BIGINT NULL,
        `duration_ms`  INT NULL,
        `msg`          TEXT         NOT NULL,
        `uri`          VARCHAR(255) NULL,
        `ip`           VARCHAR(64)  NULL,
        `ctx_json`     MEDIUMTEXT   NULL,
        INDEX idx_created (criado_em),
        INDEX idx_level (level),
        INDEX idx_app_logs_system_created (`system`, `criado_em`),
        INDEX idx_app_logs_code_created (`code`, `criado_em`),
        INDEX idx_app_logs_request_id (`request_id`),
        INDEX idx_app_logs_run_id (`run_id`),
        INDEX idx_app_logs_pedido_created (`pedido_id`, `criado_em`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

createTable($pdo, $db, 'geocoding_cache', "
    CREATE TABLE `geocoding_cache` (
        `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `cache_key`     VARCHAR(191) NOT NULL,
        `tipo`          ENUM('forward','reverse','cep') NOT NULL DEFAULT 'forward',
        `query_text`    VARCHAR(255) NULL,
        `latitude`      DECIMAL(10,8) NULL,
        `longitude`     DECIMAL(11,8) NULL,
        `response_json` LONGTEXT NULL,
        `expires_at`    DATETIME NULL,
        `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_cache_key (`cache_key`),
        KEY idx_tipo_expires (`tipo`, `expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── payment_jobs ──────────────────────────────────────────────────────────────
createTable($pdo, $db, 'payment_jobs', "
    CREATE TABLE `payment_jobs` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `pedido_id` INT NOT NULL,
        `pagamento_id` INT NOT NULL,
        `job_type` VARCHAR(32) NOT NULL,
        `idempotency_key` VARCHAR(120) NOT NULL,
        `status` VARCHAR(24) NOT NULL DEFAULT 'queued',
        `attempt_count` INT NOT NULL DEFAULT 0,
        `max_attempts` INT NOT NULL DEFAULT 5,
        `worker_id` VARCHAR(120) NULL,
        `locked_at` DATETIME NULL,
        `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_error` TEXT NULL,
        `payload_json` LONGTEXT NULL,
        `finished_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_payment_jobs_idempotency (`idempotency_key`),
        KEY idx_payment_jobs_status_available (`status`, `available_at`),
        KEY idx_payment_jobs_pedido (`pedido_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── payment_job_attempts ─────────────────────────────────────────────────────
createTable($pdo, $db, 'payment_job_attempts', "
    CREATE TABLE `payment_job_attempts` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `payment_job_id` BIGINT UNSIGNED NOT NULL,
        `attempt_number` INT NOT NULL,
        `worker_id` VARCHAR(120) NULL,
        `success` TINYINT(1) NOT NULL DEFAULT 0,
        `response_json` LONGTEXT NULL,
        `error_message` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_payment_job_attempts_job (`payment_job_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── simulation_runs ──────────────────────────────────────────────────────────
createTable($pdo, $db, 'simulation_runs', "
    CREATE TABLE `simulation_runs` (
        `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `run_id`             CHAR(32)        NOT NULL,
        `engine`             VARCHAR(32)     NOT NULL DEFAULT 'php_internal',
        `suite`              VARCHAR(120)    NULL,
        `status`             VARCHAR(16)     NOT NULL DEFAULT 'running',
        `pix_dry_run`        TINYINT(1)      NOT NULL DEFAULT 1,
        `pedido_id`          INT UNSIGNED    NULL,
        `total_fases`        SMALLINT        NOT NULL DEFAULT 0,
        `fases_ok`           SMALLINT        NOT NULL DEFAULT 0,
        `fases_erro`         SMALLINT        NOT NULL DEFAULT 0,
        `duracao_ms`         INT UNSIGNED    NULL,
        `requested_by`       INT UNSIGNED    NULL,
        `requested_at`       DATETIME        NULL,
        `target_environment` VARCHAR(64)     NULL,
        `target_url`         VARCHAR(255)    NULL,
        `browser`            VARCHAR(32)     NULL,
        `viewport`           VARCHAR(64)     NULL,
        `locale`             VARCHAR(16)     NULL,
        `timezone`           VARCHAR(64)     NULL,
        `worker_id`          VARCHAR(120)    NULL,
        `worker_pid`         INT             NULL,
        `heartbeat_at`       DATETIME        NULL,
        `started_at`         DATETIME        NULL,
        `finished_at`        DATETIME        NULL,
        `exit_code`          INT             NULL,
        `configuration_json` LONGTEXT        NULL,
        `summary_json`       LONGTEXT        NULL,
        `app_version`        VARCHAR(64)     NULL,
        `git_commit`         VARCHAR(64)     NULL,
        `iniciado_em`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `finalizado_em`      DATETIME        NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_run_id` (`run_id`),
        KEY `idx_iniciado` (`iniciado_em`),
        KEY `idx_engine_status` (`engine`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── simulation_steps ─────────────────────────────────────────────────────────
createTable($pdo, $db, 'simulation_steps', "
    CREATE TABLE `simulation_steps` (
        `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `run_id`        CHAR(32)        NOT NULL,
        `fase`          VARCHAR(255)    NOT NULL,
        `ok`            TINYINT(1)      NOT NULL DEFAULT 1,
        `mensagem`      TEXT            NOT NULL,
        `system`        VARCHAR(64)     NULL,
        `class`         VARCHAR(120)    NULL,
        `function`      VARCHAR(120)    NULL,
        `file`          VARCHAR(255)    NULL,
        `phase`         VARCHAR(120)    NULL,
        `code`          VARCHAR(64)     NULL,
        `status`        VARCHAR(24)     NULL,
        `duration_ms`   INT             NULL,
        `expected_json` LONGTEXT        NULL,
        `actual_json`   LONGTEXT        NULL,
        `error_message` LONGTEXT        NULL,
        `stack_trace`   LONGTEXT        NULL,
        `started_at`    DATETIME        NULL,
        `finished_at`   DATETIME        NULL,
        `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_run_id` (`run_id`),
        KEY `idx_criado` (`criado_em`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── simulation_artifacts ─────────────────────────────────────────────────────
createTable($pdo, $db, 'simulation_artifacts', "
    CREATE TABLE `simulation_artifacts` (
        `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `run_id`       CHAR(32)        NOT NULL,
        `step_id`      BIGINT UNSIGNED NULL,
        `kind`         VARCHAR(32)     NOT NULL,
        `filename`     VARCHAR(255)    NOT NULL,
        `private_path` VARCHAR(500)    NOT NULL,
        `mime_type`    VARCHAR(120)    NULL,
        `size_bytes`   BIGINT UNSIGNED NULL,
        `sha256`       CHAR(64)        NULL,
        `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_run_id` (`run_id`),
        KEY `idx_step_id` (`step_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── catalogo_servicos ────────────────────────────────────────────────────────
// Atalhos de serviço exibidos no painel do cliente ("Reboque agora", "Bateria",
// etc.) — administrável pelo admin em /admin/servicos (fecha o gap "catálogo
// de serviços administrável" do gate de saída do Nível 2).
createTable($pdo, $db, 'catalogo_servicos', "
    CREATE TABLE `catalogo_servicos` (
        `id`            INT             NOT NULL AUTO_INCREMENT,
        `chave`         VARCHAR(40)     NOT NULL,
        `nome`          VARCHAR(80)     NOT NULL,
        `descricao`     VARCHAR(160)    NULL,
        `tipo_problema` VARCHAR(20)     NOT NULL DEFAULT 'outro',
        `icone`         VARCHAR(60)     NOT NULL DEFAULT 'fa-truck-pickup',
        `cor`           VARCHAR(20)     NOT NULL DEFAULT 'tow',
        `ordem`         INT             NOT NULL DEFAULT 100,
        `ativo`         TINYINT(1)      NOT NULL DEFAULT 1,
        `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_catalogo_servicos_chave` (`chave`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$catalogoServicosSeed = (int)($pdo->query("SELECT COUNT(*) FROM catalogo_servicos")->fetchColumn() ?: 0);
if ($catalogoServicosSeed === 0) {
    $pdo->exec("
        INSERT INTO catalogo_servicos (chave, nome, descricao, tipo_problema, icone, cor, ordem, ativo) VALUES
        ('reboque_agora','Reboque agora','Atendimento imediato','outro','fa-truck-pickup','tow',10,1),
        ('bateria','Bateria','Partida auxiliar','bateria','fa-bolt','battery',20,1),
        ('pneu','Pneu','Troca e suporte','pneu','fa-circle-dot','tire',30,1),
        ('pane_seca','Pane seca','Falta de combustível','combustivel','fa-gas-pump','fuel',40,1),
        ('agendar','Agendar','Transporte programado','outro','fa-clock','schedule',50,1)
    ");
    out('[OK]   Catálogo de serviços semeado com os 5 atalhos padrão');
}

// ── pedido_localizacoes ─────────────────────────────────────────────────────
createTable($pdo, $db, 'pedido_localizacoes', "
    CREATE TABLE `pedido_localizacoes` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `pedido_id` INT NOT NULL,
        `guincho_id` INT NOT NULL,
        `usuario_id` INT NOT NULL,
        `fase` VARCHAR(32) NOT NULL,
        `sequence_number` INT NOT NULL,
        `client_point_id` VARCHAR(80) NOT NULL,
        `latitude` DECIMAL(10,8) NOT NULL,
        `longitude` DECIMAL(11,8) NOT NULL,
        `accuracy_m` DECIMAL(10,2) NULL,
        `speed_mps` DECIMAL(10,2) NULL,
        `heading_deg` DECIMAL(10,2) NULL,
        `device_timestamp` BIGINT NULL,
        `server_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `previous_point_id` BIGINT UNSIGNED NULL,
        `distance_raw_m` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `distance_validated_m` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `distance_accumulated_m` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `elapsed_ms` BIGINT NULL,
        `calculated_speed_kmh` DECIMAL(10,2) NULL,
        `street_name` VARCHAR(255) NULL,
        `street_source` VARCHAR(64) NULL,
        `match_confidence` DECIMAL(5,2) NULL,
        `is_valid` TINYINT(1) NOT NULL DEFAULT 1,
        `rejection_code` VARCHAR(32) NULL,
        `hash_previous` CHAR(64) NULL,
        `hash_current` CHAR(64) NULL,
        `request_id` VARCHAR(64) NULL,
        `run_id` VARCHAR(64) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_pedido_client_point` (`pedido_id`, `client_point_id`),
        UNIQUE KEY `uk_pedido_sequence` (`pedido_id`, `sequence_number`),
        KEY `idx_pedido_ts` (`pedido_id`, `server_timestamp`),
        KEY `idx_guincho_ts` (`guincho_id`, `server_timestamp`),
        KEY `idx_pedido_fase_valid` (`pedido_id`, `fase`, `is_valid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── pedido_percurso_resumos ─────────────────────────────────────────────────
createTable($pdo, $db, 'pedido_percurso_resumos', "
    CREATE TABLE `pedido_percurso_resumos` (
        `pedido_id` INT NOT NULL,
        `fase` VARCHAR(32) NOT NULL,
        `total_points` INT NOT NULL DEFAULT 0,
        `valid_points` INT NOT NULL DEFAULT 0,
        `rejected_points` INT NOT NULL DEFAULT 0,
        `started_at` DATETIME NULL,
        `last_point_at` DATETIME NULL,
        `duration_seconds` INT NOT NULL DEFAULT 0,
        `distance_raw_m` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `distance_validated_m` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `max_gap_seconds` INT NOT NULL DEFAULT 0,
        `max_speed_kmh` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `tracking_quality` VARCHAR(32) NOT NULL DEFAULT 'unknown',
        `last_street` VARCHAR(255) NULL,
        `last_latitude` DECIMAL(10,8) NULL,
        `last_longitude` DECIMAL(11,8) NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`pedido_id`, `fase`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── pedido_evidencias ───────────────────────────────────────────────────────
createTable($pdo, $db, 'pedido_evidencias', "
    CREATE TABLE `pedido_evidencias` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `pedido_id` INT NOT NULL,
        `guincho_id` INT NOT NULL,
        `tipo` ENUM('coleta','entrega') NOT NULL,
        `status` ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'accepted',
        `nonce_token` VARCHAR(255) NOT NULL,
        `nonce_expires_at` DATETIME NOT NULL,
        `point_id` BIGINT UNSIGNED NOT NULL,
        `latitude` DECIMAL(10,8) NOT NULL,
        `longitude` DECIMAL(11,8) NOT NULL,
        `accuracy_m` DECIMAL(10,2) NULL,
        `device_timestamp` BIGINT NULL,
        `server_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `original_name` VARCHAR(255) NOT NULL,
        `stored_name` VARCHAR(255) NOT NULL,
        `mime_type` VARCHAR(120) NOT NULL,
        `size_bytes` BIGINT UNSIGNED NOT NULL,
        `sha256` CHAR(64) NOT NULL,
        `metadata_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_pedido_tipo` (`pedido_id`, `tipo`, `status`),
        KEY `idx_point_id` (`point_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── pedido_cancelamentos ─────────────────────────────────────────────────────
// Pacote L1.6: essa tabela foi originalmente criada por migration_fix_cancelamento_polling.sql,
// mas ficou registrada como "aplicada" em schema_migrations sem existir de fato no banco
// (drift real encontrado em produção). Garantida aqui de forma idempotente para não
// depender do histórico de auditoria de uma migration antiga.
createTable($pdo, $db, 'pedido_cancelamentos', "
    CREATE TABLE `pedido_cancelamentos` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `pedido_id` INT NOT NULL,
        `ator_tipo` ENUM('cliente','guincho','admin','sistema') NOT NULL,
        `ator_id` INT NOT NULL,
        `motivo` VARCHAR(1000) NULL,
        `status_anterior` VARCHAR(40) NULL,
        `penalidade` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        `ip` VARCHAR(64) NULL,
        `user_agent` VARCHAR(255) NULL,
        `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_pedido_cancelamentos_pedido` (`pedido_id`),
        CONSTRAINT `fk_pedido_cancelamentos_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── cancelamento_snapshots (pacote L1.6) ──────────────────────────────────────
createTable($pdo, $db, 'cancelamento_snapshots', "
    CREATE TABLE `cancelamento_snapshots` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `pedido_id` INT NOT NULL,
        `actor_type` ENUM('cliente','guincho','admin','sistema') NOT NULL,
        `actor_id` INT NOT NULL,
        `formula_version` VARCHAR(20) NOT NULL DEFAULT 'v1',
        `factors_json` TEXT NULL,
        `por_quality` VARCHAR(20) NULL,
        `fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `refund_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `snapshot_hash` CHAR(64) NOT NULL,
        `status` ENUM('pending','confirmed','expired','superseded') NOT NULL DEFAULT 'pending',
        `expires_at` DATETIME NOT NULL,
        `confirmed_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_pedido_status` (`pedido_id`, `status`),
        KEY `idx_expires_at` (`expires_at`),
        CONSTRAINT `fk_cancel_snapshot_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── payout_ledger_entries (pacote L1.7) ───────────────────────────────────────
createTable($pdo, $db, 'payout_ledger_entries', "
    CREATE TABLE `payout_ledger_entries` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `pagamento_id` INT NOT NULL,
        `pedido_id` INT NOT NULL,
        `entry_type` ENUM('credito_guincho','credito_plataforma','debito_repasse_guincho','estorno_credito_guincho','estorno_credito_plataforma') NOT NULL,
        `valor` DECIMAL(10,2) NOT NULL,
        `referencia_externa` VARCHAR(150) NULL,
        `metadata_json` TEXT NULL,
        `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_pagamento` (`pagamento_id`),
        KEY `idx_pedido` (`pedido_id`),
        KEY `idx_tipo_criado` (`entry_type`, `criado_em`),
        CONSTRAINT `fk_payout_ledger_pagamento` FOREIGN KEY (`pagamento_id`) REFERENCES `pagamentos` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_payout_ledger_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ═════════════════════════════════════════════════════════════════════════════
out('');
out('── FASE 2: Colunas faltantes ────────────────────────────────────────────');

// usuarios
addCol($pdo, $db, 'usuarios', 'atualizado_em', 'DATETIME NULL');

// guinchos — renames de nomes antigos
renameCol($pdo, $db, 'guinchos', 'capacidade_kg',  'capacidade_ton', 'DECIMAL(5,2) NOT NULL DEFAULT 0');
renameCol($pdo, $db, 'guinchos', 'latitude_atual', 'lat_atual',      'DECIMAL(10,8) NULL');
renameCol($pdo, $db, 'guinchos', 'longitude_atual','lng_atual',       'DECIMAL(11,8) NULL');
// guinchos — colunas novas
addCol($pdo, $db, 'guinchos', 'capacidade_ton',  'DECIMAL(5,2) NOT NULL DEFAULT 0');
addCol($pdo, $db, 'guinchos', 'lat_atual',        'DECIMAL(10,8) NULL');
addCol($pdo, $db, 'guinchos', 'lng_atual',        'DECIMAL(11,8) NULL');
addCol($pdo, $db, 'guinchos', 'modelo_veiculo',   'VARCHAR(120) NULL');
addCol($pdo, $db, 'guinchos', 'ano_veiculo',      'INT NULL');
addCol($pdo, $db, 'guinchos', 'cnh_frente',       'VARCHAR(255) NULL');
addCol($pdo, $db, 'guinchos', 'cnh_verso',        'VARCHAR(255) NULL');
// guinchos — drift real encontrado no L1.10 (Playwright): migration_add_guincho_manual_location.sql
// e migration_add_guincho_profile_extras.sql estavam "já auditadas" em schema_migrations mas as
// colunas físicas não existiam (mesma classe de drift já corrigida antes para pedido_cancelamentos,
// cancelamento_snapshots e payout_ledger_entries). Sem lat_operacao/lng_operacao/cidade_placa/uf_placa/
// foto_caminhao, GuinchoController::atualizarPerfil() quebrava com "SQLSTATE[42S22]: Column not found".
addCol($pdo, $db, 'guinchos', 'lat_operacao',     'DECIMAL(10,8) NULL');
addCol($pdo, $db, 'guinchos', 'lng_operacao',     'DECIMAL(10,8) NULL');
addCol($pdo, $db, 'guinchos', 'cidade_placa',     'VARCHAR(100) NULL');
addCol($pdo, $db, 'guinchos', 'uf_placa',         'VARCHAR(2) NULL');
addCol($pdo, $db, 'guinchos', 'foto_caminhao',    'VARCHAR(255) NULL');
// veiculos — mesmo drift de migration_add_plate_emplacamento.sql
addCol($pdo, $db, 'veiculos', 'cidade_placa',     'VARCHAR(100) NULL');
addCol($pdo, $db, 'veiculos', 'uf_placa',         'VARCHAR(2) NULL');

// pagamentos — colunas adicionadas por migration_fix (FIX 4 e FIX 7)
addCol($pdo, $db, 'pagamentos', 'metodo',                 "ENUM('mercadopago','pagseguro') NOT NULL DEFAULT 'mercadopago'", 'pedido_id');
addCol($pdo, $db, 'pagamentos', 'valor_total',             'DECIMAL(10,2) NOT NULL DEFAULT 0.00', 'metodo');
addCol($pdo, $db, 'pagamentos', 'valor_guincho',           'DECIMAL(10,2) NOT NULL DEFAULT 0.00', 'valor_total');
addCol($pdo, $db, 'pagamentos', 'valor_plataforma',        'DECIMAL(10,2) NOT NULL DEFAULT 0.00', 'valor_guincho');
addCol($pdo, $db, 'pagamentos', 'id_externo',              'VARCHAR(100) NULL',                   'valor_plataforma');
addCol($pdo, $db, 'pagamentos', 'pago_guincho',            'TINYINT(1) NOT NULL DEFAULT 0',        'id_externo');
addCol($pdo, $db, 'pagamentos', 'data_pagamento',          'DATETIME NULL',                        'pago_guincho');
addCol($pdo, $db, 'pagamentos', 'data_pagamento_guincho',  'DATETIME NULL',                        'data_pagamento');
addCol($pdo, $db, 'pagamentos', 'id_transacao_pix',        'VARCHAR(100) NULL',                    'data_pagamento_guincho');
addCol($pdo, $db, 'pagamentos', 'status_pix',              "ENUM('pendente','processando','concluido','falha','falha_permanente') NOT NULL DEFAULT 'pendente'", 'id_transacao_pix');
addCol($pdo, $db, 'pagamentos', 'webhook_payload',         'TEXT NULL',                            'status_pix');
addCol($pdo, $db, 'pagamentos', 'criado_em',               'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

// logs_webhook — adiciona colunas que o código usa (pode ter nomes antigos: origem, dados, codigo_http)
addCol($pdo, $db, 'logs_webhook', 'fonte',       'VARCHAR(30) NOT NULL DEFAULT \'webhook\'');
addCol($pdo, $db, 'logs_webhook', 'payload',     'MEDIUMTEXT NULL');
addCol($pdo, $db, 'logs_webhook', 'status_http', 'SMALLINT NOT NULL DEFAULT 200');

// app_logs — observabilidade v2
addCol($pdo, $db, 'app_logs', 'application', 'VARCHAR(32) NOT NULL DEFAULT \'GUINCHAFACIL\'', 'level');
addCol($pdo, $db, 'app_logs', 'file',        'VARCHAR(255) NULL', 'func');
addCol($pdo, $db, 'app_logs', 'phase',       'VARCHAR(120) NULL', 'file');
addCol($pdo, $db, 'app_logs', 'code',        'VARCHAR(64) NULL', 'phase');
addCol($pdo, $db, 'app_logs', 'request_id',  'VARCHAR(64) NULL', 'code');
addCol($pdo, $db, 'app_logs', 'run_id',      'VARCHAR(64) NULL', 'request_id');
addCol($pdo, $db, 'app_logs', 'pedido_id',   'BIGINT NULL', 'run_id');
addCol($pdo, $db, 'app_logs', 'usuario_id',  'BIGINT NULL', 'pedido_id');
addCol($pdo, $db, 'app_logs', 'guincho_id',  'BIGINT NULL', 'usuario_id');
addCol($pdo, $db, 'app_logs', 'duration_ms', 'INT NULL', 'guincho_id');

// rate_limit
addCol($pdo, $db, 'rate_limit', 'criado_em',     'DATETIME DEFAULT CURRENT_TIMESTAMP');
addCol($pdo, $db, 'rate_limit', 'atualizado_em', 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

// pedidos — colunas de compatibilidade
addCol($pdo, $db, 'pedidos', 'custo_final',       'DECIMAL(10,2) NULL');
addCol($pdo, $db, 'pedidos', 'expiracao_aceite',  'DATETIME NULL');
addCol($pdo, $db, 'pedidos', 'raio_atual_km',     'INT DEFAULT 10');
addCol($pdo, $db, 'pedidos', 'score_minimo_atual','DECIMAL(5,4) DEFAULT 0.5000');
addCol($pdo, $db, 'pedidos', 'foto_plataforma',   'VARCHAR(255) NULL');
addCol($pdo, $db, 'pedidos', 'foto_destino',      'VARCHAR(255) NULL');
addCol($pdo, $db, 'pedidos', 'cancelado_por',     'VARCHAR(32) NULL');
addCol($pdo, $db, 'pedidos', 'motivo_cancelamento','VARCHAR(255) NULL');
addCol($pdo, $db, 'pedidos', 'taxa_cancelamento', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
addCol($pdo, $db, 'pedidos', 'cancelado_em',      'DATETIME NULL');

// guinchos — antifraude/cancelamento
addCol($pdo, $db, 'guinchos', 'total_cancelamentos', 'INT NOT NULL DEFAULT 0');

// simulation_runs
addCol($pdo, $db, 'simulation_runs', 'engine',             "VARCHAR(32) NOT NULL DEFAULT 'php_internal'");
addCol($pdo, $db, 'simulation_runs', 'suite',              'VARCHAR(120) NULL', 'engine');
addCol($pdo, $db, 'simulation_runs', 'requested_by',       'INT UNSIGNED NULL', 'suite');
addCol($pdo, $db, 'simulation_runs', 'requested_at',       'DATETIME NULL', 'requested_by');
addCol($pdo, $db, 'simulation_runs', 'target_environment', 'VARCHAR(64) NULL', 'requested_at');
addCol($pdo, $db, 'simulation_runs', 'target_url',         'VARCHAR(255) NULL', 'target_environment');
addCol($pdo, $db, 'simulation_runs', 'browser',            'VARCHAR(32) NULL', 'target_url');
addCol($pdo, $db, 'simulation_runs', 'viewport',           'VARCHAR(64) NULL', 'browser');
addCol($pdo, $db, 'simulation_runs', 'locale',             'VARCHAR(16) NULL', 'viewport');
addCol($pdo, $db, 'simulation_runs', 'timezone',           'VARCHAR(64) NULL', 'locale');
addCol($pdo, $db, 'simulation_runs', 'worker_id',          'VARCHAR(120) NULL', 'timezone');
addCol($pdo, $db, 'simulation_runs', 'worker_pid',         'INT NULL', 'worker_id');
addCol($pdo, $db, 'simulation_runs', 'heartbeat_at',       'DATETIME NULL', 'worker_pid');
addCol($pdo, $db, 'simulation_runs', 'started_at',         'DATETIME NULL', 'heartbeat_at');
addCol($pdo, $db, 'simulation_runs', 'finished_at',        'DATETIME NULL', 'started_at');
addCol($pdo, $db, 'simulation_runs', 'exit_code',          'INT NULL', 'finished_at');
addCol($pdo, $db, 'simulation_runs', 'configuration_json', 'LONGTEXT NULL', 'exit_code');
addCol($pdo, $db, 'simulation_runs', 'summary_json',       'LONGTEXT NULL', 'configuration_json');
addCol($pdo, $db, 'simulation_runs', 'app_version',        'VARCHAR(64) NULL', 'summary_json');
addCol($pdo, $db, 'simulation_runs', 'git_commit',         'VARCHAR(64) NULL', 'app_version');

// simulation_steps
addCol($pdo, $db, 'simulation_steps', 'system',        'VARCHAR(64) NULL', 'fase');
addCol($pdo, $db, 'simulation_steps', 'class',         'VARCHAR(120) NULL', 'system');
addCol($pdo, $db, 'simulation_steps', 'function',      'VARCHAR(120) NULL', 'class');
addCol($pdo, $db, 'simulation_steps', 'file',          'VARCHAR(255) NULL', 'function');
addCol($pdo, $db, 'simulation_steps', 'phase',         'VARCHAR(120) NULL', 'file');
addCol($pdo, $db, 'simulation_steps', 'code',          'VARCHAR(64) NULL', 'phase');
addCol($pdo, $db, 'simulation_steps', 'status',        'VARCHAR(24) NULL', 'code');
addCol($pdo, $db, 'simulation_steps', 'duration_ms',   'INT NULL', 'status');
addCol($pdo, $db, 'simulation_steps', 'expected_json', 'LONGTEXT NULL', 'duration_ms');
addCol($pdo, $db, 'simulation_steps', 'actual_json',   'LONGTEXT NULL', 'expected_json');
addCol($pdo, $db, 'simulation_steps', 'error_message', 'LONGTEXT NULL', 'actual_json');
addCol($pdo, $db, 'simulation_steps', 'stack_trace',   'LONGTEXT NULL', 'error_message');
addCol($pdo, $db, 'simulation_steps', 'started_at',    'DATETIME NULL', 'stack_trace');
addCol($pdo, $db, 'simulation_steps', 'finished_at',   'DATETIME NULL', 'started_at');

// simulation_artifacts
addCol($pdo, $db, 'simulation_artifacts', 'step_id',      'BIGINT UNSIGNED NULL', 'run_id');
addCol($pdo, $db, 'simulation_artifacts', 'kind',         'VARCHAR(32) NOT NULL', 'step_id');
addCol($pdo, $db, 'simulation_artifacts', 'filename',     'VARCHAR(255) NOT NULL', 'kind');
addCol($pdo, $db, 'simulation_artifacts', 'private_path', 'VARCHAR(500) NOT NULL', 'filename');
addCol($pdo, $db, 'simulation_artifacts', 'mime_type',    'VARCHAR(120) NULL', 'private_path');
addCol($pdo, $db, 'simulation_artifacts', 'size_bytes',   'BIGINT UNSIGNED NULL', 'mime_type');
addCol($pdo, $db, 'simulation_artifacts', 'sha256',       'CHAR(64) NULL', 'size_bytes');
addCol($pdo, $db, 'simulation_artifacts', 'created_at',   'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'sha256');
addCol($pdo, $db, 'geocoding_cache', 'cache_key',     'VARCHAR(191) NOT NULL');
addCol($pdo, $db, 'geocoding_cache', 'tipo',          "ENUM('forward','reverse','cep') NOT NULL DEFAULT 'forward'", 'cache_key');
addCol($pdo, $db, 'geocoding_cache', 'query_text',    'VARCHAR(255) NULL', 'tipo');
addCol($pdo, $db, 'geocoding_cache', 'latitude',      'DECIMAL(10,8) NULL', 'query_text');
addCol($pdo, $db, 'geocoding_cache', 'longitude',     'DECIMAL(11,8) NULL', 'latitude');
addCol($pdo, $db, 'geocoding_cache', 'response_json', 'LONGTEXT NULL', 'longitude');
addCol($pdo, $db, 'geocoding_cache', 'expires_at',    'DATETIME NULL', 'response_json');
addCol($pdo, $db, 'geocoding_cache', 'created_at',    'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'expires_at');
addCol($pdo, $db, 'geocoding_cache', 'updated_at',    'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'created_at');

// ═════════════════════════════════════════════════════════════════════════════
out('');
out('── FASE 3: Corrigir ENUMs ───────────────────────────────────────────────');

// pedidos.status — garante que os valores canônicos da Constituição estão presentes
if (!enumContains($pdo, $db, 'pedidos', 'status', 'a_caminho')) {
    try {
        $pdo->exec("ALTER TABLE `pedidos` MODIFY COLUMN `status`
            ENUM('aguardando_pagamento','aguardando_guincho','a_caminho','no_local','em_reboque','concluido','cancelado')
            COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aguardando_pagamento'");
        out("[FIX]  pedidos.status ENUM atualizado para valores canônicos");
    } catch (Throwable $e) {
        fail("ENUM pedidos.status: " . $e->getMessage());
    }
} else {
    out("[OK]   pedidos.status ENUM já contém 'a_caminho'");
}

// pagamentos.status — pacote L1.7: 'estornando' é um estado transitório de claim
// atômico (evita duplo refund quando o EstornoService trava a linha antes de
// chamar a API externa do gateway).
if (!enumContains($pdo, $db, 'pagamentos', 'status', 'estornando')) {
    try {
        $pdo->exec("ALTER TABLE `pagamentos` MODIFY COLUMN `status`
            ENUM('pendente','aprovado','recusado','estornado','estornando')
            COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente'");
        out("[FIX]  pagamentos.status inclui 'estornando'");
    } catch (Throwable $e) {
        fail("ENUM pagamentos.status: " . $e->getMessage());
    }
} else {
    out("[OK]   pagamentos.status já contém 'estornando'");
}

// pagamentos.status_pix — garante estado final de retry/escalação
if (!enumContains($pdo, $db, 'pagamentos', 'status_pix', 'falha_permanente')) {
    try {
        $pdo->exec("ALTER TABLE `pagamentos` MODIFY COLUMN `status_pix`
            ENUM('pendente','processando','concluido','falha','falha_permanente')
            COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente'");
        out("[FIX]  pagamentos.status_pix inclui 'falha_permanente'");
    } catch (Throwable $e) {
        fail("ENUM pagamentos.status_pix: " . $e->getMessage());
    }
} else {
    out("[OK]   pagamentos.status_pix já contém 'falha_permanente'");
}

// ═════════════════════════════════════════════════════════════════════════════
out('');
out('── FASE 4: Índices e Foreign Keys ──────────────────────────────────────');

addIndex($pdo, $db, 'pagamentos', 'idx_pagamentos_id_externo', '`id_externo`');
addIndex($pdo, $db, 'pagamentos', 'idx_pagamentos_status',     '`status`');
// Pacote L1.3 — documenta formalmente a regra "1 pagamento por pedido", que já
// existia como drift de schema (constraint aplicada fora do runner de migrations)
// e foi confirmada real durante a correção de testes. addUniqueIndex() é
// idempotente: se já existir uma UNIQUE equivalente, esta apenas garante que o
// runner conhece e versiona a regra a partir de agora.
addUniqueIndex($pdo, $db, 'pagamentos', 'uk_pagamentos_pedido_id', '`pedido_id`');
addUniqueIndex($pdo, $db, 'payment_jobs', 'uk_payment_jobs_idempotency', '`idempotency_key`');
addIndex($pdo, $db, 'payment_jobs', 'idx_payment_jobs_status_available', '`status`, `available_at`');
addIndex($pdo, $db, 'payment_jobs', 'idx_payment_jobs_pedido', '`pedido_id`');
addIndex($pdo, $db, 'payment_job_attempts', 'idx_payment_job_attempts_job', '`payment_job_id`');
addIndex($pdo, $db, 'pedidos',    'idx_pedidos_guincho_id',    '`guincho_id`');
addIndex($pdo, $db, 'avaliacoes', 'idx_avaliacoes_guincho_id', '`guincho_id`');
addIndex($pdo, $db, 'app_logs',   'idx_app_logs_system_created', '`system`, `criado_em`');
addIndex($pdo, $db, 'app_logs',   'idx_app_logs_code_created',   '`code`, `criado_em`');
addIndex($pdo, $db, 'app_logs',   'idx_app_logs_request_id',     '`request_id`');
addIndex($pdo, $db, 'app_logs',   'idx_app_logs_run_id',         '`run_id`');
addIndex($pdo, $db, 'app_logs',   'idx_app_logs_pedido_created', '`pedido_id`, `criado_em`');
addIndex($pdo, $db, 'simulation_runs', 'idx_engine_status',    '`engine`, `status`');
addUniqueIndex($pdo, $db, 'simulation_runs', 'uk_run_id',      '`run_id`');
addIndex($pdo, $db, 'simulation_steps', 'idx_run_id',          '`run_id`');
addIndex($pdo, $db, 'simulation_steps', 'idx_criado',          '`criado_em`');
addIndex($pdo, $db, 'simulation_artifacts', 'idx_run_id',      '`run_id`');
addIndex($pdo, $db, 'simulation_artifacts', 'idx_step_id',     '`step_id`');
addUniqueIndex($pdo, $db, 'geocoding_cache', 'uk_cache_key',   '`cache_key`');
addIndex($pdo, $db, 'geocoding_cache', 'idx_tipo_expires',     '`tipo`, `expires_at`');
addIndex($pdo, $db, 'pedido_localizacoes', 'idx_pedido_ts',    '`pedido_id`, `server_timestamp`');
addIndex($pdo, $db, 'pedido_localizacoes', 'idx_guincho_ts',   '`guincho_id`, `server_timestamp`');
addIndex($pdo, $db, 'pedido_localizacoes', 'idx_pedido_fase_valid', '`pedido_id`, `fase`, `is_valid`');
addUniqueIndex($pdo, $db, 'pedido_localizacoes', 'uk_pedido_client_point', '`pedido_id`, `client_point_id`');
addUniqueIndex($pdo, $db, 'pedido_localizacoes', 'uk_pedido_sequence', '`pedido_id`, `sequence_number`');
addIndex($pdo, $db, 'pedido_evidencias', 'idx_pedido_tipo',    '`pedido_id`, `tipo`, `status`');
addIndex($pdo, $db, 'pedido_evidencias', 'idx_point_id',       '`point_id`');
dedupeRateLimit($pdo, $db);
addUniqueIndex($pdo, $db, 'rate_limit', 'uk_ip_rota', '`ip`, `rota`');

addFk($pdo, $db, 'enderecos',         'fk_enderecos_usuario',       'FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'veiculos',          'fk_veiculos_usuario',        'FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'oficinas_favoritas','fk_oficinas_usuario',        'FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'guinchos',          'fk_guinchos_usuario',        'FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'pedidos',           'fk_pedidos_cliente',         'FOREIGN KEY (cliente_id)  REFERENCES usuarios(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'pedidos',           'fk_pedidos_veiculo',         'FOREIGN KEY (veiculo_id)  REFERENCES veiculos(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'pedidos',           'fk_pedidos_guincho',         'FOREIGN KEY (guincho_id)  REFERENCES guinchos(id)  ON DELETE SET NULL');
addFk($pdo, $db, 'pagamentos',        'fk_pagamentos_pedido',       'FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE');
addFk($pdo, $db, 'payment_job_attempts', 'fk_payment_job_attempts_job', 'FOREIGN KEY (payment_job_id) REFERENCES payment_jobs(id) ON DELETE CASCADE');
addFk($pdo, $db, 'avaliacoes',        'fk_avaliacoes_pedido',       'FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE');
addFk($pdo, $db, 'avaliacoes',        'fk_avaliacoes_cliente',      'FOREIGN KEY (cliente_id)  REFERENCES usuarios(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'avaliacoes',        'fk_avaliacoes_guincho',      'FOREIGN KEY (guincho_id)  REFERENCES guinchos(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'chat_mensagens',    'fk_chat_pedido',             'FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE');
addFk($pdo, $db, 'chat_mensagens',    'fk_chat_usuario',            'FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'simulation_artifacts', 'fk_simulation_artifacts_step', 'FOREIGN KEY (step_id) REFERENCES simulation_steps(id) ON DELETE SET NULL');
addFk($pdo, $db, 'pedido_localizacoes', 'fk_por_pedido',            'FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE');
addFk($pdo, $db, 'pedido_localizacoes', 'fk_por_guincho',           'FOREIGN KEY (guincho_id)  REFERENCES guinchos(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'pedido_localizacoes', 'fk_por_usuario',           'FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'pedido_evidencias',   'fk_evd_pedido',            'FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE');
addFk($pdo, $db, 'pedido_evidencias',   'fk_evd_guincho',           'FOREIGN KEY (guincho_id)  REFERENCES guinchos(id)  ON DELETE CASCADE');
addFk($pdo, $db, 'pedido_evidencias',   'fk_evd_point',             'FOREIGN KEY (point_id)    REFERENCES pedido_localizacoes(id) ON DELETE RESTRICT');

// ═════════════════════════════════════════════════════════════════════════════
out('');
out('── FASE 4.5: Cron heartbeat e retenção ───────────────────────────────────');

createTable($pdo, $db, 'cron_jobs', "
    CREATE TABLE `cron_jobs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `job_code` VARCHAR(120) NOT NULL,
        `descricao` VARCHAR(255) NULL,
        `schedule_hint` VARCHAR(120) NULL,
        `tolerancia_atraso_min` INT NOT NULL DEFAULT 15,
        `ativo` TINYINT(1) NOT NULL DEFAULT 1,
        `ultima_execucao_inicio` DATETIME NULL,
        `ultima_execucao_fim` DATETIME NULL,
        `ultima_execucao_status` ENUM('running','ok','warning','error') NULL,
        `ultima_mensagem` TEXT NULL,
        `ultimo_duration_ms` INT NULL,
        `ultima_execucao_metrics_json` LONGTEXT NULL,
        `heartbeat_at` DATETIME NULL,
        `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_cron_jobs_code` (`job_code`),
        KEY `idx_cron_jobs_heartbeat` (`heartbeat_at`),
        KEY `idx_cron_jobs_status` (`ultima_execucao_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

createTable($pdo, $db, 'cron_executions', "
    CREATE TABLE `cron_executions` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `job_code` VARCHAR(120) NOT NULL,
        `status` ENUM('running','ok','warning','error') NOT NULL DEFAULT 'running',
        `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `finished_at` DATETIME NULL,
        `heartbeat_at` DATETIME NULL,
        `duration_ms` INT NULL,
        `message` TEXT NULL,
        `metrics_json` LONGTEXT NULL,
        KEY `idx_cron_exec_job_started` (`job_code`, `started_at`),
        KEY `idx_cron_exec_status_started` (`status`, `started_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

out('── FASE 5: Seeds obrigatórios ───────────────────────────────────────────');

out('[INFO] Admin padrão desabilitado por segurança. Crie o primeiro administrador via install/cli_install.php ou por fluxo seguro de bootstrap.');

// Configurações obrigatórias
$seeds = [
    ['tarifa_por_km',      '3.50',  'Valor cobrado por km rodado (R$)'],
    ['taxa_fixa',          '10.00', 'Taxa fixa de acionamento por pedido (R$)'],
    ['comissao_plataforma','0.20',  'Comissão da plataforma em decimal (ex: 0.20 = 20%)'],
    ['tempo_expiracao_min','5',     'Minutos para expirar aceite do guincho'],
    ['raio_inicial_km',    '10',    'Raio inicial de busca por guinchos (km)'],
    ['raio_maximo_km',     '50',    'Raio máximo de busca por guinchos (km)'],
    ['system_mode',        'production', 'Modo de operação do sistema (production, sandbox, freeflow)'],
    ['payment_required',   '1',     'Exigir pagamento antecipado (1=Sim, 0=Não)'],
    ['por_max_accuracy_m', '80',    'Precisão máxima aceita para pontos POR em metros'],
    ['por_max_speed_kmh',  '130',   'Velocidade máxima aceita para rastreamento POR'],
    ['por_max_gap_seconds','180',   'Lacuna máxima esperada entre pontos POR'],
    ['por_min_point_distance_m', '8', 'Distância mínima para acumular progresso POR'],
    ['por_arrival_radius_m', '150', 'Raio de geofence para chegada na origem'],
    ['por_destination_radius_m', '200', 'Raio de geofence para chegada no destino'],
    ['por_photo_gps_max_age_seconds', '300', 'Idade máxima do ponto GPS aceito para evidências'],
    ['retention_simulation_artifacts_days', '14', 'Dias de retenção dos artefatos Playwright e QA'],
    ['retention_simulation_runs_days', '30', 'Dias de retenção dos registros de execução QA'],
    ['retention_jsonl_logs_days', '30', 'Dias de retenção dos logs JSONL em disco'],
    ['retention_cron_executions_days', '60', 'Dias de retenção do histórico de execuções de cron'],
    ['retention_por_days', '180', 'Dias de retenção dos pontos POR para pedidos encerrados'],
    ['retention_evidencias_days', '365', 'Dias de retenção das evidências operacionais'],
    ['retention_chat_days', '365', 'Dias de retenção do chat operacional em pedidos encerrados'],
];
foreach ($seeds as [$chave, $valor, $desc]) {
    seed(
        $pdo, 'configuracoes',
        "chave = ?", [$chave],
        "INSERT INTO configuracoes (chave, valor, descricao) VALUES (?,?,?)",
        [$chave, $valor, $desc]
    );
}

$cronSeeds = [
    ['cron_cancelar_pedidos_expirados', 'Cancela pedidos expirados e tenta estorno.', '* * * * *', 3],
    ['cron_reprocessar_pix', 'Reprocessa payment jobs de repasse PIX.', '*/5 * * * *', 10],
    ['cron_limpar_tokens', 'Limpa tokens expirados de redefinição de senha.', '0 3 * * *', 1440],
    ['cron_limpar_logs', 'Limpa tabelas de logs e cache geográfico.', '30 0 * * *', 1440],
    ['cron_retencao_operacional', 'Limpa artefatos QA, traces e resíduos operacionais.', '30 1 * * *', 1440],
];
foreach ($cronSeeds as [$jobCode, $descricao, $scheduleHint, $tolerancia]) {
    seed(
        $pdo, 'cron_jobs',
        "job_code = ?", [$jobCode],
        "INSERT INTO cron_jobs (job_code, descricao, schedule_hint, tolerancia_atraso_min, ativo, criado_em, atualizado_em)
         VALUES (?, ?, ?, ?, 1, NOW(), NOW())",
        [$jobCode, $descricao, $scheduleHint, $tolerancia]
    );
}

// ═════════════════════════════════════════════════════════════════════════════
out('');
out('── FASE 6: Correções de dados ──────────────────────────────────────────');

// comissao_plataforma: se estiver como percentual (>1), converter para decimal
$row = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'comissao_plataforma'")->fetch();
if ($row && (float)$row['valor'] > 1) {
    $fixado = round((float)$row['valor'] / 100, 4);
    $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'comissao_plataforma'")->execute([$fixado]);
    out("[FIX]  comissao_plataforma: {$row['valor']} → {$fixado} (convertido de % para decimal)");
} else {
    out("[OK]   comissao_plataforma já está em formato decimal");
}

out('');
out('── FASE 7: Governança auditável de migrations ──────────────────────────');
auditSqlMigrations($pdo, __DIR__);

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// ═════════════════════════════════════════════════════════════════════════════
out('');
out('═══════════════════════════════════════════════════════════════════');
if ($erros === 0) {
    out('  ✓  Migração concluída com sucesso. Nenhum erro.');
} else {
    out("  ✗  Migração concluída com {$erros} erro(s). Verifique as linhas [ERRO] acima.");
}
out('═══════════════════════════════════════════════════════════════════');
out('');
out('Próximo passo: proteja esta pasta (/install) ou remova-a do servidor.');
out('');
