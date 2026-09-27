<?php
// install-v14.php — desativa form.js + limpa CSS anti-flash + JS v14
// APAGUE DEPOIS DE RODAR

$root = __DIR__;

// ═══════════════════════════════════════════════════════════
// 1. VIEW: remover <script> do form.js + limpar CSS anti-flash
// ═══════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v14-' . date('Ymd-His'));

// 1a. Comentar a tag do form.js
$tagForm = '<script<?php echo function_exists(\'csp_script_nonce_attr\') ? csp_script_nonce_attr() : \'\'; ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-form.js"></script>';
if (strpos($view, $tagForm) !== false) {
    $view = str_replace($tagForm, '<!-- form.js DESATIVADO v14 — sintomas.js faz tudo -->', $view);
    echo "[OK] form.js removido da view\n";
} else {
    // tenta variações comuns
    $patterns = [
        '#<script[^>]*public-pre-cotacao-form\.js[^>]*></script>#',
        '#<script[^>]*public-pre-cotacao-form[^>]*>\s*</script>#',
    ];
    $removido = false;
    foreach ($patterns as $p) {
        if (preg_match($p, $view)) {
            $view = preg_replace($p, '<!-- form.js DESATIVADO v14 -->', $view, 1);
            $removido = true;
            echo "[OK] form.js removido via regex\n";
            break;
        }
    }
    if (!$removido) {
        echo "[AVISO] Tag do form.js não encontrada (pode já ter sido removida)\n";
    }
}

// 1b. Remover CSS anti-flash que estava brigando
$cssAntiFlash = '#<style>\s*/\* Anti-flash.*?</style>#s';
if (preg_match($cssAntiFlash, $view)) {
    $view = preg_replace($cssAntiFlash, '', $view, 1);
    echo "[OK] CSS anti-flash removido\n";
} else {
    // Tentar string exata
    $cssStr = '<style>
/* Anti-flash: esconde decisionStage e destinoBox até o JS liberar */
#decisionStage, #destinoBox, #destinationStage { display: none !important; }
body.prequote-decisao-visivel #decisionStage { display: block !important; }
body.prequote-destino-visivel #destinoBox,
body.prequote-destino-visivel #destinationStage { display: block !important; }
</style>';
    if (strpos($view, $cssStr) !== false) {
        $view = str_replace($cssStr, '', $view);
        echo "[OK] CSS anti-flash removido (string match)\n";
    } else {
        echo "[SKIP] CSS anti-flash não encontrado\n";
    }
}

@file_put_contents($viewPath, $view);
echo "[SAVE] pre-cotacao.php atualizado\n";

// Valida sintaxe
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1');
echo "[LINT] " . trim((string)$lint) . "\n";

// ═══════════════════════════════════════════════════════════
// 2. JS v14 — sem disparar 'change', sem depender do form.js
// ═══════════════════════════════════════════════════════════
$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v14
   - Não carrega form.js (removido da view)
   - Não dispara 'change' (evita loops)
   - Fluxo completo: endereço → situação → sintoma → confirma → decisão
   ============================================================ */
