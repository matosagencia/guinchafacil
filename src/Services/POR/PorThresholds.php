<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Models/Configuracao.php';

/**
 * §A3 (auditoria 21/07) — ponto único de leitura dos thresholds do POR.
 *
 * Antes, cada consumidor (LocationValidationService, DistanceAccumulatorService,
 * MapMatchingService, StreetResolutionService, EvidenceService,
 * PedidoTransitionService) lia `Configuracao::getAll()` e aplicava seu
 * próprio fallback — e dois deles (MapMatchingService/StreetResolutionService)
 * nem liam config nenhuma, tinham 180m HARDCODED pro raio de origem, enquanto
 * EvidenceService/PedidoTransitionService usavam 150m (config `por_arrival_radius_m`)
 * pro mesmo conceito. Ou seja: o raio que decide se uma foto de coleta é
 * aceita (150m) era mais apertado que o raio que o próprio rastreamento usava
 * pra dizer "cheguei na origem" (180m) — inconsistência real, não só
 * duplicação de código. Esta classe fixa 150m/200m como os valores
 * canônicos em todo o pipeline.
 */
final class PorThresholds
{
    private static ?array $cfg = null;

    /** Só para testes: força releitura de Configuracao::getAll() na próxima chamada. */
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

    /** Raio da geofence de origem — usado por validação de evidência, transição de status E rastreamento (antes 150 vs 180 divergiam). */
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

    /** Idade máxima do ponto GPS pra considerar "guincho ativo" (GuinchoController). */
    public static function gpsAtivoStaleSeconds(): int
    {
        return (int)(self::cfg()['por_gps_ativo_stale_seconds'] ?? 120);
    }

    // ─── Aderência à malha viária real (RoadNetworkMatchService) ───────────
    // Gap real identificado em auditoria: LocationValidationService valida
    // precisão/sequência/tempo/velocidade em linha reta, mas nunca confere se
    // o ponto está de fato sobre uma via nem o sentido de tráfego. Esta
    // camada é ADITIVA e vem DESLIGADA por padrão — ligar em produção exige
    // apontar por_road_match_base_url para uma instância OSRM própria
    // (self-hosted). O servidor demo público (router.project-osrm.org) tem
    // rate-limit e ToS que proíbem uso de produção — serve só para QA/dev.

    /** Desligado por padrão: nenhum comportamento muda até isto ser ligado explicitamente. */
    public static function roadMatchEnabled(): bool
    {
        return (string)(self::cfg()['por_road_match_enabled'] ?? '0') === '1';
    }

    /** ATENÇÃO: o default é o servidor demo público do OSRM — só para QA/dev, nunca produção. */
    public static function roadMatchBaseUrl(): string
    {
        // A URL definida no .env tem prioridade para permitir domínios
        // diferentes por ambiente sem alterar a configuração persistida.
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
     * Ponto único de leitura da URL base do serviço de roteamento OSRM-compatible
     * usado pelo FRONTEND (mapas de admin/cliente) para desenhar rotas reais.
     *
     * Antes desta função, 4 views (pedidonovo.php, pedido_trilha.php,
     * pedidodetalhe.php, dashboard.php) tinham 'https://router.project-osrm.org'
     * hardcoded cada uma. O demo público tem rate-limit e ToS que proíbem uso
     * de produção (mesma ressalva já documentada em roadMatchBaseUrl() acima) —
     * agora reaproveita a MESMA chave de config (por_road_match_base_url) usada
     * pelo backend, então apontar para uma instância OSRM própria (self-hosted)
     * exige alterar um único valor, via Configuracao::set() ou a tela admin de
     * governança de config, sem tocar em nenhuma view.
     */
    public static function routingFrontendBaseUrl(): string
    {
        return self::roadMatchBaseUrl();
    }

    /** Distância máxima (m) do ponto até a via mais próxima pra ser considerado "na estrada". */
    public static function roadMatchMaxDistanceM(): float
    {
        return (float)(self::cfg()['por_road_match_max_distance_m'] ?? 60);
    }

    /** Timeout curto e fail-open: se a chamada externa falhar/demorar, o ponto NÃO é rejeitado por isso. */
    public static function roadMatchTimeoutSeconds(): float
    {
        return (float)(self::cfg()['por_road_match_timeout_seconds'] ?? 2.5);
    }

    // ─── Tiers de qualidade de rastreamento (ProofOfRoadService::trackingQuality) ──
    // Não eram configuráveis (literais soltos na lógica); nomeados aqui pra
    // ficarem documentados num único lugar, ainda com os mesmos valores.
    public const QUALITY_POOR_REJECTION_RATE = 0.35;
    public const QUALITY_POOR_MAX_GAP_SECONDS = 300;
    public const QUALITY_FAIR_REJECTION_RATE = 0.15;
    public const QUALITY_FAIR_MAX_GAP_SECONDS = 180;
    public const QUALITY_FAIR_MAX_REJECTED_COUNT = 3;
}
