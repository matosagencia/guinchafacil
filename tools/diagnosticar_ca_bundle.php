<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


// tools/diagnosticar_ca_bundle.php
//
// Diagnóstico isolado do problema "SSL certificate problem: unable to get
// local issuer certificate" que persiste mesmo após configurar CURLOPT_CAINFO
// via ca_bundle_path(). Roda fora de qualquer fluxo de negócio pra isolar se
// o problema é o caminho do arquivo, o conteúdo do bundle, ou o próprio cURL
// da instalação PHP do XAMPP.
//
// Uso: php tools/diagnosticar_ca_bundle.php

require_once __DIR__ . '/../config.php';

echo "=== Diagnóstico CA Bundle ===\n\n";

echo "CA_BUNDLE_PATH const: " . CA_BUNDLE_PATH . "\n";
echo "is_file(): " . (is_file(CA_BUNDLE_PATH) ? 'true' : 'false') . "\n";
echo "is_readable(): " . (is_readable(CA_BUNDLE_PATH) ? 'true' : 'false') . "\n";
if (is_file(CA_BUNDLE_PATH)) {
    echo "filesize(): " . filesize(CA_BUNDLE_PATH) . " bytes\n";
    $handle = fopen(CA_BUNDLE_PATH, 'rb');
    $primeiraLinha = $handle ? fgets($handle) : false;
    if ($handle) fclose($handle);
    echo "primeira linha: " . var_export($primeiraLinha, true) . "\n";
}
echo "ca_bundle_path() retorna: " . var_export(ca_bundle_path(), true) . "\n\n";

echo "PHP_VERSION: " . PHP_VERSION . "\n";
echo "curl_version(): " . json_encode(curl_version()['ssl_version'] ?? 'desconhecida') . "\n";
echo "php.ini carregado: " . (php_ini_loaded_file() ?: '(nenhum)') . "\n";
echo "ini curl.cainfo: " . var_export(ini_get('curl.cainfo'), true) . "\n";
echo "ini openssl.cafile: " . var_export(ini_get('openssl.cafile'), true) . "\n\n";

echo "=== Teste cURL direto contra api.mercadopago.com ===\n";
$ch = curl_init('https://api.mercadopago.com/v1/payment_methods');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_VERBOSE => true,
]);
$verboseLog = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verboseLog);

$ca = ca_bundle_path();
if ($ca) {
    curl_setopt($ch, CURLOPT_CAINFO, $ca);
    echo "CURLOPT_CAINFO setado explicitamente para: {$ca}\n";
} else {
    echo "CURLOPT_CAINFO NÃO setado (ca_bundle_path() retornou null) — usando default do sistema.\n";
}

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "curl_errno: {$errno}\n";
echo "curl_error: " . ($err ?: '(nenhum)') . "\n";
echo "HTTP code: {$code}\n";
echo "resposta (primeiros 200 chars): " . substr((string)$resp, 0, 200) . "\n\n";

rewind($verboseLog);
echo "=== Log verbose do cURL ===\n";
echo stream_get_contents($verboseLog);
fclose($verboseLog);

// §CA-BUNDLE-02: MercadoPagoProvider (via Apache/mod_php, no fluxo real de
// checkout) bate no MESMO endpoint (api.mercadopago.com/v1/payments) e já
// funcionou nesta mesma máquina sem nenhum CURLOPT_CAINFO explícito — usando
// só o default do php.ini. Se o problema fosse o bundle de CA em si, teria
// falhado lá também. A hipótese mais provável agora é proxy HTTPS/SSL
// interceptando só o processo CLI (VPN corporativo, antivírus com "SSL
// scan", etc.) — curl no Windows respeita HTTP_PROXY/HTTPS_PROXY do
// ambiente. Testa isso diretamente.
echo "\n=== Variáveis de proxy no ambiente ===\n";
foreach (['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy', 'NO_PROXY'] as $var) {
    $val = getenv($var);
    echo "{$var}: " . ($val === false ? '(não definida)' : $val) . "\n";
}

echo "\n=== Teste forçando SEM proxy (CURLOPT_PROXY vazio) ===\n";
$ch2 = curl_init('https://api.mercadopago.com/v1/payment_methods');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_PROXY => '',
]);
if ($ca) {
    curl_setopt($ch2, CURLOPT_CAINFO, $ca);
}
$resp2 = curl_exec($ch2);
$errno2 = curl_errno($ch2);
$err2 = curl_error($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);
echo "curl_errno: {$errno2}\n";
echo "curl_error: " . ($err2 ?: '(nenhum)') . "\n";
echo "HTTP code: {$code2}\n";
echo "resposta (primeiros 100 chars): " . substr((string)$resp2, 0, 100) . "\n";
