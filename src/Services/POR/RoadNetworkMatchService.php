<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/PorThresholds.php';
require_once __DIR__ . '/../DebugMode.php';

/**
 * Ader�ncia real � malha vi�ria (gap identificado em auditoria: o
 * antifraude existente - LocationValidationService - nunca confere se um
 * ponto GPS est� de fato sobre uma via, s� valida precis�o/sequ�ncia/tempo/
 * velocidade em linha reta). Usa o endpoint `/nearest` do OSRM (Project OSRM
 * API), que devolve a via rote�vel mais pr�xima de uma coordenada.
 *
 * Deliberadamente N�O valida sentido/m�o de tr�fego ainda - isso exigiria
 * map-matching de uma janela de pontos sequenciais (`/match`, n�o `/nearest`)
 * comparando o rumo (bearing) do trecho percorrido contra a geometria da via
 * casada, o que � bem mais caro computacionalmente e merece decis�o pr�pria
 * sobre custo/infra antes de virar parte do caminho cr�tico de ingest�o de
 * todo ponto GPS do sistema. Ver PorThresholds::roadMatchEnabled().
 *
 * Fail-open por design: qualquer erro de rede, timeout ou resposta
 * inesperada devolve null (n�o sabemos), e o chamador NUNCA deve rejeitar um
 * ponto s� por n�o saber - s� rejeita quando SABE que o ponto est� longe
 * demais de qualquer via. Isso evita que uma instabilidade do servi�o
 * externo derrube a ingest�o de GPS de toda a frota.
 */
class RoadNetworkMatchService
{
    /**
     * Dist�ncia (m) at� a via rote�vel mais pr�xima, ou null se n�o foi
     * poss�vel determinar (rede fora do ar, timeout, resposta inesperada -
     * fail-open, nunca lan�a exce��o).
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
            DebugMode::trace('RoadNetworkMatchService', 'distanceToNearestRoadM', 'por', 'exce��o (fail-open)', [
                'erro' => $e->getMessage(),
            ]);
            return null;
        } finally {
            curl_close($ch);
        }
    }
}
