<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::pdo();
$tables = ['service_types', 'pricing_zones', 'service_price_rules'];
foreach ($tables as $table) {
    echo "Table: $table\n";
    $stmt = $db->query("SHOW FULL COLUMNS FROM $table");
    foreach ($stmt->fetchAll() as $col) {
        echo $col['Field'] . ": " . $col['Collation'] . "\n";
    }
    echo "\n";
}
