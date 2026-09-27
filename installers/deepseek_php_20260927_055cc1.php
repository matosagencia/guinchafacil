<?php
// install-v18.php — HTML cards + JS v18 + CSS
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];
function r(&$r, $t, $m) { $r[] = "[$t] $m"; }
function put($root, $rel, $c, &$r) {
    $full = $root . '/' . $rel;
    if (!is_dir(dirname($full))) @mkdir(dirname($full), 0777, true);
    if (file_exists($full)) @copy($full, $full . '.bak-v18-' . date('Ymd-His'));
    $bytes = @file_put_contents($full, $c);
    r($r, $bytes === false ? 'ERRO' : 'OK', "$rel ($bytes bytes)");
}

// ═══════════════════════════════════════════════════════════════
// 1. VIEW — reescreve o decisionStage + destinoBox + CSS
// ═══════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v18-' . date('Ymd-His'));

// Remove CSS v16/v17 antigo
$view = preg_replace('#<style>\s*/\* v1[67]:.*?</style>#s', '', $view);
$view = preg_replace('#<style>\s*/\* v17:.*?</style>#s', '', $view);

// SVG inline — mecânico e guincho
$svgMecanico = '<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"><path fill="#2fb34a" d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>';
$svgGuincho = '<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"><path fill="#2fb34a" d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>';

// Novo bloco decisionStage
$novoDecision = <<<HTML

<div class="col-12" id="decisionStage" hidden data-decision-url="<?= \$e(\$bp) ?>/api/pre-cotacao/decisao">
<fieldset class="choice-fieldset">
<legend class="label">Escolha como quer seguir</legend>
<input type="hidden" id="decisao_atendimento" name="decisao_atendimento" value="">
<div class="decision-recommendation" id="decisionRecommendation">Comparando as opções para você...</div>
<div class="decision-grid" id="decisionGrid">
<button type="button" class="decision-card" id="decisionAssistencia" data-decision-choice="assistencia">
  {$svgMecanico}
  <strong>Resolver no local</strong>
  <span class="decision-price" id="assistenciaPrice">R\$ --</span>
  <span class="decision-desc">Um <b>mecânico profissional verificado</b> vai até onde você está em poucos minutos. Ele avalia seu veículo e tenta resolver ali mesmo. Se precisar de peças, você paga a diferença direto pra ele — sem intermediários.</span>
  <span class="decision-benefit">✓ Se ele não conseguir consertar, <b>ele mesmo reboca seu carro até a oficina dele</b> — e o valor da assistência é <b>abatido do conserto</b>.</span>
  <span class="decision-desc" style="margin-top:10px;font-size:.82rem;color:#607066"><b>Uma escolha inteligente:</b> só para tirar um guincho do lugar custa a partir de <b>R\$ 150</b>, fora o trajeto completo. Com a assistência local você paga bem menos e ainda abate do reparo.</span>
  <span class="btn-main w-100 mt-3" id="btnEscolherAssistencia">Quero resolver no local</span>
</button>
<button type="button" class="decision-card" id="decisionReboque" data-decision-choice="reboque">
  {$svgGuincho}
  <strong>Rebocar</strong>
  <span class="decision-desc">Um <b>guincho plataforma</b> pode ser deslocado até você para remover o veículo até o endereço que você informar — oficina, casa ou qualquer outro destino.</span>
  <span class="decision-desc" style="margin-top:10px;font-size:.82rem;color:#607066">O valor é calculado pelo <b>trajeto completo</b> (origem → destino), sem surpresas.</span>
  <span class="btn-main w-100 mt-3" id="btnEscolherReboque">Quero rebocar</span>
</button>
</div>
<p class="muted small mb-0 mt-2" id="decisionFallbackText"></p>
</fieldset>
</div>
HTML;

// Substitui o bloco decisionStage inteiro
$pattern = '#<div class="col-12" id="decisionStage".*?</fieldset>\s*</div>#s';
if (preg_match($pattern, $view)) {
    $view = preg_replace($pattern, $novoDecision, $view, 1);
    r($report, 'OK', 'View: bloco decisionStage substituído com SVG + copy Cialdini');
} else {
    r($report, 'ERRO', 'Não achei o bloco decisionStage para substituir');
}

