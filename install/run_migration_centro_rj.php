<?php
// Script de migração automatizada: Adicionar Zona Centro - RJ
// Executa o SQL definido em migration_add_zone_centro_rj.sql com segurança.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

try {
    // Refatorado: Usando Database::pdo() conforme definido em src/Database.php
    $db = Database::pdo();
    $sqlFile = __DIR__ . '/migration_add_zone_centro_rj.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL de migração não encontrado: " . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    
    // Executar a migração
    $db->exec($sql);
    
    echo "Migração executada: A zona 'Centro - Rio de Janeiro' foi verificada (idempotente).\n";
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
