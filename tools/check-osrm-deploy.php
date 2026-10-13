<?php
declare(strict_types=1);

/**
 * Verificador de deploy: garante que OSRM_BASE_URL aponta para
 * instancia propria antes de liberar producao.
 *
 * Uso:
 *   php tools/check-osrm-deploy.php                -- modo auto (detecta APP_ENV)
 *   php tools/check-osrm-deploy.php --production   -- forca exigencia
 *   php tools/check-osrm-deploy.php --dev          -- so avisa
 *
 * Exit codes:
 *   0 = OK
 *   1 = producao sem URL propria (bloqueia deploy)
 */

if (PHP_SAPI !== 'cli') exit(1);

$raiz = dirname(__DIR__);
require_once $raiz . '/config.php';
if (!function_exists('getPDO')) require_once $raiz . '/src/Database.php';
require_once $raiz . '/src/Services/POR/PorThresholds.php';

$argv = $_SERVER['argv'] ?? [];
$modo = 'auto';
if (in_array('--production', $argv, true)) $modo = 'production';
if (in_array('--dev', $argv, true))        $modo = 'dev';

$env = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? 'production');
if ($modo === 'auto') {
    $modo = ($env === 'production' || $env === 'prod') ? 'production' : 'dev';
}

$url = PorThresholds::roadMatchBaseUrl();
$demoPublico = str_contains($url, 'router.project-osrm.org');
$envOverride = trim((string)(getenv('OSRM_BASE_URL') ?: ''));

echo "=== Verificacao OSRM (deploy) ===\n";
echo "  modo:       {$modo}\n";
echo "  APP_ENV:    {$env}\n";
echo "  URL efetiva: {$url}\n";
echo "  env override: " . ($envOverride !== '' ? $envOverride : '(vazio)') . "\n\n";

if ($modo === 'dev') {
    if ($demoPublico) {
        echo "  [AVISO] Usando OSRM demo publico. Aceitavel em dev.\n";
        echo "          Nao use em producao (rate-limit + ToS).\n";
    } else {
        echo "  [OK] Instancia propria configurada.\n";
    }
    exit(0);
}

// modo production
if ($url === '') {
    echo "  [ERRO] URL OSRM vazia. Configure OSRM_BASE_URL no .env\n";
    echo "         ou a chave por_road_match_base_url em configuracoes.\n";
    exit(1);
}

if ($demoPublico) {
    echo "  [ERRO] PRODUCAO sem OSRM proprio. O servidor demo publico\n";
    echo "         (router.project-osrm.org) tem rate-limit e ToS que\n";
    echo "         proibem uso em producao.\n\n";
    echo "         Corrija:\n";
    echo "         1. Suba uma instancia OSRM (ex.: docker run -p 5000:5000 osrm/osrm-backend)\n";
    echo "         2. Exporte OSRM_BASE_URL=http://<host>:5000 no .env\n";
    echo "         3. Rode este check de novo\n";
    exit(1);
}

// Sanidade: tenta bater na URL de saude
echo "  Testando conectividade...\n";
$ch = curl_init(rtrim($url, '/') . '/route/v1/driving/-43.19,-22.89;-43.20,-22.90?overview=false');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT_MS => 3000,
    CURLOPT_TIMEOUT_MS => 5000,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err !== '' || $body === false || $http >= 400) {
    echo "  [ERRO] Instancia OSRM inacessivel (http={$http}, err={$err}).\n";
    echo "         Deploy bloqueado.\n";
    exit(1);
}

$decoded = json_decode((string)$body, true);
if (!is_array($decoded) || ($decoded['code'] ?? '') !== 'Ok') {
    echo "  [ERRO] Resposta invalida do OSRM: " . substr((string)$body, 0, 200) . "\n";
    exit(1);
}

echo "  [OK] OSRM proprio respondendo corretamente.\n";
exit(0);