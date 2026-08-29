<?php
// Script de migração: Atualizar Polígono da Zona Centro - RJ

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

try {
    $db = Database::pdo();
    $sqlFile = __DIR__ . '/migration_update_zone_centro_rj.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL de migração não encontrado: " . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    
    // Executar a migração
    $db->exec($sql);
    
    echo "Migração executada: Polígono da zona 'Centro - Rio de Janeiro' atualizado.\n";
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
