<?php
/**
 * Central Operacional — Fase 1 (shell/navegação) + Fase 2 (API real) da
 * remodelação do backoffice admin (Pacote L2.3). A fila de pedidos ainda
 * vem renderizada no servidor (AdminController::centralOperacional(), dado
 * real do banco, paint instantâneo sem esperar fetch). O painel de detalhe
 * do pedido selecionado (visão geral, mapa, timeline, chat) consome a API
 * real construída em src/Api/Admin/OrdersApiController.php — GET
 * /api/admin/orders/{id}, /tracking, /timeline, /messages e POST /messages —
 * sem reload de página.
 *
 * @var array $worklist
 * @var array $resumoOperacional
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input
            type="search"
            id="opsGlobalSearch"
            placeholder="Buscar por pedido, cliente, placa ou telefone"
            autocomplete="off"
        >
    </div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status">
            <span class="ops-topbar__status-dot"></span>
            Operação normal
        </span>
        <span id="opsLastUpdate">Atualizado agora</span>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo operacional">
    <article class="ops-metric">
        <span class="ops-metric__label">Pedidos ativos</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['ativos']; ?></strong>
        <span class="ops-metric__trend">Agora</span>
    </article>
    <article class="ops-metric <?php echo $resumoOperacional['sem_prestador'] > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Sem prestador</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['sem_prestador']; ?></strong>
        <span class="ops-metric__trend">Requer ação</span>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">A caminho</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['a_caminho']; ?></strong>
        <span class="ops-metric__trend">Em trânsito</span>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Em atendimento</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['em_atendimento']; ?></strong>
        <span class="ops-metric__trend">No local</span>
    </article>
    <article class="ops-metric <?php echo $resumoOperacional['alertas_criticos'] > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Alertas críticos</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['alertas_criticos']; ?></strong>
        <span class="ops-metric__trend">Prioridade</span>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-truck me-1"></i>Guinchos online</span>
        <strong class="ops-metric__value"><?php echo (int)($guinchoOnlineResumo ?? 0); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-dollar-sign me-1"></i>Receita hoje</span>
        <strong class="ops-metric__value">R$<?php echo number_format((float)($receitaHojeResumo ?? 0), 0, ',', '.'); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-clock me-1"></i>ETA médio</span>
        <strong class="ops-metric__value"><?php echo $etaMedioResumo !== null ? $etaMedioResumo . ' min' : '—'; ?></strong>
    </article>
</section>

<!-- §CELULAS-NITEROI-01 (04/08/2026): "Mapa operacional ao vivo" movido do
     Dashboard pra cá — mostra todos os guinchos disponíveis/em atendimento
     de uma vez, complementando a fila de pedido único da Central. -->
<div class="card mx-3 mb-3" style="margin-top:12px;">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="fas fa-map-location-dot me-2"></i>Mapa operacional ao vivo
            <i class="fas fa-info-circle ms-1 hint-icon" title="Mostra todos os guinchos disponíveis ou em atendimento ativo ao mesmo tempo. Cor do marcador do guincho: verde = disponível; amarelo = a caminho de buscar o veículo; vermelho = em reboque, levando o veículo ao destino (volta a verde ao concluir). A linha da rota segue a mesma lógica (laranja tracejado indo buscar, azul levando ao destino); a trilha atrás do guincho é o trajeto GPS já validado, e a linha à frente é a rota restante calculada até o alvo atual."></i>
        </span>
        <span class="badge text-bg-success" id="mapaAtualizadoBadge">Atualizado agora</span>
    </div>
    <div class="card-body p-0">
        <div id="mapaOperacional" style="height:360px;border-radius:0 0 var(--radius-card,12px) var(--radius-card,12px)"></div>
    </div>
</div>

<div class="shell-ops" id="opsShell">

    <aside class="shell-ops-sidebar" id="opsSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Fila de pedidos ativos">
        <header class="ops-worklist-header">
            <span class="eyebrow">Operação</span>
            <h2>Pedidos ativos</h2>
            <p><span id="opsWorklistCount"><?php echo count($worklist); ?></span> ocorrências encontradas</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="opsWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="opsWorklistResults">
            <?php if (empty($worklist)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum pedido ativo no momento.
                </div>
            <?php else: ?>
                <?php foreach ($worklist as $i => $w): ?>
                    <button
                        type="button"
                        class="ops-worklist-item <?php echo $w['prioridade'] === 'critical' ? 'is-critical' : ($w['prioridade'] === 'warning' ? 'is-warning' : ''); ?>"
                        data-order-id="<?php echo (int)$w['id']; ?>"
                        data-search-blob="<?php echo htmlspecialchars(mb_strtolower($w['codigo'] . ' ' . $w['cliente_nome'] . ' ' . $w['veiculo_resumo']), ENT_QUOTES, 'UTF-8'); ?>"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                    >
                        <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                        <span class="ops-worklist-item__content">
                            <span class="ops-worklist-item__top">
                                <strong><?php echo htmlspecialchars($w['codigo']); ?></strong>
                                <span class="ops-badge ops-badge--<?php echo htmlspecialchars($w['status_css']); ?>">
                                    <?php echo htmlspecialchars($w['status_label']); ?>
                                </span>
                            </span>
                            <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($w['cliente_nome']); ?></span>
                            <span class="ops-worklist-item__vehicle"><?php echo htmlspecialchars($w['veiculo_resumo']); ?></span>
                            <span class="ops-worklist-item__footer">
                                <span><?php echo htmlspecialchars($w['alerta_resumo'] ?: ($w['guincho_operador'] ? 'Prestador: ' . $w['guincho_operador'] : 'Sem prestador atribuído')); ?></span>
                                <span>Há <?php echo (int)$w['minutos_decorridos']; ?> min</span>
                            </span>
                        </span>
                        <span class="ops-worklist-item__signals">
                            <?php if ($w['prioridade'] === 'critical'): ?>
                                <span class="ops-signal is-danger" title="Alerta crítico">
                                    <i class="fas fa-triangle-exclamation"></i>
                                </span>
                            <?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="opsWorkspace" aria-live="polite">
        <?php if (empty($worklist)): ?>
            <div class="ops-empty-state" style="padding:80px 20px">
                <i class="fas fa-inbox"></i>
                Nenhum pedido selecionado.
            </div>
        <?php endif; ?>
        <!-- Preenchido via JS a partir de window.__opsWorklistData (ver script no fim do arquivo). -->
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/admin-order-workspace.js?v=20260815-1"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
AdminOrderWorkspace.init({
    shellId: 'opsShell',
    resultsId: 'opsWorklistResults',
    workspaceId: 'opsWorkspace',
    worklistSearchId: 'opsWorklistSearch',
    globalSearchId: 'opsGlobalSearch',
    apiBase: '<?php echo addslashes($bp); ?>/api/admin/orders',
    csrfToken: <?php echo json_encode($csrfToken); ?>,
    worklistData: <?php echo json_encode($worklist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    osrmBaseUrl: <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>,
    emptyLabel: 'Nenhum pedido selecionado.'
});
</script>

<style>
.mapa-marker { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; color:#fff; font-size:.85rem; box-shadow:0 4px 10px rgba(0,0,0,.35); }
.mapa-marker--cliente { background:#ef4444; }
.mapa-marker--cliente-concluido { background:#6c757d; }
.mapa-marker--guincho { background:#2fb34a; }
.mapa-marker--guincho-ativo { background:#f59e0b; }
.mapa-marker--guincho-reboque { background:#dc3545; }
/* Balão do mapa (leaflet-popup) já padronizado globalmente em
   public/assets/css/base.css — vale pra este e todos os outros mapas do
   sistema, sem precisar duplicar aqui. */
