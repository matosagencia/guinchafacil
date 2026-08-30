<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$clienteNome = trim((string)($_SESSION['user']['nome'] ?? 'Cliente'));
$primeiroNome = trim((string)strtok($clienteNome, ' ')) ?: $clienteNome;

function cliente_saudacao(): string
{
    $hora = (int)date('G');
    if ($hora < 12) return 'Bom dia';
    if ($hora < 18) return 'Boa tarde';
    return 'Boa noite';
}
?>
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/css/components/communications.css">
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/css/components/dashboard.css">
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/css/pages/client-dashboard.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">
    <div class="app-dashboard">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Painel do cliente</span>
                <h1>Socorro na estrada, sem complicação</h1>
                <p>Peça, acompanhe e conclua o atendimento em um único fluxo.</p>
            </div>
        </header>

        <?php if (empty($pedidoAndamento)): ?>
        <section class="client-rescue-card mb-4">
            <div class="client-rescue-copy">
                <div class="app-panel__title mb-1"><i class="fas fa-circle-exclamation me-2 text-primary-custom"></i>Onde está o veículo?</div>
                <div class="dash-panel-subtitle">Use o GPS ou digite um ponto de referência.</div>
            </div>
            <form class="quick-rescue__row d-flex gap-2 flex-wrap" method="post" action="<?php echo $bp; ?>/cliente/pedido/rascunho" data-quick-rescue data-reverse-url="<?php echo $bp; ?>/geocode/reverse" data-geocode-url="<?php echo $bp; ?>/geocode">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                <input type="hidden" name="lat_origem" value="<?php echo htmlspecialchars((string)($pedidoRascunho['lat_origem'] ?? '')); ?>">
                <input type="hidden" name="lng_origem" value="<?php echo htmlspecialchars((string)($pedidoRascunho['lng_origem'] ?? '')); ?>">
                <div class="input-group flex-grow-1">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-location-dot text-primary-custom"></i></span>
                    <input type="text" class="form-control quick-rescue__input border-start-0" name="endereco_origem" data-quick-rescue-input value="<?php echo htmlspecialchars((string)($pedidoRascunho['endereco_origem'] ?? '')); ?>" placeholder="Onde está o veículo?" maxlength="220" required>
                </div>
                <div class="input-group" style="max-width:140px;">
                    <span class="input-group-text bg-white border-end-0">Nº</span>
                    <input type="text" class="form-control quick-rescue__input border-start-0" name="numero_origem" data-quick-rescue-number value="<?php echo htmlspecialchars((string)($pedidoRascunho['numero_origem'] ?? '')); ?>" placeholder="12" maxlength="20">
                </div>
                <button type="button" class="btn btn-outline-secondary" data-quick-rescue-gps aria-label="Usar minha localização atual"><i class="fas fa-location-crosshairs"></i></button>
                <button type="submit" class="btn btn-primary quick-rescue__cta" data-action="localize">Pedir socorro</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="app-hero client-hero mb-4">
            <div class="client-hero-info">
                <span class="client-hero-eyebrow"><?php echo cliente_saudacao(); ?></span>
                <h2 class="client-hero-title">Olá, <?php echo htmlspecialchars($primeiroNome); ?>.</h2>
                <p class="client-hero-subtitle">
                    <?php echo !empty($pedidoAndamento)
                        ? 'Seu guincho já está a caminho. Acompanhe tudo em tempo real.'
                        : 'Seu veículo está a poucos passos de receber atendimento.'; ?>
                </p>
                <div class="client-hero-chips">
                    <span class="client-chip"><?php echo (int)($totalVeiculos ?? 0); ?> veículo<?php echo (int)($totalVeiculos ?? 0) === 1 ? '' : 's'; ?></span>
                    <span class="client-chip"><?php echo (int)($totalOficinas ?? 0); ?> oficina<?php echo (int)($totalOficinas ?? 0) === 1 ? '' : 's'; ?></span>
                    <span class="client-chip">Atendimento 24h</span>
                </div>
            </div>
            <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="client-hero-mark" aria-label="Pedir socorro"><i class="fas fa-arrow-up-right-from-square"></i></a>
        </section>

        <?php
        // Catálogo administrável pelo admin (/admin/servicos). Fallback para os
        // 5 atalhos originais só se a tabela ainda não tiver sido semeada —
        // garante que a tela nunca fique vazia por falta de dado de instalação.
        $servicosParaExibir = $servicosCatalogo ?? [];
        if (empty($servicosParaExibir)) {
            $servicosParaExibir = [
                ['chave' => 'reboque_agora', 'nome' => 'Reboque agora', 'descricao' => 'Atendimento imediato', 'tipo_problema' => 'outro', 'icone' => 'fa-truck-pickup', 'cor' => 'tow'],
                ['chave' => 'bateria', 'nome' => 'Bateria', 'descricao' => 'Partida auxiliar', 'tipo_problema' => 'bateria', 'icone' => 'fa-bolt', 'cor' => 'battery'],
                ['chave' => 'pneu', 'nome' => 'Pneu', 'descricao' => 'Troca e suporte', 'tipo_problema' => 'pneu', 'icone' => 'fa-circle-dot', 'cor' => 'tire'],
                ['chave' => 'pane_seca', 'nome' => 'Pane seca', 'descricao' => 'Falta de combustível', 'tipo_problema' => 'combustivel', 'icone' => 'fa-gas-pump', 'cor' => 'fuel'],
                ['chave' => 'agendar', 'nome' => 'Agendar', 'descricao' => 'Transporte programado', 'tipo_problema' => '', 'icone' => 'fa-clock', 'cor' => 'schedule'],
            ];
        }
        ?>
        <div class="client-service-grid mb-4">
            <?php foreach ($servicosParaExibir as $srv): ?>
            <a class="client-service-tile" href="<?php echo $bp; ?>/cliente/pedido/novo<?php echo !empty($srv['tipo_problema']) ? '?tipo=' . urlencode($srv['tipo_problema']) : ''; ?>">
                <div class="client-service-icon client-service-icon--<?php echo htmlspecialchars($srv['cor']); ?>"><i class="fas <?php echo htmlspecialchars($srv['icone']); ?>"></i></div>
                <strong><?php echo htmlspecialchars($srv['nome']); ?></strong>
                <span><?php echo htmlspecialchars((string)($srv['descricao'] ?? '')); ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($incidentesAtivos)): ?>
        <section class="app-panel mb-4" aria-labelledby="incidentesAtivosTitulo">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <span class="eyebrow">Atendimento em andamento</span>
                    <h2 id="incidentesAtivosTitulo" class="h4 mb-0">Acompanhe seu incidente</h2>
                </div>
                <i class="fas fa-user-gear text-warning"></i>
            </div>
            <?php foreach ($incidentesAtivos as $inc): ?>
                <div class="border rounded-3 p-3 mb-2 bg-light-subtle">
                    <div class="d-flex justify-content-between gap-2 flex-wrap">
                        <strong>#<?php echo (int)$inc['id']; ?> · <?php echo htmlspecialchars(ucfirst(str_replace('_',' ', (string)$inc['status']))); ?></strong>
                        <?php if (!empty($inc['atendimento_status'])): ?><span class="badge text-bg-warning"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ', (string)$inc['atendimento_status']))); ?></span><?php endif; ?>
                    </div>
                    <div class="small text-muted mt-1"><?php echo htmlspecialchars($inc['especialista_nome'] ?: 'Buscando especialista'); ?></div>
                    <?php if (!empty($inc['atendimento_status']) || in_array($inc['status'], ['necessita_reboque','em_atendimento','resolvido_local'], true)): ?>
                        <div class="alert alert-info py-2 px-3 small mt-2 mb-0">O atendimento do especialista é cobrado conforme a cotação. Se você solicitar um guincho, o reboque será uma cobrança adicional, confirmada antes do envio.</div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <?php if (($inc['atendimento_status'] ?? '') === 'aguardando_aprovacao' && !empty($inc['atendimento_id'])): ?>
                            <form method="post" action="<?php echo $bp; ?>/cliente/incidente/orcamento/aprovar/<?php echo (int)$inc['atendimento_id']; ?>" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <button class="btn btn-sm btn-warning" type="submit">Aprovar orçamento</button>
                            </form>
                            <form method="post" action="<?php echo $bp; ?>/cliente/incidente/orcamento/recusar/<?php echo (int)$inc['atendimento_id']; ?>" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Recusar</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array($inc['status'], ['necessita_reboque','em_atendimento'], true)): ?>
                            <form method="post" action="<?php echo $bp; ?>/cliente/incidente/reboque/<?php echo (int)$inc['id']; ?>" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <button class="btn btn-sm btn-outline-dark" type="submit">Solicitar guincho</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

            <?php if (!empty($pedidoAndamento)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div id="pedidoAtivoClienteCard"
                         class="dash-active-card"
                         data-status-url="<?php echo $bp; ?>/cliente/pedido/status-json/<?php echo (int)$pedidoAndamento['id']; ?>">
                        <div class="dash-active-card-head">
                            <div class="dash-active-driver">
                                <div id="clienteAtivoFotoWrap">
                                    <?php if (!empty($pedidoAndamento['guincho_foto'])): ?>
                                        <img id="clienteAtivoFoto" class="dash-active-avatar" src="<?php echo htmlspecialchars($pedidoAndamento['guincho_foto']); ?>" alt="Foto do guincho">
                                    <?php else: ?>
                                        <div id="clienteAtivoFotoFallback" class="dash-active-avatar-fallback"><i class="fas fa-truck-pickup"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="dash-active-title" id="clienteAtivoNome"><?php echo htmlspecialchars($pedidoAndamento['guincho_operador'] ?? 'Guincho em atendimento'); ?></div>
                                    <div class="dash-active-subtitle" id="clienteAtivoPlaca"><?php echo htmlspecialchars(trim(($pedidoAndamento['guincho_placa'] ?? '') . (($pedidoAndamento['guincho_uf'] ?? '') ? ' - ' . $pedidoAndamento['guincho_uf'] : ''))); ?></div>
                                </div>
                            </div>
                            <span class="badge text-bg-success" id="clienteAtivoStatusBadge"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pedidoAndamento['status'] ?? ''))); ?></span>
                        </div>

                        <div class="dash-active-route">
                            <div class="dash-active-route-item">
                                <i class="fas fa-location-dot"></i>
                                <div>
                                    <strong>Origem</strong>
                                    <span id="clienteAtivoOrigem"><?php echo htmlspecialchars((string)($pedidoAndamento['endereco_origem'] ?? 'Local da ocorrência')); ?></span>
                                </div>
                            </div>
                            <div class="dash-active-route-item">
                                <i class="fas fa-flag-checkered"></i>
                                <div>
                                    <strong>Destino</strong>
                                    <span id="clienteAtivoDestino"><?php echo htmlspecialchars((string)($pedidoAndamento['endereco_destino'] ?? 'Destino informado')); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="dash-active-kpis">
                            <div class="dash-active-kpi">
                                <strong id="clienteAtivoEta"><?php echo htmlspecialchars((string)($pedidoAndamento['tempo_estimado'] ?? 'Agora')); ?></strong>
                                <span>ETA</span>
                            </div>
                            <div class="dash-active-kpi">
                                <strong id="clienteAtivoKm"><?php echo isset($pedidoAndamento['distancia_km']) ? number_format((float)$pedidoAndamento['distancia_km'], 1, ',', '.') . ' km' : '-'; ?></strong>
                                <span>Trajeto</span>
                            </div>
                            <div class="dash-active-kpi">
                                <strong><i class="fas fa-comments text-primary-custom"></i></strong>
                                <span>Chat do pedido</span>
                            </div>
                        </div>

                        <div class="dash-active-actions">
                            <a href="<?php echo $bp; ?>/cliente/pedido/<?php echo (int)$pedidoAndamento['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye me-2"></i>Acompanhar
                            </a>
                            <a href="<?php echo $bp; ?>/cliente/chat/<?php echo (int)$pedidoAndamento['id']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-comments me-2"></i>Abrir chat
                            </a>
                            <a href="<?php echo $bp; ?>/cliente/chat/<?php echo (int)$pedidoAndamento['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-comments me-2"></i>Chat
                            </a>
                        </div>
                    </div>
                    <div id="statusBannerCliente"
                         class="status-banner-live app-status-inline d-none"
                         data-status="<?php echo htmlspecialchars($pedidoAndamento['status'] ?? ''); ?>"
                         data-status-url="<?php echo $bp; ?>/cliente/pedido/status-json/<?php echo (int)$pedidoAndamento['id']; ?>">
                        <div>
                            <div class="status-banner-title">
                                <i class="fas fa-location-dot text-primary-custom"></i>
                                Pedido #<?php echo (int)$pedidoAndamento['id']; ?>
                            </div>
                            <div class="status-banner-meta">
                                <span data-status-label><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pedidoAndamento['status'] ?? ''))); ?></span>
                                <span data-status-extra><?php echo htmlspecialchars($pedidoAndamento['guincho_operador'] ?? ''); ?></span>
                            </div>
                        </div>
                        <a href="<?php echo $bp; ?>/cliente/pedido/<?php echo (int)$pedidoAndamento['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-arrow-right me-1"></i>Acompanhar
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php if (!empty($comunicados) && empty($pedidoAndamento)): ?>
            <?php $comunicados = $comunicados; include __DIR__ . '/../components/communication_carousel.php'; ?>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="dash-card p-4">
                    <div class="app-panel__header">
                        <div>
                            <h3 class="app-panel__title"><i class="fas fa-map-location-dot me-2 text-primary-custom"></i>Sua Localização</h3>
                            <p class="dash-panel-subtitle">Origem sugerida para o próximo pedido.</p>
                        </div>
                        <button type="button" id="btnAtualizarLocalizacao" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-rotate me-1"></i>Atualizar
                        </button>
                    </div>
                    <div id="map" class="map-container" style="border-radius:22px;min-height:320px"></div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="dash-card p-4 h-100">
                    <div class="app-panel__header">
                        <div>
                            <h3 class="app-panel__title"><i class="fas fa-clock-rotate-left me-2 text-primary-custom"></i>Últimos Pedidos</h3>
                            <p class="dash-panel-subtitle">Resumo rápido das corridas mais recentes.</p>
                        </div>
                        <a href="<?php echo $bp; ?>/cliente/historico" class="btn btn-outline-primary btn-sm">Ver histórico</a>
                    </div>

                    <div class="app-status-list dash-list-scroll">
                        <?php if (!empty($ultimosPedidos)): foreach ($ultimosPedidos as $p): ?>
                        <div class="app-status-item">
                            <div class="client-activity-icon"><i class="fas fa-truck-pickup"></i></div>
                            <div class="client-activity-body">
                                <strong>Pedido #<?php echo (int)$p['id']; ?><?php echo !empty($p['modelo']) ? ' · ' . htmlspecialchars($p['modelo']) : ''; ?></strong>
                                <span><?php echo htmlspecialchars((string)($p['endereco_origem'] ?? 'Origem registrada')); ?><?php echo !empty($p['endereco_destino']) ? ' → ' . htmlspecialchars($p['endereco_destino']) : ''; ?></span>
                                <span class="badge badge-<?php echo $p['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$p['status'])); ?></span>
                            </div>
                        </div>
                        <?php endforeach; else: ?>
                        <div class="app-status-item">
                            <div class="client-activity-icon"><i class="fas fa-circle-info"></i></div>
                            <div class="client-activity-body">
                                <strong>Nenhum pedido recente</strong>
                                <span>Assim que houver novas corridas elas aparecerão aqui.</span>
                            </div>
                            <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="btn btn-sm btn-primary">Criar</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../layouts/footer.php'; ?>

