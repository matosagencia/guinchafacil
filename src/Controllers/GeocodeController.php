<?php
// File: guinchafacil/src/Controllers/GeocodeController.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/GeocodingService.php';

class GeocodeController extends BaseController
{
    /** Busca pública limitada, usada antes do cadastro na pré-cotação. */
    public function searchPublic(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $query = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($query) < 3 || mb_strlen($query) > 220) { http_response_code(422); echo json_encode(['ok'=>false,'erro'=>'consulta_invalida']); exit; }
        $agora = microtime(true); $ultimo = (float)($_SESSION['public_geocode_last'] ?? 0);
        if (($agora - $ultimo) < 0.35) { http_response_code(429); echo json_encode(['ok'=>false,'erro'=>'aguarde']); exit; }
        $_SESSION['public_geocode_last'] = $agora;
        $result = (new GeocodingService())->geocode($query);
        echo json_encode($result ? ['ok'=>true,'items'=>[$result],'result'=>$result] : ['ok'=>false,'items'=>[],'erro'=>'nao_encontrado'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
    }

    public function search(): void
    {
        header('Content-Type: application/json');

        if (!AuthService::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'nao_autenticado']);
            exit;
        }

        $query = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($query) < 3 || mb_strlen($query) > 220) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'consulta_invalida']);
            exit;
        }

        $result = (new GeocodingService())->geocode($query);
        if (!$result) {
            echo json_encode(['ok' => false, 'erro' => 'nao_encontrado']);
            exit;
        }

        echo json_encode(['ok' => true, 'result' => $result]);
        exit;
    }

    public function reverse(): void
    {
        header('Content-Type: application/json');

        if (!AuthService::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'nao_autenticado']);
            exit;
        }

        $lat = (float)($_GET['lat'] ?? 0);
        $lng = (float)($_GET['lng'] ?? 0);
        if ($lat === 0.0 || $lng === 0.0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'coordenadas_invalidas']);
            exit;
        }

        $service = new GeocodingService();
        $result = $service->reverseGeocode($lat, $lng);
        if (!$result) {
            echo json_encode(['ok' => false, 'erro' => 'nao_encontrado']);
            exit;
        }

        echo json_encode(['ok' => true, 'result' => $result]);
        exit;
    }
}
