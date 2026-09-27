<?php
// diag-fluxo.php — mapeia o fluxo atual da pré-cotação
// RODA UMA VEZ E APAGA

$root = __DIR__;
$out = [];

// ─── 1. View principal ───
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
if (!is_file($viewPath)) {
    // Tenta outras localizações comuns
    foreach ([
        '/src/Views/public/pre-cotacao.php',
        '/src/views/public/pre-cotacao.php',
        '/views/public/pre-cotacao.php',
    ] as $alt) {
        if (is_file($root . $alt)) { $viewPath = $root . $alt; break; }
    }
}

if (is_file($viewPath)) {
    $view = file_get_contents($viewPath);
    $out[] = "══════════════════════════════════════════════";
    $out[] = "VIEW: " . str_replace($root, '', $viewPath);
    $out[] = "Tamanho: " . strlen($view) . " bytes";
    $out[] = "══════════════════════════════════════════════";
    $out[] = "";

    // Lista todos os IDs de divs/stages
    preg_match_all('/id="([^"]+)"/', $view, $m);
    $ids = array_unique($m[1]);
    $out[] = "── IDs encontrados na view (" . count($ids) . ") ──";
    foreach ($ids as $id) {
        // Filtra só os que parecem importantes
        if (preg_match('/stage|box|card|btn|stage|veiculo|situacao|sintoma|decisao|destino|comparativo|oficina/i', $id)) {
            $out[] = "  #$id";
        }
    }
    $out[] = "";

    // Acha o card "Outro problema"
    $out[] = "── Busca 'Outro problema' ──";
    $linhas = explode("\n", $view);
    foreach ($linhas as $i => $linha) {
        if (stripos($linha, 'outro') !== false && stripos($linha, 'problema') !== false) {
            $ctxStart = max(0, $i - 3);
            $ctxEnd = min(count($linhas) - 1, $i + 3);
            $out[] = "  Linha " . ($i + 1) . ":";
            for ($j = $ctxStart; $j <= $ctxEnd; $j++) {
                $out[] = "    " . ($j + 1) . ": " . rtrim($linhas[$j]);
            }
            $out[] = "  ---";
        }
    }
    $out[] = "";

    // Acha stage do veículo
    $out[] = "── Stage do veículo ──";
    foreach ($linhas as $i => $linha) {
        if (stripos($linha, 'veiculoStage') !== false || (stripos($linha, 'veiculo') !== false && stripos($linha, 'stage') !== false)) {
            $ctxStart = max(0, $i - 2);
            $ctxEnd = min(count($linhas) - 1, $i + 15);
            for ($j = $ctxStart; $j <= $ctxEnd; $j++) {
                $out[] = "  " . ($j + 1) . ": " . rtrim($linhas[$j]);
            }
            $out[] = "  ---";
            break;
        }
    }
    $out[] = "";

    // Acha #btnCotacao
    $out[] = "── Ocorrências de #btnCotacao / btnVerCotacao / Ver minha ──";
    foreach ($linhas as $i => $linha) {
        if (preg_match('/btnCotacao|btnVerCotacao|Ver minha|ver minha/i', $linha)) {
            $out[] = "  L" . ($i + 1) . ": " . trim($linha);
        }
    }
    $out[] = "";
} else {
    $out[] = "[ERRO] Não achei pre-cotacao.php";
}

// ─── 2. JS ───
$out[] = "══════════════════════════════════════════════";
$out[] = "ARQUIVOS JS (candidatos)";
$out[] = "══════════════════════════════════════════════";
$jsCandidatos = glob($root . '/public/assets/js/*pre-cotacao*.js');
$jsCandidatos = array_merge($jsCandidatos, glob($root . '/public/assets/js/*sintomas*.js'));
$jsCandidatos = array_unique($jsCandidatos);

foreach ($jsCandidatos as $jsPath) {
    $js = file_get_contents($jsPath);
    $out[] = "";
    $out[] = "── " . basename($jsPath) . " (" . strlen($js) . " bytes) ──";

    // Funções que mexem em veiculo/situacao/decisao
    preg_match_all('/function\s+(\w+)\s*\(/', $js, $m);
    $funcs = array_unique($m[1]);
    $out[] = "  Funções: " . implode(', ', $funcs);
    $out[] = "";

    // Acha onde mexe em "veiculo"
    $linhasJs = explode("\n", $js);
    $out[] = "  ── Referências a 'veiculo' ──";
    foreach ($linhasJs as $i => $linha) {
        if (stripos($linha, 'veiculo') !== false) {
            $out[] = "    L" . ($i + 1) . ": " . trim($linha);
        }
    }
    $out[] = "";

    // Acha onde mexe em "Outro" / "outro_problema"
    $out[] = "  ── Referências a 'outro' ──";
    foreach ($linhasJs as $i => $linha) {
        if (stripos($linha, 'outro') !== false) {
            $out[] = "    L" . ($i + 1) . ": " . trim($linha);
        }
    }
    $out[] = "";

    // Acha o handler de clique em cards (data-choice-value)
    $out[] = "  ── Handler data-choice (clique em cards) ──";
    foreach ($linhasJs as $i => $linha) {
        if (stripos($linha, 'data-choice') !== false) {
            $out[] = "    L" . ($i + 1) . ": " . trim($linha);
        }
    }
    $out[] = "";

    // Acha a função que mostra o decisionStage
    $out[] = "  ── Referências a decisionStage / mostrar ──";
    foreach ($linhasJs as $i => $linha) {
        if (preg_match('/decisionStage|mostrar\s*\(|showStage/i', $linha)) {
            $out[] = "    L" . ($i + 1) . ": " . trim($linha);
        }
    }
    $out[] = "";
}

// ─── 3. Escreve o relatório ───
$reportPath = $root . '/diag-fluxo-resultado.txt';
file_put_contents($reportPath, implode("\n", $out));

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Diag Fluxo</title>
<style>
body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:12px;max-height:70vh;overflow:auto}
h1{color:#22c55e}
.btn{background:#2fb34a;color:#fff;padding:12px 20px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin:8px 8px 8px 0}
.btn:hover{background:#248f3a}
.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}
</style>
</head><body>
<h1>🔍 Diagnóstico do Fluxo 1a</h1>
<pre><?= htmlspecialchars(implode("\n", $out)) ?></pre>
<p>Relatório também salvo em: <code>diag-fluxo-resultado.txt</code></p>
<p class="del">APAGUE: diag-fluxo.php</p>
</body></html>