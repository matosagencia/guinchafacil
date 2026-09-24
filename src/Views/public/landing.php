<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
header('Content-Type: text/html; charset=UTF-8');
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
<title>Guincho e Reboque 24 Horas | Assistência Veicular GuinchaFácil</title>
<meta name="description" content="Precisa de guincho 24 horas ou reboque 24 horas? O GuinchaFácil conecta você a assistência veicular, mecânica, elétrica, bateria e pneus, com cotação clara.">
<link rel="canonical" href="https://guinchafacil.com.br/">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="GuinchaF&aacute;cil">
<meta property="og:title" content="GuinchaF&aacute;cil — Socorro para o seu ve&iacute;culo">
<meta property="og:description" content="Receba uma cota&ccedil;&atilde;o de assist&ecirc;ncia veicular antes de criar sua conta.">
<meta property="og:url" content="https://guinchafacil.com.br/">
<meta property="og:image" content="https://guinchafacil.com.br/public/assets/img/landing/roadside-help.jpg">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="GuinchaF&aacute;cil — Socorro para o seu ve&iacute;culo">
<meta name="twitter:description" content="Assist&ecirc;ncia veicular com localiza&ccedil;&atilde;o, triagem e cota&ccedil;&atilde;o clara.">
<meta name="twitter:image" content="https://guinchafacil.com.br/public/assets/img/landing/roadside-help.jpg">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'AutomotiveBusiness',
    'name' => 'GuinchaFácil',
    'url' => 'https://guinchafacil.com.br/',
    'logo' => 'https://guinchafacil.com.br/public/assets/img/logo-48.png',
    'image' => 'https://guinchafacil.com.br/public/assets/img/landing/roadside-help.jpg',
    'description' => 'Assistência veicular 24h: guincho, mecânica leve, autoelétrica, bateria e pneus.',
    'areaServed' => ['@type' => 'Country', 'name' => 'Brasil'],
    'availableLanguage' => ['@type' => 'Language', 'name' => 'Português'],
    'serviceType' => ['Guincho', 'Mecânica 24h', 'Autoelétrica', 'Troca de bateria', 'Troca de pneu', 'Pane seca'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
<link rel="icon" href="<?= $e($bp) ?>/public/assets/img/favicon-32.png">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/tokens.css"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-landing.css?v=20260922-cities-menu"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-landing-rescue.css?v=20260812-1"></head>
<body class="gf-landing"><header class="gf-nav"><a class="gf-brand" href="<?= $e($bp) ?>/"><img src="<?= $e($bp) ?>/public/assets/img/logo-48.png" alt="" width="40" height="40"><span>Guincha<strong>F&aacute;cil</strong></span></a><nav aria-label="Navega&ccedil;&atilde;o principal"><a href="#como-funciona">Como funciona</a><details class="gf-cities-menu"><summary>Cidades</summary><div class="gf-cities-dropdown"><span class="gf-cities-title">Atendimento por cidade</span><?php foreach (array_slice($cidadesSeo ?? [], 0, 20) as $cidadeIndex => $cidadeSeo): ?><a href="<?= $e($bp) ?>/guincho/<?= $e((string)$cidadeSeo['slug']) ?>"><?= ($cidadeIndex % 2 === 0 ? 'Guincho em ' : 'Reboque em ') . $e((string)$cidadeSeo['nome']) ?><?= !empty($cidadeSeo['uf']) ? ' - ' . $e(strtoupper((string)$cidadeSeo['uf'])) : '' ?></a><?php endforeach; ?><a class="gf-cities-all" href="<?= $e($bp) ?>/guincho">Ver todas as cidades</a></div></details><a href="#parceiros">Seja parceiro</a><a class="gf-login" href="<?= $e($bp) ?>/login">Entrar</a></nav></header>
<main><section class="gf-hero" aria-labelledby="hero-title"><div><p class="gf-eyebrow">&bull; Socorro 24h para o seu veículo</p><h1 id="hero-title">Parou? Socorro imediato: <em>Guincho e reboque, mecânica, elétrica e pneus.</em></h1><p class="gf-lead">Guincho e reboque 24 horas para pane seca, bateria, pneu furado ou outros imprevistos. Informe sua localização e receba uma cotação clara antes de criar sua conta.</p><a class="gf-cta" href="<?= $e($bp) ?>/pre-cotacao" data-marketing-event="begin_quote"><span class="gf-pin">&#8982;</span><span><b>Ver minha cota&ccedil;&atilde;o</b><small>Sem cobran&ccedil;a nesta etapa</small></span><span class="gf-arrow">&rarr;</span></a><p class="gf-reassure">&#10003; Voc&ecirc; decide antes de pagar &middot; cota&ccedil;&atilde;o clara</p><div class="gf-hero-proof" aria-label="Compromissos do atendimento"><span>Localiza&ccedil;&atilde;o conferida</span><span>Triagem orientada</span><span>Pre&ccedil;o antes do pagamento</span></div></div>
<div class="gf-card"><div class="gf-card-head"><span class="gf-dot"></span><b>Seu pr&oacute;ximo passo</b><small>1 de 3</small></div><div class="gf-location"><span>&#8982;</span><div><small>Localiza&ccedil;&atilde;o do ve&iacute;culo</small><b>Use o GPS ou digite o endere&ccedil;o</b></div></div><div class="gf-steps"><div class="active"><b>1</b><span>Localiza&ccedil;&atilde;o</span></div><div><b>2</b><span>O que aconteceu</span></div><div><b>3</b><span>Cota&ccedil;&atilde;o e pagamento</span></div></div><p class="gf-card-note">N&atilde;o sabe explicar o problema? Tudo bem. A triagem ajuda a escolher o atendimento adequado.</p></div></section>
<section class="gf-safety"><b>Est&aacute; em risco imediato?</b><span>Proteja-se primeiro e acione os servi&ccedil;os p&uacute;blicos de emerg&ecirc;ncia quando necess&aacute;rio. O GuinchaF&aacute;cil conecta voc&ecirc; &agrave; assist&ecirc;ncia veicular.</span></section>
<section class="gf-photo-band" aria-label="Assist&ecirc;ncia na estrada"><figure><img src="<?= $e($bp) ?>/public/assets/img/landing/roadside-help.jpg" alt="Motorista ao lado de um carro parado na estrada, verificando o problema"><figcaption>Quando o imprevisto acontece, o primeiro passo &eacute; localizar o ve&iacute;culo com seguran&ccedil;a.</figcaption></figure><figure><img src="<?= $e($bp) ?>/public/assets/img/landing/mechanic-help.jpg" alt="Mec&acirc;nico trabalhando em um ve&iacute;culo na estrada"><figcaption>Profissionais preparados ajudam a colocar voc&ecirc; de volta no caminho.</figcaption></figure></section>
<section class="gf-section" id="como-funciona"><p class="gf-eyebrow">Sem complica&ccedil;&atilde;o</p><h2>Do primeiro toque ao atendimento</h2><p class="gf-muted">Voc&ecirc; n&atilde;o precisa conhecer o nome t&eacute;cnico do servi&ccedil;o. O processo acompanha a sua situa&ccedil;&atilde;o.</p><div class="gf-process"><article><i>01</i><h3>Marque onde est&aacute;</h3><p>Use o GPS ou procure o endere&ccedil;o e confira o ponto no mapa.</p></article><article><i>02</i><h3>Triagem Rápida</h3><p>Guincho, mecânica, bateria ou pneu? Nossa rede tria o melhor prestador.</p></article><article><i>03</i><h3>Veja e confirme</h3><p>Confira a cota&ccedil;&atilde;o oficial antes de seguir para o pagamento.</p></article></div></section>
<section class="gf-partner" id="parceiros"><div><p class="gf-eyebrow">Para quem atende</p><h2>Você é Guincheiro ou prestador móvel?</h2><p>Receba ofertas compatíveis com sua região e especialidade. Seja guincho, autoelétrica, borracheiro ou mecânico.</p></div>
<div class="d-flex gap-3 justify-content-center">
    <a href="<?= $e($bp) ?>/registro/guincho" class="btn btn-primary">Quero ser Guincheiro &rarr;</a>
    <a href="<?= $e($bp) ?>/registro/especialista" class="btn btn-secondary">Quero ser prestador móvel &rarr;</a>
</div></section>
<section class="gf-trust"><div><b>Pre&ccedil;o antes da confirma&ccedil;&atilde;o</b><span>A cota&ccedil;&atilde;o aparece antes do pagamento.</span></div><div><b>Localiza&ccedil;&atilde;o conferida</b><span>Voc&ecirc; pode ajustar o ponto no mapa.</span></div><div><b>Acompanhamento</b><span>O status fica vis&iacute;vel para voc&ecirc;.</span></div></section></main>
<?php if (!empty($cidadesSeo)): ?><section class="gf-section" id="cidades-atendidas"><p class="gf-eyebrow">Atendimento local</p><h2>Guincho e assistencia por cidade</h2><p class="gf-muted">Encontre a pagina da sua cidade e veja as areas com atendimento ativo.</p><div class="gf-process"><?php foreach ($cidadesSeo as $cidadeIndex => $cidadeSeo): ?><article><i>⌖</i><h3><a href="<?= $e($bp) ?>/guincho/<?= $e((string)$cidadeSeo['slug']) ?>"><?= ($cidadeIndex % 2 === 0 ? 'Guincho em ' : 'Reboque em ') . $e((string)$cidadeSeo['nome']) ?><?= !empty($cidadeSeo['uf']) ? ' - ' . $e(strtoupper((string)$cidadeSeo['uf'])) : '' ?></a></h3><p><?= $cidadeIndex % 2 === 0 ? 'Guincho e assistencia veicular por zonas ativas.' : 'Reboque e assistencia veicular por zonas ativas.' ?></p></article><?php endforeach; ?></div></section><?php endif; ?>
 <footer class="gf-footer"><span>&copy; <?= date('Y') ?> GuinchaF&aacute;cil</span><div><a href="<?= $e($bp) ?>/login">Acesso da equipe</a><a href="<?= $e($bp) ?>/termos-servico.php">Termos</a><a href="<?= $e($bp) ?>/politica-privacidade.php">Privacidade</a></div></footer></body></html>
<?php include __DIR__ . '/../components/modelo_atendimento.php'; ?>
