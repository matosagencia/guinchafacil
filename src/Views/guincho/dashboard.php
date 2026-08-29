<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$operadorNome = trim((string)($_SESSION['user']['nome'] ?? 'Operador'));

/** Formata segundos decorridos como "Agora" / "X min" / "Xh" para a fila de ofertas. */
function tow_relative_time(?int $segundos): string
{
    if ($segundos === null) return '-';
    if ($segundos < 60) return 'Agora';
    $min = (int)floor($segundos / 60);
    if ($min < 60) return $min . ' min';
    return (int)floor($min / 60) . 'h';
}

/** Formata segundos decorridos desde o último ponto POR para o painel Proof-of-Road. */
function tow_format_elapsed(?int $segundos): string
{
    if ($segundos === null) return '-';
    if ($segundos < 60) return $segundos . ' s';
    $min = (int)floor($segundos / 60);
    if ($min < 60) return $min . ' min';
    return (int)floor($min / 60) . 'h';
}

/** Saudação real baseada no horário do servidor (não é texto fixo). */
function tow_saudacao(): string
{
    $hora = (int)date('G');
    if ($hora < 12) return 'Bom dia';
    if ($hora < 18) return 'Boa tarde';
    return 'Boa noite';
}
?>
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/css/components/communications.css">
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/css/components/dashboard.css">
<!-- Pacote L2.1/L2.2 (escopo único desta página): tokens.css e themes/tow.css
     já são incluídos globalmente pelo header.php (para o perfil guincho);
     só falta o CSS desta página, que resolve o descompasso de classes
     .tow-* sem CSS real definido. -->
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/css/pages/tow-dashboard.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">
    <div class="app-dashboard">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Painel operacional</span>
                <h1>Pronto para a próxima corrida</h1>
                <p>Ofertas, localização e ganhos em tempo real.</p>
            </div>
        </header>

        <?php foreach ($_SESSION['_flash'] ?? [] as $flash): unset($_SESSION['_flash']); ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($avisoCelulaPedraMorta)): ?>
        <!-- §CELULAS-NITEROI-01 (05/08/2026): aviso PERSISTENTE (não some
             sozinho, aparece toda vez que o guincho abre o painel enquanto
             a célula dele estiver 'pedra_morta') — evita que o prestador
             interprete "0 chamados" como bug do sistema. -->
        <div class="alert alert-warning" role="status">
            <strong>Região em fase de validação.</strong>
            <?php echo htmlspecialchars($avisoCelulaPedraMorta); ?>
        </div>
        <?php endif; ?>

                <section class="app-hero tow-hero mb-4">
            <div class="tow-hero-info">
                <span class="tow-hero-eyebrow"><?php echo tow_saudacao(); ?></span>
                <h2 class="tow-hero-title"><?php echo htmlspecialchars($operadorNome); ?></h2>
                <p class="tow-hero-subtitle">
                    Área de cobertura: <?php echo (int)$raioCoberturaKm; ?> km ·
                    GPS <span id="heroGpsQualidade">verificando…</span>
                </p>
            </div>
            <div class="tow-hero-switch">
                <span id="labelDisponivel"><?php echo $disponivel ? 'Online' : 'Offline'; ?></span>
                <label class="toggle-switch mb-0">
                    <input type="checkbox" id="toggleDisponivel" <?php echo $disponivel ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        <!-- §GPS-CONFIRM-01: modal bloqueante de confirmação de localização.
             Renderizado sempre; só é aberto via JS quando $precisaConfirmarLocalizacao
             é true (a cada login, e a cada 4h dentro da mesma sessão). Sem
             backdrop dispensável e sem botão de fechar — não dá pra contornar
             pela UI (o servidor também reforça isso em toggleDisponibilidade()). -->
        <div class="modal fade" id="modalConfirmarLocalizacao" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-location-dot text-primary-custom me-2"></i>Confirme sua localização</h5>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Pra receber ofertas de pedidos perto de você, precisamos confirmar onde você está agora.</p>
                        <p class="text-muted small mb-2">Isso é pedido a cada login (e a cada poucas horas) porque sua posição pode mudar — sem uma localização atual, o sistema não consegue calcular corretamente a distância até os pedidos.</p>
                        <p class="text-muted small mb-3"><i class="fas fa-hand-pointer me-1"></i>Se o pino não estiver exatamente onde você está, arraste-o no mapa até o ponto certo antes de confirmar.</p>
                        <div id="mapConfirmarLocalizacao" style="height: 300px; border-radius: 14px; overflow: hidden; border: 1px solid var(--theme-border);"></div>
                        <div id="confirmarLocalizacaoStatus" class="small text-muted mt-2"></div>
                        <div id="confirmarLocalizacaoErro" class="alert alert-danger small d-none mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="btnConfirmarLocalizacao">
                            <i class="fas fa-check me-1"></i>Confirmar esta posição
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($pedidoAtivo): ?>
        <section class="tow-active-card mb-4"
             id="pedidoAtivoGuinchoCard"
             data-status-url="<?php echo $bp; ?>/guincho/pedido/status-json/<?php echo (int)$pedidoAtivo['id']; ?>">
            <div class="tow-active-card-head">
                <div class="tow-active-driver">
                    <div class="tow-active-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="tow-active-title" id="guinchoAtivoCliente"><?php echo htmlspecialchars($pedidoAtivo['cliente_nome'] ?? 'Cliente em atendimento'); ?></div>
                        <div class="tow-active-subtitle" id="guinchoAtivoPedido">Pedido #<?php echo (int)$pedidoAtivo['id']; ?></div>
                    </div>
                </div>
                <span class="badge text-bg-warning" id="guinchoAtivoStatusBadge"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pedidoAtivo['status'] ?? ''))); ?></span>
            </div>

            <div class="tow-active-route">
                <div class="tow-active-route-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Origem</strong>
                        <span id="guinchoAtivoOrigem"><?php echo htmlspecialchars((string)($pedidoAtivo['endereco_origem'] ?? 'Origem registrada')); ?></span>
                    </div>
                </div>
                <div class="tow-active-route-item">
                    <i class="fas fa-flag-checkered"></i>
                    <div>
                        <strong>Destino</strong>
                        <span id="guinchoAtivoDestino"><?php echo htmlspecialchars((string)($pedidoAtivo['endereco_destino'] ?? 'Destino informado')); ?></span>
                    </div>
                </div>
            </div>

            <div class="tow-active-kpis">
                <div class="tow-active-kpi">
                    <strong id="guinchoAtivoEta"><?php echo htmlspecialchars((string)($pedidoAtivo['tempo_estimado'] ?? 'Agora')); ?></strong>
                    <span>ETA</span>
                </div>
                <div class="tow-active-kpi">
                    <strong id="guinchoAtivoKm"><?php echo isset($pedidoAtivo['distancia_km']) ? number_format((float)$pedidoAtivo['distancia_km'], 1, ',', '.') . ' km' : '-'; ?></strong>
                    <span>Trajeto</span>
                </div>
                <div class="tow-active-kpi">
                    <strong><i class="fas fa-comments text-primary-custom"></i></strong>
                    <span>Chat do pedido</span>
                </div>
            </div>

            <div class="tow-active-actions">
                <a href="<?php echo $bp; ?>/guincho/atendimento/<?php echo (int)$pedidoAtivo['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-arrow-right me-2"></i>Continuar
                </a>
                <a href="<?php echo $bp; ?>/guincho/atendimento/<?php echo (int)$pedidoAtivo['id']; ?>#chatBox" class="btn btn-outline-secondary">
                    <i class="fas fa-comments me-2"></i>Abrir chat
                </a>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($pedidoAtivo): ?>
        <div id="statusBannerGuincho" class="status-banner-live d-none"
             data-status="<?php echo htmlspecialchars($pedidoAtivo['status'] ?? ''); ?>"
             data-status-url="<?php echo $bp; ?>/guincho/pedido/status-json/<?php echo (int)$pedidoAtivo['id']; ?>">
            <i class="fas fa-truck-fast fa-2x"></i>
            <div class="flex-grow-1">
                <div class="status-banner-title">Corrida em andamento</div>
                <div class="status-banner-meta">
                    <span data-status-label><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pedidoAtivo['status'] ?? ''))); ?></span>
                    <span data-status-extra><?php echo htmlspecialchars($pedidoAtivo['cliente_nome'] ?? ''); ?></span>
                </div>
            </div>
            <a href="<?php echo $bp; ?>/guincho/atendimento/<?php echo (int)$pedidoAtivo['id']; ?>"
               class="btn btn-warning btn-sm">Continuar atendimento</a>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?php echo $bp; ?>/guincho/historico">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="tow-stat-value"><?php echo (int)$atendimentosHoje; ?></div>
                        <div class="tow-stat-label">Atendimentos hoje</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?php echo $bp; ?>/guincho/financeiro">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-coins"></i></div>
                        <div class="tow-stat-value">R$<?php echo number_format((float)$ganhoHoje, 0, ',', '.'); ?></div>
                        <div class="tow-stat-label">Ganho hoje</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?php echo $bp; ?>/guincho/perfil">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-star"></i></div>
                        <div class="tow-stat-value"><?php echo $notaMedia > 0 ? number_format((float)$notaMedia, 1) : '-'; ?></div>
                        <div class="tow-stat-label">Nota média</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?php echo $bp; ?>/guincho/operacao">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-road"></i></div>
                        <div class="tow-stat-value"><?php echo number_format((float)$kmPercorridos, 0, ',', '.'); ?></div>
                        <div class="tow-stat-label">Km hoje</div>
                    </div>
                </a>
            </div>
        </div>

        <?php if (empty($pedidoAtivo) && empty($pedidoPendente) && !empty($comunicados)): ?>
            <?php $comunicados = $comunicados; include __DIR__ . '/../components/communication_carousel.php'; ?>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="tow-card p-4">
                    <div class="tow-panel-header">
                        <div>
                            <h3 class="tow-panel-title"><i class="fas fa-bolt me-2 text-primary-custom"></i>Nova solicitação</h3>
                            <p class="tow-panel-subtitle">Aceite antes que o tempo expire — depois disso, outro guincho pode ser chamado.</p>
                        </div>
                    </div>
                    <div id="ofertaAtivaContainer">
                        <?php if ($pedidoPendente): $p = $pedidoPendente; ?>
                        <div class="tow-offer" data-pedido-id="<?php echo (int)$p['id']; ?>">
                            <div class="tow-offer-head">
                                <div>
                                    <span class="tow-offer-eyebrow">Nova solicitação</span>
                                    <h4 class="tow-offer-title">Pedido #<?php echo (int)$p['id']; ?></h4>
                                    <p class="tow-offer-subtitle">
                                        <?php echo htmlspecialchars(trim(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? ''))); ?><?php echo !empty($p['tipo_problema']) ? ' · ' . htmlspecialchars($p['tipo_problema']) : ''; ?>
                                    </p>
                                </div>
                                <span class="tow-offer-timer" id="ofertaTimer" data-expira="<?php echo htmlspecialchars((string)($p['expiracao_aceite'] ?? '')); ?>">--:--</span>
                            </div>
                            <div class="tow-offer-metrics">
                                <div class="tow-offer-metric"><span>Até o cliente</span><strong><?php echo number_format((float)$p['distancia_km'], 1, ',', '.'); ?> km</strong></div>
                                <div class="tow-offer-metric"><span>Chegada</span><strong><?php echo $p['eta_min'] !== null ? (int)$p['eta_min'] . ' min' : '-'; ?></strong></div>
                                <div class="tow-offer-metric"><span>Serviço</span><strong><?php echo number_format((float)$p['distancia_servico_km'], 1, ',', '.'); ?> km</strong></div>
                                <div class="tow-offer-metric"><span>Valor</span><strong>R$ <?php echo number_format((float)$p['custo_estimado'], 2, ',', '.'); ?></strong></div>
                            </div>
                            <div class="tow-offer-route">
                                <div class="tow-offer-route-item"><span class="tow-offer-dot tow-offer-dot--origin"></span><?php echo htmlspecialchars($p['endereco_origem']); ?></div>
                                <div class="tow-offer-route-item"><span class="tow-offer-dot tow-offer-dot--dest"></span><?php echo htmlspecialchars($p['endereco_destino']); ?></div>
                            </div>
                            <div id="map" class="map-container" style="height:220px;border-radius:var(--radius-md);margin:var(--space-3) 0 var(--space-4)"></div>
                            <div class="tow-offer-actions">
                                <form method="post" action="<?php echo $bp; ?>/guincho/recusar/<?php echo (int)$p['id']; ?>" class="flex-grow-1 m-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                    <button type="submit" class="btn btn-outline-secondary w-100">Recusar</button>
                                </form>
                                <a href="<?php echo $bp; ?>/guincho/aceitar/<?php echo (int)$p['id']; ?>" class="btn btn-success flex-grow-1">Aceitar</a>
                            </div>
                        </div>
                        <?php elseif ($pedidoAtivo): ?>
                        <div class="text-center py-4" style="color:var(--theme-muted)">
                            <i class="fas fa-truck-fast fa-2x mb-2 d-block"></i>
                            <p class="mb-0">Você já tem um atendimento em andamento.<br>Novas ofertas só aparecem depois de concluí-lo.</p>
                        </div>
                        <div id="map" class="map-container d-none" style="height:0"></div>
                        <?php elseif (!$disponivel): ?>
                        <div class="text-center py-4" style="color:var(--theme-muted)">
                            <i class="fas fa-toggle-off fa-2x mb-2 d-block"></i>
                            <p class="mb-0">Você está <strong>offline</strong>.<br>Ative o toggle para receber pedidos.</p>
                        </div>
                        <div id="map" class="map-container d-none" style="height:0"></div>
                        <?php else: ?>
                        <div class="text-center py-4" style="color:var(--theme-muted)">
                            <i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i>
                            <p class="mb-0">Aguardando novas solicitações...</p>
                        </div>
                        <div id="map" class="map-container d-none" style="height:0"></div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            <div class="col-lg-4 d-flex flex-column gap-4">
                <section class="tow-card p-4 flex-fill" id="fila-ofertas">
                    <div class="tow-panel-header">
                        <div>
                            <h3 class="tow-panel-title"><i class="fas fa-list-ol me-2 text-primary-custom"></i>Fila de ofertas</h3>
                            <p class="tow-panel-subtitle">Ordenada por compatibilidade</p>
                        </div>
                    </div>
                    <div id="filaOfertasContainer" class="tow-queue">
                        <?php if (!empty($ofertasFila)): foreach ($ofertasFila as $o): ?>
                        <div class="tow-queue-item">
                            <div>
                                <strong>#<?php echo (int)$o['id']; ?> · <?php echo htmlspecialchars($o['bairro']); ?></strong>
                                <span><?php echo number_format((float)$o['distancia_km'], 1, ',', '.'); ?> km · R$ <?php echo number_format((float)$o['custo_estimado'], 2, ',', '.'); ?></span>
                            </div>
                            <span class="tow-queue-time"><?php echo htmlspecialchars(tow_relative_time($o['segundos_desde_criacao'])); ?></span>
                        </div>
                        <?php endforeach; elseif ($pedidoAtivo): ?>
                        <div class="text-center py-4" style="color:var(--theme-muted)">
                            <i class="fas fa-truck-fast fa-2x mb-2 d-block"></i>
                            <p class="mb-0">Atendimento em andamento.</p>
                        </div>
                        <?php elseif (!$disponivel): ?>
                        <div class="text-center py-4" style="color:var(--theme-muted)">
                            <i class="fas fa-toggle-off fa-2x mb-2 d-block"></i>
                            <p class="mb-0">Você está <strong>offline</strong>.</p>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4" style="color:var(--theme-muted)">
                            <i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i>
                            <p class="mb-0">Nenhuma oferta compatível no momento.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="tow-card p-4 flex-fill">
                    <div class="tow-panel-header">
                        <div>
                            <h3 class="tow-panel-title"><i class="fas fa-satellite me-2 text-primary-custom"></i>Proof-of-Road</h3>
                            <p class="tow-panel-subtitle">Qualidade do rastreamento</p>
                        </div>
                    </div>
                    <?php if ($porPanel): ?>
                    <div class="tow-por-list" id="porPanel" data-pedido-id="<?php echo (int)$pedidoAtivo['id']; ?>" data-status-url="<?php echo $bp; ?>/guincho/pedido/status-json/<?php echo (int)$pedidoAtivo['id']; ?>">
                        <div class="tow-por-row">
                            <span>GPS ativo</span>
                            <strong id="porGpsAtivo" class="<?php echo $porPanel['gps_ativo'] ? 'text-success' : 'text-danger'; ?>"><?php echo $porPanel['gps_ativo'] ? 'Sim ✓' : 'Não'; ?></strong>
                        </div>
                        <div class="tow-por-row">
                            <span>Precisão atual</span>
                            <strong id="porPrecisao"><?php echo $porPanel['precisao_m'] !== null ? number_format((float)$porPanel['precisao_m'], 0, ',', '.') . ' m' : '-'; ?></strong>
                        </div>
                        <div class="tow-por-row">
                            <span>Qualidade</span>
                            <strong id="porQualidade"><?php echo $porPanel['qualidade_pct'] !== null ? (int)$porPanel['qualidade_pct'] . '%' : '-'; ?></strong>
                        </div>
                        <div class="tow-por-row">
                            <span>Rota íntegra</span>
                            <strong id="porRotaIntegra" class="<?php echo $porPanel['rota_integra'] ? 'text-success' : 'text-danger'; ?>"><?php echo $porPanel['rota_integra'] ? 'Sim' : 'Não'; ?></strong>
                        </div>
                        <div class="tow-por-row">
                            <span>Pontos válidos</span>
                            <strong id="porPontos"><?php echo (int)$porPanel['pontos_validos']; ?></strong>
                        </div>
                        <div class="tow-por-row">
                            <span>Pontos em fila</span>
                            <strong id="porPontosFila" title="Pontos capturados neste navegador que ainda não foram sincronizados com o servidor (guardados localmente por falta de conexão).">0</strong>
                        </div>
                        <div class="tow-por-row">
                            <span>Última sincronização</span>
                            <strong id="porUltimaSincronizacao"><?php echo htmlspecialchars(tow_format_elapsed($porPanel['segundos_desde_ultimo_ponto'])); ?></strong>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4" style="color:var(--theme-muted)">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        <p class="mb-0">Sem atendimento em andamento.<br>O rastreamento aparece aqui durante uma corrida.</p>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/vendor/leaflet/leaflet.css">
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/core/offline-queue.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/atendimento-status.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/communications.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken ?? ''); ?>';
const BP = '<?php echo $bp; ?>';
const PRECISA_CONFIRMAR_LOCALIZACAO = <?php echo $precisaConfirmarLocalizacao ? 'true' : 'false'; ?>;

