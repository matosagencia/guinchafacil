<?php

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/src/Services/Security/ConfigSecurityService.php';

// ------------------------------------------------------------
// Carrega variáveis do .env (§5.4: credenciais fora do código)
// ------------------------------------------------------------
ConfigSecurityService::loadEnvFiles(__DIR__);

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
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH',    __DIR__ . '/public');
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH',    PUBLIC_PATH . '/uploads');
// §SEC-UPL-02 (correção): doc_cnh_frente/doc_cnh_verso/foto_veiculo do
// guincheiro são documento de identidade — não podem morar em UPLOAD_PATH
// (dentro do webroot público, servível direto via
// http://.../public/uploads/<arquivo>, ignorando toda a autenticação de
// ArquivoController::servir()). Caminho dedicado, fora do webroot, mesmo
// padrão de EvidenceService::privateStorageDir() (storage/private/...),
// que o .htaccess da raiz já bloqueia (RewriteRule .../storage/.../[F,L]).
// UPLOAD_PATH continua igual para foto_caminhao/comunicados — essas são
// imagens de exibição pública de propósito, não documento sensível.
if (!defined('UPLOAD_PATH_DOCS')) define('UPLOAD_PATH_DOCS', dirname(PUBLIC_PATH) . '/storage/private/uploads');
define('APP_ENV',        env('APP_ENV', 'production'));
define('FORCE_BASEPATH', env('FORCE_BASEPATH', ''));

define('APP_DEBUG',      env('APP_DEBUG', 'false') === 'true');
define('ENCRYPTION_KEY', env('ENCRYPTION_KEY'));
define('ADMIN_EMAIL',    env('ADMIN_EMAIL', env('SMTP_FROM_EMAIL')));

// ------------------------------------------------------------
// MercadoPago
// ------------------------------------------------------------
define('MP_ENV',           env('MP_ENV', 'production'));
define('MP_ACCESS_TOKEN_SANDBOX', env('MP_ACCESS_TOKEN_SANDBOX'));
define('MP_ACCESS_TOKEN_PROD',    env('MP_ACCESS_TOKEN_PROD'));
define('MP_PUBLIC_KEY_SANDBOX',   env('MP_PUBLIC_KEY_SANDBOX'));
define('MP_PUBLIC_KEY_PROD',      env('MP_PUBLIC_KEY_PROD'));
define('MP_ACCESS_TOKEN',  (env('MP_ENV', 'production') === 'sandbox') ? env('MP_ACCESS_TOKEN_SANDBOX') : env('MP_ACCESS_TOKEN', env('MP_ACCESS_TOKEN_PROD')));
define('MP_PUBLIC_KEY',    (env('MP_ENV', 'production') === 'sandbox') ? env('MP_PUBLIC_KEY_SANDBOX') : env('MP_PUBLIC_KEY_PROD'));
// Secret para validação HMAC-SHA256 dos webhooks (§5.2)
// Obter em: MP > Sua conta > Webhooks
define('MP_WEBHOOK_SECRET', env('MP_WEBHOOK_SECRET'));

// ------------------------------------------------------------
// §CA-BUNDLE-01: bundle de CAs para chamadas cURL a APIs externas
// (MercadoPago, PagSeguro). Visto na prática: `tools/simular_fluxo_completo.php`
// falhando em produção local (XAMPP/Windows) com "SSL certificate problem:
// unable to get local issuer certificate" ao chamar a API de Pix — o
// curl.cainfo do php.ini não está configurado nesse ambiente. Em vez de
// depender de cada máquina/host ter isso configurado corretamente (varia
// entre XAMPP local, cPanel de produção, etc.), o projeto carrega um bundle
// próprio (Mozilla CA bundle via curl.se/ca/cacert.pem) e cada chamada cURL
// a uma API externa deve setar CURLOPT_CAINFO com esse caminho quando o
// arquivo existir — ver ca_bundle_path().
define('CA_BUNDLE_PATH', __DIR__ . '/storage/cacert.pem');

function ca_bundle_path(): ?string
{
    return is_file(CA_BUNDLE_PATH) ? CA_BUNDLE_PATH : null;
}

// ------------------------------------------------------------
// Aplicação — configurações adicionais
// ------------------------------------------------------------
define('HTTPS_ONLY',       env('HTTPS_ONLY', 'false') === 'true');
define('COMPANY_ADDRESS',  env('COMPANY_ADDRESS', 'Rua da Gamboa 131, Rio de Janeiro'));
define('COMPANY_WHATSAPP', env('COMPANY_WHATSAPP', '21959256849'));
define('SERPAPI_KEY', env('SERPAPI_KEY', ''));
define('PROSPECCAO_URL_PRE_CADASTRO', env('PROSPECCAO_URL_PRE_CADASTRO', APP_URL . '/parceiros/interesse'));
define('PROSPECCAO_OFERTA_RECIPROCIDADE', env('PROSPECCAO_OFERTA_RECIPROCIDADE', 'primeiros 30 dias sem taxa de adesao'));
define('PROSPECCAO_CATEGORIAS_ALVO', env('PROSPECCAO_CATEGORIAS_ALVO', 'guincho,reboque,autoeletrica,borracheiro,chaveiro_automotivo,mecanico_movel,oficina_mecanica,bateria_automotiva,socorro_veicular'));
define('PROSPECCAO_QUOTA_ALVO_PADRAO', (int) env('PROSPECCAO_QUOTA_ALVO_PADRAO', '5'));
define('PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO', (int) env('PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO', '100'));
define('PROSPECCAO_RAIO_PADRAO_KM', (int) env('PROSPECCAO_RAIO_PADRAO_KM', '15'));

