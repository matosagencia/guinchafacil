<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este runner so pode ser executado via CLI.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

$sqlFile = __DIR__ . '/migration_pricing_niteroi_rio.sql';

try {
    if (!is_readable($sqlFile)) {
        throw new RuntimeException('Migration nao encontrada ou sem leitura: ' . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Migration vazia ou impossivel de ler.');
    }

    $db = Database::pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $requiredZones = ['NITEROI_GERAL', 'centro-rj'];
    $placeholders = implode(',', array_fill(0, count($requiredZones), '?'));
    $zoneCheck = $db->prepare("SELECT code FROM pricing_zones WHERE code IN ($placeholders)");
    $zoneCheck->execute($requiredZones);
    $foundZones = $zoneCheck->fetchAll(PDO::FETCH_COLUMN);
    $missingZones = array_values(array_diff($requiredZones, $foundZones));
    if ($missingZones !== []) {
        throw new RuntimeException(
            'Zona(s) ausente(s): ' . implode(', ', $missingZones)
            . '. Aplique primeiro as migrations de cidades/zonas.'
        );
    }

    $db->exec($sql);

    $check = $db->query(
        "SELECT pz.code AS zone_code, COUNT(*) AS rule_count
           FROM service_price_rules spr
           JOIN pricing_zones pz ON pz.id = spr.pricing_zone_id
          WHERE pz.code IN ('NITEROI_GERAL', 'centro-rj')
            AND spr.vehicle_category IS NULL
            AND spr.version = 20260823
            AND spr.active = 1
          GROUP BY pz.code
          ORDER BY pz.code"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($requiredZones as $zone) {
        if ((int)($check[$zone] ?? 0) !== 12) {
            throw new RuntimeException("Validacao falhou em {$zone}: esperadas 12 regras ativas.");
        }
    }

    echo "Regras de Niteroi e Rio atualizadas com sucesso (versao 20260823).\n";
    echo "12 regras ativas por zona; reexecucao idempotente confirmada pelo desenho da migration.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro ao atualizar regras de preco: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
