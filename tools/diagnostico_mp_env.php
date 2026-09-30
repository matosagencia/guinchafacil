<?php
/**
 * Diagnostico da configuracao MercadoPago.
 * Uso: C:\xampp\php\php.exe tools\diagnostico_mp_env.php
 *
 * Mostra:
 *   - .env cru (mascarado)
 *   - constantes carregadas pelo config.php
 *   - qual conta MP o access_token pertence (GET /users/me)
 *   - cria um card_token de teste com a public_key e checa live_mode
 *   - faz um charge de R$ 1,00 pra confirmar se o par esta alinhado
 *
 * ATENCAO: se MP_ENV=production, o passo 5 faz cobranca REAL de R$ 1.
 * Passe --no-charge pra pular.
 */

require_once __DIR__ . '/../config.php';

$noCharge = in_array('--no-charge', $argv ?? [], true);

echo "============================================================\n";
echo "  DIAGNOSTICO MERCADO PAGO\n";
echo "============================================================\n\n";

// ----- 1. .env cru -----
echo "----- 1. .env CRU (mascarado) -----\n";
$envCandidates = [
    dirname(__DIR__) . '/.env',
    'C:/xampp/htdocs/.env',
];
$envFound = null;
foreach ($envCandidates as $p) {
    if (is_file($p)) { $envFound = $p; break; }
}
if ($envFound) {
    echo "Arquivo: $envFound\n";
    $lines = file($envFound, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^(MP_[A-Z_]+)\s*=\s*(.*)$/', $line, $m)) {
            $k = $m[1]; $v = trim($m[2]);
            $masked = strlen($v) > 24 ? substr($v, 0, 24) . '...' : $v;
            if ($v === '') $masked = '(VAZIO)';
            echo "  $k = $masked\n";
        }
    }
} else {
    echo "  Nao achei .env em nenhum dos caminhos testados\n";
}

// ----- 2. Constantes em runtime -----
echo "\n----- 2. CONSTANTES EM RUNTIME (config.php carregou) -----\n";
$consts = [
    'MP_ENV',
    'PAYMENT_GATEWAY_ACTIVE',
    'MP_ACCESS_TOKEN',
    'MP_PUBLIC_KEY',
    'MP_ACCESS_TOKEN_SANDBOX',
    'MP_ACCESS_TOKEN_PROD',
    'MP_PUBLIC_KEY_SANDBOX',
    'MP_PUBLIC_KEY_PROD',
];
foreach ($consts as $c) {
    $v = defined($c) ? (string)constant($c) : '(nao definida)';
    $masked = strlen($v) > 24 ? substr($v, 0, 24) . '...' : $v;
    if ($v === '') $masked = '(VAZIO)';
    echo "  $c = $masked\n";
}

