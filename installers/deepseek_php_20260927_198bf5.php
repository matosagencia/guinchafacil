<?php
// install-fix-v12.php — bloqueio total do form.js
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
@copy($jsPath, $jsPath . '.bak-v12-' . date('Ymd-His'));

$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v12
   Bloqueio TOTAL do form.js. Só a minha chamada passa.
   ============================================================ */
(function () {
    'use strict';
    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }
    function $(id) { return document.getElementById(id); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var API_URL = '/api/pre-cotacao/decisao';
    var SEM_COBERTURA = !!(window.__preCotacaoSemCobertura && window.__preCotacaoSemCobertura.status);
    var WHATSAPP = window.__preCotacaoWhatsApp || '';

    // ═══ 1. INTERCEPTAÇÃO TOTAL DE FETCH ═══
    // Bloqueia TODAS as chamadas à API do form.js.
    // Só deixa passar quando NÓS setamos __triagemPermitirFetch = true.
    var fetchOriginal = window.fetch.bind(window);
    window.fetch = function (url, options) {
        var urlStr = typeof url === 'string' ? url : (url && url.url) || '';
        if (urlStr.indexOf(API_URL) !== -1) {
            if (window.__triagemPermitirFetch !== true) {
                var t = ($('tipo_problema') || {}).value || '?';
                log('BLOQUEANDO fetch do form.js (tipo="' + t + '")');
                return Promise.resolve(new Response(JSON.stringify({
                    ok: true,
                    data: { acao: 'aguardando_sintoma', opcoes_disponiveis: [] },
                    error: null
                }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
            }
        }
        return fetchOriginal(url, options);
    };

    // ═══ 2. TRAVA DE RENDER — esconde o card do form.js ═══
    // Se o form.js por algum motivo renderizar o card B (mesmo com
    // fetch bloqueado, ele pode rodar o render padrão), forçamos
    // esconder o decisionStage até termos NOSSA resposta.
    function esconderDecision() {
        var d = $('decisionStage');
        if (d) d.hidden = true;
        var da = $('decisionAssistencia'); if (da) da.hidden = true;
        var dr = $('decisionReboque'); if (dr) dr.hidden = true;
        var rd = $('reboqueDeslocamento'); if (rd) rd.textContent = 'R$ --';
        var ap = $('assistenciaPrice'); if (ap) ap.textContent = 'R$ --';
        var as = $('assistenciaSaida'); if (as) as.textContent = 'R$ --';
    }

    // ═══ 3. HIDE/SHOW ═══
    function hideAll() {
        ['situacaoStage','sintomaStage','fieldVeiculoPodeMover','vehicleStage','decisionStage'].forEach(function (id) {
            var el = $(id); if (el) el.hidden = true;
        });
        var db = $('destinoBox'); if (db) db.classList.add('d-none');
    }
    function mostrar(id) { hideAll(); var el = $(id); if (el) el.hidden = false; log('mostrar:', id); }

    // ═══ 4. CLIQUES (capture global) ═══
    document.addEventListener('click', function (e) {
        var card = e.target.closest && e.target.closest('[data-choice-group]');
        if (!card) return;
        var g = card.getAttribute('data-choice-group');
        var v = card.getAttribute('data-choice-value');

        if (g === 'tipo_problema') {
            log('clique situação:', v);
            e.stopImmediatePropagation(); e.preventDefault();
            var t = $('tipo_problema'); if (t) t.value = v;
            document.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');

            if (v === 'me_orientem' || v === 'resolver_local') {
                mostrar('sintomaStage');
            } else if (v === 'levar_carro') {
                mostrar('fieldVeiculoPodeMover');
            }
            return;
        }

        if (g === 'veiculo_pode_mover') {
            log('clique veiculo_pode_mover:', v);
            e.stopImmediatePropagation(); e.preventDefault();
            var vm = $('veiculo_pode_mover'); if (vm) vm.value = v === '1' ? '1' : '0';
            document.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
            if (v === '1') mostrar('sintomaStage');
            else aplicarSintoma('reboque');
            return;
        }

        if (g === 'sintoma') {
            log('clique sintoma:', v);
            e.stopImmediatePropagation(); e.preventDefault();
            document.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
            var s = $('sintoma'); if (s) s.value = v;
            aplicarSintoma(MAPA[v] || 'mecanica');
            return;
        }

        if (e.target.closest('#btnSituacaoVoltar')) {
            var sint = $('sintomaStage'), fvm = $('fieldVeiculoPodeMover');
            if ((sint && !sint.hidden) || (fvm && !fvm.hidden)) {
                e.stopImmediatePropagation(); e.preventDefault();
                mostrar('situacaoStage');
            }
        }
    }, true);

    // ═══ 5. APLICAR SINTOMA ═══
    function aplicarSintoma(tipo) {
        if (SEM_COBERTURA) { log('sem cobertura — forçando reboque'); tipo = 'reboque'; }
        log('aplicarSintoma:', tipo);

        var t = $('tipo_problema'); if (t) t.value = tipo;

        // Só aparece o decisionStage quando NÓS mostrarmos
        hideAll();
        esconderDecision();
        var d = $('decisionStage'); if (d) d.hidden = false;

        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Verificando disponibilidade na sua região...';

        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) { log('ERRO: lat/lng vazios'); return; }

        window.__triagemPermitirFetch = true;
        fetchOriginal(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: tipo,
                veiculo_pode_mover: true,
                lat_origem: Number(lat.value),
                lng_origem: Number(lng.value),
                categoria: 'popular',
                distancia_km: 5.0
            }})
        })
        .then(function (r) { return r.json(); })
        .then(function (p) { render(p.data || p); })
        .catch(function (err) { log('ERRO API:', err); mostrarWhatsApp(); })
        .finally(function () { window.__triagemPermitirFetch = false; });
    }

    function money(v) { return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }

    function mostrarWhatsApp() {
        var rec = $('decisionRecommendation');
        if (!rec || !WHATSAPP) return;
        rec.innerHTML = '<div style="padding:12px 0">' +
            '<p style="margin:0 0 8px"><strong>Vamos resolver direto com você.</strong></p>' +
            '<p style="margin:0 0 12px;font-size:.9rem">Fale agora com um atendente no WhatsApp.</p>' +
            '<a href="' + WHATSAPP + '" target="_blank" rel="noopener" ' +
            'style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;' +
            'padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:700">' +
            '<i class="fab fa-whatsapp" style="font-size:1.2rem"></i>Falar com atendente</a>' +
            '</div>';
        var d = $('decisionStage'); if (d) d.hidden = false;
        var da = $('decisionAssistencia'); if (da) da.hidden = true;
        var dr = $('decisionReboque'); if (dr) dr.hidden = true;
    }

    // ═══ 6. RENDER ═══
    function render(data) {
        log('render:', data);

        if (data.acao === 'encaminhar_suporte' || data.acao === 'aguardando_sintoma') {
            mostrarWhatsApp();
            return;
        }

        var ap = $('assistenciaPrice'), as = $('assistenciaSaida');
        var rd = $('reboqueDeslocamento');
        var da = $('decisionAssistencia'), dr = $('decisionReboque');
        var rec = $('decisionRecommendation');
        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};

        var semAssist = !assist.disponivel || data.sem_oficina === true || SEM_COBERTURA;

        if (semAssist) {
            log('sem assistência — overlay + auto-destino');
            hideAll();

            var valor = Number(tow.custo_total || 0);
            var valorTxt = valor > 0 ? 'R$ ' + valor.toFixed(2).replace('.', ',') : 'calculando…';
            var aviso = document.createElement('div');
            aviso.style.cssText = 'position:fixed;inset:0;background:rgba(15,17,21,.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';
            aviso.innerHTML = '<div style="background:#fff;padding:24px;border-radius:16px;max-width:420px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3)">' +
                '<p style="margin:0 0 12px;font-size:1.1rem;font-weight:700;color:#142018">Sem assistência na sua região</p>' +
                '<p style="margin:0 0 16px;color:#607066;font-size:.92rem;line-height:1.5">Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher.</p>' +
                '<p style="margin:0 0 8px;font-size:.85rem;color:#607066">Valor estimado do reboque</p>' +
                '<p style="margin:0;font-size:1.5rem;font-weight:800;color:#d97706">' + valorTxt + '</p>' +
                '<p style="margin:16px 0 0;font-size:.8rem;color:#607066">Abrindo destino…</p></div>';
            document.body.appendChild(aviso);

            setTimeout(function () {
                aviso.remove();
                document.dispatchEvent(new Event('prequote:go-destination'));
                setTimeout(function () {
                    var ds = $('destinationStage'); if (ds) ds.hidden = false;
                    var box = $('destinoBox'); if (box) box.classList.remove('d-none');
                }, 100);
            }, 1500);
            return;
        }

        // Tem assistência → A + B
        if (ap) ap.textContent = money(assist.custo_saida);
        if (as) as.textContent = money(assist.custo_saida);
        if (rd) rd.textContent = money(tow.custo_total);
        if (da) da.hidden = false;
        if (dr) dr.hidden = false;

        if (rec) {
            if (data.recomendacao === 'assistencia') {
                rec.innerHTML = '<strong>Recomendamos resolver no local</strong> — mais rápido e mais barato. ' +
                    'Se o mecânico não conseguir consertar aqui, <strong>ele mesmo reboca seu carro até a oficina</strong> ' +
                    'e o valor da assistência é <strong>abatido do conserto</strong>.';
            } else {
                rec.textContent = 'As duas opções fazem sentido. Você decide como seguir.';
            }
        }
        var d = $('decisionStage'); if (d) d.hidden = false;
        log('render OK — A visível:', da && !da.hidden, '| B visível:', dr && !dr.hidden);
    }

    // ═══ 7. Limpeza periódica do card sujo ═══
    setInterval(esconderDecision, 2000);

    log('init v12 — bloqueio TOTAL | SEM_COBERTURA=' + SEM_COBERTURA);
})();
JSEOF;

@file_put_contents($jsPath, $js);

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix v12</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>Fix v12 — bloqueio total do form.js</h1>
<pre>[OK] sintomas.js v12 salvo (<?= filesize($jsPath) ?> bytes)

── O QUE MUDOU ──
• form.js NÃO pode mais chamar a API (mesmo quando tipo é válido)
• Só a nossa chamada passa (flag __triagemPermitirFetch)
• Limpeza periódica do card sujo a cada 2s
• Overlay de "sem cobertura" com valor correto

── TESTE ──
1. Ctrl+Shift+R em /pre-cotacao
2. F12 → Console → Clica "Me orientem" → "Pneu"
3. Console deve mostrar:
   [triagem] clique situação: me_orientem
   [triagem] mostrar: sintomaStage
   [triagem] clique sintoma: pneu
   [triagem] aplicarSintoma: pneu
   [triagem] render OK — A visível: true | B visível: true
4. Deve aparecer card A + card B SEM o "R$ 0,00"</pre>
<p class="del">APAGUE: install-fix-v12.php</p>
</body></html>