<?php
declare(strict_types=1);

// Runner idempotente da revisao de precos de pneu para Niteroi.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

$sqlFile = __DIR__ . '/migration_update_tire_prices_niteroi.sql';

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
        "SELECT spr.base_customer_price, spr.minimum_customer_price, spr.maximum_customer_price
           FROM service_price_rules spr
           JOIN pricing_zones pz ON pz.id = spr.pricing_zone_id
           JOIN service_types st ON st.id = spr.service_type_id
          WHERE pz.code = 'NITEROI_GERAL'
            AND st.code = 'TIRE_CHANGE'
            AND spr.vehicle_category IS NULL
            AND spr.version = 2
            AND spr.active = 1
          LIMIT 1"
    );
    $check->execute();
    $rule = $check->fetch(PDO::FETCH_ASSOC);

    if (!$rule) {
        throw new RuntimeException(
            'Regra nao aplicada: verifique se NITEROI_GERAL e TIRE_CHANGE existem.'
        );
    }

    printf(
        "Precos de pneu em Niteroi atualizados (idempotente): base R$ %.2f, faixa R$ %.2f-R$ %.2f.\n",
        (float) $rule['base_customer_price'],
        (float) $rule['minimum_customer_price'],
        (float) $rule['maximum_customer_price']
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro ao atualizar precos de pneu em Niteroi: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
