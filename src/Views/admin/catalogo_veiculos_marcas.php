<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../components/vehicle_brand_badge.php';

$marcas = $marcas ?? [];
$contagemModelos = $contagemModelos ?? [];
$totalMarcas = count($marcas);
$marcasAtivas = count(array_filter($marcas, static fn($m) => !empty($m['active'])));
$totalModelos = array_sum($contagemModelos);
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <div class="ops-topbar">
        <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="marcasBusca" placeholder="Buscar marca" autocomplete="off" aria-label="Buscar marca"></div>
        <div class="ops-topbar__meta">
            <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalMarcas; ?> marcas</span>
        </div>
    </div>

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">Catálogo de veículos</span>
            <h1>Marcas</h1>
            <p>Biblioteca visual usada pelo cliente pra selecionar o veículo dele no cadastro — marca → modelo → versão. Cadastre aos poucos; o cliente sempre pode digitar manualmente quando a marca/modelo dele ainda não estiver aqui.</p>
        </div>
    </header>

    <section class="ops-summary mb-4" aria-label="Resumo do catálogo de veículos">
        <article class="ops-metric">
            <span class="ops-metric__label">Marcas cadastradas</span>
            <strong class="ops-metric__value"><?php echo $totalMarcas; ?></strong>
        </article>
        <article class="ops-metric">
            <span class="ops-metric__label">Ativas</span>
            <strong class="ops-metric__value"><?php echo $marcasAtivas; ?></strong>
        </article>
        <article class="ops-metric">
            <span class="ops-metric__label">Modelos cadastrados</span>
            <strong class="ops-metric__value"><?php echo $totalModelos; ?></strong>
        </article>
    </section>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-3">
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/novo" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Nova marca
        </a>
    </div>

    <div class="row g-3" id="marcasGrid">
        <?php if (empty($marcas)): ?>
        <div class="col-12">
            <div class="ops-empty-state" style="padding:60px 20px">
                <i class="fas fa-car"></i>
                Nenhuma marca cadastrada ainda.
            </div>
        </div>
        <?php else: foreach ($marcas as $m):
            $qtdModelos = $contagemModelos[(int)$m['id']] ?? 0;
        ?>
        <div class="col-6 col-md-3 col-lg-2" data-search="<?php echo htmlspecialchars(strtolower($m['name']), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card h-100 <?php echo empty($m['active']) ? 'opacity-50' : ''; ?>" style="text-align:center;">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/<?php echo (int)$m['id']; ?>" class="card-body d-flex flex-column align-items-center justify-content-center gap-2 text-decoration-none">
                    <?php echo vehicle_brand_badge_html($m['name'], $m['logo_path'] ?? null, 64); ?>
                    <strong class="text-body"><?php echo htmlspecialchars($m['name']); ?></strong>
                    <span class="text-muted small"><?php echo $qtdModelos; ?> modelo(s)</span>
                    <?php if (empty($m['active'])): ?><span class="badge text-bg-secondary">Inativa</span><?php endif; ?>
                </a>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/novo?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-pen me-1"></i>Editar
                </a>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <script<?php echo csp_script_nonce_attr(); ?>>
    (function () {
        var busca = document.getElementById('marcasBusca');
        var itens = Array.prototype.slice.call(document.querySelectorAll('#marcasGrid [data-search]'));
        if (!busca) return;
        busca.addEventListener('input', function () {
            var query = busca.value.trim().toLowerCase();
            itens.forEach(function (item) {
                item.hidden = Boolean(query) && item.getAttribute('data-search').indexOf(query) < 0;
            });
        });
    })();
    </script>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
