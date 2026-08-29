<?php
declare(strict_types=1);

/**
 * tools/limpar_ambiente_teste.php
 * §CELULAS-NITEROI-01 (05/08/2026): reset do ambiente de teste — mantém
 * SOMENTE os usuários de staff (admin/gerente/funcionario) e apaga tudo
 * que depende de cliente/guincho/pedido/pagamento, pra recomeçar o QA das
 * 5 células territoriais do zero. Preserva catálogo (marcas/modelos/
 * serviços), cidades, células/polígonos e configurações — nada disso
 * depende de usuário e não é tocado.
 *
 * Como decide o que apagar (sem lista fixa de tabelas, pra não esquecer
 * nenhuma das dezenas de tabelas que foram surgindo ao longo do projeto):
 *   1. Marca pra apagar: usuarios.tipo NOT IN ('admin','gerente','funcionario').
 *   2. Lê TODAS as foreign keys reais do banco via INFORMATION_SCHEMA.
 *   3. Faz o fechamento transitivo: qualquer tabela que referencia (direta
 *      ou indiretamente) `usuarios`, `guinchos`, `pedidos` ou `pagamentos`
 *      também entra na lista de limpeza, delimitada pelos IDs raiz.
 *   4. Roda os DELETE com FOREIGN_KEY_CHECKS=0 (evita ter que calcular a
 *      ordem topológica exata — mais simples e igualmente seguro pra um
 *      reset completo).
 *
 * Regra suprema do projeto: sem evidência, não está pronto / nunca destrutivo
 * sem prova. Por isso:
 *   - SEMPRE roda em modo leitura (dry-run) por padrão — só apaga com
 *     --confirm explícito.
 *   - Com --confirm, faz um `mysqldump` completo do banco ANTES de
 *     qualquer DELETE e ABORTA se o backup falhar ou vier vazio.
 *   - Cada tabela apagada é logada com a contagem de linhas removidas.
 *
 * Uso:
 *   php tools/limpar_ambiente_teste.php               (dry-run, não apaga nada)
 *   php tools/limpar_ambiente_teste.php --confirm      (faz backup + apaga)
 *   php tools/limpar_ambiente_teste.php --confirm --sem-backup  (pula o backup — não recomendado)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Acesso negado. Use o terminal.\n");
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/Logger.php';

$args = array_slice($argv, 1);
$confirm = in_array('--confirm', $args, true);
$semBackup = in_array('--sem-backup', $args, true);

$pdo = getPDO();
$dbName = DB_NAME;

echo "Modo: " . ($confirm ? "CONFIRM (vai apagar)" : "DRY-RUN (nada será apagado — use --confirm pra aplicar)") . "\n";
echo str_repeat('-', 70) . "\n";

// ── 1) Usuários a apagar: tudo que não for staff ────────────────────────
$tiposStaff = ['admin', 'gerente', 'funcionario'];
$placeholders = implode(',', array_fill(0, count($tiposStaff), '?'));
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE tipo NOT IN ({$placeholders})");
$stmt->execute($tiposStaff);
$idsUsuarios = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

$stmtTotalUsuarios = $pdo->query('SELECT COUNT(*) FROM usuarios');
$totalUsuarios = (int)$stmtTotalUsuarios->fetchColumn();

echo "Usuários no total: {$totalUsuarios}\n";
echo "Usuários staff (preservados): " . ($totalUsuarios - count($idsUsuarios)) . "\n";
echo "Usuários a apagar (cliente/guincho/outros): " . count($idsUsuarios) . "\n";

if (!$idsUsuarios) {
    echo "\nNada a apagar — só existem usuários de staff. Encerrando.\n";
    exit(0);
}

// ── 2) Fechamento transitivo via INFORMATION_SCHEMA ─────────────────────
// Mapa completo de FKs reais do banco: tabela_filha.coluna -> tabela_pai.coluna
$stmt = $pdo->prepare(
    "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL"
);
$stmt->execute([$dbName]);
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// tabela_pai -> [ [tabela_filha, coluna_filha], ... ]
$filhosPorPai = [];
foreach ($fks as $fk) {
    $pai = $fk['REFERENCED_TABLE_NAME'];
    $filhosPorPai[$pai][] = ['tabela' => $fk['TABLE_NAME'], 'coluna' => $fk['COLUMN_NAME']];
}

// Tabelas "raiz" cujas linhas específicas (por ID) determinam a purga.
// usuarios: só os IDs não-staff. guinchos/pedidos/pagamentos: TODOS os
// registros deles que sobrarem depois de resolver a cascata a partir de
// usuarios — descobertos dinamicamente abaixo, não hardcoded.
$idsPorTabela = ['usuarios' => $idsUsuarios];
// Fallback pra tabela filha sem coluna `id` própria (ex.: tabela de junção
// pura, não observada hoje no schema mas coberta por segurança): guarda a
// condição de DELETE direto por coluna FK, em vez de rastrear um "id" que
// não existe. tabela => [ ['coluna'=>..., 'valores'=>[...]], ... ]
$criteriosFkPorTabela = [];
$ordemProcessamento = ['usuarios'];
$i = 0;
while ($i < count($ordemProcessamento)) {
    $paiAtual = $ordemProcessamento[$i];
    $i++;
    $idsPai = $idsPorTabela[$paiAtual] ?? [];
    if (!$idsPai) {
        continue;
    }
    foreach ($filhosPorPai[$paiAtual] ?? [] as $filho) {
        $tabelaFilha = $filho['tabela'];
        $colunaFilha = $filho['coluna'];
        $ph = implode(',', array_fill(0, count($idsPai), '?'));
        $sql = "SELECT DISTINCT id FROM `{$tabelaFilha}` WHERE `{$colunaFilha}` IN ({$ph})";
        try {
            $s = $pdo->prepare($sql);
            $s->execute($idsPai);
            $idsFilha = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        } catch (\PDOException $e) {
            $idsFilha = null;
        }
        if ($idsFilha !== null) {
            $idsPorTabela[$tabelaFilha] = array_values(array_unique(array_merge($idsPorTabela[$tabelaFilha] ?? [], $idsFilha)));
        } else {
            // Sem `id`: registra o critério pra contar/apagar direto pela
            // FK, e ainda assim tenta continuar a cascata a partir dela
            // (BFS por essa tabela fica limitado a esse critério).
            $criteriosFkPorTabela[$tabelaFilha][] = ['coluna' => $colunaFilha, 'valores' => $idsPai];
        }
        if (!in_array($tabelaFilha, $ordemProcessamento, true)) {
            $ordemProcessamento[] = $tabelaFilha;
        }
    }
}

echo "\nTabelas atingidas pela limpeza (via FKs reais do banco):\n";
$totalLinhasAfetadas = 0;
foreach ($ordemProcessamento as $tabela) {
    if ($tabela === 'usuarios') {
        continue;
    }
    $qtd = isset($idsPorTabela[$tabela]) ? count($idsPorTabela[$tabela]) : 0;
    if ($qtd > 0) {
        echo "  - {$tabela}: {$qtd} linha(s)\n";
        $totalLinhasAfetadas += $qtd;
    } elseif (isset($criteriosFkPorTabela[$tabela])) {
        echo "  - {$tabela}: linhas serão apagadas por coluna FK direta (tabela sem `id` próprio)\n";
    }
}
echo "  - usuarios: " . count($idsUsuarios) . " linha(s)\n";
echo "\nTotal de linhas que seriam apagadas: " . ($totalLinhasAfetadas + count($idsUsuarios)) . " (+ linhas de tabelas sem `id`, ver acima)\n";
echo str_repeat('-', 70) . "\n";

if (!$confirm) {
    echo "\nDry-run concluído. Nada foi apagado. Rode com --confirm pra aplicar.\n";
    exit(0);
}

// ── 3) Backup obrigatório antes de apagar qualquer coisa ────────────────
if (!$semBackup) {
    $backupDir = dirname(__DIR__) . '/storage/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    $backupPath = $backupDir . '/backup_pre_limpeza_' . date('Y-m-d_His') . '.sql';

    $mysqldumpBin = getenv('MYSQLDUMP_BIN') ?: 'mysqldump';
    // No Windows, escapeshellarg() não protege corretamente senhas como
    // argumento de comando. MYSQL_PWD é herdado pelo mysqldump sem expor a
    // credencial na linha de comando.
    putenv('MYSQL_PWD=' . DB_PASS);
    $cmd = sprintf(
        '%s --host=%s --user=%s %s > %s 2>%s',
        escapeshellcmd($mysqldumpBin),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_NAME),
        escapeshellarg($backupPath),
        escapeshellarg($backupPath . '.err')
    );

    echo "Gerando backup em {$backupPath} ...\n";
    exec($cmd, $outputLinhas, $exitCode);

    $backupOk = $exitCode === 0 && is_file($backupPath) && filesize($backupPath) > 0;
    if (!$backupOk) {
        $erroDetalhe = is_file($backupPath . '.err') ? trim((string)file_get_contents($backupPath . '.err')) : '';
        fwrite(STDERR, "FALHA ao gerar backup (exit code {$exitCode}). {$erroDetalhe}\n");
        fwrite(STDERR, "Se o comando 'mysqldump' não estiver no PATH, defina a variável de ambiente MYSQLDUMP_BIN com o caminho completo (ex.: C:\\xampp\\mysql\\bin\\mysqldump.exe) e rode de novo.\n");
        fwrite(STDERR, "Nenhum dado foi apagado.\n");
        exit(1);
    }
    if (is_file($backupPath . '.err') && filesize($backupPath . '.err') === 0) {
        @unlink($backupPath . '.err');
    }
    echo "Backup OK: {$backupPath} (" . round(filesize($backupPath) / 1024, 1) . " KB)\n";
} else {
    echo "AVISO: rodando com --sem-backup — nenhum backup foi gerado.\n";
}

// ── 4) Apagar de fato, com FK checks desligado ───────────────────────────
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->beginTransaction();
try {
    $resumo = [];
    // Ordem inversa da descoberta (filhos antes dos pais) só por clareza de
    // log — com FOREIGN_KEY_CHECKS=0 a ordem não afeta a integridade.
    foreach (array_reverse($ordemProcessamento) as $tabela) {
        if ($tabela === 'usuarios') {
            continue;
        }
        $ids = $idsPorTabela[$tabela] ?? [];
        if (!$ids) {
            continue;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $apagado = $pdo->prepare("DELETE FROM `{$tabela}` WHERE id IN ({$ph})");
        $apagado->execute($ids);
        $resumo[$tabela] = $apagado->rowCount();
    }
    // Tabelas sem `id` próprio: apaga direto por cada critério de coluna FK.
    foreach ($criteriosFkPorTabela as $tabela => $criterios) {
        $totalTabela = 0;
        foreach ($criterios as $criterio) {
            $valores = $criterio['valores'];
            if (!$valores) {
                continue;
            }
            $ph = implode(',', array_fill(0, count($valores), '?'));
            $apagado = $pdo->prepare("DELETE FROM `{$tabela}` WHERE `{$criterio['coluna']}` IN ({$ph})");
            $apagado->execute($valores);
            $totalTabela += $apagado->rowCount();
        }
        $resumo[$tabela] = ($resumo[$tabela] ?? 0) + $totalTabela;
    }
    // $placeholders (topo do script) foi montado pro NOT IN de $tiposStaff —
    // aqui precisamos de um novo, do tamanho de $idsUsuarios, pro IN de IDs.
    $phIds = implode(',', array_fill(0, count($idsUsuarios), '?'));
    $stmtApagaUsuarios = $pdo->prepare("DELETE FROM usuarios WHERE id IN ({$phIds})");
    $stmtApagaUsuarios->execute($idsUsuarios);
    $resumo['usuarios'] = $stmtApagaUsuarios->rowCount();

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, "FALHA durante a limpeza, tudo revertido: " . $e->getMessage() . "\n");
    exit(1);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\nLimpeza concluída:\n";
foreach ($resumo as $tabela => $qtd) {
    echo "  - {$tabela}: {$qtd} linha(s) apagada(s)\n";
}

Logger::log(Logger::LEVEL_WARN, 'ToolLimparAmbienteTeste', 'run', 'admin',
    'Ambiente de teste limpo via tools/limpar_ambiente_teste.php — manteve só usuários de staff',
    ['resumo' => $resumo]);

echo "\nPronto. Ambiente com só usuários de staff (admin/gerente/funcionario).\n";
