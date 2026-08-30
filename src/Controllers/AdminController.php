<?php
// File: guinchafacil/src/Controllers/AdminController.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Guincho.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Veiculo.php';
require_once __DIR__ . '/../Models/Oficina.php';
require_once __DIR__ . '/../Models/Avaliacao.php';
require_once __DIR__ . '/../Models/Pagamento.php';
require_once __DIR__ . '/../Models/Catalog/ProviderCapability.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Models/Cidade.php';
require_once __DIR__ . '/../Models/Chat.php';
require_once __DIR__ . '/../Models/PedidoLocalizacao.php';
require_once __DIR__ . '/../Services/Logger.php';
require_once __DIR__ . '/../Services/PixService.php';
require_once __DIR__ . '/../Services/PedidoService.php';
require_once __DIR__ . '/../Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../DTO/PedidoTransitionRequest.php';
require_once __DIR__ . '/../Services/PaymentJobService.php';
require_once __DIR__ . '/../Services/POR/ProofOfRoadService.php';
require_once __DIR__ . '/../Services/POR/RoutingSnapshotService.php';
require_once __DIR__ . '/../Services/Security/ConfigSecurityService.php';
require_once __DIR__ . '/../Services/CronMonitorService.php';
require_once __DIR__ . '/../Services/AdminLogService.php';
require_once __DIR__ . '/../Models/SimulationArtifact.php';
require_once __DIR__ . '/../Services/QA/PlaywrightRunnerService.php';
require_once __DIR__ . '/../Models/ServicoCatalogo.php';
require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
require_once __DIR__ . '/../Services/EspecialistaPricingService.php';
require_once __DIR__ . '/../Models/Feriado.php';
require_once __DIR__ . '/../Services/DebugMode.php';
require_once __DIR__ . '/../Models/Demanda.php';
require_once __DIR__ . '/../Services/TerritorioMetasService.php';
require_once __DIR__ . '/../Services/FinancialAttributionReportService.php';
require_once __DIR__ . '/../Services/EnderecoFormatter.php';
require_once __DIR__ . '/AdminHealthController.php';
require_once __DIR__ . '/AdminEnvAuditController.php';
require_once __DIR__ . '/AdminChatController.php';
require_once __DIR__ . '/AdminLogsController.php';

/**
 * Controller do painel administrativo
 * Admin pode acessar e gerenciar TODOS os perfis.
 */
class AdminController extends BaseController
{
    public function __construct() { parent::__construct(); }

    // ─── DASHBOARD ───────────────────────────────────────────────
    public function dashboard(): void
    {
        AuthService::requireAuth('admin');
        $totaisDia = Pedido::totaisDoDia();
        $ultPedidos = Pedido::listarRecentes(10);
        $guinchoOnline = Guincho::contarDisponiveis();
        $totalUsuarios = Usuario::contarTotal([]);
        $operationalSnapshot = Pedido::operationalSnapshot();
        $pedidoSerie = Pedido::seriePorDia(7);
        $pedidoStatusBreakdown = Pedido::statusBreakdown();
        $receitaHoje = (float)($totaisDia['faturamento'] ?? 0);
        $totalPedidosHoje = (int)($totaisDia['total'] ?? 0);
        $guinchoAtivos = (int)$guinchoOnline;
        $dashboardInsights = [
            'concluidos_hoje' => (int)($totaisDia['concluidos'] ?? 0),
            'cancelados_hoje' => (int)($totaisDia['cancelados'] ?? 0),
            'aguardando_guincho' => (int)($operationalSnapshot['aguardando_guincho'] ?? 0),
            'a_caminho' => (int)($operationalSnapshot['a_caminho'] ?? 0),
            'em_execucao' => (int)($operationalSnapshot['a_caminho'] ?? 0) + (int)($operationalSnapshot['no_local'] ?? 0) + (int)($operationalSnapshot['em_reboque'] ?? 0),
        ];
        $pedidosAtivosTotal = (int)($operationalSnapshot['aguardando_guincho'] ?? 0) + $dashboardInsights['em_execucao'];
        $etaMedioMin = $this->calcularEtaMedioMinutos();

        // Alertas operacionais reais (evidência falhou, GPS degradado, PIX em
        // retry, CNH vencendo) — ver AdminAlertService. Deliberadamente
        // separado do checklist técnico do Admin Health (/admin/health).
        require_once __DIR__ . '/../Services/AdminAlertService.php';
        $alertasPrioritarios = AdminAlertService::listar(8);
        $alertasAbertos = count($alertasPrioritarios);

        $operacaoPorHora = $this->operacaoPorHora();
        $financeiroJobs = $this->financeiroEJobs();
        $qaRelease = $this->qaRelease();

        // §CELULAS-NITEROI-01 (04/08/2026): painel de metas por célula
        // territorial + resumo estratégico (as "7 perguntas" da integração
        // marketing/financeiro) — ver TerritorioMetasService e
        // FinancialAttributionReportService::resumo()/porCanal().
        $territorioPainel = TerritorioMetasService::painel();
        $cidadesTerritorio = Cidade::listarAtivas();
        $inicioMes = date('Y-m-01');
        $hojeData = date('Y-m-d');
        $resumoEstrategico = FinancialAttributionReportService::resumo($inicioMes, $hojeData);
        $canaisEstrategico = FinancialAttributionReportService::porCanal($inicioMes, $hojeData);
        require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
        $preQuoteDemandSummary = PreQuoteDemandService::resumoPorServico();

        require_once __DIR__ . '/../Services/POR/PorThresholds.php';
        $osrmBaseUrl = PorThresholds::routingFrontendBaseUrl();

        require __DIR__ . '/../Views/admin/dashboard.php';
    }

    /**
     * Central Operacional (Pacote L2.3 — Fase 1 da remodelação do backoffice
     * admin, ver doc/BRIEFING-CODEX-API-ADMIN-ORDERS.md para a Fase 2/API
     * que vai alimentar esta mesma tela com dados de tracking/timeline/chat
     * em tempo real). Por ora: shell de despacho (nav + fila de pedidos +
     * detalhe do primeiro pedido ativo) com dado REAL do banco, sem ainda
     * polling/WebSocket — isso é fase posterior.
     */
    public function centralOperacional(): void
    {
        AuthService::requireAuth('admin');

        // Reaproveita a mesma query já usada em /admin/pedidos, sem filtro de
        // status, e filtra em memória os que ainda estão em andamento —
        // listarPorStatus() só aceita 1 status por chamada, não um array.
        $todosRecentes = Pedido::listarPorStatus('', 1, [], 100);
        $statusAtivos = ['aguardando_pagamento', 'aguardando_guincho', 'a_caminho', 'no_local', 'em_reboque', 'teste_final'];
        $pedidosAtivos = array_values(array_filter(
            $todosRecentes,
            static fn(array $p): bool => in_array((string)($p['status'] ?? ''), $statusAtivos, true)
        ));

        // Alertas reais (mesmo AdminAlertService do dashboard) correlacionados
        // ao pedido pelo padrão "Pedido #<id>" presente no campo 'info' —
        // AdminAlertService não expõe pedido_id estruturado pra todo tipo de
        // alerta (ex.: demandas sem pedido vinculado), então a correlação é
        // por regex em vez de arriscar quebrar o serviço existente.
        require_once __DIR__ . '/../Services/AdminAlertService.php';
        $alertasPorPedido = [];
        foreach (AdminAlertService::listar(30) as $alerta) {
            if (preg_match('/Pedido #(\d+)/', (string)($alerta['info'] ?? ''), $m)) {
                $pid = (int)$m[1];
                $alertasPorPedido[$pid][] = $alerta;
            }
        }

        $statusLabels = [
            'aguardando_pagamento' => ['label' => 'Aguardando pagamento', 'css' => 'new'],
            'aguardando_guincho'   => ['label' => 'Buscando prestador',   'css' => 'searching'],
            'a_caminho'            => ['label' => 'A caminho',            'css' => 'route'],
            'no_local'             => ['label' => 'No local',             'css' => 'route'],
            'em_reboque'           => ['label' => 'Em atendimento',       'css' => 'service'],
            'teste_final'          => ['label' => 'Teste final',         'css' => 'service'],
        ];

        $worklist = [];
        foreach ($pedidosAtivos as $p) {
            $pid = (int)($p['id'] ?? 0);
            $criadoEm = strtotime((string)($p['criado_em'] ?? 'now'));
            $minutosDecorridos = max(0, (int)floor((time() - $criadoEm) / 60));
            $alertasPedido = $alertasPorPedido[$pid] ?? [];
            $temAlertaErro = false;
            foreach ($alertasPedido as $a) {
                if (($a['nivel'] ?? '') === 'erro') { $temAlertaErro = true; break; }
            }
            // Prioridade real (não estética): erro correlacionado = crítico;
            // pedido esperando prestador há mais de 15 min = atenção; resto
            // segue o fluxo normal. Critério provisório da Fase 1 — a Fase 2
            // (API do Codex) deve substituir por sinal real de SLA/GPS.
            $prioridade = 'normal';
            if ($temAlertaErro) {
                $prioridade = 'critical';
            } elseif (($p['status'] ?? '') === 'aguardando_guincho' && $minutosDecorridos >= 15) {
                $prioridade = 'warning';
            }
            $statusInfo = $statusLabels[$p['status'] ?? ''] ?? ['label' => ucfirst((string)($p['status'] ?? '')), 'css' => 'new'];
            $veiculoResumo = trim(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? '') . ($p['placa'] ? ' · ' . $p['placa'] : ''));
            $worklist[] = [
                'id' => $pid,
                'codigo' => 'GF-' . $pid,
                'status' => $p['status'] ?? '',
                'status_label' => $statusInfo['label'],
                'status_css' => $statusInfo['css'],
                'prioridade' => $prioridade,
                'cliente_nome' => (string)($p['cliente_nome'] ?? ''),
                'veiculo_resumo' => $veiculoResumo !== '' ? $veiculoResumo : 'Veículo não informado',
                'guincho_operador' => $p['guincho_operador'] ?? null,
                'minutos_decorridos' => $minutosDecorridos,
                'alerta_resumo' => $alertasPedido[0]['label'] ?? '',
                'endereco_origem' => (string)($p['endereco_origem'] ?? ''),
                'endereco_destino' => (string)($p['endereco_destino'] ?? ''),
                'valor_total' => (float)($p['valor_total'] ?? 0),
            ];
        }

        $resumoOperacional = [
            'ativos' => count($worklist),
            'sem_prestador' => count(array_filter($worklist, static fn($w) => $w['status'] === 'aguardando_guincho')),
            'a_caminho' => count(array_filter($worklist, static fn($w) => in_array($w['status'], ['a_caminho', 'no_local'], true))),
            'em_atendimento' => count(array_filter($worklist, static fn($w) => $w['status'] === 'em_reboque')),
            'alertas_criticos' => count(array_filter($worklist, static fn($w) => $w['prioridade'] === 'critical')),
        ];

        // Métricas essenciais que antes só existiam no Dashboard separado —
        // trazidas pra cá pra que a Central sirva como entrada operacional
        // única (ver auditoria de navegação: "incorporar métricas do
        // dashboard no topo da Central"). O Dashboard continua existindo
        // como Resumo Executivo (gráficos + metas por território); o mapa
        // operacional ao vivo foi movido pra cá em 04/08/2026.
        $totaisDiaResumo = Pedido::totaisDoDia();
        $guinchoOnlineResumo = (int)Guincho::contarDisponiveis();
        $receitaHojeResumo = (float)($totaisDiaResumo['faturamento'] ?? 0);
        $etaMedioResumo = $this->calcularEtaMedioMinutos();

        // Fase 2: painel de detalhe (mapa/timeline/chat) consome a API real
        // do Codex (src/Api/Admin/OrdersApiController.php) via fetch no
        // browser — precisa do token CSRF pra poder postar mensagem de chat.
        $csrfToken = AuthService::gerarCsrfToken();

        // §CELULAS-NITEROI-01 (04/08/2026): "Mapa operacional ao vivo" (todos
        // os guinchos disponíveis/em atendimento) movido do Dashboard pra cá
        // — precisa da mesma URL de roteamento OSRM centralizada (item #37).
        require_once __DIR__ . '/../Services/POR/PorThresholds.php';
        $osrmBaseUrl = PorThresholds::routingFrontendBaseUrl();

