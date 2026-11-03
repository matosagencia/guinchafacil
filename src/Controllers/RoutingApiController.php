<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/Logger.php';
require_once __DIR__ . '/../Services/POR/PorThresholds.php';

final class RoutingApiController extends BaseController
{
    public function route(string $path = ''): void
    {
        $suffix = trim($path);
        if ($suffix === '' || !preg_match('/^[0-9,\.\-;]+$/', $suffix)) {
            $this->json(['ok' => false, 'error' => 'invalid_route_path'], 422);
            return;
        }

        $query = isset($_SERVER['QUERY_STRING']) ? trim((string)$_SERVER['QUERY_STRING']) : '';
        $upstream = rtrim(PorThresholds::roadMatchBaseUrl(), '/') . '/route/v1/driving/' . $suffix;
        if ($query !== '') {
            $upstream .= '?' . $query;
        }

        $body = false;
        $httpCode = 0;
        $curlErr = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($upstream);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT_MS => 1500,
                CURLOPT_TIMEOUT_MS => 6000,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: GuinchaFacil-RouteProxy/1.0'],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            if ($ca = ca_bundle_path()) curl_setopt($ch, CURLOPT_CAINFO, $ca);
            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'GET', 'timeout' => 6,
                'header' => "Accept: application/json\r\nUser-Agent: GuinchaFacil-RouteProxy/1.0\r\n",
                'ignore_errors' => true,
            ]]);
            $body = @file_get_contents($upstream, false, $context);
            foreach (($http_response_header ?? []) as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
                    $httpCode = (int)$match[1];
                    break;
                }
            }
            if ($body === false) $curlErr = 'http_stream_failed';
        }

        if ($curlErr !== '' || $body === false) {
            Logger::event([
                'level' => Logger::LEVEL_WARN,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'ROUTE',
                'phase' => 'proxy',
                'code' => 'ROUTE-PROXY-001',
                'message' => 'Falha ao buscar rota no upstream.',
                'context' => [
                    'upstream' => $upstream,
                    'curl_error' => $curlErr,
                ],
            ]);
            $this->json(['ok' => false, 'error' => 'route_upstream_unavailable'], 502);
            return;
        }

        http_response_code($httpCode > 0 ? $httpCode : 502);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo $body;
    }

    private function json(array $payload, int $status): void
    {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
