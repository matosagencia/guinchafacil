<?php
// install-fix-v5.php — v5 com delegacia no document + trava de estado
// APAGUE DEPOIS DE RODAR

$root = __DIR__;

$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v5
   Estratégia definitiva:
   - Delegacia de eventos no DOCUMENT com capture=true (roda ANTES de todos)
   - Trava de estado: re-aplica sintomaStage visível várias vezes
   ============================================================ */
(function () {
    'use strict';

    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var TRAVA_SINT = false;

    function $(id) { return document.getElementById(id); }

    function hideAll() {
        ['situacaoStage','sintomaStage','fieldVeiculoPodeMover','vehicleStage','decisionStage'].forEach(function (id) {
            var el = $(id); if (el) el.hidden = true;
        });
        var db = $('destinoBox'); if (db) db.classList.add('d-none');
    }

    function mostrarSituacao() {
        hideAll();
        var el = $('situacaoStage'); if (el) el.hidden = false;
        log('mostrarSituacao');
    }

    function mostrarSintoma() {
        hideAll();
        var el = $('sintomaStage'); if (el) el.hidden = false;
        log('mostrarSintoma — hidden agora:', el.hidden);
    }

    function mostrarVeiculoMover() {
        hideAll();
        var el = $('fieldVeiculoPodeMover'); if (el) el.hidden = false;
        log('mostrarVeiculoMover');
    }

    function mostrarDecision() {
        hideAll();
        var el = $('decisionStage'); if (el) el.hidden = false;
        log('mostrarDecision — hidden agora:', el.hidden);
    }

    // ── Trava: re-aplica estado por 2s depois de aplicar ──
    function travarSintoma() {
        TRAVA_SINT = true;
        [10, 50, 100, 200, 500, 1000].forEach(function (delay) {
            setTimeout(function () {
                if (TRAVA_SINT) {
                    var el = $('sintomaStage');
                    if (el && el.hidden) {
                        el.hidden = false;
                        log('trava: re-mostrou sintomaStage');
                    }
                }
            }, delay);
        });
        setTimeout(function () { TRAVA_SINT = false; }, 2000);
    }

    function travarDecision() {
        TRAVA_SINT = false;
        [10, 50, 100, 200, 500, 1000].forEach(function (delay) {
            setTimeout(function () {
                var el = $('decisionStage');
                if (el && el.hidden) {
                    el.hidden = false;
                    log('trava: re-mostrou decisionStage');
                }
            }, delay);
        });
    }

    // ══════════════════════════════════════════════════════
    // DELEGACIA DE EVENTOS (roda ANTES de qualquer outro handler)
    // ══════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        var card = e.target.closest('[data-choice-group]');
        if (!card) return;

        var grupo = card.getAttribute('data-choice-group');
        var valor = card.getAttribute('data-choice-value');

        if (grupo === 'tipo_problema') {
            log('CAPTURE clique situação:', valor);
            e.stopImmediatePropagation();
            e.preventDefault();

            var tipo = $('tipo_problema');
            if (tipo) tipo.value = valor;

            // marca seleção
            document.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');

            if (valor === 'me_orientem' || valor === 'resolver_local') {
                mostrarSintoma();
                travarSintoma();
            } else if (valor === 'levar_carro') {
                mostrarVeiculoMover();
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
                mostrarSintoma();
                travarSintoma();
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

            var tipo2 = MAPA[valor] || 'mecanica';
            aplicarSintoma(tipo2);
            return;
        }
    }, true); // ← capture = roda ANTES de tudo

    // ══════════════════════════════════════════════════════
    function aplicarSintoma(tipo) {
        log('aplicarSintoma:', tipo);
        var tipoInput = $('tipo_problema');
        if (tipoInput) tipoInput.value = tipo;

        mostrarDecision();
        travarDecision();

        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Comparando as opções para você...';

        chamarApi(tipo);
    }

    function chamarApi(tipo) {
        var lat = $('lat_origem');
        var lng = $('lng_origem');
        var url = '/api/pre-cotacao/decisao';

        if (!lat || !lng || !lat.value || !lng.value) {
            log('ERRO: lat/lng vazio');
            return;
        }

        log('chamando API:', url);

        fetch(url, {
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
        .then(function (payload) { log('API resposta:', payload); render(payload.data || payload); })
        .catch(function (err) { log('ERRO API:', err); });
    }

    function money(v) {
        return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function render(data) {
        log('render:', data);

        var ap = $('assistenciaPrice'), as = $('assistenciaSaida');
        var rd = $('reboqueDeslocamento');
        var da = $('decisionAssistencia'), dr = $('decisionReboque');
        var rec = $('decisionRecommendation');

        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};

        if (data.acao === 'encaminhar_suporte') {
            if (rec) rec.textContent = data.justificativa || 'Suporte.';
            if (da) da.hidden = true;
            if (dr) dr.hidden = true;
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

        mostrarDecision();
        travarDecision();
        log('render OK — A visível:', da && !da.hidden, '| B visível:', dr && !dr.hidden);
    }

    // Botão voltar
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#btnSituacaoVoltar');
        if (!btn) return;
        var sint = $('sintomaStage'), fvm = $('fieldVeiculoPodeMover');
        if ((sint && !sint.hidden) || (fvm && !fvm.hidden)) {
            e.stopImmediatePropagation(); e.preventDefault();
            mostrarSituacao();
        }
    }, true);

    log('init v5 — delegacia instalada');
})();
JSEOF;

$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
if (file_exists($jsPath)) @copy($jsPath, $jsPath . '.bak-v5-' . date('Ymd-His'));
$bytes = @file_put_contents($jsPath, $js);

$report = [];
$report[] = ($bytes === false) ? "[ERRO] Falha" : "[OK] sintomas.js v5 ($bytes bytes)";
$report[] = "";
$report[] = "── TESTE ──";
$report[] = "1. Ctrl+Shift+R em /pre-cotacao";
$report[] = "2. F12 → Console";
$report[] = "3. Clica 'Me orientem' → deve logar [triagem] CAPTURE clique situação: me_orientem";
$report[] = "4. Clica 'Pneu' → deve logar CAPTURE clique sintoma + API + render";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix v5</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:8px}</style>
</head><body>
<h1>Fix v5 — Delegacia de eventos</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-v5.php</p>
</body></html>