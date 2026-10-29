<?php
declare(strict_types=1);

define('TESTINGEnvironment', true);

spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\' => __DIR__ . '/../src/',
        'Services\\' => __DIR__ . '/../src/Services/',
        'Models\\' => __DIR__ . '/../src/Models/',
        'Controllers\\' => __DIR__ . '/../src/Controllers/'
    ];

    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $pdo = new PDO('sqlite::memory:', null, null, $options);
        } else {
            $host = defined('DB_HOST') && DB_HOST !== '' ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
            $name = getenv('PHPUNIT_DB_NAME') ?: 'guinchafacil_phpunit_qa';
            $user = getenv('PHPUNIT_DB_USER') ?: (defined('DB_USER') && DB_USER !== '' ? DB_USER : (getenv('DB_USER') ?: 'root'));
            $pass = getenv('PHPUNIT_DB_PASS');
            if ($pass === false) $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');
            $host = getenv('PHPUNIT_DB_HOST') ?: $host;
            $port = getenv('PHPUNIT_DB_PORT') ?: '3306';
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, $options);
            $actual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!str_starts_with($actual, 'guinchafacil_phpunit_qa')) {
                throw new RuntimeException('TEST-DATABASE-GUARD: PHPUnit bloqueado fora de banco QA: ' . $actual);
            }
        }
    }
    return $pdo;
}

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

// Serviços legados sem namespace usados por testes arquivados.
foreach ([
    __DIR__ . '/../src/Services/EstornoService.php',
    __DIR__ . '/../src/Services/PixFaturaService.php',
    __DIR__ . '/../src/Services/PixService.php',
    __DIR__ . '/../src/Models/Pagamento.php',
    __DIR__ . '/../src/Controllers/WebhookController.php',
] as $legacyPath) {
    if (file_exists($legacyPath)) require_once $legacyPath;
}
