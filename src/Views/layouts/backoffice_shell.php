<?php
/**
 * Layout Lovable reutilizável para views PHP restritas.
 * Controller fornece $perfil, $conteudo_pagina e opcionalmente $titulo_pagina,
 * $breadcrumb, $itens_menu, $notificacoes_url, $notificacoes_count e $assets_css.
 * NÃO usar simultaneamente src/Views/layouts/header.php ou footer.php legados.
 * A autenticação e autorização permanecem obrigatórias no controller.
 */
require_once __DIR__ . '/backoffice_helpers.php';
$contract = require __DIR__ . '/backoffice_contract.php';
$classes = ['admin'=>'operator','funcionario'=>'finance','gerente'=>'executive','guincho'=>'driver','oficina'=>'workshop','cliente'=>'client','especialista'=>'driver','parceiro'=>'operator'];
$papel = (string)($perfil ?? ($_SESSION['user']['tipo'] ?? ''));
if (!isset($classes[$papel])) { throw new RuntimeException('Papel invalido no shell'); }
$papelAutenticado = (string)($_SESSION['user']['tipo'] ?? '');
if ($papel !== $papelAutenticado && $papelAutenticado !== 'admin') { throw new RuntimeException('Perfil não autorizado'); }
$tema = $classes[$papel];
$profileContract = $contract[$papel] ?? $contract['admin'];
$nome = (string)($_SESSION['user']['nome'] ?? 'Usuário');
$iniciais = '';
foreach (preg_split('/\s+/u',trim($nome)) ?: [] as $p) { if ($p !== '' && strlen($iniciais) < 2) $iniciais .= strtoupper(substr($p,0,1)); }
$iniciais = $iniciais ?: 'GF';
$bp = rtrim((string)(defined('BASE_PATH') ? BASE_PATH : ''), '/');
$titulo_pagina = (string)($titulo_pagina ?? 'Painel');
$breadcrumb = (array)($breadcrumb ?? ['Backoffice', $titulo_pagina]);
$conteudo_pagina = (string)($conteudo_pagina ?? '');
$espacos = array_fill_keys(array_keys($classes), $profileContract['workspace']);
$menuBase = require __DIR__ . '/backoffice_menus.php';
$itens_menu = (array)($itens_menu ?? ($menuBase[$papel] ?? $menuBase['admin']));
$rota = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$notificacoes_url = isset($notificacoes_url) ? gfBoHref((string)$notificacoes_url) : '#';
$notificacoes_count = isset($notificacoes_count) && is_numeric($notificacoes_count) ? max(0,(int)$notificacoes_count) : 0;
$scriptNonce = function_exists('csp_script_nonce_attr') ? (string)csp_script_nonce_attr() : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= gfBoH($titulo_pagina) ?> — GuinchaFácil</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap">
<link rel="stylesheet" href="<?= gfBoH($bp) ?>/public/assets/css/gf-bo-lovable.css?v=20261017-18">
<link rel="stylesheet" href="<?= gfBoH($bp) ?>/public/assets/css/gf-bo-profiles.css?v=20261110-1">
<link rel="stylesheet" href="<?= gfBoH($bp) ?>/public/assets/css/gf-bo-components.css?v=20261110-1">
<link rel="stylesheet" href="<?= gfBoH($bp) ?>/public/assets/css/components/gf-footer-payments.css?v=20261101-3">
<?php foreach ((array)($assets_css ?? []) as $sheet): if (is_string($sheet) && gfBoHref($sheet) !== '#'): ?>
<link rel="stylesheet" href="<?= gfBoH($bp.$sheet) ?>">
<?php endif; endforeach; ?>
<link rel="stylesheet" href="<?= gfBoH($bp) ?>/public/assets/css/backoffice-v3-overrides.css?v=20261111-1">
</head>
<body class="gf-backoffice-profile-<?= gfBoH($papel) ?>" data-role="<?= gfBoH($papel) ?>" data-theme="<?= gfBoH($profileContract['preset']) ?>">
<div class="bo bo-<?= gfBoH($tema) ?> bo-p-<?= gfBoH($papel) ?>" data-gf-profile="<?= gfBoH($papel) ?>">
  <aside class="bo-sidebar" aria-label="Menu principal">
    <a class="brand-name bo-brand" href="<?= gfBoH($bp) ?>/" aria-label="GuinchaFácil — início">Guincha<span>Fácil</span><span class="brand-period">.</span></a>
    <div class="bo-workspace"><span class="bo-avatar"><?= gfBoIcon('shield-check',17) ?></span>
      <div class="bo-workspace-copy"><strong><?= gfBoH($espacos[$papel][0]) ?></strong><small><?= gfBoH($espacos[$papel][1]) ?></small></div>
    </div>
    <nav aria-label="Navegação principal">
    <?php foreach ($itens_menu as $item): ?>
      <?php if (isset($item['section'])): ?><small class="bo-nav-label"><?= gfBoH($item['section']) ?></small><?php continue; endif; ?>
      <?php if (empty($item['label']) || empty($item['href'])) continue;
        $href=gfBoHref((string)$item['href']); if ($href==='#') continue;
        $matches = (array)($item['match'] ?? [$href]); $ativo = false; foreach ($matches as $prefix) { if ($rota === $prefix || str_starts_with($rota, rtrim($prefix, '/') . '/')) { $ativo = true; break; } } ?>
      <a class="bo-nav<?= $ativo ? ' active' : '' ?>" href="<?= gfBoH($bp.$href) ?>" title="<?= gfBoH($item['label']) ?>" <?= $ativo ? 'aria-current="page"' : '' ?>>
        <?= gfBoIcon((string)($item['icon'] ?? 'layout-dashboard')) ?><span><?= gfBoH($item['label']) ?></span></a>
      <?php if (!empty($item['children'])): ?><div class="bo-nav-submenu" aria-label="<?= gfBoH($item['label']) ?> submenu">
      <?php foreach ((array)$item['children'] as $child): $chref=gfBoHref((string)($child['href'] ?? '#')); if ($chref === '#') continue; $chativo=false; foreach ((array)($child['match'] ?? [$chref]) as $prefix) { if ($rota === $prefix || str_starts_with($rota, rtrim($prefix, '/') . '/')) { $chativo=true; break; } } ?><a class="bo-nav bo-nav-sub<?= $chativo ? ' active' : '' ?>" href="<?= gfBoH($bp.$chref) ?>" <?= $chativo ? 'aria-current="page"' : '' ?>><?= gfBoIcon((string)($child['icon'] ?? 'circle'),16) ?><span><?= gfBoH($child['label'] ?? '') ?></span></a><?php endforeach; ?></div><?php endif; ?>
    <?php endforeach; ?>
    </nav>
    <div class="bo-sidebar-bottom"><a class="bo-nav" href="<?= gfBoH($bp) ?>/" title="Voltar ao site"><?= gfBoIcon('external-link') ?><span>Voltar ao site</span></a>
      <div class="bo-session"><span class="status-dot" aria-hidden="true"></span><span>Área autenticada</span></div>
    </div>
  </aside>
  <div class="bo-body">
    <header class="bo-header gf-panel-topbar">
      <div class="bo-header-start"><button type="button" class="bo-header-button" data-bo-collapse aria-expanded="true" aria-label="Recolher menu lateral"><?= gfBoIcon('chevron-left',20) ?></button>
        <span class="bo-breadcrumb bo-breadcrumb">
        <?php $partes=array_values($breadcrumb); foreach ($partes as $i => $parte):
           if ($i>0) echo gfBoIcon('chevron-right',13);
           if ($i===count($partes)-1): ?><strong><?= gfBoH($parte) ?></strong>
           <?php else: ?><?= gfBoH($parte) ?><?php endif; endforeach; ?>
        </span>
      </div>
      <div class="bo-header-actions">
        <span class="bo-theme-select bo-theme-select--fixed" aria-label="Perfil visual definido pela sessão"><span class="bo-theme-dot" aria-hidden="true"></span><span><?= gfBoH($profileContract['workspace'][0] ?? 'Perfil') ?></span></span>
        <?php if ($notificacoes_url !== '#'): ?>
          <a class="bo-notification-link" href="<?= gfBoH($bp.$notificacoes_url) ?>" aria-label="Notificações"><?= gfBoIcon('bell',18) ?>
          <?php if ($notificacoes_count>0): ?><span class="bo-notification-count"><?= $notificacoes_count ?></span><?php endif; ?></a>
        <?php else: ?><span class="bo-notification-link" aria-label="Notificações não configuradas"><?= gfBoIcon('bell',18) ?></span><?php endif; ?>
        <span class="bo-avatar" title="<?= gfBoH($nome) ?>"><?= gfBoH($iniciais) ?></span>
        <a class="bo-header-button" href="<?= gfBoH($bp) ?>/logout" data-confirm-logout aria-label="Sair"><?= gfBoIcon('log-out', 17) ?></a>
      </div>
    </header>
    <main class="bo-content"><?= $conteudo_pagina /* O conteúdo vem da view interna, nunca de $_GET/$_POST sem escapar */ ?></main>
    <footer class="bo-footer">
      <div class="bo-footer__meta"><span><span class="status-dot" aria-hidden="true"></span> Dados operacionais do GuinchaFácil</span><span>GuinchaFácil / Backoffice</span></div>
      <?php require __DIR__ . '/../components/gf-footer-payments.php'; ?>
    </footer>
  </div>
</div>
<script<?= $scriptNonce ?> defer src="<?= gfBoH($bp) ?>/public/assets/js/gf-bo-shell.js?v=20261017-18"></script>
<script<?= $scriptNonce ?> defer src="<?= gfBoH($bp) ?>/public/assets/js/gf-bo-dialog.js?v=20261110-1"></script>
<script<?= $scriptNonce ?> defer src="<?= gfBoH($bp) ?>/public/assets/js/session-manager.js"></script>
</body></html>
