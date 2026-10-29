<?php
/** Fase 18E: entrada única por hub; links legados continuam operacionais. */
$bp = $bp ?? (defined('BASE_PATH') ? BASE_PATH : '');
$cur = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$hubs = [
 ['operacao','Operação','fa-tower-broadcast','/admin/central',['/admin/central','/admin/pedidos','/admin/pedido/','/admin/despacho','/admin/alertas','/admin/ocorrencias','/admin/dashboard']],
 ['pessoas','Pessoas e Parceiros','fa-users','/admin/usuarios',['/admin/usuarios','/admin/usuario','/admin/guincho','/admin/prestador','/admin/oficina','/admin/especialistas','/admin/documentos']],
 ['financeiro','Financeiro','fa-wallet','/admin/financeiro',['/admin/financeiro','/admin/faturas','/admin/fatura/','/admin/carteira','/admin/saques']],
 ['catalogo','Catálogo e Preços','fa-toolbox','/admin/catalogo-servicos/tipos',['/admin/catalogo','/admin/precificacao','/admin/produtos','/admin/servicos','/admin/feriados']],
 ['territorio','Território e Crescimento','fa-map-location-dot','/admin/cidades',['/admin/cidades','/admin/demanda','/admin/marketing','/admin/prospeccao','/admin/planejamento','/admin/comunicados']],
 ['qualidade','Qualidade','fa-shield-halved','/admin/avaliacoes',['/admin/avaliacoes','/admin/proof-of-road','/admin/checklists']],
 ['sistema','Sistema','fa-gear','/admin/configuracoes',['/admin/configuracoes','/admin/env','/admin/logs','/admin/health','/admin/simulador','/admin/testes']],
];
?>
<button type="button" class="ops-nav-toggle" id="opsNavCollapseToggle" aria-label="Recolher menu" title="Recolher menu"><i class="fas fa-angles-left" aria-hidden="true"></i><span class="ops-nav-toggle__label">Recolher menu</span></button>
<nav class="ops-nav gf18e-nav" aria-label="Módulos do backoffice">
 <div class="ops-nav-section"><div class="ops-nav-section__links">
 <a class="ops-nav-link<?= str_starts_with($cur, '/admin/pedido/novo') ? ' is-active' : '' ?>" href="<?= htmlspecialchars(rtrim((string)$bp,'/').'/admin/pedido/novo',ENT_QUOTES,'UTF-8') ?>" title="Criar pedido de socorro"><span class="ops-nav-link__icon"><i class="fas fa-truck-medical" aria-hidden="true"></i></span><span class="ops-nav-link__label">Criar pedido de socorro</span></a>
 <?php foreach($hubs as [$key,$label,$icon,$route,$prefixes]):
  $active=false; foreach($prefixes as $prefix){if($cur===$prefix || str_starts_with($cur, rtrim($prefix,'/').'/')){$active=true;break;}}
 ?>
 <a class="ops-nav-link<?= $active?' is-active':'' ?>" href="<?= htmlspecialchars(rtrim((string)$bp,'/').$route, ENT_QUOTES,'UTF-8') ?>" <?= $active?'aria-current="page"':'' ?> title="<?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?>"><span class="ops-nav-link__icon"><i class="fas <?= htmlspecialchars($icon,ENT_QUOTES,'UTF-8') ?>" aria-hidden="true"></i></span><span class="ops-nav-link__label"><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></span></a>
 <?php endforeach; ?>
 </div></div>
</nav>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim((string)$bp,'/'),ENT_QUOTES,'UTF-8') ?>/public/assets/css/pages/gf-f18e-navegacao.css">
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
