<?php

// File: guinchafacil/src/Controllers/GuinchoController.php



require_once __DIR__ . '/../../config.php';

require_once __DIR__ . '/../Services/AuthService.php';

require_once __DIR__ . '/BaseController.php';

require_once __DIR__ . '/../Services/RankingService.php';

require_once __DIR__ . '/../Services/NotificacaoService.php';

require_once __DIR__ . '/../Services/PixService.php';

require_once __DIR__ . '/../Models/Guincho.php';

require_once __DIR__ . '/../Models/Pedido.php';

require_once __DIR__ . '/../Models/Pagamento.php';

require_once __DIR__ . '/../Models/Chat.php';

require_once __DIR__ . '/../Models/Configuracao.php';

require_once __DIR__ . '/../Models/Avaliacao.php';

require_once __DIR__ . '/../Models/PedidoEvidencia.php';

require_once __DIR__ . '/../Services/POR/ProofOfRoadService.php';

require_once __DIR__ . '/../Services/POR/GeofenceService.php';

require_once __DIR__ . '/../Services/POR/PorThresholds.php';

require_once __DIR__ . '/../Services/Evidence/EvidenceService.php';

require_once __DIR__ . '/../Services/Pedido/PedidoTransitionService.php';

require_once __DIR__ . '/../Services/PaymentJobService.php';

require_once __DIR__ . '/../Services/ComunicadoService.php';

require_once __DIR__ . '/../Models/Catalog/ServiceCategory.php';

require_once __DIR__ . '/../Models/Catalog/ServiceType.php';

require_once __DIR__ . '/../Models/Catalog/ProviderCapability.php';

require_once __DIR__ . '/../Services/Dispatch/ProviderVehicleCompatibilityService.php';

require_once __DIR__ . '/../Models/PedidoDiagnostico.php';

require_once __DIR__ . '/../Models/PedidoOrcamento.php';

require_once __DIR__ . '/../Services/Diagnostico/DiagnosticoService.php';

require_once __DIR__ . '/../Models/Produto.php';

require_once __DIR__ . '/../Models/ProviderProdutoEstoque.php';

require_once __DIR__ . '/../Services/Estoque/EstoqueService.php';

require_once __DIR__ . '/../Services/ProofOfService/ProofOfServiceService.php';



/**

 * Controller do perfil Guincho

 */

class GuinchoController extends BaseController

{

    // Â§GPS-CONFIRM-01: idade mÃ¡xima (em segundos) de uma confirmaÃ§Ã£o de

    // localizaÃ§Ã£o antes de o guincho ser obrigado a confirmar de novo, mesmo

    // dentro da MESMA sessÃ£o logada (evita ficar Online por horas/dias com

    // uma posiÃ§Ã£o velha â€” a raiz do bug real de matching do pedido 1539).

    private const LOCALIZACAO_MAX_IDADE_SEGUNDOS = 4 * 3600;



    public function __construct(){ parent::__construct(); }



    /** LocalizaÃ§Ã£o confirmada nesta sessÃ£o e ainda dentro da validade? */

    private static function localizacaoConfirmadaValida(): bool

    {

        $confirmadaEm = $_SESSION['guincho_localizacao_confirmada_em'] ?? null;

        if (!is_int($confirmadaEm)) return false;

        return (time() - $confirmadaEm) <= self::LOCALIZACAO_MAX_IDADE_SEGUNDOS;

    }



    /**

     * Dashboard: mapa com pedidos disponÃ­veis ao redor

     */

