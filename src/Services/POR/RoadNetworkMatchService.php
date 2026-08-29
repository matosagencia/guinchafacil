<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/PorThresholds.php';
require_once __DIR__ . '/../DebugMode.php';

/**
 * Aderência real à malha viária (gap identificado em auditoria: o
 * antifraude existente — LocationValidationService — nunca confere se um
 * ponto GPS está de fato sobre uma via, só valida precisão/sequência/tempo/
 * velocidade em linha reta). Usa o endpoint `/nearest` do OSRM (Project OSRM
 * API), que devolve a via roteável mais próxima de uma coordenada.
 *
 * Deliberadamente NÃO valida sentido/mão de tráfego ainda — isso exigiria
 * map-matching de uma janela de pontos sequenciais (`/match`, não `/nearest`)
 * comparando o rumo (bearing) do trecho percorrido contra a geometria da via
 * casada, o que é bem mais caro computacionalmente e merece decisão própria
 * sobre custo/infra antes de virar parte do caminho crítico de ingestão de
 * todo ponto GPS do sistema. Ver PorThresholds::roadMatchEnabled().
 *
 * Fail-open por design: qualquer erro de rede, timeout ou resposta
 * inesperada devolve null (não sabemos), e o chamador NUNCA deve rejeitar um
 * ponto só por não saber — só rejeita quando SABE que o ponto está longe
 * demais de qualquer via. Isso evita que uma instabilidade do serviço
 * externo derrube a ingestão de GPS de toda a frota.
 */
class RoadNetworkMatchService
{
    /**
     * Distância (m) até a via roteável mais próxima, ou null se não foi
     * possível determinar (rede fora do ar, timeout, resposta inesperada —
     * fail-open, nunca lança exceção).
     */
    public static function distanceToNearestRoadM(float $lat, float $lng): ?float
    {
        $baseUrl = PorThresholds::roadMatchBaseUrl();
        $timeout = PorThresholds::roadMatchTimeoutSeconds();
        $url = sprintf('%s/nearest/v1/driving/%F,%F', $baseUrl, $lng, $lat);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int)ceil($timeout),
            CURLOPT_TIMEOUT_MS => (int)round($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int)round(min($timeout, 1.5) * 1000),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        try {
            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);

            if ($curlErrno !== 0 || $raw === false || $httpCode !== 200) {
                DebugMode::trace('RoadNetworkMatchService', 'distanceToNearestRoadM', 'por', 'falha de rede/HTTP (fail-open)', [
                    'curl_errno' => $curlErrno,
                    'http_code' => $httpCode,
                ]);
                return null;
            }

            $data = json_decode($raw, true);
            if (!is_array($data) || ($data['code'] ?? '') !== 'Ok' || empty($data['waypoints'][0])) {
                return null;
            }

            $waypoint = $data['waypoints'][0];
            if (!isset($waypoint['distance'])) {
                return null;
            }

            return (float)$waypoint['distance'];
        } catch (Throwable $e) {
            DebugMode::trace('RoadNetworkMatchService', 'distanceToNearestRoadM', 'por', 'exceção (fail-open)', [
                'erro' => $e->getMessage(),
            ]);
            return null;
        } finally {
            curl_close($ch);
        }
    }
}
