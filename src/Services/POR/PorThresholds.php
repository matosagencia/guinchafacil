<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Models/Configuracao.php';

/**
 * �A3 (auditoria 21/07) - ponto �nico de leitura dos thresholds do POR.
 *
 * Antes, cada consumidor (LocationValidationService, DistanceAccumulatorService,
 * MapMatchingService, StreetResolutionService, EvidenceService,
 * PedidoTransitionService) lia `Configuracao::getAll()` e aplicava seu
 * pr�prio fallback - e dois deles (MapMatchingService/StreetResolutionService)
 * nem liam config nenhuma, tinham 180m HARDCODED pro raio de origem, enquanto
 * EvidenceService/PedidoTransitionService usavam 150m (config `por_arrival_radius_m`)
 * pro mesmo conceito. Ou seja: o raio que decide se uma foto de coleta �
 * aceita (150m) era mais apertado que o raio que o pr�prio rastreamento usava
 * pra dizer "cheguei na origem" (180m) - inconsist�ncia real, n�o s�
 * duplica��o de c�digo. Esta classe fixa 150m/200m como os valores
 * can�nicos em todo o pipeline.
 */
final class PorThresholds
{
    private static ?array $cfg = null;

    /** S� para testes: for�a releitura de Configuracao::getAll() na pr�xima chamada. */
    public static function reset(): void
    {
        self::$cfg = null;
    }

    private static function cfg(): array
    {
        if (self::$cfg === null) {
            self::$cfg = Configuracao::getAll();
        }
        return self::$cfg;
    }

    public static function maxAccuracyM(): float
    {
        return (float)(self::cfg()['por_max_accuracy_m'] ?? 80);
    }

    public static function maxGapSeconds(): int
    {
        return (int)(self::cfg()['por_max_gap_seconds'] ?? 180);
    }

    public static function maxSpeedKmh(): float
    {
        return (float)(self::cfg()['por_max_speed_kmh'] ?? 130);
    }

    public static function minPointDistanceM(): float
    {
        return (float)(self::cfg()['por_min_point_distance_m'] ?? 8);
    }

    /** Raio da geofence de origem - usado por valida��o de evid�ncia, transi��o de status E rastreamento (antes 150 vs 180 divergiam). */
    public static function arrivalRadiusM(): float
    {
        return (float)(self::cfg()['por_arrival_radius_m'] ?? 150);
    }

    /** Raio da geofence de destino. */
    public static function destinationRadiusM(): float
    {
        return (float)(self::cfg()['por_destination_radius_m'] ?? 200);
    }

    public static function photoGpsMaxAgeSeconds(): int
    {
        return (int)(self::cfg()['por_photo_gps_max_age_seconds'] ?? 300);
    }

    /** Idade m�xima do ponto GPS pra considerar "guincho ativo" (GuinchoController). */
    public static function gpsAtivoStaleSeconds(): int
    {
        return (int)(self::cfg()['por_gps_ativo_stale_seconds'] ?? 120);
    }

    // ??? Ader�ncia � malha vi�ria real (RoadNetworkMatchService) ???????????
    // Gap real identificado em auditoria: LocationValidationService valida
    // precis�o/sequ�ncia/tempo/velocidade em linha reta, mas nunca confere se
    // o ponto est� de fato sobre uma via nem o sentido de tr�fego. Esta
    // camada � ADITIVA e vem DESLIGADA por padr�o - ligar em produ��o exige
    // apontar por_road_match_base_url para uma inst�ncia OSRM pr�pria
    // (self-hosted). O servidor demo p�blico (router.project-osrm.org) tem
    // rate-limit e ToS que pro�bem uso de produ��o - serve s� para QA/dev.

    /** Desligado por padr�o: nenhum comportamento muda at� isto ser ligado explicitamente. */
    public static function roadMatchEnabled(): bool
    {
        return (string)(self::cfg()['por_road_match_enabled'] ?? '0') === '1';
    }

    /** ATEN��O: o default � o servidor demo p�blico do OSRM - s� para QA/dev, nunca produ��o. */
    public static function roadMatchBaseUrl(): string
    {
        // A URL definida no .env tem prioridade para permitir dom�nios
        // diferentes por ambiente sem alterar a configura��o persistida.
        $envUrl = function_exists('env')
            ? trim((string)env('OSRM_BASE_URL', ''))
            : trim((string)(getenv('OSRM_BASE_URL') ?: ''));
        if ($envUrl !== '') {
            return rtrim($envUrl, '/');
        }

        $url = trim((string)(self::cfg()['por_road_match_base_url'] ?? ''));
        return $url !== '' ? rtrim($url, '/') : 'https://router.project-osrm.org';
    }

    /**
     * Ponto �nico de leitura da URL base do servi�o de roteamento OSRM-compatible
     * usado pelo FRONTEND (mapas de admin/cliente) para desenhar rotas reais.
     *
     * Antes desta fun��o, 4 views (pedidonovo.php, pedido_trilha.php,
     * pedidodetalhe.php, dashboard.php) tinham 'https://router.project-osrm.org'
     * hardcoded cada uma. O demo p�blico tem rate-limit e ToS que pro�bem uso
     * de produ��o (mesma ressalva j� documentada em roadMatchBaseUrl() acima) -
     * agora reaproveita a MESMA chave de config (por_road_match_base_url) usada
     * pelo backend, ent�o apontar para uma inst�ncia OSRM pr�pria (self-hosted)
     * exige alterar um �nico valor, via Configuracao::set() ou a tela admin de
     * governan�a de config, sem tocar em nenhuma view.
     */
    public static function routingFrontendBaseUrl(): string
    {
        $basePath = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : '';
        return $basePath . '/api/routing/osrm';
    }

    /** Dist�ncia m�xima (m) do ponto at� a via mais pr�xima pra ser considerado "na estrada". */
    public static function roadMatchMaxDistanceM(): float
    {
        return (float)(self::cfg()['por_road_match_max_distance_m'] ?? 60);
    }

    /** Timeout curto e fail-open: se a chamada externa falhar/demorar, o ponto N�O � rejeitado por isso. */
    public static function roadMatchTimeoutSeconds(): float
    {
        return (float)(self::cfg()['por_road_match_timeout_seconds'] ?? 2.5);
    }

    // ??? Tiers de qualidade de rastreamento (ProofOfRoadService::trackingQuality) ??
    // N�o eram configur�veis (literais soltos na l�gica); nomeados aqui pra
    // ficarem documentados num �nico lugar, ainda com os mesmos valores.
    public const QUALITY_POOR_REJECTION_RATE = 0.35;
    public const QUALITY_POOR_MAX_GAP_SECONDS = 300;
    public const QUALITY_FAIR_REJECTION_RATE = 0.15;
    public const QUALITY_FAIR_MAX_GAP_SECONDS = 180;
    public const QUALITY_FAIR_MAX_REJECTED_COUNT = 3;
}