/** §GPS-CONFIRM-01: modal bloqueante — pede navigator.geolocation e envia
 * pra /guincho/localizacao (mesma rota usada pelo rastreamento em
 * atendimento ativo). Só fecha o modal quando o servidor confirma sucesso;
 * em caso de erro/recusa, mostra o motivo e deixa tentar de novo. */
let modalConfirmarLocalizacaoInstance = null;
function abrirModalConfirmarLocalizacao() {
    const el = document.getElementById('modalConfirmarLocalizacao');
    if (!el || typeof bootstrap === 'undefined') return;

    // §GPS-CONFIRM-02: .app-dashboard tem `isolation: isolate` (dashboard.css)
    // pra conter os círculos decorativos do fundo — isso sem querer cria um
    // novo contexto de empilhamento que prende o modal (mesmo sendo
    // position:fixed) ATRÁS do backdrop do Bootstrap, que é anexado direto
    // no <body>. Move o modal pra ser filho direto do <body> antes de abrir,
    // assim ele compete pelo z-index no MESMO contexto que o backdrop.
    if (el.parentElement !== document.body) {
        document.body.appendChild(el);
    }

    if (!modalConfirmarLocalizacaoInstance) {
        modalConfirmarLocalizacaoInstance = new bootstrap.Modal(el, { backdrop: 'static', keyboard: false });
    }
    modalConfirmarLocalizacaoInstance.show();
}
function fecharModalConfirmarLocalizacao() {
    if (modalConfirmarLocalizacaoInstance) modalConfirmarLocalizacaoInstance.hide();
}

