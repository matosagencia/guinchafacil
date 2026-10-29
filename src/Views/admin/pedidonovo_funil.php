<?php
/**
 * /admin/pedido/novo/funil - Etapa 2 do funil admin (Caminho 3).
 *
 * Reaproveita 100% do funil publico v2:
 *   - public-pre-cotacao.css / public-pre-cotacao-map.css
 *   - components/address-picker.css + .js
 *   - public-pre-cotacao-flow.js (motor de estagios)
 *   - /api/pre-cotacao/* (endpoints publicos, sem auth)
 *
 * Diferencas em relacao ao /pre-cotacao publico:
 *   - header.php + sidebar_admin.php + footer.php (shell admin)
 *   - banner "criando em nome de: <cliente>"
 *   - 3 hidden inputs (cliente_id, veiculo_id, guincho_id) do contexto
 *   - form action aponta pra /admin/pedido/criar
 *
 * Variaveis do controller:
 *   $bp, $csrf_token, $contexto (cliente_nome, veiculo_resumo, guincho_nome)
 *   $adminCtx (cliente_id, veiculo_id, guincho_id)
 */
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$ctx = $contexto ?? [];
$adminCtx = $adminCtx ?? [];
$csrf_token = $csrf_token ?? '';

include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao.css?v=20260812-3">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao-map.css?v=20260924-1">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/components/address-picker.css?v=20260929-11">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/admin-pedido-flow.css?v=20261002-1">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/lovable-admin-funil.css?v=20261016-4b">

<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/gf-admin-funil-lovable-light-v2.css?v=20261016">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/gf-admin-criar-pedido-lovable-v3.css?v=20261016-9">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/gf-admin-pedido-disponibilidade-v1.css?v=20261016-10">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/gf-admin-funil-workspace-v4.css?v=20261017-11">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content gf-lovable-admin-flow gf-admin-order-v3">

    <nav class="gf-order-crumb" aria-label="Localização"><a href="<?= $e($bp) ?>/admin/dashboard">Backoffice</a><span aria-hidden="true">›</span><a href="<?= $e($bp) ?>/admin/pedidos">Pedidos</a><span aria-hidden="true">›</span><strong>Novo pedido</strong></nav>
    <header class="gf-order-intro"><div><span class="gf-order-eyebrow">ATENDIMENTO / TRIAGEM E PRECIFICAÇÃO</span><h1>Novo pedido de socorro</h1><p>Informe a ocorrência e confirme a cotação oficial.</p></div><a class="gf-order-back" href="<?= $e($bp) ?>/admin/pedido/novo/v2">Alterar cliente ou veículo</a></header>
    <ol class="gf-order-progress" aria-label="Etapas do pedido">
      <li class="is-complete"><span aria-label="Concluído">✓</span><div><strong>Cliente e veículo</strong><small>Identificados</small></div></li>
      <li class="is-active" aria-current="step"><span>2</span><div><strong>Triagem e cotação</strong><small>Em andamento</small></div></li>
    </ol>
    <div class="admin-flow-context">
        <div>
            <span class="admin-flow-context__label">Criando pedido em nome de:</span>
            <strong><?= $e($ctx['cliente_nome'] ?? '') ?></strong>
            <span class="text-muted">
                 Veículo: <?= $e($ctx['veiculo_resumo'] ?? '') ?>
            </span>
        </div>
        <a href="<?= $e($bp) ?>/admin/pedido/novo/v2" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-pen me-1"></i>Trocar contexto
        </a>
    </div>


    <!-- Contadores operacionais exclusivos da area Admin; dados consultados por coordenada. -->
    <section class="gf-ops-availability" id="gfOpsAvailability" aria-label="Disponibilidade regional de parceiros" data-endpoint="<?= $e($bp) ?>/public/admin-disponibilidade.php">
      <div class="gf-ops-availability__intro"><span class="gf-order-eyebrow">REDE DE ATENDIMENTO / REGIÃO</span><h2>Disponibilidade para este chamado</h2><p>Indicadores em tempo real de elegibilidade operacional. Não representam reserva nem aceite do parceiro.</p></div>
      <div class="gf-ops-availability__grid">
        <div class="gf-ops-availability__metric"><span>Guinchos aptos</span><strong id="gfOpsGuinchos">—</strong><small id="gfOpsGuinchosHint">Confirme a origem no mapa</small></div>
        <div class="gf-ops-availability__metric"><span>Oficinas aptas a receber</span><strong id="gfOpsOficinas">—</strong><small id="gfOpsOficinasHint">Confirme a origem ou o destino</small></div>
      </div>
      <details style="margin-top:12px;border-top:1px solid #dce3e8;padding-top:10px" id="gfOpsDetails">
        <summary style="cursor:pointer;font-weight:600">Ver elegibilidade individual e motivos de exclusão</summary>
        <div id="gfOpsDetailsBody" style="display:grid;gap:8px;padding-top:10px"><small>Confirme a origem para consultar.</small></div>
      </details>
      <p class="gf-ops-availability__note" id="gfOpsStatus" role="status" aria-live="polite">Aguardando endereço confirmado.</p>
    </section>

    <section class="gf-f11-stage-tracker" id="gfF11Tracker" aria-label="Andamento da triagem">
      <div class="gf-f11-stage-tracker__label"><span>ETAPA OPERACIONAL</span><strong id="gfF11StageLabel">Localização da ocorrência</strong></div>
      <div class="gf-f11-stage-tracker__meter" role="progressbar" aria-label="Progresso indicativo da triagem" aria-valuemin="1" aria-valuemax="7" aria-valuenow="1" id="gfF11Progress"><span id="gfF11ProgressFill"></span></div>
      <small id="gfF11StageHelp">Confirme a localização para consultar a rede de atendimento.</small>
    </section>

    <div class="card gf-order-triage-card">
        <p class="eyebrow">ETAPA 2 / ATENDIMENTO</p>
        <h1 class="title" id="admin-flow-title">Onde está o veículo?</h1>
        <p class="muted mb-0">Informe localização, necessidade de atendimento e destino quando aplicável. Revise a cotação antes de criar o pedido.</p>

        <div class="mt-4">
            <?php require __DIR__ . '/partials/_precotacao_funil_admin.php'; ?>
        </div>
    </div>

