<?php
// install-v20.php — mostra preço do reboque dentro do destinoBox
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════
// 1. VIEW — adiciona elemento visível de preço no destinoBox
// ═══════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v20-' . date('Ymd-His'));

// Adiciona preço + CTA antes do botão Ver minha cotação (que fica no rodapé)
$anchor = 'class="col-12 d-none" id="destinoBox"';
$anchor2 = 'class="col-12" id="destinoBox" hidden';

$precoBloco = <<<'HTML'

<div class="col-12 mt-3" id="precoDestinoWrap" hidden>
    <div class="alert alert-info d-flex justify-content-between align-items-center" style="margin-bottom:0">
        <div>
            <span style="font-size:.85rem;color:#607066">Valor do reboque (origem → destino)</span>
            <div style="font-size:1.5rem;font-weight:800;color:#2fb34a" id="precoReboqueDestino">R$ --</div>
        </div>
        <div style="font-size:.8rem;color:#607066;text-align:right">
            Toque no mapa para ajustar<br>
            o ponto de entrega
        </div>
    </div>
</div>
HTML;

// Procura por ambas as variantes
if (strpos($view, $anchor) !== false) {
    $view = str_replace(
        '<div class="col-12 d-none" id="destinoBox">',
        '<div class="col-12 d-none" id="destinoBox">' . $precoBloco,
        $view
    );
    $report[] = "[OK] Preço adicionado após destinoBox (variante d-none)";
} elseif (strpos($view, $anchor2) !== false) {
    $view = str_replace(
        '<div class="col-12" id="destinoBox" hidden>',
        '<div class="col-12" id="destinoBox" hidden>' . $precoBloco,
        $view
    );
    $report[] = "[OK] Preço adicionado após destinoBox (variante hidden)";
} else {
    // Tenta regex mais flexível
    if (preg_match('#<div class="col-12[^"]*" id="destinoBox"[^>]*>#', $view, $m)) {
        $view = str_replace($m[0], $m[0] . $precoBloco, $view);
        $report[] = "[OK] Preço adicionado via regex";
    } else {
        $report[] = "[ERRO] Não achei o destinoBox";
    }
}

@file_put_contents($viewPath, $view);
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1');
$report[] = "[LINT] " . trim((string)$lint);

// ═══════════════════════════════════════════════════════════════
// 2. JS — atualizar também o #precoReboqueDestino quando calcula
// ═══════════════════════════════════════════════════════════════
$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
$js = file_get_contents($jsPath);
@copy($jsPath, $jsPath . '.bak-v20-' . date('Ymd-His'));

$oldCalc = "var rd = \$('reboqueDeslocamento');\n            if (rd && tow.custo_total) rd.textContent = money(tow.custo_total);";
$newCalc = "var rd = \$('reboqueDeslocamento');\n            if (rd && tow.custo_total) rd.textContent = money(tow.custo_total);\n            var prd = \$('precoReboqueDestino');\n            if (prd && tow.custo_total) prd.textContent = money(tow.custo_total);\n            var prW = \$('precoDestinoWrap');\n            if (prW) prW.hidden = false;";

if (strpos($js, 'precoReboqueDestino') !== false) {
    $report[] = "[SKIP] JS já tem precoReboqueDestino";
} elseif (strpos($js, $oldCalc) !== false) {
    $js = str_replace($oldCalc, $newCalc, $js);
    @file_put_contents($jsPath, $js);
    $report[] = "[OK] JS atualiza precoReboqueDestino";
} else {
    // Fallback: injeta depois da primeira ocorrência de getElementById('reboqueDeslocamento')
    $oldSimple = "if (rd && tow.custo_total) rd.textContent = money(tow.custo_total);";
    if (strpos($js, $oldSimple) !== false) {
        $newSimple = $oldSimple . "\n            var prd = \$('precoReboqueDestino');\n            if (prd && tow.custo_total) prd.textContent = money(tow.custo_total);\n            var prW = \$('precoDestinoWrap');\n            if (prW) prW.hidden = false;";
        $js = str_replace($oldSimple, $newSimple, $js);
        @file_put_contents($jsPath, $js);
        $report[] = "[OK] JS atualizado via fallback";
    } else {
        $report[] = "[AVISO] Não achei ponto de injeção no JS — patch manual necessário";
    }
}

$report[] = "";
$report[] = "── TESTE ──";
$report[] = "1. Roda: npx playwright test tests/e2e/fluxo-1a.spec.js --reporter=list --headed";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v20</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>Installer v20 — preço no destinoBox</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v20.php</p>
</body></html>