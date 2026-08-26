<?php
// Executor do SQL (PDO) com logs claros
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

function fail(string $phase, string $msg): void {
    http_response_code(500);
    echo "[FAIL][$phase] $msg\n";
    exit;
}

$sqlFile = __DIR__ . '/guinchafacil.sql';
if (!file_exists($sqlFile)) {
    fail('bootstrap', "Arquivo SQL não encontrado: $sqlFile");
}

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    fail('bootstrap', "Constantes DB_* não definidas no config.php (DB_HOST, DB_NAME, DB_USER, DB_PASS).");
}

$dropFirst = isset($_POST['drop_first']) && $_POST['drop_first'] === '1';

echo "[OK][bootstrap] Lendo SQL: $sqlFile\n";

$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    fail('read_sql', 'Não foi possível ler o arquivo SQL ou ele está vazio.');
}

// Remove comentários básicos
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$sql = preg_replace('/^\s*#.*$/m', '', $sql);

// Divide por ; respeitando strings (simples e bom o bastante pra dumps comuns)
$statements = [];
$buffer = '';
$inString = false;
$stringChar = '';

$len = strlen($sql);
for ($i = 0; $i < $len; $i++) {
    $ch = $sql[$i];
    $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

    if ($inString) {
        $buffer .= $ch;
        if ($ch === $stringChar) {
            // não fecha se for escape \'
            $prev = ($i > 0) ? $sql[$i - 1] : '';
            if ($prev !== '\\') {
                $inString = false;
                $stringChar = '';
            }
        }
        continue;
    }

    if ($ch === '\'' || $ch === '"') {
        $inString = true;
        $stringChar = $ch;
        $buffer .= $ch;
        continue;
    }

    if ($ch === ';') {
        $stmt = trim($buffer);
        $buffer = '';
        if ($stmt !== '') $statements[] = $stmt;
        continue;
    }

    $buffer .= $ch;
}

$tail = trim($buffer);
if ($tail !== '') $statements[] = $tail;

echo "[OK][parse] Statements: " . count($statements) . "\n";

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fail('pdo_connect', $e->getMessage());
}

echo "[OK][pdo_connect] Conectado em " . DB_HOST . "/" . DB_NAME . "\n";

// Opcional: DROP tables (perigoso)
if ($dropFirst) {
    echo "[OK][drop_first] Coletando tabelas...\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $t) {
        $tbl = $t[0];
        $pdo->exec("DROP TABLE IF EXISTS `$tbl`");
    }
    echo "[OK][drop_first] DROP concluído.\n";
}

$pdo->beginTransaction();
try {
    $n = 0;
    foreach ($statements as $stmt) {
        $n++;
        $pdo->exec($stmt);
        if ($n % 50 === 0) echo "[OK][exec] $n/" . count($statements) . "\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('exec_sql', "Falhou no statement #$n: " . $e->getMessage());
}

echo "[OK][done] Banco instalado com sucesso.\n";