/** §GPS-CONFIRM-03: a confirmação por navigator.geolocation "às cegas" (sem
 * o guincho ver/ajustar onde caiu o pino) foi trocada por um mapa com
 * marcador ARRASTÁVEL — reaproveita window.MapManager (mesmo helper do mapa
 * de ofertas) e o padrão de ícone via L.divIcon + Font Awesome já usado em
 * cliente/pedidostatus.php (evita o problema clássico do Leaflet de ícone
 * padrão quebrado por path de asset). Só envia pro servidor a posição REAL
 * do pino depois que o operador confirma — se o GPS do navegador vier
 * impreciso/errado, ele ajusta manualmente antes de clicar. */
let mapConfirmarLocalizacaoInstance = null;
let markerConfirmarLocalizacao = null;

function pinConfirmarLocalizacaoIcon() {
    return L.divIcon({
        className: '',
        html: '<i class="fas fa-truck-pickup" style="color:#2fb34a; font-size:28px; text-shadow:0 0 3px rgba(0,0,0,.6);"></i>',
        iconSize: [28, 28],
        iconAnchor: [14, 24],
    });
}

function iniciarMapaConfirmarLocalizacao() {
    const statusEl = document.getElementById('confirmarLocalizacaoStatus');
    if (!mapConfirmarLocalizacaoInstance && window.MapManager && window.L) {
        mapConfirmarLocalizacaoInstance = window.MapManager.init('mapConfirmarLocalizacao');
    }
    const map = mapConfirmarLocalizacaoInstance;
    if (!map) return;

    // Modal acabou de ficar visível — sem isso o Leaflet mede o container
    // com display:none e o mapa renderiza com 0x0 (bug clássico de mapa
    // dentro de modal).
    setTimeout(() => { try { map.invalidateSize(); } catch (e) {} }, 50);

    function colocarMarcador(lat, lng) {
        if (markerConfirmarLocalizacao) {
            markerConfirmarLocalizacao.setLatLng([lat, lng]);
        } else {
            markerConfirmarLocalizacao = L.marker([lat, lng], {
                draggable: true,
                icon: pinConfirmarLocalizacaoIcon(),
            }).addTo(map);
        }
        map.setView([lat, lng], 16);
    }

    // Posição inicial: tenta o GPS do navegador; se falhar/recusar, deixa o
    // pino no centro padrão do mapa (São Paulo) e pede pro operador arrastar
    // manualmente até o lugar certo — nunca deixa sem NENHUM pino.
    if (!markerConfirmarLocalizacao) {
        const centroPadrao = map.getCenter();
        colocarMarcador(centroPadrao.lat, centroPadrao.lng);

        if (navigator.geolocation) {
            statusEl.textContent = 'Localizando você...';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    colocarMarcador(pos.coords.latitude, pos.coords.longitude);
                    statusEl.textContent = 'Pino posicionado pelo GPS do navegador. Arraste se precisar ajustar.';
                },
                (err) => {
                    const msg = window.GpsResilience ? window.GpsResilience.geoErrorMessage(err) : 'Não deu pra acessar seu GPS automaticamente.';
                    statusEl.textContent = msg + ' Arraste o pino no mapa até sua posição real antes de confirmar.';
                },
                { enableHighAccuracy: true, maximumAge: 15000, timeout: 15000 }
            );
        } else {
            statusEl.textContent = 'Seu navegador não suporta GPS automático. Arraste o pino no mapa até sua posição real.';
        }
    }
}