</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function () {
  'use strict';
  var panel = document.getElementById('gfOpsAvailability');
  if (!panel) return;
  var endpoint = panel.getAttribute('data-endpoint');
  var origin = null, dest = null, timer = null, current = null, reqNo = 0;
  function el(id) { return document.getElementById(id); }
  function text(id, value) { var node = el(id); if (node) node.textContent = String(value); }
  function valid(x) { return typeof x === 'number' && isFinite(x); }
  function point(d) {
    var lat = Number(d.lat), lng = Number(d.lng);
    return valid(lat) && valid(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? {lat:lat,lng:lng} : null;
  }
  function renderDetails(data) {
    var holder=el('gfOpsDetailsBody'); if (!holder) return;
    holder.replaceChildren();
    var diag=data && data.diagnostico;
    if (!diag) {holder.textContent='Diagnostico indisponivel';return;}
    function section(title, items, format) {
      var head=document.createElement('strong');head.textContent=title;holder.appendChild(head);
      if (!items || !items.length) {var empty=document.createElement('small');empty.textContent='Nenhum cadastro encontrado';holder.appendChild(empty);return;}
      items.forEach(function(item){
        var node=document.createElement('div');
        node.style.cssText='padding:9px;border:1px solid #e0e5e8;border-radius:8px;background:#fff;font-size:13px;';
        node.textContent=format(item);holder.appendChild(node);
      });
    }
    section('Guinchos candidatos ao reboque', diag.guinchos, function(g){
      return 'Guincho #'+g.id+' · '+g.dist_km+' km · aprovado/possivel candidato · online: nao verificado · '+g.observacao;
    });
    section('Providers de oficina',diag.providers_oficina,function(w){
      return w.nome+' (#'+w.id+') · aprovado: '+(w.aprovado?'sim':'nao')+' · patio: '+(w.recebe_patio?'sim':'nao')+
        ' · raio 20km: '+(w.dentro_raio?'sim':'nao')+' · GPS: '+w.fonte_gps+
        ' · online: nao verificado · '+(w.motivos.length?w.motivos.join('; '):'Apto ao contador; confirmar disponibilidade real');
    });
    section('Oficinas no cadastro legado',diag.oficinas_legadas,function(w){
      return w.nome+' (#'+w.id+') · '+(w.dist_km===null?'distancia nao disponivel':w.dist_km+' km')+
        ' · '+w.observacao+' · online: nao verificado';
    });
    var note=document.createElement('small');note.textContent=diag.nota_online;holder.appendChild(note);
  }
  function update() {
    if (!origin) return;
    if (current) current.abort();
    var myReq = ++reqNo;
    current = new AbortController();
    text('gfOpsStatus', 'Consultando elegibilidade regional…');
    var params = new URLSearchParams({lat:String(origin.lat),lng:String(origin.lng)});
    if (dest) {params.set('dest_lat', String(dest.lat));params.set('dest_lng',String(dest.lng));}
    fetch(endpoint + '?' + params.toString(), {credentials:'same-origin',cache:'no-store',signal:current.signal,headers:{'Accept':'application/json'}})
      .then(function(r){if (!r.ok) throw Error('HTTP ' + r.status);return r.json();})
      .then(function(data){
        if (myReq !== reqNo) return;
        if (!data || data.ok !== true) throw Error('Consulta indisponível');
        renderDetails(data);
        text('gfOpsGuinchos', data.guinchos === null ? '—' : data.guinchos);
        text('gfOpsOficinas', data.oficinas === null ? '—' : data.oficinas);
        text('gfOpsGuinchosHint','Candidatos para reboque, nos anéis de cobertura');
        text('gfOpsOficinasHint','Parceiras em até ' + data.raio_oficinas_km + ' km da ' + (dest ? 'destinação' : 'origem') + ' (linha reta)');
        text('gfOpsStatus','Leitura operacional atualizada. Confirme a disponibilidade diretamente antes de despachar.');
      }).catch(function(error){if (error.name === 'AbortError') return;
        renderDetails(null);
        text('gfOpsGuinchos','—');text('gfOpsOficinas','—');
        text('gfOpsStatus','Consulta indisponível: não interprete como zero parceiros.');
      });
  }
  document.addEventListener('gf:address-confirmed',function(e){
    var d = e && e.detail || {}; var p=point(d); if (!p) return;
    if (d.role === 'origem') origin=p;
    else if (d.role === 'destino') dest=p;
    else return;
    clearTimeout(timer); timer=setTimeout(update,220);
  });
})();
</script>


