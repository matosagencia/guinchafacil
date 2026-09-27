<?php
// install-fix-js.php — JS autossuficiente para triagem
// APAGUE DEPOIS DE RODAR

$root = __DIR__;

$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v3 AUTOSSUFICIENTE
   Não depende do public-pre-cotacao-form.js.
   Intercepta TODOS os cliques (capture) e controla tudo direto.
   ============================================================ */
(function () {
    'use strict';

    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }

    function init() {
        var tipoInput         = document.getElementById('tipo_problema');
        var situacaoStage     = document.getElementById('situacaoStage');
        var sintomaStage      = document.getElementById('sintomaStage');
        var fieldVeiculoMover = document.getElementById('fieldVeiculoPodeMover');
        var veiculoPodeMover  = document.getElementById('veiculo_pode_mover');
        var decisionStage     = document.getElementById('decisionStage');
        var vehicleStage      = document.getElementById('vehicleStage');
        var destinoBox        = document.getElementById('destinoBox');
        var btnAvancar        = document.getElementById('btnSituacaoAvancar');

        log('init', {
            tipo: !!tipoInput, situacao: !!situacaoStage, sintoma: !!sintomaStage,
            veiculo: !!fieldVeiculoMover, decision: !!decisionStage, btnAvancar: !!btnAvancar
        });

        if (!tipoInput || !situacaoStage || !sintomaStage) {
            log('ERRO: elementos faltando — abortando');
            return;
        }

        var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };

        function hideAll() {
            situacaoStage.hidden = true;
            sintomaStage.hidden = true;
            if (fieldVeiculoMover) fieldVeiculoMover.hidden = true;
            if (vehicleStage) vehicleStage.hidden = true;
            if (destinoBox) destinoBox.classList.add('d-none');
        }

        function mostrarSituacao() {
            hideAll();
            situacaoStage.hidden = false;
            log('mostrarSituacao');
        }

        function mostrarSintoma() {
            hideAll();
            sintomaStage.hidden = false;
            log('mostrarSintoma');
        }

        function mostrarVeiculoMover() {
            hideAll();
            if (fieldVeiculoMover) fieldVeiculoMover.hidden = false;
            log('mostrarVeiculoMover');
        }

        // ─── 1. Intercepta clique em card de situação ──────────────
        situacaoStage.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.stopPropagation();
                e.preventDefault();

                var s = card.getAttribute('data-choice-value');
                log('clicou situação:', s);

                situacaoStage.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                tipoInput.value = s;

                if (s === 'me_orientem' || s === 'resolver_local') {
                    mostrarSintoma();
                } else if (s === 'levar_carro') {
                    mostrarVeiculoMover();
                }
            }, true); // capture: roda antes de qualquer outro handler
        });

        // ─── 2. Veículo pode se mover? ──────────────────────────────
        if (fieldVeiculoMover) {
            fieldVeiculoMover.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    var pode = card.getAttribute('data-choice-value') === '1';
                    log('clicou veiculo pode mover:', pode);
                    if (veiculoPodeMover) veiculoPodeMover.value = pode ? '1' : '0';
                    fieldVeiculoMover.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (c) {
                        c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                    });
                    card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                    if (pode) mostrarSintoma();
                    else aplicarSintoma('reboque');
                }, true);
            });
        }

        // ─── 3. Clique em sintoma ───────────────────────────────────
        sintomaStage.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (card) {
            card.addEventListener('click', function (e) {
                e.stopPropagation();
                e.preventDefault();
                var s = card.getAttribute('data-choice-value') || '';
                var tipo = MAPA[s] || 'mecanica';
                log('clicou sintoma:', s, '→ tipo:', tipo);
                sintomaStage.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                var sInput = document.getElementById('sintoma');
                if (sInput) sInput.value = s;
                aplicarSintoma(tipo);
            }, true);
        });

        // ─── 4. Aplicar sintoma e disparar decisão ─────────────────
        function aplicarSintoma(tipo) {
            tipoInput.value = tipo;
            tipoInput.dispatchEvent(new Event('change', { bubbles: true }));
            log('aplicarSintoma:', tipo, '— mostrando decisionStage');

            // Mostra o decisionStage diretamente
            hideAll();
            if (decisionStage) {
                decisionStage.hidden = false;
                log('decisionStage visível');
            }

            // Chama API direto
            chamarApiDecisao(tipo);
        }

        // ─── 5. API + render ────────────────────────────────────────
        function chamarApiDecisao(tipo) {
            var lat = document.getElementById('lat_origem');
            var lng = document.getElementById('lng_origem');
            var url = decisionStage && decisionStage.dataset.decisionUrl
                ? decisionStage.dataset.decisionUrl
                : '/guinchafacil/api/pre-cotacao/decisao';

            if (!lat || !lng || !lat.value || !lng.value) {
                log('ERRO: lat/lng vazio — não chama API');
                return;
            }

            log('chamando API:', url, { tipo: tipo, lat: lat.value, lng: lng.value });

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    pedido_draft: {
                        tipo_problema: tipo,
                        veiculo_pode_mover: (veiculoPodeMover && veiculoPodeMover.value === '1') ? true : true,
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
                log('ERRO na API:', err);
            });
        }

        function money(v) {
            return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        function render(data) {
            var assistenciaPrice   = document.getElementById('assistenciaPrice');
            var assistenciaSaida   = document.getElementById('assistenciaSaida');
            var reboqueDeslocamento = document.getElementById('reboqueDeslocamento');
            var decisionAssistencia = document.getElementById('decisionAssistencia');
            var decisionReboque     = document.getElementById('decisionReboque');
            var recommendation      = document.getElementById('decisionRecommendation');

            var assist = data.opcao_assistencia || {};
            var tow = data.opcao_reboque || {};

            log('render', { acao: data.acao, assist_disponivel: assist.disponivel, tow_custo: tow.custo_total });

            if (data.acao === 'encaminhar_suporte') {
                if (recommendation) recommendation.textContent = data.justificativa || 'Vamos te orientar pelo suporte.';
                if (decisionAssistencia) decisionAssistencia.hidden = true;
                if (decisionReboque) decisionReboque.hidden = true;
                return;
            }

            if (assistenciaPrice) assistenciaPrice.textContent = money(assist.custo_saida);
            if (assistenciaSaida) assistenciaSaida.textContent = money(assist.custo_saida);
            if (reboqueDeslocamento) reboqueDeslocamento.textContent = money(tow.custo_total);

            if (decisionAssistencia) decisionAssistencia.hidden = !assist.disponivel;
            if (decisionReboque) decisionReboque.hidden = false;

            if (recommendation) {
                if (!assist.disponivel) {
                    recommendation.textContent = data.justificativa || 'Reboque e o caminho mais rápido.';
                } else if (data.recomendacao === 'assistencia') {
                    recommendation.textContent = 'Recomendamos resolver no local — mais rápido e mais barato';
                } else {
                    recommendation.textContent = 'As duas opções fazem sentido. Você decide como seguir.';
                }
            }
        }

        // ─── 6. Voltar contextual ───────────────────────────────────
        var btnVoltar = document.getElementById('btnSituacaoVoltar');
        if (btnVoltar) {
            btnVoltar.addEventListener('click', function (e) {
                if (!sintomaStage.hidden || (fieldVeiculoMover && !fieldVeiculoMover.hidden)) {
                    e.stopImmediatePropagation(); e.preventDefault();
                    mostrarSituacao();
                }
            }, true);
        }

        // Estado inicial: só situação visível
        log('estado inicial: situacaoStage visível');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JSEOF;

// Backup + salvar
$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
if (file_exists($jsPath)) @copy($jsPath, $jsPath . '.bak-v3-' . date('Ymd-His'));
$bytes = @file_put_contents($jsPath, $js);

$report = [];
$report[] = ($bytes === false) ? "[ERRO] Falha ao salvar JS" : "[OK] JS autossuficiente salvo ($bytes bytes)";

// Confirma que o Service está correto
$servicePath = $root . '/src/Services/DecisaoAtendimentoService.php';
$svc = file_get_contents($servicePath);
if (preg_match("/TIPOS_SUPORTE\s*=\s*\[([^\]]+)\]/", $svc, $m)) {
    $report[] = "[CHECK] TIPOS_SUPORTE: [" . trim($m[1]) . "]";
    if (strpos($m[1], "'me_orientem'") !== false) {
        $report[] = "[AVISO] 'me_orientem' AINDA está em TIPOS_SUPORTE — rode o install-fix-fluxo de novo!";
    } else {
        $report[] = "[OK] Service sem 'me_orientem'";
    }
}

// Confirma que o public-pre-cotacao.js tem listeners nos cards
$otherJs = @file_get_contents($root . '/public/assets/js/public-pre-cotacao.js');
if ($otherJs) {
    $temSintoma = strpos($otherJs, 'tipo_problema') !== false;
    $temSituacao = strpos($otherJs, 'situacaoStage') !== false;
    $report[] = "[CHECK] public-pre-cotacao.js: menciona tipo_problema=" . ($temSintoma?'sim':'não') . ", situacaoStage=" . ($temSituacao?'sim':'não');
}

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix JS</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:8px}</style>
</head><body>
<h1>Fix — JS autossuficiente</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-js.php</p>
</body></html>