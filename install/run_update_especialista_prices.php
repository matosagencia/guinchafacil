<?php
declare(strict_types=1);

// Runner idempotente da revisao de precos do catalogo de especialistas.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

$sqlFile = __DIR__ . '/migration_especialista_catalogo_precos_v2.sql';

try {
    if (!is_file($sqlFile) || !is_readable($sqlFile)) {
        throw new RuntimeException('Arquivo SQL nao encontrado ou sem permissao de leitura: ' . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Arquivo SQL vazio ou impossivel de ler: ' . $sqlFile);
    }

    $db = Database::pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec($sql);

    $check = $db->prepare(
        "SELECT preco_atendimento, preco_adicional, adicional_noturno
           FROM servicos_especialista
          WHERE codigo = 'TIRE_CHANGE'
          LIMIT 1"
    );
    $check->execute();
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Servico TIRE_CHANGE nao encontrado no catalogo de especialistas.');
    }

    printf(
        "Precos do especialista atualizados: base R$ %.2f, adicional R$ %.2f, noturno R$ %.2f.\n",
        (float) $row['preco_atendimento'],
        (float) $row['preco_adicional'],
        (float) $row['adicional_noturno']
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro ao atualizar precos do catalogo de especialistas: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