function iniciarConfirmacaoLocalizacao() {
    const btn = document.getElementById('btnConfirmarLocalizacao');
    const erroEl = document.getElementById('confirmarLocalizacaoErro');
    const modalEl = document.getElementById('modalConfirmarLocalizacao');
    if (!btn || !modalEl) return;

    // O mapa só pode ser inicializado depois que o modal está DE FATO visível
    // (ver iniciarMapaConfirmarLocalizacao) — Bootstrap dispara esse evento
    // exatamente nesse momento, então plugamos a inicialização nele em vez
    // de no show().
    modalEl.addEventListener('shown.bs.modal', iniciarMapaConfirmarLocalizacao);

    btn.addEventListener('click', async () => {
        erroEl.classList.add('d-none');
        if (!markerConfirmarLocalizacao) {
            erroEl.textContent = 'Aguarde o mapa carregar antes de confirmar.';
            erroEl.classList.remove('d-none');
            return;
        }

        const pos = markerConfirmarLocalizacao.getLatLng();
        btn.disabled = true;
        btn.textContent = 'Confirmando...';

        try {
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('latitude', pos.lat);
            fd.append('longitude', pos.lng);
            fd.append('pedido_id', '0');

            const data = await window.apiFetch(BP + '/guincho/localizacao', { method: 'POST', body: fd });
            if (data.ok) {
                fecharModalConfirmarLocalizacao();
                const gpsEl = document.getElementById('heroGpsQualidade');
                if (gpsEl) gpsEl.textContent = 'confirmado no mapa';
                mostrarToast('Localização confirmada.', 'success');
            } else {
                erroEl.textContent = data.erro || 'Não foi possível confirmar a localização. Tente de novo.';
                erroEl.classList.remove('d-none');
            }
        } catch (e) {
            erroEl.textContent = 'Falha de conexão ao enviar a localização. Tente de novo.';
            erroEl.classList.remove('d-none');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Confirmar esta posição';
        }
    });
}