</style>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', function () {
    (function initMapaOperacional() {
        const el = document.getElementById('mapaOperacional');
        if (!el || typeof L === 'undefined') return;
        const basePath = <?php echo json_encode($bp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const mapaJsonUrl = `${basePath}/admin/dashboard/mapa-json`;
        const map = L.map(el).setView([-23.55052, -46.633308], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

        const iconCliente = L.divIcon({
            className: 'mapa-marker mapa-marker--cliente',
            html: '<i class="fas fa-map-pin"></i>',
            iconSize: [28, 28],
        });
        const iconClienteConcluido = L.divIcon({
            className: 'mapa-marker mapa-marker--cliente-concluido',
            html: '<i class="fas fa-map-pin"></i>',
            iconSize: [28, 28],
        });
        const iconDestino = L.divIcon({
            className: 'mapa-marker mapa-marker--cliente-concluido',
            html: '<i class="fas fa-flag-checkered"></i>',
            iconSize: [28, 28],
        });
        const iconGuincho = L.divIcon({
            className: 'mapa-marker mapa-marker--guincho',
            html: '<i class="fas fa-truck-pickup"></i>',
            iconSize: [28, 28],
        });
        const iconGuinchoAtivo = L.divIcon({
            className: 'mapa-marker mapa-marker--guincho-ativo',
            html: '<i class="fas fa-truck-fast"></i>',
            iconSize: [28, 28],
        });
        const iconGuinchoReboque = L.divIcon({
            className: 'mapa-marker mapa-marker--guincho-reboque',
            html: '<i class="fas fa-truck-fast"></i>',
            iconSize: [28, 28],
        });

        let marcadores = [];
        let rotaSyncEpoch = 0;

        // URL do serviço de roteamento OSRM-compatible, centralizada via config
        // 'por_road_match_base_url' (item #37).
        const OSRM_BASE_URL = <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>;

        function buildOsrmUrl(points) {
            const coords = points.map((p) => String(p.lng) + ',' + String(p.lat)).join(';');
            return OSRM_BASE_URL + '/route/v1/driving/' + coords + '?overview=full&geometries=geojson';
        }

        function desenharRotaRestante(origemPt, destinoPt, epoch, indoPara) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 6000);
            const style = indoPara === 'destino'
                ? { color: '#0d6efd', weight: 4, opacity: 0.75, dashArray: '6,8' }
                : { color: '#f59e0b', weight: 3, opacity: 0.65, dashArray: '6,8' };

            fetch(buildOsrmUrl([origemPt, destinoPt]), { signal: controller.signal })
                .then((r) => r.ok ? r.json() : null)
                .then((payload) => {
                    if (epoch !== rotaSyncEpoch) return;
                    const coords = payload && payload.routes && payload.routes[0] && payload.routes[0].geometry
                        ? payload.routes[0].geometry.coordinates
                        : null;
                    let layer;
                    if (Array.isArray(coords) && coords.length >= 2) {
                        layer = L.polyline(coords.map((c) => [c[1], c[0]]), style).addTo(map);
                    } else {
                        layer = L.polyline([[origemPt.lat, origemPt.lng], [destinoPt.lat, destinoPt.lng]], style).addTo(map);
                    }
                    marcadores.push(layer);
                })
                .catch(() => {
                    if (epoch !== rotaSyncEpoch) return;
                    const layer = L.polyline([[origemPt.lat, origemPt.lng], [destinoPt.lat, destinoPt.lng]], style).addTo(map);
                    marcadores.push(layer);
                })
                .finally(() => clearTimeout(timeoutId));
        }

        async function syncMapa() {
            try {
                const resp = await fetch(mapaJsonUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!resp.ok) return;
                const payload = await resp.json();
                if (!payload || payload.ok !== true) return;

                marcadores.forEach((m) => map.removeLayer(m));
                marcadores = [];
                rotaSyncEpoch += 1;
                const epochAtual = rotaSyncEpoch;

                (payload.clientes || []).forEach((c) => {
                    const chegouNaOrigem = ['no_local', 'em_reboque'].includes(String(c.status || ''));
                    const marker = L.marker([c.lat, c.lng], { icon: chegouNaOrigem ? iconClienteConcluido : iconCliente })
                        .bindPopup(`<strong>Pedido #${c.pedido_id}</strong><br>${c.label}<br>${c.endereco || ''}`);
                    marker.addTo(map);
                    marcadores.push(marker);

                    if (c.lat_destino !== null && c.lng_destino !== null) {
                        const destinoMarker = L.marker([Number(c.lat_destino), Number(c.lng_destino)], { icon: iconDestino })
                            .bindPopup(`<strong>Destino do pedido #${c.pedido_id}</strong><br>${c.endereco_destino || ''}`);
                        destinoMarker.addTo(map);
                        marcadores.push(destinoMarker);
                        desenharRotaRestante(
                            { lat: Number(c.lat), lng: Number(c.lng) },
                            { lat: Number(c.lat_destino), lng: Number(c.lng_destino) },
                            epochAtual,
                            'destino'
                        );
                    }
                });

                (payload.guinchos || []).forEach((g) => {
                    const emReboque = g.em_atendimento && g.rota && g.rota.indo_para === 'destino';
                    const icon = !g.em_atendimento ? iconGuincho : (emReboque ? iconGuinchoReboque : iconGuinchoAtivo);
                    const statusTxt = g.em_atendimento
                        ? `Em atendimento — Pedido #${g.pedido_id} (${g.pedido_status || ''})`
                        : 'Disponível';
                    const marker = L.marker([g.lat, g.lng], { icon: icon })
                        .bindPopup(`<strong>${g.label}</strong><br>${g.placa || ''}<br>${statusTxt}`);
                    marker.addTo(map);
                    marcadores.push(marker);

                    if (g.em_atendimento && g.rota) {
                        const trail = g.rota.trail || [];
                        if (trail.length >= 2) {
                            const trailColor = g.rota.indo_para === 'destino' ? '#0d6efd' : '#f59e0b';
                            const trailLine = L.polyline(trail.map((p) => [p.lat, p.lng]), {
                                color: trailColor,
                                weight: 4,
                                opacity: 0.85,
                            }).addTo(map);
                            marcadores.push(trailLine);
                        }

                        if (g.rota.target_lat !== null && g.rota.target_lng !== null) {
                            const ultimoPonto = trail.length > 0
                                ? { lat: trail[trail.length - 1].lat, lng: trail[trail.length - 1].lng }
                                : { lat: g.lat, lng: g.lng };
                            desenharRotaRestante(ultimoPonto, { lat: g.rota.target_lat, lng: g.rota.target_lng }, epochAtual, g.rota.indo_para);

                            const targetIcon = L.divIcon({
                                className: 'mapa-marker mapa-marker--cliente',
                                html: g.rota.indo_para === 'origem' ? '<i class="fas fa-flag"></i>' : '<i class="fas fa-flag-checkered"></i>',
                                iconSize: [24, 24],
                            });
                            const targetMarker = L.marker([g.rota.target_lat, g.rota.target_lng], { icon: targetIcon })
                                .bindPopup(g.rota.indo_para === 'origem' ? 'Indo buscar o veículo' : 'Indo para o destino');
                            targetMarker.addTo(map);
                            marcadores.push(targetMarker);
                        }
                    }
                });

                const badge = document.getElementById('mapaAtualizadoBadge');
                if (badge) badge.textContent = `Atualizado ${payload.atualizado_em || ''}`;
            } catch (error) {
                console.warn('Falha ao sincronizar mapa operacional:', error);
            }
        }

        syncMapa();
        window.setInterval(syncMapa, 10000);
    })();
});
</script>

