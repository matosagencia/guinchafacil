<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_header-public.php';
?>
<main class="gf-section" aria-labelledby="site-map-title">
    <p class="gf-eyebrow">Navegação</p>
    <h1 id="site-map-title">Mapa do site</h1>
    <p class="gf-muted">Acesse rapidamente as principais áreas do GuinchaFácil.</p>
    <div class="gf-process">
        <article><h2>Para quem precisa de ajuda</h2><p><a href="<?= $e($bp) ?>/pre-cotacao">Fazer uma cotação</a></p><p><a href="<?= $e($bp) ?>/guincho">Cidades atendidas</a></p><p><a href="<?= $e($bp) ?>/login">Entrar na conta</a></p></article>
        <article><h2>Para parceiros</h2><p><a href="<?= $e($bp) ?>/parceiros">Rede de parceiros</a></p><p><a href="<?= $e($bp) ?>/parceiros/guinchos">Seja guincheiro</a></p><p><a href="<?= $e($bp) ?>/parceiros/oficinas">Seja uma oficina parceira</a></p></article>
        <article><h2>GuinchaFácil</h2><p><a href="<?= $e($bp) ?>/">Página inicial</a></p><p><a href="<?= $e($bp) ?>/sitemap.xml">Sitemap XML</a></p><p><a href="<?= $e($bp) ?>/login">Área de acesso</a></p></article>
    </div>
</main>
<?php require __DIR__ . '/_footer-public.php'; ?>
