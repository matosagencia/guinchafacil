<?php
declare(strict_types=1);
/**
 * Contadores regionais do funil administrativo. Somente leitura.
 * Não substitui matching, aceite do parceiro nem cotação B-011.
 */
require_once dirname(__DIR__) . '/config.php';
// O front controller inicia a sessao; o endpoint direto precisa fazer o mesmo.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/src/Services/AuthService.php';
AuthService::requireAuth('admin');
require_once dirname(__DIR__) . '/src/Services/GuinchoDisponibilidade.php';
require_once dirname(__DIR__) . '/src/Services/ProviderWorkshopService.php';
    require_once dirname(__DIR__) . '/src/Services/Workshops/AdminWorkshopGeoResolver.php';
require_once dirname(__DIR__) . '/src/Services/Workshops/AdminNetworkDiagnostic.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function gfAdmNumber(string $key, float $min, float $max): ?float {
    $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_FLOAT);
    if ($v === false || $v === null || !is_finite((float)$v) || $v < $min || $v > $max) return null;
    return (float)$v;
}
$lat = gfAdmNumber('lat', -90, 90);
$lng = gfAdmNumber('lng', -180, 180);
if ($lat === null || $lng === null || ($lat === 0.0 && $lng === 0.0)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'erro'=>'COORDENADAS_INVALIDAS']);
    exit;
}
$destLat = gfAdmNumber('dest_lat', -90, 90);
$destLng = gfAdmNumber('dest_lng', -180, 180);
if (($destLat === null) !== ($destLng === null)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'erro'=>'DESTINO_INVALIDO']);
    exit;
}
$raioKm = 20.0; // raio de exibição de oficinas; NÃO é garantia de atendimento.
try {
    $ids = [];
    $aneis = GuinchoDisponibilidade::candidatosPorAnel($lat, $lng);
    foreach ($aneis as $anel) {
        foreach (($anel['candidatos'] ?? []) as $c) {
            $id = (int)($c['id'] ?? 0);
            if ($id > 0) $ids[$id] = true;
        }
    }
    // Nunca conta oficinas legadas diretamente: aprovação e demais gates permanecem no domínio Provider.
    $workshops = ProviderWorkshopService::listarOficinasElegiveis();
    $refLat = $destLat ?? $lat;
    $refLng = $destLng ?? $lng;
    // A lista já vem com a localização resolvida pela autoridade central;
    // não refazer aqui uma segunda leitura do cadastro legado.
    $n = AdminWorkshopGeoResolver::countWithinRadius($workshops, [], $refLat, $refLng, $raioKm);
    $diagnostico = AdminNetworkDiagnostic::build(getPDO(), $aneis, $refLat, $refLng, $raioKm);
    echo json_encode(['ok'=>true,'guinchos'=>count($ids),'oficinas'=>$n,'raio_oficinas_km'=>$raioKm,'diagnostico'=>$diagnostico], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[GF-ADMIN-DISPONIBILIDADE] '.$e->getMessage());
    http_response_code(503);
    echo json_encode(['ok'=>false,'erro'=>'CONSULTA_INDISPONIVEL']);
}