// §IP-CANONICO-01: lista de IPs/CIDRs de reverse proxies confiáveis, separados
// por vírgula. Vazio por padrão (XAMPP roda direto, sem proxy na frente) —
// nesse caso X-Forwarded-For é ignorado e só REMOTE_ADDR (não forjável pelo
// cliente) é usado em rate-limit e auditoria. Só preencher se um dia houver
// um reverse proxy/CDN real na frente (ex: "127.0.0.1,10.0.0.0/8").
define('TRUSTED_PROXIES', env('TRUSTED_PROXIES', ''));

// ------------------------------------------------------------
// Gateway de cobrança ativo
// ------------------------------------------------------------
define('PAYMENT_GATEWAY_ACTIVE', env('PAYMENT_GATEWAY_ACTIVE', 'mercadopago'));

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
// Simulado / testes
// ------------------------------------------------------------
define('SIMULATION_ENABLED',      env('SIMULATION_ENABLED', 'false') === 'true');
define('PIX_DRY_RUN',             env('PIX_DRY_RUN', 'true') === 'true');
define('SIMULATION_ADMIN_TOKEN',  env('SIMULATION_ADMIN_TOKEN', ''));

// ------------------------------------------------------------
// Log do sistema — chave seletora (governada em /admin/env)
// §LOG-TOGGLE-01: em DEV fica ligado por padrão; em produção o plano é
// deixar desligado e só ligar durante janelas de manutenção periódica —
// ver Logger::event(), que consulta esta constante antes de gravar
// qualquer evento em logs/app-*.jsonl e na tabela app_logs.
// ------------------------------------------------------------
define('SYSTEM_LOG_ENABLED',      env('SYSTEM_LOG_ENABLED', 'true') === 'true');

ConfigSecurityService::validateEnvironment($_ENV);
ConfigSecurityService::validateEnvironmentFiles(__DIR__, $_ENV);

// ------------------------------------------------------------
// Operacional
// ------------------------------------------------------------
define('MAX_PIX_TENTATIVAS',       (int) env('MAX_PIX_TENTATIVAS', '5'));
define('GEOCODING_CACHE_TTL_DAYS', (int) env('GEOCODING_CACHE_TTL_DAYS', '30'));
define('TARIFA_BASE',              (float) env('TARIFA_BASE', '25.00'));
define('TARIFA_KM',                (float) env('TARIFA_KM', '4.50'));

// ------------------------------------------------------------
// Upload e logs (caminhos, não segredos — podem ficar aqui)
// ------------------------------------------------------------
// (UPLOAD_PATH já definido acima, fora do webroot — este segundo `define`
// nunca executava de verdade por causa do `if (!defined())`, mas apontava
// para dirname(__DIR__) . '/uploads/' = FORA do projeto inteiro, o que no
// XAMPP cai dentro de C:\xampp\htdocs\uploads — alcançável pelo vhost
// padrão da porta 80. Removido para não confundir/arriscar reintrodução.)
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
// Bug real encontrado em 30/07/2026 (mesma classe do bug já documentado e
// corrigido acima pro UPLOAD_PATH): dirname(__DIR__) sobe UM NÍVEL A MAIS
// que o necessário. __DIR__ aqui já é a raiz do projeto (onde este
// config.php mora); dirname(__DIR__) ia para C:\xampp\htdocs\logs — um
// diretório IRMÃO de guinchafacil/, fora do projeto inteiro. Toda vez que
// alguém checava guinchafacil/logs/app-*.jsonl (inclusive nesta própria
// investigação) encontrava só o app.log legado de 12/07 e nada mais — não
// porque o Logger estivesse quebrado, mas porque ele sempre escreveu no
// lugar errado, silenciosamente, desde sempre. Confirmado: os arquivos
// reais (com semanas de eventos) estavam em C:\xampp\htdocs\logs\.
define('LOG_DIR',        __DIR__ . '/logs');

// ------------------------------------------------------------
// Sessão
// ------------------------------------------------------------
// Idle: expira após inatividade real. Absolute: exige novo login mesmo com uso contínuo.
define('SESSION_IDLE_TIMEOUT', (int) env('SESSION_IDLE_TIMEOUT', '3600'));
define('SESSION_ABSOLUTE_TIMEOUT', (int) env('SESSION_ABSOLUTE_TIMEOUT', '43200'));
define('SESSION_WARNING_SECONDS', (int) env('SESSION_WARNING_SECONDS', '120'));
define('SESSION_COOKIE_LIFETIME', (int) env('SESSION_COOKIE_LIFETIME', (string) SESSION_ABSOLUTE_TIMEOUT));

$sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $sessionSecure ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', (string) SESSION_ABSOLUTE_TIMEOUT);
ini_set('session.cookie_lifetime', (string) SESSION_COOKIE_LIFETIME);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_COOKIE_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ------------------------------------------------------------
// Singleton PDO
// ------------------------------------------------------------
if (!function_exists('getPDO')) {
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
if (APP_DEBUG || APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . '/php_errors.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . '/php_errors.log');
}
