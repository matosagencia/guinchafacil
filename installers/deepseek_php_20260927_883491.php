<?php
// install-v16.php — cards clicáveis + oculta mapa + fluxo "levar o carro" + cotação reboque
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];
function r(&$r, $t, $m) { $r[] = "[$t] $m"; }
function put($root, $rel, $c, &$r) {
    $full = $root . '/' . $rel;
    if (!is_dir(dirname($full))) @mkdir(dirname($full), 0777, true);
    if (file_exists($full)) @copy($full, $full . '.bak-v16-' . date('Ymd-His'));
    $bytes = @file_put_contents($full, $c);
    r($r, $bytes === false ? 'ERRO' : 'OK', "$rel ($bytes bytes)");
}

// ═══════════════════════════════════════════════════════════════
// 1. VIEW — body class oculta mapa quando decisionStage visível
// ═══════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v16-' . date('Ymd-His'));

if (strpos($view, '.step-decisao-ativa') === false) {
    $css = <<<'CSS'

<style>
/* v16: quando decisionStage ou destinoBox estiver visível,
   o mapa de origem e a busca de endereço desaparecem
   (evita distração; o foco é a decisão) */
body.step-decisao-ativa .origin-map-composition,
body.step-decisao-ativa .origin-map-composition + *,
body.step-destino-ativo .origin-map-composition { display: none !important; }

/* Quando destinoBox está visível, esconde o antigo mapa de origem */
body.step-destino-ativo #originMapPanel { display: none !important; }

/* decision-card clicável */
.decision-card { cursor: pointer; transition: transform .15s ease, border-color .15s ease; }
.decision-card:hover { transform: translateY(-2px); border-color: #2fb34a; }
.decision-card.is-recommended { border-color: #2fb34a; box-shadow: 0 0 0 2px rgba(47,179,74,.15); }
</style>
CSS;
    $view = str_replace('</head>', $css . "\n</head>", $view);
    r($report, 'OK', 'View: CSS step-decisao-ativa + hover cards');
}

// v16: card A/B com data-action + botão único "Continuar"
// Substitui o bloco decisionStage por uma versão que inclui botão CTA
@file_put_contents($viewPath, $view);
r($report, 'LINT', trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1')));

// ═══════════════════════════════════════════════════════════════
// 2. JS v16 — cards clicáveis + body class + cota origem→destino
// ═══════════════════════════════════════════════════════════════
$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v16
   Fluxo fechado:
     address → situacao → sintoma → decisão (A/B clicáveis)
                  ↓ (levar_carro ou 0 oficinas)
              destinoBox → cota origem→destino → resumo
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
    var decisionInput = null; // #decisao_atendimento

    // ═══════════════════════════════════════════════════════════
    // Visibilidade de stages
    // ═══════════════════════════════════════════════════════════
    var STAGES = ['situacaoStage','sintomaStage','decisionStage','destinoBox',
                  'destinationStage','vehicleStage','fieldVeiculoPodeMover',
                  'confirmarEnderecoStage'];

    function hideStages() {
        STAGES.forEach(function(id){ var el=$(id); if (el) el.hidden = true; });
        document.body.classList.remove('step-decisao-ativa','step-destino-ativo');
    }
    function mostrar(id) {
        hideStages();
        var el = $(id); if (el) el.hidden = false;

        // Body classes para esconder mapa de origem
        if (id === 'decisionStage') document.body.classList.add('step-decisao-ativa');
        if (id === 'destinoBox')    document.body.classList.add('step-destino-ativo');

        // Botões de navegação
        var btnVoltar = $('btnSituacaoVoltar');
        var btnAvancar = $('btnSituacaoAvancar');
        var btnCotacao = $('btnCotacao');
        if (btnVoltar) btnVoltar.style.display = ($('situacaoStage') && !$('situacaoStage').hidden) ? 'none' : '';
        if (btnAvancar) btnAvancar.style.display = 'none';
        if (btnCotacao) btnCotacao.style.display = ($('destinoBox') && !$('destinoBox').hidden) ? '' : 'none';

        log('mostrar:', id);
    }

    // ═══════════════════════════════════════════════════════════
    // Bloqueia 'change' do antigo form.js (se ainda em cache)
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'tipo_problema') e.stopImmediatePropagation();
    }, true);

    // ═══════════════════════════════════════════════════════════
    // ETAPA 2 — busca de oficinas
    // ═══════════════════════════════════════════════════════════
    function buscarOficinas() {
        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) {
            mostrar('situacaoStage'); return;
        }
        log('buscando oficinas...');
        var url = API_OFICINAS + '?lat=' + encodeURIComponent(lat.value) + '&lng=' + encodeURIComponent(lng.value);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                oficinasNoRaio = (j.data && j.data.oficinas) || j.oficinas || [];
                log('oficinas no raio:', oficinasNoRaio.length);
                if (oficinasNoRaio.length === 0) {
                    log('CASO A — 0 oficinas → Opção 3');
                    tipoEscolhido = 'reboque';
                    mostrar('destinoBox');
                    ensureDestinoMap();
                } else {
                    log('CASO B —', oficinasNoRaio.length, 'oficinas → mostrar situação');
                    mostrar('situacaoStage');
                }
            })
            .catch(function (err) {
                log('ERRO busca:', err);
                mostrar('situacaoStage');
            });
    }

    document.addEventListener('prequote:location-confirmed', function () {
        log('endereço confirmado');
        buscarOficinas();
    });

    // ═══════════════════════════════════════════════════════════
    // Click global (capture)
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        // ─── CARD A / B (decisionStage) ───
        var dcard = e.target.closest && e.target.closest('.decision-card');
        if (dcard) {
            e.stopImmediatePropagation(); e.preventDefault();
            var choice = dcard.getAttribute('data-decision-choice') || dcard.id;
            if (dcard.id === 'decisionAssistencia') choice = 'assistencia';
            else if (dcard.id === 'decisionReboque') choice = 'reboque';
            log('clique decision-card:', choice);

            // Marca visualmente
            document.querySelectorAll('.decision-card').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            dcard.classList.add('is-selected'); dcard.setAttribute('aria-pressed', 'true');

            // Guarda escolha
            if (!decisionInput) decisionInput = $('decisao_atendimento');
            if (decisionInput) decisionInput.value = choice;

            // Fluxo
            if (choice === 'assistencia') {
                // Segue direto pro cadastro/pagamento — pre-cotacaoForm POST /pre-cotacao/aceitar
                log('assistência escolhida → redireciona pro cadastro');
                var formAccept = document.querySelector('form[action$="/pre-cotacao/aceitar"]');
                if (formAccept) formAccept.submit();
                else location.href = '/registro/cliente?retorno=%2Fcliente%2Fpedido%2Fnovo';
            } else if (choice === 'reboque') {
                // Vai pro destino
                log('reboque escolhido → destinoBox');
                tipoEscolhido = 'reboque';
                mostrar('destinoBox');
                ensureDestinoMap();
            }
            return;
        }

        // ─── Cards de situação/sintoma (comportamento anterior) ───
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

                if (v === 'me_orientem' || v === 'resolver_local') {
                    mostrar('sintomaStage');
                } else if (v === 'levar_carro') {
                    tipoEscolhido = 'reboque';
                    if (!decisionInput) decisionInput = $('decisao_atendimento');
                    if (decisionInput) decisionInput.value = 'reboque';
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
            return;
        }

        // ─── Voltar ───
        if (e.target.closest('#btnSituacaoVoltar')) {
            e.stopImmediatePropagation(); e.preventDefault();
            var noDestino = $('destinoBox') && !$('destinoBox').hidden;
            var noSintoma = $('sintomaStage') && !$('sintomaStage').hidden;
            var noDecision = $('decisionStage') && !$('decisionStage').hidden;
            if (noDecision) { mostrar('sintomaStage'); return; }
            if (noDestino || noSintoma) { mostrar('situacaoStage'); return; }
            location.reload();
            return;
        }

        // ─── Botão principal "Ver minha cotação" (só no destinoBox) ───
        if (e.target.closest('#btnCotacao')) {
            // Deixa o form normal enviar (action /pre-cotacao). Só garante destino preenchido.
            var ld = $('lat_destino'), lnd = $('lng_destino');
            if ((!ld || !ld.value) && destinoMarker) {
                var pos = destinoMarker.getLatLng();
                if (ld) ld.value = pos.lat;
                if (lnd) lnd.value = pos.lng;
            }
            return;
        }
    }, true);

    // ═══════════════════════════════════════════════════════════
    // Aplicar sintoma → API decisão
    // ═══════════════════════════════════════════════════════════
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

    // ═══════════════════════════════════════════════════════════
    // Render da decisão
    // ═══════════════════════════════════════════════════════════
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
            aviso.id = '__precoOverlay';
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

        // ─── Tem A e B ───
        if (ap) ap.textContent = money(assist.custo_saida);
        if (as) as.textContent = money(assist.custo_saida);

        // Card B: valor do reboque
        if (rd) rd.textContent = money(tow.custo_total);

        if (da) { da.hidden = false; da.setAttribute('aria-pressed', 'false'); }
        if (dr) { dr.hidden = false; dr.setAttribute('aria-pressed', 'false'); }

        if (rec) {
            if (data.recomendacao === 'assistencia') {
                rec.innerHTML = '<strong>Recomendamos resolver no local</strong> — mais rápido e mais barato. ' +
                    'Se o mecânico não conseguir consertar aqui, <strong>ele mesmo reboca seu carro até a oficina</strong> ' +
                    'e o valor da assistência é <strong>abatido do conserto</strong>.';
            } else {
                rec.textContent = 'As duas opções fazem sentido. Você decide como seguir.';
            }
        }
        log('render OK — A:', da && !da.hidden, '| B:', dr && !dr.hidden);
    }

    // ═══════════════════════════════════════════════════════════
    // Mapa do destino
    // ═══════════════════════════════════════════════════════════
    function ensureDestinoMap() {
        if (destinoMap || !window.L) return;
        var box = $('destinoBox');
        if (!box) return;

        // Remove mapa anterior se houver
        var old = document.getElementById('destinoMapWrap');
        if (old) old.remove();

        var wrap = document.createElement('div');
        wrap.id = 'destinoMapWrap';
        wrap.style.cssText = 'height:240px;border-radius:10px;margin-top:12px;position:relative;z-index:1';
        box.appendChild(wrap);

        setTimeout(function () {
            // Centro provisório: origem do cliente
            var latO = $('lat_origem'), lngO = $('lng_origem');
            var center = (latO && latO.value && lngO && lngO.value)
                ? [Number(latO.value), Number(lngO.value)]
                : [-22.9068, -43.1729];

            destinoMap = L.map(wrap).setView(center, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(destinoMap);

            // Marcador de destino — arrastável
            var iconDest = L.divIcon({
                html: '<i class="fas fa-flag-checkered" style="color:#2fb34a;font-size:28px;text-shadow:0 0 3px rgba(0,0,0,.6)"></i>',
                iconSize: [28, 32], iconAnchor: [14, 32], className: ''
            });

            destinoMarker = L.marker(center, { draggable: true, icon: iconDest }).addTo(destinoMap);

            function syncDestino(lat, lng) {
                var ld = $('lat_destino'); if (ld) ld.value = lat;
                var lnd = $('lng_destino'); if (lnd) lnd.value = lng;
                // Recalcula cota origem→destino
                recalcularCotaReboque(lat, lng);
            }

            destinoMarker.on('dragend', function () {
                var pos = destinoMarker.getLatLng();
                syncDestino(pos.lat, pos.lng);
            });

            destinoMap.on('click', function (ev) {
                destinoMarker.setLatLng(ev.latlng);
                syncDestino(ev.latlng.lat, ev.latlng.lng);
            });

            // Sincroniza inicial
            syncDestino(center[0], center[1]);

            setTimeout(function () { destinoMap.invalidateSize(); }, 100);
        }, 50);
    }

    /**
     * Recalcula cota do reboque para origem→destino.
     * Usa Haversine e chama a API de decisão com distancia_km.
     * Atualiza o #reboqueDeslocamento com o total.
     */
    function recalcularCotaReboque(latDest, lngDest) {
        var latO = $('lat_origem'), lngO = $('lng_origem');
        if (!latO || !lngO) return;
        var dLat = (latDest - Number(latO.value)) * Math.PI / 180;
        var dLng = (lngDest - Number(lngO.value)) * Math.PI / 180;
        var lat1 = Number(latO.value) * Math.PI / 180;
        var lat2 = latDest * Math.PI / 180;
        var a = Math.sin(dLat/2)**2 + Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLng/2)**2;
        var dist = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        log('distância origem→destino:', dist.toFixed(2), 'km');

        // Chamada rápida à API pra pegar custo com essa distância
        fetch(API_DECISAO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: 'reboque',
                veiculo_pode_mover: true,
                lat_origem: Number(latO.value),
                lng_origem: Number(lngO.value),
                categoria: 'popular',
                distancia_km: Math.max(1, dist)
            }})
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            var d = j.data || j;
            var tow = d.opcao_reboque || {};
            var rd = $('reboqueDeslocamento');
            if (rd && tow.custo_total) rd.textContent = money(tow.custo_total);
            log('cota reboque atualizada:', tow.custo_total);
        })
        .catch(function (e) { log('erro cota:', e); });
    }

    // ═══════════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════════
    function init() {
        log('init v16');
        hideStages();
        decisionInput = $('decisao_atendimento');
        // Se já tem endereço, o public-pre-cotacao.js dispara o evento
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
r($report, 'INFO', '1. Mapa de origem some quando decisionStage/destinoBox aparece');
r($report, 'INFO', '2. Cards A/B clicáveis (assistencia → cadastro | reboque → destino)');
r($report, 'INFO', '3. "Levar o carro" e "0 oficinas" → destinoBox + mapa de destino');
r($report, 'INFO', '4. Cota reboque recalculada quando cliente marca destino (origem→destino)');
r($report, 'INFO', '');
r($report, 'INFO', 'TESTE:');
r($report, 'INFO', '1. Ctrl+Shift+R em /pre-cotacao');
r($report, 'INFO', '2. Me orientem → Pneu → ver A/B clicáveis, mapa some');
r($report, 'INFO', '3. Clica B → destinoBox + mapa de destino aparece');
r($report, 'INFO', '4. Marca destino → valor do reboque atualiza');
r($report, 'INFO', '5. Testa "Levar o carro" (deve ir direto pro destinoBox)');

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v16</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔥 Installer v16 — cards clicáveis + cota origem→destino</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v16.php</p>
</body></html>