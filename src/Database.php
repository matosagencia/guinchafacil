<?php
// File: src/Database.php
// Mantém compatibilidade com código legado que chama getPDO(),
// sem redeclarar a função quando config.php já a define.

declare(strict_types=1);

if (!function_exists('getPDO')) {
    /**
     * Retorna um PDO singleton.
     * Depende de DB_HOST, DB_NAME, DB_USER, DB_PASS e DB_CHARSET (definidos em config.php).
     */
    function getPDO(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $host = defined('DB_HOST') ? (string)DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? (string)DB_NAME : '';
        $user = defined('DB_USER') ? (string)DB_USER : '';
        $pass = defined('DB_PASS') ? (string)DB_PASS : '';
        $cs   = defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4';

        if ($name === '' || $user === '') {
            throw new RuntimeException('Config de banco ausente: defina DB_NAME/DB_USER em config.php');
        }

        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $cs;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    }
}

/**
 * Wrapper opcional (pra quem prefere Database::pdo()).
 */
final class Database
{
    public static function pdo(): PDO
    {
        return getPDO();
    }
}