// Corrige destinoBox: remove d-none, usa hidden
$view = str_replace('<div class="col-12 d-none" id="destinoBox">', '<div class="col-12" id="destinoBox" hidden>', $view);

// CSS v18
$css = <<<'CSS'

<style>
/* v18: controle de telas por body class */
body.stage-address  #btnSituacaoVoltar { display: none !important; }
body.stage-address  .funnel-navigation { display: flex; }
#btnSituacaoAvancar { display: none !important; }
#btnCotacao { display: none !important; }
body.stage-destino #btnCotacao { display: block !important; }

body.stage-decisao .origin-map-composition,
body.stage-destino .origin-map-composition,
body.stage-vehicle .origin-map-composition { display: none !important; }

body.stage-address .origin-map-composition { display: block !important; }

/* Cards A/B */
.decision-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 767px) { .decision-grid { grid-template-columns: 1fr; } }
.decision-card {
    display: flex; flex-direction: column; align-items: flex-start;
    padding: 20px; border: 2px solid #d4e6d8; border-radius: 16px;
    background: #fff; text-align: left; cursor: pointer;
    transition: transform .15s, border-color .15s, box-shadow .15s;
    width: 100%;
}
.decision-card:hover { transform: translateY(-2px); border-color: #2fb34a; box-shadow: 0 6px 20px rgba(47,179,74,.15); }
.decision-card.is-recommended { border-color: #2fb34a; box-shadow: 0 0 0 2px rgba(47,179,74,.15); }
.decision-card.is-selected { border-color: #2fb34a; background: #edf8ef; }
.decision-card svg { width: 48px; height: 48px; margin-bottom: 8px; }
.decision-card strong { font-size: 1.15rem; color: #142018; margin-bottom: 4px; display: block; }
.decision-card .decision-desc { display: block; font-size: .88rem; color: #405247; line-height: 1.5; margin: 6px 0; }
.decision-card .decision-benefit { display: block; font-size: .84rem; color: #248f3a; font-weight: 600; margin-top: 8px; }
.decision-card .decision-price { font-size: 1.6rem; font-weight: 800; color: #2fb34a; margin: 4px 0; display: block; }
.decision-card .btn-main { pointer-events: none; }  /* botão é visual; clique no card todo */
</style>
CSS;
$view = str_replace('</head>', $css . "\n</head>", $view);

@file_put_contents($viewPath, $view);
r($report, 'LINT', trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1')));

// ═══════════════════════════════════════════════════════════════
// 2. JS v18 — controle por body class
// ═══════════════════════════════════════════════════════════════
$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v18 FINAL
   Controle por body class: stage-address | stage-situacao |
   stage-sintoma | stage-decisao | stage-destino
   ============================================================ */
(function () {
    'use strict';
    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }
    function $(id) { return document.getElementById(id); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var API_DECISAO   = '/api/pre-cotacao/decisao';
    var API_OFICINAS  = '/api/pre-cotacao/oficinas-proximas';
    var WHATSAPP = window.__preCotacaoWhatsApp || '';
    var SEM_COBERTURA = !!(window.__preCotacaoSemCobertura && window.__preCotacaoSemCobertura.status);

    var oficinasNoRaio = null;
    var tipoEscolhido = '';
    var destinoMap = null;
    var destinoMarker = null;

    // ═══ Stages ═══
    var STAGES = ['situacaoStage','sintomaStage','decisionStage','destinoBox',
                  'destinationStage','vehicleStage','fieldVeiculoPodeMover'];

    function hideStages() {
        STAGES.forEach(function(id){ var el=$(id); if (el) el.hidden = true; });
    }

    function setStage(nome) {
        // nome: address | situacao | sintoma | decisao | destino
        document.body.classList.remove('stage-address','stage-situacao','stage-sintoma','stage-decisao','stage-destino');
        document.body.classList.add('stage-' + nome);
    }

    function mostrar(id) {
        hideStages();
        var el = $(id); if (el) el.hidden = false;

        // Mapeia id→nome de estágio
        var mapa = {
            'situacaoStage': 'situacao',
            'sintomaStage': 'sintoma',
            'decisionStage': 'decisao',
            'destinoBox': 'destino'
        };
        if (mapa[id]) setStage(mapa[id]);
        log('mostrar:', id, '| stage:', mapa[id] || id);
    }

    // ═══ Anti-form.js ═══
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'tipo_problema') e.stopImmediatePropagation();
    }, true);

    // ═══ Busca oficinas ═══
    function buscarOficinas() {
        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) { mostrar('situacaoStage'); return; }
        log('buscando oficinas...');
        var url = API_OFICINAS + '?lat=' + encodeURIComponent(lat.value) + '&lng=' + encodeURIComponent(lng.value);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                oficinasNoRaio = (j.data && j.data.oficinas) || j.oficinas || [];
                log('oficinas no raio:', oficinasNoRaio.length);
                if (oficinasNoRaio.length === 0) {
                    log('CASO A — 0 oficinas → destino');
                    tipoEscolhido = 'reboque';
                    mostrar('destinoBox');
                    ensureDestinoMap();
                } else {
                    log('CASO B —', oficinasNoRaio.length, '→ situação');
                    mostrar('situacaoStage');
                }
            })
            .catch(function (err) { log('ERRO busca:', err); mostrar('situacaoStage'); });
    }

    document.addEventListener('prequote:location-confirmed', function () {
        log('endereço confirmado');
        buscarOficinas();
    });

    // ═══ Click global ═══
    document.addEventListener('click', function (e) {
        // Card A/B
        var dcard = e.target.closest && e.target.closest('.decision-card');
        if (dcard) {
            e.stopImmediatePropagation(); e.preventDefault();
            var id = dcard.id;
            log('clique decision-card:', id);

            document.querySelectorAll('.decision-card').forEach(function (c) {
                c.classList.remove('is-selected');
            });
            dcard.classList.add('is-selected');

            if (id === 'decisionAssistencia') {
                log('assistência → cadastro');
                var formAcc = document.querySelector('form[action$="/pre-cotacao/aceitar"]');
                if (formAcc) formAcc.submit();
                else location.href = '/registro/cliente?retorno=%2Fcliente%2Fpedido%2Fnovo';
            } else if (id === 'decisionReboque') {
                log('reboque → destino');
                tipoEscolhido = 'reboque';
                var di = $('decisao_atendimento'); if (di) di.value = 'reboque';
                mostrar('destinoBox');
                ensureDestinoMap();
            }
            return;
        }

        // Cards de situação/sintoma
        var card = e.target.closest && e.target.closest('[data-choice-group]');
        if (card) {
            var g = card.getAttribute('data-choice-group');
            var v = card.getAttribute('data-choice-value');

            if (g === 'tipo_problema') {
                e.stopImmediatePropagation(); e.preventDefault();
                log('clique situação:', v);
                var t = $('tipo_problema'); if (t) t.value = v;
                document.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                if (v === 'me_orientem' || v === 'resolver_local') mostrar('sintomaStage');
                else if (v === 'levar_carro') {
                    tipoEscolhido = 'reboque';
                    var di = $('decisao_atendimento'); if (di) di.value = 'reboque';
                    mostrar('destinoBox');
                    ensureDestinoMap();
                }
                return;
            }

            if (g === 'sintoma') {
                e.stopImmediatePropagation(); e.preventDefault();
                log('clique sintoma:', v);
                document.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                var s = $('sintoma'); if (s) s.value = v;
                tipoEscolhido = MAPA[v] || 'mecanica';
                aplicarSintoma(tipoEscolhido);
                return;
            }
        }

        // Voltar
        if (e.target.closest('#btnSituacaoVoltar')) {
            e.stopImmediatePropagation(); e.preventDefault();
            var noDestino = $('destinoBox') && !$('destinoBox').hidden;
            var noDecision = $('decisionStage') && !$('decisionStage').hidden;
            var noSintoma = $('sintomaStage') && !$('sintomaStage').hidden;
            if (noDecision) { mostrar('sintomaStage'); return; }
            if (noDestino || noSintoma) { mostrar('situacaoStage'); return; }
            location.reload();
            return;
        }

        // btnCotacao — só envia o form se destinoBox visível
        if (e.target.closest('#btnCotacao')) {
            var ld = $('lat_destino'), lnd = $('lng_destino');
            if ((!ld || !ld.value) && destinoMarker) {
                var pos = destinoMarker.getLatLng();
                if (ld) ld.value = pos.lat;
                if (lnd) lnd.value = pos.lng;
            }
            return;
        }
    }, true);

    // ═══ Aplicar sintoma ═══
    function aplicarSintoma(tipo) {
        if (SEM_COBERTURA || (oficinasNoRaio && oficinasNoRaio.length === 0)) tipo = 'reboque';
        log('aplicarSintoma:', tipo);
        var t = $('tipo_problema'); if (t) t.value = tipo;

        mostrar('decisionStage');
        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Calculando valores...';

        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng) return;

        fetch(API_DECISAO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: tipo, veiculo_pode_mover: true,
                lat_origem: Number(lat.value), lng_origem: Number(lng.value),
                categoria: 'popular', distancia_km: 5.0
            }})
        })
        .then(function (r) { return r.json(); })
        .then(function (p) { render(p.data || p); })
        .catch(function (err) { log('ERRO API:', err); mostrarWhatsApp(); });
    }

    function money(v) { return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }

    function mostrarWhatsApp() {
        var rec = $('decisionRecommendation');
        if (!rec) return;
        if (!WHATSAPP) { rec.textContent = 'Aguarde, vamos te transferir para o suporte.'; return; }
        rec.innerHTML = '<div style="padding:12px 0">' +
            '<p style="margin:0 0 8px"><strong>Vamos resolver direto com você.</strong></p>' +
            '<p style="margin:0 0 12px;font-size:.9rem">Fale agora com um atendente no WhatsApp.</p>' +
            '<a href="' + WHATSAPP + '" target="_blank" rel="noopener" ' +
            'style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;' +
            'padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:700">' +
            '<i class="fab fa-whatsapp" style="font-size:1.2rem"></i>Falar com atendente</a></div>';
    }

    function render(data) {
        log('render:', data);
        if (data.acao === 'encaminhar_suporte' || data.acao === 'aguardando_sintoma') { mostrarWhatsApp(); return; }

        var ap = $('assistenciaPrice'), as = $('assistenciaSaida'), rd = $('reboqueDeslocamento');
        var da = $('decisionAssistencia'), dr = $('decisionReboque');
        var rec = $('decisionRecommendation');
        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};
        var semAssist = !assist.disponivel || data.sem_oficina === true || SEM_COBERTURA;

        if (semAssist) {
            log('sem assistência → overlay + destino');
            hideStages();
            var valor = Number(tow.custo_total || 0);
            var aviso = document.createElement('div');
            aviso.style.cssText = 'position:fixed;inset:0;background:rgba(15,17,21,.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';
            aviso.innerHTML = '<div style="background:#fff;padding:24px;border-radius:16px;max-width:420px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3)">' +
                '<p style="margin:0 0 12px;font-size:1.1rem;font-weight:700;color:#142018">Sem assistência na sua região</p>' +
                '<p style="margin:0 0 16px;color:#607066;font-size:.92rem;line-height:1.5">Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher.</p>' +
                '<p style="margin:0 0 8px;font-size:.85rem;color:#607066">Valor estimado</p>' +
                '<p style="margin:0;font-size:1.5rem;font-weight:800;color:#d97706">' + money(valor) + '</p>' +
                '<p style="margin:16px 0 0;font-size:.8rem;color:#607066">Abrindo destino…</p></div>';
            document.body.appendChild(aviso);
            setTimeout(function () {
                aviso.remove();
                tipoEscolhido = 'reboque';
                var di = $('decisao_atendimento'); if (di) di.value = 'reboque';
                mostrar('destinoBox');
                ensureDestinoMap();
            }, 1500);
            return;
        }

        if (ap) ap.textContent = money(assist.custo_saida);
        if (as) as.textContent = money(assist.custo_saida);
        if (rd) rd.textContent = money(tow.custo_total);
        if (da) da.hidden = false;
        if (dr) dr.hidden = false;

        if (rec) {
            rec.innerHTML = '<strong>Recomendamos resolver no local</strong> — mais rápido e mais barato. ' +
                'Se o mecânico não conseguir consertar aqui, <strong>ele mesmo reboca seu carro até a oficina</strong> ' +
                'e o valor da assistência é <strong>abatido do conserto</strong>.';
        }
        if (da) da.classList.add('is-recommended');
        log('render OK');
    }

    function ensureDestinoMap() {
        if (destinoMap || !window.L) return;
        var box = $('destinoBox');
        if (!box) return;
        var old = document.getElementById('destinoMapWrap');
        if (old) old.remove();
        var wrap = document.createElement('div');
        wrap.id = 'destinoMapWrap';
        wrap.style.cssText = 'height:240px;border-radius:10px;margin-top:12px;position:relative;z-index:1';
        box.appendChild(wrap);
        setTimeout(function () {
            var latO = $('lat_origem'), lngO = $('lng_origem');
            var center = (latO && latO.value && lngO && lngO.value)
                ? [Number(latO.value), Number(lngO.value)]
                : [-22.9068, -43.1729];
            destinoMap = L.map(wrap).setView(center, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(destinoMap);
            var iconDest = L.divIcon({
                html: '<i class="fas fa-flag-checkered" style="color:#2fb34a;font-size:28px;text-shadow:0 0 3px rgba(0,0,0,.6)"></i>',
                iconSize: [28, 32], iconAnchor: [14, 32], className: ''
            });
            destinoMarker = L.marker(center, { draggable: true, icon: iconDest }).addTo(destinoMap);
            function sync(lat, lng) {
                var ld = $('lat_destino'); if (ld) ld.value = lat;
                var lnd = $('lng_destino'); if (lnd) lnd.value = lng;
                calcCota(lat, lng);
            }
            destinoMarker.on('dragend', function () { var p = destinoMarker.getLatLng(); sync(p.lat, p.lng); });
            destinoMap.on('click', function (ev) { destinoMarker.setLatLng(ev.latlng); sync(ev.latlng.lat, ev.latlng.lng); });
            sync(center[0], center[1]);
            setTimeout(function () { destinoMap.invalidateSize(); }, 100);
        }, 50);
    }

    function calcCota(latDest, lngDest) {
        var latO = $('lat_origem'), lngO = $('lng_origem');
        if (!latO || !lngO) return;
        var dLat = (latDest - Number(latO.value)) * Math.PI / 180;
        var dLng = (lngDest - Number(lngO.value)) * Math.PI / 180;
        var lat1 = Number(latO.value) * Math.PI / 180;
        var lat2 = latDest * Math.PI / 180;
        var a = Math.sin(dLat/2)**2 + Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLng/2)**2;
        var dist = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        log('distância origem→destino:', dist.toFixed(2), 'km');
        fetch(API_DECISAO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: 'reboque', veiculo_pode_mover: true,
                lat_origem: Number(latO.value), lng_origem: Number(lngO.value),
                categoria: 'popular', distancia_km: Math.max(1, dist)
            }})
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            var d = j.data || j;
            var tow = d.opcao_reboque || {};
            var rd = $('reboqueDeslocamento');
            if (rd && tow.custo_total) rd.textContent = money(tow.custo_total);
            log('cota atualizada:', tow.custo_total);
        })
        .catch(function (e) { log('erro cota:', e); });
    }

    function init() {
        log('init v18');
        hideStages();
        setStage('address');
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
JSEOF;

put($root, 'public/assets/js/public-pre-cotacao-sintomas.js', $js, $report);

// ═══════════════════════════════════════════════════════════════
// 3. Diagnóstico
// ═══════════════════════════════════════════════════════════════
r($report, '', '── DIAGNÓSTICO ──');
try {
    require_once $root . '/config.php';
    $t = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1 AND disponivel=1")->fetchColumn();
    r($report, 'OK', "oficinas online: $t");
} catch (Throwable $e) { r($report, 'AVISO', $e->getMessage()); }

r($report, '', '── O QUE MUDOU ──');
r($report, 'INFO', '1. Cards A/B com SVG (mecânico + guincho) e copy Cialdini');
r($report, 'INFO', '2. JS v18: body class controla quais botões aparecem');
r($report, 'INFO', '3. Mapa de origem some em stage-decisao/stage-destino');
r($report, 'INFO', '4. btnCotacao só aparece em stage-destino');
r($report, 'INFO', '');
r($report, 'INFO', 'TESTE:');
r($report, 'INFO', '1. Ctrl+Shift+R em /pre-cotacao');
r($report, 'INFO', '2. Confirma endereço → situação');
r($report, 'INFO', '3. Me orientem → Pneu → A/B com SVG + copy');
r($report, 'INFO', '4. Clica B → destinoBox + mapa');
r($report, 'INFO', '5. "Levar o carro" → destinoBox');

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v18</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔥 Installer v18 — Cards SVG + JS body-class</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v18.php</p>
</body></html>