(function () {
    'use strict';
    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }
    function $(id) { return document.getElementById(id); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var API_DECISAO  = '/api/pre-cotacao/decisao';
    var API_OFICINAS = '/api/pre-cotacao/oficinas-proximas';
    var WHATSAPP = window.__preCotacaoWhatsApp || '';
    var SEM_COBERTURA = !!(window.__preCotacaoSemCobertura && window.__preCotacaoSemCobertura.status);

    // ─── Estado ───
    var tipoEscolhido = '';
    var destinoMap = null;
    var destinoMarker = null;

    // ─── Esconde tudo, mostra só um ───
    function hideAll() {
        ['situacaoStage','sintomaStage','fieldVeiculoPodeMover','vehicleStage',
         'decisionStage','confirmarEnderecoStage','destinoBox'].forEach(function (id) {
            var el = $(id); if (el) el.hidden = true;
        });
    }
    function mostrar(id) {
        hideAll();
        var el = $(id); if (el) el.hidden = false;
        log('mostrar:', id);
    }

    // ─── Anti-form.js: bloqueia 'change' em tipo_problema ───
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'tipo_problema') {
            e.stopImmediatePropagation();
        }
    }, true);

    // ─── Clique em cards (capture) ───
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
            if (v === 'me_orientem' || v === 'resolver_local') mostrar('sintomaStage');
            else if (v === 'levar_carro') mostrar('fieldVeiculoPodeMover');
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
            else { tipoEscolhido = 'reboque'; buscarOficinas(); }
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
            tipoEscolhido = MAPA[v] || 'mecanica';
            // Mostra confirmação de endereço
            var end = $('enderecoConfirmar');
            if (end) {
                var inputEnd = $('inputOrigem') || $('endereco_origem');
                end.textContent = (inputEnd && inputEnd.value) ? inputEnd.value : 'endereço informado';
            }
            mostrar('confirmarEnderecoStage');
            return;
        }

        if (e.target.closest('#btnEnderecoNao')) {
            e.stopImmediatePropagation(); e.preventDefault();
            mostrar('situacaoStage');
            return;
        }

        if (e.target.closest('#btnEnderecoSim')) {
            e.stopImmediatePropagation(); e.preventDefault();
            buscarOficinas();
            return;
        }

        if (e.target.closest('#btnSituacaoVoltar')) {
            e.stopImmediatePropagation(); e.preventDefault();
            var conf = $('confirmarEnderecoStage');
            if (conf && !conf.hidden) { mostrar('sintomaStage'); return; }
            var fvm = $('fieldVeiculoPodeMover');
            if (fvm && !fvm.hidden) { mostrar('situacaoStage'); return; }
            mostrar('situacaoStage');
            return;
        }
    }, true);

    // ─── Busca oficinas ───
    function buscarOficinas() {
        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) {
            log('ERRO: lat/lng vazios');
            mostrar('situacaoStage');
            return;
        }

        // Mostra loading dentro do confirmar
        var busca = $('buscandoOficinas');
        if (busca) busca.hidden = false;
        var res = $('resultadoBusca');
        if (res) { res.hidden = true; res.innerHTML = ''; }

        var url = API_OFICINAS + '?lat=' + encodeURIComponent(lat.value) + '&lng=' + encodeURIComponent(lng.value) + '&tipo=' + encodeURIComponent(tipoEscolhido);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (busca) busca.hidden = true;
                var oficinas = (j.data && j.data.oficinas) || j.oficinas || [];
                log('oficinas encontradas:', oficinas.length);

                if (oficinas.length > 0) {
                    // Tem oficinas online → aplica sintoma (mostra A+B)
                    aplicarSintoma(tipoEscolhido);
                } else {
                    // Sem oficinas online → aviso + auto-reboque
                    if (res) {
                        res.hidden = false;
                        res.innerHTML = '<div class="alert alert-warning mb-0" style="font-size:.95rem">' +
                            '<strong>Sem oficinas online na sua região.</strong><br>' +
                            '<span style="color:#607066;font-size:.88rem">Mas a gente resolve: um guincho leva seu carro até a oficina que você escolher.</span></div>';
                    }
                    setTimeout(function () { tipoEscolhido = 'reboque'; aplicarSintoma('reboque'); }, 1400);
                }
            })
            .catch(function (err) {
                log('ERRO busca:', err);
                if (busca) busca.hidden = true;
                aplicarSintoma(tipoEscolhido);
            });
    }

    // ─── Aplica sintoma e chama API de decisão ───
    function aplicarSintoma(tipo) {
        if (SEM_COBERTURA) tipo = 'reboque';
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

    // ─── Render ───
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
            hideAll();
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
                mostrar('destinoBox');
                ensureDestinoMap();
            }, 1500);
            return;
        }

        // A + B
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
        log('render OK — A:', da && !da.hidden, '| B:', dr && !dr.hidden);
    }

    // ─── Mapa do destino ───
    function ensureDestinoMap() {
        if (destinoMap || !window.L) return;
        var box = $('destinoBox');
        if (!box) return;
        var wrap = document.createElement('div');
        wrap.id = 'destinoMapWrap';
        wrap.style.cssText = 'height:220px;border-radius:10px;margin-top:10px';
        box.appendChild(wrap);
        destinoMap = L.map(wrap).setView([-22.9068, -43.1729], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(destinoMap);
        setTimeout(function () { destinoMap.invalidateSize(); }, 50);
    }

    // ─── Eventos que o public-pre-cotacao.js dispara ───
    document.addEventListener('prequote:location-confirmed', function () {
        log('endereço confirmado — mostrando situação');
        mostrar('situacaoStage');
    });

    log('init v14 — fluxo completo, sem form.js');
})();
JSEOF;

$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
@copy($jsPath, $jsPath . '.bak-v14-' . date('Ymd-His'));
@file_put_contents($jsPath, $js);
echo "[OK] sintomas.js v14 (" . filesize($jsPath) . " bytes)\n";

// ═══════════════════════════════════════════════════════════
// 3. Diagnóstico
// ═══════════════════════════════════════════════════════════
echo "\n── DIAGNÓSTICO ──\n";
try {
    require_once $root . '/config.php';
    $t = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1 AND disponivel=1")->fetchColumn();
    echo "[OK] oficinas online: $t\n";
} catch (Throwable $e) { echo "[AVISO] " . $e->getMessage() . "\n"; }

echo "\n── PRÓXIMO ──\n";
echo "1. Reiniciar Apache pelo painel XAMPP (Stop → Start)\n";
echo "2. Ctrl+Shift+R em /pre-cotacao\n";
echo "3. Testar: Me orientem → Pneu → Confirmar → A+B\n";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v14</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>Installer v14 — desativa form.js</h1>
<p>Se a página acima não aparecer, olha o terminal. Copia a saída abaixo:</p>
<pre>Rode o script pelo terminal:
  C:\xampp\php\php.exe install-v14.php

Ou pelo navegador:
  http://localhost:8080/install-v14.php</pre>
<p class="del">APAGUE: install-v14.php</p>
</body></html>