<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/vendor/leaflet/leaflet.css">
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/atendimento-status.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/quick-rescue.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/communications.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', () => {
    let clienteMapaAtual = null;
    let clienteMapaMarcador = null;
    if (typeof MapManager !== 'undefined') clienteMapaAtual = MapManager.init('map');

    function centralizarLocalizacaoCliente(silencioso) {
        const btn = document.getElementById('btnAtualizarLocalizacao');
        if (!clienteMapaAtual || typeof LocationService === 'undefined') return;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Localizando…'; }
        LocationService.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                clienteMapaAtual.setView([lat, lng], 15);
                if (clienteMapaMarcador) {
                    clienteMapaMarcador.setLatLng([lat, lng]);
                } else if (window.L) {
                    clienteMapaMarcador = L.marker([lat, lng]).addTo(clienteMapaAtual).bindPopup('Você está aqui');
                }
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-rotate me-1"></i>Atualizar'; }
            },
            () => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-rotate me-1"></i>Atualizar'; }
                if (!silencioso) alert('Não foi possível obter sua localização. Verifique a permissão de GPS do navegador.');
            }
        );
    }

    const btnAtualizarLocalizacao = document.getElementById('btnAtualizarLocalizacao');
    if (btnAtualizarLocalizacao) {
        btnAtualizarLocalizacao.addEventListener('click', () => centralizarLocalizacaoCliente(false));
        centralizarLocalizacaoCliente(true);
    }
    if (window.AtendimentoStatus) AtendimentoStatus.start({ bannerSelector: '#statusBannerCliente' });

    const pedidoCard = document.getElementById('pedidoAtivoClienteCard');
    if (pedidoCard) {
        const statusUrl = pedidoCard.dataset.statusUrl;
        if (statusUrl) {
            fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => {
                    if (!data || !data.ok) return;

                    const nome = document.getElementById('clienteAtivoNome');
                    const placa = document.getElementById('clienteAtivoPlaca');
                    const status = document.getElementById('clienteAtivoStatusBadge');
                    const origem = document.getElementById('clienteAtivoOrigem');
                    const destino = document.getElementById('clienteAtivoDestino');
                    const eta = document.getElementById('clienteAtivoEta');
                    const km = document.getElementById('clienteAtivoKm');
                    const foto = document.getElementById('clienteAtivoFoto');
                    const fallback = document.getElementById('clienteAtivoFotoFallback');

                    if (nome && data.guincho_nome) nome.textContent = data.guincho_nome;
                    if (placa) {
                        const uf = data.guincho_uf ? ` - ${data.guincho_uf}` : '';
                        placa.textContent = data.guincho_placa ? `${data.guincho_placa}${uf}` : placa.textContent;
                    }
                    if (status && data.status_label) status.textContent = data.status_label;
                    if (origem && data.routing && data.routing.origem) origem.textContent = data.routing.origem;
                    if (destino && data.routing && data.routing.destino) destino.textContent = data.routing.destino;
                    if (eta && data.routing && (data.routing.eta || data.routing.tempo_estimado)) eta.textContent = data.routing.eta || data.routing.tempo_estimado;
                    if (km && data.routing && (data.routing.distance_km || data.routing.distancia_km)) {
                        const distancia = data.routing.distance_km ?? data.routing.distancia_km;
                        km.textContent = `${Number(distancia).toFixed(1).replace('.', ',')} km`;
                    }
                    if (data.guincho_foto && foto) {
                        foto.src = data.guincho_foto;
                        foto.style.display = 'block';
                        if (fallback) fallback.style.display = 'none';
                    }
                })
                .catch(() => {});
        }
    }
});
</script>