/** Timer regressivo do card "Nova solicitação" (mm:ss até expiracao_aceite). */
function iniciarOfertaTimer() {
    const timerEl = document.getElementById('ofertaTimer');
    if (!timerEl) return;
    const expiraStr = timerEl.dataset.expira;
    if (!expiraStr) { timerEl.textContent = '--:--'; return; }
    const expiraMs = new Date(expiraStr.replace(' ', 'T')).getTime();
    if (Number.isNaN(expiraMs)) { timerEl.textContent = '--:--'; return; }

    function tick() {
        const el = document.getElementById('ofertaTimer');
        if (!el) return; // card foi re-renderizado/removido
        const restanteMs = expiraMs - Date.now();
        if (restanteMs <= 0) { el.textContent = '00:00'; return; }
        const totalSeg = Math.floor(restanteMs / 1000);
        const mm = String(Math.floor(totalSeg / 60)).padStart(2, '0');
        const ss = String(totalSeg % 60).padStart(2, '0');
        el.textContent = `${mm}:${ss}`;
        setTimeout(tick, 1000);
    }
    tick();
}

/** Classifica a precisão real do GPS do navegador (não é texto fixo). */
function iniciarGpsQualidadeHero() {
    const el = document.getElementById('heroGpsQualidade');
    if (!el) return;
    if (!navigator.geolocation) { el.textContent = 'indisponível'; return; }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const acc = pos.coords.accuracy;
            if (acc <= 20) el.textContent = 'com boa precisão';
            else if (acc <= 75) el.textContent = 'com precisão razoável';
            else el.textContent = 'com sinal fraco';
        },
        () => { el.textContent = 'sem permissão de localização'; },
        { enableHighAccuracy: true, maximumAge: 15000, timeout: 10000 }
    );
}

