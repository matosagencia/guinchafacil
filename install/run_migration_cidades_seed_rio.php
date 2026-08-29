<?php
// Script de migração: Seed cidade Rio de Janeiro e vinculação (refatorado para múltiplas queries)

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

try {
    $db = Database::pdo();
    $sqlFile = __DIR__ . '/migration_cidades_seed_rio.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL de migração não encontrado: " . $sqlFile);
    }

    $sqlContent = file_get_contents($sqlFile);
    
    // Divide o SQL por ponto e vírgula para executar um por um
    $queries = array_filter(array_map('trim', explode(';', $sqlContent)));

    foreach ($queries as $query) {
        if (empty($query)) continue;
        $db->exec($query);
    }
    
    echo "Migração executada: Cidade 'Rio de Janeiro' inserida/verificada e vinculada à zona 'Centro-RJ'.\n";
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
    exit(1);
}
