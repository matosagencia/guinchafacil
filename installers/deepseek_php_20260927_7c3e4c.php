<?php
// diag-login-live.php — simula login completo como se fosse o browser
// APAGUE DEPOIS DE USAR

require_once __DIR__ . '/config.php';

$email = 'oficina@teste.com';
$senha = 'teste123';
$base  = 'http://localhost:8080';
$cookieJar = sys_get_temp_dir() . '/diag_cookies_' . uniqid() . '.txt';

$log = [];
function addLog(&$log, $titulo, $conteudo = '') {
    $log[] = "─── $titulo ───";
    if ($conteudo !== '') $log[] = $conteudo;
}

// ═══════════════════════════════════════════════════════════════════
// 1. LIMPEZA — rate limit + arquivos de sessão velhos
// ═══════════════════════════════════════════════════════════════════
try {
    getPDO()->exec("DELETE FROM rate_limit WHERE rota = 'login'");
    addLog($log, '1. Rate limit', 'login: limpo');
} catch (Throwable $e) {
    addLog($log, '1. Rate limit', 'erro: ' . $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════
// 2. GET /login — captura CSRF + cookies
// ═══════════════════════════════════════════════════════════════════
$ch = curl_init("$base/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_COOKIEFILE     => $cookieJar,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER         => true,
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

addLog($log, '2. GET /login', "HTTP $httpCode\nHeaders:\n$headers");

// Extrai CSRF do HTML
$csrf = '';
if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $m)) {
    $csrf = $m[1];
}
addLog($log, '2b. CSRF extraído do HTML', $csrf !== '' ? substr($csrf, 0, 20) . '... (' . strlen($csrf) . ' chars)' : '❌ NÃO ENCONTRADO');

// Mostra cookie PHPSESSID
$cookiesRaw = file_get_contents($cookieJar);
addLog($log, '2c. Cookies recebidos', $cookiesRaw);

// ═══════════════════════════════════════════════════════════════════
// 3. POST /login — envia credenciais
// ═══════════════════════════════════════════════════════════════════
$postData = http_build_query([
    'csrf_token' => $csrf,
    'email'      => $email,
    'senha'      => $senha,
]);

$ch = curl_init("$base/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_COOKIEFILE     => $cookieJar,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER         => true,
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

addLog($log, '3. POST /login', "HTTP $httpCode\nHeaders:\n$headers");

// Extrai Location
$location = '';
if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
    $location = trim($m[1]);
}
addLog($log, '3b. Redirecionamento (Location)', $location ?: '❌ SEM REDIRECIONAMENTO');

// Verifica se o corpo tem alguma mensagem de erro
if (stripos($body, 'Email ou senha incorretos') !== false) {
    addLog($log, '3c. Erro na resposta', '❌ "Email ou senha incorretos"');
} elseif (stripos($body, 'Token inválido') !== false) {
    addLog($log, '3c. Erro na resposta', '❌ "Token inválido" (CSRF)');
} elseif (stripos($body, 'Muitas tentativas') !== false) {
    addLog($log, '3c. Erro na resposta', '❌ "Muitas tentativas" (rate limit)');
} else {
    addLog($log, '3c. Corpo da resposta', substr(strip_tags($body), 0, 300));
}

// ═══════════════════════════════════════════════════════════════════
// 4. Verifica se sessão foi criada
// ═══════════════════════════════════════════════════════════════════
$cookiesRaw = file_get_contents($cookieJar);
preg_match('/PHPSESSID\s+(\S+)/', $cookiesRaw, $sidMatch);
$sessId = $sidMatch[1] ?? '';

addLog($log, '4. Sessão PHP criada', $sessId ?: '❌ sem PHPSESSID');

if ($sessId) {
    // Verifica o arquivo de sessão
    $sessionFile = "C:/xampp/tmp/sess_$sessId";
    if (file_exists($sessionFile)) {
        $content = file_get_contents($sessionFile);
        addLog($log, '4b. Arquivo de sessão', "Existe — conteúdo:\n" . substr($content, 0, 300));
    } else {
        addLog($log, '4b. Arquivo de sessão', "❌ NÃO encontrado em C:/xampp/tmp/sess_$sessId");
        // Tenta descobrir onde as sessões são salvas
        $savePath = ini_get('session.save_path');
        addLog($log, '4c. session.save_path', $savePath);
    }
}

// ═══════════════════════════════════════════════════════════════════
// 5. Testa login via simulação direta do AuthController
// ═══════════════════════════════════════════════════════════════════
require_once __DIR__ . '/src/Services/AuthService.php';
$loginResult = AuthService::login($email, $senha);
addLog($log, '5. AuthService::login() direto', json_encode($loginResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ═══════════════════════════════════════════════════════════════════
// 6. Diagnóstico do servidor
// ═══════════════════════════════════════════════════════════════════
addLog($log, '6. Configurações do servidor', implode("\n", [
    'PHP version (web): ' . PHP_VERSION,
    'session.save_path: ' . ini_get('session.save_path'),
    'session.name: ' . ini_get('session.name'),
    'session.cookie_lifetime: ' . ini_get('session.cookie_lifetime'),
    'session.use_strict_mode: ' . ini_get('session.use_strict_mode'),
    'session.use_only_cookies: ' . ini_get('session.use_only_cookies'),
    'session.cookie_httponly: ' . ini_get('session.cookie_httponly'),
    'session.cookie_samesite: ' . ini_get('session.cookie_samesite'),
    'session.cookie_secure: ' . ini_get('session.cookie_secure'),
    'APP_DEBUG: ' . (defined('APP_DEBUG') ? (APP_DEBUG ? 'true' : 'false') : 'n/a'),
    'APP_ENV: ' . (defined('APP_ENV') ? APP_ENV : 'n/a'),
]));

// ═══════════════════════════════════════════════════════════════════
// 7. Últimas 5 linhas do log de erros
// ═══════════════════════════════════════════════════════════════════
$errorLog = __DIR__ . '/logs/php_errors.log';
if (file_exists($errorLog)) {
    $lines = file($errorLog);
    $ultimas = array_slice($lines, -5);
    addLog($log, '7. Últimas 5 linhas de php_errors.log', implode('', $ultimas));
}

// Cleanup
@unlink($cookieJar);

?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Diag Login Live</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#eee;line-height:1.6;font-size:13px}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;word-break:break-word;border:1px solid #2a2f3a;font-size:12px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔬 Diagnóstico — Login ao vivo (curl interno)</h1>
<pre><?= htmlspecialchars(implode("\n", $log)) ?></pre>
<p class="del">APAGUE: diag-login-live.php</p>
</body></html>