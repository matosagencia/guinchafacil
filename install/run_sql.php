<?php
// File: install/run_sql.php
// Instalador simples: executa guinchafacil.sql no banco configurado em ../config.php
// IMPORTANTE: rode UMA VEZ e depois APAGUE a pasta /install por segurança.

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

header('Content-Type: text/plain; charset=utf-8');

$token = $_GET['token'] ?? '';
$expected = defined('INSTALL_TOKEN') ? (string)INSTALL_TOKEN : '';
if ($expected !== '' && !hash_equals($expected, (string)$token)) {
    http_response_code(403);
    echo "403 Forbidden\n";
    exit;
}

$sqlFile = __DIR__ . '/../guinchafacil.sql';
if (!is_file($sqlFile)) {
    http_response_code(404);
    echo "SQL não encontrado: guinchafacil.sql (coloque o arquivo na raiz do projeto)\n";
    exit;
}

$pdo = getPDO();
$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    http_response_code(400);
    echo "SQL vazio ou ilegível\n";
    exit;
}

// Parser simples por ';' respeitando strings e comentários.
$stmts = [];
$buf = '';
$inStr = false;
$strCh = '';
$len = strlen($sql);
for ($i=0; $i<$len; $i++) {
    $ch = $sql[$i];
    $nx = $i+1 < $len ? $sql[$i+1] : "";

    // comenta linha --
    if (!$inStr && $ch === '-' && $nx === '-') {
        while ($i < $len && $sql[$i] !== "\n") $i++;
        continue;
    }
    // comenta bloco /* */
    if (!$inStr && $ch === '/' && $nx === '*') {
        $i += 2;
        while ($i < $len-1 && !($sql[$i] === '*' && $sql[$i+1] === '/')) $i++;
        $i++; // pula '/'
        continue;
    }

    if (!$inStr && ($ch === '"' || $ch === "'")) {
        $inStr = true; $strCh = $ch;
        $buf .= $ch;
        continue;
    }

    if ($inStr) {
        $buf .= $ch;
        // escape
        if ($ch === '\\') {
            if ($i+1 < $len) { $buf .= $sql[$i+1]; $i++; }
            continue;
        }
        if ($ch === $strCh) { $inStr = false; $strCh = ''; }
        continue;
    }

    if ($ch === ';') {
        $stmt = trim($buf);
        if ($stmt !== '') $stmts[] = $stmt;
        $buf = '';
        continue;
    }

    $buf .= $ch;
}
$last = trim($buf);
if ($last !== '') $stmts[] = $last;

$pdo->beginTransaction();
try {
    $ok = 0;
    foreach ($stmts as $s) {
        $pdo->exec($s);
        $ok++;
    }
    $pdo->commit();
    echo "OK: executadas {$ok} queries\n";
    echo "Próximo passo: apague a pasta /install\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "ERRO: " . $e->getMessage() . "\n";
}
