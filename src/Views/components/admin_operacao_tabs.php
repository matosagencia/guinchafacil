<?php
/** Fase 18E: navegacao secundária. Sem queries nem mutações de estado. */
$base = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : '';
$uri = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$links = [
    ['/admin/central', 'Ao vivo'],
    ['/admin/pedidos', 'Pedidos'],
    ['/admin/despacho', 'Despacho'],
    ['/admin/alertas', 'Alertas'],
    ['/admin/ocorrencias', 'Ocorrências'],
    ['/admin/dashboard', 'Resumo'],
];
?>
<nav class="gf18e-operacao-tabs" aria-label="Abas do módulo Operação">
<?php foreach ($links as [$route,$label]):
    $href = $base . $route;
    $active = $uri === $href || $uri === $route;
?>
<a class="gf18e-operacao-tab<?= $active ? ' is-active' : '' ?>"
   href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
   <?= $active ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
</nav>