<?php
// Não usa layouts/footer.php aqui: aquele parcial fecha </main></div> de
// .main-wrapper/.main-content (markup do layout antigo de 2 colunas), que
// esta página não abre — ela usa .shell-ops (grid próprio). Fechamento
// mínimo equivalente, reaproveitando só o bootstrap bundle (necessário
// pro modal de sessão expirada do header.php).
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
/**
 * Central Operacional — Fase 1 (shell/navegação) + Fase 2 (API real) da
 * remodelação do backoffice admin (Pacote L2.3). A fila de pedidos ainda
 * vem renderizada no servidor (AdminController::centralOperacional(), dado
 * real do banco, paint instantâneo sem esperar fetch). O painel de detalhe
 * do pedido selecionado (visão geral, mapa, timeline, chat) consome a API
 * real construída em src/Api/Admin/OrdersApiController.php — GET
 * /api/admin/orders/{id}, /tracking, /timeline, /messages e POST /messages —
 * sem reload de página.
 *
 * @var array $worklist
 * @var array $resumoOperacional
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input
            type="search"
            id="opsGlobalSearch"
            placeholder="Buscar por pedido, cliente, placa ou telefone"
            autocomplete="off"
        >
    </div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status">
            <span class="ops-topbar__status-dot"></span>
            Operação normal
        </span>
        <span id="opsLastUpdate">Atualizado agora</span>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo operacional">
    <article class="ops-metric">
        <span class="ops-metric__label">Pedidos ativos</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['ativos']; ?></strong>
        <span class="ops-metric__trend">Agora</span>
    </article>
    <article class="ops-metric <?php echo $resumoOperacional['sem_prestador'] > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Sem prestador</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['sem_prestador']; ?></strong>
        <span class="ops-metric__trend">Requer ação</span>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">A caminho</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['a_caminho']; ?></strong>
        <span class="ops-metric__trend">Em trânsito</span>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Em atendimento</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['em_atendimento']; ?></strong>
        <span class="ops-metric__trend">No local</span>
    </article>
    <article class="ops-metric <?php echo $resumoOperacional['alertas_criticos'] > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Alertas críticos</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoOperacional['alertas_criticos']; ?></strong>
        <span class="ops-metric__trend">Prioridade</span>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-truck me-1"></i>Guinchos online</span>
        <strong class="ops-metric__value"><?php echo (int)($guinchoOnlineResumo ?? 0); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-dollar-sign me-1"></i>Receita hoje</span>
        <strong class="ops-metric__value">R$<?php echo number_format((float)($receitaHojeResumo ?? 0), 0, ',', '.'); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-clock me-1"></i>ETA médio</span>
        <strong class="ops-metric__value"><?php echo $etaMedioResumo !== null ? $etaMedioResumo . ' min' : '—'; ?></strong>
    </article>
</section>

<!-- §CELULAS-NITEROI-01 (04/08/2026): "Mapa operacional ao vivo" movido do
     Dashboard pra cá — mostra todos os guinchos disponíveis/em atendimento
     de uma vez, complementando a fila de pedido único da Central. -->
<div class="card mx-3 mb-3" style="margin-top:12px;">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="fas fa-map-location-dot me-2"></i>Mapa operacional ao vivo
            <i class="fas fa-info-circle ms-1 hint-icon" title="Mostra todos os guinchos disponíveis ou em atendimento ativo ao mesmo tempo. Cor do marcador do guincho: verde = disponível; amarelo = a caminho de buscar o veículo; vermelho = em reboque, levando o veículo ao destino (volta a verde ao concluir). A linha da rota segue a mesma lógica (laranja tracejado indo buscar, azul levando ao destino); a trilha atrás do guincho é o trajeto GPS já validado, e a linha à frente é a rota restante calculada até o alvo atual."></i>
        </span>
        <span class="badge text-bg-success" id="mapaAtualizadoBadge">Atualizado agora</span>
    </div>
    <div class="card-body p-0">
        <div id="mapaOperacional" style="height:360px;border-radius:0 0 var(--radius-card,12px) var(--radius-card,12px)"></div>
    </div>
</div>

<div class="shell-ops" id="opsShell">

    <aside class="shell-ops-sidebar" id="opsSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Fila de pedidos ativos">
        <header class="ops-worklist-header">
            <span class="eyebrow">Operação</span>
            <h2>Pedidos ativos</h2>
            <p><span id="opsWorklistCount"><?php echo count($worklist); ?></span> ocorrências encontradas</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="opsWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="opsWorklistResults">
            <?php if (empty($worklist)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum pedido ativo no momento.
                </div>
            <?php else: ?>
                <?php foreach ($worklist as $i => $w): ?>
                    <button
                        type="button"
                        class="ops-worklist-item <?php echo $w['prioridade'] === 'critical' ? 'is-critical' : ($w['prioridade'] === 'warning' ? 'is-warning' : ''); ?>"
                        data-order-id="<?php echo (int)$w['id']; ?>"
                        data-search-blob="<?php echo htmlspecialchars(mb_strtolower($w['codigo'] . ' ' . $w['cliente_nome'] . ' ' . $w['veiculo_resumo']), ENT_QUOTES, 'UTF-8'); ?>"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                    >
                        <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                        <span class="ops-worklist-item__content">
                            <span class="ops-worklist-item__top">
                                <strong><?php echo htmlspecialchars($w['codigo']); ?></strong>
                                <span class="ops-badge ops-badge--<?php echo htmlspecialchars($w['status_css']); ?>">
                                    <?php echo htmlspecialchars($w['status_label']); ?>
                                </span>
                            </span>
                            <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($w['cliente_nome']); ?></span>
                            <span class="ops-worklist-item__vehicle"><?php echo htmlspecialchars($w['veiculo_resumo']); ?></span>
                            <span class="ops-worklist-item__footer">
                                <span><?php echo htmlspecialchars($w['alerta_resumo'] ?: ($w['guincho_operador'] ? 'Prestador: ' . $w['guincho_operador'] : 'Sem prestador atribuído')); ?></span>
                                <span>Há <?php echo (int)$w['minutos_decorridos']; ?> min</span>
                            </span>
                        </span>
                        <span class="ops-worklist-item__signals">
                            <?php if ($w['prioridade'] === 'critical'): ?>
                                <span class="ops-signal is-danger" title="Alerta crítico">
                                    <i class="fas fa-triangle-exclamation"></i>
                                </span>
                            <?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="opsWorkspace" aria-live="polite">
        <?php if (empty($worklist)): ?>
            <div class="ops-empty-state" style="padding:80px 20px">
                <i class="fas fa-inbox"></i>
                Nenhum pedido selecionado.
            </div>
        <?php endif; ?>
        <!-- Preenchido via JS a partir de window.__opsWorklistData (ver script no fim do arquivo). -->
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/admin-order-workspace.js?v=20260802-1"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
AdminOrderWorkspace.init({
    shellId: 'opsShell',
    resultsId: 'opsWorklistResults',
    workspaceId: 'opsWorkspace',
    worklistSearchId: 'opsWorklistSearch',
    globalSearchId: 'opsGlobalSearch',
    apiBase: '<?php echo addslashes($bp); ?>/api/admin/orders',
    csrfToken: <?php echo json_encode($csrfToken); ?>,
    worklistData: <?php echo json_encode($worklist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    osrmBaseUrl: <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>,
    emptyLabel: 'Nenhum pedido selecionado.'
});
</script>

<style>
.mapa-marker { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; color:#fff; font-size:.85rem; box-shadow:0 4px 10px rgba(0,0,0,.35); }
.mapa-marker--cliente { background:#ef4444; }
.mapa-marker--cliente-concluido { background:#6c757d; }
.mapa-marker--guincho { background:#2fb34a; }
.mapa-marker--guincho-ativo { background:#f59e0b; }
.mapa-marker--guincho-reboque { background:#dc3545; }
/* Balão do mapa (leaflet-popup) já padronizado globalmente em
   public/assets/css/base.css — vale pra este e todos os outros mapas do
   sistema, sem precisar duplicar aqui. */
