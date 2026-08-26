<?php

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// ------------------------------------------------------------
// Carrega variáveis do .env (§5.4: credenciais fora do código)
// ------------------------------------------------------------
(static function () {
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
})();

// Helper local — lê variável de ambiente do processo/OS, depois $_ENV, depois fallback
function env(string $key, string $fallback = ''): string {
    $val = getenv($key);
    if ($val !== false) return $val;
    return $_ENV[$key] ?? $fallback;
}

// ------------------------------------------------------------
// Banco de dados
// ------------------------------------------------------------
define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_NAME',    env('DB_NAME'));
define('DB_USER',    env('DB_USER'));
define('DB_PASS',    env('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

// ------------------------------------------------------------
// Aplicação
// ------------------------------------------------------------
define('APP_URL',        env('APP_URL', 'https://guinchafacil.com.br'));
define('APP_ENV',        env('APP_ENV', 'production'));
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY'));
define('ADMIN_EMAIL',    env('ADMIN_EMAIL', env('SMTP_FROM_EMAIL')));

// ------------------------------------------------------------
// MercadoPago
// ------------------------------------------------------------
define('MP_ACCESS_TOKEN',  env('MP_ACCESS_TOKEN'));
define('MP_PUBLIC_KEY',    env('MP_PUBLIC_KEY'));
// Secret para validação HMAC-SHA256 dos webhooks (§5.2)
// Obter em: MP > Sua conta > Webhooks
define('MP_WEBHOOK_SECRET', env('MP_WEBHOOK_SECRET'));

// ------------------------------------------------------------
// PagSeguro
// ------------------------------------------------------------
define('PS_TOKEN', env('PS_TOKEN'));
define('PS_EMAIL', env('PS_EMAIL'));

// PagSeguro environment
define('PS_ENV',          env('PS_ENV', 'sandbox')); // 'sandbox' or 'production'
define('PS_BASE_URL',     PS_ENV === 'production'
    ? 'https://ws.pagseguro.uol.com.br'
    : 'https://ws.sandbox.pagseguro.uol.com.br');
define('PS_CHECKOUT_URL', PS_ENV === 'production'
    ? 'https://pagseguro.uol.com.br'
    : 'https://sandbox.pagseguro.uol.com.br');

// ------------------------------------------------------------
// SMTP (PHPMailer — §9: mail() nativo proibido em produção)
// ------------------------------------------------------------
define('SMTP_HOST',       env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT',       (int) env('SMTP_PORT', '587'));
define('SMTP_USER',       env('SMTP_USER'));
define('SMTP_PASS',       env('SMTP_PASS'));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL'));
define('SMTP_FROM_NAME',  env('SMTP_FROM_NAME', 'GuinchaFácil'));

// ------------------------------------------------------------
// Upload e logs (caminhos, não segredos — podem ficar aqui)
// ------------------------------------------------------------
define('UPLOAD_PATH',    dirname(__DIR__) . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('LOG_DIR',        dirname(__DIR__) . '/logs');

// ------------------------------------------------------------
// Sessão
// ------------------------------------------------------------
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// ------------------------------------------------------------
// Singleton PDO
// ------------------------------------------------------------
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET,
            ]);
        } catch (PDOException $e) {
            error_log('Erro de conexão com banco: ' . $e->getMessage());
            die('Erro interno do servidor. Tente novamente mais tarde.');
        }
    }
    return $pdo;
}

// ------------------------------------------------------------
// Funções utilitárias
// ------------------------------------------------------------
function sanitizeInput($data) {
    if (is_array($data)) return array_map('sanitizeInput', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function formatPhone(string $phone): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 11) return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
    if (strlen($phone) === 10) return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
    return $phone;
}

function formatCpf(string $cpf): string {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) === 11) return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    return $cpf;
}

function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;
    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function generateUuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ------------------------------------------------------------
// Nível de erro por ambiente
// ------------------------------------------------------------
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}