// ----- 3. Quem e o access_token? -----
echo "\n----- 3. QUEM E O ACCESS_TOKEN? (GET /users/me) -----\n";
$token = defined('MP_ACCESS_TOKEN') ? (string)MP_ACCESS_TOKEN : '';
if ($token === '') {
    echo "  TOKEN VAZIO - abortando\n";
    exit(1);
}
$ch = curl_init('https://api.mercadopago.com/users/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "  HTTP $code\n";
$user = json_decode((string)$resp, true);
if (is_array($user)) {
    echo "  user_id:   " . ($user['id'] ?? '?') . "\n";
    echo "  nickname:  " . ($user['nickname'] ?? '?') . "\n";
    echo "  email:     " . ($user['email'] ?? '?') . "\n";
    echo "  site_id:   " . ($user['site_id'] ?? '?') . "\n";
    echo "\n  >>> Abra o painel MP (Credenciais de teste) e compare o user_id acima.\n";
    echo "  >>> Se forem DIFERENTES, o access_token NAO e da mesma conta das credenciais de teste.\n";
} else {
    echo "  Body: " . substr((string)$resp, 0, 300) . "\n";
}

// ----- 4. Cria card_token com public_key -----
echo "\n----- 4. CARD_TOKEN COM A PUBLIC_KEY -----\n";
$pub = defined('MP_PUBLIC_KEY') ? (string)MP_PUBLIC_KEY : '';
if ($pub === '') {
    echo "  PUBLIC_KEY VAZIA - abortando\n";
    exit(1);
}
$payload = [
    'card_number' => '5031433215406351',
    'security_code' => '123',
    'expiration_month' => 11,
    'expiration_year' => 2030,
    'cardholder' => [
        'name' => 'APRO',
        'identification' => ['type' => 'CPF', 'number' => '12345678909'],
    ],
];
$ch = curl_init('https://api.mercadopago.com/v1/card_tokens?public_key=' . urlencode($pub));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "  HTTP $code\n";
$ct = json_decode((string)$resp, true);
$cardTokenId = $ct['id'] ?? '';
if ($cardTokenId !== '') {
    echo "  card_token_id: $cardTokenId\n";
    echo "  live_mode:     " . ($ct['live_mode'] ?? '?') . "\n";
    echo "  first_six:     " . ($ct['first_six_digits'] ?? '?') . "\n";
} else {
    echo "  Body: " . substr((string)$resp, 0, 300) . "\n";
    echo "  >>> public_key NAO conseguiu tokenizar. Verifique se ela esta correta.\n";
    exit(1);
}

// ----- 5. Charge de R$ 1,00 -----
if ($noCharge) {
    echo "\n----- 5. CHARGE PULADO (--no-charge) -----\n";
    exit(0);
}

echo "\n----- 5. TESTE DE CHARGE R$ 1,00 -----\n";
echo "  (se MP_ENV=production, isso e' uma cobranca REAL)\n";
$idem = 'diag-' . bin2hex(random_bytes(8));
$chargePayload = [
    'transaction_amount' => 1.00,
    'description' => 'Diagnostico MP',
    'payment_method_id' => 'master',
    'token' => $cardTokenId,
    'installments' => 1,
    'payer' => [
        'email' => 'test_user_diag@test.com',
        'identification' => ['type' => 'CPF', 'number' => '12345678909'],
    ],
];
$ch = curl_init('https://api.mercadopago.com/v1/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($chargePayload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'X-Idempotency-Key: ' . $idem,
    ],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  HTTP $code\n";
$pay = json_decode((string)$resp, true);
if (is_array($pay)) {
    echo "  status:        " . ($pay['status'] ?? '?') . "\n";
    echo "  status_detail: " . ($pay['status_detail'] ?? '?') . "\n";
    if (!empty($pay['cause'])) {
        echo "  cause:         " . json_encode($pay['cause'], JSON_UNESCAPED_SLASHES) . "\n";
    }
    if (!empty($pay['message'])) {
        echo "  message:       " . $pay['message'] . "\n";
    }

    echo "\n  >>> DIAGNOSTICO:\n";
    $raw = (string)$resp;
    if (strpos($raw, 'E214') !== false || strpos($raw, 'invalid card_token_id') !== false) {
        echo "  [X] E214 — public_key e access_token NAO sao da mesma aplicacao MP\n";
        echo "      Copie AMBOS da mesma aba (Testes) e cole juntos no .env\n";
    } elseif (($pay['status'] ?? '') === 'approved') {
        echo "  [OK] Aprovado. O par public_key/access_token esta alinhado.\n";
    } elseif (($pay['status'] ?? '') === 'rejected' && ($pay['status_detail'] ?? '') === 'cc_rejected_other_reason') {
        echo "  [!] Recusa generica. Pode ser: cartao de teste nao bate com o ambiente,\n";
        echo "      ou a conta de teste esta bloqueada. Verifique no painel MP.\n";
    } else {
        echo "  [?] Ver o status_detail acima\n";
    }
} else {
    echo "  Body: " . substr((string)$resp, 0, 400) . "\n";
}