document.addEventListener('DOMContentLoaded', () => {
    // §A4 — declarado aqui (não só dentro do bloco de ofertas mais abaixo)
    // porque o #map inicial renderizado pelo servidor e o #map recriado por
    // renderOferta() são o MESMO elemento — a instância precisa ser
    // rastreada desde a primeira inicialização pra destroy() funcionar já
    // no primeiro ciclo de renderOferta().
    let ofertaMapInstance = null;
    if (typeof MapManager !== 'undefined') ofertaMapInstance = MapManager.init('map');
    if (window.AtendimentoStatus) AtendimentoStatus.start({ bannerSelector: '#statusBannerGuincho' });
    iniciarOfertaTimer();
    iniciarGpsQualidadeHero();
    iniciarConfirmacaoLocalizacao();
    if (PRECISA_CONFIRMAR_LOCALIZACAO) abrirModalConfirmarLocalizacao();

    const pedidoAtivoCard = document.getElementById('pedidoAtivoGuinchoCard');
    const porPanel = document.getElementById('porPanel');
    const statusUrl = (pedidoAtivoCard && pedidoAtivoCard.dataset.statusUrl) || (porPanel && porPanel.dataset.statusUrl);

    if (porPanel) {
        atualizarPontosEmFila();
        const filaPolling = setInterval(atualizarPontosEmFila, 5000);
        if (window.SessionManager) window.SessionManager.registerPolling(filaPolling);
    }

    function atualizarPorPanel(porSnapshot) {
        if (!porPanel || !porSnapshot) return;
        const ultimoValido = porSnapshot.last_valid_point;
        const ultimoPonto = porSnapshot.last_point;
        const resumo = porSnapshot.summary_destino || porSnapshot.summary_origem;

        const gpsEl = document.getElementById('porGpsAtivo');
        const precisaoEl = document.getElementById('porPrecisao');
        const qualidadeEl = document.getElementById('porQualidade');
        const rotaEl = document.getElementById('porRotaIntegra');
        const pontosEl = document.getElementById('porPontos');
        const syncEl = document.getElementById('porUltimaSincronizacao');

        const ultimoTimestamp = (ultimoValido && ultimoValido.server_timestamp) || (ultimoPonto && ultimoPonto.server_timestamp);
        let segundosDesde = null;
        if (ultimoTimestamp) {
            segundosDesde = Math.max(0, Math.floor((Date.now() - new Date(ultimoTimestamp.replace(' ', 'T')).getTime()) / 1000));
        }
        const gpsAtivo = !!ultimoValido && segundosDesde !== null && segundosDesde <= 120;

        if (gpsEl) {
            gpsEl.textContent = gpsAtivo ? 'Sim ✓' : 'Não';
            gpsEl.className = gpsAtivo ? 'text-success' : 'text-danger';
        }
        if (precisaoEl) precisaoEl.textContent = (ultimoValido && ultimoValido.accuracy_m != null) ? `${Math.round(Number(ultimoValido.accuracy_m))} m` : '-';
        if (qualidadeEl) qualidadeEl.textContent = (porSnapshot.qualidade_pct != null) ? `${porSnapshot.qualidade_pct}%` : '-';
        if (rotaEl) {
            const integra = porSnapshot.rota_integra !== false;
            rotaEl.textContent = integra ? 'Sim' : 'Não';
            rotaEl.className = integra ? 'text-success' : 'text-danger';
        }
        if (pontosEl) pontosEl.textContent = resumo ? (resumo.valid_points ?? 0) : 0;
        if (syncEl) {
            if (segundosDesde === null) { syncEl.textContent = '-'; }
            else if (segundosDesde < 60) { syncEl.textContent = `${segundosDesde} s`; }
            else if (segundosDesde < 3600) { syncEl.textContent = `${Math.floor(segundosDesde / 60)} min`; }
            else { syncEl.textContent = `${Math.floor(segundosDesde / 3600)}h`; }
        }
    }

    /**
     * "Pontos em fila" reflete o buffer offline REAL (IndexedDB, ver
     * public/assets/js/core/offline-queue.js), não um dado do servidor —
     * o servidor nunca vê pontos que ainda não foram enviados. Como
     * IndexedDB é por origem, esta leitura enxerga o mesmo armazenamento
     * usado por guincho/atendimento.php (aba onde o GPS de fato roda).
     */
    function atualizarPontosEmFila() {
        const el = document.getElementById('porPontosFila');
        if (!el || !window.PorOfflineQueue) return;
        window.PorOfflineQueue.count().then((n) => { el.textContent = String(n); }).catch(() => {});
    }

    if (statusUrl) {
        const atualizarStatus = () => fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (!data || !data.ok) return;

                const cliente = document.getElementById('guinchoAtivoCliente');
                const status = document.getElementById('guinchoAtivoStatusBadge');
                const origem = document.getElementById('guinchoAtivoOrigem');
                const destino = document.getElementById('guinchoAtivoDestino');
                const eta = document.getElementById('guinchoAtivoEta');
                const km = document.getElementById('guinchoAtivoKm');

                if (cliente && data.cliente_nome) cliente.textContent = data.cliente_nome;
                if (status && data.status_label) status.textContent = data.status_label;
                if (origem && data.routing && data.routing.origem) origem.textContent = data.routing.origem;
                if (destino && data.routing && data.routing.destino) destino.textContent = data.routing.destino;
                if (eta && data.routing && (data.routing.eta || data.routing.tempo_estimado)) eta.textContent = data.routing.eta || data.routing.tempo_estimado;
                if (km && data.routing && (data.routing.distance_km || data.routing.distancia_km)) {
                    const distancia = data.routing.distance_km ?? data.routing.distancia_km;
                    km.textContent = `${Number(distancia).toFixed(1).replace('.', ',')} km`;
                }

                atualizarPorPanel(data.por_snapshot);
            })
            .catch(() => {});

        atualizarStatus();
        if (porPanel) {
            const porPolling = setInterval(atualizarStatus, 15000);
            if (window.SessionManager) window.SessionManager.registerPolling(porPolling);
        }
    }

    const toggle = document.getElementById('toggleDisponivel');
    const label  = document.getElementById('labelDisponivel');
    if (!toggle) return;

    const ofertaContainer = document.getElementById('ofertaAtivaContainer');
    const filaContainer = document.getElementById('filaOfertasContainer');
    let pedidosPolling = null;
    let pedidosSse = null;
    let pedidosSseRetry = null;

    function escapeHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function relativeTime(segundos) {
        if (segundos == null) return '-';
        if (segundos < 60) return 'Agora';
        const min = Math.floor(segundos / 60);
        if (min < 60) return `${min} min`;
        return `${Math.floor(min / 60)}h`;
    }

    function renderOferta(p) {
        if (!ofertaContainer) return;
        if (typeof MapManager !== 'undefined') { MapManager.destroy(ofertaMapInstance); ofertaMapInstance = null; }
        if (!p) {
            ofertaContainer.innerHTML = '<div class="text-center py-4" style="color:var(--theme-muted)"><i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i><p class="mb-0">Aguardando novas solicitações...</p></div><div id="map" class="map-container d-none" style="height:0"></div>';
            return;
        }
        const subtitulo = [`${p.marca ?? ''} ${p.modelo ?? ''}`.trim(), p.tipo_problema].filter(Boolean).join(' · ');
        ofertaContainer.innerHTML = `
            <div class="tow-offer" data-pedido-id="${p.id}">
                <div class="tow-offer-head">
                    <div>
                        <span class="tow-offer-eyebrow">Nova solicitação</span>
                        <h4 class="tow-offer-title">Pedido #${p.id}</h4>
                        <p class="tow-offer-subtitle">${escapeHtml(subtitulo)}</p>
                    </div>
                    <span class="tow-offer-timer" id="ofertaTimer" data-expira="${escapeHtml(p.expiracao_aceite || '')}">--:--</span>
                </div>
                <div class="tow-offer-metrics">
                    <div class="tow-offer-metric"><span>Até o cliente</span><strong>${Number(p.distancia_km).toFixed(1).replace('.', ',')} km</strong></div>
                    <div class="tow-offer-metric"><span>Chegada</span><strong>${p.eta_min != null ? p.eta_min + ' min' : '-'}</strong></div>
                    <div class="tow-offer-metric"><span>Serviço</span><strong>${Number(p.distancia_servico_km ?? 0).toFixed(1).replace('.', ',')} km</strong></div>
                    <div class="tow-offer-metric"><span>Valor</span><strong>R$ ${Number(p.custo_estimado).toFixed(2).replace('.', ',')}</strong></div>
                </div>
                <div class="tow-offer-route">
                    <div class="tow-offer-route-item"><span class="tow-offer-dot tow-offer-dot--origin"></span>${p.endereco_origem}</div>
                    <div class="tow-offer-route-item"><span class="tow-offer-dot tow-offer-dot--dest"></span>${p.endereco_destino}</div>
                </div>
                <div id="map" class="map-container" style="height:220px;border-radius:var(--radius-md);margin:var(--space-3) 0 var(--space-4)"></div>
                <div class="tow-offer-actions">
                    <form method="post" action="${BP}/guincho/recusar/${p.id}" class="flex-grow-1 m-0">
                        <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                        <button type="submit" class="btn btn-outline-secondary w-100">Recusar</button>
                    </form>
                    <a href="${BP}/guincho/aceitar/${p.id}" class="btn btn-success flex-grow-1">Aceitar</a>
                </div>
            </div>`;
        if (typeof MapManager !== 'undefined') ofertaMapInstance = MapManager.init('map');
        iniciarOfertaTimer();
    }

    function renderFila(lista) {
        if (!filaContainer) return;
        if (!lista || lista.length === 0) {
            filaContainer.innerHTML = '<div class="text-center py-4" style="color:var(--theme-muted)"><i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i><p class="mb-0">Nenhuma oferta compatível no momento.</p></div>';
            return;
        }
        filaContainer.innerHTML = lista.map((o) => `
            <div class="tow-queue-item">
                <div>
                    <strong>#${o.id} · ${o.bairro || ''}</strong>
                    <span>${Number(o.distancia_km).toFixed(1).replace('.', ',')} km · R$ ${o.custo_estimado}</span>
                </div>
                <span class="tow-queue-time">${relativeTime(o.segundos_desde_criacao)}</span>
            </div>`).join('');
    }

    function renderPedidosOffline() {
        renderOferta(null);
        if (ofertaContainer) ofertaContainer.innerHTML = '<div class="text-center py-4" style="color:var(--theme-muted)"><i class="fas fa-toggle-off fa-2x mb-2 d-block"></i><p class="mb-0">Você está <strong>offline</strong>.<br>Ative o toggle para receber pedidos.</p></div><div id="map" class="map-container d-none" style="height:0"></div>';
        if (filaContainer) filaContainer.innerHTML = '<div class="text-center py-4" style="color:var(--theme-muted)"><i class="fas fa-toggle-off fa-2x mb-2 d-block"></i><p class="mb-0">Você está <strong>offline</strong>.</p></div>';
    }

    function encerrarPedidosSse() {
        if (pedidosSseRetry) {
            clearTimeout(pedidosSseRetry);
            pedidosSseRetry = null;
        }
        if (pedidosSse) {
            pedidosSse.close();
            pedidosSse = null;
        }
    }

    function reagendarPedidosSse() {
        if (window.SessionManager && window.SessionManager.isExpired && window.SessionManager.isExpired()) return;
        if (pedidosSseRetry) clearTimeout(pedidosSseRetry);
        pedidosSseRetry = setTimeout(iniciarPedidosSse, 4000);
    }

    async function checarPedidos() {
        if (!toggle.checked) return;
        try {
            const data = await window.apiFetch(BP + '/guincho/pedidos-disponiveis?_=' + Date.now());

            if (data.ok) {
                renderOferta(data.pedidos.length > 0 ? data.pedidos[0] : null);
                renderFila(data.pedidos);
            }
        } catch (e) { console.error('Falha ao checar pedidos', e); }
    }

    function iniciarPedidosSse() {
        if (!window.EventSource || !toggle.checked) return;

        encerrarPedidosSse();
        pedidosSse = new EventSource(BP + '/sse/pedidos');

        // O evento SSE só avisa que "algo mudou" — a fila completa (ordenada
        // por compatibilidade) é sempre buscada de novo via checarPedidos(),
        // para manter o card de oferta e a fila com os mesmos campos
        // enriquecidos (bairro, ETA, distância de serviço etc.).
        pedidosSse.addEventListener('novo_pedido', () => checarPedidos());

        pedidosSse.addEventListener('session_expired', () => {
            encerrarPedidosSse();
            if (window.SessionManager) window.SessionManager.handleUnauthorized();
        });

        pedidosSse.addEventListener('stream_close', () => {
            encerrarPedidosSse();
            checarPedidos();
        });

        pedidosSse.onerror = () => {
            encerrarPedidosSse();
            reagendarPedidosSse();
        };
    }

    checarPedidos();
    iniciarPedidosSse();
    pedidosPolling = setInterval(checarPedidos, 45000);
    if (window.SessionManager) window.SessionManager.registerPolling(pedidosPolling);
    document.addEventListener('guincha:session-expired', encerrarPedidosSse);
    window.addEventListener('beforeunload', encerrarPedidosSse);

    toggle.addEventListener('change', async function () {
        const desejado = this.checked ? 1 : 0;
        toggle.disabled = true;
        label.textContent = 'Salvando...';

        try {
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('disponivel', desejado ? '1' : '0');

            const data = await window.apiFetch(BP + '/guincho/disponibilidade', {
                method: 'POST',
                body: fd
            });

            if (data.ok) {
                label.textContent = data.disponivel ? 'Online' : 'Offline';
                toggle.checked = !!data.disponivel;
                if (data.disponivel) {
                    checarPedidos();
                    iniciarPedidosSse();
                } else {
                    encerrarPedidosSse();
                    renderPedidosOffline();
                }
            } else {
                toggle.checked = !desejado;
                label.textContent = !desejado ? 'Online' : 'Offline';
                mostrarToast(data.erro || 'Tente novamente.', 'danger');
                if (data.precisa_confirmar_localizacao) abrirModalConfirmarLocalizacao();
            }
        } catch (e) {
            toggle.checked = !desejado;
            label.textContent = !desejado ? 'Online' : 'Offline';
        } finally {
            toggle.disabled = false;
        }
    });
});

function mostrarToast(msg, tipo = 'danger') {
    const container = document.getElementById('toastContainer') || (() => {
        const el = document.createElement('div');
        el.id = 'toastContainer';
        el.style.cssText = 'position:fixed;top:70px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px';
        document.body.appendChild(el);
        return el;
    })();
    const toast = document.createElement('div');
    toast.className = `alert alert-${tipo} alert-dismissible shadow mb-0`;
    toast.style.cssText = 'min-width:260px;max-width:380px;animation:fadeIn .2s';
    toast.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${msg}
        <button type="button" class="btn-close" data-dismiss-parent=".alert"></button>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>