</style>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', function () {
    (function initMapaOperacional() {
        const el = document.getElementById('mapaOperacional');
        if (!el || typeof L === 'undefined') return;
        const basePath = <?php echo json_encode($bp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const mapaJsonUrl = `${basePath}/admin/dashboard/mapa-json`;
        const map = L.map(el).setView([-23.55052, -46.633308], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

        const iconCliente = L.divIcon({
            className: 'mapa-marker mapa-marker--cliente',
            html: '<i class="fas fa-map-pin"></i>',
            iconSize: [28, 28],
        });
        const iconClienteConcluido = L.divIcon({
            className: 'mapa-marker mapa-marker--cliente-concluido',
            html: '<i class="fas fa-map-pin"></i>',
            iconSize: [28, 28],
        });
        const iconDestino = L.divIcon({
            className: 'mapa-marker mapa-marker--cliente-concluido',
            html: '<i class="fas fa-flag-checkered"></i>',
            iconSize: [28, 28],
        });
        const iconGuincho = L.divIcon({
            className: 'mapa-marker mapa-marker--guincho',
            html: '<i class="fas fa-truck-pickup"></i>',
            iconSize: [28, 28],
        });
        const iconGuinchoAtivo = L.divIcon({
            className: 'mapa-marker mapa-marker--guincho-ativo',
            html: '<i class="fas fa-truck-fast"></i>',
            iconSize: [28, 28],
        });
        const iconGuinchoReboque = L.divIcon({
            className: 'mapa-marker mapa-marker--guincho-reboque',
            html: '<i class="fas fa-truck-fast"></i>',
            iconSize: [28, 28],
        });

        let marcadores = [];
        let rotaSyncEpoch = 0;

        // URL do serviço de roteamento OSRM-compatible, centralizada via config
        // 'por_road_match_base_url' (item #37).
        const OSRM_BASE_URL = <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>;

        function buildOsrmUrl(points) {
            const coords = points.map((p) => String(p.lng) + ',' + String(p.lat)).join(';');
            return OSRM_BASE_URL + '/route/v1/driving/' + coords + '?overview=full&geometries=geojson';
        }

        function desenharRotaRestante(origemPt, destinoPt, epoch, indoPara) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 6000);
            const style = indoPara === 'destino'
                ? { color: '#0d6efd', weight: 4, opacity: 0.75, dashArray: '6,8' }
                : { color: '#f59e0b', weight: 3, opacity: 0.65, dashArray: '6,8' };

            fetch(buildOsrmUrl([origemPt, destinoPt]), { signal: controller.signal })
                .then((r) => r.ok ? r.json() : null)
                .then((payload) => {
                    if (epoch !== rotaSyncEpoch) return;
                    const coords = payload && payload.routes && payload.routes[0] && payload.routes[0].geometry
                        ? payload.routes[0].geometry.coordinates
                        : null;
                    let layer;
                    if (Array.isArray(coords) && coords.length >= 2) {
                        layer = L.polyline(coords.map((c) => [c[1], c[0]]), style).addTo(map);
                    } else {
                        layer = L.polyline([[origemPt.lat, origemPt.lng], [destinoPt.lat, destinoPt.lng]], style).addTo(map);
                    }
                    marcadores.push(layer);
                })
                .catch(() => {
                    if (epoch !== rotaSyncEpoch) return;
                    const layer = L.polyline([[origemPt.lat, origemPt.lng], [destinoPt.lat, destinoPt.lng]], style).addTo(map);
                    marcadores.push(layer);
                })
                .finally(() => clearTimeout(timeoutId));
        }

        async function syncMapa() {
            try {
                const resp = await fetch(mapaJsonUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!resp.ok) return;
                const payload = await resp.json();
                if (!payload || payload.ok !== true) return;

                marcadores.forEach((m) => map.removeLayer(m));
                marcadores = [];
                rotaSyncEpoch += 1;
                const epochAtual = rotaSyncEpoch;

                (payload.clientes || []).forEach((c) => {
                    const chegouNaOrigem = ['no_local', 'em_reboque'].includes(String(c.status || ''));
                    const marker = L.marker([c.lat, c.lng], { icon: chegouNaOrigem ? iconClienteConcluido : iconCliente })
                        .bindPopup(`<strong>Pedido #${c.pedido_id}</strong><br>${c.label}<br>${c.endereco || ''}`);
                    marker.addTo(map);
                    marcadores.push(marker);

                    if (c.lat_destino !== null && c.lng_destino !== null) {
                        const destinoMarker = L.marker([Number(c.lat_destino), Number(c.lng_destino)], { icon: iconDestino })
                            .bindPopup(`<strong>Destino do pedido #${c.pedido_id}</strong><br>${c.endereco_destino || ''}`);
                        destinoMarker.addTo(map);
                        marcadores.push(destinoMarker);
                        desenharRotaRestante(
                            { lat: Number(c.lat), lng: Number(c.lng) },
                            { lat: Number(c.lat_destino), lng: Number(c.lng_destino) },
                            epochAtual,
                            'destino'
                        );
                    }
                });

                (payload.guinchos || []).forEach((g) => {
                    const emReboque = g.em_atendimento && g.rota && g.rota.indo_para === 'destino';
                    const icon = !g.em_atendimento ? iconGuincho : (emReboque ? iconGuinchoReboque : iconGuinchoAtivo);
                    const statusTxt = g.em_atendimento
                        ? `Em atendimento — Pedido #${g.pedido_id} (${g.pedido_status || ''})`
                        : 'Disponível';
                    const marker = L.marker([g.lat, g.lng], { icon: icon })
                        .bindPopup(`<strong>${g.label}</strong><br>${g.placa || ''}<br>${statusTxt}`);
                    marker.addTo(map);
                    marcadores.push(marker);

                    if (g.em_atendimento && g.rota) {
                        const trail = g.rota.trail || [];
                        if (trail.length >= 2) {
                            const trailColor = g.rota.indo_para === 'destino' ? '#0d6efd' : '#f59e0b';
                            const trailLine = L.polyline(trail.map((p) => [p.lat, p.lng]), {
                                color: trailColor,
                                weight: 4,
                                opacity: 0.85,
                            }).addTo(map);
                            marcadores.push(trailLine);
                        }

                        if (g.rota.target_lat !== null && g.rota.target_lng !== null) {
                            const ultimoPonto = trail.length > 0
                                ? { lat: trail[trail.length - 1].lat, lng: trail[trail.length - 1].lng }
                                : { lat: g.lat, lng: g.lng };
                            desenharRotaRestante(ultimoPonto, { lat: g.rota.target_lat, lng: g.rota.target_lng }, epochAtual, g.rota.indo_para);

                            const targetIcon = L.divIcon({
                                className: 'mapa-marker mapa-marker--cliente',
                                html: g.rota.indo_para === 'origem' ? '<i class="fas fa-flag"></i>' : '<i class="fas fa-flag-checkered"></i>',
                                iconSize: [24, 24],
                            });
                            const targetMarker = L.marker([g.rota.target_lat, g.rota.target_lng], { icon: targetIcon })
                                .bindPopup(g.rota.indo_para === 'origem' ? 'Indo buscar o veículo' : 'Indo para o destino');
                            targetMarker.addTo(map);
                            marcadores.push(targetMarker);
                        }
                    }
                });

                const badge = document.getElementById('mapaAtualizadoBadge');
                if (badge) badge.textContent = `Atualizado ${payload.atualizado_em || ''}`;
            } catch (error) {
                console.warn('Falha ao sincronizar mapa operacional:', error);
            }
        }

        syncMapa();
        window.setInterval(syncMapa, 10000);
    })();
});
</script>

<?php
// Não usa layouts/footer.php aqui: aquele parcial fecha </main></div> de
// .main-wrapper/.main-content (markup do layout antigo de 2 colunas), que
// esta página não abre — ela usa .shell-ops (grid próprio). Fechamento
// mínimo equivalente, reaproveitando só o bootstrap bundle (necessário
// pro modal de sessão expirada do header.php).
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