    public function dashboard(): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) {

            $this->redirect('/login');

        }

        

        // Pedido ativo atual (se houver)

        $pedidoAtivo = Pedido::buscarAtivoDoGuincho($guincho['id']);



        // VariÃ¡veis para a view

        $disponivel = (bool)$guincho['disponivel'];



        // EstatÃ­sticas do dia

        $pdo = getPDO();

        $stats = $pdo->prepare(

            "SELECT

                COUNT(*) AS atendimentos_hoje,

                COALESCE(SUM(p.custo_final), 0) AS ganho_hoje,

                COALESCE(SUM(p.distancia_km), 0) AS km_hoje

             FROM pedidos p

             WHERE p.guincho_id = ? AND p.status = 'concluido' AND DATE(p.criado_em) = CURDATE()"

        );

        $stats->execute([$guincho['id']]);

        $statsHoje = $stats->fetch(PDO::FETCH_ASSOC);



        $atendimentosHoje = (int)($statsHoje['atendimentos_hoje'] ?? 0);

        $ganhoHoje        = (float)($statsHoje['ganho_hoje'] ?? 0);

        $kmPercorridos    = (float)($statsHoje['km_hoje'] ?? 0);

        $notaMedia        = (float)($guincho['reputacao'] ?? 0);

        $raioCoberturaKm  = (int)($guincho['raio_cobertura_km'] ?? 20);

        $pedidosRecentes   = Pedido::listarPorGuincho($guincho['id'], 1, 4);

        $comunicados = ComunicadoService::resolveActiveForProfile('guincho', ComunicadoService::PLACEMENT_TOW_DASHBOARD_AFTER_STATS, 3);



        $csrfToken = AuthService::gerarCsrfToken();



        // Â§GPS-CONFIRM-01: guincho precisa confirmar a localizaÃ§Ã£o atual

        // (via navigator.geolocation, nÃ£o IP) a cada login e periodicamente

        // â€” ver GuinchoController::localizacaoConfirmadaValida(). Sem isso,

        // lat_atual/lng_atual podia ficar congelado por dias enquanto o

        // guincho ficava Online sÃ³ esperando oferta, quebrando o matching

        // por distÃ¢ncia silenciosamente (achado real: pedido 1539).

        $precisaConfirmarLocalizacao = !self::localizacaoConfirmadaValida();



        // Defesa em profundidade: se o guincho estÃ¡ marcado Online (de uma

        // sessÃ£o/dia anterior) mas ainda nÃ£o confirmou a localizaÃ§Ã£o nesta

        // sessÃ£o, e nÃ£o estÃ¡ no meio de um atendimento (nunca derruba um

        // serviÃ§o em andamento), tira ele de disponÃ­vel AGORA â€” nÃ£o espera

        // o clique no modal. Assim, mesmo que o JS do modal falhe por algum

        // motivo, o guincho nÃ£o fica recebendo ofertas com posiÃ§Ã£o velha.

        if ($precisaConfirmarLocalizacao && $disponivel && !$pedidoAtivo) {

            Guincho::atualizarDisponibilidade($guincho['id'], false);

            $disponivel = false;

            Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'MATCHING', 'Guincho marcado Offline automaticamente por falta de confirmaÃ§Ã£o de localizaÃ§Ã£o vÃ¡lida', [

                'guincho_id' => $guincho['id'],

                'code' => 'GPS-CONFIRM-AUTO-OFFLINE',

            ]);

        }



        // Fila de ofertas aguardando (sÃ³ exibe se o guincho nÃ£o tiver corrida ativa) â€”

        // reaproveita a mesma lÃ³gica de distÃ¢ncia/score de pedidosDisponiveis(), mas

        // jÃ¡ enriquecida com os campos exigidos pelo card "Nova solicitaÃ§Ã£o" e pela

        // lista "Fila de ofertas".

        $pedidoPendente = null;

        $ofertasFila = [];

        if (!$pedidoAtivo && $disponivel) {

            $ofertasFila = $this->montarOfertasDisponiveis($guincho);

            $pedidoPendente = $ofertasFila[0] ?? null;

        }



        // Proof-of-Road: sÃ³ faz sentido com atendimento em andamento (hÃ¡ pontos

        // de rastreamento sendo ingeridos). Sem pedido ativo, o painel exibe o

        // estado "sem atendimento" na prÃ³pria view.

        $porPanel = null;

        if ($pedidoAtivo) {

            $porPanel = $this->montarProofOfRoadPanel((int)$pedidoAtivo['id'], (string)($pedidoAtivo['status'] ?? ''));

        }



        // Â§CELULAS-NITEROI-01 (05/08/2026): aviso PERSISTENTE (nÃ£o sÃ³ no

        // momento do cadastro) quando a cÃ©lula da base do guincho estÃ¡

        // 'pedra_morta' â€” sem isso, o prestador vÃª "0 chamados" dia apÃ³s

        // dia e conclui que o app estÃ¡ com bug, quando na verdade a regiÃ£o

        // dele ainda nÃ£o foi ativada operacionalmente. Nunca bloqueia nada,

        // sÃ³ informa. Silencioso (nÃ£o calcula/mostra) se nÃ£o houver

        // lat_operacao/lng_operacao ou nenhuma cÃ©lula mapeada â€” mesma regra

        // aditiva do gate de cadastro.

        $avisoCelulaPedraMorta = null;

        if (!empty($guincho['lat_operacao']) && !empty($guincho['lng_operacao'])) {

            require_once __DIR__ . '/../Services/Pricing/ZonePricingService.php';

            $zonaDoGuincho = ZonePricingService::resolverZonaPorCoordenada((float)$guincho['lat_operacao'], (float)$guincho['lng_operacao']);

            if ($zonaDoGuincho !== null && ($zonaDoGuincho['status_expansao'] ?? '') === 'pedra_morta') {

                $avisoCelulaPedraMorta = 'Sua regiÃ£o ("' . $zonaDoGuincho['name'] . '") ainda estÃ¡ em fase de validaÃ§Ã£o operacional â€” por isso vocÃª ainda nÃ£o recebe chamados de clientes daqui. NÃ£o Ã© um erro do seu cadastro; assim que essa regiÃ£o for ativada pela nossa equipe, os pedidos passam a chegar normalmente.';

            }

        }



        require __DIR__ . '/../Views/guincho/dashboard.php';

    }



    /**

     * Monta a fila de ofertas disponÃ­veis para o guincho, ordenada por

     * compatibilidade (mesmo score de pedidosDisponiveis()), jÃ¡ com

     * distÃ¢ncia atÃ© o cliente, ETA estimado, bairro e tempo relativo desde

     * a criaÃ§Ã£o â€” dados reais consumidos pelo card "Nova solicitaÃ§Ã£o" e

     * pela lista "Fila de ofertas" do dashboard.

     */

    private function montarOfertasDisponiveis(array $guincho, int $limite = 6): array

    {
        require_once __DIR__ . '/../Services/Finance/BloqueioFinanceiroService.php';
        if (BloqueioFinanceiroService::guinchoEstaBloqueado((int)($guincho['id'] ?? 0))) {
            error_log('[FIN-BLOCK-MATCH] guincho bloqueado excluído do matching.');
            return [];
        }

        $latRaw = $guincho['lat_atual'] ?? ($guincho['lat_operacao'] ?? null);

        $lngRaw = $guincho['lng_atual'] ?? ($guincho['lng_operacao'] ?? null);

        $lat = is_numeric($latRaw) ? (float)$latRaw : null;

        $lng = is_numeric($lngRaw) ? (float)$lngRaw : null;

        $semLocalizacao = ($lat === null || $lng === null || ((float)$lat === 0.0 && (float)$lng === 0.0));



        // Â§COBERTURA-RAIO-01 (05/08/2026): raio efetivo agora respeita o

        // raio_cobertura_km prÃ³prio do guincho (antes ignorado â€” sÃ³ o teto

        // global raio_maximo_km filtrava), sem nunca ultrapassar esse teto.

        // Ver CoberturaService para a mesma regra usada no gate de abertura

        // de pedido do cliente.

        require_once __DIR__ . '/../Services/CoberturaService.php';

        $cfg  = Configuracao::getAll();

        $raio = CoberturaService::raioEfetivoGuincho($guincho, (float)($cfg['raio_maximo_km'] ?? 50));



        $aguardando = Pedido::listarAguardandoGuincho();

        $ofertas = [];

        $ofertasAbaixoDoScore = [];

        foreach ($aguardando as $pedido) {

            // Etapa 4 (matching por capacidade): pedidos de REBOQUE continuam

            // visÃ­veis a qualquer guincho aprovado, exatamente como sempre

            // funcionou â€” nada muda aqui para nÃ£o regredir o Ãºnico fluxo em

            // produÃ§Ã£o real. SÃ³ os NOVOS tipos de serviÃ§o (ON_SITE/HYBRID:

            // partida auxiliar, pneu, chaveiro etc.) exigem capacidade

            // aprovada (provider_capabilities) para aparecerem na fila.

            $attendanceMode = (string)($pedido['attendance_mode'] ?? 'TOWING');

            if ($attendanceMode === 'TOWING') {

                // Reboque sÃ³ Ã© ofertado a prestador com reboque APROVADO â€”

                // especialista (chaveiro/elÃ©trica/pneu) nunca recebe reboque.

                if ((int)($guincho['reboque_aprovado'] ?? 1) !== 1) {

                    continue;

                }

            } else {

                $serviceTypeId = (int)($pedido['service_type_id'] ?? 0);

                if ($serviceTypeId <= 0 || !ProviderCapability::possuiCapacidadeAprovada((int)$guincho['id'], $serviceTypeId)) {

                    continue;

                }

            }



            // Etapa 15 â€” compatibilidade prestador Ã— veÃ­culo. Esconde pedidos

            // INELIGIBLE; deixa passar ELIGIBLE e REQUIRES_CONFIRMATION (estes

            // aparecem com aviso, mas o aceite direto Ã© barrado na transaÃ§Ã£o).

            // Fallback conservador: sem config veicular, reboque = ELIGIBLE.

            $compatStatus = null; $compatWarnings = [];

            $serviceTypeIdCmp = (int)($pedido['service_type_id'] ?? 0);

            if ($serviceTypeIdCmp > 0) {

                $compat = ProviderVehicleCompatibilityService::evaluate(new CompatibilityRequest(

                    (int)$pedido['id'], (int)$guincho['id'], $serviceTypeIdCmp, CompatibilityRequest::OP_QUEUE_FILTER

                ));

                if (!$compat->allowsOffer()) {

                    continue;

                }

                $compatStatus = $compat->getStatus();

                $compatWarnings = $compat->getWarnings();

            }



            $distancia = $semLocalizacao ? 0.0 : GeoService::haversine(

                $lat, $lng,

                (float)$pedido['lat_origem'],

                (float)$pedido['lng_origem']

            );

            if (!$semLocalizacao && $distancia > $raio) continue;



            $score = $semLocalizacao ? 1.0 : RankingService::calcularScore($distancia, (float)($guincho['reputacao'] ?? 0));

            $scoreMinimoAtual = (float)($pedido['score_minimo_atual'] ?? 0.0);
            $abaixoDoScore = !$semLocalizacao && $scoreMinimoAtual > 0.0 && $score < $scoreMinimoAtual;



            $enderecoOrigem = (string)($pedido['endereco_origem'] ?? '');

            $partesEndereco = array_map('trim', explode(',', $enderecoOrigem));

            $bairro = $partesEndereco[1] ?? ($partesEndereco[0] ?? $enderecoOrigem);



            $segundosDesde = 0;

            if (!empty($pedido['criado_em'])) {

                $segundosDesde = max(0, time() - strtotime((string)$pedido['criado_em']));

            }



            // ETA estimado: velocidade mÃ©dia urbana de ~28km/h atÃ© o cliente.

            $etaMin = $semLocalizacao ? null : max(1, (int)ceil(($distancia / 28) * 60));



            $ofertaItem = [

                'id'                 => (int)$pedido['id'],

                'tipo_problema'      => (string)($pedido['tipo_problema'] ?? ''),

                'marca'              => (string)($pedido['marca'] ?? ''),

                'modelo'             => (string)($pedido['modelo'] ?? ''),

                'endereco_origem'    => $enderecoOrigem,

                'endereco_destino'   => (string)($pedido['endereco_destino'] ?? ''),

                'bairro'             => $bairro !== '' ? $bairro : $enderecoOrigem,

                'distancia_km'       => round($distancia, 1),

                'distancia_servico_km' => (float)($pedido['distancia_km'] ?? 0),

                'custo_estimado'     => (float)($pedido['custo_estimado'] ?? 0),
                'forma_pagamento_escolhida' => (string)($pedido['forma_pagamento_escolhida'] ?? ''),
                'provedor_pagamento_escolhido' => (string)($pedido['provedor_pagamento_escolhido'] ?? ''),

                'eta_min'            => $etaMin,

                'score'              => round($score, 4),

                'segundos_desde_criacao' => $segundosDesde,

                'expiracao_aceite'   => (string)($pedido['expiracao_aceite'] ?? ''),

                'compat_status'      => $compatStatus,

                'compat_warnings'    => $compatWarnings,
            ];
            if ($abaixoDoScore) { $ofertasAbaixoDoScore[] = $ofertaItem; } else { $ofertas[] = $ofertaItem; }

        }



        if (empty($ofertas) && !empty($ofertasAbaixoDoScore)) {
            error_log(sprintf('[GuinchoController::montarOfertasDisponiveis] FALLBACK SCORE: %d pedido(s) abaixo do score minimo (guincho=%d)', count($ofertasAbaixoDoScore), (int)$guincho['id']));
            $ofertas = $ofertasAbaixoDoScore;
        }
        usort($ofertas, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($ofertas, 0, $limite);

    }



    /**

     * Monta os dados reais do painel "Proof-of-Road" a partir do

     * ProofOfRoadService (rastreamento por GPS do atendimento em curso):

     * status do GPS, precisÃ£o do Ãºltimo ponto vÃ¡lido, quantidade de pontos

     * vÃ¡lidos/rejeitados e hÃ¡ quanto tempo o Ãºltimo ponto foi recebido.

     */

    private function montarProofOfRoadPanel(int $pedidoId, string $statusPedido): array

    {

        $snapshot = ProofOfRoadService::getCurrentSnapshot($pedidoId);

        $ultimoValido = $snapshot['last_valid_point'] ?? null;

        $ultimoPonto  = $snapshot['last_point'] ?? null;



        // Fase corrente: coleta (a_caminho/no_local â†’ indo atÃ© a origem) ou

        // entrega (em_reboque â†’ indo atÃ© o destino). Usa o resumo da fase

        // atual quando existir; combina origem+destino como fallback.

        $fase = in_array($statusPedido, ['em_reboque', 'concluido'], true) ? 'destino' : 'origem';

        $resumoFase = $snapshot['summary_' . $fase] ?? null;

        $resumoOrigem = $snapshot['summary_origem'] ?? null;

        $resumoDestino = $snapshot['summary_destino'] ?? null;



        $pontosValidos   = (int)($resumoFase['valid_points'] ?? (($resumoOrigem['valid_points'] ?? 0) + ($resumoDestino['valid_points'] ?? 0)));

        $pontosRejeitados = (int)($resumoFase['rejected_points'] ?? (($resumoOrigem['rejected_points'] ?? 0) + ($resumoDestino['rejected_points'] ?? 0)));

        $qualidade = (string)($resumoFase['tracking_quality'] ?? 'unknown');



        $ultimoTimestamp = $ultimoValido['server_timestamp'] ?? ($ultimoPonto['server_timestamp'] ?? null);

        $segundosDesdeUltimo = null;

        if ($ultimoTimestamp) {

            $segundosDesdeUltimo = max(0, time() - strtotime((string)$ultimoTimestamp));

        }



        return [

            'gps_ativo'          => $ultimoValido !== null && $segundosDesdeUltimo !== null && $segundosDesdeUltimo <= PorThresholds::gpsAtivoStaleSeconds(),

            'precisao_m'         => isset($ultimoValido['accuracy_m']) ? round((float)$ultimoValido['accuracy_m'], 0) : null,

            'pontos_validos'     => $pontosValidos,

            'pontos_rejeitados'  => $pontosRejeitados,

            'qualidade'          => $qualidade,

            'qualidade_pct'      => $snapshot['qualidade_pct'] ?? null,

            'rota_integra'       => $snapshot['rota_integra'] ?? true,

            'segundos_desde_ultimo_ponto' => $segundosDesdeUltimo,

            'tem_pontos'         => $ultimoPonto !== null,

        ];

    }



    /**

     * AJAX: atualiza localizaÃ§Ã£o do guincho

     */

    public function atualizarLocalizacao(): void

    {

        AuthService::requireAuth('guincho');

        header('Content-Type: application/json');

        

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            echo json_encode(['ok' => false, 'erro' => 'MÃ©todo invÃ¡lido']);

            exit;

        }

        

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            echo json_encode(['ok' => false, 'erro' => 'Token invÃ¡lido']);

            exit;

        }

        

        $usuario = AuthService::getCurrentUser();

        $lat = filter_var($_POST['latitude'] ?? ($_POST['lat'] ?? ''), FILTER_VALIDATE_FLOAT);

        $lng = filter_var($_POST['longitude'] ?? ($_POST['lng'] ?? ''), FILTER_VALIDATE_FLOAT);

        

        if ($lat === false || $lng === false) {

            // Â§LOG-GC-01 (29/07/2026): antes, coordenada invÃ¡lida vinda do

            // navegador (ex.: geolocation mock/erro do device) morria aqui

            // sem log nenhum â€” o painel do guincheiro simplesmente parava de

            // avanÃ§ar e nÃ£o havia como saber, olhando o log do admin, que a

            // requisiÃ§Ã£o tinha chegado e sido descartada por essa razÃ£o.

            Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'POR', 'LocalizaÃ§Ã£o recusada: coordenadas invÃ¡lidas', [

                'guincho_id' => $usuario['id'] ?? null,

                'code' => 'POR-LOC-001',

                'lat_recebido' => $_POST['latitude'] ?? ($_POST['lat'] ?? null),

                'lng_recebido' => $_POST['longitude'] ?? ($_POST['lng'] ?? null),

            ]);

            echo json_encode(['ok' => false, 'erro' => 'Coordenadas invÃ¡lidas']);

            exit;

        }



        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        Guincho::atualizarLocalizacao($guincho['id'], $lat, $lng);



        // Â§GPS-CONFIRM-01: qualquer atualizaÃ§Ã£o de posiÃ§Ã£o bem-sucedida

        // (venha do modal de confirmaÃ§Ã£o no login, venha do rastreamento

        // durante atendimento ativo) conta como confirmaÃ§Ã£o fresca â€” reseta

        // o relÃ³gio de validade em GuinchoController::localizacaoConfirmadaValida().

        $_SESSION['guincho_localizacao_confirmada_em'] = time();



        $pedidoId = (int)($_POST['pedido_id'] ?? 0);

        if ($pedidoId <= 0) {

            $ativo = Pedido::buscarAtivoDoGuincho((int)$guincho['id']);

            $pedidoId = (int)($ativo['id'] ?? 0);

        }



        $por = ['ok' => true, 'accepted' => true];

        if ($pedidoId > 0) {

            $por = ProofOfRoadService::ingestPoint($pedidoId, (int)$guincho['id'], (int)$usuario['id'], [

                'latitude' => $lat,

                'longitude' => $lng,

                'accuracy_m' => $_POST['accuracy_m'] ?? null,

                'speed_mps' => $_POST['speed_mps'] ?? null,

                'heading_deg' => $_POST['heading_deg'] ?? null,

                'device_timestamp' => $_POST['device_timestamp'] ?? null,

                'sequence' => $_POST['sequence'] ?? null,

                'client_point_id' => $_POST['client_point_id'] ?? null,

            ]);

        } else {

            // Â§LOG-GC-02 (29/07/2026): sem pedido_id resolvÃ­vel (nem enviado

            // pelo cliente, nem achado como "ativo" do guincho), o ponto de

            // GPS Ã© aceito sÃ³ pra atualizar a posiÃ§Ã£o bruta do guincho, mas

            // NENHUM rastreamento por pedido acontece â€” isso Ã© fÃ¡cil de

            // confundir com "o ponto foi ingerido normalmente" se nÃ£o logado.

            Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'POR', 'LocalizaÃ§Ã£o recebida sem pedido_id resolvÃ­vel â€” POR nÃ£o foi ingerido, sÃ³ a posiÃ§Ã£o bruta do guincho foi atualizada', [

                'guincho_id' => $guincho['id'] ?? null,

                'code' => 'POR-LOC-002',

            ]);

        }



        echo json_encode(array_merge(['ok' => true], $por));

        exit;

    }



    /**

     * AJAX: alterna disponibilidade online/offline

     */

    public function toggleDisponibilidade(): void

    {

        AuthService::requireAuth('guincho');

        header('Content-Type: application/json');



        // Aceita tanto FormData (POST) quanto JSON body

        $input = [];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {

            $raw   = file_get_contents('php://input');

            $input = json_decode($raw, true) ?: [];

        } else {

            $input = $_POST;

        }



        if (!AuthService::validarCsrfToken($input['csrf_token'] ?? '')) {

            echo json_encode(['ok' => false, 'erro' => 'Token invÃ¡lido']);

            exit;

        }



        $usuario    = AuthService::getCurrentUser();

        $guincho    = Guincho::buscarPorUsuario($usuario['id']);



        if (!$guincho) {

            echo json_encode(['ok' => false, 'erro' => 'Guincho nÃ£o encontrado']);

            exit;

        }



        // Usa o estado desejado enviado pelo cliente; se nÃ£o vier, inverte o atual

        // IMPORTANTE: (int) antes do cast â€” FormData envia "0"/"1" como string,

        // e em PHP a string "0" Ã© falsy, mas qualquer outra string nÃ£o-vazia Ã© truthy.

        // Usar intval() garante comportamento correto.

        if (isset($input['disponivel'])) {

            $novoStatus = (intval($input['disponivel']) === 1) ? 1 : 0;

        } else {

            $novoStatus = $guincho['disponivel'] ? 0 : 1;

        }



        // Guincho nÃ£o aprovado nÃ£o pode se marcar disponÃ­vel

        if ($novoStatus === 1 && !$guincho['aprovado']) {

            echo json_encode(['ok' => false, 'erro' => 'Seu cadastro ainda nÃ£o foi aprovado pelo administrador.', 'disponivel' => 0]);

            exit;

        }



        // Â§GPS-CONFIRM-01: reforÃ§o server-side â€” mesmo que alguÃ©m contorne o

        // modal do dashboard e chame esta rota direto, nÃ£o deixa ficar

        // Online sem uma confirmaÃ§Ã£o de localizaÃ§Ã£o fresca (evita voltar a

        // reproduzir o bug real do pedido 1539: lat_atual congelado).

        if ($novoStatus === 1 && !self::localizacaoConfirmadaValida()) {

            Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'MATCHING', 'Tentativa de ficar Online sem confirmaÃ§Ã£o de localizaÃ§Ã£o vÃ¡lida', [

                'guincho_id' => $guincho['id'],

                'code' => 'GPS-CONFIRM-BLOQUEIO',

            ]);

            echo json_encode(['ok' => false, 'erro' => 'Confirme sua localizaÃ§Ã£o atual antes de ficar disponÃ­vel.', 'disponivel' => 0, 'precisa_confirmar_localizacao' => true]);

            exit;

        }



        Guincho::atualizarDisponibilidade($guincho['id'], (bool)$novoStatus);

        echo json_encode(['ok' => true, 'disponivel' => $novoStatus]);

        exit;

    }



    /**

     * AJAX: retorna pedidos disponÃ­veis ao redor do guincho

     */

    public function pedidosDisponiveis(): void

    {

        AuthService::requireAuth('guincho', false);

        header('Content-Type: application/json; charset=UTF-8');

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        header('Pragma: no-cache');

        

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);



        if (!$guincho['disponivel'] || !$guincho['aprovado']) {

            echo json_encode(['ok' => true, 'pedidos' => []]);

            exit;

        }



        $latRaw = $guincho['lat_atual'] ?? ($guincho['lat_operacao'] ?? null);

        $lngRaw = $guincho['lng_atual'] ?? ($guincho['lng_operacao'] ?? null);

        $lat = is_numeric($latRaw) ? (float)$latRaw : null;

        $lng = is_numeric($lngRaw) ? (float)$lngRaw : null;

        $semLocalizacao = ($lat === null || $lng === null || ($lat == 0.0 && $lng == 0.0));



        // Reaproveita a mesma lÃ³gica de distÃ¢ncia/score/enriquecimento usada

        // no dashboard, para que a fila renderizada via SSE/polling seja

        // idÃªntica (mesmos campos) Ã  renderizada no load inicial da pÃ¡gina.

        $ofertas = $this->montarOfertasDisponiveis($guincho, 20);



        $resultado = array_map(function ($o) {

            return [

                'id'                   => $o['id'],

                'tipo_problema'        => htmlspecialchars($o['tipo_problema']),

                'endereco_origem'      => htmlspecialchars($o['endereco_origem']),

                'endereco_destino'     => htmlspecialchars($o['endereco_destino']),

                'bairro'               => htmlspecialchars($o['bairro']),

                'marca'                => htmlspecialchars($o['marca']),

                'modelo'               => htmlspecialchars($o['modelo']),

                'distancia_km'         => $o['distancia_km'],

                'distancia_servico_km' => $o['distancia_servico_km'],

                'custo_estimado'       => number_format($o['custo_estimado'], 2, '.', ''),

                'eta_min'              => $o['eta_min'],

                'score'                => $o['score'],

                'segundos_desde_criacao' => $o['segundos_desde_criacao'],

                'expiracao_aceite'     => $o['expiracao_aceite'],

            ];

        }, $ofertas);



        echo json_encode(['ok' => true, 'pedidos' => $resultado, 'localizacao_disponivel' => !$semLocalizacao], JSON_UNESCAPED_UNICODE);

        exit;

    }



    /**

     * Exibe detalhe do pedido para o guincho aceitar

     */

    public function aceitarForm(int $id): void

    {

        AuthService::requireAuth('guincho');

        $pedido = Pedido::buscarPorId($id);



        if (!$pedido || $pedido['status'] !== 'aguardando_guincho') {

            $this->redirect('/guincho/dashboard');

        }



        // Etapa 4 (matching por capacidade) â€” mesma regra do aceite real

        // (PedidoTransitionService::assignInternal): nÃ£o mostra a tela de

        // aceite pra um serviÃ§o novo que este guincho nÃ£o estÃ¡ habilitado

        // a executar. Reboque continua sem essa exigÃªncia.

        $attendanceMode = (string)($pedido['attendance_mode'] ?? 'TOWING');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if ($attendanceMode === 'TOWING') {

            if (!$guincho || (int)($guincho['reboque_aprovado'] ?? 1) !== 1) {

                $this->redirect('/guincho/dashboard');

            }

        } else {

            $serviceTypeId = (int)($pedido['service_type_id'] ?? 0);

            if (!$guincho || $serviceTypeId <= 0 || !ProviderCapability::possuiCapacidadeAprovada((int)$guincho['id'], $serviceTypeId)) {

                $this->redirect('/guincho/dashboard');

            }

        }



        // Etapa 15 â€” na tela de aceite, INELIGIBLE volta pro dashboard;

        // REQUIRES_CONFIRMATION segue para a tela (que mostra os avisos), mas

        // o POST de aceite ainda serÃ¡ barrado pela revalidaÃ§Ã£o transacional.

        $compatAviso = [];

        $serviceTypeIdCmp = (int)($pedido['service_type_id'] ?? 0);

        if ($guincho && $serviceTypeIdCmp > 0) {

            $compat = ProviderVehicleCompatibilityService::evaluate(new CompatibilityRequest(

                $id, (int)$guincho['id'], $serviceTypeIdCmp, CompatibilityRequest::OP_OFFER_CREATION

            ));

            if ($compat->getStatus() === CompatibilityResult::INELIGIBLE) {

                $this->redirect('/guincho/dashboard');

            }

            $compatAviso = $compat->getWarnings();

        }



        require __DIR__ . '/../Views/guincho/pedidoaceitar.php';

    }



    /**

     * POST: guincho aceita o pedido

     * Â§11: SELECT FOR UPDATE previne race condition no aceite simultÃ¢neo

     */

    public function aceitar(int $id): void

    {

        AuthService::requireAuth('guincho');



        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            $this->redirect('/guincho/dashboard');

        }



        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);



        if (!$guincho || !$guincho['aprovado']) {

            $this->redirect('/guincho/dashboard');

        }



        $result = PedidoTransitionService::acceptByGuincho($id, (int)$guincho['id'], (int)$usuario['id']);

        if (!$result->ok) {

            Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'aceite', (string)$result->error, [

                'pedido_id' => $id,

                'guincho_id' => $guincho['id'],

            ]);

            $this->redirect('/guincho/dashboard');

        }



        // Notifica cliente (fora da transaÃ§Ã£o para nÃ£o bloquear SMTP)

        $pedido = Pedido::buscarPorId($id);

        if ($pedido) {

            $cliente        = ['nome' => $pedido['cliente_nome'], 'email' => $pedido['cliente_email']];

            $guinchoUsuario = ['nome' => $usuario['nome'], 'telefone' => $usuario['telefone'], 'placa_guincho' => $guincho['placa_guincho']];

            NotificacaoService::guinchoAceito($pedido, $cliente, $guinchoUsuario);

        }



        $this->redirect("/guincho/atendimento/{$id}");

    }



    /**

     * GET: formulÃ¡rio de ediÃ§Ã£o de perfil

     */

    public function perfilForm(): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) { $this->redirect('/login'); }



        $pdo    = getPDO();

        $stmt   = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");

        $stmt->execute([$usuario['id']]);

        $dadosUsuario = $stmt->fetch(PDO::FETCH_ASSOC);



        $csrfToken = AuthService::gerarCsrfToken();

        $flashMsg  = $_SESSION['_flash'] ?? [];

        unset($_SESSION['_flash']);

        require __DIR__ . '/../Views/guincho/perfil.php';

    }



    public function perfilOperacaoForm(): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) { $this->redirect('/login'); }



        $csrfToken = AuthService::gerarCsrfToken();

        $flashMsg  = $_SESSION['_flash'] ?? [];

        unset($_SESSION['_flash']);

        require __DIR__ . '/../Views/guincho/perfil_operacao.php';

    }



    public function perfilBancarioForm(): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) { $this->redirect('/login'); }



        $csrfToken = AuthService::gerarCsrfToken();

        $flashMsg  = $_SESSION['_flash'] ?? [];

        unset($_SESSION['_flash']);

        require __DIR__ . '/../Views/guincho/perfil_bancario.php';

    }



    /**

     * ROADMAP socorro automotivo Â§3.3: "Quais serviÃ§os vocÃª oferece?" â€”

     * prestador declara capacidades (nascem PENDING, ver ProviderCapability::declarar()).

     */

    public function capacidades(): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) { $this->redirect('/login'); }



        $servicosOcultos = [
            'MECHANICAL_ASSISTANCE', 'ELECTRICAL_DIAGNOSIS', 'JUMP_START',
            'BATTERY_TEST', 'BATTERY_REPLACEMENT', 'TIRE_INFLATION',
            'TIRE_CHANGE', 'AUTOMOTIVE_LOCKSMITH', 'FUEL_DELIVERY',
        ];
        $tiposServico = array_values(array_filter(
            ServiceType::listarAtivos(),
            static fn(array $tipo): bool => !in_array((string)($tipo['code'] ?? ''), $servicosOcultos, true)
        ));

        $minhasCapacidades = ProviderCapability::listarPorPrestador((int)$guincho['id']);

        $capacidadesPorTipo = [];

        foreach ($minhasCapacidades as $c) {

            $capacidadesPorTipo[(int)$c['service_type_id']] = $c;

        }



        $csrfToken = AuthService::gerarCsrfToken();

        $flashMsg  = $_SESSION['_flash'] ?? [];

        unset($_SESSION['_flash']);

        require __DIR__ . '/../Views/guincho/capacidades.php';

    }



    public function capacidadesSalvar(): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            http_response_code(403); exit;

        }



        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) { $this->redirect('/login'); }



        $selecionados = array_map('intval', (array)($_POST['service_type_id'] ?? []));

        $tiposValidos = array_column(ServiceType::listarAtivos(), 'id');



        foreach ($selecionados as $serviceTypeId) {

            if (!in_array($serviceTypeId, $tiposValidos, true)) {

                continue; // nunca aceitar service_type_id vindo de fora sem checar contra o catÃ¡logo ativo

            }

            // Preço não vem do prestador — quem define é o admin (config/tarifas).
            // O raio operacional é único e fica em guincho/perfil, não por serviço.
            ProviderCapability::declarar((int)$guincho['id'], $serviceTypeId);

        }



        // Â§FLASH-FORMATO-01: NÃƒO usar $this->setFlashMessage() (BaseController)

        // aqui â€” ela sobrescreve $_SESSION['_flash'] com um item Ãºnico plano

        // (['message'=>..,'type'=>..]), formato incompatÃ­vel com o resto deste

        // controller (perfil/operaÃ§Ã£o/bancÃ¡rio, linhas ~767/811/844) e com a

        // view capacidades.php, que fazem foreach esperando array de itens.

        // Bug real encontrado via QA (onboarding-stress.spec.ts): a mensagem de

        // sucesso nunca aparecia â€” acesso a offset invÃ¡lido em string.

        $_SESSION['_flash'][] = ['message' => 'Capacidades enviadas para anÃ¡lise. VocÃª serÃ¡ avisado quando forem aprovadas.', 'type' => 'success'];

        $this->redirect('/guincho/capacidades');

    }



    /** Especialista pede para virar guincho (envia dados/documentos de reboque). */

    public function tornarSeGuincho(): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) { $this->redirect('/guincho/dashboard'); }



        $jaEhGuincho = (int)($guincho['reboque_aprovado'] ?? 0) === 1;

        $emAnalise   = !$jaEhGuincho && (int)($guincho['oferece_reboque'] ?? 0) === 1;



        $csrfToken = AuthService::gerarCsrfToken();

        $flash = $this->getFlashMessage();

        require __DIR__ . '/../Views/guincho/tornarse_guincho.php';

    }



    public function tornarSeGuinchoSalvar(): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            http_response_code(403); exit;

        }

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) { $this->redirect('/guincho/dashboard'); }



        $placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($_POST['placa_guincho'] ?? '')));

        $cnh   = preg_replace('/\D/', '', $_POST['cnh_numero'] ?? '');

        $validade = trim($_POST['cnh_validade'] ?? '');

        $capacidade = (float)($_POST['capacidade_ton'] ?? 0);



        $erros = [];

        if (strlen($placa) !== 7) { $erros[] = 'Placa do guincho invÃ¡lida (7 caracteres).'; }

        if (strlen($cnh) < 9) { $erros[] = 'NÃºmero da CNH invÃ¡lido.'; }

        if ($validade === '') { $erros[] = 'Informe a validade da CNH.'; }

        if ($capacidade <= 0) { $erros[] = 'Informe a capacidade em toneladas.'; }

        if ($erros) {

            $this->setFlashMessage(implode(' ', $erros), 'error');

            $this->redirect('/guincho/tornar-se-guincho');

        }



        Guincho::solicitarReboque((int)$guincho['id'], [

            'placa_guincho' => $placa,

            'cidade_placa' => trim((string)($_POST['cidade_placa'] ?? '')),

            'uf_placa' => strtoupper(trim((string)($_POST['uf_placa'] ?? ''))),

            'capacidade_ton' => $capacidade,

            'cnh_numero' => $cnh,

            'cnh_validade' => $validade,

        ]);



        $this->setFlashMessage('SolicitaÃ§Ã£o enviada! Assim que o admin aprovar seus documentos, vocÃª passa a receber chamados de reboque.', 'success');

        $this->redirect('/guincho/tornar-se-guincho');

    }



    /**

     * POST: salva ediÃ§Ã£o de perfil

     */

    public function perfilSalvar(): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            http_response_code(403); exit;

        }



        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pdo     = getPDO();



        try {

            $pdo->beginTransaction();



            // â”€â”€ Dados pessoais (tabela usuarios) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

            $nome     = trim($_POST['nome'] ?? '');

            $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');



            if (mb_strlen($nome) < 3) throw new Exception('Nome deve ter ao menos 3 caracteres.');

            if (strlen($telefone) < 10) throw new Exception('Telefone invÃ¡lido.');



            // Troca de senha (opcional)

            if (!empty($_POST['nova_senha'])) {

                $novaSenha = $_POST['nova_senha'];

                $confirma  = $_POST['confirmar_senha'] ?? '';

                if (strlen($novaSenha) < 8) throw new Exception('Nova senha deve ter ao menos 8 caracteres.');

                if ($novaSenha !== $confirma)  throw new Exception('As senhas nÃ£o conferem.');



                // Verifica senha atual

                $stmtSenha = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");

                $stmtSenha->execute([$usuario['id']]);

                $row = $stmtSenha->fetch(PDO::FETCH_ASSOC);

                if (!password_verify($_POST['senha_atual'] ?? '', $row['senha_hash'] ?? '')) {

                    throw new Exception('Senha atual incorreta.');

                }



                $pdo->prepare("UPDATE usuarios SET nome = ?, telefone = ?, senha_hash = ? WHERE id = ?")

                    ->execute([$nome, $telefone, password_hash($novaSenha, PASSWORD_BCRYPT), $usuario['id']]);

            } else {

                $pdo->prepare("UPDATE usuarios SET nome = ?, telefone = ? WHERE id = ?")

                    ->execute([$nome, $telefone, $usuario['id']]);

            }



            // Atualiza nome na sessÃ£o

            $_SESSION['user']['nome'] = $nome;



            // â”€â”€ Dados do veÃ­culo / operaÃ§Ã£o (tabela guinchos) â”€â”€â”€â”€â”€â”€â”€â”€â”€

            $placa        = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['placa_guincho'] ?? ''));

            $capacidade   = (float)($_POST['capacidade_ton']    ?? 0);

            $raio         = (int)($_POST['raio_cobertura_km']   ?? 20);

            $chavePix     = trim($_POST['chave_pix']            ?? '');

            $chaveTipo    = $_POST['chave_pix_tipo']            ?? 'cpf';

            $cnhNumero    = preg_replace('/\D/', '', $_POST['cnh_numero'] ?? '');

            $cnhValidade  = trim($_POST['cnh_validade']         ?? '');

            $cidadePlaca  = trim($_POST['cidade_placa']         ?? '');

            $ufPlaca      = strtoupper(trim($_POST['uf_placa']  ?? ''));



            if (strlen($placa) < 7) throw new Exception('Placa invÃ¡lida (mÃ­nimo 7 caracteres).');

            if ($capacidade <= 0)   throw new Exception('Capacidade deve ser maior que zero.');

            if (empty($chavePix))   throw new Exception('Chave PIX Ã© obrigatÃ³ria.');



            // Processar foto do caminhÃ£o

            $fotoCaminhao = $guincho['foto_caminhao'];

            $novaFoto = $this->processarUpload('foto_caminhao');

            if ($novaFoto) $fotoCaminhao = $novaFoto;



            // Verifica se placa jÃ¡ pertence a outro guincho

            $dupPlaca = $pdo->prepare("SELECT id FROM guinchos WHERE placa_guincho = ? AND id != ?");

            $dupPlaca->execute([$placa, $guincho['id']]);

            if ($dupPlaca->fetch()) throw new Exception('Placa jÃ¡ cadastrada para outro guincho.');



            $pdo->prepare(

                "UPDATE guinchos SET

                    placa_guincho = ?, capacidade_ton = ?, raio_cobertura_km = ?,

                    chave_pix = ?, chave_pix_tipo = ?,

                    cnh_numero = ?, cnh_validade = ?,

                    lat_operacao = ?, lng_operacao = ?,

                    cidade_placa = ?, uf_placa = ?, foto_caminhao = ?

                 WHERE id = ?"

            )->execute([

                $placa, $capacidade, $raio,

                $chavePix, $chaveTipo,

                $cnhNumero, $cnhValidade,

                (float)($_POST['lat_operacao'] ?? 0),

                (float)($_POST['lng_operacao'] ?? 0),

                $cidadePlaca, $ufPlaca, $fotoCaminhao,

                $guincho['id']

            ]);



            $pdo->commit();

            $_SESSION['_flash'][] = ['message' => 'Perfil atualizado com sucesso!', 'type' => 'success'];

        } catch (Exception $e) {

            $pdo->rollBack();

            $_SESSION['_flash'][] = ['message' => $e->getMessage(), 'type' => 'error'];

        }



        $this->redirect('/guincho/perfil');

    }



    public function perfilOperacaoSalvar(): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            http_response_code(403); exit;

        }



        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pdo     = getPDO();



        try {

            $placa       = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['placa_guincho'] ?? ''));

            $cidadePlaca = trim((string)($_POST['cidade_placa'] ?? ''));

            $ufPlaca     = strtoupper(trim((string)($_POST['uf_placa'] ?? '')));

            $capacidade  = (float)($_POST['capacidade_ton'] ?? 0);

            $raio        = (int)($_POST['raio_cobertura_km'] ?? 20);

            $cnhNumero   = preg_replace('/\D/', '', $_POST['cnh_numero'] ?? '');

            $cnhValidade = trim((string)($_POST['cnh_validade'] ?? ''));

            $latOperacao = (float)($_POST['lat_operacao'] ?? 0);

            $lngOperacao = (float)($_POST['lng_operacao'] ?? 0);



            if (strlen($placa) < 7) throw new Exception('Placa invÃ¡lida (mÃ­nimo 7 caracteres).');

            if ($capacidade <= 0) throw new Exception('Capacidade deve ser maior que zero.');



            $dupPlaca = $pdo->prepare("SELECT id FROM guinchos WHERE placa_guincho = ? AND id != ?");

            $dupPlaca->execute([$placa, $guincho['id']]);

            if ($dupPlaca->fetch()) throw new Exception('Placa jÃ¡ cadastrada para outro guincho.');



            $pdo->prepare(

                "UPDATE guinchos

                 SET placa_guincho = ?, cidade_placa = ?, uf_placa = ?, capacidade_ton = ?, raio_cobertura_km = ?, cnh_numero = ?, cnh_validade = ?, lat_operacao = ?, lng_operacao = ?

                 WHERE id = ?"

            )->execute([$placa, $cidadePlaca, $ufPlaca, $capacidade, $raio, $cnhNumero, $cnhValidade, $latOperacao, $lngOperacao, $guincho['id']]);



            $_SESSION['_flash'][] = ['message' => 'Dados operacionais atualizados com sucesso!', 'type' => 'success'];

        } catch (Exception $e) {

            $_SESSION['_flash'][] = ['message' => $e->getMessage(), 'type' => 'error'];

        }



        $this->redirect('/guincho/operacao');

    }



    public function perfilBancarioSalvar(): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            http_response_code(403); exit;

        }



        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pdo     = getPDO();



        try {

            $chavePix  = trim((string)($_POST['chave_pix'] ?? ''));

            $chaveTipo = (string)($_POST['chave_pix_tipo'] ?? 'cpf');

            if ($chavePix === '') throw new Exception('Chave PIX Ã© obrigatÃ³ria.');



            $tiposPermitidos = ['cpf', 'email', 'telefone', 'aleatoria'];

            if (!in_array($chaveTipo, $tiposPermitidos, true)) {

                $chaveTipo = 'cpf';

            }



            $pdo->prepare(

                "UPDATE guinchos SET chave_pix = ?, chave_pix_tipo = ? WHERE id = ?"

            )->execute([$chavePix, $chaveTipo, $guincho['id']]);



            $_SESSION['_flash'][] = ['message' => 'Dados bancÃ¡rios atualizados com sucesso!', 'type' => 'success'];

        } catch (Exception $e) {

            $_SESSION['_flash'][] = ['message' => $e->getMessage(), 'type' => 'error'];

        }



        $this->redirect('/guincho/bancario');

    }



    /**

     */

    public function recusar(int $id): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            $this->redirect('/guincho/dashboard');

        }

        // NÃ£o altera o pedido â€” apenas redireciona. Outro guincho pode aceitar.

        $this->redirect('/guincho/dashboard');

    }



    /**

     */

    public function atualizarStatus(int $id): void

    {

        AuthService::requireAuth('guincho');

        header('Content-Type: application/json');

        

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            echo json_encode(['ok' => false, 'erro' => 'Token invÃ¡lido']);

            exit;

        }

        

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pedido  = Pedido::buscarPorId($id);

        $pdo     = getPDO();

        

        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            echo json_encode(['ok' => false, 'erro' => 'Acesso negado']);

            exit;

        }

        

        $transicoes = [

            'a_caminho'  => 'no_local',

            'no_local'   => 'em_reboque',

            'em_reboque' => 'concluido',

        ];



        $novoStatus = $transicoes[$pedido['status']] ?? null;

        if (!$novoStatus) {

            echo json_encode(['ok' => false, 'erro' => 'TransiÃ§Ã£o invÃ¡lida']);

            exit;

        }



        require_once __DIR__ . '/../Services/AuditTrailService.php';



        $cfg = Configuracao::getAll();



        $transitionContext = [];

        if ($novoStatus === 'em_reboque') {

            $token = (string)($_POST['evidence_token'] ?? '');

            if ($token === '' || !isset($_FILES['foto_plataforma'])) {

                echo json_encode(['ok' => false, 'erro' => 'Foto de coleta com nonce vÃ¡lido Ã© obrigatÃ³ria.']);

                exit;

            }

            $evidencia = EvidenceService::storeUploadedEvidence($pedido, (int)$guincho['id'], 'coleta', $_FILES['foto_plataforma'], $token);

            $transitionContext['foto_plataforma'] = $evidencia['stored_name'];

            $transitionContext['evidence_id'] = $evidencia['id'];



            AuditTrailService::evento('upload_foto_plataforma', 'GuinchoController', 'atualizarStatus', ['pedido_id' => $id, 'foto' => $evidencia['stored_name'], 'point_id' => $evidencia['point_id']]);

        } elseif ($novoStatus === 'concluido') {

            $token = (string)($_POST['evidence_token'] ?? '');

            if ($token === '' || !isset($_FILES['foto_destino'])) {

                echo json_encode(['ok' => false, 'erro' => 'Foto de entrega com nonce vÃ¡lido Ã© obrigatÃ³ria.']);

                exit;

            }

            $evidencia = EvidenceService::storeUploadedEvidence($pedido, (int)$guincho['id'], 'entrega', $_FILES['foto_destino'], $token);

            $transitionContext['foto_destino'] = $evidencia['stored_name'];

            $transitionContext['evidence_id'] = $evidencia['id'];



            AuditTrailService::evento('upload_foto_destino', 'GuinchoController', 'atualizarStatus', ['pedido_id' => $id, 'foto' => $evidencia['stored_name'], 'point_id' => $evidencia['point_id']]);

        }



        $transition = PedidoTransitionService::transition(new PedidoTransitionRequest(

            'guincho',

            (int)$usuario['id'],

            $id,

            $novoStatus,

            (int)$guincho['id'],

            $transitionContext

        ));

        if (!$transition->ok) {

            // Â§LOG-GC-03 (29/07/2026): esta era a lacuna central do bug

            // "painel do especialista nÃ£o se move" â€” quando o guincheiro

            // clica pra avanÃ§ar (ex.: a_caminho -> no_local) e

            // PedidoTransitionService recusa (geofence, prÃ©-condiÃ§Ã£o, etc.),

            // o motivo sÃ³ aparecia como texto solto na tela por um instante

            // (data.erro) e sumia â€” nada ficava no app-*.jsonl nem em

            // app_logs. Espelha o mesmo padrÃ£o jÃ¡ usado em aceitar() acima.

            Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'transicao', (string)$transition->error, [

                'pedido_id' => $id,

                'guincho_id' => $guincho['id'],

                'status_atual' => $pedido['status'],

                'status_pretendido' => $novoStatus,

            ]);

            echo json_encode(['ok' => false, 'erro' => $transition->error]);

            exit;

        }



        AuditTrailService::evento('status_pedido_atualizado', 'GuinchoController', 'atualizarStatus', ['pedido_id' => $id, 'status' => $novoStatus]);



        // Se concluÃ­do: fecha operacionalmente e enfileira repasse (Â§13)

        if ($novoStatus === 'concluido') {
        $valorTotal   = (float)($pedido['custo_final'] ?: $pedido['custo_estimado']);  // <-- sem indentaÃ§Ã£o
        [$valorGuincho, $valorPlat] = $this->splitRepasseParaConclusao($id, $valorTotal, $cfg);



        // Verifica se Ã© Freeflow para gerar registro financeiro manualmente

        if (($cfg['system_mode'] ?? 'production') === 'freeflow') {

            $pagamentoIdFreeflow = Pagamento::criar($id, 'freeflow', $valorTotal, $valorGuincho, $valorPlat);

            // Marca como aprovado diretamente

            $pdo = getPDO();

            $pdo->prepare("UPDATE pagamentos SET status = 'aprovado' WHERE pedido_id = ?")->execute([$id]);

            // FIX (achado via CarteiraService::checarReconciliacaoGlobal(),

            // tela /admin/carteiras â€” divergÃªncia real entre payout_ledger_entries

            // e pagamentos.valor_guincho): o modo freeflow aprovava o

            // pagamento e preenchia valor_guincho, mas NUNCA gravava o

            // lanÃ§amento 'credito_guincho' no ledger contÃ¡bil (sÃ³

            // PedidoTransitionService::approvePayment fazia isso, no fluxo

            // normal). Sem este lanÃ§amento, a carteira do guincheiro conta

            // esse valor como recebido em pagamentos mas o ledger nunca soube

            // dele. NÃ£o altera nenhum valor jÃ¡ calculado â€” sÃ³ grava o mesmo

            // lanÃ§amento que o fluxo normal jÃ¡ grava.

            if ($pagamentoIdFreeflow) {

                require_once __DIR__ . '/../Services/Payment/PayoutLedgerService.php';

                PayoutLedgerService::registrarSplitAprovado($pdo, (int)$pagamentoIdFreeflow, $id, $valorGuincho, $valorPlat, 'freeflow:' . $id);

                Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'financeiro',

                    "LanÃ§amento de ledger gravado para pagamento freeflow #{$pagamentoIdFreeflow} do pedido #{$id}.",

                    ['pedido_id' => $id, 'pagamento_id' => $pagamentoIdFreeflow, 'valor_guincho' => $valorGuincho, 'valor_plataforma' => $valorPlat]);

            } else {

                Logger::log(Logger::LEVEL_ERROR, __CLASS__, __FUNCTION__, 'financeiro',

                    "Pagamento::criar retornou false pro pedido #{$id} em modo freeflow â€” ledger NÃƒO foi gravado.",

                    ['pedido_id' => $id]);

            }

        } else {

            $enqueue = PaymentJobService::enqueuePixPayout($id, $valorGuincho, $valorPlat);

            if (!$enqueue['ok']) {

                Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'pix_enqueue', (string)$enqueue['erro'], ['pedido_id' => $id]);

                echo json_encode(['ok' => true, 'status' => $novoStatus, 'pix' => 'bloqueado']);

                exit;

            }

        }



            $cliente     = ['nome' => $pedido['cliente_nome'], 'email' => $pedido['cliente_email']];

            $guinchoUser = ['nome' => $usuario['nome'], 'email' => $usuario['email']];

            $pedido['custo_final'] = $valorTotal;

            NotificacaoService::pedidoConcluido($pedido, $cliente, $guinchoUser, $valorGuincho);

        }

        

        echo json_encode(['ok' => true, 'status' => $novoStatus, 'pix' => $novoStatus === 'concluido' ? 'enfileirado' : null]);

        exit;

    }



    /**

     * FIX CRÃTICO (achado via CarteiraService::checarReconciliacaoGlobal(),

     * tela /admin/carteiras â€” divergÃªncia real de R$ ~2.410 entre o ledger

     * contÃ¡bil e a soma de pagamentos.valor_guincho): antes deste fix, os

     * dois pontos de conclusÃ£o de atendimento (reboque em atualizarStatus()

     * e Proof-of-Service resolvido) RECALCULAVAM comissÃ£o/repasse do zero

     * com uma fÃ³rmula diferente da usada na aprovaÃ§Ã£o do pagamento

     * (PedidoTransitionService::approvePayment, Â§SPLIT-LIQUIDO-01 â€” desconta

     * reserva_gateway_percentual ANTES de aplicar a comissÃ£o). A fÃ³rmula

     * antiga aqui nÃ£o descontava a reserva, entÃ£o o valor_guincho recalculado

     * era MAIOR que o valor jÃ¡ aprovado e jÃ¡ registrado no ledger â€” e esse

     * valor maior sobrescrevia pagamentos.valor_guincho (via

     * Pagamento::prepararRepassePix) e era o que efetivamente saÃ­a via Pix

     * real. Resultado: o guincheiro recebia a mais do que o ledger sabia, a

     * cada pedido de reboque concluÃ­do â€” a reserva de gateway (4,5% do

     * valor bruto) estava sendo paga ao guincheiro em vez de retida pela

     * plataforma. Com comissao_plataforma=0.08 e reserva_gateway_percentual

     * =0.045 (valores reais configurados neste ambiente), isso Ã© R$ 3,60 de

     * diferenÃ§a a cada R$ 100 de pedido â€” dinheiro real, nÃ£o sÃ³ contÃ¡bil.

     *

     * CorreÃ§Ã£o: NUNCA recalcular na conclusÃ£o. Reaproveita o split jÃ¡

     * calculado e jÃ¡ auditado no ledger na aprovaÃ§Ã£o do pagamento

     * (Pagamento::buscarPorPedido â€” fonte de verdade Ãºnica). SÃ³ cai no

     * fallback (recalcular) quando nÃ£o hÃ¡ pagamento aprovado prÃ©vio

     * compatÃ­vel com o valor total atual (ex.: freeflow puro, que nunca

     * passou por approvePayment; ou uma inconsistÃªncia real de dados) â€” e

     * nesse caso usa a MESMA fÃ³rmula lÃ­quida da aprovaÃ§Ã£o, nunca a antiga.

     *

     * @return array{0:float,1:float} [valorGuincho, valorPlataforma]

     */

    private function splitRepasseParaConclusao(int $pedidoId, float $valorTotal, array $cfg): array

    {

        $pagamentoAprovado = Pagamento::buscarPorPedido($pedidoId);

        $temPagamentoCompativel = $pagamentoAprovado

            && (string)($pagamentoAprovado['status'] ?? '') === 'aprovado'

            && abs((float)($pagamentoAprovado['valor_total'] ?? 0) - $valorTotal) <= 0.02;



        if ($temPagamentoCompativel) {

            $valorGuincho = round((float)$pagamentoAprovado['valor_guincho'], 2);

            $valorPlataforma = round((float)$pagamentoAprovado['valor_plataforma'], 2);

            Logger::log(Logger::LEVEL_DEBUG, __CLASS__, __FUNCTION__, 'financeiro',

                "Reaproveitando split jÃ¡ aprovado do pagamento #{$pagamentoAprovado['id']} pro pedido #{$pedidoId} â€” guincho=R$ {$valorGuincho}, plataforma=R$ {$valorPlataforma}.",

                ['pedido_id' => $pedidoId, 'pagamento_id' => $pagamentoAprovado['id'], 'valor_total' => $valorTotal, 'valor_guincho' => $valorGuincho, 'valor_plataforma' => $valorPlataforma]);

            return [$valorGuincho, $valorPlataforma];

        }



        // Fallback: sem pagamento aprovado compatÃ­vel â€” recalcula com a

        // MESMA fÃ³rmula lÃ­quida da aprovaÃ§Ã£o (nunca a fÃ³rmula antiga

        // divergente). Loga em WARN porque este caminho nÃ£o deveria ser

        // comum em produÃ§Ã£o (sÃ³ freeflow sem histÃ³rico de aprovaÃ§Ã£o prÃ©via).

        $rawComissao = isset($cfg['comissao_plataforma']) ? (float)$cfg['comissao_plataforma'] : 0.08;

        $comissao = $rawComissao > 1 ? $rawComissao / 100 : $rawComissao;

        $rawReservaGateway = isset($cfg['reserva_gateway_percentual']) ? (float)$cfg['reserva_gateway_percentual'] : 0.045;

        $reservaGateway = $rawReservaGateway > 1 ? $rawReservaGateway / 100 : $rawReservaGateway;

        $liquidoPosGateway = round($valorTotal * (1 - $reservaGateway), 2);

        $valorPlataforma = round($liquidoPosGateway * $comissao, 2);

        $valorGuincho = round($liquidoPosGateway - $valorPlataforma, 2);



        Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'financeiro',

            "Nenhum pagamento aprovado compatÃ­vel encontrado pro pedido #{$pedidoId} na conclusÃ£o â€” recalculando com a fÃ³rmula lÃ­quida (mesma da aprovaÃ§Ã£o). " .

            ($pagamentoAprovado ? "Pagamento #{$pagamentoAprovado['id']} encontrado mas status='" . ($pagamentoAprovado['status'] ?? '?') . "' ou valor_total divergente (R$ " . ($pagamentoAprovado['valor_total'] ?? '?') . " vs R$ {$valorTotal})." : 'Nenhum pagamento encontrado para o pedido.'),

            ['pedido_id' => $pedidoId, 'valor_total' => $valorTotal, 'valor_guincho' => $valorGuincho, 'valor_plataforma' => $valorPlataforma, 'pagamento_encontrado' => $pagamentoAprovado ? $pagamentoAprovado['id'] : null]);



        return [$valorGuincho, $valorPlataforma];

    }



    /**

     * Upload de arquivo genÃ©rico

     */

    private function processarUpload(string $campo): ?string

    {

        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) return null;

        

        $file = $_FILES[$campo];

        // Valida tamanho (mÃ¡x 5MB)

        if ($file['size'] > 5 * 1024 * 1024) return null;

        

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Valida extensÃ£o

        if (!in_array($ext, ['jpg','jpeg','png'])) return null;

        

        $destDir = UPLOAD_PATH;

        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

        

        $fileName = uniqid($campo . '_') . '.' . $ext;

        $destPath = $destDir . '/' . $fileName;

        

        if (move_uploaded_file($file['tmp_name'], $destPath)) {

            return $fileName;

        }

        return null;

    }



    /**

     * Tela de atendimento em andamento

     */

    public function atendimento(int $id): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pedido  = Pedido::buscarPorId($id);

        $pdo     = getPDO();

        

        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            $this->redirect('/guincho/dashboard');

        }

        

        $mensagens = Chat::listarPorPedido($id);

        $csrfToken = AuthService::gerarCsrfToken();

        $flash = $this->getFlashMessage();

        $porSnapshot = ProofOfRoadService::getCurrentSnapshot($id);

        $evidenceToken = null;

        if ($pedido['status'] === 'no_local' || $pedido['status'] === 'preparacao_veiculo') {

            try { $evidenceToken = EvidenceService::issueNonce($pedido, 'coleta'); } catch (Throwable) { $evidenceToken = null; }

        } elseif ($pedido['status'] === 'diagnostico_iniciado') {

            try { $evidenceToken = EvidenceService::issueNonce($pedido, 'diagnostico'); } catch (Throwable) { $evidenceToken = null; }

        } elseif ($pedido['status'] === 'em_reboque' || $pedido['status'] === 'teste_final') {

            try { $evidenceToken = EvidenceService::issueNonce($pedido, 'entrega'); } catch (Throwable) { $evidenceToken = null; }

        }



        // Etapa 5 â€” painel de diagnÃ³stico/orÃ§amento sÃ³ faz sentido para

        // serviÃ§os que nÃ£o sÃ£o reboque (OnSiteFlowDefinition). Reboque

        // (TOWING) segue 100% pelo botÃ£o Ãºnico de atualizarStatus(), sem

        // nada disso â€” atendimento.php decide o que exibir com base nesta flag.

        $attendanceMode = (string)($pedido['attendance_mode'] ?? 'TOWING');

        $ehServicoLocal = $attendanceMode !== 'TOWING';

        $diagnostico = $ehServicoLocal ? PedidoDiagnostico::buscarPorPedido($id) : null;

        $orcamento = $ehServicoLocal ? PedidoOrcamento::buscarPorPedido($id) : null;



        // Â§COBERTURA-RAIO-01 (06/08/2026): estoque do prÃ³prio prestador, pra

        // o formulÃ¡rio de orÃ§amento complementar deixar o guincheiro vincular

        // um item a um produto real (produto_id) em vez de sÃ³ texto livre â€”

        // sem isso, EstoqueService::baixarPorPedido() nunca tem o que baixar

        // (ver DiagnosticoService::decidirOrcamento()). Vazio/erro nÃ£o quebra

        // a tela â€” item sem produto_id continua funcionando como antes (mÃ£o

        // de obra/serviÃ§o que nÃ£o consome estoque fÃ­sico).

        $estoquePrestador = [];

        if ($ehServicoLocal && $guincho) {

            require_once __DIR__ . '/../Models/ProviderProdutoEstoque.php';

            try {

                $estoquePrestador = ProviderProdutoEstoque::listarPorPrestador((int)$guincho['id']);

            } catch (Throwable) {

                $estoquePrestador = [];

            }

        }



        require __DIR__ . '/../Views/guincho/atendimento.php';

    }



    /**

     * Etapa 5 â€” no_local â†’ diagnostico_iniciado. BotÃ£o "Iniciar diagnÃ³stico"

     * na tela de atendimento, sÃ³ visÃ­vel/aceito para serviÃ§os nÃ£o-reboque.

     *

     * Etapa 6 (Proof-of-Service) â€” exige a foto de "antes" aqui, mesmo

     * padrÃ£o de nonce+geofence jÃ¡ usado no `no_local â†’ em_reboque` do

     * reboque comum (tipo 'coleta', reaproveitado). Sem isso o checklist

     * de Proof-of-Service nunca fecharia como completo para nenhum serviÃ§o

     * novo, jÃ¡ que `requires_before_evidence` nasce `true` por padrÃ£o.

     */

    public function diagnosticoIniciar(int $id): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            $this->redirect("/guincho/atendimento/{$id}");

        }

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pedido = Pedido::buscarPorId($id);

        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            $this->redirect('/guincho/dashboard');

        }



        $token = (string)($_POST['evidence_token'] ?? '');

        if ($token === '' || !isset($_FILES['foto_chegada'])) {

            $this->setFlashMessage('Foto de chegada com nonce vÃ¡lido Ã© obrigatÃ³ria.', 'error');

            $this->redirect("/guincho/atendimento/{$id}");

        }

        EvidenceService::storeUploadedEvidence($pedido, (int)$guincho['id'], 'coleta', $_FILES['foto_chegada'], $token);



        $result = DiagnosticoService::iniciarDiagnostico($id, (int)$guincho['id'], (int)$usuario['id']);

        if (!$result->ok) {

            $this->setFlashMessage((string)$result->error, 'error');

        }

        $this->redirect("/guincho/atendimento/{$id}");

    }



    /**

     * Etapa 5 â€” diagnostico_iniciado â†’ diagnostico_concluido â†’ (em_execucao_servico

     * | autorizacao_servico_pendente | conversao_reboque_pendente), conforme o

     * resultado escolhido. Itens de orÃ§amento chegam como arrays paralelos

     * (item_descricao[]/item_valor[]) â€” formulÃ¡rio simples, sem JS de tabela dinÃ¢mica.

     */

    public function diagnosticoConcluir(int $id): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            $this->redirect("/guincho/atendimento/{$id}");

        }

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);



        $resultado = (string)($_POST['resultado'] ?? '');

        $descricao = trim((string)($_POST['descricao'] ?? '')) ?: null;



        // O parecer precisa ser verificÃ¡vel: foto do veÃ­culo/defeito no local,

        // vinculada a nonce, GPS, horÃ¡rio, hash e identidade do prestador.

        // Isso evita orÃ§amento apenas verbal e cria trilha de auditoria para

        // aceite, contestaÃ§Ã£o e conciliaÃ§Ã£o posterior.

        $pedido = Pedido::buscarPorId($id);

        $evidenceToken = (string)($_POST['evidence_token'] ?? '');

        if (!$pedido || $evidenceToken === '' || !isset($_FILES['foto_parecer'])) {

            $this->setFlashMessage('Foto do parecer/orÃ§amento com nonce vÃ¡lido Ã© obrigatÃ³ria.', 'error');

            $this->redirect("/guincho/atendimento/{$id}");

        }

        try {

            EvidenceService::storeUploadedEvidence($pedido, (int)$guincho['id'], 'diagnostico', $_FILES['foto_parecer'], $evidenceToken);

        } catch (Throwable $e) {

            $this->setFlashMessage('NÃ£o foi possÃ­vel validar a foto do parecer: ' . $e->getMessage(), 'error');

            $this->redirect("/guincho/atendimento/{$id}");

        }



        // Â§COBERTURA-RAIO-01 (06/08/2026): item_produto_id[]/item_quantidade[]

        // sÃ£o novos e OPCIONAIS â€” item sem produto selecionado continua

        // funcionando exatamente como antes (mÃ£o de obra/serviÃ§o, nÃ£o baixa

        // estoque). Quando produto_id vem preenchido, Ã© isso que permite

        // DiagnosticoService::decidirOrcamento() chamar

        // EstoqueService::baixarPorPedido() na aprovaÃ§Ã£o (antes desta

        // mudanÃ§a, produto_id/quantidade nem chegavam a existir em lugar

        // nenhum do formulÃ¡rio â€” a integraÃ§Ã£o era estruturalmente impossÃ­vel).

        $itens = [];

        $descricoes = $_POST['item_descricao'] ?? [];

        $valores = $_POST['item_valor'] ?? [];

        $produtoIds = $_POST['item_produto_id'] ?? [];

        $quantidades = $_POST['item_quantidade'] ?? [];

        foreach ($descricoes as $i => $desc) {

            $desc = trim((string)$desc);

            $valor = (float)str_replace(',', '.', (string)($valores[$i] ?? '0'));

            if ($desc !== '' && $valor > 0) {

                $produtoId = (int)($produtoIds[$i] ?? 0);

                $item = ['descricao' => $desc, 'valor' => $valor];

                if ($produtoId > 0) {

                    $item['produto_id'] = $produtoId;

                    $item['quantidade'] = max(1, (int)($quantidades[$i] ?? 1));

                }

                $itens[] = $item;

            }

        }



        $result = DiagnosticoService::concluirDiagnostico($id, (int)$guincho['id'], (int)$usuario['id'], $resultado, $descricao, $itens);

        if (!$result->ok) {

            $this->setFlashMessage((string)$result->error, 'error');

        }

        $this->redirect("/guincho/atendimento/{$id}");

    }



    /**

     * Etapa 7 â€” preparacao_veiculo â†’ em_reboque. SÃ³ existe quando a

     * conversÃ£o colocou o MESMO prestador (hÃ­brido) para tocar o reboque â€”

     * reaproveita a mesma validaÃ§Ã£o de geofence/evidÃªncia 'coleta' que o

     * fluxo de reboque original jÃ¡ exige em no_local â†’ em_reboque

     * (PedidoTransitionService::validatePreconditions nÃ£o distingue como

     * chegou atÃ© aqui).

     */

    public function preparacaoConcluir(int $id): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            $this->redirect("/guincho/atendimento/{$id}");

        }

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pedido = Pedido::buscarPorId($id);

        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            $this->redirect('/guincho/dashboard');

        }



        $token = (string)($_POST['evidence_token'] ?? '');

        if ($token === '' || !isset($_FILES['foto_plataforma'])) {

            $this->setFlashMessage('Foto de coleta com nonce vÃ¡lido Ã© obrigatÃ³ria.', 'error');

            $this->redirect("/guincho/atendimento/{$id}");

        }

        $evidencia = EvidenceService::storeUploadedEvidence($pedido, (int)$guincho['id'], 'coleta', $_FILES['foto_plataforma'], $token);



        $result = PedidoTransitionService::transition(new PedidoTransitionRequest(

            'guincho', (int)$usuario['id'], $id, 'em_reboque', (int)$guincho['id'],

            ['foto_plataforma' => $evidencia['stored_name'], 'evidence_id' => $evidencia['id']]

        ));

        if (!$result->ok) {

            $this->setFlashMessage((string)$result->error, 'error');

        }

        $this->redirect("/guincho/atendimento/{$id}");

    }



    /** Etapa 5 â€” em_execucao_servico â†’ teste_final. */

    public function execucaoConcluir(int $id): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            $this->redirect("/guincho/atendimento/{$id}");

        }

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);



        $result = DiagnosticoService::concluirExecucao($id, (int)$guincho['id'], (int)$usuario['id']);

        if (!$result->ok) {

            $this->setFlashMessage((string)$result->error, 'error');

        }

        $this->redirect("/guincho/atendimento/{$id}");

    }



    /**

     * Etapa 5 â€” teste_final â†’ concluido (resolvido, com evidÃªncia â€” mesmo

     * padrÃ£o de nonce+foto jÃ¡ usado em atualizarStatus() para 'concluido')

     * ou â†’ conversao_reboque_pendente (nÃ£o resolveu, Etapa 7 assume dali).

     */

    public function testeFinalConcluir(int $id): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            $this->redirect("/guincho/atendimento/{$id}");

        }

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pedido = Pedido::buscarPorId($id);

        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            $this->redirect('/guincho/dashboard');

        }



        $resolvido = !empty($_POST['resolvido']);

        $context = [];



        if ($resolvido) {

            $token = (string)($_POST['evidence_token'] ?? '');

            if ($token === '' || !isset($_FILES['foto_destino'])) {

                $this->setFlashMessage('Foto de conclusÃ£o com nonce vÃ¡lido Ã© obrigatÃ³ria.', 'error');

                $this->redirect("/guincho/atendimento/{$id}");

            }

            $evidencia = EvidenceService::storeUploadedEvidence($pedido, (int)$guincho['id'], 'entrega', $_FILES['foto_destino'], $token);

            $context['foto_destino'] = $evidencia['stored_name'];

            $context['evidence_id'] = $evidencia['id'];

        }



        $result = DiagnosticoService::confirmarResultadoFinal($id, (int)$guincho['id'], (int)$usuario['id'], $resolvido, $context);

        if (!$result->ok) {

            $this->setFlashMessage((string)$result->error, 'error');

            $this->redirect("/guincho/atendimento/{$id}");

        }



        // Etapa 6 (Proof-of-Service) â€” fecha o checklist estruturado sÃ³

        // quando o serviÃ§o de fato terminou resolvido no local. Se virou

        // reboque (conversao_reboque_pendente), o Proof-of-Service desse

        // atendimento fica em aberto de propÃ³sito â€” a "prova" de reboque Ã©

        // outra (POR/geofence), jÃ¡ existente, tratada em outro lugar.

        if ($resolvido) {

            ProofOfServiceService::avaliarEFechar($id, (int)$guincho['id']);

        }



        // Reaproveita o mesmo fecho financeiro/notificaÃ§Ã£o do fluxo de

        // reboque quando o serviÃ§o local termina resolvido (mesma regra de

        // comissÃ£o/Pix â€” Etapa 11/order_charge_items ainda nÃ£o substitui isso).

        if ($resolvido) {

            $cfg = Configuracao::getAll();

            $valorTotal = (float)($pedido['custo_final'] ?: $pedido['custo_estimado']);

            [$valorGuincho, $valorPlat] = $this->splitRepasseParaConclusao($id, $valorTotal, $cfg);



            if (($cfg['system_mode'] ?? 'production') === 'freeflow') {

                $pagamentoIdFreeflow = Pagamento::criar($id, 'freeflow', $valorTotal, $valorGuincho, $valorPlat);

                $pdoFreeflow = getPDO();

                $pdoFreeflow->prepare("UPDATE pagamentos SET status = 'aprovado' WHERE pedido_id = ?")->execute([$id]);

                // Mesmo fix do outro branch freeflow (GuinchoController::atualizarStatus)

                // â€” achado via CarteiraService::checarReconciliacaoGlobal() em

                // /admin/carteiras: freeflow nunca gravava o lanÃ§amento contÃ¡bil.

                if ($pagamentoIdFreeflow) {

                    require_once __DIR__ . '/../Services/Payment/PayoutLedgerService.php';

                    PayoutLedgerService::registrarSplitAprovado($pdoFreeflow, (int)$pagamentoIdFreeflow, $id, $valorGuincho, $valorPlat, 'freeflow:' . $id);

                    Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'financeiro',

                        "LanÃ§amento de ledger gravado para pagamento freeflow #{$pagamentoIdFreeflow} do pedido #{$id} (Proof-of-Service).",

                        ['pedido_id' => $id, 'pagamento_id' => $pagamentoIdFreeflow, 'valor_guincho' => $valorGuincho, 'valor_plataforma' => $valorPlat]);

                } else {

                    Logger::log(Logger::LEVEL_ERROR, __CLASS__, __FUNCTION__, 'financeiro',

                        "Pagamento::criar retornou false pro pedido #{$id} em modo freeflow (Proof-of-Service) â€” ledger NÃƒO foi gravado.",

                        ['pedido_id' => $id]);

                }

            } else {

                $enqueue = PaymentJobService::enqueuePixPayout($id, $valorGuincho, $valorPlat);

                if (!$enqueue['ok']) {

                    Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'pix_enqueue', (string)$enqueue['erro'], ['pedido_id' => $id]);

                }

            }

        }



        $this->redirect("/guincho/atendimento/{$id}");

    }



    /**

     * AJAX: reemite o nonce de evidÃªncia com o ÃšLTIMO ponto GPS vÃ¡lido no

     * momento da chamada.

     *

     * Bug real corrigido aqui: o nonce embutido no HTML de atendimento.php Ã©

     * gerado uma Ãºnica vez, no carregamento da pÃ¡gina (ver atendimento()

     * acima). Um guincheiro real carrega a tela de "em_reboque" logo ao

     * coletar o veÃ­culo (ainda perto da origem), dirige atÃ© o destino sem

     * recarregar a pÃ¡gina, e sÃ³ entÃ£o tira a foto de entrega â€” nesse momento

     * o nonce antigo ainda aponta pro ponto GPS da ORIGEM, entÃ£o

     * EvidenceService::storeUploadedEvidence rejeita por geofence

     * ("EvidÃªncia rejeitada fora da geofence permitida"), e como essa

     * exception nÃ£o Ã© capturada localmente, vira um 500 genÃ©rico via

     * set_exception_handler â€” bloqueando a conclusÃ£o de QUALQUER atendimento

     * com distÃ¢ncia real entre origem e destino. Este endpoint permite ao

     * front pedir um nonce atualizado, vinculado ao ponto GPS mais recente,

     * imediatamente antes do envio da foto (ver atendimento.php JS).

     */

    public function evidenciaNonce(int $id): void

    {

        AuthService::requireAuth('guincho');

        header('Content-Type: application/json');



        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pedido  = Pedido::buscarPorId($id);



        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            echo json_encode(['ok' => false, 'erro' => 'Acesso negado']);

            exit;

        }



        $tipo = match ($pedido['status']) {

            'no_local' => 'coleta',

            'em_reboque', 'teste_final' => 'entrega',

            default => null,

        };



        if ($pedido['status'] === 'diagnostico_iniciado') {

            $tipo = 'diagnostico';

        } elseif ($tipo === null) {

            echo json_encode(['ok' => false, 'erro' => 'Nenhuma evidÃªncia esperada para o status atual.']);

            exit;

        }



        try {

            $nonce = EvidenceService::issueNonce($pedido, $tipo);

            echo json_encode(['ok' => true, 'evidence_token' => $nonce['token'], 'point_id' => $nonce['point_id']]);

        } catch (Throwable $e) {

            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);

        }

        exit;

    }



    /**

     * AJAX: envia mensagem no chat

     */

    public function chatEnviar(int $pedidoId = 0): void

    {

        AuthService::requireAuth('guincho');

        header('Content-Type: application/json');

        

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            // L1.9 #47: mesmo tratamento do ClienteController â€” 401 para

            // acionar apiFetch/SessionManager.handleUnauthorized() no front,

            // em vez de um 200 silencioso.

            http_response_code(401);

            echo json_encode(['ok' => false, 'erro' => 'SessÃ£o expirada. FaÃ§a login novamente.']);

            exit;

        }



        if ($pedidoId <= 0) {

            $pedidoId = (int)($_POST['pedido_id'] ?? 0);

        }

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario((int)$usuario['id']);

        $pedido = Pedido::buscarPorId($pedidoId);

        $mensagem = trim($_POST['mensagem'] ?? '');



        if (!$guincho || !$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            echo json_encode(['ok' => false, 'erro' => 'Mensagem invÃ¡lida']);

            exit;

        }



        require_once __DIR__ . '/../Services/ChatService.php';

        $resultado = (new ChatService())->sendMessage([

            'pedido_id' => $pedidoId,

            'usuario_id' => $usuario['id'],

            'mensagem' => $mensagem,

            'idempotency_key' => $_POST['idempotency_key'] ?? null,

        ]);

        echo json_encode(['ok' => $resultado['ok'], 'erro' => $resultado['erro'], 'id' => $resultado['id']]);

        exit;

    }



    /**

     * AJAX: busca mensagens novas do chat

     */

    public function chatMensagens(int $pedidoId): void

    {

        AuthService::requireAuth('guincho', false);

        header('Content-Type: application/json');

        

        $usuario  = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario((int)$usuario['id']);

        $pedido = Pedido::buscarPorId($pedidoId);

        if (!$guincho || !$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            echo json_encode(['ok' => false, 'erro' => 'Acesso negado']);

            exit;

        }

        $desdeId  = (int)($_GET['desde_id'] ?? 0);

        require_once __DIR__ . '/../Services/ChatService.php';

        $chatService = new ChatService();

        $mensagens = $chatService->getMessagesByPedido($pedidoId, $desdeId);



        $chatService->marcarLidas($pedidoId, $usuario['id']);

        echo json_encode(['ok' => true, 'mensagens' => $mensagens]);

        exit;

    }



    public function pedidoStatusJson(int $id): void

    {

        AuthService::requireAuth('guincho', false);

        header('Content-Type: application/json; charset=UTF-8');

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');



        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario((int)$usuario['id']);

        $pedido = Pedido::buscarPorId($id);

        if (!$guincho || !$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            http_response_code(403);

            echo json_encode(['ok' => false, 'erro' => 'acesso_negado'], JSON_UNESCAPED_UNICODE);

            exit;

        }



        echo json_encode([

            'ok' => true,

            'pedido_id' => (int)$pedido['id'],

            'status' => (string)$pedido['status'],

            'status_label' => $this->statusLabel((string)$pedido['status']),

            'tem_chat_novo' => Chat::contarNaoLidas($id, (int)$usuario['id']) > 0,

            'cliente_nome' => $pedido['cliente_nome'] ?? null,

            'pago_guincho' => ($pedido['status'] ?? '') === 'concluido',

            'cancelado_por' => $pedido['cancelado_por'] ?? null,

            'taxa_cancelamento' => (float)($pedido['taxa_cancelamento'] ?? 0),

            'motivo_cancelamento' => $pedido['motivo_cancelamento'] ?? null,

            'guincho_ainda_atribuido' => (int)($pedido['guincho_id'] ?? 0) === (int)$guincho['id'],

            'por_snapshot' => ProofOfRoadService::getCurrentSnapshot($id),

        ], JSON_UNESCAPED_UNICODE);

        exit;

    }



    public function cancelarAtendimento(int $id): void

    {

        AuthService::requireAuth('guincho');

        header('Content-Type: application/json; charset=UTF-8');

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');



        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {

            http_response_code(403);

            echo json_encode(['ok' => false, 'erro' => 'Token invÃ¡lido.', 'penalidade' => 0], JSON_UNESCAPED_UNICODE);

            exit;

        }



        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario((int)$usuario['id']);

        $motivo = trim((string)($_POST['motivo'] ?? ''));

        if (!$guincho || $motivo === '') {

            http_response_code(422);

            echo json_encode(['ok' => false, 'erro' => 'Informe o motivo do cancelamento.', 'penalidade' => 0], JSON_UNESCAPED_UNICODE);

            exit;

        }



        require_once __DIR__ . '/../Services/CancelamentoService.php';

        try {

            $resultado = CancelamentoService::cancelarPorGuincho($id, (int)$guincho['id'], $motivo);

            http_response_code($resultado['ok'] ? 200 : 409);

            echo json_encode([

                'ok' => (bool)$resultado['ok'],

                'erro' => $resultado['erro'],

                'penalidade' => (float)($resultado['penalidade_reputacao'] ?? 0),

                'pedido_reaberto' => (bool)($resultado['ok'] ?? false),

            ], JSON_UNESCAPED_UNICODE);

        } catch (Throwable $e) {

            Logger::exception(__CLASS__, __FUNCTION__, 'cancelamento_guincho', $e, [

                'pedido_id' => $id,

                'guincho_id' => $guincho['id'],

                'fase' => 'cancelamento_constitucional',

            ]);

            http_response_code(500);

            echo json_encode(['ok' => false, 'erro' => 'Erro interno ao cancelar.', 'penalidade' => 0], JSON_UNESCAPED_UNICODE);

        }

        exit;

    }





    private function statusLabel(string $status): string

    {

        $labels = [

            'aguardando_guincho' => 'Aguardando guincho',

            'a_caminho' => 'Guincho a caminho',

            'no_local' => 'Guincho no local',

            'em_reboque' => 'Em reboque',

            'concluido' => 'Concluido',

            'cancelado' => 'Cancelado',

        ];

        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));

    }



    /**

     * HistÃ³rico de atendimentos do guincho

     */

    public function historico(): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $pagina  = max(1, (int)($_GET['pagina'] ?? 1));

        $pedidos = Pedido::listarPorGuincho($guincho['id'], $pagina);

        $total   = Pedido::contarPorGuincho($guincho['id']);

        require __DIR__ . '/../Views/guincho/historico.php';

    }



    /**

     * Extrato financeiro do guincho

     */

    public function financeiro(): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        

        $mes  = (int)($_GET['mes'] ?? date('m'));

        $ano  = (int)($_GET['ano'] ?? date('Y'));

        

        $pagamentos = Pagamento::extratoGuincho((int)$guincho['id'], $mes, $ano);

        $totais     = Pagamento::totaisExtratoGuincho((int)$guincho['id'], $mes, $ano);



        // LÃª comissÃ£o real do banco em vez de hardcodar 15%

        $cfg             = Configuracao::getAll();

        $comissaoPercent = (float)($cfg['comissao_plataforma'] ?? 0.08) * 100; // ex: 0.08 â†’ 8

        $systemMode      = (string)($cfg['system_mode'] ?? 'production');



        require __DIR__ . '/../Views/guincho/financeiro.php';

    }



    /**

     * Â§RECIBO-PIX-01: recibo/comprovante do repasse Pix de um atendimento

     * concluÃ­do, no formato de fatura simples â€” pro guincheiro usar como

     * documento de apoio na prÃ³pria contabilidade (nÃ£o Ã© nota fiscal; o

     * texto da view deixa isso explÃ­cito). SÃ³ Ã© acessÃ­vel depois que o

     * repasse realmente caiu (pago_guincho = 1) e sÃ³ pelo prÃ³prio guincho

     * dono do pedido.

     */

    public function recibo(int $id): void

    {

        AuthService::requireAuth('guincho');

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        $guinchoCompleto = Guincho::buscarPorId((int)$guincho['id']);



        $pedido = Pedido::buscarPorId($id);

        if (!$pedido || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {

            $this->redirect('/guincho/financeiro');

        }



        $pagamento = Pagamento::buscarPorPedido($id);

        if (!$pagamento || empty($pagamento['pago_guincho'])) {

            $this->redirect('/guincho/financeiro');

        }



        require __DIR__ . '/../Views/guincho/recibo.php';

    }



    // â”€â”€â”€ Etapa 8 â€” meu estoque (produtos por prestador) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€



    public function estoque(): void

    {

        AuthService::requireAuth('guincho');

        // A tela de estoque foi retirada do painel do guincho; os dados
        // operacionais agora ficam concentrados no perfil.
        $this->redirect('/guincho/perfil');
        return;

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) {

            $this->redirect('/guincho/dashboard');

        }



        $meuEstoque = ProviderProdutoEstoque::listarPorPrestador((int)$guincho['id']);

        // Produtos ativos ainda nÃ£o presentes no meu estoque (para adicionar).

        $jaTenho = array_column($meuEstoque, 'produto_id');

        $disponiveis = array_filter(Produto::listarTodos(), static function ($p) use ($jaTenho) {

            return (int)$p['active'] === 1 && !in_array((int)$p['id'], array_map('intval', $jaTenho), true);

        });



        $csrfToken = AuthService::gerarCsrfToken();

        $flash = $this->getFlashMessage();

        require __DIR__ . '/../Views/guincho/estoque.php';

    }



    public function estoqueSalvar(): void

    {

        AuthService::requireAuth('guincho');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {

            http_response_code(403); exit;

        }

        $usuario = AuthService::getCurrentUser();

        $guincho = Guincho::buscarPorUsuario($usuario['id']);

        if (!$guincho) {

            $this->redirect('/guincho/dashboard');

        }



        $produtoId = (int)($_POST['produto_id'] ?? 0);

        $quantidade = max(0, (int)($_POST['quantidade'] ?? 0));

        $precoRaw = trim((string)($_POST['preco_venda'] ?? ''));

        $precoVenda = $precoRaw !== '' ? (float)str_replace(',', '.', $precoRaw) : null;



        $produto = Produto::buscarPorId($produtoId);

        if (!$produto) {

            $this->setFlashMessage('Produto invÃ¡lido.', 'error');

            $this->redirect('/guincho/estoque');

        }



        ProviderProdutoEstoque::definir((int)$guincho['id'], $produtoId, $quantidade, $precoVenda);

        $this->setFlashMessage('Estoque atualizado.', 'success');

        $this->redirect('/guincho/estoque');

    }

}

