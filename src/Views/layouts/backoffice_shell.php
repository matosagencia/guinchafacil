<?php
/**
 * Layout Lovable reutilizável para views PHP restritas.
 * Controller fornece $perfil, $conteudo_pagina e opcionalmente $titulo_pagina,
 * $breadcrumb, $itens_menu, $notificacoes_url, $notificacoes_count e $assets_css.
 * NÃO usar simultaneamente src/Views/layouts/header.php ou footer.php legados.
 * A autenticação e autorização permanecem obrigatórias no controller.
 */
require_once __DIR__ . '/backoffice_helpers.php';
$classes = ['admin'=>'operator','operador'=>'operator','guincho'=>'driver','oficina'=>'workshop','cliente'=>'client'];
$papel = (string)($perfil ?? ($_SESSION['user']['tipo'] ?? ''));
if (!isset($classes[$papel])) { throw new RuntimeException('Papel invalido no shell'); }
$papelAutenticado = (string)($_SESSION['user']['tipo'] ?? '');
if ($papel === 'admin' && $papelAutenticado !== 'admin') { throw new RuntimeException('Papel nao autorizado'); }
$tema = $classes[$papel];
$nome = (string)($_SESSION['user']['nome'] ?? 'Usuário');
$iniciais = '';
foreach (preg_split('/\s+/u',trim($nome)) ?: [] as $p) { if ($p !== '' && strlen($iniciais) < 2) $iniciais .= strtoupper(substr($p,0,1)); }
$iniciais = $iniciais ?: 'GF';
$bp = rtrim((string)(defined('BASE_PATH') ? BASE_PATH : ''), '/');
$titulo_pagina = (string)($titulo_pagina ?? 'Painel');
$breadcrumb = (array)($breadcrumb ?? ['Backoffice', $titulo_pagina]);
$conteudo_pagina = (string)($conteudo_pagina ?? '');
$espacos = [
  'admin'=>['Central GuinchaFácil','Workspace operacional'],
  'operador'=>['Central GuinchaFácil','Workspace operacional'],
  'guincho'=>['Guincho parceiro','Operação do prestador'],
  'oficina'=>['Oficina parceira','Operação da oficina'],
  'cliente'=>['Área do cliente','Minhas solicitações'],
];
$menuBase = [
 'admin'=>[
  ['section'=>'PRINCIPAL'],['label'=>'Central operacional','href'=>'/admin/central','icon'=>'layout-dashboard'],
  ['label'=>'Pedidos','href'=>'/admin/pedidos','icon'=>'list-checks'],
  ['label'=>'Criar pedido','href'=>'/admin/pedido/novo','icon'=>'plus-circle'],
  ['label'=>'Despacho','href'=>'/admin/despacho','icon'=>'truck'],
  ['label'=>'Financeiro e repasses','href'=>'/admin/financeiro','icon'=>'wallet'],
  ['section'=>'SERVIÇOS'],['label'=>'Tipos de serviço','href'=>'/admin/catalogo-servicos/tipos','icon'=>'wrench'],
 ],
 'guincho'=>[
  ['section'=>'OPERAÇÃO'],['label'=>'Painel','href'=>'/guincho/dashboard','icon'=>'layout-dashboard'],
  ['label'=>'Histórico','href'=>'/guincho/historico','icon'=>'history'],
  ['label'=>'Financeiro','href'=>'/guincho/financeiro','icon'=>'wallet'],
  ['section'=>'CONTA'],['label'=>'Meu perfil','href'=>'/guincho/perfil','icon'=>'user-round'],
 ],
 'oficina'=>[
  ['section'=>'OPERAÇÃO'],['label'=>'Painel','href'=>'/oficina/dashboard','icon'=>'layout-dashboard'],
  ['label'=>'Ofertas','href'=>'/oficina/pedidos','icon'=>'bell'],
  ['label'=>'Histórico','href'=>'/oficina/historico','icon'=>'history'],
  ['label'=>'Financeiro','href'=>'/oficina/financeiro','icon'=>'wallet'],
  ['section'=>'CONTA'],['label'=>'Meu perfil','href'=>'/oficina/perfil','icon'=>'user-round'],
 ],
 'cliente'=>[
  ['section'=>'SOLICITAÇÕES'],['label'=>'Painel','href'=>'/cliente/dashboard','icon'=>'layout-dashboard'],
  ['label'=>'Pedir socorro','href'=>'/cliente/pedido/novo','icon'=>'truck'],
  ['label'=>'Histórico','href'=>'/cliente/historico','icon'=>'history'],
  ['section'=>'CONTA'],['label'=>'Veículos','href'=>'/cliente/veiculos','icon'=>'car'],
  ['label'=>'Meu perfil','href'=>'/cliente/perfil','icon'=>'user-round'],
 ],
];
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
<link rel="stylesheet" href="<?= gfBoH($bp) ?>/public/assets/css/components/gf-footer-payments.css?v=20261101-3">
<?php foreach ((array)($assets_css ?? []) as $sheet): if (is_string($sheet) && gfBoHref($sheet) !== '#'): ?>
<link rel="stylesheet" href="<?= gfBoH($bp.$sheet) ?>">
<?php endif; endforeach; ?>
</head>
<body>
<div class="bo bo-<?= gfBoH($tema) ?>" data-gf-profile="<?= gfBoH($papel) ?>">
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
        $ativo=(rtrim($rota,'/')===rtrim($href,'/')); ?>
      <a class="bo-nav<?= $ativo ? ' active' : '' ?>" href="<?= gfBoH($bp.$href) ?>" title="<?= gfBoH($item['label']) ?>" <?= $ativo ? 'aria-current="page"' : '' ?>>
        <?= gfBoIcon((string)($item['icon'] ?? 'layout-dashboard')) ?><span><?= gfBoH($item['label']) ?></span></a>
    <?php endforeach; ?>
    </nav>
    <div class="bo-sidebar-bottom"><a class="bo-nav" href="<?= gfBoH($bp) ?>/" title="Voltar ao site"><?= gfBoIcon('external-link') ?><span>Voltar ao site</span></a>
      <div class="bo-session"><span class="status-dot" aria-hidden="true"></span><span>Área autenticada</span></div>
    </div>
  </aside>
  <div class="bo-body">
    <header class="bo-header">
      <div class="bo-header-start"><button type="button" class="bo-header-button" data-bo-collapse aria-expanded="true" aria-label="Recolher menu lateral"><?= gfBoIcon('chevron-left',20) ?></button>
        <span class="bo-breadcrumb gf-order-crumb">
        <?php $partes=array_values($breadcrumb); foreach ($partes as $i => $parte):
           if ($i>0) echo gfBoIcon('chevron-right',13);
           if ($i===count($partes)-1): ?><strong><?= gfBoH($parte) ?></strong>
           <?php else: ?><?= gfBoH($parte) ?><?php endif; endforeach; ?>
        </span>
      </div>
      <div class="bo-header-actions">
        <label class="bo-theme-select"><span class="bo-theme-dot" aria-hidden="true"></span>
          <select data-bo-theme aria-label="Tema visual" <?= $papel !== 'admin' ? 'disabled' : '' ?>>
            <?php foreach (['operator'=>'Operador / Despacho','driver'=>'Prestador / Guincheiro','client'=>'Cliente','workshop'=>'Oficina Parceira'] as $id => $label):
               if ($papel !== 'admin' && $id !== $tema) continue; ?>
            <option value="<?= gfBoH($id) ?>" <?= $tema===$id ? 'selected' : '' ?>><?= gfBoH($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php if ($notificacoes_url !== '#'): ?>
          <a class="bo-notification-link" href="<?= gfBoH($bp.$notificacoes_url) ?>" aria-label="Notificações"><?= gfBoIcon('bell',18) ?>
          <?php if ($notificacoes_count>0): ?><span class="bo-notification-count"><?= $notificacoes_count ?></span><?php endif; ?></a>
        <?php else: ?><span class="bo-notification-link" aria-label="Notificações não configuradas"><?= gfBoIcon('bell',18) ?></span><?php endif; ?>
        <span class="bo-avatar" title="<?= gfBoH($nome) ?>"><?= gfBoH($iniciais) ?></span>
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
</body></html>
