<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$nomeCidade = (string)($cidade['nome'] ?? 'sua cidade');
$uf = strtoupper((string)($cidade['uf'] ?? ''));
$titulo = "Guincho e assistência veicular em {$nomeCidade}" . ($uf !== '' ? " - {$uf}" : '');
$descricao = "Peça guincho e assistência veicular em {$nomeCidade}. Informe sua localização, veja a cotação e acompanhe o atendimento.";
$url = 'https://guinchafacil.com.br/guincho/' . rawurlencode((string)$cidade['slug']);
$zonasExibidas = array_values(array_filter($zonas, static fn(array $z): bool => !empty($z['polygon_geojson']) || in_array(($z['status_expansao'] ?? ''), ['pedra_viva', 'pedra_morta'], true)));
$zonasVivas = array_values(array_filter($zonasExibidas, static fn(array $z): bool => ($z['status_expansao'] ?? '') === 'pedra_viva'));
foreach ($zonasExibidas as &$zonaExibida) {
    if (($zonaExibida['status_expansao'] ?? '') !== 'pedra_viva') $zonaExibida['status_expansao'] = 'pedra_morta';
}
unset($zonaExibida);
$outrasCidades = [];
foreach (Cidade::listarAtivas() as $outraCidade) {
    if ((int)($outraCidade['id'] ?? 0) === (int)($cidade['id'] ?? 0) || empty($outraCidade['slug'])) continue;
    if (PricingZone::listarPorOrdemExpansao((int)$outraCidade['id'], true)) $outrasCidades[] = $outraCidade;
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($titulo) ?> | GuinchaFácil</title><meta name="description" content="<?= $e($descricao) ?>"><link rel="canonical" href="<?= $e($url) ?>">
<?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
<meta property="og:type" content="website"><meta property="og:locale" content="pt_BR"><meta property="og:site_name" content="GuinchaFácil"><meta property="og:title" content="<?= $e($titulo) ?>"><meta property="og:description" content="<?= $e($descricao) ?>"><meta property="og:url" content="<?= $e($url) ?>">
<link rel="icon" href="<?= $e($bp) ?>/public/assets/img/favicon-32.png"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/tokens.css"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-landing.css">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> type="application/ld+json"><?= json_encode(['@context'=>'https://schema.org','@type'=>'AutomotiveBusiness','name'=>'GuinchaFácil','url'=>$url,'description'=>$descricao,'areaServed'=>['@type'=>'City','name'=>$nomeCidade,'address'=>['@type'=>'PostalAddress','addressLocality'=>$nomeCidade,'addressRegion'=>$uf,'addressCountry'=>'BR']],'serviceType'=>['Guincho','Assistência veicular','Troca de pneu','Partida auxiliar','Pane seca']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script></head>
<body class="gf-landing"><header class="gf-nav"><a class="gf-brand" href="<?= $e($bp) ?>/"><img src="<?= $e($bp) ?>/public/assets/img/logo-48.png" alt="GuinchaFácil" width="40" height="40"><span>Guincha<strong>Fácil</strong></span></a><nav aria-label="Navegação principal"><a href="<?= $e($bp) ?>/#como-funciona">Como funciona</a><a href="<?= $e($bp) ?>/pre-cotacao">Fazer cotação</a><a class="gf-login" href="<?= $e($bp) ?>/login">Entrar</a></nav></header>
<main><section class="gf-hero" aria-labelledby="cidade-title"><div><p class="gf-eyebrow">Assistência veicular local</p><h1 id="cidade-title">Guincho e socorro para seu veículo em <em><?= $e($nomeCidade) ?></em></h1><p class="gf-lead">Informe onde você está, conte o que aconteceu e veja sua cotação antes de criar sua conta.</p><a class="gf-cta" href="<?= $e($bp) ?>/pre-cotacao"><span class="gf-pin">⌖</span><span><b>Ver minha cotação</b><small>Sem cobrança nesta etapa</small></span><span class="gf-arrow">→</span></a></div><div class="gf-card"><div class="gf-card-head"><span class="gf-dot"></span><b>Cobertura em <?= $e($nomeCidade) ?></b></div><div class="gf-location"><span>⌖</span><div><small>Áreas mapeadas</small><b><?= count($zonasVivas) ?> zonas com atendimento ativo</b></div></div><p class="gf-card-note">A disponibilidade varia conforme o status da zona e o tipo de serviço.</p></div></section>
<section class="gf-section" id="areas"><p class="gf-eyebrow">Cobertura e expansão</p><h2>Atendimento por zonas</h2><p class="gf-muted">Pedras Vivas e Pedras Mortas fazem parte do nosso mapa de operação em <?= $e($nomeCidade) ?>.</p><div class="gf-process"><?php foreach ($zonasExibidas as $zona): $status = ($zona['status_expansao'] ?? '') === 'pedra_viva' ? 'Pedra Viva — atendimento ativo' : (($zona['status_expansao'] ?? '') === 'pedra_morta' ? 'Pedra Morta — em expansão' : 'Área mapeada'); ?><article><i><?= $e((string)($zona['ordem_expansao'] ?? '')) ?></i><h3><?= $e((string)$zona['name']) ?></h3><p><strong><?= $e($status) ?></strong><br><?= $e((string)($zona['bairros_referencia'] ?? 'Área mapeada para expansão')) ?></p></article><?php endforeach; ?></div></section>
<section class="gf-trust"><div><b>Cotação antes do cadastro</b><span>Veja as condições antes de decidir.</span></div><div><b>Localização conferida</b><span>Use o GPS ou informe o endereço.</span></div><div><b>Atendimento acompanhado</b><span>O status fica visível para você.</span></div></section>
<?php if ($outrasCidades): ?><section class="gf-section" id="outras-cidades"><p class="gf-eyebrow">Rede GuinchaFácil</p><h2>Explore outras cidades</h2><p class="gf-muted">Conheça as áreas cadastradas na nossa rede.</p><div class="gf-process"><?php foreach ($outrasCidades as $outraCidade): ?><article><i>↗</i><h3><a href="<?= $e($bp) ?>/guincho/<?= $e((string)$outraCidade['slug']) ?>"><?= $e((string)$outraCidade['nome']) ?><?= !empty($outraCidade['uf']) ? ' - ' . $e(strtoupper((string)$outraCidade['uf'])) : '' ?></a></h3><p>Guincho e assistência veicular por zonas.</p></article><?php endforeach; ?></div></section><?php endif; ?></main>
<footer class="gf-footer"><span>&copy; <?= date('Y') ?> GuinchaFácil</span><div><a href="<?= $e($bp) ?>/">Página inicial</a><a href="<?= $e($bp) ?>/pre-cotacao">Fazer cotação</a><a href="<?= $e($bp) ?>/termos-servico.php">Termos</a><a href="<?= $e($bp) ?>/politica-privacidade.php">Privacidade</a></div></footer></body></html>
