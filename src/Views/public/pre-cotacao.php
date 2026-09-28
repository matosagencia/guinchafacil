<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
// Flag precotacao_funil_v2: quando '1', usa o partial novo (funil v2).
if (!isset($funilV2On)) {
    $funilV2On = false;
    if (class_exists('Configuracao')) {
        try { $funilV2On = (string)Configuracao::get('precotacao_funil_v2', '0') === '1'; } catch (Throwable $e) {}
    }
}
$flash = $flash ?? null;
$resultado = ($_GET['resultado'] ?? '') === '1' && !empty($cotacao) && (int)($cotacao['expira_em'] ?? 0) > time();

$forceTowDraft = is_array($forceTowDraft ?? null) ? $forceTowDraft : null;
$draftLocalizacao = (string)($forceTowDraft['localizacao'] ?? '');
$draftNumeroOrigem = (string)($forceTowDraft['numero_origem'] ?? '');
$draftLatOrigem = isset($forceTowDraft['lat_origem']) ? (string)$forceTowDraft['lat_origem'] : '';
$draftLngOrigem = isset($forceTowDraft['lng_origem']) ? (string)$forceTowDraft['lng_origem'] : '';
$draftCategoria = (string)($forceTowDraft['categoria'] ?? 'popular');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
<title>Cota&ccedil;&atilde;o de guincho e assist&ecirc;ncia veicular &mdash; GuinchaF&aacute;cil</title>
<meta name="description" content="Informe sua localiza&ccedil;&atilde;o e receba uma cota&ccedil;&atilde;o de guincho ou assist&ecirc;ncia veicular antes do cadastro.">
<link rel="canonical" href="https://guinchafacil.com.br/pre-cotacao">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="Cota&ccedil;&atilde;o de guincho e assist&ecirc;ncia veicular">
<meta property="og:description" content="Veja sua cota&ccedil;&atilde;o antes de criar sua conta ou pagar.">
<meta property="og:url" content="https://guinchafacil.com.br/pre-cotacao">
<link rel="icon" href="<?= $e($bp) ?>/public/assets/img/favicon-32.png">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao.css?v=20260812-3">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-landing.css?v=20260922-cities-menu">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao-map.css?v=20260924-1">
<?php if ($funilV2On): ?><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/components/address-picker.css?v=20260929-11"><?php endif; ?>
<style>.address-fields{display:grid;grid-template-columns:minmax(0,1fr) 120px;gap:8px;position:relative}.address-number-label{display:block;font-size:.74rem;font-weight:700;color:#55705b;margin:0 0 4px}.regional-map-panel{margin-top:12px;padding:12px;border:1px solid #cfe3d3;border-radius:14px;background:#f7fbf7}.regional-map-panel[hidden]{display:none}.regional-map-head{display:flex;justify-content:space-between;gap:12px;font-size:.82rem;margin-bottom:8px}.regional-map-head span{color:#607066}.regional-map{height:280px;border-radius:10px;overflow:hidden}.funnel-navigation{display:flex;gap:8px;align-items:center}@media(max-width:520px){.address-fields{grid-template-columns:minmax(0,1fr) 94px}.regional-map-head{display:block}.regional-map{height:240px}.funnel-navigation{flex-wrap:wrap}.funnel-navigation .btn-main{flex-basis:100%}}</style>
<script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?> src="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?> src="<?= $e($bp) ?>/public/assets/js/public-quote-result.js"></script>
<style>.origin-map-composition{position:relative}.origin-map-composition:has(#originMapPanel:not([hidden]))>.col-12:first-child{position:absolute;z-index:999;top:26px;right:26px;width:min(390px,calc(100% - 52px));padding:14px;border-radius:14px;background:rgba(255,255,255,.97);box-shadow:0 5px 16px rgba(20,80,35,.16)}@media(max-width:520px){.origin-map-composition:has(#originMapPanel:not([hidden]))>.col-12:first-child{position:relative;top:auto;right:auto;width:auto;margin-top:10px;padding:0;background:transparent;box-shadow:none}}</style>
<style>
body{margin:0;background:#f4f8f5;color:#142018;font-family:system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:820px;margin:auto;padding:28px 18px 60px}.brand{color:#142018;text-decoration:none;font-weight:800;font-size:1.15rem}.brand strong{color:#2fb34a}.card{margin-top:34px;border:1px solid #d4e6d8;border-radius:22px;background:#fff;box-shadow:0 18px 50px rgba(20,80,35,.1);padding:28px}.eyebrow{color:#248f3a;font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.title{font-size:clamp(2rem,5vw,3.4rem);line-height:1.05;font-weight:850;letter-spacing:-.04em}.muted{color:#607066}.label{font-weight:650;font-size:.86rem;color:#405247}.form-control,.form-select{min-height:48px;border-color:#cfe3d3}.form-control:focus,.form-select:focus{border-color:#2fb34a;box-shadow:0 0 0 3px rgba(47,179,74,.15)}.btn-main{min-height:52px;border:0;border-radius:12px;background:#2fb34a;color:#fff;font-weight:800}.btn-main:hover{background:#248f3a;color:#fff}.quote{border:1px solid #b9dfc0;background:#edf8ef;border-radius:16px;padding:20px}.quote strong{font-size:2rem;color:#176d2c}.trust{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}.trust span{padding:7px 10px;border-radius:999px;background:#f4f8f5;color:#55705b;font-size:.75rem}.back{color:#55705b;text-decoration:none;font-size:.9rem}
</style><style>.input-group{position:relative}.public-address-suggestions{position:absolute;z-index:20;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #cfe3d3;border-radius:10px;box-shadow:0 10px 24px rgba(20,80,35,.14);overflow:hidden}.public-address-suggestions[hidden]{display:none}.public-address-suggestion{display:block;width:100%;border:0;border-bottom:1px solid #edf3ee;background:#fff;color:#25382b;text-align:left;padding:11px 13px;font-size:.88rem}.public-address-suggestion:last-child{border-bottom:0}.public-address-suggestion:hover,.public-address-suggestion:focus{background:#edf8ef;outline:0}</style>
<style>
#sintomaStage .choice-grid-sintoma{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
#sintomaStage .choice-card{min-height:110px;padding:14px 12px;display:flex;flex-direction:column;align-items:center;text-align:center;border:1px solid #cfe3d3;border-radius:12px;background:#fff;cursor:pointer}
#sintomaStage .choice-card i{font-size:1.6rem;color:#2fb34a;margin-bottom:6px}
#sintomaStage .choice-card strong{display:block;font-size:.95rem;color:#142018;margin-bottom:2px}
#sintomaStage .choice-card small{display:block;font-size:.75rem;color:#607066;line-height:1.25}
#sintomaStage .choice-card.is-selected,#sintomaStage .choice-card[aria-pressed="true"]{border-color:#2fb34a;background:#edf8ef;box-shadow:0 0 0 2px rgba(47,179,74,.15)}
</style>










<style>
/* v19: controle de telas por body class */
#btnSituacaoAvancar { display: none !important; }
#btnCotacao { display: none !important; }

/* Esconde o bloco de origem + mapa em decisão e destino */
body.stage-decisao  #originMapPanel,
body.stage-destino  #originMapPanel,
body.stage-sintoma  #originMapPanel,
body.stage-decisao  .origin-map-composition,
body.stage-destino  .origin-map-composition,
body.stage-sintoma  .origin-map-composition { display: none !important; }

/* Mostra botão Ver cotação só no estágio destino */
body.stage-destino #btnCotacao, body.stage-veiculo #btnCotacao { display: block !important; }

/* Esconde Voltar em address */
body.stage-address #btnSituacaoVoltar { display: none !important; }

/* ─── Cards A/B ─── */
.decision-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 767px) { .decision-grid { grid-template-columns: 1fr; } }
.decision-card {
    display: flex; flex-direction: column; align-items: stretch;
    padding: 20px; border: 2px solid #d4e6d8; border-radius: 16px;
    background: #fff; text-align: left; cursor: pointer;
    transition: transform .15s, border-color .15s, box-shadow .15s;
    width: 100%; box-sizing: border-box;
}
.decision-card:hover { transform: translateY(-2px); border-color: #2fb34a; box-shadow: 0 6px 20px rgba(47,179,74,.15); }
.decision-card.is-recommended { border-color: #2fb34a; box-shadow: 0 0 0 2px rgba(47,179,74,.15); }
.decision-card.is-selected { border-color: #2fb34a; background: #edf8ef; }
.decision-card svg { width: 48px; height: 48px; margin-bottom: 8px; }
.decision-card strong { font-size: 1.15rem; color: #142018; margin-bottom: 4px; display: block; }
.decision-card .decision-desc { display: block; font-size: .88rem; color: #405247; line-height: 1.5; margin: 6px 0; }
.decision-card .decision-benefit { display: block; font-size: .84rem; color: #248f3a; font-weight: 600; margin-top: 8px; }
.decision-card .decision-price { font-size: 1.6rem; font-weight: 800; color: #2fb34a; margin: 4px 0; display: block; }

/* Botão dos cards — centraliza o texto, ocupa largura toda, não captura clique */
.decision-card .btn-main {
    display: block;
    width: 100%;
    margin-top: 14px;
    padding: 12px 16px;
    text-align: center !important;
    line-height: 1.2;
    pointer-events: none;   /* o clique vai pro card inteiro */
    box-sizing: border-box;
}
.decision-card.is-recommended .btn-main { background: #2fb34a; }
</style>

<style>
/* v19: controle de telas por body class */
#btnSituacaoAvancar { display: none !important; }
#btnCotacao { display: none !important; }

/* Esconde o bloco de origem + mapa em decisão e destino */
body.stage-decisao  #originMapPanel,
body.stage-destino  #originMapPanel,
body.stage-sintoma  #originMapPanel,
body.stage-decisao  .origin-map-composition,
body.stage-destino  .origin-map-composition,
body.stage-sintoma  .origin-map-composition { display: none !important; }

/* Mostra botão Ver cotação só no estágio destino */
body.stage-destino #btnCotacao, body.stage-veiculo #btnCotacao { display: block !important; }

/* Esconde Voltar em address */
body.stage-address #btnSituacaoVoltar { display: none !important; }

/* ─── Cards A/B ─── */
.decision-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 767px) { .decision-grid { grid-template-columns: 1fr; } }
.decision-card {
    display: flex; flex-direction: column; align-items: stretch;
    padding: 20px; border: 2px solid #d4e6d8; border-radius: 16px;
    background: #fff; text-align: left; cursor: pointer;
    transition: transform .15s, border-color .15s, box-shadow .15s;
    width: 100%; box-sizing: border-box;
}
.decision-card:hover { transform: translateY(-2px); border-color: #2fb34a; box-shadow: 0 6px 20px rgba(47,179,74,.15); }
.decision-card.is-recommended { border-color: #2fb34a; box-shadow: 0 0 0 2px rgba(47,179,74,.15); }
.decision-card.is-selected { border-color: #2fb34a; background: #edf8ef; }
.decision-card svg { width: 48px; height: 48px; margin-bottom: 8px; }
.decision-card strong { font-size: 1.15rem; color: #142018; margin-bottom: 4px; display: block; }
.decision-card .decision-desc { display: block; font-size: .88rem; color: #405247; line-height: 1.5; margin: 6px 0; }
.decision-card .decision-benefit { display: block; font-size: .84rem; color: #248f3a; font-weight: 600; margin-top: 8px; }
.decision-card .decision-price { font-size: 1.6rem; font-weight: 800; color: #2fb34a; margin: 4px 0; display: block; }

/* Botão dos cards — centraliza o texto, ocupa largura toda, não captura clique */
.decision-card .btn-main {
    display: block;
    width: 100%;
    margin-top: 14px;
    padding: 12px 16px;
    text-align: center !important;
    line-height: 1.2;
    pointer-events: none;   /* o clique vai pro card inteiro */
    box-sizing: border-box;
}
.decision-card.is-recommended .btn-main { background: #2fb34a; }
</style>
</head><body><main class="wrap">
<header class="gf-nav"><a class="gf-brand" href="<?= $e($bp) ?>/"><img src="<?= $e($bp) ?>/public/assets/img/logo-48.png" alt="GuinchaF&aacute;cil" width="40" height="40"><span>Guincha<strong>F&aacute;cil</strong></span></a><nav aria-label="Navega&ccedil;&atilde;o principal"><a href="<?= $e($bp) ?>/#como-funciona">Como funciona</a><details class="gf-cities-menu"><summary>Cidades</summary><div class="gf-cities-dropdown"><span class="gf-cities-title">Atendimento por cidade</span><?php foreach (array_slice($cidadesSeo ?? [], 0, 20) as $cidadeSeo): ?><a href="<?= $e($bp) ?>/guincho/<?= $e((string)$cidadeSeo['slug']) ?>"><?= $e((string)$cidadeSeo['nome']) ?><?= !empty($cidadeSeo['uf']) ? ' - ' . $e(strtoupper((string)$cidadeSeo['uf'])) : '' ?></a><?php endforeach; ?><a class="gf-cities-all" href="<?= $e($bp) ?>/guincho">Ver todas as cidades</a></div></details><a href="<?= $e($bp) ?>/#parceiros">Seja parceiro</a><a class="gf-login" href="<?= $e($bp) ?>/login">Entrar</a></nav></header>
<section class="card" aria-labelledby="title">
<?php if (!$resultado): ?>
<p class="eyebrow">Sem cadastro nesta etapa</p><h1 id="title" class="title">Veja sua cota&ccedil;&atilde;o antes de criar sua conta.</h1>
<p class="muted">Informe os dados essenciais. Mostraremos as condi&ccedil;&otilde;es antes de voc&ecirc; decidir. Nada ser&aacute; cobrado agora.</p>
<?php if (!empty($flash)): ?><div class="alert alert-danger"><?= htmlspecialchars((string)($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if (!$funilV2On): ?>
<form method="post" action="<?= $e($bp) ?>/pre-cotacao" class="row g-3" data-marketing-event="generate_lead">
<input type="hidden" name="csrf_token" value="<?= $e((string)($csrf_token ?? '')) ?>">
<div class="origin-map-composition"><div class="col-12"><label class="label" for="localizacao">Onde est&aacute; o ve&iacute;culo?</label><div class="address-fields"><div><input class="form-control" id="localizacao" name="localizacao" maxlength="220" placeholder="Rua, bairro e cidade" autocomplete="street-address" value="<?= $e($draftLocalizacao) ?>"></div><div><label class="address-number-label" for="numero_origem">N&uacute;mero</label><input class="form-control" id="numero_origem" name="numero_origem" maxlength="20" inputmode="numeric" placeholder="Ex.: 280" autocomplete="address-line2" required value="<?= $e($draftNumeroOrigem) ?>"></div></div><button class="btn btn-outline-success mt-2" id="btnGps" type="button">Usar minha localiza&ccedil;&atilde;o</button><small class="muted d-block mt-1" id="gpsStatus">Digite a rua e o n&uacute;mero. Se houver endere&ccedil;os iguais, escolha a cidade correta.</small></div>
<input type="hidden" id="lat_origem" name="lat_origem">
<input type="hidden" id="uf_origem_detectada" value="">
<input type="hidden" id="uf_destino_detectada" value=""><input type="hidden" id="lng_origem" name="lng_origem"><div id="originMapPanel" class="regional-map-panel" hidden><div class="regional-map-head"><strong>Confirme o ponto no mapa</strong><span id="mapAccuracyStatus">Arraste o pin até o local exato.</span></div><div id="originMap" class="regional-map" aria-label="Mapa regional para ajustar a localização"></div><small id="pinAddressStatus" class="muted">O endereço será atualizado a partir do pin. Se o número for estimado, você poderá revisá-lo.</small></div></div>
<div class="col-12" id="situacaoStage"><fieldset class="choice-fieldset"><legend class="label">O que aconteceu?</legend><input type="hidden" id="tipo_problema" name="tipo_problema" value="me_orientem"><div class="choice-grid choice-grid-help">
<?php $ajudas = [['me_orientem','fa-comments','Me orientem','Nao sei qual caminho escolher.'],['resolver_local','fa-user-cog','Resolver no local','Quero uma avaliacao onde estou.'],['levar_carro','fa-truck-pickup','Levar o carro','Preciso de uma oficina ou destino.']]; foreach($ajudas as $i=>$a): ?><button type="button" class="choice-card<?= $i===0?' is-selected':'' ?>" data-choice-group="tipo_problema" data-choice-value="<?= $e($a[0]) ?>" aria-pressed="<?= $i===0?'true':'false' ?>"><i class="fas <?= $e($a[1]) ?>" aria-hidden="true"></i><strong><?= $a[2] ?></strong><small><?= $a[3] ?></small></button><?php endforeach; ?></div></fieldset></div>
<div class="col-12" id="fieldVeiculoPodeMover" hidden><fieldset class="choice-fieldset"><legend class="label">O veículo pode se mover com segurança?</legend><input type="hidden" id="veiculo_pode_mover" name="veiculo_pode_mover" value="1"><div class="choice-grid choice-grid-help"><button type="button" class="choice-card is-selected" data-choice-group="veiculo_pode_mover" data-choice-value="1" aria-pressed="true"><i class="fas fa-check-circle" aria-hidden="true"></i><strong>Sim</strong><small>Ele ainda pode rodar com segurança.</small></button><button type="button" class="choice-card" data-choice-group="veiculo_pode_mover" data-choice-value="0" aria-pressed="false"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><strong>Não</strong><small>Precisa ser removido por reboque.</small></button></div></fieldset></div>


<div class="col-12" id="confirmarEnderecoStage" hidden>
<fieldset class="choice-fieldset">
<legend class="label">Confirme seu endereço</legend>
<div id="confirmarEnderecoTexto" style="font-size:1rem;color:#405247;margin:.5rem 0 1rem;padding:.75rem 1rem;background:#f4f8f5;border-radius:10px">
  Você informou que o veículo está na <strong id="enderecoConfirmar">—</strong>. Está correto?
</div>
<div class="choice-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
  <button type="button" class="choice-card is-selected" id="btnEnderecoSim" style="padding:1rem">
    <i class="fas fa-check-circle" style="color:#2fb34a;font-size:1.5rem;margin-bottom:.35rem"></i>
    <strong>Sim, está correto</strong>
    <small>Buscar oficinas nesta localização</small>
  </button>
  <button type="button" class="choice-card" id="btnEnderecoNao" style="padding:1rem">
    <i class="fas fa-pen-to-square" style="color:#f59e0b;font-size:1.5rem;margin-bottom:.35rem"></i>
    <strong>Não, corrigir</strong>
    <small>Voltar e ajustar o pin no mapa</small>
  </button>
</div>
<div id="buscandoOficinas" class="text-center py-3" hidden>
  <i class="fas fa-spinner fa-spin fa-2x" style="color:#2fb34a"></i>
  <p class="mt-2 mb-0" style="color:#607066">Buscando oficinas na sua região...</p>
</div>
<div id="resultadoBusca" class="mt-3" hidden></div>
</fieldset>
</div>
<div class="col-12" id="sintomaStage" hidden>
<fieldset class="choice-fieldset">
<legend class="label">Qual foi o problema?</legend>
<input type="hidden" id="sintoma" name="sintoma" value="">
<div class="choice-grid choice-grid-sintoma">
<?php $sintomas = [
    ['pneu',     'fa-circle-notch', 'Pneu',     'Furado, murcho ou danificado'],
    ['eletrica', 'fa-bolt',         'Eletrica', 'Nao liga, painel apagado'],
    ['bateria',  'fa-car-battery',  'Bateria',  'Nao pega partida'],
    ['mecanica', 'fa-gears',        'Mecanica', 'Motor, freio ou suspensao'],
    ['chaveiro', 'fa-key',          'Chaveiro', 'Chave presa, perdida ou travada'],
]; foreach($sintomas as $s): ?>
<button type="button" class="choice-card" data-choice-group="sintoma" data-choice-value="<?= $e($s[0]) ?>" aria-pressed="false"><i class="fas <?= $e($s[1]) ?>" aria-hidden="true"></i><strong><?= $e($s[2]) ?></strong><small><?= $e($s[3]) ?></small></button>
<?php endforeach; ?>
</div>
</fieldset>
</div>


<div class="col-12" id="decisionStage" hidden data-decision-url="<?= $e($bp) ?>/api/pre-cotacao/decisao">
<fieldset class="choice-fieldset">
<legend class="label">Escolha como quer seguir</legend>
<input type="hidden" id="decisao_atendimento" name="decisao_atendimento" value="">
<div class="decision-recommendation" id="decisionRecommendation">Comparando as opções para você...</div>
<div class="decision-grid" id="decisionGrid">
<button type="button" class="decision-card" id="decisionAssistencia" data-decision-choice="assistencia">
  <svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"><path fill="#2fb34a" d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>
  <strong>Resolver no local</strong>
  <span class="decision-price" id="assistenciaPrice">R$ --</span>
  <span class="decision-desc">Um <b>mecânico profissional verificado</b> vai até onde você está em poucos minutos. Ele avalia seu veículo e tenta resolver ali mesmo. Se precisar de peças, você paga a diferença direto pra ele — sem intermediários.</span>
  <span class="decision-benefit">✓ Se ele não conseguir consertar, <b>ele mesmo reboca seu carro até a oficina dele</b> — e o valor da assistência é <b>abatido do conserto</b>.</span>
  <span class="decision-desc" style="margin-top:10px;font-size:.82rem;color:#607066"><b>Uma escolha inteligente:</b> só para tirar um guincho do lugar custa a partir de <b>R$ 150</b>, fora o trajeto completo. Com a assistência local você paga bem menos e ainda abate do reparo.</span>
  <span class="btn-main w-100 mt-3" id="btnEscolherAssistencia">Quero resolver no local</span>
</button>
<button type="button" class="decision-card" id="decisionReboque" data-decision-choice="reboque">
  <svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"><path fill="#2fb34a" d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
  <strong>Rebocar</strong>
  <span class="decision-desc">Um <b>guincho plataforma</b> pode ser deslocado até você para remover o veículo até o endereço que você informar — oficina, casa ou qualquer outro destino.</span>
  <span class="decision-desc" style="margin-top:10px;font-size:.82rem;color:#607066">O valor é calculado pelo <b>trajeto completo</b> (origem → destino), sem surpresas.</span>
  <span class="btn-main w-100 mt-3" id="btnEscolherReboque">Quero rebocar</span>
</button>
</div>
<p class="muted small mb-0 mt-2" id="decisionFallbackText"></p>
</fieldset>
</div>
<div class="col-12" id="vehicleStage" hidden><fieldset class="choice-fieldset"><legend class="label">Qual &eacute; o tipo de ve&iacute;culo?</legend><input type="hidden" id="categoria" name="categoria" value="popular"><div class="choice-grid choice-grid-vehicle">
<?php $veiculos = [['popular','fa-car','Carro','Ve&iacute;culo de passeio comum.'],['moto','fa-motorcycle','Moto','Motocicleta ou scooter.'],['suv','fa-car-side','SUV','Ve&iacute;culo alto ou utilit&aacute;rio esportivo.'],['caminhonete','fa-truck-pickup','Caminhonete','Picape ou ve&iacute;culo de carga leve.'],['eletrico','fa-charging-station','El&eacute;trico','Ve&iacute;culo 100% el&eacute;trico.']]; foreach($veiculos as $i=>$v): ?><button type="button" class="choice-card<?= $i===0?' is-selected':'' ?>" data-choice-group="categoria" data-choice-value="<?= $e($v[0]) ?>" aria-pressed="<?= $i===0?'true':'false' ?>"><i class="fas <?= $e($v[1]) ?>" aria-hidden="true"></i><strong><?= $v[2] ?></strong><small><?= $v[3] ?></small></button><?php endforeach; ?></div></fieldset></div>
<div class="col-12" id="destinoBox" hidden>
<div class="col-12 mt-3" id="precoDestinoWrap" hidden>
    <div class="alert alert-info d-flex justify-content-between align-items-center" style="margin-bottom:0">
        <div>
            <span style="font-size:.85rem;color:#607066">Valor do reboque (origem → destino)</span>
            <div style="font-size:1.5rem;font-weight:800;color:#2fb34a" id="precoReboqueDestino">R$ --</div>
        </div>
        <div style="font-size:.8rem;color:#607066;text-align:right">
            Toque no mapa para ajustar<br>
            o ponto de entrega
        </div>
    </div>
</div>
<label class="label" for="destino">Para onde deve levar o ve&iacute;culo?</label><div class="address-fields"><div><input class="form-control" id="destino" name="destino" maxlength="220" placeholder="Rua, bairro e cidade" autocomplete="street-address"></div><div><label class="address-number-label" for="numero_destino">N&uacute;mero</label><input class="form-control" id="numero_destino" name="numero_destino" maxlength="20" inputmode="numeric" placeholder="Ex.: 247" autocomplete="address-line2"><input type="hidden" id="lat_destino" name="lat_destino"><input type="hidden" id="lng_destino" name="lng_destino"></div></div><small class="muted">Digite o endere&ccedil;o e o n&uacute;mero; depois escolha a cidade sugerida para calcular a rota.</small></div>
<div class="col-12 funnel-navigation"><button class="btn btn-outline-secondary" id="btnSituacaoVoltar" type="button">Voltar</button><button class="btn btn-success" id="btnSituacaoAvancar" type="button">Avan&ccedil;ar</button><button class="btn-main flex-grow-1" id="btnCotacao" type="submit">Ver minha cota&ccedil;&atilde;o</button></div></form>
<?php else: ?>
<?php require __DIR__ . '/partials/_precotacao_funil.php'; ?>
<?php endif; ?>
<?php else: ?>
<p class="eyebrow">Sua cota&ccedil;&atilde;o est&aacute; pronta</p><h1 id="title" class="title">Confira o valor antes de se cadastrar.</h1>
<div class="quote mt-4"><span class="muted"><?= htmlspecialchars((string)($cotacao['service_code'] ?? 'TOW_CAR'), ENT_QUOTES, 'UTF-8') ?> Â· <?= number_format((float)$cotacao['distancia_km'], 1, ',', '.') ?> km</span><br><strong>R$ <?= number_format((float)$cotacao['valor'], 2, ',', '.') ?></strong><p class="muted mb-0 mt-2">Calculada pelas regras vigentes da plataforma. V&aacute;lida por 15 minutos; o valor ser&aacute; revalidado antes do pagamento.</p></div>
<?php if (isset($cotacao['lat_origem'], $cotacao['lng_origem'], $cotacao['lat_destino'], $cotacao['lng_destino']) && $cotacao['lat_destino'] !== null && $cotacao['lng_destino'] !== null): ?>
<section class="quote-route-card mt-4" aria-labelledby="quote-route-title">
    <div class="quote-route-head"><div><span class="eyebrow">Trajeto estimado</span><h2 id="quote-route-title">Veja o caminho do reboque</h2></div><span id="quoteRouteStatus" class="muted">Calculando rota...</span></div>
    <div id="publicQuoteMap" class="public-quote-map" data-lat-origin="<?= $e((string)$cotacao['lat_origem']) ?>" data-lng-origin="<?= $e((string)$cotacao['lng_origem']) ?>" data-lat-destination="<?= $e((string)$cotacao['lat_destino']) ?>" data-lng-destination="<?= $e((string)$cotacao['lng_destino']) ?>" data-route-base="<?= $e($bp . '/api/routing/osrm') ?>"></div>
    <p class="muted small mb-0 mt-2">A rota exibida é uma estimativa para explicar a distância usada na cotação.</p>
</section>
<?php endif; ?>
<p class="muted mt-4">Nada foi cobrado e nenhum pedido foi criado. Para proteger seus dados e acompanhar o atendimento, crie sua conta quando decidir continuar.</p>
<form method="post" action="<?= $e($bp) ?>/pre-cotacao/aceitar" data-marketing-event="accept_quote"><input type="hidden" name="csrf_token" value="<?= $e((string)($csrf_token ?? '')) ?>"><button class="btn-main w-100" type="submit">Aceitar e criar cadastro</button></form>
<a class="back d-block text-center mt-3" href="<?= $e($bp) ?>/registro/cliente?retorno=%2Fcliente%2Fpedido%2Fnovo">Ir direto para o cadastro</a>
<a class="back d-block text-center mt-3" href="<?= $e($bp) ?>/pre-cotacao">Voltar e revisar os dados</a>
<?php endif; ?></section></main><script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao.js"></script><!-- form.js DESATIVADO v14 — sintomas.js faz tudo --><script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?>><?php if ($prefillLat !== null && $prefillLng !== null): ?>document.addEventListener('DOMContentLoaded',function(){var lat=document.getElementById('lat_origem'),lng=document.getElementById('lng_origem'),status=document.getElementById('gpsStatus');if(lat&&lng){lat.value=<?= json_encode($prefillLat) ?>;lng.value=<?= json_encode($prefillLng) ?>;}if(status)status.textContent='Localização aproximada da região selecionada. Confirme o ponto exato pelo GPS ou pelo endereço.';});<?php endif; ?><?php if ($forceTowDraft): ?>document.addEventListener('DOMContentLoaded',function(){var tipo=document.getElementById('tipo_problema'),categoria=document.getElementById('categoria'),lat=document.getElementById('lat_origem'),lng=document.getElementById('lng_origem'),status=document.getElementById('gpsStatus');if(tipo)tipo.value='colisao';if(categoria)categoria.value=<?= json_encode($draftCategoria) ?>;if(lat)lat.value=<?= json_encode($draftLatOrigem) ?>;if(lng)lng.value=<?= json_encode($draftLngOrigem) ?>;if(status)status.textContent='Informe agora para onde o veiculo deve ser levado para calcular o reboque.';document.dispatchEvent(new Event('prequote:type-change'));document.dispatchEvent(new Event('prequote:go-destination'));});<?php endif; ?></script>
<script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?>>
window.__preCotacaoSemCobertura = <?= json_encode($_SESSION['pre_cotacao_sem_cobertura'] ?? null) ?>;
window.__preCotacaoWhatsApp = <?= json_encode(
    defined('COMPANY_WHATSAPP') && COMPANY_WHATSAPP !== ''
        ? 'https://wa.me/55' . preg_replace('/\D/', '', (string)COMPANY_WHATSAPP) . '?text=' . rawurlencode('Olá! Estou com emergência no meu carro e preciso de ajuda.')
        : ''
) ?>;
</script>
<?php if ($funilV2On): ?>
<script<?php echo function_exists("csp_script_nonce_attr") ? csp_script_nonce_attr() : ""; ?>>window.__gfBasePath = <?= json_encode($bp) ?>;</script>
<script<?php echo function_exists("csp_script_nonce_attr") ? csp_script_nonce_attr() : ""; ?> src="<?= $e($bp) ?>/public/assets/js/components/address-picker.js?v=20260929-11"></script>
<script<?php echo function_exists("csp_script_nonce_attr") ? csp_script_nonce_attr() : ""; ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-flow.js?v=20260929-11"></script>
<?php else: ?>
<script<?php echo function_exists("csp_script_nonce_attr") ? csp_script_nonce_attr() : ""; ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-sintomas.js"></script>
<?php endif; ?>
<?php require __DIR__ . '/../cliente/_precotacao_extras.php'; ?>
</body></html>
<?php include __DIR__ . '/../components/modelo_atendimento.php'; ?>
