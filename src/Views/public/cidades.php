<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cidades atendidas por guincho | GuinchaFácil</title>
<meta name="description" content="Encontre cidades com cobertura ativa de guincho e assistência veicular pela GuinchaFácil.">
<link rel="canonical" href="https://guinchafacil.com.br/guincho">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/tokens.css"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-landing.css">
</head><body class="gf-landing"><header class="gf-nav"><a class="gf-brand" href="<?= $e($bp) ?>/"><img src="<?= $e($bp) ?>/public/assets/img/logo-48.png" alt="GuinchaFácil" width="40" height="40"><span>Guincha<strong>Fácil</strong></span></a><nav aria-label="Navegação principal"><a href="<?= $e($bp) ?>/#como-funciona">Como funciona</a><a href="<?= $e($bp) ?>/guincho">Cidades</a><a href="<?= $e($bp) ?>/#parceiros">Seja parceiro</a><a class="gf-login" href="<?= $e($bp) ?>/login">Entrar</a></nav></header>
<main><section class="gf-section" aria-labelledby="cidades-title"><p class="gf-eyebrow">Atendimento local</p><h1 id="cidades-title">Guincho e assistência por cidade</h1><p class="gf-muted">Consulte as cidades com zonas de atendimento ativo e acesse a página local de cada região.</p><div class="gf-process gf-city-index"><?php foreach ($cidadesSeo as $cidadeSeo): ?><article><i>⌖</i><h2><a href="<?= $e($bp) ?>/guincho/<?= $e((string)$cidadeSeo['slug']) ?>"><?= $e((string)$cidadeSeo['nome']) ?><?= !empty($cidadeSeo['uf']) ? ' - ' . $e(strtoupper((string)$cidadeSeo['uf'])) : '' ?></a></h2><p>Guincho e assistência veicular por zonas ativas.</p></article><?php endforeach; ?></div></section></main>
<footer class="gf-footer"><span>&copy; <?= date('Y') ?> GuinchaFácil</span><div><a href="<?= $e($bp) ?>/">Página inicial</a><a href="<?= $e($bp) ?>/pre-cotacao">Fazer cotação</a></div></footer></body></html>
