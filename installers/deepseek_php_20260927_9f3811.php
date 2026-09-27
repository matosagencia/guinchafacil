<?php
// install-v27.php — corrige fluxo 1a:
//   1. #btnCotacao visível na tela de comparativa (stage-decisao)
//   2. tipo_problema default = me_orientem (remove "Outro problema")
//   3. Observer que aplica body.stage-* automaticamente quando stages mudam
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════════
// 1. VIEW — 2 fixes
// ═══════════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v27-' . date('Ymd-His'));

// 1a. #btnCotacao visível em stage-decisao E stage-destino
if (strpos($view, 'body.stage-decisao #btnCotacao') === false) {
    $old = 'body.stage-destino #btnCotacao { display: block !important; }';
    $new = 'body.stage-destino #btnCotacao, body.stage-decisao #btnCotacao { display: block !important; }';
    $view = str_replace($old, $new, $view);
    $report[] = "[OK] CSS: #btnCotacao visível em stage-decisao + stage-destino";
} else {
    $report[] = "[SKIP] CSS já tem stage-decisao";
}

// 1b. tipo_problema default me_orientem (remove "Outro problema")
if (strpos($view, 'name="tipo_problema" value="outro"') !== false) {
    $view = str_replace(
        'name="tipo_problema" value="outro"',
        'name="tipo_problema" value="me_orientem"',
        $view
    );
    $report[] = "[OK] View: tipo_problema default = me_orientem";
} else {
    $report[] = "[INFO] View: tipo_problema já não usa 'outro'";
}

@file_put_contents($viewPath, $view);
$report[] = "[LINT] view: " . trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1'));

// ═══════════════════════════════════════════════════════════════════
// 2. JS sintomas.js — observer que aplica body.stage-* corretamente
//    + handler que força decisão quando card de sintoma/veículo é clicado
// ═══════════════════════════════════════════════════════════════════
$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
$js = file_get_contents($jsPath);
@copy($jsPath, $jsPath . '.bak-v27-' . date('Ymd-His'));

$fixJs = <<<'JSFIX'

/* ═══════════════════════════════════════════════════════════════════
   v27: sincroniza body.stage-* com o stage realmente visível
   + força avanço para decisionStage quando um sintoma é escolhido
   ═══════════════════════════════════════════════════════════════════ */
(function () {
    var MAPA = {
        'confirmarEnderecoStage': 'endereco',
        'situacaoStage': 'situacao',
        'sintomaStage': 'sintoma',
        'vehicleStage': 'veiculo',
        'decisionStage': 'decisao',
        'destinoBox': 'destino'
    };

    function aplicarBodyClass() {
        var ativo = null;
        Object.keys(MAPA).forEach(function (id) {
            var el = document.getElementById(id);
            if (el && !el.hidden) ativo = id;
        });
        Object.keys(MAPA).forEach(function (id) {
            document.body.classList.remove('stage-' + MAPA[id]);
        });
        if (ativo) {
            document.body.classList.add('stage-' + MAPA[ativo]);
            console.log('[v27] stage ativo:', ativo, '→ body.stage-' + MAPA[ativo]);
        }
    }

    // Observa mudanças em hidden/class dos stages
    document.addEventListener('DOMContentLoaded', function () {
        var obs = new MutationObserver(aplicarBodyClass);
        Object.keys(MAPA).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) obs.observe(el, { attributes: true, attributeFilter: ['hidden', 'style', 'class'] });
        });
        aplicarBodyClass();

        // Fallback: clique em card de sintoma → força decisionStage
        document.addEventListener('click', function (ev) {
            var card = ev.target.closest && ev.target.closest('[data-choice-group="sintoma"], [data-choice-group="tipo_veiculo"], [data-choice-group="veiculo"]');
            if (!card) return;
            // Espera o handler original executar
            setTimeout(function () {
                var decision = document.getElementById('decisionStage');
                if (decision && decision.hidden) {
                    // Se ainda está escondido, força manualmente
                    Object.keys(MAPA).forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) el.hidden = true;
                    });
                    decision.hidden = false;
                    aplicarBodyClass();
                    console.log('[v27] decisionStage forçado após clique em card');
                }
            }, 800);
        });
    });
})();
JSFIX;

if (strpos($js, '[v27]') === false) {
    $js .= "\n\n" . $fixJs;
    @file_put_contents($jsPath, $js);
    $report[] = "[OK] sintomas.js: observer + fallback de clique (v27)";
} else {
    $report[] = "[SKIP] sintomas.js: v27 já presente";
}

// ═══════════════════════════════════════════════════════════════════
// 3. Verificação final
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── VERIFICAÇÃO ──";
$viewFinal = file_get_contents($viewPath);
if (strpos($viewFinal, 'body.stage-decisao #btnCotacao') !== false) {
    $report[] = "✅ #btnCotacao aparece em stage-decisao";
}
if (strpos($viewFinal, 'name="tipo_problema" value="me_orientem"') !== false) {
    $report[] = "✅ tipo_problema default = me_orientem";
}

$jsFinal = file_get_contents($jsPath);
if (strpos($jsFinal, '[v27]') !== false) {
    $report[] = "✅ sintomas.js: observer v27 instalado";
}

$report[] = "";
$report[] = "── TESTE ──";
$report[] = "1. Reinicia Apache (painel XAMPP: Stop → Start)";
$report[] = "2. Abre /pre-cotacao em anônimo (Ctrl+Shift+N) — sem cache";
$report[] = "3. F12 → Console. Deve aparecer [v27] stage ativo: ... em cada mudança";
$report[] = "4. Testa: endereço → Me orientem → escolhe tipo veículo → comparativa aparece com #btnCotacao";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v27</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔧 Installer v27 — Fluxo 1a</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v27.php</p>
</body></html>