<?php
// install-v19.php — esconde originMapPanel + centraliza botões dos cards
// APAGUE DEPOIS DE RODAR

$root = __DIR__;

$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v19-' . date('Ymd-His'));

// Remove CSS v18 se existir
$view = preg_replace('#<style>\s*/\* v18:.*?</style>#s', '', $view);

$css = <<<'CSS'

<style>
/* v19: controle de telas por body class */
#btnSituacaoAvancar { display: none !important; }
#btnCotacao { display: none !important; }

/* Esconde o bloco de origem + mapa em decisão e destino */
body.stage-decisao  #originMapPanel,
body.stage-destino  #originMapPanel,
body.stage-sintoma  #originMapPanel,
body.stage-decisao  .origin-map-composition,
body.stage-destino  .origin-map-composition,
body.stage-sintoma  .origin-map-composition { display: none !important; }

/* Mostra botão Ver cotação só no estágio destino */
body.stage-destino #btnCotacao { display: block !important; }

/* Esconde Voltar em address */
body.stage-address #btnSituacaoVoltar { display: none !important; }

/* ─── Cards A/B ─── */
.decision-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 767px) { .decision-grid { grid-template-columns: 1fr; } }
.decision-card {
    display: flex; flex-direction: column; align-items: stretch;
    padding: 20px; border: 2px solid #d4e6d8; border-radius: 16px;
    background: #fff; text-align: left; cursor: pointer;
    transition: transform .15s, border-color .15s, box-shadow .15s;
    width: 100%; box-sizing: border-box;
}
.decision-card:hover { transform: translateY(-2px); border-color: #2fb34a; box-shadow: 0 6px 20px rgba(47,179,74,.15); }
.decision-card.is-recommended { border-color: #2fb34a; box-shadow: 0 0 0 2px rgba(47,179,74,.15); }
.decision-card.is-selected { border-color: #2fb34a; background: #edf8ef; }
.decision-card svg { width: 48px; height: 48px; margin-bottom: 8px; }
.decision-card strong { font-size: 1.15rem; color: #142018; margin-bottom: 4px; display: block; }
.decision-card .decision-desc { display: block; font-size: .88rem; color: #405247; line-height: 1.5; margin: 6px 0; }
.decision-card .decision-benefit { display: block; font-size: .84rem; color: #248f3a; font-weight: 600; margin-top: 8px; }
.decision-card .decision-price { font-size: 1.6rem; font-weight: 800; color: #2fb34a; margin: 4px 0; display: block; }

/* Botão dos cards — centraliza o texto, ocupa largura toda, não captura clique */
.decision-card .btn-main {
    display: block;
    width: 100%;
    margin-top: 14px;
    padding: 12px 16px;
    text-align: center !important;
    line-height: 1.2;
    pointer-events: none;   /* o clique vai pro card inteiro */
    box-sizing: border-box;
}
.decision-card.is-recommended .btn-main { background: #2fb34a; }
</style>
CSS;

$view = str_replace('</head>', $css . "\n</head>", $view);
@file_put_contents($viewPath, $view);

$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1');

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v19</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>Installer v19 — esconde mapa + centraliza botões</h1>
<pre>[OK] CSS v19 aplicado
[LINT] <?= htmlspecialchars(trim($lint)) ?>

── TESTE ──
1. Ctrl+Shift+R em /pre-cotacao
2. Confirma endereço → situação
3. Me orientem → Pneu → cards A/B
4. VERIFICAR:
   - originMapPanel NÃO aparece embaixo dos cards
   - Texto dos botões "Quero resolver no local" / "Quero rebocar" centralizado
5. Clica B → destinoBox + mapa</pre>
<p class="del">APAGUE: install-v19.php</p>
</body></html>