<?php
// install-fix-nonce.php — adiciona nonce ao script do sintomas.js
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$report = [];

if (!file_exists($viewPath)) die('ERRO: pre-cotacao.php nao encontrado');
$view = file_get_contents($viewPath);

// Backup
$bak = $viewPath . '.bak-nonce-' . date('Ymd-His');
@copy($viewPath, $bak);
$report[] = "[OK] Backup: " . basename($bak);

// Tag atual (sem nonce)
$tagErrada = '<script src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-sintomas.js"></script>';

// Tag correta (com nonce)
$tagCerta = '<script<?php echo function_exists("csp_script_nonce_attr") ? csp_script_nonce_attr() : ""; ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-sintomas.js"></script>';

$count = 0;
if (strpos($view, $tagErrada) !== false) {
    $view = str_replace($tagErrada, $tagCerta, $view);
    $count = substr_count($view, 'csp_script_nonce_attr') - substr_count($view, 'csp_script_nonce_attr', 0, strpos($view, 'public-pre-cotacao-sintomas.js'));
    $report[] = "[OK] Tag do sintomas.js corrigida com nonce";
} else {
    $report[] = "[AVISO] Tag exata não encontrada — tentando regex";

    // Fallback: regex para capturar a tag de qualquer forma
    $pattern = '#<script(\s+(?!.*nonce)[^>]*)?\s+src="([^"]*public-pre-cotacao-sintomas\.js)"[^>]*></script>#';
    if (preg_match($pattern, $view)) {
        $view = preg_replace_callback($pattern, function ($m) {
            return '<script<?php echo function_exists("csp_script_nonce_attr") ? csp_script_nonce_attr() : ""; ?> src="' . $m[2] . '"></script>';
        }, $view);
        $report[] = "[OK] Tag corrigida via regex";
    } else {
        $report[] = "[ERRO] Não foi possível corrigir";
    }
}

// Verifica se TODAS as tags <script src=...> têm nonce
if (preg_match_all('#<script(\s[^>]*)?\s+src=#', $view, $matches)) {
    $semNonce = 0;
    foreach ($matches[0] as $tag) {
        if (strpos($tag, 'nonce') === false && strpos($tag, 'csp_script_nonce_attr') === false) {
            $semNonce++;
            $report[] = "[AVISO] Script sem nonce: " . substr($tag, 0, 100);
        }
    }
    $report[] = $semNonce === 0 ? "[OK] Todos os <script src> têm nonce" : "[AVISO] $semNonce scripts sem nonce";
}

// Salva
if (file_put_contents($viewPath, $view) === false) {
    $report[] = "[ERRO] Falha ao salvar";
} else {
    $report[] = "[SAVE] pre-cotacao.php atualizado (" . strlen($view) . " bytes)";
}

// Valida sintaxe
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1');
$report[] = "[LINT] " . trim((string)$lint);

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix Nonce</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:8px}</style>
</head><body>
<h1>Fix — Nonce do sintomas.js</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-nonce.php</p>
</body></html>