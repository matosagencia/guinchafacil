<?php
$bp  = $bp ?? (defined('BASE_PATH') ? BASE_PATH : '');
$cur = $cur ?? ($_SERVER['REQUEST_URI'] ?? '');
function opsNavActive(string $uri, string $match): string { return strpos($uri, $match) !== false ? 'is-active' : ''; }
?>
<button type="button" class="ops-nav-toggle" id="opsNavCollapseToggle" aria-label="Recolher menu" title="Recolher menu">
    <i class="fas fa-angles-left" aria-hidden="true"></i>
    <span class="ops-nav-toggle__label">Recolher menu</span>
</button>
<nav class="ops-nav" aria-label="Navegação administrativa">
    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="atendimento" aria-expanded="true"><span class="ops-nav-section__title-text">Atendimento</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/central" class="ops-nav-link <?php echo opsNavActive($cur, '/admin/central'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-tower-broadcast"></i></span><span class="ops-nav-link__label">Central Operacional</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/dashboard" class="ops-nav-link <?php echo opsNavActive($cur, '/admin/dashboard'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-gauge"></i></span><span class="ops-nav-link__label">Resumo Executivo</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos" class="ops-nav-link <?php echo (strpos($cur, '/pedidos') !== false || preg_match('#/pedido/\d#', $cur)) ? 'is-active' : ''; ?>"><span class="ops-nav-link__icon"><i class="fas fa-list-check"></i></span><span class="ops-nav-link__label">Pedidos</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/novo" class="ops-nav-link"><span class="ops-nav-link__icon"><i class="fas fa-circle-plus"></i></span><span class="ops-nav-link__label">Criar Pedido</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/despacho" class="ops-nav-link <?php echo opsNavActive($cur, '/despacho'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-diagram-project"></i></span><span class="ops-nav-link__label">Despacho</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/dashboard#mapaOperacional" class="ops-nav-link"><span class="ops-nav-link__icon"><i class="fas fa-map-location-dot"></i></span><span class="ops-nav-link__label">Mapa ao vivo</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/alertas" class="ops-nav-link <?php echo opsNavActive($cur, '/alertas'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-triangle-exclamation"></i></span><span class="ops-nav-link__label">Alertas Operacionais</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/ocorrencias" class="ops-nav-link <?php echo opsNavActive($cur, '/ocorrencias'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-file-circle-exclamation"></i></span><span class="ops-nav-link__label">Ocorrências</span></a>
        </div>
    </div>

    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="clientes" aria-expanded="true"><span class="ops-nav-section__title-text">Pessoas</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuarios" class="ops-nav-link <?php echo opsNavActive($cur, '/usuarios'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-users"></i></span><span class="ops-nav-link__label">Pessoas</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuario/novo" class="ops-nav-link"><span class="ops-nav-link__icon"><i class="fas fa-user-plus"></i></span><span class="ops-nav-link__label">Criar Cliente</span></a>
        </div>
    </div>

    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="guinchos-e-especialistas" aria-expanded="true"><span class="ops-nav-section__title-text">Rede de atendimento</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchos" class="ops-nav-link <?php echo (strpos($cur, '/guinchos') !== false || strpos($cur, '/prestadores') !== false || strpos($cur, '/documentos') !== false || preg_match('#/guincho[-/](?!novo)#', $cur)) ? 'is-active' : ''; ?>"><span class="ops-nav-link__icon"><i class="fas fa-truck"></i></span><span class="ops-nav-link__label">Guinchos</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/guincho/novo" class="ops-nav-link"><span class="ops-nav-link__icon"><i class="fas fa-truck-medical"></i></span><span class="ops-nav-link__label">Cadastrar Guincheiro</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/especialistas" class="ops-nav-link <?php echo strpos($cur, '/especialista') !== false ? 'is-active' : ''; ?>"><span class="ops-nav-link__icon"><i class="fas fa-user-gear"></i></span><span class="ops-nav-link__label">Especialistas</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/especialistas/cadastrar" class="ops-nav-link"><span class="ops-nav-link__icon"><i class="fas fa-user-plus"></i></span><span class="ops-nav-link__label">Cadastrar Especialista</span></a>
        </div>
    </div>

    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="financeiro" aria-expanded="true"><span class="ops-nav-section__title-text">Financeiro</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/financeiro" class="ops-nav-link <?php echo (strpos($cur, 'financeiro') !== false || strpos($cur, '/carteira') !== false || strpos($cur, '/saques') !== false) ? 'is-active' : ''; ?>"><span class="ops-nav-link__icon"><i class="fas fa-chart-line"></i></span><span class="ops-nav-link__label">Financeiro</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/financeiro/visao-unificada" class="ops-nav-link <?php echo opsNavActive($cur, 'financeiro/visao-unificada'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-chart-pie"></i></span><span class="ops-nav-link__label">Receita e margem</span></a>
        </div>
    </div>

    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="marketing" aria-expanded="true"><span class="ops-nav-section__title-text">Marketing</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/marketing" class="ops-nav-link <?php echo opsNavActive($cur, '/admin/marketing'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-chess-board"></i></span><span class="ops-nav-link__label">Central de Marketing</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/marketing#prospeccao" class="ops-nav-link <?php echo strpos($cur, '/admin/marketing') !== false ? 'is-active' : ''; ?>"><span class="ops-nav-link__icon"><i class="fas fa-user-plus"></i></span><span class="ops-nav-link__label">Prospecção</span></a>
        </div>
    </div>

    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="servicos" aria-expanded="true"><span class="ops-nav-section__title-text">Serviços</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/tipos" class="ops-nav-link <?php echo (strpos($cur, 'catalogo-servicos') !== false && strpos($cur, 'capacidades') === false) ? 'is-active' : ''; ?>"><span class="ops-nav-link__icon"><i class="fas fa-toolbox"></i></span><span class="ops-nav-link__label">Tipos de Serviço</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/capacidades" class="ops-nav-link <?php echo opsNavActive($cur, 'catalogo-servicos/capacidades'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-user-check"></i></span><span class="ops-nav-link__label">Aprovar serviços</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos" class="ops-nav-link <?php echo opsNavActive($cur, 'catalogo-veiculos'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-car"></i></span><span class="ops-nav-link__label">Catálogo de Veículos</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/precificacao/zonas" class="ops-nav-link <?php echo opsNavActive($cur, 'precificacao'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-map-location-dot"></i></span><span class="ops-nav-link__label">Zonas de guincho</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/tarifas" class="ops-nav-link <?php echo opsNavActive($cur, 'catalogo-servicos'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-tags"></i></span><span class="ops-nav-link__label">Tarifas de especialistas</span></a>
        </div>
    </div>

    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="planejamento" aria-expanded="true"><span class="ops-nav-section__title-text">Planejamento</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/planejamento" class="ops-nav-link <?php echo opsNavActive($cur, 'planejamento'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-calculator"></i></span><span class="ops-nav-link__label">Planejamento</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/cidades" class="ops-nav-link <?php echo opsNavActive($cur, '/cidade'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-city"></i></span><span class="ops-nav-link__label">Cidades-alvo</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/feriados" class="ops-nav-link <?php echo opsNavActive($cur, '/feriado'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-calendar-day"></i></span><span class="ops-nav-link__label">Feriados</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/comunicados" class="ops-nav-link <?php echo opsNavActive($cur, 'comunicado'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-bullhorn"></i></span><span class="ops-nav-link__label">Comunicados</span></a>
        </div>
    </div>

    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="configuracoes" aria-expanded="true"><span class="ops-nav-section__title-text">Configurações</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/configuracoes" class="ops-nav-link <?php echo opsNavActive($cur, 'configuracoes'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-gear"></i></span><span class="ops-nav-link__label">Configurações</span></a>
        </div>
    </div>

    <div class="ops-nav-section">
        <button type="button" class="ops-nav-section__title ops-nav-section__toggle" data-section-id="qualidade-e-sre" aria-expanded="true"><span class="ops-nav-section__title-text">Qualidade e SRE</span><i class="fas fa-chevron-down ops-nav-section__chevron" aria-hidden="true"></i></button>
        <div class="ops-nav-section__links">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/avaliacoes" class="ops-nav-link <?php echo (strpos($cur, '/avaliacoes') !== false || strpos($cur, '/proof-of-road') !== false || strpos($cur, '/checklists-incompletos') !== false) ? 'is-active' : ''; ?>"><span class="ops-nav-link__icon"><i class="fas fa-star-half-stroke"></i></span><span class="ops-nav-link__label">Qualidade Operacional</span></a>
            <span class="ops-nav-section__title admin-nav-subsection-title">Sistema</span>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/env" class="ops-nav-link <?php echo opsNavActive($cur, '/env'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-shield-halved"></i></span><span class="ops-nav-link__label">Ambiente (.env)</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/env/auditoria" class="ops-nav-link <?php echo opsNavActive($cur, '/env/auditoria'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-clipboard-check"></i></span><span class="ops-nav-link__label">Auditoria do ambiente</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/simulador" class="ops-nav-link <?php echo opsNavActive($cur, 'simulador'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-flask"></i></span><span class="ops-nav-link__label">QA Playwright</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/logs" class="ops-nav-link <?php echo opsNavActive($cur, '/logs'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-terminal"></i></span><span class="ops-nav-link__label">Logs</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/health" class="ops-nav-link <?php echo opsNavActive($cur, '/health'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-heart-pulse"></i></span><span class="ops-nav-link__label">Health Check</span></a>
        </div>
    </div>
</nav>
<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    var STORAGE_KEY = 'gf_admin_sidebar_collapsed';
    var toggle = document.getElementById('opsNavCollapseToggle');
    if (!toggle) return;

    document.querySelectorAll('.ops-nav-link').forEach(function (link) {
        if (link.hasAttribute('title')) return;
        var label = link.querySelector('.ops-nav-link__label');
        if (label) link.setAttribute('title', label.textContent.trim());
    });

    function applyState(collapsed) {
        document.body.classList.toggle('is-sidebar-collapsed', collapsed);
        toggle.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Recolher menu');
        toggle.setAttribute('title', collapsed ? 'Expandir menu' : 'Recolher menu');
        var icon = toggle.querySelector('i');
        if (icon) icon.className = collapsed ? 'fas fa-angles-right' : 'fas fa-angles-left';
        var label = toggle.querySelector('.ops-nav-toggle__label');
        if (label) label.textContent = collapsed ? 'Expandir menu' : 'Recolher menu';
    }

    applyState(document.body.classList.contains('is-sidebar-collapsed'));

    toggle.addEventListener('click', function () {
        var collapsed = !document.body.classList.contains('is-sidebar-collapsed');
        applyState(collapsed);
        try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
    });

    var SECTIONS_KEY = 'gf_admin_sidebar_sections_collapsed';
    var collapsedSections = {};
    try { collapsedSections = JSON.parse(localStorage.getItem(SECTIONS_KEY) || '{}'); } catch (e) { collapsedSections = {}; }

    document.querySelectorAll('.ops-nav-section__toggle').forEach(function (btn) {
        var sectionId = btn.getAttribute('data-section-id');
        var section = btn.closest('.ops-nav-section');
        if (!section) return;

        function setCollapsed(collapsed) {
            section.classList.toggle('is-collapsed', collapsed);
            btn.setAttribute('aria-expanded', String(!collapsed));
        }

        setCollapsed(!!collapsedSections[sectionId]);

        btn.addEventListener('click', function () {
            var collapsed = !section.classList.contains('is-collapsed');
            setCollapsed(collapsed);
            collapsedSections[sectionId] = collapsed;
            try { localStorage.setItem(SECTIONS_KEY, JSON.stringify(collapsedSections)); } catch (e) {}
        });
    });
})();
</script>
