<?php
// install-fix-js-v4.php — JS defensivo com logging
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v4 DEFENSIVO
   Bloqueia handlers concorrentes e reafirma estado.
   ============================================================ */
(function () {
    'use strict';

    var DEBUG = window.__debugTriagem !== false;
    function log() {
        if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments)));
    }

    function init() {
        log('init — início');

        var tipoInput         = document.getElementById('tipo_problema');
        var situacaoStage     = document.getElementById('situacaoStage');
        var sintomaStage      = document.getElementById('sintomaStage');
        var fieldVeiculoMover = document.getElementById('fieldVeiculoPodeMover');
        var veiculoPodeMover  = document.getElementById('veiculo_pode_mover');
        var decisionStage     = document.getElementById('decisionStage');
        var vehicleStage      = document.getElementById('vehicleStage');
        var destinoBox        = document.getElementById('destinoBox');

        if (!tipoInput || !situacaoStage || !sintomaStage || !decisionStage) {
            log('ERRO: elementos faltando — abortando');
            return;
        }

        var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };

        // ── Estado explícito ────────────────────────────────────
        function show(el, visible) {
            if (el) el.hidden = !visible;
        }

        function mostrarSituacao() {
            show(sintomaStage, false);
            show(fieldVeiculoMover, false);
            show(vehicleStage, false);
            show(decisionStage, false);
            if (destinoBox) destinoBox.classList.add('d-none');
            show(situacaoStage, true);
            log('mostrarSituacao');
        }

        function mostrarSintoma() {
            show(situacaoStage, false);
            show(sintomaStage, true);
            show(fieldVeiculoMover, false);
            show(vehicleStage, false);
            show(decisionStage, false);
            if (destinoBox) destinoBox.classList.add('d-none');
            log('mostrarSintoma — sintomaStage.hidden agora:', sintomaStage.hidden);
        }

        function mostrarVeiculoMover() {
            show(situacaoStage, false);
            show(sintomaStage, false);
            show(fieldVeiculoMover, true);
            show(vehicleStage, false);
            show(decisionStage, false);
            if (destinoBox) destinoBox.classList.add('d-none');
            log('mostrarVeiculoMover');
        }

        // ── Reafirma o estado depois que outros handlers rodarem ──
        function reafirmar(fn, delay) {
            setTimeout(fn, delay || 0);
        }

        // ══════════════════════════════════════════════════════
        // 1. Cards de situação
        // ══════════════════════════════════════════════════════
        situacaoStage.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.stopImmediatePropagation();
                e.preventDefault();
                var s = card.getAttribute('data-choice-value');
                log('clique situação:', s);

                situacaoStage.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected');
                card.setAttribute('aria-pressed', 'true');
                tipoInput.value = s;

                if (s === 'me_orientem' || s === 'resolver_local') {
                    mostrarSintoma();
                    reafirmar(mostrarSintoma, 50);
                } else if (s === 'levar_carro') {
                    mostrarVeiculoMover();
                    reafirmar(mostrarVeiculoMover, 50);
                }
            }, true);
        });

        // ══════════════════════════════════════════════════════
        // 2. Veículo pode mover?
        // ══════════════════════════════════════════════════════
        if (fieldVeiculoMover) {
            fieldVeiculoMover.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    var pode = card.getAttribute('data-choice-value') === '1';
                    log('veiculo pode mover:', pode);
                    if (veiculoPodeMover) veiculoPodeMover.value = pode ? '1' : '0';
                    fieldVeiculoMover.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (c) {
                        c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                    });
                    card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                    if (pode) {
                        mostrarSintoma();
                        reafirmar(mostrarSintoma, 50);
                    } else {
                        aplicarSintoma('reboque');
                    }
                }, true);
            });
        }

        // ══════════════════════════════════════════════════════
        // 3. Sintomas
        // ══════════════════════════════════════════════════════
        sintomaStage.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.stopImmediatePropagation();
                e.preventDefault();
                var s = card.getAttribute('data-choice-value') || '';
                var tipo = MAPA[s] || 'mecanica';
                log('clique sintoma:', s, '→ tipo:', tipo);

                sintomaStage.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                var sInput = document.getElementById('sintoma');
                if (sInput) sInput.value = s;

                aplicarSintoma(tipo);
            }, true);
        });

        // ══════════════════════════════════════════════════════
        // 4. Aplicar sintoma → chamar API
        // ══════════════════════════════════════════════════════
        function aplicarSintoma(tipo) {
            log('aplicarSintoma:', tipo);
            tipoInput.value = tipo;

            // Mostra decisionStage imediatamente
            show(situacaoStage, false);
            show(sintomaStage, false);
            show(fieldVeiculoMover, false);
            show(vehicleStage, false);
            show(decisionStage, true);

            var rec = document.getElementById('decisionRecommendation');
            if (rec) rec.textContent = 'Comparando as opções para você...';

            // Reafirma o decisionStage visível
            reafirmar(function () {
                show(decisionStage, true);
                log('reafirmou decisionStage visível');
            }, 100);

            chamarApiDecisao(tipo);
        }

        // ══════════════════════════════════════════════════════
        // 5. API
        // ══════════════════════════════════════════════════════
        function chamarApiDecisao(tipo) {
            var lat = document.getElementById('lat_origem');
            var lng = document.getElementById('lng_origem');
            var url = decisionStage.dataset.decisionUrl || '/api/pre-cotacao/decisao';

            if (!lat || !lng || !lat.value || !lng.value) {
                log('ERRO: lat/lng vazio — abortando');
                return;
            }

            log('chamando API:', url);

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    pedido_draft: {
                        tipo_problema: tipo,
                        veiculo_pode_mover: true,
                        lat_origem: Number(lat.value),
                        lng_origem: Number(lng.value),
                        categoria: (document.getElementById('categoria') || {}).value || 'popular'
                    }
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                log('resposta API:', payload);
                render(payload.data || payload);
            })
            .catch(function (err) {
                log('ERRO API:', err);
                var rec = document.getElementById('decisionRecommendation');
                if (rec) rec.textContent = 'Não conseguimos comparar agora. Você pode seguir com reboque.';
            });
        }

        // ══════════════════════════════════════════════════════
        // 6. Render
        // ══════════════════════════════════════════════════════
        function money(v) {
            return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        function render(data) {
            log('render data:', data);

            var assistenciaPrice    = document.getElementById('assistenciaPrice');
            var assistenciaSaida    = document.getElementById('assistenciaSaida');
            var reboqueDeslocamento = document.getElementById('reboqueDeslocamento');
            var decisionAssistencia = document.getElementById('decisionAssistencia');
            var decisionReboque     = document.getElementById('decisionReboque');
            var rec                 = document.getElementById('decisionRecommendation');

            var assist = data.opcao_assistencia || {};
            var tow    = data.opcao_reboque || {};

            if (data.acao === 'encaminhar_suporte') {
                if (rec) rec.textContent = data.justificativa || 'Vamos te orientar pelo suporte.';
                if (decisionAssistencia) decisionAssistencia.hidden = true;
                if (decisionReboque) decisionReboque.hidden = true;
                return;
            }

            if (assistenciaPrice) assistenciaPrice.textContent = money(assist.custo_saida);
            if (assistenciaSaida) assistenciaSaida.textContent = money(assist.custo_saida);
            if (reboqueDeslocamento) reboqueDeslocamento.textContent = money(tow.custo_total);

            if (decisionAssistencia) decisionAssistencia.hidden = !assist.disponivel;
            if (decisionReboque) decisionReboque.hidden = false;

            if (rec) {
                if (!assist.disponivel) {
                    rec.textContent = data.justificativa || 'Reboque é o caminho mais rápido.';
                } else if (data.recomendacao === 'assistencia') {
                    rec.textContent = 'Recomendamos resolver no local — mais rápido e mais barato';
                } else {
                    rec.textContent = 'As duas opções fazem sentido. Você decide como seguir.';
                }
            }

            // Reafirma que decisionStage está visível
            show(decisionStage, true);
            log('render OK — card A hidden:', decisionAssistencia ? decisionAssistencia.hidden : 'n/a',
                '| card B hidden:', decisionReboque ? decisionReboque.hidden : 'n/a');
        }

        // ══════════════════════════════════════════════════════
        // 7. Voltar
        // ══════════════════════════════════════════════════════
        var btnVoltar = document.getElementById('btnSituacaoVoltar');
        if (btnVoltar) {
            btnVoltar.addEventListener('click', function (e) {
                if (!sintomaStage.hidden || (fieldVeiculoMover && !fieldVeiculoMover.hidden)) {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    mostrarSituacao();
                }
            }, true);
        }

        log('init — fim, tudo conectado');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JSEOF;

$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
if (file_exists($jsPath)) @copy($jsPath, $jsPath . '.bak-v4-' . date('Ymd-His'));
$bytes = @file_put_contents($jsPath, $js);

$report = [];
$report[] = ($bytes === false) ? "[ERRO] Falha ao salvar" : "[OK] JS v4 salvo ($bytes bytes)";
$report[] = "";
$report[] = "── INSTRUÇÕES ──";
$report[] = "1. Ctrl+Shift+R em /guinchafacil/pre-cotacao";
$report[] = "2. F12 → Console";
$report[] = "3. Clica 'Me orientem' — deve aparecer log [triagem]";
$report[] = "4. Clica 'Pneu' — deve aparecer [triagem] chamando API + resposta";
$report[] = "5. Cola o log do console aqui";
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix JS v4</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:8px}</style>
</head><body>
<h1>Fix — JS v4 defensivo</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-js-v4.php</p>
</body></html>