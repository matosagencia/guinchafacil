<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$_SERVER['REQUEST_URI'] = '/cli';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'cli';
$_SERVER['SCRIPT_NAME'] = '/cli.php';
if (!defined('REQUEST_ID')) define('REQUEST_ID', 'cli_ors');
if (!defined('CSP_SCRIPT_NONCE')) define('CSP_SCRIPT_NONCE', 'cli');

$raiz = dirname(__DIR__);
require_once $raiz . '/config.php';
if (!function_exists('getPDO')) require_once $raiz . '/src/Database.php';
require_once $raiz . '/src/Models/Configuracao.php';

echo "=== 1. Onde a chave está ===\n";
echo "getenv():       " . var_export(getenv('ORS_API_KEY'), true) . "\n";
echo "\$_ENV:          " . var_export($_ENV['ORS_API_KEY'] ?? null, true) . "\n";
echo "\$_SERVER:       " . var_export($_SERVER['ORS_API_KEY'] ?? null, true) . "\n";
echo "env() helper:   " . var_export(function_exists('env') ? env('ORS_API_KEY', '(vazio)') : '(helper não existe)', true) . "\n";

$cfg = Configuracao::getAll();
echo "configuracoes:  " . var_export($cfg['ors_api_key'] ?? null, true) . "\n";

echo "\n=== 2. .env tem a linha? ===\n";
$envFile = $raiz . '/.env';
if (is_file($envFile)) {
    $conteudo = file_get_contents($envFile);
    if (preg_match('/^ORS_API_KEY=(.+)$/m', $conteudo, $m)) {
        $chave = trim($m[1], " \t\"'");
        echo "Sim. Comprimento: " . strlen($chave) . "\n";
        echo "Primeiros 8: " . substr($chave, 0, 8) . "...\n";
    } else {
        echo "NÃO — a chave não está no .env\n";
    }
} else {
    echo ".env não existe\n";
}

echo "\n=== 3. CA bundle existe? ===\n";
echo "ca_bundle_path():    " . var_export(function_exists('ca_bundle_path') ? ca_bundle_path() : '(não existe)', true) . "\n";
echo "curl.cainfo ini:     " . var_export(ini_get('curl.cainfo'), true) . "\n";
echo "openssl.cafile ini:  " . var_export(ini_get('openssl.cafile'), true) . "\n";
$caminhos = [
    'C:/xampp/apache/bin/curl-ca-bundle.crt',
    'C:/xampp/php/extras/ssl/cacert.pem',
];
foreach ($caminhos as $c) {
    echo "is_file($c): " . (is_file($c) ? 'SIM' : 'não') . "\n";
}

echo "\n=== 4. Teste direto ORS (com CA configurado) ===\n";

// Junta a chave igual ao RoutingService faz
$apiKey = trim((string)(
    ($_ENV['ORS_API_KEY'] ?? '')
    ?: ($_SERVER['ORS_API_KEY'] ?? '')
    ?: (function_exists('env') ? (string)env('ORS_API_KEY', '') : '')
    ?: (string)(getenv('ORS_API_KEY') ?: '')
    ?: (string)($cfg['ors_api_key'] ?? '')
));

if ($apiKey === '') {
    echo "  ABORTA: chave vazia — não adianta testar rede\n";
    exit(1);
}
echo "  Chave encontrada (comprimento: " . strlen($apiKey) . ")\n";

$ch = curl_init('https://api.heigit.org/openrouteservice/v2/directions/driving-car');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'coordinates' => [[-46.4372655, -23.1794793], [-47.0683, -22.8975]],
    ]),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: ' . $apiKey,
    ],
    CURLOPT_CONNECTTIMEOUT_MS => 5000,
    CURLOPT_TIMEOUT_MS => 10000,
]);
// CA bundle explícito
$ca = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
if (is_file($ca)) {
    curl_setopt($ch, CURLOPT_CAINFO, $ca);
    echo "  Usando CA: $ca\n";
}

$resp = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "  HTTP: $http\n";
echo "  curl_error: " . ($err ?: '(vazio)') . "\n";
echo "  Resposta (400 chars):\n  " . substr((string)$resp, 0, 400) . "\n";

echo "\n=== FIM ===\n";