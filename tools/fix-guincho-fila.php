<?php
/**
 * fix-guincho-fila.php (v3)
 *
 * Aplica o patch B-GUINCHO-FILA no GuinchoController.php
 * - Ancora 3, 4, 5 usam regex tolerante a linhas em branco entre campos
 */

$target = 'C:/xampp/htdocs/guinchafacil/src/Controllers/GuinchoController.php';

if (!file_exists($target)) {
    fwrite(STDERR, "[ERRO] Arquivo nao encontrado: $target\n");
    exit(1);
}

$stamp = date('Ymd-His');
$backup = $target . '.bak-' . $stamp;
copy($target, $backup);
echo "[ok] Backup: $backup\n";

$content = file_get_contents($target);
if ($content === false) {
    fwrite(STDERR, "[ERRO] Falha ao ler arquivo\n");
    exit(2);
}

// --- Ancora 1 ---
$a1 = '            if (!$semLocalizacao && isset($pedido[\'score_minimo_atual\']) && $score < (float)$pedido[\'score_minimo_atual\']) continue;';
$r1 = '            $scoreMinimoAtual = (float)($pedido[\'score_minimo_atual\'] ?? 0.0);' . "\n"
    . '            $abaixoDoScore = !$semLocalizacao && $scoreMinimoAtual > 0.0 && $score < $scoreMinimoAtual;';

if (strpos($content, $a1) === false) {
    fwrite(STDERR, "[ERRO] Ancora 1 nao encontrada\n");
    exit(3);
}
$content = str_replace($a1, $r1, $content);
echo "[ok] Ancora 1 aplicada\n";

// --- Ancora 2 ---
$pattern2 = '/(\$ofertas = \[\];)\s*\n\s*\n(\s*foreach \(\$aguardando as \$pedido\) \{)/';
$repl2 = '$1' . "\n\n"
       . '        $ofertasAbaixoDoScore = [];' . "\n\n"
       . '$2';

$count2 = 0;
$content = preg_replace($pattern2, $repl2, $content, -1, $count2);
if ($count2 === 0) {
    fwrite(STDERR, "[ERRO] Ancora 2 nao encontrada\n");
    exit(4);
}
echo "[ok] Ancora 2 aplicada ($count2 ocorrencia(s))\n";

// --- Ancora 3 (regex tolerante) ---
// Match: "$ofertas[] = [" + qualquer whitespace + "'id'" + whitespace + "=> (int)$pedido['id']"
// Substitui so "$ofertas[] =" por "$ofertaItem =" (mantem o resto)
$pattern3 = '/(\$ofertas\[\] = \[)(\s*\'id\'\s*=>\s*\(int\)\$pedido\[\'id\'\],)/';
$repl3 = '$ofertaItem = [$2';

$count3 = 0;
$content = preg_replace($pattern3, $repl3, $content, -1, $count3);
if ($count3 === 0) {
    fwrite(STDERR, "[ERRO] Ancora 3 nao encontrada\n");
    if (preg_match('/(\$ofertas\[\][\s\S]{0,150}\'id\')/', $content, $m)) {
        fwrite(STDERR, "Trecho: " . substr($m[1], 0, 200) . "\n");
    }
    exit(5);
}
echo "[ok] Ancora 3 aplicada ($count3 ocorrencia(s))\n";

// --- Ancora 4 (regex tolerante) ---
// Match: "'compat_warnings' => $compatWarnings," + whitespace + "];"
$pattern4 = '/\'compat_warnings\'\s*=>\s*\$compatWarnings,\s*\n\s*\];/';
$repl4 = "'compat_warnings'    => \$compatWarnings,\n            ];\n"
       . "            if (\$abaixoDoScore) { \$ofertasAbaixoDoScore[] = \$ofertaItem; } else { \$ofertas[] = \$ofertaItem; }";

$count4 = 0;
$content = preg_replace($pattern4, $repl4, $content, -1, $count4);
if ($count4 === 0) {
    fwrite(STDERR, "[ERRO] Ancora 4 nao encontrada\n");
    exit(6);
}
echo "[ok] Ancora 4 aplicada ($count4 ocorrencia(s))\n";

// --- Ancora 5 (regex tolerante) ---
$pattern5 = '/usort\(\$ofertas, fn\(\$a, \$b\) => \$b\[\'score\'\] <=> \$a\[\'score\'\]\);\s*\n\s*return array_slice\(\$ofertas, 0, \$limite\);/';
$repl5 = "if (empty(\$ofertas) && !empty(\$ofertasAbaixoDoScore)) {\n"
       . "            error_log(sprintf('[GuinchoController::montarOfertasDisponiveis] FALLBACK SCORE: %d pedido(s) abaixo do score minimo (guincho=%d)', count(\$ofertasAbaixoDoScore), (int)\$guincho['id']));\n"
       . "            \$ofertas = \$ofertasAbaixoDoScore;\n"
       . "        }\n"
       . "        usort(\$ofertas, fn(\$a, \$b) => \$b['score'] <=> \$a['score']);\n"
       . "        return array_slice(\$ofertas, 0, \$limite);";

$count5 = 0;
$content = preg_replace($pattern5, $repl5, $content, -1, $count5);
if ($count5 === 0) {
    fwrite(STDERR, "[ERRO] Ancora 5 nao encontrada\n");
    exit(7);
}
echo "[ok] Ancora 5 aplicada ($count5 ocorrencia(s))\n";

// Gravar
if (file_put_contents($target, $content) === false) {
    fwrite(STDERR, "[ERRO] Falha ao gravar arquivo\n");
    copy($backup, $target);
    exit(8);
}
echo "[ok] Patch aplicado\n";

// Validar sintaxe
$lint = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($target) . ' 2>&1');
echo $lint;

if (strpos($lint, 'No syntax errors') === false) {
    fwrite(STDERR, "[ERRO] php -l falhou. Restaurando backup...\n");
    copy($backup, $target);
    exit(9);
}

echo "\n=== PATCH B-GUINCHO-FILA APLICADO COM SUCESSO ===\n";