<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function () {
  'use strict';
  var stages = [
    ['endereco','Localização da ocorrência','Confirme a origem no mapa antes de prosseguir.'],
    ['modo','Modalidade do socorro','Defina se o cliente precisa de orientação, atendimento no local ou reboque.'],
    ['veiculo','Categoria do veículo','Confirme o tipo de veículo para precificação e elegibilidade.'],
    ['sintoma','Triagem técnica','Selecione o serviço conforme os sintomas relatados.'],
    ['opcoes','Decisão de atendimento','Revise as possibilidades de solução para a ocorrência.'],
    ['destino','Destino do veículo','Confirme o endereço de entrega antes de calcular a rota.'],
    ['cotacao','Revisão e pagamento','Confira a cotação oficial e a forma de pagamento antes de criar o pedido.']
  ];
  var progress = document.getElementById('gfF11Progress');
  var fill = document.getElementById('gfF11ProgressFill');
  var label = document.getElementById('gfF11StageLabel');
  var help = document.getElementById('gfF11StageHelp');
  document.addEventListener('gf:flow-stage', function (event) {
    var name = event && event.detail && event.detail.stage;
    var index = stages.findIndex(function (item) { return item[0] === name; });
    if (index < 0) return;
    if (label) label.textContent = stages[index][1];
    if (help) help.textContent = stages[index][2];
    if (fill) fill.style.width = ((index + 1) * 100 / stages.length).toFixed(2) + '%';
    if (progress) progress.setAttribute('aria-valuenow', String(index + 1));
  });
})();
</script>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>window.__gfBasePath = <?= json_encode($bp) ?>;</script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/js/components/address-picker.js?v=20260929-11"></script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
window.__gfFlowOptions = window.__gfFlowOptions || {};
<?php if (!empty($adminCtx['veiculo_id']) && !empty($adminCtx['veiculo_categoria'])): ?>
window.__gfFlowOptions.skipVeiculo = true;
window.__gfFlowOptions.veiculoCategoria = <?= json_encode($adminCtx['veiculo_categoria']) ?>;
<?php endif; ?>
</script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-flow.js?v=20260929-11"></script>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function () {
    'use strict';
    var TITLES = {
        'endereco': 'Onde está o veículo?',
        'modo':     'O que você precisa?',
        'veiculo':  'Qual o tipo do veículo?',
        'sintoma':  'O que está acontecendo?',
        'opcoes':   'Como você quer prosseguir?',
        'destino':  'Para onde levar o veículo?',
        'cotacao':  'Confirme seu pedido'
    };
    document.addEventListener('gf:flow-stage', function (ev) {
        var stage = ev && ev.detail && ev.detail.stage;
        var el = document.getElementById('admin-flow-title');
        if (!el || !stage || !TITLES[stage]) return;
        el.textContent = TITLES[stage];
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>