        require __DIR__ . '/../Views/admin/central_operacional.php';
    }

    /**
     * Tela dedicada de Alertas Operacionais (item da sidebar reorganizada,
     * antes marcado "em breve"). Reaproveita 100% o AdminAlertService já
     * usado no widget do Command Center — só pede muito mais linhas por
     * categoria (não só o top 8) e permite filtrar por nível.
     */
    public function alertasOperacionais(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Services/AdminAlertService.php';

        $nivelFiltro = (string)($_GET['nivel'] ?? '');
        $todosAlertas = AdminAlertService::listar(500, 100);

        $alertas = [];
        foreach ($todosAlertas as $a) {
            if ($nivelFiltro !== '' && ($a['nivel'] ?? '') !== $nivelFiltro) continue;
            $pedidoId = null;
            if (preg_match('/[Pp]edido #(\d+)/', (string)($a['info'] ?? ''), $m)) {
                $pedidoId = (int)$m[1];
            }
            $a['pedido_id'] = $pedidoId;
            $alertas[] = $a;
        }

        // A fila operacional deve ser revisada por contexto, não por evento
        // individual. Agrupa todos os alertas do mesmo pedido e, quando não
        // há pedido associado, agrupa pelo tipo de evento.
        $alertasAgrupados = [];
        $nivelPeso = ['info' => 1, 'aviso' => 2, 'erro' => 3];
        foreach ($alertas as $a) {
            $pedidoId = (int)($a['pedido_id'] ?? 0);
            $label = trim((string)($a['label'] ?? 'Evento operacional'));
            $chave = $pedidoId > 0 ? 'pedido:' . $pedidoId : 'evento:' . strtolower($label);
            if (!isset($alertasAgrupados[$chave])) {
                $alertasAgrupados[$chave] = [
                    'chave' => $chave,
                    'pedido_id' => $pedidoId > 0 ? $pedidoId : null,
                    'titulo' => $pedidoId > 0 ? 'Pedido #' . $pedidoId : $label,
                    'nivel' => (string)($a['nivel'] ?? 'info'),
                    'quando' => (string)($a['quando'] ?? '—'),
                    'itens' => [],
                ];
            }
            $grupo =& $alertasAgrupados[$chave];
            $grupo['itens'][] = $a;
            if (($nivelPeso[$a['nivel'] ?? 'info'] ?? 0) > ($nivelPeso[$grupo['nivel']] ?? 0)) {
                $grupo['nivel'] = (string)$a['nivel'];
            }
            $grupo['quando'] = (string)($a['quando'] ?? $grupo['quando']);
            unset($grupo);
        }
        $alertasAgrupados = array_values($alertasAgrupados);

        $contagemPorNivel = ['erro' => 0, 'aviso' => 0, 'info' => 0];
        foreach ($todosAlertas as $a) {
            $nivel = (string)($a['nivel'] ?? '');
            if (isset($contagemPorNivel[$nivel])) $contagemPorNivel[$nivel]++;
        }

        require __DIR__ . '/../Views/admin/alertas_operacionais.php';
    }

    /**
     * Ocorrências operacionais (Pacote L2.3 — item que estava "em breve").
     * Ver install/migration_ocorrencias_v1.sql e src/Models/Ocorrencia.php.
     * Registro estruturado (avaria, atraso, conduta, veículo, segurança),
     * separado dos alertas 100% derivados do AdminAlertService.
     */
    public function ocorrencias(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Models/Ocorrencia.php';

        $statusFiltro = (string)($_GET['status'] ?? '');
        $filtros = $statusFiltro !== '' ? ['status' => $statusFiltro] : [];
        $ocorrencias = Ocorrencia::listar($filtros, 200);
        $contagemPorStatus = Ocorrencia::contarPorStatus();
        $csrfToken = AuthService::gerarCsrfToken();

        require __DIR__ . '/../Views/admin/ocorrencias.php';
    }

    /** Registra uma nova ocorrência para um pedido (via tela de Ocorrências ou detalhe do pedido). */
    public function ocorrenciaCriar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        require_once __DIR__ . '/../Models/Ocorrencia.php';

        $pedidoId = (int)($_POST['pedido_id'] ?? 0);
        $tipo = (string)($_POST['tipo'] ?? 'outro');
        $severidade = (string)($_POST['severidade'] ?? 'media');
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $tiposValidos = ['avaria', 'atraso', 'conduta', 'veiculo', 'seguranca', 'outro'];
        $severidadesValidas = ['baixa', 'media', 'alta', 'critica'];

        if (!$pedidoId || $descricao === '' || !in_array($tipo, $tiposValidos, true) || !in_array($severidade, $severidadesValidas, true)) {
            $_SESSION['_flash'][] = ['message' => 'Preencha pedido, tipo, severidade e descrição da ocorrência.', 'type' => 'error'];
            $this->redirect('/admin/ocorrencias');
            return;
        }

        $usuario = AuthService::getCurrentUser();
        Ocorrencia::criar([
            'pedido_id' => $pedidoId,
            'tipo' => $tipo,
            'severidade' => $severidade,
            'relator_tipo' => 'admin',
            'relator_id' => (int)($usuario['id'] ?? 0),
            'descricao' => $descricao,
        ]);

        $_SESSION['_flash'][] = ['message' => 'Ocorrência registrada.', 'type' => 'success'];
        $this->redirect('/admin/ocorrencias');
    }

    /** Muda o status de uma ocorrência (em_analise/resolvida/arquivada), com nota de resolução quando aplicável. */
    public function ocorrenciaResolver(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        require_once __DIR__ . '/../Models/Ocorrencia.php';

        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $resolucao = trim((string)($_POST['resolucao'] ?? ''));
        $usuario = AuthService::getCurrentUser();

        if ($id) {
            Ocorrencia::atualizarStatus($id, $status, (int)($usuario['id'] ?? 0), $resolucao !== '' ? $resolucao : null);
        }

        $_SESSION['_flash'][] = ['message' => 'Ocorrência atualizada.', 'type' => 'success'];
        // §OCORRENCIAS-SHELL-01: preserva o filtro de status e reabre no
        // mesmo item selecionado (arquitetura lista+workspace) após a ação —
        // mesmo padrão usado em Capacidades/Carteiras.
        $statusFiltroRetorno = (string)($_POST['retorno_status_filtro'] ?? '');
        $query = [];
        if ($statusFiltroRetorno !== '') $query['status'] = $statusFiltroRetorno;
        if ($id > 0) $query['ocorrencia_id'] = $id;
        $this->redirect('/admin/ocorrencias' . ($query ? ('?' . http_build_query($query)) : ''));
    }

    /** Pedidos concluídos por hora, últimas 24h (dado real, agrupado por atualizado_em). */
    private function operacaoPorHora(): array
    {
        $labels = [];
        $valores = [];
        try {
            $stmt = getPDO()->prepare(
                "SELECT DATE_FORMAT(atualizado_em, '%H:00') AS hora, COUNT(*) AS total
                 FROM pedidos
                 WHERE status = 'concluido' AND atualizado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY hora ORDER BY hora"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $labels[] = (string)$row['hora'];
                $valores[] = (int)$row['total'];
            }
        } catch (Throwable $e) {
            // Sem dados ainda — gráfico fica vazio, não fabricamos números.
        }
        return ['labels' => $labels, 'valores' => $valores];
    }

    /** Financeiro e jobs (pagamentos aprovados, jobs PIX pendentes/em falha) — 100% real. */
    private function financeiroEJobs(): array
    {
        $pdo = getPDO();
        $pctAprovados = null;
        $jobsPendentes = 0;
        $jobsFalha = 0;
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS total, SUM(status = 'aprovado') AS aprovados
                 FROM pagamentos WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
            );
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)($row['total'] ?? 0);
            if ($total > 0) {
                $pctAprovados = round(((int)($row['aprovados'] ?? 0) / $total) * 100, 1);
            }
        } catch (Throwable $e) {
        }
        try {
            $jobsPendentes = (int)$pdo->query(
                "SELECT COUNT(*) FROM payment_jobs WHERE job_type LIKE '%pix%' AND status IN ('queued','retry','processing')"
            )->fetchColumn();
            $jobsFalha = (int)$pdo->query(
                "SELECT COUNT(*) FROM payment_jobs WHERE status = 'failed'"
            )->fetchColumn();
        } catch (Throwable $e) {
        }
        return [
            'pct_aprovados' => $pctAprovados,
            'jobs_pendentes' => $jobsPendentes,
            'jobs_falha' => $jobsFalha,
        ];
    }

    /** QA de release: última execução Playwright registrada em simulation_runs/steps. */
    private function qaRelease(): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT run_id, status FROM simulation_runs
                 WHERE engine = 'playwright'
                 ORDER BY iniciado_em DESC LIMIT 1"
            );
            $stmt->execute();
            $run = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$run) {
                return ['disponivel' => false];
            }
            $stmtSteps = getPDO()->prepare(
                "SELECT COUNT(*) AS total, SUM(status = 'passed') AS aprovados,
                        MIN(CASE WHEN status = 'failed' THEN code END) AS primeira_falha
                 FROM simulation_steps WHERE run_id = ?"
            );
            $stmtSteps->execute([$run['run_id']]);
            $steps = $stmtSteps->fetch(PDO::FETCH_ASSOC);
            return [
                'disponivel' => true,
                'total' => (int)($steps['total'] ?? 0),
                'aprovados' => (int)($steps['aprovados'] ?? 0),
                'falha_codigo' => $steps['primeira_falha'] ?? null,
            ];
        } catch (Throwable $e) {
            return ['disponivel' => false];
        }
    }

    /**
     * ETA médio real (não decorativo): média do tempo estimado dos pedidos
     * atualmente em andamento, usando a mesma heurística de velocidade
     * urbana (~28km/h) já usada em GuinchoController::montarOfertasDisponiveis().
     */
    private function calcularEtaMedioMinutos(): ?int
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT distancia_km FROM pedidos
                 WHERE status IN ('aguardando_guincho','a_caminho','no_local','em_reboque')
                   AND distancia_km IS NOT NULL AND distancia_km > 0"
            );
            $stmt->execute();
            $distancias = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return null;
        }
        if (empty($distancias)) {
            return null;
        }
        $etas = array_map(static fn($d) => max(1, ((float)$d / 28) * 60), $distancias);
        return (int)round(array_sum($etas) / count($etas));
    }

    public function dashboardJson(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $totaisDia = Pedido::totaisDoDia();
        $ultPedidos = Pedido::listarRecentes(10);
        $guinchoOnline = Guincho::contarDisponiveis();
        $totalUsuarios = Usuario::contarTotal([]);
        $operationalSnapshot = Pedido::operationalSnapshot();
        $pedidoSerie = Pedido::seriePorDia(7);
        $pedidoStatusBreakdown = Pedido::statusBreakdown();
        $emExecucaoJson = (int)($operationalSnapshot['a_caminho'] ?? 0) + (int)($operationalSnapshot['no_local'] ?? 0) + (int)($operationalSnapshot['em_reboque'] ?? 0);

        require_once __DIR__ . '/../Services/AdminAlertService.php';
        $alertasPrioritarios = AdminAlertService::listar(8);
        $etaMedioMin = $this->calcularEtaMedioMinutos();

        // §CELULAS-NITEROI-01 (04/08/2026): "Metas & Território" precisa ser
        // AO VIVO, igual o resto do dashboard — recalcula tudo a cada poll
        // (sem cache), o JS troca window.__territorioPainel e re-renderiza a
        // célula selecionada no momento (ver admin-territorio-metas.js).
        $territorioPainelJson = TerritorioMetasService::painel();
        require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
        $preQuoteDemandSummary = PreQuoteDemandService::resumoPorServico();

        echo json_encode([
            'ok' => true,
            'cards' => [
                'total_pedidos_hoje' => (int)($totaisDia['total'] ?? 0),
                'receita_hoje' => (float)($totaisDia['faturamento'] ?? 0),
                'guinchos_ativos' => (int)$guinchoOnline,
                'total_usuarios' => (int)$totalUsuarios,
                'pedidos_ativos' => (int)($operationalSnapshot['aguardando_guincho'] ?? 0) + $emExecucaoJson,
                'alertas_abertos' => count($alertasPrioritarios),
                'eta_medio_min' => $etaMedioMin,
            ],
            'alertas' => array_map(static function (array $c): array {
                return [
                    'label' => (string)($c['label'] ?? ''),
                    'info' => (string)($c['info'] ?? ''),
                    'nivel' => (string)($c['nivel'] ?? 'aviso'),
                    'quando' => (string)($c['quando'] ?? ''),
                ];
            }, $alertasPrioritarios),
            'insights' => [
                'concluidos_hoje' => (int)($totaisDia['concluidos'] ?? 0),
                'cancelados_hoje' => (int)($totaisDia['cancelados'] ?? 0),
                'aguardando_guincho' => (int)($operationalSnapshot['aguardando_guincho'] ?? 0),
                'em_execucao' => (int)($operationalSnapshot['a_caminho'] ?? 0) + (int)($operationalSnapshot['no_local'] ?? 0) + (int)($operationalSnapshot['em_reboque'] ?? 0),
            ],
            'operational_snapshot' => [
                'aguardando_pagamento' => (int)($operationalSnapshot['aguardando_pagamento'] ?? 0),
                'aguardando_guincho' => (int)($operationalSnapshot['aguardando_guincho'] ?? 0),
                'a_caminho' => (int)($operationalSnapshot['a_caminho'] ?? 0),
                'no_local' => (int)($operationalSnapshot['no_local'] ?? 0),
                'em_reboque' => (int)($operationalSnapshot['em_reboque'] ?? 0),
            ],
            'status_breakdown' => array_map(function (array $row): array {
                $status = (string)($row['status'] ?? '');
                return [
                    'status' => $status,
                    'label' => $this->statusLabel($status),
                    'total' => (int)($row['total'] ?? 0),
                ];
            }, $pedidoStatusBreakdown),
            'serie' => array_map(static function (array $row): array {
                return [
                    'label' => (string)($row['label'] ?? ''),
                    'total' => (int)($row['total'] ?? 0),
                    'concluidos' => (int)($row['concluidos'] ?? 0),
                ];
            }, $pedidoSerie),
            'ultimos_pedidos' => array_map(function (array $pedido): array {
                $status = (string)($pedido['status'] ?? '');
                return [
                    'id' => (int)($pedido['id'] ?? 0),
                    'cliente_nome' => (string)($pedido['cliente_nome'] ?? '—'),
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'data_label' => date('d/m H:i', strtotime((string)($pedido['criado_em'] ?? 'now'))),
                ];
            }, $ultPedidos),
            'territorio_painel' => $territorioPainelJson,
            'pre_quote_demand_summary' => $preQuoteDemandSummary,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * GET /admin/dashboard/mapa-json
     * Mapa operacional ao vivo: mostra o CLIENTE quando há pedido ativo
     * (origem do pedido, lat_origem/lng_origem) e o GUINCHO, tanto disponível
     * (ocioso, aguardando chamado) quanto em atendimento (a_caminho/no_local/
     * em_reboque), sempre pela posição real mais recente (lat_atual/lng_atual,
     * atualizada a cada ping de Guincho::atualizarLocalizacao(), inclusive
     * durante o deslocamento — ver GuinchoController::atualizarLocalizacao()).
     *
     * Achado em QA (2026-07): a query anterior só trazia guinchos com
     * disponivel=1, ou seja, EXCLUÍA justamente os guinchos que estão em
     * atendimento ativo — o admin nunca via o deslocamento de verdade no
     * mapa, só via guinchos ociosos parados. Corrigido unindo com o pedido
     * ativo do guincho (se houver) para expor status/pedido_id também.
     */
    public function dashboardMapaJson(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $pdo = getPDO();

        $stmtPedidos = $pdo->prepare(
            "SELECT p.id, p.status, p.lat_origem, p.lng_origem, p.endereco_origem,
                    p.lat_destino, p.lng_destino, p.endereco_destino,
                    u.nome AS cliente_nome
             FROM pedidos p
             JOIN usuarios u ON u.id = p.cliente_id
             WHERE p.status IN ('aguardando_guincho','a_caminho','no_local','em_reboque')
               AND p.lat_origem IS NOT NULL AND p.lng_origem IS NOT NULL
             ORDER BY p.criado_em DESC
             LIMIT 100"
        );
        $stmtPedidos->execute();
        $pedidosAtivos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

        $stmtGuinchos = $pdo->prepare(
            "SELECT g.id, g.placa_guincho, u.nome AS operador_nome, g.disponivel,
                    COALESCE(g.lat_atual, g.lat_operacao) AS lat,
                    COALESCE(g.lng_atual, g.lng_operacao) AS lng,
                    ap.id AS pedido_ativo_id, ap.status AS pedido_ativo_status,
                    ap.lat_origem AS pedido_lat_origem, ap.lng_origem AS pedido_lng_origem,
                    ap.lat_destino AS pedido_lat_destino, ap.lng_destino AS pedido_lng_destino
             FROM guinchos g
             JOIN usuarios u ON u.id = g.usuario_id
             LEFT JOIN pedidos ap ON ap.guincho_id = g.id
                 AND ap.status IN ('a_caminho','no_local','em_reboque')
             WHERE g.aprovado = 1 AND u.ativo = 1
               AND (g.disponivel = 1 OR ap.id IS NOT NULL)
               AND COALESCE(g.lat_atual, g.lat_operacao) IS NOT NULL
               AND COALESCE(g.lng_atual, g.lng_operacao) IS NOT NULL
             LIMIT 200"
        );
        $stmtGuinchos->execute();
        $guinchosMapa = $stmtGuinchos->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'atualizado_em' => date('H:i:s'),
            'clientes' => array_map(static function (array $p): array {
                return [
                    'pedido_id' => (int)$p['id'],
                    'status' => (string)$p['status'],
                    'lat' => (float)$p['lat_origem'],
                    'lng' => (float)$p['lng_origem'],
                    'label' => (string)($p['cliente_nome'] ?? 'Cliente'),
                    'endereco' => (string)($p['endereco_origem'] ?? ''),
                    'lat_destino' => $p['lat_destino'] !== null ? (float)$p['lat_destino'] : null,
                    'lng_destino' => $p['lng_destino'] !== null ? (float)$p['lng_destino'] : null,
                    'endereco_destino' => (string)($p['endereco_destino'] ?? ''),
                ];
            }, $pedidosAtivos),
            'guinchos' => array_map(static function (array $g) use ($pdo): array {
                $emAtendimento = !empty($g['pedido_ativo_id']);
                $rota = null;
                if ($emAtendimento) {
                    // Alvo do trajeto: enquanto "a_caminho", o guincho está indo
                    // buscar o veículo (origem); em "no_local"/"em_reboque", já
                    // coletou e está indo pro destino.
                    $indoPara = $g['pedido_ativo_status'] === 'a_caminho' ? 'origem' : 'destino';
                    $targetLat = $indoPara === 'origem' ? $g['pedido_lat_origem'] : $g['pedido_lat_destino'];
                    $targetLng = $indoPara === 'origem' ? $g['pedido_lng_origem'] : $g['pedido_lng_destino'];

                    $trailStmt = $pdo->prepare(
                        "SELECT latitude, longitude
                           FROM pedido_localizacoes
                          WHERE pedido_id = ? AND is_valid = 1
                          ORDER BY id DESC
                          LIMIT 60"
                    );
                    $trailStmt->execute([(int)$g['pedido_ativo_id']]);
                    $trailPoints = array_reverse($trailStmt->fetchAll(PDO::FETCH_ASSOC));

                    $rota = [
                        'target_lat' => $targetLat !== null ? (float)$targetLat : null,
                        'target_lng' => $targetLng !== null ? (float)$targetLng : null,
                        'indo_para' => $indoPara,
                        'trail' => array_map(static fn(array $p): array => [
                            'lat' => (float)$p['latitude'],
                            'lng' => (float)$p['longitude'],
                        ], $trailPoints),
                    ];
                }

                return [
                    'guincho_id' => (int)$g['id'],
                    'lat' => (float)$g['lat'],
                    'lng' => (float)$g['lng'],
                    'label' => (string)($g['operador_nome'] ?? 'Guincho'),
                    'placa' => (string)($g['placa_guincho'] ?? ''),
                    'em_atendimento' => $emAtendimento,
                    'pedido_id' => $emAtendimento ? (int)$g['pedido_ativo_id'] : null,
                    'pedido_status' => $emAtendimento ? (string)$g['pedido_ativo_status'] : null,
                    'rota' => $rota,
                ];
            }, $guinchosMapa),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ─── USUÁRIOS ────────────────────────────────────────────────
    public function usuarios(): void
    {
        AuthService::requireAuth('admin');
        $pagina  = max(1, (int)($_GET['pagina'] ?? 1));
        $filtros = [
            'busca' => $_GET['busca'] ?? '',
            'tipo'  => $_GET['tipo']  ?? '',
        ];
        if (($_GET['ativo'] ?? '') === '0') { $filtros['ativo'] = 0; }
        $usuarios = Usuario::listar($filtros, $pagina);
        $total    = Usuario::contarTotal($filtros);
        // §PESSOAS-ABAS-01: "abas" da página (Clientes/Prestadores/
        // Administradores/Suspensos) são só filtros ?tipo=/?ativo= sobre a
        // mesma listagem paginada — Usuario::listar()/contarTotal() já
        // suportavam ambos os filtros, não precisou de query nova.
        $resumoUsuarios = [
            'clientes'  => Usuario::contarTotal(['tipo' => 'cliente']),
            'guinchos'  => Usuario::contarTotal(['tipo' => 'guincho']),
            'admins'    => Usuario::contarTotal(['tipo' => 'admin']),
            'suspensos' => Usuario::contarTotal(['ativo' => 0]),
        ];
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/usuarios.php';
    }

    /**
     * §USUARIOS-SHELL-01: extraído de usuarioDetalhe() pra ser reaproveitado
     * também pelo fragmento AJAX do workspace de /admin/usuarios (mesmo
     * padrão de carregarDetalheGuincho()/guincho_detalhe_workspace.php).
     */
    private function carregarDetalheUsuario(int $id): ?array
    {
        $usuario = Usuario::buscarPorId($id);
        if (!$usuario) return null;

        $extra = null;
        if ($usuario['tipo'] === 'guincho') {
            $extra = Guincho::buscarPorUsuario($usuario['id']);
        }
        $pedidos = [];
        try {
            if ($usuario['tipo'] === 'cliente') {
                $pedidos = Pedido::listarPorCliente($usuario['id'], 1, 10);
            } elseif ($usuario['tipo'] === 'guincho' && !empty($extra['id'])) {
                $pedidos = Pedido::listarPorGuincho($extra['id'], 1, 10);
            }
        } catch (Throwable $e) { $pedidos = []; }

        return [
            'usuario' => $usuario,
            'extra' => $extra,
            'pedidos' => $pedidos,
            'csrfToken' => AuthService::gerarCsrfToken(),
        ];
    }

    public function usuarioDetalheFragmento(int $id): void
    {
        AuthService::requireAuth('admin');
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        try {

        $dados = $this->carregarDetalheUsuario($id);
        if ($dados === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'erro' => 'Usuário não encontrado.']);
            exit;
        }

        extract($dados);
        ob_start();
        require __DIR__ . '/../Views/admin/partials/usuario_detalhe_workspace.php';
        $html = ob_get_clean();

        if (ob_get_level() > 0) ob_clean();
        echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            if (ob_get_level() > 0) ob_clean();
            Logger::exception('AdminController', 'usuarioDetalheFragmento', 'admin_usuarios', $e, ['usuario_id' => $id]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Não foi possível carregar os dados deste usuário.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function usuarioDetalhe(int $id): void
    {
        AuthService::requireAuth('admin');
        $this->redirect('/admin/usuarios?usuario_id=' . $id);
    }

    public function usuarioForm(): void
    {
        AuthService::requireAuth('admin');
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/usuarioform.php';
    }

    public function usuarioSalvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $nome  = trim($_POST['nome']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $tipo  = in_array($_POST['tipo'] ?? '', ['admin','cliente','guincho','funcionario','gerente'])
                    ? $_POST['tipo'] : 'cliente';
        $tel   = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
        $cpf   = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($nome) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) {
            $this->redirect('/admin/usuario/novo?erro=1');
        }

        // Verificar duplicatas
        $pdo = getPDO();
        $existe = $pdo->prepare("SELECT id FROM usuarios WHERE email=? OR cpf=? LIMIT 1");
        $existe->execute([$email, $cpf]);
        if ($existe->fetch()) {
            $this->redirect('/admin/usuario/novo?erro=email_duplicado');
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo,criado_em)
                                VALUES (?,?,?,?,?,?,1,NOW())");
        $stmt->execute([$nome, $email, $hash, $tel, $cpf, $tipo]);
        $usuarioId = (int)$pdo->lastInsertId();
        if ($tipo === 'guincho') {
            $gDados = [
                'cnh_numero'        => trim($_POST['cnh_numero'] ?? ''),
                'cnh_validade'      => $_POST['cnh_validade'] ?? date('Y-m-d', strtotime('+5 years')),
                'placa_guincho'     => strtoupper(trim($_POST['placa_guincho'] ?? 'AAA0000')),
                'capacidade_ton'    => (float)($_POST['capacidade_ton'] ?? 1),
                'raio_cobertura_km' => (int)($_POST['raio_cobertura_km'] ?? 20),
                'chave_pix'         => trim($_POST['chave_pix'] ?? ''),
                'chave_pix_tipo'    => $_POST['chave_pix_tipo'] ?? 'cpf',
                'foto_veiculo'      => $this->processarUpload('foto_veiculo'),
                'doc_cnh_frente'    => $this->processarUpload('doc_cnh_frente'),
                'doc_cnh_verso'     => $this->processarUpload('doc_cnh_verso'),
            ];
            Guincho::criarDeRegistro($usuarioId, $gDados);
        }
        $this->redirect('/admin/usuarios?criado=1');
    }

    public function usuarioAtivar(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        if ($id === 0) $id = (int)($_POST['id'] ?? 0);
        Usuario::ativar($id);
        $retornoUsuarioId = (int)($_POST['retorno_usuario_id'] ?? 0);
        $this->redirect('/admin/usuarios?msg=ativado' . ($retornoUsuarioId > 0 ? '&usuario_id=' . $retornoUsuarioId : ''));
    }

    public function usuarioSuspender(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        if ($id === 0) $id = (int)($_POST['id'] ?? 0);
        Usuario::suspender($id);
        $retornoUsuarioId = (int)($_POST['retorno_usuario_id'] ?? 0);
        $this->redirect('/admin/usuarios?msg=suspenso' . ($retornoUsuarioId > 0 ? '&usuario_id=' . $retornoUsuarioId : ''));
    }

    public function usuariosSuspenderGet(): void
    {
        AuthService::requireAuth('admin');
        $this->redirect('/admin/usuarios?info=suspensao_via_post');
    }

    // ─── ESPECIALISTAS ───────────────────────────────────────────
    public function especialistas(): void
    {
        AuthService::requireAuth('admin');
        $pdo = getPDO();
        $stmt = $pdo->query(
            "SELECT u.id, e.id AS especialista_id, u.nome, u.email, u.telefone,
                    e.nome_profissional, e.cpf_cnpj, e.chave_pix, e.chave_pix_tipo,
                    e.raio_atendimento_km, e.bio, e.aprovado, e.disponivel, e.reputacao, e.total_avaliacoes,
                    GROUP_CONCAT(se.nome ORDER BY se.nome SEPARATOR ', ') AS especialidade
             FROM usuarios u
             JOIN especialistas e ON u.id = e.usuario_id
             LEFT JOIN especialista_servicos es ON es.especialista_id=e.id AND es.habilitado=1
             LEFT JOIN servicos_especialista se ON se.id=es.servico_id
             WHERE u.tipo='especialista'
             GROUP BY u.id, u.nome, e.aprovado, e.disponivel
             ORDER BY e.aprovado ASC, u.nome ASC"
        );
        $especialistas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        require __DIR__ . '/../Views/admin/especialistas.php';
    }

    public function especialistaCadastroForm(): void
    {
        AuthService::requireAuth('admin');
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/especialista_form.php';
    }

    public function especialistaDetalheFragmento(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        $id = max(0, $id);
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT u.id, u.nome, u.email, u.telefone, u.ativo,
                    e.id AS especialista_id, e.nome_profissional, e.cpf_cnpj,
                    e.documento_tipo, e.documento_numero, e.chave_pix, e.chave_pix_tipo,
                    e.raio_atendimento_km, e.bio, e.aprovado, e.disponivel, e.reputacao, e.total_avaliacoes, e.criado_em,
                    GROUP_CONCAT(se.nome ORDER BY se.nome SEPARATOR ', ') AS servicos
             FROM especialistas e JOIN usuarios u ON u.id=e.usuario_id
             LEFT JOIN especialista_servicos es ON es.especialista_id=e.id AND es.habilitado=1
             LEFT JOIN servicos_especialista se ON se.id=es.servico_id
             WHERE e.id=? GROUP BY u.id, e.id");
        $stmt->execute([$id]);
        $especialista = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$especialista) { http_response_code(404); echo '<div class="ops-empty-state">Especialista não encontrado.</div>'; return; }
        $evs = $pdo->prepare("SELECT ev.id, ev.evento, ev.criado_em, ev.metadata_json, i.id AS incidente_id FROM atendimento_eventos ev JOIN atendimentos_especialista a ON a.id=ev.atendimento_id JOIN incidentes i ON i.id=ev.incidente_id WHERE a.especialista_id=? ORDER BY ev.criado_em DESC LIMIT 20");
        $evs->execute([$id]); $especialista['evidencias'] = $evs->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $docs = $pdo->prepare('SELECT id,tipo,numero,arquivo,status,observacao_admin,criado_em FROM especialista_documentos WHERE especialista_id=? ORDER BY criado_em DESC');
        $docs->execute([$id]); $especialista['documentos'] = $docs->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $fin = $pdo->prepare("SELECT fl.tipo, fl.valor, fl.status, fl.criado_em, fl.referencia_id FROM financeiro_lancamentos fl JOIN atendimentos_especialista a ON a.incidente_id=fl.incidente_id WHERE a.especialista_id=? ORDER BY fl.criado_em DESC LIMIT 30");
        $fin->execute([$id]); $especialista['financeiro'] = $fin->fetchAll(PDO::FETCH_ASSOC) ?: [];
        require __DIR__ . '/../Views/admin/partials/especialista_detalhe_workspace.php';
    }

    public function guinchosPendentes(): void
    {
        AuthService::requireAuth('admin');
        $pendentes = Guincho::listarPendentes();
        $semDocumentos = 0;
        foreach ($pendentes as $guincho) {
            if (empty($guincho['doc_cnh_frente']) && empty($guincho['foto_veiculo'])) $semDocumentos++;
        }
        $resumoPendentes = ['total' => count($pendentes), 'sem_documentos' => $semDocumentos];
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/guinchospendentes.php';
    }

    public function especialistaAprovar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $id = max(0, (int)($_POST['id'] ?? 0));
        if ($id > 0) getPDO()->prepare('UPDATE especialistas SET aprovado=1 WHERE id=?')->execute([$id]);
        $this->redirect('/admin/especialistas?msg=aprovado');
    }

    public function especialistaSuspender(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $id = max(0, (int)($_POST['id'] ?? 0));
        if ($id > 0) getPDO()->prepare('UPDATE especialistas SET aprovado=0, disponivel=0 WHERE id=?')->execute([$id]);
        $this->redirect('/admin/especialistas?msg=suspenso');
    }

    public function especialistaDocumentoStatus(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $id = max(0, (int)($_POST['documento_id'] ?? 0));
        $status = (string)($_POST['status'] ?? '');
        if ($id > 0 && in_array($status, ['aprovado','rejeitado'], true)) {
            getPDO()->prepare('UPDATE especialista_documentos SET status=?, observacao_admin=? WHERE id=?')
                ->execute([$status, trim((string)($_POST['observacao_admin'] ?? '')) ?: null, $id]);
        }
        $this->redirect('/admin/especialistas?msg=documento_' . ($status === 'aprovado' ? 'aprovado' : 'rejeitado'));
    }


    /**
     * §GUINCHOS-SHELL-01: carrega tudo que o painel de detalhe de um
     * guincho precisa (dados do operador/veículo, capacidades, compat.
     * veicular, últimos atendimentos). Extraído pra método próprio porque
     * agora tem DOIS consumidores: a antiga página standalone (mantida só
     * como redirect de compatibilidade, ver guinchoDetalhe()) e o endpoint
     * de fragmento HTML consumido via fetch() pelo workspace de
     * /admin/guinchos (guinchoDetalheFragmento()).
     * @return array|null null se o guincho não existe.
     */
    private function carregarDetalheGuincho(int $id): ?array
    {
        $guincho = Guincho::buscarPorId($id);
        if (!$guincho) return null;
        $usuario = Usuario::buscarPorId($guincho['usuario_id']);
        $pedidos = Pedido::listarPorGuincho($id, 1, 15);

        // Serviços prestados (máximo de detalhe): capacidades declaradas +
        // compatibilidade veicular + status de reboque.
        require_once __DIR__ . '/../Models/Catalog/ProviderCapability.php';
        require_once __DIR__ . '/../Models/Dispatch/ProviderServiceVehicleCapability.php';
        $capacidades = ProviderCapability::listarPorPrestador($id);
        $capacidadesVeiculares = ProviderServiceVehicleCapability::listarPorPrestador($id);
        // Agrupa compatibilidades veiculares por service_type_id para exibir junto.
        $compatPorServico = [];
        foreach ($capacidadesVeiculares as $cv) {
            $compatPorServico[(int)$cv['service_type_id']][] = $cv;
        }

        return [
            'guincho' => $guincho,
            'usuario' => $usuario,
            'pedidos' => $pedidos,
            'capacidades' => $capacidades,
            'compatPorServico' => $compatPorServico,
            'csrfToken' => AuthService::gerarCsrfToken(),
        ];
    }

    /**
     * §GUINCHOS-SHELL-01: /admin/guinchos agora é shell-ops (lista +
     * workspace, mesmo padrão de Central/Capacidades/Carteiras/Ocorrências).
     * Esta rota antiga (/admin/guincho/{id}) vira um redirect pra manter
     * links/favoritos antigos funcionando.
     */
    public function guinchoDetalhe(int $id): void
    {
        AuthService::requireAuth('admin');
        $this->redirect('/admin/guinchos?guincho_id=' . $id);
    }

    /**
     * Fragmento HTML do painel de detalhe de um guincho, consumido via
     * fetch() pelo workspace de /admin/guinchos ao selecionar um item —
     * essa tela é pesada demais (múltiplas seções/queries) pra pré-renderizar
     * a de TODOS os guinchos aprovados de uma vez.
     */
    public function guinchoDetalheFragmento(int $id): void
    {
        AuthService::requireAuth('admin');
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        try {

        $dados = $this->carregarDetalheGuincho($id);
        if ($dados === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'erro' => 'Guincho não encontrado.']);
            exit;
        }

        extract($dados);
        ob_start();
        require __DIR__ . '/../Views/admin/partials/guincho_detalhe_workspace.php';
        $html = ob_get_clean();

        if (ob_get_level() > 0) ob_clean();
        echo json_encode(['ok' => true, 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            if (ob_get_level() > 0) ob_clean();
            Logger::exception('AdminController', 'guinchoDetalheFragmento', 'admin_prestadores', $e, ['guincho_id' => $id]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Falha ao carregar os dados do prestador.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function guinchoAprovar(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        if ($id === 0) $id = (int)($_POST['id'] ?? 0);
        Guincho::aprovar($id);
        $guincho = Guincho::buscarPorId($id);
        if ($guincho) {
            $usuario = Usuario::buscarPorId($guincho['usuario_id']);
            if ($usuario) {
                require_once __DIR__ . '/../Services/NotificacaoService.php';
                NotificacaoService::cadastroAprovado($usuario);
            }
        }
        $this->redirect('/admin/guinchos?msg=aprovado');
    }

    public function guinchoRejeitar(int $id = 0): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        if ($id === 0) $id = (int)($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        // Marca guincho como rejeitado e suspende usuário
        $pdo = getPDO();
        $g = Guincho::buscarPorId($id);
        if ($g) {
            $pdo->prepare("UPDATE guinchos SET aprovado=0 WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE usuarios SET ativo=0 WHERE id=?")->execute([$g['usuario_id']]);
        }
        $this->redirect('/admin/guinchos?msg=rejeitado');
    }

    // ─── PEDIDOS ─────────────────────────────────────────────────
    public function pedidos(): void
    {
        AuthService::requireAuth('admin');
        $pagina  = max(1, (int)($_GET['pagina'] ?? 1));
        $filtros = [
            'status' => $_GET['status'] ?? '',
            'busca'  => $_GET['busca']  ?? '',
            'data'   => $_GET['data']   ?? '',
        ];
        $pedidos = Pedido::listarPorStatus((string)($filtros['status'] ?? ''), $pagina, $filtros);
        $total   = Pedido::contar($filtros);
        $totalPaginas = (int)ceil($total / 50);
        $resumoPedidos = [
            'aguardando_guincho' => Pedido::contar(['status' => 'aguardando_guincho']),
            'em_atendimento'     => Pedido::contar(['status' => 'a_caminho']) + Pedido::contar(['status' => 'no_local']) + Pedido::contar(['status' => 'em_reboque']),
            'concluido_hoje'     => Pedido::contar(['status' => 'concluido', 'data' => date('Y-m-d')]),
            'cancelado'          => Pedido::contar(['status' => 'cancelado']),
        ];

        // §PEDIDOS-SHELL-01: monta a fila no mesmo formato consumido por
        // AdminOrderWorkspace (public/assets/js/admin-order-workspace.js),
        // reaproveitado de AdminController::centralOperacional() — o painel
        // de detalhe (mapa/timeline/chat) é o mesmo, consumindo a mesma API
        // real /api/admin/orders/{id}. Aqui cobre TODOS os status (incluindo
        // concluído/cancelado), diferente da Central que só mostra ativos.
        $statusLabelsWorklist = [
            'aguardando_pagamento' => ['label' => 'Aguardando pagamento', 'css' => 'new'],
            'aguardando_guincho'   => ['label' => 'Buscando prestador',   'css' => 'searching'],
            'a_caminho'            => ['label' => 'A caminho',            'css' => 'route'],
            'no_local'             => ['label' => 'No local',             'css' => 'route'],
            'em_reboque'           => ['label' => 'Em atendimento',       'css' => 'service'],
            'teste_final'          => ['label' => 'Teste final',         'css' => 'service'],
            'concluido'            => ['label' => 'Concluído',           'css' => 'service'],
            'cancelado'            => ['label' => 'Cancelado',           'css' => 'audit'],
        ];
        $worklist = [];
        foreach ($pedidos as $p) {
            $pid = (int)($p['id'] ?? 0);
            $criadoEm = strtotime((string)($p['criado_em'] ?? 'now'));
            $minutosDecorridos = max(0, (int)floor((time() - $criadoEm) / 60));
            $statusInfo = $statusLabelsWorklist[$p['status'] ?? ''] ?? ['label' => ucfirst(str_replace('_', ' ', (string)($p['status'] ?? ''))), 'css' => 'new'];
            $veiculoResumo = trim(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? '') . ($p['placa'] ? ' · ' . $p['placa'] : ''));
            $worklist[] = [
                'id' => $pid,
                'codigo' => 'GF-' . $pid,
                'status' => $p['status'] ?? '',
                'status_label' => $statusInfo['label'],
                'status_css' => $statusInfo['css'],
                'prioridade' => ($p['status'] ?? '') === 'aguardando_guincho' && $minutosDecorridos >= 15 ? 'warning' : 'normal',
                'cliente_nome' => (string)($p['cliente_nome'] ?? ''),
                'veiculo_resumo' => $veiculoResumo !== '' ? $veiculoResumo : 'Veículo não informado',
                'guincho_operador' => $p['guincho_operador'] ?? null,
                'minutos_decorridos' => $minutosDecorridos,
                'alerta_resumo' => '',
                'endereco_origem' => (string)($p['endereco_origem'] ?? ''),
                'endereco_destino' => (string)($p['endereco_destino'] ?? ''),
                'valor_total' => (float)($p['valor_total'] ?? 0),
            ];
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/pedidos.php';
    }

    public function pedidosJson(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $pagina  = max(1, (int)($_GET['pagina'] ?? 1));
        $filtros = [
            'status' => $_GET['status'] ?? '',
            'busca'  => $_GET['busca']  ?? '',
            'data'   => $_GET['data']   ?? '',
        ];

        $pedidos = Pedido::listarPorStatus((string)($filtros['status'] ?? ''), $pagina, $filtros);
        $total   = Pedido::contar($filtros);
        $totalPaginas = (int)ceil($total / 50);

        echo json_encode([
            'ok' => true,
            'pagina' => $pagina,
            'total' => $total,
            'total_paginas' => max(1, $totalPaginas),
            'pedidos' => array_map([$this, 'serializePedidoListItem'], $pedidos),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function pedidoDetalhe(int $id): void
    {
        AuthService::requireAuth('admin');
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido) { http_response_code(404); echo '<h1>404 — Pedido não encontrado</h1>'; exit; }
        $mensagens = Chat::listarPorPedido($id);
        $csrfToken = AuthService::gerarCsrfToken();
        $paymentJobs = PaymentJobService::listByPedido($id);
        $paymentAttemptsByJob = [];
        foreach ($paymentJobs as $paymentJob) {
            $paymentAttemptsByJob[(int)$paymentJob['id']] = PaymentJobService::listAttempts((int)$paymentJob['id']);
        }
        $routingSnapshot = RoutingSnapshotService::buildForPedido($pedido);
        $porSummary = ProofOfRoadService::getSummary($id);
        $porTrail = ProofOfRoadService::getTrail($id, 80);
        $porRejected = PedidoLocalizacao::listarRejeitados($id, 15);
        // Guinchos disponíveis para atribuição
        $guinchoDisponiveis = [];
        if (empty($pedido['guincho_id'])) {
            $guinchoDisponiveis = Guincho::listarAprovados();
        }
        // Salvaguarda de conclusão manual: sabe se já existe evidência normal
        // (GPS) aceita de coleta/entrega para não exigir comprovante manual
        // duplicado do que já foi validado pelo fluxo padrão.
        $pdo = getPDO();
        $jaTemEvidencia = ['coleta' => false, 'entrega' => false];
        try {
            $stmtEvid = $pdo->prepare("SELECT tipo FROM pedido_evidencias WHERE pedido_id = ? AND status = 'accepted'");
            $stmtEvid->execute([$id]);
            foreach ($stmtEvid->fetchAll(PDO::FETCH_COLUMN) as $tipoEvid) {
                if (isset($jaTemEvidencia[$tipoEvid])) {
                    $jaTemEvidencia[$tipoEvid] = true;
                }
            }
        } catch (Throwable $e) {
            // best-effort — se falhar, o form simplesmente pede os dois comprovantes
        }
        // §EVIDENCIA-LINK-01: as fotos de coleta/entrega saíram de
        // public/uploads (webroot) pra storage/private/evidencias há tempos
        // (ver EvidenceService), mas esta tela ainda linkava direto pra
        // /public/uploads/{foto_plataforma|foto_destino} — link morto desde
        // então. Busca a evidência real (com id) pra linkar via rota
        // autenticada /evidencia/{id}, igual o resto do sistema já faz.
        $evidenciaColeta = PedidoEvidencia::buscarUltimaPorTipo($id, 'coleta');
        $evidenciaEntrega = PedidoEvidencia::buscarUltimaPorTipo($id, 'entrega');
        // Trilha de auditoria funcionário → gerente deste pedido: quem
        // solicitou cada ação sensível (cancelamento, conclusão manual,
        // reembolso etc.), quem aprovou/rejeitou e quando — direto no
        // registro do pedido, sem precisar ir a uma tela separada.
        $demandasDoPedido = Demanda::listarPorPedido($id);
        foreach ($paymentJobs as $paymentJob) {
            $demandasDoPedido = array_merge($demandasDoPedido, Demanda::listarPorPaymentJob((int)$paymentJob['id']));
        }
        require __DIR__ . '/../Views/admin/pedidodetalhe.php';
    }

    public function pedidoTrilha(int $id): void
    {
        AuthService::requireAuth('admin');
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido) {
            http_response_code(404);
            echo '<h1>404 — Pedido não encontrado</h1>';
            exit;
        }

        $trailFilters = [
            'fase' => (string)($_GET['fase'] ?? ''),
            'valid_only' => (string)($_GET['valid_only'] ?? ''),
        ];
        $routingSnapshot = RoutingSnapshotService::buildForPedido($pedido);
        $porSummary = ProofOfRoadService::getSummary($id);
        $porTrail = PedidoLocalizacao::listarTrailFiltrado($id, $trailFilters, 400);
        $porRejected = PedidoLocalizacao::listarRejeitados($id, 100);
        $porRejectedSummary = PedidoLocalizacao::resumirRejeicoes($id);
        require __DIR__ . '/../Views/admin/pedido_trilha.php';
    }

    public function pedidoStatusJson(int $id): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=UTF-8');

        $pedido = Pedido::buscarPorId($id);
        if (!$pedido) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'erro' => 'pedido_nao_encontrado'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $trailFilters = [
            'fase' => (string)($_GET['fase'] ?? ''),
            'valid_only' => (string)($_GET['valid_only'] ?? ''),
        ];
        $trailLimit = max(20, min(400, (int)($_GET['limit'] ?? 120)));
        // §A3 — cliente de polling manda o maior id já visto pra receber só
        // pontos novos; sem o parâmetro, comportamento igual a antes.
        $sincePointId = isset($_GET['since_point_id']) && ctype_digit((string)$_GET['since_point_id'])
            ? (int)$_GET['since_point_id']
            : null;
        $routingSnapshot = RoutingSnapshotService::buildForPedido($pedido);
        $porSummary = ProofOfRoadService::getSummary($id);
        $porTrail = PedidoLocalizacao::listarTrailFiltrado($id, $trailFilters, $trailLimit, $sincePointId);
        $porRejected = PedidoLocalizacao::listarRejeitados($id, 100);
        $porRejectedSummary = PedidoLocalizacao::resumirRejeicoes($id);
        $porSnapshot = ProofOfRoadService::getCurrentSnapshot($id);

        echo json_encode([
            'ok' => true,
            'pedido' => $this->serializePedidoStatus($pedido),
            'routing_snapshot' => $routingSnapshot,
            'por_summary' => $porSummary,
            'por_trail_incremental' => $sincePointId !== null,
            'por_trail' => array_map([$this, 'serializeTrailPoint'], $porTrail),
            'por_rejected' => array_map([$this, 'serializeTrailPoint'], $porRejected),
            'por_rejected_summary' => $porRejectedSummary,
            'last_point' => $porSnapshot['last_point'] ? $this->serializeTrailPoint($porSnapshot['last_point']) : null,
            'last_valid_point' => $porSnapshot['last_valid_point'] ? $this->serializeTrailPoint($porSnapshot['last_valid_point']) : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function pedidoCriarForm(): void
    {
        AuthService::requireAuth('admin');
        $pdo      = getPDO();
        $clientes = $pdo->query(
            "SELECT u.id, u.nome, u.email FROM usuarios u WHERE u.tipo='cliente' AND u.ativo=1 ORDER BY u.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
        $veiculos = [];   // carregados via AJAX ao selecionar cliente
        $guinchos = $pdo->query(
            "SELECT g.id, u.nome, g.placa_guincho FROM guinchos g JOIN usuarios u ON g.usuario_id=u.id WHERE g.aprovado=1 AND g.disponivel=1 AND u.ativo=1 ORDER BY u.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
        $cfg      = Configuracao::getAll();
        // Paridade com o painel do cliente: catálogo de tipos de serviço
        // (define attendance_mode e alimenta matching/compatibilidade).
        $tiposServico = ServiceType::listarAtivos();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/pedidocriar.php';
    }

    public function pedidoCriar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $pdo         = getPDO();
        $clienteId   = (int)($_POST['cliente_id']   ?? 0);
        $veiculoId   = (int)($_POST['veiculo_id']   ?? 0);
        $guinchoId   = (int)($_POST['guincho_id']   ?? 0) ?: null;
        $tipo        = $_POST['tipo_problema'] ?? 'outro';
        $descricao   = trim($_POST['descricao'] ?? '');
        $numeroOrigem = trim((string)($_POST['numero_origem'] ?? ''));
        $numeroDestino = trim((string)($_POST['numero_destino'] ?? ''));
        $endOrigem   = EnderecoFormatter::comNumeroNoTexto(
            (string)($_POST['endereco_origem'] ?? ''),
            $numeroOrigem !== '' ? $numeroOrigem : null
        );
        $endDestino  = EnderecoFormatter::comNumeroNoTexto(
            (string)($_POST['endereco_destino'] ?? ''),
            $numeroDestino !== '' ? $numeroDestino : null
        );
        $latOrigem   = (float)($_POST['lat_origem']  ?? -23.5505);
        $lngOrigem   = (float)($_POST['lng_origem']  ?? -46.6333);
        $latDestino  = (float)($_POST['lat_destino'] ?? -23.5505);
        $lngDestino  = (float)($_POST['lng_destino'] ?? -46.6333);

        // Cálculo básico de custo
        $dist = (float)($_POST['distancia_km'] ?? 5);
        require_once __DIR__ . '/../Services/TarifaService.php';
        $veiculo = $veiculoId > 0 ? Veiculo::buscarPorId($veiculoId) : null;
        $categoriaTarifa = is_array($veiculo) ? TarifaService::categoriaDeVeiculo($veiculo) : null;
        $cfg  = Configuracao::getAll();


        $tiposValidos = ['eletrica','pneu','colisao','bateria','combustivel','outro'];
        if (!in_array($tipo, $tiposValidos)) $tipo = 'outro';

        // Paridade com o painel do cliente (Etapa 2/14/15) —
        // tipo de serviço do catálogo (define attendance_mode) e as
        // condições situacionais da ocorrência.
        $serviceTypeId = null;
        $attendanceMode = 'TOWING';
        $serviceTypeIdPost = (int)($_POST['service_type_id'] ?? 0);
        if ($serviceTypeIdPost > 0) {
            $tipoServico = ServiceType::buscarPorId($serviceTypeIdPost);
            if ($tipoServico && !empty($tipoServico['active'])) {
                $serviceTypeId = (int)$tipoServico['id'];
                $attendanceMode = (string)($tipoServico['attendance_mode'] ?? 'TOWING');
            }
        }
        if ($attendanceMode !== 'TOWING' && $endDestino === '') {
            $endDestino = $endOrigem;
            $latDestino = $latOrigem;
            $lngDestino = $lngOrigem;
        }
        if ($attendanceMode !== 'TOWING' && $serviceTypeId) {
            $pricing = EspecialistaPricingService::calcular((string)($tipoServico['code'] ?? ''), $dist);
            $custo = $pricing ? (float)$pricing['customer_amount'] : TarifaService::calcular($dist, $categoriaTarifa);
        } else {
            $custo = TarifaService::calcular($dist, $categoriaTarifa);
        }
        $veiculoBatido  = isset($_POST['veiculo_esta_batido']) ? (int)!!$_POST['veiculo_esta_batido'] : null;
        $rodasTravadas  = isset($_POST['rodas_travadas']) ? (int)!!$_POST['rodas_travadas'] : null;
        $dificilAcesso  = isset($_POST['local_dificil_acesso']) ? (int)!!$_POST['local_dificil_acesso'] : null;
        $garagemSubsolo = isset($_POST['em_garagem_subsolo']) ? (int)!!$_POST['em_garagem_subsolo'] : null;

        $pedidoService = new PedidoService();
        $paymentRequired = $pedidoService->pagamentoObrigatorio();
        $statusInicial = $pedidoService->statusInicialPedido();

        $stmt = $pdo->prepare(
            "INSERT INTO pedidos (cliente_id,veiculo_id,guincho_id,tipo_problema,descricao_problema,
              lat_origem,lng_origem,endereco_origem,lat_destino,lng_destino,endereco_destino,
              distancia_km,custo_estimado,status,service_type_id,attendance_mode,
              veiculo_esta_batido,rodas_travadas,local_dificil_acesso,em_garagem_subsolo,criado_em)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
        );
        $stmt->execute([
            $clienteId, $veiculoId, $guinchoId, $tipo, $descricao,
            $latOrigem, $lngOrigem, $endOrigem,
            $latDestino, $lngDestino, $endDestino,
            $dist, $custo, $statusInicial,
            $serviceTypeId, $attendanceMode,
            $veiculoBatido, $rodasTravadas, $dificilAcesso, $garagemSubsolo,
        ]);
        $pedidoId = (int)$pdo->lastInsertId();

        // Etapa 15 — snapshot veicular/situacional do pedido, igual ao fluxo
        // do cliente, para a compatibilidade prestador×veículo funcionar
        // também em pedidos abertos pelo admin.
        if (is_array($veiculo)) {
            try {
                require_once __DIR__ . '/../Services/Dispatch/OrderVehicleRequirementService.php';
                OrderVehicleRequirementService::registrar($pedidoId, $veiculo, [
                    'batido' => $veiculoBatido,
                    'rodas_travadas' => $rodasTravadas,
                    'dificil_acesso' => $dificilAcesso,
                    'garagem_subsolo' => $garagemSubsolo,
                ]);
            } catch (\Throwable $e) {
                error_log('[AdminPedidoCriar] snapshot veicular falhou p/ pedido ' . $pedidoId . ': ' . $e->getMessage());
            }
        }

        if ($statusInicial === 'aguardando_guincho') {
            $expMin = (int)($cfg['tempo_expiracao_min'] ?? 5);
            $raioInicial = (int)($cfg['raio_inicial_km'] ?? 10);
            Pedido::definirExpiracao($pedidoId, date('Y-m-d H:i:s', strtotime("+{$expMin} minutes")), $raioInicial);
        }

        // Se guincho foi selecionado e não precisa de pagamento, já atribui pela máquina de estados
        if ($guinchoId && !$paymentRequired) {
            $actorId = (int)($_SESSION['usuario_id'] ?? 0);
            $assign = PedidoTransitionService::assignByAdmin($pedidoId, (int)$guinchoId, $actorId);
            if (!$assign->ok) {
                Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'pedido_criar_assign', (string)$assign->error, [
                    'pedido_id' => $pedidoId,
                    'guincho_id' => $guinchoId,
                ]);
            }
        }

        $this->redirect("/admin/pedido/{$pedidoId}?criado=1");
    }

    public function pedidoCalcularCusto(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=UTF-8');

        $distancia = (float)($_GET['distancia_km'] ?? 0);
        if ($distancia <= 0) {
            echo json_encode(['ok' => false, 'erro' => 'Distância inválida'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once __DIR__ . '/../Models/Veiculo.php';
        require_once __DIR__ . '/../Services/TarifaService.php';

        $categoria = trim((string)($_GET['categoria'] ?? ''));
        $veiculoId = (int)($_GET['veiculo_id'] ?? 0);
        if ($veiculoId > 0) {
            $veiculo = Veiculo::buscarPorId($veiculoId);
            if (!$veiculo) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'erro' => 'Veículo não encontrado'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $categoria = $categoria !== '' ? $categoria : TarifaService::categoriaDeVeiculo($veiculo);
        }

        $serviceTypeId = (int)($_GET['service_type_id'] ?? 0);
        if ($serviceTypeId > 0) {
            $serviceType = ServiceType::buscarPorId($serviceTypeId);
            if ($serviceType && (int)$serviceType['active'] === 1 && (string)$serviceType['attendance_mode'] !== 'TOWING') {
                $pricing = EspecialistaPricingService::calcular((string)$serviceType['code'], $distancia);
                if ($pricing) {
                    echo json_encode(['ok'=>true,'custo'=>(float)$pricing['customer_amount'],'distancia'=>$distancia,'origem'=>'especialista_catalogo','tarifa'=>['tipo'=>'especialista','codigo'=>$pricing['codigo'],'detalhe'=>$pricing['detalhe'],'provider_amount'=>$pricing['provider_amount'],'platform_amount'=>$pricing['platform_amount']]], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }

        $prioridade = (($_GET['prioridade'] ?? '0') === '1');
        $detalhe = TarifaService::calcularDetalhado($distancia, $categoria, $prioridade);

        echo json_encode([
            'ok' => true,
            'custo' => (float)$detalhe['valor'],
            'distancia' => (float)$detalhe['distancia_km'],
            'tarifa' => $detalhe,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─── FINANCEIRO ──────────────────────────────────────────────
    public function financeiro(): void
    {
        AuthService::requireAuth('admin');
        $bounds = Pagamento::periodBounds();
        $defaultInicio = $bounds['min_date'] ?: date('Y-m-01');
        $defaultFim = $bounds['max_date'] ?: date('Y-m-d');
        $dataInicio = $_GET['inicio'] ?? $defaultInicio;
        $dataFim    = $_GET['fim']    ?? $defaultFim;
        $pagina     = max(1, (int)($_GET['pagina'] ?? 1));
        $filtrosPagamento = [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'status' => trim((string)($_GET['status'] ?? '')),
            'metodo' => trim((string)($_GET['metodo'] ?? '')),
        ];
        $filtrosJobs = [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'status' => trim((string)($_GET['job_status'] ?? '')),
            'job_type' => trim((string)($_GET['job_type'] ?? '')),
            'pedido_id' => (int)($_GET['pedido_id'] ?? 0),
            'worker_id' => trim((string)($_GET['worker_id'] ?? '')),
        ];
        if ($filtrosJobs['pedido_id'] <= 0) {
            $filtrosJobs['pedido_id'] = '';
        }

        $pagamentos = Pagamento::listar($filtrosPagamento, $pagina, 20);
        $totalPagamentos = Pagamento::contar($filtrosPagamento);
        $totalPaginas = max(1, (int)ceil($totalPagamentos / 20));
        $totais     = Pagamento::totalPorPeriodo($dataInicio, $dataFim);
        $paymentStatusBreakdown = Pagamento::statusBreakdown($filtrosPagamento);
        $paymentMethodBreakdown = Pagamento::methodBreakdown($filtrosPagamento);
        $paymentApprovedSeries = Pagamento::approvedSeries($dataInicio, $dataFim);
        $paymentInsights = Pagamento::adminInsights($dataInicio, $dataFim);
        $topGuinchos = Pagamento::topGuinchos($dataInicio, $dataFim, 5);
        $topClientes = Pagamento::topClientes($dataInicio, $dataFim, 5);
        $paymentJobStats = PaymentJobService::summarize($filtrosJobs);
        $paymentJobs = PaymentJobService::list($filtrosJobs, 50);
        $cfg = Configuracao::getAll();
        $systemMode = (string)($cfg['system_mode'] ?? 'production');
        $csrfToken = AuthService::gerarCsrfToken();
        $queryBase = array_filter([
            'inicio' => $dataInicio,
            'fim' => $dataFim,
            'status' => $filtrosPagamento['status'],
            'metodo' => $filtrosPagamento['metodo'],
            'job_status' => $filtrosJobs['status'],
            'job_type' => $filtrosJobs['job_type'],
            'pedido_id' => $filtrosJobs['pedido_id'],
            'worker_id' => $filtrosJobs['worker_id'],
        ], static fn ($value): bool => $value !== '' && $value !== null);
        require __DIR__ . '/../Views/admin/financeiro.php';
    }

    public function paymentJobRetry(int $jobId): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $actorId = (int)($_SESSION['usuario_id'] ?? 0);
        $result = PaymentJobService::forceRetry($jobId, $actorId);
        $redirectTo = $this->sanitizeAdminRedirectPath((string)($_POST['redirect_to'] ?? ''));

        if ($result['ok'] ?? false) {
            $this->redirect($this->appendQueryParam($redirectTo ?: '/admin/financeiro', 'msg', 'payment_job_reenfileirado'));
        }

        $message = trim((string)($result['erro'] ?? 'Erro ao reenfileirar payment job.'));
        $this->redirect($this->appendQueryParam(
            $redirectTo ?: '/admin/financeiro',
            'msg',
            'payment_job_retry_falha'
        ) . '&job_error=' . rawurlencode($message));
    }

    public function exportarCsv(): void
    {
        AuthService::requireAuth('admin');
        $bounds = Pagamento::periodBounds();
        $dataInicio = $_GET['inicio'] ?? ($bounds['min_date'] ?: date('Y-m-01'));
        $dataFim    = $_GET['fim']    ?? ($bounds['max_date'] ?: date('Y-m-d'));
        $filtrosPagamento = [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'status' => trim((string)($_GET['status'] ?? '')),
            'metodo' => trim((string)($_GET['metodo'] ?? '')),
        ];
        $pagamentos = Pagamento::listar($filtrosPagamento, 1, 9999);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="financeiro_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['ID', 'Pedido', 'Método', 'Valor Total', 'Valor Guincho', 'Comissão', 'Status', 'Data'], ';');
        foreach ($pagamentos as $p) {
            fputcsv($out, [
                $p['id'], $p['pedido_id'], $p['metodo_normalizado'] ?? $p['metodo'],
                number_format($p['valor_total'],     2, ',', '.'),
                number_format($p['valor_guincho'],   2, ',', '.'),
                number_format($p['valor_plataforma'],2, ',', '.'),
                $p['status'], $p['data_pagamento'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ─── CONFIGURAÇÕES ───────────────────────────────────────────
    public function configuracoes(): void
    {
        // Explicitly check for admin role and handle authorization
        $currentUser = AuthService::getCurrentUser();
        $isAdmin = $currentUser && $currentUser['tipo'] === 'admin';

        if (!$isAdmin) {
            // Redirect to login if not admin or not logged in
            // Use a query parameter to indicate the authorization issue
            header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/login?auth_error=403');
            exit;
        }

        // If authorized, proceed with loading configurations
        $config    = Configuracao::getAll();
        $envPathAtivo = $this->managedEnvPath();
        $envArquivoAtivo = basename($envPathAtivo);
        $envAtual  = $this->parseEnvFile($envPathAtivo);
        $csrfToken = AuthService::gerarCsrfToken();
        // §PRECO-POR-CIDADE-01: seletor opcional de cidade-alvo pra editar
        // as tarifas de reboque ESPECÍFICAS daquela cidade em vez da global
        // — mesmo padrão de UX já usado em /admin/planejamento.
        require_once __DIR__ . '/../Models/Cidade.php';
        $cidadesAtivas = Cidade::listarAtivas();
        $cidadeIdConfig = (int)($_GET['cidade_id'] ?? 0);
        $configGlobal = $config;
        if ($cidadeIdConfig > 0) {
            foreach (self::TARIFA_CAMPOS_POR_CIDADE as $campo) {
                $chaveCidade = $this->chaveConfigCidade($campo, $cidadeIdConfig);
                if (isset($config[$chaveCidade]) && $config[$chaveCidade] !== '') {
                    $config[$campo] = $config[$chaveCidade];
                }
            }
        }
        require __DIR__ . '/../Views/admin/configuracoes.php';
    }

    /** Campos de tarifa de reboque que podem ter override por cidade-alvo (ver TarifaService::cfgPorCidade). */
    private const TARIFA_CAMPOS_POR_CIDADE = [
        'tarifa_por_km', 'taxa_fixa',
        'tarifa_noturna_km', 'tarifa_noturna_fixa',
        'tarifa_feriado_km', 'tarifa_feriado_fixa',
        'tarifa_suv_km', 'tarifa_suv_fixa',
        'tarifa_caminhonete_km', 'tarifa_caminhonete_fixa',
        'tarifa_eletrico_km', 'tarifa_eletrico_fixa',
        'tarifa_moto_km', 'tarifa_moto_fixa',
        'taxa_prioridade_valor', 'taxa_prioridade',
    ];

    /** Mesma convenção `{chave}__cidade_{id}` de AdminPlanejamentoController::chaveCidade() / TarifaService::chaveCidade(). */
    private function chaveConfigCidade(string $chave, int $cidadeId): string
    {
        return $cidadeId > 0 ? $chave . '__cidade_' . $cidadeId : $chave;
    }

    public function configuracoesSalvar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }

        $cidadeIdConfig = (int)($_POST['cidade_id'] ?? 0);

        // --- CONFIGURAÇÕES DE TARIFAS ---
        $campos = [
            "tarifa_por_km", "taxa_fixa", "comissao_plataforma",
            "tarifa_noturna_km", "tarifa_noturna_fixa", "taxa_prioridade",
            "turno_noturno_inicio", "turno_noturno_fim",
            "tempo_expiracao_min", "raio_inicial_km", "raio_maximo_km",
            // Rotação automática de gateway (GatewayRotationService) — só
            // tem efeito quando PAYMENT_GATEWAY_ACTIVE é um único gateway
            // (não 'todos'); ver §16 da constituição.
            "gateway_rotacao_limite_diario",
            // §A6 — adicional de feriado (empilha com o noturno) e tarifas
            // por categoria de veículo (antes só existiam as chaves de
            // popular/base; suv/eletrico/caminhonete não eram configuráveis).
            "tarifa_feriado_km", "tarifa_feriado_fixa",
            "tarifa_suv_km", "tarifa_suv_fixa",
            "tarifa_caminhonete_km", "tarifa_caminhonete_fixa",
            "tarifa_eletrico_km", "tarifa_eletrico_fixa",
            "tarifa_moto_km", "tarifa_moto_fixa",
            // §SPLIT-LIQUIDO-01 — reserva de gateway (descontada do bruto
            // antes de comissao_plataforma incidir, ver
            // PedidoTransitionService::approvePayment) e crédito de
            // conversão pane→reboque (ver ConversionService).
            "reserva_gateway_percentual",
            "credito_conversao_percentual", "credito_conversao_maximo",
            "marketing_tracking_enabled", "marketing_google_ads_id",
            "marketing_google_ads_conversion_label", "marketing_ga4_measurement_id",
            "marketing_meta_pixel_id",
        ];
        // §PRECO-POR-CIDADE-01: quando o admin está editando com uma
        // cidade-alvo selecionada, os campos de TARIFA vão pra chave
        // segmentada (`{chave}__cidade_{id}`) em vez da global — o resto
        // (comissão, raio de despacho, expiração, gateway...) continua
        // sendo sempre global, não faz sentido por cidade.
        foreach ($campos as $c) {
            if (!isset($_POST[$c])) {
                continue;
            }
            if ($cidadeIdConfig > 0 && in_array($c, self::TARIFA_CAMPOS_POR_CIDADE, true)) {
                Configuracao::set($this->chaveConfigCidade($c, $cidadeIdConfig), $_POST[$c]);
            } else {
                Configuracao::set($c, $_POST[$c]);
            }
        }
        // --- NOVAS CONFIGURAÇÕES: SYSTEM_MODE e PAYMENT_REQUIRED ---
        // System Mode
        $modesValidos = ['production', 'sandbox', 'freeflow'];
        $systemMode = (string)Configuracao::get('system_mode', 'production');
        if (array_key_exists('system_mode', $_POST) && in_array((string)$_POST['system_mode'], $modesValidos, true)) {
            $systemMode = (string)$_POST['system_mode'];
            Configuracao::set('system_mode', $systemMode);
        }

        // Payment Required
        $paymentRequired = isset($_POST['payment_required']) && $_POST['payment_required'] == '1' ? '1' : '0';
        Configuracao::set('payment_required', $paymentRequired);

        // Modo de debug global (observabilidade cross-cutting, ver DebugMode.php)
        $debugModeAtivo = isset($_POST['debug_mode_ativo']) && $_POST['debug_mode_ativo'] == '1' ? '1' : '0';
        Configuracao::set('debug_mode_ativo', $debugModeAtivo);
        DebugMode::resetCache();

        // --- GATEWAY DE PAGAMENTO ---
        $envUpdates = [];
        if (isset($_POST["gateway_pagamento"]) && in_array($_POST["gateway_pagamento"], ["mercadopago", "pagseguro"], true)) {
            $envUpdates["PAYMENT_GATEWAY_ACTIVE"] = $_POST["gateway_pagamento"];
            Configuracao::set("gateway_pagamento", $_POST["gateway_pagamento"]);
        }

        $envCampos = [
            "MP_ACCESS_TOKEN", "MP_PUBLIC_KEY", "MP_WEBHOOK_SECRET", "MP_ENV",
            "MP_ACCESS_TOKEN_SANDBOX", "MP_ACCESS_TOKEN_PROD",
            "MP_PUBLIC_KEY_SANDBOX", "MP_PUBLIC_KEY_PROD",
            "MP_CLIENT_ID_PROD", "MP_CLIENT_SECRET_PROD",
            "PS_EMAIL", "PS_TOKEN", "PS_ENV"
        ];
        foreach ($envCampos as $chave) {
            if (array_key_exists($chave, $_POST)) {
                $envUpdates[$chave] = trim((string)$_POST[$chave]);
            }
        }

        if (!empty($envUpdates) && !$this->updateEnvValues($this->managedEnvPath(), $envUpdates)) {
            Logger::log(Logger::LEVEL_ERROR, "AdminController", "configuracoesSalvar", "env", "Falha ao gravar .env", ["keys" => array_keys($envUpdates)]);
            $this->redirect("/admin/configuracoes?erro=env_write" . ($cidadeIdConfig > 0 ? '&cidade_id=' . $cidadeIdConfig : ''));
        }

        Logger::log(Logger::LEVEL_INFO, "AdminController", "configuracoesSalvar", "admin",
            "Configurações salvas", [
                'env_keys' => array_keys($envUpdates),
                'system_mode' => $systemMode,
                'payment_required' => $paymentRequired,
                'cidade_id_tarifas' => $cidadeIdConfig ?: null,
            ]
        );
        // §PRECO-POR-CIDADE-01: se o admin tinha uma cidade-alvo selecionada
        // no seletor de tarifas, volta pra mesma cidade selecionada (senão
        // a tela recarrega em branco/global e parece que "sumiu" o que
        // acabou de editar).
        $this->redirect("/admin/configuracoes?salvo=1" . ($cidadeIdConfig > 0 ? '&cidade_id=' . $cidadeIdConfig : ''));
    }

    // ─── LOGS ────────────────────────────────────────────────────
    public function logs(): void
    {
        AuthService::requireAuth('admin');
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = 25;
        $pdo    = getPDO();
        $offset = ($pagina - 1) * $porPagina;

        $filtros = [
            'periodo_inicio' => trim((string)($_GET['periodo_inicio'] ?? '')),
            'periodo_fim' => trim((string)($_GET['periodo_fim'] ?? '')),
            'level' => trim((string)($_GET['level'] ?? '')),
            'system' => trim((string)($_GET['system'] ?? '')),
            'class' => trim((string)($_GET['class'] ?? '')),
            'function' => trim((string)($_GET['function'] ?? '')),
            'file' => trim((string)($_GET['file'] ?? '')),
            'phase' => trim((string)($_GET['phase'] ?? '')),
            'code' => trim((string)($_GET['code'] ?? '')),
            'request_id' => trim((string)($_GET['request_id'] ?? '')),
            'run_id' => trim((string)($_GET['run_id'] ?? '')),
            'pedido_id' => trim((string)($_GET['pedido_id'] ?? '')),
            'usuario_id' => trim((string)($_GET['usuario_id'] ?? '')),
            'guincho_id' => trim((string)($_GET['guincho_id'] ?? '')),
            'texto' => trim((string)($_GET['texto'] ?? '')),
        ];

        $where = [];
        $params = [];
        $matchConfig = [
            'level' => ['column' => 'level', 'type' => 'exact'],
            'system' => ['column' => 'system', 'type' => 'exact'],
            'class' => ['column' => 'cls', 'type' => 'like'],
            'function' => ['column' => 'func', 'type' => 'like'],
            'file' => ['column' => 'file', 'type' => 'like'],
            'phase' => ['column' => 'phase', 'type' => 'like'],
            'code' => ['column' => 'code', 'type' => 'exact'],
            'request_id' => ['column' => 'request_id', 'type' => 'exact'],
            'run_id' => ['column' => 'run_id', 'type' => 'exact'],
            'pedido_id' => ['column' => 'pedido_id', 'type' => 'int'],
            'usuario_id' => ['column' => 'usuario_id', 'type' => 'int'],
            'guincho_id' => ['column' => 'guincho_id', 'type' => 'int'],
        ];

        foreach ($matchConfig as $key => $cfg) {
            $value = $filtros[$key];
            if ($value === '') {
                continue;
            }
            if ($cfg['type'] === 'exact') {
                $where[] = $cfg['column'] . " = :" . $key;
                $params[":" . $key] = $value;
                continue;
            }
            if ($cfg['type'] === 'like') {
                $where[] = $cfg['column'] . " LIKE :" . $key;
                $params[":" . $key] = '%' . $value . '%';
                continue;
            }
            if ($cfg['type'] === 'int' && ctype_digit($value)) {
                $where[] = $cfg['column'] . " = :" . $key;
                $params[":" . $key] = (int)$value;
            }
        }

        if ($filtros['periodo_inicio'] !== '') {
            $where[] = "criado_em >= :periodo_inicio";
            $params[':periodo_inicio'] = $filtros['periodo_inicio'] . ' 00:00:00';
        }
        if ($filtros['periodo_fim'] !== '') {
            $where[] = "criado_em <= :periodo_fim";
            $params[':periodo_fim'] = $filtros['periodo_fim'] . ' 23:59:59';
        }
        if ($filtros['texto'] !== '') {
            $where[] = "(msg LIKE :texto OR ctx_json LIKE :texto OR uri LIKE :texto OR ip LIKE :texto)";
            $params[':texto'] = '%' . $filtros['texto'] . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $appLogs = [];
        $appTotal = 0;
        $stats = [
            'errors' => 0,
            'warns' => 0,
            'requests' => 0,
            'runs' => 0,
        ];
        try {
            $stmt = $pdo->prepare("SELECT * FROM app_logs {$whereSql} ORDER BY criado_em DESC LIMIT :limit OFFSET :o");
            foreach ($params as $name => $value) {
                $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
            $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $appLogs  = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM app_logs {$whereSql}");
            foreach ($params as $name => $value) {
                $stmtCount->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmtCount->execute();
            $appTotal = (int)$stmtCount->fetchColumn();

            $stmtStats = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN level = 'ERROR' THEN 1 ELSE 0 END) AS errors,
                    SUM(CASE WHEN level = 'WARN' THEN 1 ELSE 0 END) AS warns,
                    COUNT(DISTINCT request_id) AS requests,
                    COUNT(DISTINCT run_id) AS runs
                FROM app_logs
                {$whereSql}
            ");
            foreach ($params as $name => $value) {
                $stmtStats->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmtStats->execute();
            $statsRow = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats = [
                'errors' => (int)($statsRow['errors'] ?? 0),
                'warns' => (int)($statsRow['warns'] ?? 0),
                'requests' => (int)($statsRow['requests'] ?? 0),
                'runs' => (int)($statsRow['runs'] ?? 0),
            ];
        } catch (Throwable $e) { $appLogs = []; $appTotal = 0; }

        $webhookLogs = [];
        $webhookTotal = 0;
        try {
            $stmt2 = $pdo->prepare("SELECT * FROM logs_webhook ORDER BY criado_em DESC LIMIT :limit OFFSET :o");
            $stmt2->bindValue(':limit', $porPagina, PDO::PARAM_INT);
            $stmt2->bindValue(':o', $offset, PDO::PARAM_INT);
            $stmt2->execute();
            $webhookLogs  = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $webhookTotal = (int)$pdo->query("SELECT COUNT(*) FROM logs_webhook")->fetchColumn();
        } catch (Throwable $e) { $webhookLogs = []; $webhookTotal = 0; }

        $logDir = defined('LOG_DIR') ? (string)LOG_DIR : (dirname(__DIR__, 2) . '/logs');
        $logFile = $logDir . '/app-' . date('Y-m-d') . '.jsonl';
        if (!is_file($logFile)) {
            $candidates = glob($logDir . '/app-*.jsonl') ?: [];
            rsort($candidates);
            if (!empty($candidates)) {
                $logFile = (string)$candidates[0];
            } else {
                $logFile = $logDir . '/app.log';
            }
        }
        $fileTail = [];
        if (is_file($logFile)) {
            $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
            if (is_array($lines)) $fileTail = array_slice($lines, -200);
        }
        $totalPaginas = max(1, (int)ceil($appTotal / $porPagina));
        $queryBase = $_GET;
        unset($queryBase['pagina']);
        require __DIR__ . '/../Views/admin/logs.php';
    }

    // ─── CHAT (visualização pelo admin) ─────────────────────────
    public function chat(): void
    {
        // Ponte de compatibilidade para chamadas internas e integrações antigas.
        (new AdminChatController())->index();
    }

    // ─── PEDIDO: Alterar Status, Cancelar, Atribuir ─────────────
    public function pedidoAlterarStatus(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $pedidoId = (int)($_POST["pedido_id"] ?? 0);
        $status   = $_POST["status"] ?? "";
        if ($pedidoId && $status !== '') {
            $usuario = AuthService::getCurrentUser();
            $result = PedidoTransitionService::transition(new PedidoTransitionRequest(
                'admin',
                (int)$usuario['id'],
                $pedidoId,
                $status,
                null,
                ['admin_override_reason' => 'admin_manual_transition']
            ));
            if (!$result->ok) {
                $_SESSION['_flash'][] = ['message' => (string)$result->error, 'type' => 'error'];
                $this->redirect("/admin/pedido/{$pedidoId}");
            }
        }
        $this->redirect("/admin/pedido/{$pedidoId}?msg=status_atualizado");
    }

    public function pedidoCancelar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }

        $pedidoId     = (int)($_POST["pedido_id"] ?? 0);
        $justificativa = trim($_POST["justificativa"] ?? '');
        $senha        = $_POST["senha"] ?? '';

        if (!$pedidoId || empty($justificativa) || empty($senha)) {
            $_SESSION['_flash'][] = ['message' => 'Dados incompletos.', 'type' => 'error'];
            $this->redirect("/admin/pedido/{$pedidoId}");
        }

        // Validar senha do Admin
        $usuario = AuthService::getCurrentUser();
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($senha, $row['senha_hash'])) {
            $_SESSION['_flash'][] = ['message' => 'Senha incorreta.', 'type' => 'error'];
            $this->redirect("/admin/pedido/{$pedidoId}");
        }

        $result = PedidoTransitionService::cancelByAdmin($pedidoId, (int)$usuario['id'], $justificativa);
        if ($result->ok) {
            // Log de auditoria
            Logger::log(Logger::LEVEL_INFO, 'AdminController', 'pedidoCancelar', 'admin', 
                "Pedido #{$pedidoId} cancelado por admin {$usuario['id']}. Justificativa: {$justificativa}");

            $_SESSION['_flash'][] = ['message' => 'Pedido cancelado com sucesso.', 'type' => 'success'];
        } else {
            $_SESSION['_flash'][] = ['message' => 'Falha ao cancelar pedido.', 'type' => 'error'];
        }

        $this->redirect("/admin/pedido/{$pedidoId}");
    }


    public function pedidoAtribuir(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $pedidoId  = (int)($_POST["pedido_id"] ?? 0);
        $guinchoId = (int)($_POST["guincho_id"] ?? 0);
        // Tela de Despacho (Pacote L2.3) reaproveita esta mesma action; quando
        // a atribuição vem de lá, o admin espera voltar pra fila de despacho
        // (não pro detalhe do pedido) após atribuir. Padrão antigo (redirect
        // pro detalhe do pedido) permanece intacto quando 'retorno' não vem.
        $retorno = (string)($_POST['retorno'] ?? '');
        $destinoOk = $retorno === 'despacho' ? "/admin/despacho?msg=guincho_atribuido" : "/admin/pedido/{$pedidoId}?msg=guincho_atribuido";
        $destinoErro = $retorno === 'despacho' ? "/admin/despacho?pedido_id={$pedidoId}" : "/admin/pedido/{$pedidoId}";
        if ($pedidoId && $guinchoId) {
            $usuario = AuthService::getCurrentUser();
            $result = PedidoTransitionService::assignByAdmin($pedidoId, $guinchoId, (int)$usuario['id']);
            if (!$result->ok) {
                $_SESSION['_flash'][] = ['message' => (string)$result->error, 'type' => 'error'];
                $this->redirect($destinoErro);
            }
        }
        $this->redirect($destinoOk);
    }

    /**
     * Despacho manual (Pacote L2.3 — item "Despacho" da nav reorganizada,
     * antes marcado "em breve"). Sem schema novo: reaproveita
     * Guincho::listarAprovados() (aprovados + disponíveis) e ordena por
     * distância real (Haversine, GeoService::haversine) até a origem do
     * pedido selecionado. A ação de atribuir reaproveita 100% a action já
     * existente (pedidoAtribuir/PedidoTransitionService::assignByAdmin) —
     * nenhuma regra de negócio de atribuição foi duplicada ou reescrita.
     */
    public function despacho(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Services/GeoService.php';

        $filas = Pedido::listarPorStatus('aguardando_guincho', 1, [], 50);

        $pedidoSelecionadoId = (int)($_GET['pedido_id'] ?? 0);
        $pedidoSelecionado = null;
        if ($pedidoSelecionadoId) {
            $pedidoSelecionado = Pedido::buscarPorId($pedidoSelecionadoId);
            // só faz sentido despachar pedidos ainda aguardando prestador
            if ($pedidoSelecionado && (string)($pedidoSelecionado['status'] ?? '') !== 'aguardando_guincho') {
                $pedidoSelecionado = null;
            }
        }
        if (!$pedidoSelecionado && !empty($filas)) {
            $pedidoSelecionado = Pedido::buscarPorId((int)$filas[0]['id']);
        }

        $prestadores = Guincho::listarAprovados();
        if ($pedidoSelecionado) {
            $modoAtendimento = (string)($pedidoSelecionado['attendance_mode'] ?? 'TOWING');
            $serviceTypeId = (int)($pedidoSelecionado['service_type_id'] ?? 0);
            if ($modoAtendimento !== 'TOWING' && $serviceTypeId > 0) {
                require_once __DIR__ . '/../Models/Catalog/ProviderCapability.php';
                $elegiveis = ProviderCapability::listarPrestadoresAprovados($serviceTypeId);
                $idsElegiveis = array_fill_keys(array_map(static fn(array $p): int => (int)$p['provider_id'], $elegiveis), true);
                $prestadores = array_values(array_filter($prestadores, static fn(array $p): bool => isset($idsElegiveis[(int)$p['id']])));
            }
            $latOrigem = isset($pedidoSelecionado['lat_origem']) ? (float)$pedidoSelecionado['lat_origem'] : null;
            $lngOrigem = isset($pedidoSelecionado['lng_origem']) ? (float)$pedidoSelecionado['lng_origem'] : null;
            foreach ($prestadores as &$pr) {
                $latG = isset($pr['lat_atual']) && $pr['lat_atual'] !== null ? (float)$pr['lat_atual'] : null;
                $lngG = isset($pr['lng_atual']) && $pr['lng_atual'] !== null ? (float)$pr['lng_atual'] : null;
                if ($latOrigem !== null && $lngOrigem !== null && $latG !== null && $lngG !== null) {
                    $pr['distancia_km'] = round(GeoService::haversine($latOrigem, $lngOrigem, $latG, $lngG), 1);
                } else {
                    $pr['distancia_km'] = null; // sem GPS recente — não pode ser ordenado por distância
                }
            }
            unset($pr);
            usort($prestadores, static function (array $a, array $b): int {
                // sem GPS vai pro fim da lista, não pro topo (evita sugerir
                // prestador "mais próximo" sem sinal de posição real)
                if ($a['distancia_km'] === null && $b['distancia_km'] === null) return 0;
                if ($a['distancia_km'] === null) return 1;
                if ($b['distancia_km'] === null) return -1;
                return $a['distancia_km'] <=> $b['distancia_km'];
            });
        }

        $csrfToken = AuthService::gerarCsrfToken();

        require __DIR__ . '/../Views/admin/despacho.php';
    }

    // ─── PEDIDO: Conclusão manual assistida (GPS/servidor indisponível) ──
    // Salvaguarda para quando o rastreamento do cliente/guincho ou o próprio
    // servidor falha no meio do atendimento — sem isso o pedido ficava preso
    // para sempre (pedidoAlterarStatus() aplica geofence/evidência também
    // para admin, sem bypass). Ver migration_conclusao_manual_v1.sql.
    public function pedidoConcluirManual(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $pedidoId = (int)($_POST['pedido_id'] ?? 0);
        $justificativa = trim((string)($_POST['justificativa'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');

        if (!$pedidoId || $justificativa === '' || $senha === '') {
            $_SESSION['_flash'][] = ['message' => 'Dados incompletos: justificativa e senha são obrigatórias.', 'type' => 'error'];
            $this->redirect("/admin/pedido/{$pedidoId}");
        }

        $usuario = AuthService::getCurrentUser();
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($senha, $row['senha_hash'])) {
            $_SESSION['_flash'][] = ['message' => 'Senha incorreta.', 'type' => 'error'];
            $this->redirect("/admin/pedido/{$pedidoId}");
        }

        $comprovantes = [];
        foreach (['coleta', 'entrega'] as $tipo) {
            $campo = 'comprovante_' . $tipo;
            if (isset($_FILES[$campo]) && ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $comprovantes[] = [
                    'tipo' => $tipo,
                    'file' => $_FILES[$campo],
                    'lat' => isset($_POST['lat_' . $tipo]) && $_POST['lat_' . $tipo] !== '' ? (float)$_POST['lat_' . $tipo] : null,
                    'lng' => isset($_POST['lng_' . $tipo]) && $_POST['lng_' . $tipo] !== '' ? (float)$_POST['lng_' . $tipo] : null,
                ];
            }
        }

        $result = PedidoTransitionService::concludeManuallyByAdmin($pedidoId, (int)$usuario['id'], $justificativa, $comprovantes);
        if ($result->ok) {
            Logger::log(Logger::LEVEL_WARN, 'AdminController', 'pedidoConcluirManual', 'admin',
                "Pedido #{$pedidoId} concluído MANUALMENTE por admin {$usuario['id']} (GPS/servidor indisponível). Justificativa: {$justificativa}");
            $_SESSION['_flash'][] = ['message' => 'Pedido concluído manualmente. Fica pendente de revisão de auditoria.', 'type' => 'success'];
        } else {
            $_SESSION['_flash'][] = ['message' => (string)$result->error, 'type' => 'error'];
        }

        $this->redirect("/admin/pedido/{$pedidoId}");
    }

    public function pedidoRevisarManual(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $pedidoId = (int)($_POST['pedido_id'] ?? 0);
        $veredito = (string)($_POST['veredito'] ?? '');
        $nota = trim((string)($_POST['nota'] ?? ''));
        $usuario = AuthService::getCurrentUser();

        $result = PedidoTransitionService::revisarConclusaoManual($pedidoId, (int)$usuario['id'], $veredito, $nota);
        $_SESSION['_flash'][] = $result->ok
            ? ['message' => 'Revisão registrada: ' . $veredito . '.', 'type' => 'success']
            : ['message' => (string)$result->error, 'type' => 'error'];

        $this->redirect("/admin/pedido/{$pedidoId}");
    }

    public function pixReprocessar(int $pedidoId): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $result = PixService::reprocessar($pedidoId);
        $msg = !empty($result['sucesso']) ? 'pix_reprocessado' : 'pix_falha';
        // Tela de Saques (Pacote L2.3) reaproveita esta mesma action; quando
        // o reprocessamento vem de lá, volta pra lá em vez do detalhe do
        // pedido — comportamento antigo preservado quando 'retorno' não vem.
        if ((string)($_POST['retorno'] ?? '') === 'saques') {
            $this->redirect("/admin/saques?msg={$msg}");
            return;
        }
        $this->redirect("/admin/pedido/{$pedidoId}?msg={$msg}");
    }

    // ─── USUÁRIO: Editar ──────────────────────────────────────────
    public function usuarioEditar(int $id): void
    {
        AuthService::requireAuth("admin");
        $usuario = Usuario::buscarPorId($id);
        if (!$usuario) { http_response_code(404); echo "<h1>404</h1>"; exit; }
        $extra = null;
        if ($usuario["tipo"] === "guincho") {
            $extra = Guincho::buscarPorUsuario($id);
        }

        // Ficha 360°: relações existentes reunidas sem duplicar cadastros.
        $veiculosAssociados = Veiculo::listarPorUsuario($id);
        $oficinasAssociadas = Oficina::listarPorUsuario($id);
        $pedidosClienteAssociados = $usuario['tipo'] === 'cliente'
            ? Pedido::listarPorCliente($id, 1, 50) : [];
        $pedidosPrestadorAssociados = [];
        $pagamentosAssociados = [];
        $avaliacoesAssociadas = [];
        $capacidadesAssociadas = [];
        $logsAssociados = [];
        try {
            $pdo = getPDO();
            if (!empty($extra['id'])) {
                $pedidosPrestadorAssociados = Pedido::listarPorGuincho((int)$extra['id'], 1, 50);
                $pagamentosAssociados = Pagamento::listarPorGuincho((int)$extra['id']);
                $avaliacoesAssociadas = Avaliacao::listarPorGuincho((int)$extra['id']);
                $capacidadesAssociadas = ProviderCapability::listarPorPrestador((int)$extra['id']);
            } elseif ($usuario['tipo'] === 'cliente') {
                $stmt = $pdo->prepare(
                    'SELECT pg.*, p.status AS pedido_status, p.tipo_problema, p.criado_em AS pedido_criado_em
                       FROM pagamentos pg JOIN pedidos p ON p.id = pg.pedido_id
                      WHERE p.cliente_id = ? ORDER BY pg.criado_em DESC, pg.id DESC LIMIT 50'
                );
                $stmt->execute([$id]);
                $pagamentosAssociados = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            $stmt = $pdo->prepare(
                'SELECT criado_em, level, system, code, pedido_id FROM app_logs
                  WHERE usuario_id = ? ORDER BY criado_em DESC, id DESC LIMIT 20'
            );
            $stmt->execute([$id]);
            $logsAssociados = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('AdminController::usuarioEditar relacionamentos: ' . $e->getMessage());
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . "/../Views/admin/usuarioedit.php";
    }

    public function usuarioAtualizar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $id    = (int)($_POST["id"] ?? 0);
        $nome  = trim($_POST["nome"]  ?? "");
        $email = trim($_POST["email"] ?? "");
        $tel   = preg_replace("/\D/", "", $_POST["telefone"] ?? "");
        $cpf   = preg_replace("/\D/", "", $_POST["cpf"] ?? "");
        $ativo = (int)($_POST["ativo"] ?? 1);
        if (!$id || empty($nome) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect("/admin/usuario/editar/{$id}?erro=1");
        }
        $pdo = getPDO();
        $dup = $pdo->prepare("SELECT id FROM usuarios WHERE (email=? OR cpf=?) AND id!=? LIMIT 1");
        $dup->execute([$email, $cpf, $id]);
        if ($dup->fetch()) {
            $this->redirect("/admin/usuario/editar/{$id}?erro=email_duplicado");
        }
        $pdo->prepare("UPDATE usuarios SET nome=?, email=?, telefone=?, cpf=?, ativo=? WHERE id=?")
            ->execute([$nome, $email, $tel, $cpf, $ativo, $id]);
        $this->redirect("/admin/usuario/editar/{$id}?salvo=1");
    }

    public function usuarioSenha(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $id    = (int)($_POST["id"] ?? 0);
        $senha = $_POST["senha"] ?? "";
        $conf  = $_POST["confirmar"] ?? "";
        if (!$id || strlen($senha) < 8 || $senha !== $conf) {
            $this->redirect("/admin/usuario/editar/{$id}?erro=1");
        }
        $hash = password_hash($senha, PASSWORD_BCRYPT);
        getPDO()->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")
                ->execute([$hash, $id]);
        $this->redirect("/admin/usuario/editar/{$id}?salvo=1");
    }

    // ─── GUINCHO: Listar, Form, Criar, Atualizar ─────────────────
    public function guinchos(): void
    {
        AuthService::requireAuth("admin");
        $pendentes = Guincho::listarPendentes();

        // Filtro "Especialistas" (Pacote L2.3 — item da nav reorganizada que
        // estava "em breve"). Não é uma entidade nova: migration_prestador_tipo_v1.sql
        // já modela isso via guinchos.oferece_reboque (0 = especialista tipo
        // chaveiro/elétrica/pneu, sem aprovação de reboque; 1 = guincheiro
        // tradicional). Reaproveita 100% a mesma listagem/tela, só filtrando.
        $tipoFiltro = (string)($_GET['tipo'] ?? '');
        $whereTipo = '';
        if ($tipoFiltro === 'reboque') {
            $whereTipo = ' AND g.oferece_reboque = 1';
        }

        try {
            $stmt = getPDO()->prepare(
                "SELECT g.*, u.nome AS nome_operador, u.email, u.telefone, u.ativo
                 FROM guinchos g JOIN usuarios u ON g.usuario_id = u.id
                 WHERE g.aprovado = 1{$whereTipo} ORDER BY u.nome"
            );
            $stmt->execute();
            $aprovados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $aprovados = []; }

        // §GUINCHOS-SHELL-01: se a página foi aberta com ?guincho_id= (vindo
        // do link "Ver Perfil" da fila de pendentes, ou de um favorito
        // antigo /admin/guincho/{id}) e esse guincho NÃO está na lista de
        // aprovados (ex.: ainda pendente, ou filtrado pelo tipo atual),
        // busca ele à parte e prepende na lista — senão o workspace nunca
        // acharia o item pra selecionar.
        $guinchoIdSolicitado = (int)($_GET['guincho_id'] ?? 0);
        if ($guinchoIdSolicitado > 0 && !in_array($guinchoIdSolicitado, array_column($aprovados, 'id'), true)) {
            $extra = Guincho::buscarPorId($guinchoIdSolicitado);
            if ($extra) {
                $extra['ativo'] = $extra['ativo'] ?? 1;
                $extra['_fora_do_filtro_atual'] = true;
                array_unshift($aprovados, $extra);
            }
        }

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . "/../Views/admin/guinchos.php";
    }

    /**
     * §PRESTADORES-HUB-01: módulo único "Prestadores" fundindo Guinchos +
     * Especialistas + Guinchos pendentes + Documentos em duas abas (o
     * detalhe de aprovado/pendente já usa o MESMO partial de sempre,
     * guincho_detalhe_workspace.php, que já sabia renderizar o card de
     * Aprovar/Rejeitar quando !aprovado — só precisou entrar na mesma
     * worklist). As 4 rotas antigas (/admin/guinchos, /admin/guinchos?tipo=,
     * /admin/guinchospendentes, /admin/documentos) continuam funcionando
     * exatamente como antes (não foram tocadas), só deixam de aparecer como
     * links independentes na sidebar — ver Tarefa 5 do protocolo Codex,
     * mesma lógica aplicada aqui pro bloco Claude.
     */
    public function prestadores(): void
    {
        AuthService::requireAuth('admin');
        $pendentes = Guincho::listarPendentes();

        $tipoFiltro = (string)($_GET['tipo'] ?? '');
        $whereTipo = '';
        if ($tipoFiltro === 'especialista') {
            $whereTipo = ' AND g.oferece_reboque = 0';
        } elseif ($tipoFiltro === 'reboque') {
            $whereTipo = ' AND g.oferece_reboque = 1';
        }

        try {
            $stmt = getPDO()->prepare(
                "SELECT g.*, u.nome AS nome_operador, u.email, u.telefone, u.ativo
                 FROM guinchos g JOIN usuarios u ON g.usuario_id = u.id
                 WHERE g.aprovado = 1{$whereTipo} ORDER BY u.nome"
            );
            $stmt->execute();
            $aprovados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $aprovados = []; }

        // Aba "Prestadores": aprovados (filtrados por tipo, se houver) +
        // pendentes sempre no fim da lista, marcados com _pendente=true pra
        // a view desenhar o badge certo e o filtro "Pendentes" poder isolar
        // só eles no client-side.
        $worklistPrestadores = $aprovados;
        foreach ($pendentes as $p) {
            $p['_pendente'] = true;
            $worklistPrestadores[] = $p;
        }

        $guinchoIdSolicitado = (int)($_GET['guincho_id'] ?? 0);
        if ($guinchoIdSolicitado > 0 && !in_array($guinchoIdSolicitado, array_column($worklistPrestadores, 'id'), true)) {
            $extra = Guincho::buscarPorId($guinchoIdSolicitado);
            if ($extra) {
                $extra['ativo'] = $extra['ativo'] ?? 1;
                $extra['_fora_do_filtro_atual'] = true;
                array_unshift($worklistPrestadores, $extra);
            }
        }

        // Aba "Documentos": mesma query/cálculo de cnh_status já usado em
        // documentos() — reaproveitado aqui pra não duplicar a lógica de
        // vencimento em dois lugares.
        $statusFiltro = (string)($_GET['status'] ?? '');
        try {
            $stmtDoc = getPDO()->prepare(
                "SELECT g.*, u.nome AS operador_nome, u.email AS operador_email
                 FROM guinchos g JOIN usuarios u ON u.id = g.usuario_id
                 WHERE g.aprovado = 1 ORDER BY u.nome"
            );
            $stmtDoc->execute();
            $documentos = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $documentos = []; }

        // Mesmo cálculo de status/URLs de documentos() (linha ~3030) — copiado
        // pra manter os dois idênticos, já que documentos() não foi tocado
        // (continua funcionando como rota de compatibilidade independente).
        $hoje = new DateTimeImmutable('today');
        $limite = $hoje->modify('+30 days');
        foreach ($documentos as &$d) {
            $validade = trim((string)($d['cnh_validade'] ?? ''));
            $status = 'ausente';
            if ($validade !== '') {
                try {
                    $data = new DateTimeImmutable($validade);
                    $status = $data < $hoje ? 'vencida' : ($data <= $limite ? 'vencendo' : 'ok');
                } catch (Throwable $e) { $status = 'ausente'; }
            }
            $d['cnh_status'] = $status;
            $d['cnh_frente_url'] = !empty($d['doc_cnh_frente']) ? BASE_PATH . '/arquivo/' . (int)$d['id'] . '?tipo=doc_cnh_frente' : null;
            $d['cnh_verso_url'] = !empty($d['doc_cnh_verso']) ? BASE_PATH . '/arquivo/' . (int)$d['id'] . '?tipo=doc_cnh_verso' : null;
            $d['foto_veiculo_url'] = !empty($d['foto_veiculo']) ? BASE_PATH . '/arquivo/' . (int)$d['id'] . '?tipo=foto_veiculo' : null;
        }
        unset($d);
        if (in_array($statusFiltro, ['vencida', 'vencendo', 'ok', 'ausente'], true)) {
            $documentos = array_values(array_filter($documentos, static fn(array $d): bool => $d['cnh_status'] === $statusFiltro));
        }

        $resumoPrestadores = [
            'aprovados' => count($aprovados),
            'online' => count(array_filter($aprovados, static fn($g) => !empty($g['disponivel']))),
            'suspensos' => count(array_filter($aprovados, static fn($g) => empty($g['ativo'] ?? 1))),
            'pendentes' => count($pendentes),
        ];

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/prestadores.php';
    }

    public function guinchoNovoForm(): void
    {
        AuthService::requireAuth("admin");
        $csrfToken = AuthService::gerarCsrfToken();
        $cidadesAtivas = Cidade::listarAtivas();
        require __DIR__ . "/../Views/admin/guinchoform.php";
    }

    public function guinhoCriar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $nome    = trim($_POST["nome"]  ?? "");
        $email   = trim($_POST["email"] ?? "");
        $tel     = preg_replace("/\D/", "", $_POST["telefone"] ?? "");
        $cpf     = preg_replace("/\D/", "", $_POST["cpf"] ?? "");
        $senha   = $_POST["senha"] ?? "";
        $confirma = $_POST["confirmar_senha"] ?? "";
        if (empty($nome) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8 || $senha !== $confirma) {
            $this->redirect("/admin/guincho/novo?erro=1");
        }
        $pdo = getPDO();
        $existe = $pdo->prepare("SELECT id FROM usuarios WHERE email=? OR cpf=? LIMIT 1");
        $existe->execute([$email, $cpf]);
        if ($existe->fetch()) { $this->redirect("/admin/guincho/novo?erro=email_duplicado"); }
        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo,criado_em) VALUES (?,?,?,?,?,'guincho',1,NOW())")->execute([$nome,$email,$hash,$tel,$cpf]);
        $usuarioId = (int)$pdo->lastInsertId();
        $aprovado = (int)($_POST["aprovado"] ?? 0);
        $dados = [
            "cidade_id"        => (int)($_POST["cidade_id"] ?? 0),
            "cnh_numero"       => trim($_POST["cnh_numero"] ?? ""),
            "cnh_validade"     => $_POST["cnh_validade"] ?? date("Y-m-d", strtotime("+5 years")),
            "placa_guincho"    => strtoupper(trim($_POST["placa_guincho"] ?? "")),
            "cidade_placa"     => trim((string)($_POST["cidade_placa"] ?? "")),
            "uf_placa"         => strtoupper(trim((string)($_POST["uf_placa"] ?? ""))),
            "capacidade_ton"   => (float)($_POST["capacidade_ton"] ?? 0),
            "raio_cobertura_km"=> (int)($_POST["raio_cobertura_km"] ?? 20),
            "chave_pix"        => trim($_POST["chave_pix"] ?? ""),
            "chave_pix_tipo"   => $_POST["chave_pix_tipo"] ?? "cpf",
            "lat_operacao"     => (float)($_POST["lat_operacao"] ?? 0),
            "lng_operacao"     => (float)($_POST["lng_operacao"] ?? 0),
            "foto_veiculo"     => $this->processarUpload("foto_veiculo"),
            "doc_cnh_frente"   => $this->processarUpload("doc_cnh_frente"),
            "doc_cnh_verso"    => $this->processarUpload("doc_cnh_verso"),
        ];
        $guinchoId = Guincho::criarDeRegistro($usuarioId, $dados);
        if ($guinchoId && $aprovado) { Guincho::aprovar((int)$guinchoId); }
        $this->redirect("/admin/guinchos?msg=criado");
    }

    public function guinchoAtualizar(): void
    {
        AuthService::requireAuth("admin");
        if (!AuthService::validarCsrfToken($_POST["csrf_token"] ?? "")) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST["id"] ?? 0);
        if (!$id) { $this->redirect("/admin/guinchos"); }
        $g = Guincho::buscarPorId($id);
        if (!$g) { $this->redirect("/admin/guinchos"); }
        getPDO()->prepare(
            "UPDATE guinchos SET placa_guincho=?, cidade_placa=?, uf_placa=?, cnh_numero=?, cnh_validade=?,
             chave_pix=?, chave_pix_tipo=?, capacidade_ton=?, lat_operacao=?, lng_operacao=?, aprovado=? WHERE id=?"
        )->execute([
            strtoupper(trim($_POST["placa_guincho"] ?? "")),
            trim((string)($_POST["cidade_placa"] ?? "")),
            strtoupper(trim((string)($_POST["uf_placa"] ?? ""))),
            trim($_POST["cnh_numero"] ?? ""),
            $_POST["cnh_validade"] ?? date("Y-m-d", strtotime("+5 years")),
            trim($_POST["chave_pix"] ?? ""),
            $_POST["chave_pix_tipo"] ?? "cpf",
            (float)($_POST["capacidade_ton"] ?? 0),
            (float)($_POST["lat_operacao"] ?? 0),
            (float)($_POST["lng_operacao"] ?? 0),
            (int)($_POST["aprovado"] ?? 0),
            $id,
        ]);
        $this->redirect("/admin/usuario/editar/{$g["usuario_id"]}?salvo=1");
    }

    // ─── AJAX: veículos por cliente (para pedidocriar.php) ────
    public function veiculosAjax(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json');
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        if (!$clienteId) { echo json_encode(['veiculos' => []]); exit; }
        require_once __DIR__ . '/../Models/Veiculo.php';
        $veiculos = Veiculo::listarPorUsuario($clienteId);
        echo json_encode(['veiculos' => $veiculos]);
        exit;
    }

    // GET /admin/usuario/senha - redireciona para lista de usuários
    // A alteração de senha é feita dentro da tela de edição do usuário via POST
    public function clientesAjax(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=utf-8');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { echo json_encode(['clientes' => []]); exit; }
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT id, nome, email, telefone FROM usuarios
             WHERE tipo='cliente' AND ativo=1 AND (nome LIKE ? OR email LIKE ?)
             ORDER BY nome LIMIT 10"
        );
        $stmt->execute(["%$q%", "%$q%"]);
        echo json_encode(['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function oficinasAjax(): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=utf-8');
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        if (!$clienteId) { echo json_encode(['oficinas' => []]); exit; }
        $stmt = getPDO()->prepare(
            "SELECT id, nome, endereco, lat, lng FROM oficinas_favoritas WHERE usuario_id=? ORDER BY nome"
        );
        $stmt->execute([$clienteId]);
        echo json_encode(['oficinas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }


    public function usuarioSenhaGet(): void
    {
        AuthService::requireAuth('admin');
        $this->redirect('/admin/usuarios?info=senha_via_editar');
    }

    // ─── GOVERNANÇA DO .ENV ──────────────────────────────────────

    public function envGovernanca(): void
    {
        AuthService::requireAuth('admin');
        $envPath  = $this->managedEnvPath();
        $envAtual = ConfigSecurityService::parseManagedEnvFile($envPath);
        $runtimeAudit = ConfigSecurityService::auditManagedEnvironment(dirname(__DIR__, 2), $_ENV);
        $csrfToken = AuthService::gerarCsrfToken();
        $msg = $_GET['msg'] ?? '';
        $envErrors = array_values(array_filter(array_map('trim', explode('|', (string)($_GET['env_errors'] ?? '')))));
        require __DIR__ . '/../Views/admin/env_governanca.php';
    }

    public function envSalvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $envPath   = $this->managedEnvPath();
        $envAtual  = ConfigSecurityService::parseManagedEnvFile($envPath);
        $novos     = $_POST['env'] ?? [];
        $adminId   = (int)($_SESSION['usuario_id'] ?? 0);

        $sensivel = ['DB_PASS','MP_ACCESS_TOKEN','MP_PUBLIC_KEY','MP_WEBHOOK_SECRET',
                     'PS_TOKEN','SMTP_PASS','ENCRYPTION_KEY','SIMULATION_ADMIN_TOKEN'];

        // Mescla: campos sensíveis em branco = manter atual
        $merged = $envAtual;
        foreach ($novos as $chave => $valor) {
            $chave = preg_replace('/[^A-Z0-9_]/', '', strtoupper((string)$chave));
            if ($chave === '') continue;
            if (in_array($chave, $sensivel, true) && trim((string)$valor) === '') {
                continue; // mantém valor atual
            }
            $merged[$chave] = trim((string)$valor);
        }

        $validation = ConfigSecurityService::validateManagedEnvMap($merged);
        if (!empty($validation['critical'])) {
            $this->redirect('/admin/env?msg=erro_validacao&env_errors=' . rawurlencode(implode('|', $validation['critical'])));
        }

        // Gera conteúdo novo
        $grupos = [
            'Banco de dados'        => ['DB_HOST','DB_NAME','DB_USER','DB_PASS'],
            'Aplicacao'             => ['APP_NAME','APP_URL','APP_ENV','APP_DEBUG','HTTPS_ONLY','FORCE_BASEPATH'],
            'Institucional'         => ['COMPANY_ADDRESS','COMPANY_WHATSAPP','ADMIN_EMAIL'],
            'Gateway ativo'         => ['PAYMENT_GATEWAY_ACTIVE'],
            'MercadoPago'           => ['MP_ACCESS_TOKEN','MP_PUBLIC_KEY','MP_WEBHOOK_SECRET','MP_ENV'],
            'PagSeguro'             => ['PS_EMAIL','PS_TOKEN','PS_ENV'],
            'SMTP'                  => ['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','SMTP_FROM_EMAIL','SMTP_FROM_NAME'],
            'Simulado e testes'     => ['SIMULATION_ENABLED','PIX_DRY_RUN','SIMULATION_ADMIN_TOKEN'],
            'Operacional'           => ['MAX_PIX_TENTATIVAS','GEOCODING_CACHE_TTL_DAYS','TARIFA_BASE','TARIFA_KM','ENCRYPTION_KEY'],
            'Log do sistema'        => ['SYSTEM_LOG_ENABLED'],
        ];

        $linhas = ["# GuinchaFacil -- variaveis de ambiente", "# NUNCA commitar este arquivo\n"];
        $todasChavesOrdenadas = [];
        foreach ($grupos as $nomeGrupo => $chaves) {
            $linhas[] = "# {$nomeGrupo}";
            foreach ($chaves as $c) {
                $val = $merged[$c] ?? '';
                $linhas[] = $c . '=' . ConfigSecurityService::formatManagedEnvValue((string)$val);
                $todasChavesOrdenadas[] = $c;
            }
            $linhas[] = '';
        }
        // Chaves extras não classificadas
        foreach ($merged as $c => $v) {
            if (!in_array($c, $todasChavesOrdenadas, true)) {
                $linhas[] = $c . '=' . ConfigSecurityService::formatManagedEnvValue((string)$v);
            }
        }
        $conteudo = implode("\n", $linhas) . "\n";

        // Escrita atômica: tmp → rename
        $tmpPath = $envPath . '.tmp.' . time();
        if (file_put_contents($tmpPath, $conteudo, LOCK_EX) === false) {
            $this->redirect('/admin/env?msg=erro_write');
        }
        // Valida parse do tmp
        $parsed = $this->parseEnvFileRaw($tmpPath);
        if ($parsed === null) {
            @unlink($tmpPath);
            $this->redirect('/admin/env?msg=erro_parse');
        }
        rename($tmpPath, $envPath);

        // Auditoria — registra cada chave alterada
        $pdo = getPDO();
        foreach ($merged as $chave => $valorNovo) {
            $valorAntigo = $envAtual[$chave] ?? null;
            if ($valorAntigo === $valorNovo) continue;

            $acao    = ($valorAntigo === null) ? 'criado' : (($valorNovo === '') ? 'removido' : 'alterado');
            $mascarado = in_array($chave, $sensivel, true)
                ? (strlen($valorNovo) > 4 ? substr($valorNovo, 0, 2) . str_repeat('*', max(4, strlen($valorNovo) - 4)) . substr($valorNovo, -2) : '****')
                : $valorNovo;
            $hash = hash('sha256', $chave . $valorNovo . $adminId . time() . random_bytes(8));

            try {
                $pdo->prepare(
                    "INSERT INTO env_auditoria (admin_id, chave, valor_mascarado, acao, hash_alteracao, criado_em)
                     VALUES (?, ?, ?, ?, ?, NOW())"
                )->execute([$adminId, $chave, $mascarado, $acao, $hash]);
            } catch (Throwable $e) {
                // Auditoria é aditiva: a tabela pode não existir ainda em bancos antigos
                // sem a migration aplicada — não pode impedir a gravação do .env.
                Logger::exception(__CLASS__, __FUNCTION__, 'env_auditoria_insert', $e, [
                    'chave' => $chave, 'admin_id' => $adminId,
                ]);
            }

            Logger::log(Logger::LEVEL_INFO, 'AdminController', 'envSalvar', 'env_admin',
                "[EnvAdmin][SAVE] {$chave} {$acao} por admin #{$adminId}",
                ['chave' => $chave, 'acao' => $acao, 'admin_id' => $adminId]
            );
        }

        $this->redirect('/admin/env?msg=salvo');
    }

    private function updateEnvValues(string $path, array $updates): bool
    {
        $current = ConfigSecurityService::parseManagedEnvFile($path);
        foreach ($updates as $key => $value) {
            $key = preg_replace("/[^A-Z0-9_]/", "", strtoupper((string)$key));
            if ($key === "") continue;
            $current[$key] = trim((string)$value);
        }

        $validation = ConfigSecurityService::validateManagedEnvMap($current);
        if (!empty($validation['critical'])) {
            return false;
        }

        $groups = [
            "Banco de dados" => ["DB_HOST","DB_NAME","DB_USER","DB_PASS"],
            "Aplicacao" => ["APP_NAME","APP_URL","APP_ENV","APP_DEBUG","HTTPS_ONLY","FORCE_BASEPATH"],
            "Institucional" => ["COMPANY_ADDRESS","COMPANY_WHATSAPP","ADMIN_EMAIL"],
            "Gateway ativo" => ["PAYMENT_GATEWAY_ACTIVE"],
            "MercadoPago" => [
                "MP_ACCESS_TOKEN","MP_PUBLIC_KEY","MP_WEBHOOK_SECRET","MP_ENV",
                "MP_ACCESS_TOKEN_SANDBOX","MP_ACCESS_TOKEN_PROD",
                "MP_PUBLIC_KEY_SANDBOX","MP_PUBLIC_KEY_PROD",
                "MP_CLIENT_ID_PROD", "MP_CLIENT_SECRET_PROD"
            ],
            "PagSeguro" => ["PS_EMAIL","PS_TOKEN","PS_ENV"],
            "SMTP" => ["SMTP_HOST","SMTP_PORT","SMTP_USER","SMTP_PASS","SMTP_FROM_EMAIL","SMTP_FROM_NAME"],
            "Simulado e testes" => ["SIMULATION_ENABLED","PIX_DRY_RUN","SIMULATION_ADMIN_TOKEN"],
            "Operacional" => ["MAX_PIX_TENTATIVAS","GEOCODING_CACHE_TTL_DAYS","TARIFA_BASE","TARIFA_KM","ENCRYPTION_KEY"],
            "Log do sistema" => ["SYSTEM_LOG_ENABLED"],
        ];

        $lines = ["# GuinchaFacil -- variaveis de ambiente", "# NUNCA commitar este arquivo", ""];
        $written = [];
        foreach ($groups as $group => $keys) {
            $lines[] = "# " . $group;
            foreach ($keys as $key) {
                if (!array_key_exists($key, $current)) continue;
                $lines[] = $key . "=" . ConfigSecurityService::formatManagedEnvValue((string)$current[$key]);
                $written[] = $key;
            }
            $lines[] = "";
        }
        foreach ($current as $key => $value) {
            if (!in_array($key, $written, true)) {
                $lines[] = $key . "=" . ConfigSecurityService::formatManagedEnvValue((string)$value);
            }
        }

        $tmpPath = $path . ".tmp." . getmypid() . "." . time();
        if (file_put_contents($tmpPath, implode("\n", $lines) . "\n", LOCK_EX) === false) {
            return false;
        }
        if ($this->parseEnvFileRaw($tmpPath) === null) {
            @unlink($tmpPath);
            return false;
        }
        return rename($tmpPath, $path);
    }

    private function sanitizeAdminRedirectPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') {
            return '';
        }
        if (!str_starts_with($path, '/admin/')) {
            return '';
        }

        return $path;
    }

    private function appendQueryParam(string $path, string $key, string $value): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . rawurlencode($key) . '=' . rawurlencode($value);
    }

    private function managedEnvPath(): string
    {
        return ConfigSecurityService::resolveManagedEnvPath(dirname(__DIR__, 2));
    }

    private function serializePedidoStatus(array $pedido): array
    {
        return [
            'id' => (int)($pedido['id'] ?? 0),
            'status' => (string)($pedido['status'] ?? ''),
            'status_label' => $this->statusLabel((string)($pedido['status'] ?? '')),
            'guincho_id' => !empty($pedido['guincho_id']) ? (int)$pedido['guincho_id'] : null,
            'guincho_nome' => $pedido['guincho_operador'] ?? null,
            'guincho_telefone' => $pedido['guincho_telefone'] ?? null,
            'guincho_placa' => $pedido['guincho_placa'] ?? null,
            'lat_guincho' => isset($pedido['lat_guincho']) ? (float)$pedido['lat_guincho'] : null,
            'lng_guincho' => isset($pedido['lng_guincho']) ? (float)$pedido['lng_guincho'] : null,
        ];
    }

    private function serializePedidoListItem(array $pedido): array
    {
        $status = (string)($pedido['status'] ?? '');
        return [
            'id' => (int)($pedido['id'] ?? 0),
            'cliente_nome' => (string)($pedido['cliente_nome'] ?? '—'),
            'cliente_telefone' => $pedido['cliente_telefone'] ?? null,
            'placa' => $pedido['placa'] ?? null,
            'modelo' => $pedido['modelo'] ?? null,
            'guincho_nome' => $pedido['guincho_operador'] ?? null,
            'tipo_problema' => (string)($pedido['tipo_problema'] ?? ''),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'custo_estimado' => (float)($pedido['custo_estimado'] ?? 0),
            'valor_label' => 'R$ ' . number_format((float)($pedido['custo_estimado'] ?? 0), 2, ',', '.'),
            'data_label' => date('d/m/Y', strtotime((string)($pedido['criado_em'] ?? 'now'))),
            'hora_label' => date('H:i', strtotime((string)($pedido['criado_em'] ?? 'now'))),
        ];
    }

    private function serializeTrailPoint(array $point): array
    {
        return [
            'id' => (int)($point['id'] ?? 0),
            'fase' => (string)($point['fase'] ?? ''),
            'sequence_number' => (int)($point['sequence_number'] ?? 0),
            'latitude' => (float)($point['latitude'] ?? 0),
            'longitude' => (float)($point['longitude'] ?? 0),
            'is_valid' => !empty($point['is_valid']),
            'rejection_code' => $point['rejection_code'] ?? null,
            'street_name' => $point['street_name'] ?? null,
            'accuracy_m' => isset($point['accuracy_m']) ? (float)$point['accuracy_m'] : null,
            'calculated_speed_kmh' => isset($point['calculated_speed_kmh']) ? (float)$point['calculated_speed_kmh'] : null,
            'distance_raw_m' => isset($point['distance_raw_m']) ? (float)$point['distance_raw_m'] : null,
            'distance_accumulated_m' => isset($point['distance_accumulated_m']) ? (float)$point['distance_accumulated_m'] : null,
            'server_timestamp' => $point['server_timestamp'] ?? null,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'aguardando_pagamento' => 'Aguardando Pagamento',
            'aguardando_guincho' => 'Aguardando Guincho',
            'a_caminho' => 'A Caminho',
            'no_local' => 'No Local',
            'em_reboque' => 'Em Reboque',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function envAuditoria(): void
    {
        // Ponte de compatibilidade para chamadas internas e integrações antigas.
        (new AdminEnvAuditController())->index();
    }

    private function parseEnvFile(string $path): array
    {
        return ConfigSecurityService::parseManagedEnvFile($path);
    }

    private function parseEnvFileRaw(string $path): ?array
    {
        if (!is_file($path)) return null;
        $result = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (!str_contains($line, '=')) continue;
            [$key, $val] = explode('=', $line, 2);
            $result[trim($key)] = trim($val);
        }
        return empty($result) ? null : $result;
    }

    // ─── HEALTH CHECK ────────────────────────────────────────────

    public function health(): void
    {
        // Ponte de compatibilidade para chamadas internas e integrações antigas.
        (new AdminHealthController())->health();
    }

    // ─── SIMULADOR ───────────────────────────────────────────────

    public function simulador(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Models/SimulationRun.php';
        $runsRecentes = SimulationRun::listarRecentes(20);
        $csrfToken    = AuthService::gerarCsrfToken();
        $playwrightEnabled = PlaywrightRunnerService::isEnabled();
        require __DIR__ . '/../Views/admin/simulacao.php';
    }

    public function simularExecutar(): void
    {
        AuthService::requireAuth('admin');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $simEnabled = defined('SIMULATION_ENABLED') ? SIMULATION_ENABLED : (env('SIMULATION_ENABLED', 'false') === 'true');
        if (!$simEnabled) {
            Logger::log(Logger::LEVEL_WARN, 'AdminController', 'simularExecutar', 'simulacao',
                'Tentativa de executar simulador com SIMULATION_ENABLED=false.');
            $this->redirect('/admin/simulador');
        }

        require_once __DIR__ . '/../Services/SimulationService.php';

        $dryRun  = (($_POST['pix_dry_run'] ?? '1') === '1');
        $service = new SimulationService($dryRun);
        $result  = $service->run();

        $this->redirect('/admin/simulador/' . $result['run_id']);
    }

    public function qaExecutar(): void
    {
        AuthService::requireAuth('admin');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $usuario = AuthService::getCurrentUser();
        $config = [
            'suite' => $_POST['suite'] ?? 'smoke',
            'browser' => $_POST['browser'] ?? 'chromium',
            'viewport' => $_POST['viewport'] ?? 'desktop',
            'locale' => $_POST['locale'] ?? 'pt-BR',
            'timezone' => $_POST['timezone'] ?? 'America/Sao_Paulo',
            'target_environment' => $_POST['target_environment'] ?? 'staging',
            'target_url' => $_POST['target_url'] ?? (defined('APP_URL') ? APP_URL : ''),
            'pix_dry_run' => (($_POST['pix_dry_run'] ?? '1') === '1'),
            'stop_on_failure' => (($_POST['stop_on_failure'] ?? '1') === '1'),
            'record_video' => (($_POST['record_video'] ?? '1') === '1'),
            'record_trace' => (($_POST['record_trace'] ?? '1') === '1'),
            'cleanup_after_run' => (($_POST['cleanup_after_run'] ?? '0') === '1'),
        ];

        try {
            $runId = PlaywrightRunnerService::queue($config, (int)($usuario['id'] ?? 0));
            Logger::log(Logger::LEVEL_INFO, 'AdminController', 'qaExecutar', 'qa', 'Execução Playwright enfileirada.', [
                'run_id' => $runId,
                'suite' => $config['suite'],
                'browser' => $config['browser'],
            ]);
            $this->redirect('/admin/simulador/' . $runId);
        } catch (Throwable $e) {
            Logger::log(Logger::LEVEL_ERROR, 'AdminController', 'qaExecutar', 'qa', 'Falha ao enfileirar Playwright.', [
                'erro' => $e->getMessage(),
            ]);
            $this->setFlashMessage('Falha ao enfileirar execução Playwright: ' . $e->getMessage(), 'error');
            $this->redirect('/admin/simulador');
        }
    }

    public function simuladorResultado(string $runId): void
    {
        AuthService::requireAuth('admin');

        if (!ctype_xdigit($runId) || strlen($runId) !== 32) {
            http_response_code(404); echo '<h1>404</h1>'; exit;
        }

        require_once __DIR__ . '/../Models/SimulationRun.php';
        require_once __DIR__ . '/../Models/SimulationStep.php';

        $runWarnings = [];
        try {
            $run = SimulationRun::buscarPorRunId($runId);
        } catch (Throwable $e) {
            Logger::log(Logger::LEVEL_ERROR, 'AdminController', 'simuladorResultado', 'qa',
                'Falha ao carregar execução do simulador.', [
                    'run_id' => $runId,
                    'erro' => $e->getMessage(),
                ]);
            http_response_code(500);
            echo 'Erro interno do servidor. Tente novamente mais tarde.';
            exit;
        }

        if (!$run) { http_response_code(404); echo '<h1>404 — Simulação não encontrada</h1>'; exit; }

        try {
            $steps = SimulationStep::listarPorRun($runId);
        } catch (Throwable $e) {
            $steps = [];
            $runWarnings[] = 'Não foi possível carregar as fases desta execução: ' . $e->getMessage();
            Logger::log(Logger::LEVEL_WARN, 'AdminController', 'simuladorResultado', 'qa',
                'Falha ao carregar fases da execução.', [
                    'run_id' => $runId,
                    'erro' => $e->getMessage(),
                ]);
        }

        try {
            $artifacts = SimulationArtifact::listarPorRun($runId);
        } catch (Throwable $e) {
            $artifacts = [];
            $runWarnings[] = 'Não foi possível carregar os artefatos desta execução: ' . $e->getMessage();
            Logger::log(Logger::LEVEL_WARN, 'AdminController', 'simuladorResultado', 'qa',
                'Falha ao carregar artefatos da execução.', [
                    'run_id' => $runId,
                    'erro' => $e->getMessage(),
                ]);
        }

        require __DIR__ . '/../Views/admin/simulacao_resultado.php';
    }

    public function qaStatus(string $runId): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json; charset=UTF-8');

        $run = SimulationRun::buscarPorRunId($runId);
        if (!$run) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'erro' => 'run_not_found'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'run_id' => $run['run_id'],
            'engine' => $run['engine'] ?? 'php_internal',
            'status' => $run['status'] ?? 'unknown',
            'heartbeat_at' => $run['heartbeat_at'] ?? null,
            'started_at' => $run['started_at'] ?? null,
            'finished_at' => $run['finished_at'] ?? null,
            'worker_id' => $run['worker_id'] ?? null,
            'total_fases' => (int)($run['total_fases'] ?? 0),
            'fases_ok' => (int)($run['fases_ok'] ?? 0),
            'fases_erro' => (int)($run['fases_erro'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function qaCancelar(string $runId): void
    {
        AuthService::requireAuth('admin');

        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $ok = PlaywrightRunnerService::cancel($runId);
        $this->setFlashMessage($ok ? 'Execução cancelada.' : 'Não foi possível cancelar esta execução.', $ok ? 'success' : 'error');
        $this->redirect('/admin/simulador/' . $runId);
    }

    public function qaArtifact(int $artifactId): void
    {
        AuthService::requireAuth('admin');
        $runId = (string)($_GET['run_id'] ?? '');

        $artifact = SimulationArtifact::buscarPorId($artifactId);
        if (!$artifact || (string)($artifact['run_id'] ?? '') !== $runId) {
            http_response_code(404);
            echo '<h1>404 — Artefato não encontrado</h1>';
            exit;
        }

        $path = (string)($artifact['private_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            echo '<h1>404 — Arquivo do artefato não encontrado</h1>';
            exit;
        }

        header('Content-Type: ' . ($artifact['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: inline; filename="' . basename((string)$artifact['filename']) . '"');
        readfile($path);
        exit;
    }

    public function logsV2(): void
    {
        // Ponte de compatibilidade para chamadas internas e integrações antigas.
        (new AdminLogsController())->index();
    }

    public function logsExport(): void
    {
        AuthService::requireAuth('admin');

        $format = strtolower(trim((string)($_GET['format'] ?? 'jsonl')));
        if (!in_array($format, ['jsonl', 'csv'], true)) {
            $format = 'jsonl';
        }

        $filtros = AdminLogService::normalizeFilters($_GET);
        $export = AdminLogService::exportRows($format, $filtros);

        header('Content-Type: ' . $export['content_type']);
        header('Content-Disposition: attachment; filename="' . basename((string)$export['filename']) . '"');
        echo $export['content'];
        exit;
    }

    // ─── UPLOAD DE ARQUIVOS ──────────────────────────────────────
    private function processarUpload(string $campo): ?string
    {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) return null;
        $file = $_FILES[$campo];
        if ($file['size'] > 5 * 1024 * 1024) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'])) return null;
        $destDir = defined('UPLOAD_PATH') ? UPLOAD_PATH : (dirname(__DIR__, 3) . '/uploads');
        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
        $fileName = uniqid($campo . '_') . '.' . $ext;
        $destPath = $destDir . '/' . $fileName;
        if (move_uploaded_file($file['tmp_name'], $destPath)) return $fileName;
        return null;
    }

    // ─── CATÁLOGO DE SERVIÇOS ─────────────────────────────────────
    /**
     * GET /admin/servicos — lista os atalhos de serviço exibidos no
     * painel do cliente ("Reboque agora", "Bateria", etc.), agora
     * administráveis em vez de fixos no código-fonte.
     */
    public function servicos(): void
    {
        AuthService::requireAuth('admin');
        // Etapa 16 — unificação de telas de serviço. /admin/servicos deixa de
        // renderizar o catálogo cosmético legado (catalogo_servicos, atalhos
        // do painel do cliente) e passa a apontar para o catálogo estruturado
        // real (service_types), fonte única de domínio/tarifa/compatibilidade
        // com o reboque protegido. As rotas servico/* legadas seguem existindo
        // (a tela de "novo pedido" do cliente ainda lê catalogo_servicos), mas
        // não há mais entrada de admin separada para elas.
        $this->redirect('/admin/catalogo-servicos/tipos');
    }

    public function servicoForm(): void
    {
        AuthService::requireAuth('admin');
        $servico = null;
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $servico = ServicoCatalogo::buscarPorId($id);
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/servicoform.php';
    }

    public function servicoSalvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $tiposValidos = ['eletrica','pneu','colisao','bateria','combustivel','outro'];
        $coresValidas = ['tow','battery','tire','fuel','schedule'];
        $dados = [
            'chave' => preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string)($_POST['chave'] ?? '')))),
            'nome' => trim((string)($_POST['nome'] ?? '')),
            'descricao' => trim((string)($_POST['descricao'] ?? '')) ?: null,
            'tipo_problema' => in_array($_POST['tipo_problema'] ?? '', $tiposValidos, true) ? $_POST['tipo_problema'] : 'outro',
            'icone' => trim((string)($_POST['icone'] ?? '')) ?: 'fa-truck-pickup',
            'cor' => in_array($_POST['cor'] ?? '', $coresValidas, true) ? $_POST['cor'] : 'tow',
            'ordem' => (int)($_POST['ordem'] ?? 100),
            'ativo' => !empty($_POST['ativo']) ? 1 : 0,
        ];
        if ($dados['chave'] === '' || $dados['nome'] === '') {
            $this->redirect('/admin/servico/novo?erro=1');
        }
        if ($id > 0) {
            ServicoCatalogo::atualizar($id, $dados);
        } else {
            ServicoCatalogo::criar($dados);
        }
        $this->redirect('/admin/servicos?salvo=1');
    }

    public function servicoAlternar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            ServicoCatalogo::alternarAtivo($id);
        }
        $this->redirect('/admin/servicos');
    }

    public function servicoRemover(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            ServicoCatalogo::remover($id);
        }
        $this->redirect('/admin/servicos?removido=1');
    }

    public function avaliacoes(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Models/Avaliacao.php';
        $filtros = ['guincho_id' => (int)($_GET['guincho_id'] ?? 0), 'nota' => (int)($_GET['nota'] ?? 0)];
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $avaliacoes = Avaliacao::listarTodas($filtros, $pagina);
        $resumo = Avaliacao::resumo($filtros);
        require __DIR__ . '/../Views/admin/avaliacoes.php';
    }

    public function documentos(): void
    {
        AuthService::requireAuth('admin');
        $stmt = getPDO()->prepare('SELECT g.*, u.nome AS operador_nome, u.email AS operador_email FROM guinchos g JOIN usuarios u ON u.id = g.usuario_id WHERE g.aprovado = 1 ORDER BY u.nome');
        $stmt->execute();
        $documentos = [];
        $hoje = new DateTimeImmutable('today'); $limite = $hoje->modify('+30 days');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $validade = trim((string)($row['cnh_validade'] ?? '')); $status = 'ausente';
            if ($validade !== '') {
                try { $data = new DateTimeImmutable($validade); $status = $data < $hoje ? 'vencida' : ($data <= $limite ? 'vencendo' : 'ok'); }
                catch (Throwable $e) { $status = 'ausente'; }
            }
            $row['cnh_status'] = $status;
            $row['cnh_frente_url'] = !empty($row['doc_cnh_frente']) ? BASE_PATH . '/arquivo/' . (int)$row['id'] . '?tipo=doc_cnh_frente' : null;
            $row['cnh_verso_url'] = !empty($row['doc_cnh_verso']) ? BASE_PATH . '/arquivo/' . (int)$row['id'] . '?tipo=doc_cnh_verso' : null;
            $row['foto_veiculo_url'] = !empty($row['foto_veiculo']) ? BASE_PATH . '/arquivo/' . (int)$row['id'] . '?tipo=foto_veiculo' : null;
            $documentos[] = $row;
        }
        $statusFiltro = (string)($_GET['status'] ?? '');
        if (in_array($statusFiltro, ['vencida', 'vencendo', 'ok', 'ausente'], true)) $documentos = array_values(array_filter($documentos, static fn(array $d): bool => $d['cnh_status'] === $statusFiltro));
        require __DIR__ . '/../Views/admin/documentos.php';
    }

    public function proofOfRoad(): void
    {
        AuthService::requireAuth('admin');
        $where = ['1=1']; $params = [];
        $qualidade = trim((string)($_GET['tracking_quality'] ?? ''));
        if ($qualidade !== '') { $where[] = 'r.tracking_quality = ?'; $params[] = $qualidade; }
        $dias = max(0, (int)($_GET['dias'] ?? 0));
        if ($dias > 0) { $where[] = 'p.criado_em >= DATE_SUB(NOW(), INTERVAL ' . $dias . ' DAY)'; }
        $pedidoId = (int)($_GET['pedido_id'] ?? 0);
        if ($pedidoId > 0) { $where[] = 'r.pedido_id = ?'; $params[] = $pedidoId; }
        $stmt = getPDO()->prepare('SELECT r.*, p.status AS pedido_status, p.criado_em AS pedido_criado_em, c.nome AS cliente_nome, gu.nome AS guincho_nome FROM pedido_percurso_resumos r JOIN pedidos p ON p.id = r.pedido_id JOIN usuarios c ON c.id = p.cliente_id LEFT JOIN guinchos g ON g.id = p.guincho_id LEFT JOIN usuarios gu ON gu.id = g.usuario_id WHERE ' . implode(' AND ', $where) . ' ORDER BY CASE r.tracking_quality WHEN "poor" THEN 0 WHEN "degraded" THEN 1 WHEN "good" THEN 2 ELSE 3 END, (r.rejected_points / NULLIF(r.total_points, 0)) DESC, r.updated_at DESC');
        $stmt->execute($params); $trilhas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($trilhas as &$trilha) { $total = (int)($trilha['total_points'] ?? 0); $trilha['rejected_percent'] = $total > 0 ? round(((int)$trilha['rejected_points'] / $total) * 100, 1) : 0.0; } unset($trilha);
        $resumo = ['total' => count($trilhas), 'ruins' => count(array_filter($trilhas, static fn(array $t): bool => in_array($t['tracking_quality'], ['poor', 'degraded'], true)))];
        require __DIR__ . '/../Views/admin/proof_of_road.php';
    }

    /**
     * Carteiras (Pacote L2.3 — item que estava "em breve"). Painel de
     * VISIBILIDADE sobre o repasse Pix que já é automático hoje — não cria
     * saldo retido nem fluxo de saque manual (decisão confirmada com o
     * usuário; ver docblock de CarteiraService). Toda falha de query é
     * exposta explicitamente na tela (nunca "R$ 0,00" silencioso).
     */
    public function carteiras(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Services/Finance/CarteiraService.php';

        $resumo = CarteiraService::resumoTodosGuincheiros();
        $reconciliacao = CarteiraService::checarReconciliacaoGlobal();

        if (!$resumo['ok']) {
            Logger::log(Logger::LEVEL_ERROR, 'AdminController', 'carteiras', 'financeiro',
                'Tela /admin/carteiras carregou com falha no resumo: ' . $resumo['erro'], []);
        }

        require __DIR__ . '/../Views/admin/carteiras.php';
    }

    /**
     * §CARTEIRAS-SHELL-01: /admin/carteiras agora é uma página só (shell-ops,
     * lista + workspace, mesmo padrão de /admin/central e
     * /admin/catalogo-servicos/capacidades). Esta rota antiga
     * (/admin/carteira/{id}) vira um redirect pra manter links/favoritos
     * antigos funcionando — a página é quem seleciona o guincheiro certo
     * via ?guincho_id= e busca o detalhe pelo endpoint JSON abaixo.
     */
    public function carteiraDetalhe(int $guinchoId): void
    {
        AuthService::requireAuth('admin');
        $this->redirect('/admin/carteiras?guincho_id=' . $guinchoId);
    }

    /**
     * Endpoint JSON do extrato de um guincheiro — consumido via fetch() pelo
     * workspace de /admin/carteiras ao selecionar um item na lista. Evita
     * reintroduzir N+1 (CarteiraService::resumoTodosGuincheiros() já avisa
     * explicitamente sobre isso): só busca o detalhe do guincheiro que o
     * admin de fato clicou, não de todos de uma vez.
     */
    public function carteiraDetalheJson(int $guinchoId): void
    {
        AuthService::requireAuth('admin');
        header('Content-Type: application/json');
        require_once __DIR__ . '/../Services/Finance/CarteiraService.php';

        $saldo = CarteiraService::saldoGuincho($guinchoId);
        $extrato = CarteiraService::extratoGuincho($guinchoId);

        $stmt = getPDO()->prepare(
            "SELECT g.*, u.nome AS nome_operador, u.email, u.telefone
             FROM guinchos g JOIN usuarios u ON u.id = g.usuario_id WHERE g.id = ? LIMIT 1"
        );
        $stmt->execute([$guinchoId]);
        $guincho = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$guincho) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'erro' => 'Guincheiro não encontrado.']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'guincho' => [
                'id' => (int)$guincho['id'],
                'nome_operador' => (string)$guincho['nome_operador'],
                'placa_guincho' => $guincho['placa_guincho'] ?? null,
                'telefone' => $guincho['telefone'] ?? null,
            ],
            'saldo' => $saldo,
            'extrato' => $extrato,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * "Saques" (Pacote L2.3) — como não existe solicitação manual de saque
     * hoje (o repasse Pix já é automático), esta tela é o painel operacional
     * real equivalente: repasses pendentes/processando/com falha, com o
     * botão de reprocessar reaproveitando 100% a action já existente
     * (AdminController::pixReprocessar / PixService::reprocessar).
     */
    public function saques(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Services/Finance/CarteiraService.php';

        $repasses = CarteiraService::repassesPendentesOuComFalha();
        $csrfToken = AuthService::gerarCsrfToken();

        if (!$repasses['ok']) {
            Logger::log(Logger::LEVEL_ERROR, 'AdminController', 'saques', 'financeiro',
                'Tela /admin/saques carregou com falha na consulta: ' . $repasses['erro'], []);
        }

        require __DIR__ . '/../Views/admin/saques.php';
    }

    // §A6 — CRUD de feriados (usado por TarifaService::isFeriado() pro
    // adicional de tarifa). Mesmo padrão de servicos()/servicoSalvar() acima.
    public function feriados(): void
    {
        AuthService::requireAuth('admin');
        $feriados = Feriado::listarTodos();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/feriados.php';
    }

    public function feriadoSalvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $data = trim((string)($_POST['data'] ?? ''));
        $nome = trim((string)($_POST['nome'] ?? ''));
        if ($data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $nome === '') {
            $this->redirect('/admin/feriados?erro=1');
        }
        Feriado::criar([
            'data' => $data,
            'nome' => $nome,
            'recorrente_anual' => !empty($_POST['recorrente_anual']),
            'ativo' => true,
        ]);
        $this->redirect('/admin/feriados?salvo=1');
    }

    public function feriadoAlternar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            Feriado::alternarAtivo($id);
        }
        $this->redirect('/admin/feriados');
    }

    public function feriadoRemover(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            Feriado::remover($id);
        }
        $this->redirect('/admin/feriados?removido=1');
    }

    // §A7 — CRUD de cidades-alvo (expansão territorial). Guincheiro é
    // vinculado a uma cidade no cadastro (AuthService::registroGuincho);
    // cliente não tem esse vínculo. Mesmo padrão de feriados() acima.
    public function cidades(): void
    {
        AuthService::requireAuth('admin');
        $cidades = Cidade::listarTodas();
        $guinchosPorCidade = Cidade::contarGuinchosPorCidade();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/cidades.php';
    }

    public function cidadeSalvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $nome = trim((string)($_POST['nome'] ?? ''));
        $uf = strtoupper(trim((string)($_POST['uf'] ?? '')));
        if ($nome === '' || !preg_match('/^[A-Z]{2}$/', $uf)) {
            $this->redirect('/admin/cidades?erro=1');
        }
        Cidade::criar($nome, $uf, !empty($_POST['ativo']));
        $this->redirect('/admin/cidades?salvo=1');
    }

    public function cidadeAlternar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            Cidade::alternarAtivo($id);
        }
        $this->redirect('/admin/cidades');
    }

    /**
     * §PRECO-POR-CIDADE-01: grava o centro geográfico + raio de abrangência
     * de uma cidade-alvo — é o que liga essa cidade ao motor real de
     * precificação (Cidade::resolverPorCoordenada(), consumido por
     * TarifaService e ServicePricingRule). Campos opcionais: deixar em
     * branco remove a geo (a cidade volta a nunca ser resolvida por
     * coordenada, sem quebrar nada — mesmo comportamento de antes desta
     * funcionalidade existir).
     */
    public function cidadeGeoSalvar(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !Cidade::buscarPorId($id)) {
            $this->redirect('/admin/cidades');
        }
        $latRaw = trim((string)($_POST['lat_centro'] ?? ''));
        $lngRaw = trim((string)($_POST['lng_centro'] ?? ''));
        $raioRaw = trim((string)($_POST['raio_km'] ?? ''));
        $lat = $latRaw !== '' ? (float)str_replace(',', '.', $latRaw) : null;
        $lng = $lngRaw !== '' ? (float)str_replace(',', '.', $lngRaw) : null;
        $raio = $raioRaw !== '' ? max(1, (int)$raioRaw) : null;
        Cidade::atualizarGeo($id, $lat, $lng, $raio);
        $this->redirect('/admin/cidades?geo_salva=1');
    }
}


// Método helper: GET /admin/usuario/suspender redireciona com aviso
// (ação real é POST)
