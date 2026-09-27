<?php
// install-fix-v6.php — bloqueia chamadas automáticas do form.js
// APAGUE DEPOIS DE RODAR

$root = __DIR__;

$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v6
   Estratégia: interceptar fetch pra bloquear chamadas
   automáticas do form.js que colocam a tela em "suporte".
   ============================================================ */
(function () {
    'use strict';

    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var API_URL = '/api/pre-cotacao/decisao';
    var TIPOS_VALIDOS = ['pneu','eletrica','bateria','mecanica','chaveiro','reboque'];

    function $(id) { return document.getElementById(id); }
    function tipoValor() {
        var t = $('tipo_problema');
        return t ? (t.value || '').toLowerCase().trim() : '';
    }

    // ═══════════════════════════════════════════════════════
    // 1. INTERCEPTAR FETCH (antes de qualquer outra coisa)
    //    Bloqueia chamadas do form.js quando tipo ainda é
    //    vazio/outro/me_orientem — evita a tela "suporte".
    // ═══════════════════════════════════════════════════════
    var fetchOriginal = window.fetch.bind(window);
    window.fetch = function (url, options) {
        var urlStr = typeof url === 'string' ? url : (url && url.url) || '';
        if (urlStr.indexOf(API_URL) !== -1) {
            var tipo = tipoValor();
            var isNossaChamada = window.__triagemPermitirFetch === true;
            var isTipoValido = TIPOS_VALIDOS.indexOf(tipo) !== -1;

            if (!isNossaChamada && !isTipoValido) {
                log('BLOQUEANDO fetch do form.js — tipo inválido:', tipo);
                return Promise.resolve(new Response(JSON.stringify({
                    ok: true, data: { acao: 'aguardando_sintoma', opcoes_disponiveis: [] }, error: null
                }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
            }
        }
        return fetchOriginal(url, options);
    };

    // ═══════════════════════════════════════════════════════
    // 2. ESTADO — hide/show
    // ═══════════════════════════════════════════════════════
    function hideAll() {
        ['situacaoStage','sintomaStage','fieldVeiculoPodeMover','vehicleStage','decisionStage'].forEach(function (id) {
            var el = $(id); if (el) el.hidden = true;
        });
        var db = $('destinoBox'); if (db) db.classList.add('d-none');
    }
    function mostrar(id) {
        hideAll();
        var el = $(id); if (el) el.hidden = false;
        log('mostrar:', id, '| hidden:', el ? el.hidden : 'n/a');
    }

    // Re-aplica o estado por 1s após escolha (evita outros handlers esconderem)
    var travasAtivas = {};
    function travar(id, duracao) {
        travasAtivas[id] = true;
        var dur = duracao || 1500;
        [16, 50, 150, 400, 800, 1200].forEach(function (d) {
            if (d > dur) return;
            setTimeout(function () {
                if (!travasAtivas[id]) return;
                var el = $(id);
                if (el && el.hidden) {
                    el.hidden = false;
                    log('trava: reabriu', id);
                }
            }, d);
        });
        setTimeout(function () { travasAtivas[id] = false; }, dur);
    }

    // ═══════════════════════════════════════════════════════
    // 3. DELEGACIA DE CLIQUES (capture global)
    // ═══════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        var card = e.target.closest && e.target.closest('[data-choice-group]');
        if (!card) return;

        var grupo = card.getAttribute('data-choice-group');
        var valor = card.getAttribute('data-choice-value');

        if (grupo === 'tipo_problema') {
            log('CAPTURE clique situação:', valor);
            e.stopImmediatePropagation();
            e.preventDefault();

            var t = $('tipo_problema');
            if (t) t.value = valor;

            document.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');

            if (valor === 'me_orientem' || valor === 'resolver_local') {
                mostrar('sintomaStage');
                travar('sintomaStage');
            } else if (valor === 'levar_carro') {
                mostrar('fieldVeiculoPodeMover');
                travar('fieldVeiculoPodeMover');
            }
            return;
        }

        if (grupo === 'veiculo_pode_mover') {
            log('CAPTURE clique veiculo pode mover:', valor);
            e.stopImmediatePropagation();
            e.preventDefault();

            var vm = $('veiculo_pode_mover');
            if (vm) vm.value = valor === '1' ? '1' : '0';

            document.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');

            if (valor === '1') {
                mostrar('sintomaStage');
                travar('sintomaStage');
            } else {
                aplicarSintoma('reboque');
            }
            return;
        }

        if (grupo === 'sintoma') {
            log('CAPTURE clique sintoma:', valor);
            e.stopImmediatePropagation();
            e.preventDefault();

            document.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');

            var sInput = $('sintoma');
            if (sInput) sInput.value = valor;

            aplicarSintoma(MAPA[valor] || 'mecanica');
            return;
        }

        // Botão voltar
        if (e.target.closest('#btnSituacaoVoltar')) {
            var sint = $('sintomaStage'), fvm = $('fieldVeiculoPodeMover');
            if ((sint && !sint.hidden) || (fvm && !fvm.hidden)) {
                e.stopImmediatePropagation(); e.preventDefault();
                mostrar('situacaoStage');
            }
        }
    }, true);

    // ═══════════════════════════════════════════════════════
    // 4. FLUXO DE DECISÃO
    // ═══════════════════════════════════════════════════════
    function aplicarSintoma(tipo) {
        log('aplicarSintoma:', tipo);

        var t = $('tipo_problema');
        if (t) t.value = tipo;

        mostrar('decisionStage');
        travar('decisionStage', 2500);

        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Comparando as opções para você...';

        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) {
            log('ERRO: lat/lng vazios');
            return;
        }

        log('chamando API com tipo:', tipo);
        window.__triagemPermitirFetch = true;

        fetchOriginal(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: tipo,
                veiculo_pode_mover: true,
                lat_origem: Number(lat.value),
                lng_origem: Number(lng.value),
                categoria: 'popular'
            }})
        })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
            log('API resposta recebida');
            render(payload.data || payload);
        })
        .catch(function (err) { log('ERRO API:', err); })
        .finally(function () { window.__triagemPermitirFetch = false; });
    }

    function money(v) {
        return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function render(data) {
        var ap = $('assistenciaPrice'), as = $('assistenciaSaida');
        var rd = $('reboqueDeslocamento');
        var da = $('decisionAssistencia'), dr = $('decisionReboque');
        var rec = $('decisionRecommendation');

        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};

        if (data.acao === 'encaminhar_suporte' || data.acao === 'aguardando_sintoma') {
            log('ação ignorada:', data.acao);
            return;
        }

        if (ap) ap.textContent = money(assist.custo_saida);
        if (as) as.textContent = money(assist.custo_saida);
        if (rd) rd.textContent = money(tow.custo_total);

        if (da) da.hidden = !assist.disponivel;
        if (dr) dr.hidden = false;

        if (rec) {
            if (!assist.disponivel) {
                rec.textContent = data.justificativa || 'Reboque é o caminho mais rápido.';
            } else if (data.recomendacao === 'assistencia') {
                rec.textContent = 'Recomendamos resolver no local — mais rápido e mais barato';
            } else {
                rec.textContent = 'As duas opções fazem sentido. Você decide como seguir.';
            }
        }

        mostrar('decisionStage');
        travar('decisionStage', 2500);
        log('render OK — A visível:', da && !da.hidden, '| B visível:', dr && !dr.hidden);
    }

    // ═══════════════════════════════════════════════════════
    // 5. BOOTSTRAP
    // ═══════════════════════════════════════════════════════
    // Se o form.js já escondeu tudo e mostrou "suporte" antes
    // do nosso init, forçamos o estado inicial correto:
    function resetInicial() {
        var situacao = $('situacaoStage');
        var sintoma  = $('sintomaStage');
        var decision = $('decisionStage');

        // Se a decision está visível com "suporte", esconde e volta pra situação
        var rec = $('decisionRecommendation');
        if (decision && !decision.hidden && rec && rec.textContent.indexOf('suporte') !== -1) {
            log('estado inicial errado detectado — resetando');
            mostrar('situacaoStage');
        }
    }

    log('init v6 — fetch interceptado');
    setTimeout(resetInicial, 300);
    setTimeout(resetInicial, 1000);

})();
JSEOF;

$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
if (file_exists($jsPath)) @copy($jsPath, $jsPath . '.bak-v6-' . date('Ymd-His'));
$bytes = @file_put_contents($jsPath, $js);

$report = [];
$report[] = ($bytes === false) ? "[ERRO]" : "[OK] sintomas.js v6 ($bytes bytes)";
$report[] = "";
$report[] = "── O que mudou ──";
$report[] = "• Intercepta window.fetch pra bloquear chamadas do form.js";
$report[] = "• Bloqueia só quando tipo_problema é vazio/outro/me_orientem";
$report[] = "• Deixa passar quando tipo é pneu/eletrica/bateria/mecanica/chaveiro/reboque";
$report[] = "• Reset inicial: se tela abrir em 'suporte', volta pra situação";
$report[] = "• Trava: re-mostra decisionStage por 2.5s após render";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix v6</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:8px}</style>
</head><body>
<h1>Fix v6 — Interceptar fetch</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-v6.php</p>
</body></html>