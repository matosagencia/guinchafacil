<?php
// ============================================================
// Stage 1 Runner - preflight + migration precotacao_triagem_servicos
// Idempotente. Roda seguro multiplas vezes.
// ============================================================

require_once __DIR__ . '/../config.php';

echo str_repeat("=", 63) . "\n";
echo "  STAGE 1 - Preflight + Migration\n";
echo str_repeat("=", 63) . "\n\n";

$erros = [];

// --- 1. PHP ---
echo "[1] PHP\n";
echo "    versao: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    $erros[] = "PHP 8.0+ obrigatorio. Encontrado: " . PHP_VERSION;
    echo "    [FAIL] precisa ser 8.0+\n";
} else {
    echo "    [OK]\n";
}

// --- 2. Banco ---
echo "\n[2] Banco de dados\n";
try {
    $pdo = getPDO();
    $versao = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "    [OK] MySQL $versao\n";
} catch (Throwable $e) {
    echo "    [FAIL] " . $e->getMessage() . "\n";
    exit(1);
}

// --- 3. Tabelas-chave ---
echo "\n[3] Tabelas-chave\n";
$tabelas = ['usuarios','guinchos','oficinas','provider_workshop_settings','cidades','configuracoes','service_types','provider_capabilities'];
foreach ($tabelas as $t) {
    try {
        $ok = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
        if ($ok) {
            echo "    [OK] $t\n";
        } else {
            echo "    [WARN] $t NAO EXISTE\n";
        }
    } catch (Throwable $e) {
        echo "    [FAIL] $t -> " . $e->getMessage() . "\n";
    }
}

// --- 4. Migration: cria tabela ---
echo "\n[4] Criando tabela precotacao_triagem_servicos\n";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `precotacao_triagem_servicos` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(64) NOT NULL,
            `nome` VARCHAR(120) NOT NULL,
            `descricao` VARCHAR(255) DEFAULT NULL,
            `icone` VARCHAR(64) DEFAULT NULL,
            `oficina_tipo` VARCHAR(64) DEFAULT NULL,
            `service_type_code` VARCHAR(64) DEFAULT NULL,
            `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 100,
            `ativo` TINYINT(1) NOT NULL DEFAULT 0,
            `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_slug` (`slug`),
            KEY `idx_ativo_ordem` (`ativo`, `ordem`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "    [OK] tabela garantida\n";
} catch (Throwable $e) {
    $erros[] = "criar tabela: " . $e->getMessage();
    echo "    [FAIL] " . $e->getMessage() . "\n";
}

// --- 5. Seed ---
echo "\n[5] Seed dos servicos\n";
$seed = [
    ['pneu',          'Pneu',          'Furado, murcho ou danificado',    'fa-circle-notch',     'borracharia',   'TIRE_CHANGE',            10, 1],
    ['eletrica',      'Eletrica',      'Nao liga, painel apagado',        'fa-bolt',             'eletrica',      'ELECTRICAL_DIAGNOSIS',   20, 1],
    ['bateria',       'Bateria',       'Nao pega partida',                'fa-car-battery',      'bateria',       'JUMP_START',             30, 1],
    ['mecanica',      'Mecanica',      'Motor, freio ou suspensao',       'fa-gears',            'mecanica',      'MECHANICAL_ASSISTANCE',  40, 1],
    ['chaveiro',      'Chaveiro',      'Chave presa, perdida ou travada', 'fa-key',              'chaveiro',      'AUTOMOTIVE_LOCKSMITH',   50, 1],
    ['arrefecimento', 'Arrefecimento', 'Superaquecimento, vazamento',     'fa-temperature-half', 'arrefecimento', null,                     60, 0],
    ['suspensao',     'Suspensao',     'Amortecedor, mola, batente',      'fa-arrows-up-down',   'suspensao',     null,                     70, 0],
    ['freios',        'Freios',        'Pastilha, disco, fluido',         'fa-circle-stop',      'freios',        null,                     80, 0],
    ['transmissao',   'Transmissao',   'Cambio, embreagem',               'fa-gear',             'transmissao',   null,                     90, 0],
];
try {
    $stmt = $pdo->prepare("
        INSERT INTO `precotacao_triagem_servicos`
            (`slug`, `nome`, `descricao`, `icone`, `oficina_tipo`, `service_type_code`, `ordem`, `ativo`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `nome` = VALUES(`nome`),
            `descricao` = VALUES(`descricao`),
            `icone` = VALUES(`icone`),
            `oficina_tipo` = VALUES(`oficina_tipo`),
            `service_type_code` = VALUES(`service_type_code`),
            `ordem` = VALUES(`ordem`)
    ");
    $novos = 0; $atualizados = 0;
    foreach ($seed as $row) {
        $stmt->execute($row);
        $affected = $stmt->rowCount();
        if ($affected === 1) { $novos++; }
        elseif ($affected === 2) { $atualizados++; }
    }
    echo "    [OK] $novos novo(s), $atualizados atualizado(s)\n";
} catch (Throwable $e) {
    $erros[] = "seed: " . $e->getMessage();
    echo "    [FAIL] " . $e->getMessage() . "\n";
}

// --- 6. Flag ---
echo "\n[6] Flag precotacao_funil_v2\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM configuracoes")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
        $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
            ->execute(['precotacao_funil_v2', '0']);
        echo "    [OK] flag setada como 0 (desligada)\n";
    } else {
        echo "    [WARN] tabela configuracoes sem colunas chave/valor\n";
        echo "           colunas encontradas: " . implode(', ', $cols) . "\n";
    }
} catch (Throwable $e) {
    echo "    [WARN] " . $e->getMessage() . "\n";
}

// --- 7. Verificacao ---
echo "\n[7] Verificacao final\n";
try {
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM precotacao_triagem_servicos")->fetchColumn();
    $ativos  = (int)$pdo->query("SELECT COUNT(*) FROM precotacao_triagem_servicos WHERE ativo = 1")->fetchColumn();
    echo "    total: $total\n";
    echo "    ativos: $ativos\n";
    if ($total >= 5 && $ativos >= 5) {
        echo "    [OK] migration aplicada\n";
    } else {
        $erros[] = "esperado >= 5 servicos ativos, encontrado $ativos";
    }
} catch (Throwable $e) {
    $erros[] = "verificacao: " . $e->getMessage();
    echo "    [FAIL] " . $e->getMessage() . "\n";
}

// --- RESUMO ---
echo "\n" . str_repeat("=", 63) . "\n";
if (empty($erros)) {
    echo "  [PASS] STAGE 1 OK - pronto para STAGE 2\n";
    exit(0);
}
echo "  [FAIL] " . count($erros) . " erro(s):\n";
foreach ($erros as $e) { echo "    - $e\n"; }
exit(1);
