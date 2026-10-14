<?php
/**
 * fix-B-guincho-beep-vol.php
 *
 * Sobe o volume do beep do guincho (gfBeep).
 * Antes: gain 0.20, dur 0.15s
 * Depois: gain 0.40, dur 0.18s
 */

$file = 'C:/xampp/htdocs/guinchafacil/src/Views/guincho/dashboard.php';

if (!file_exists($file)) {
    fwrite(STDERR, "[ERRO] Arquivo nao encontrado: $file\n");
    exit(1);
}

$stamp  = date('Ymd-His');
$backup = $file . '.bak-beep-vol-' . $stamp;
copy($file, $backup);
echo "[ok] Backup: $backup\n";

$c = file_get_contents($file);

$antes1 = "g.gain.exponentialRampToValueAtTime(0.20, start + 0.012);";
$depois1 = "g.gain.exponentialRampToValueAtTime(0.40, start + 0.012);";

if (strpos($c, $antes1) === false) {
    fwrite(STDERR, "[ERRO] Ancora 1 (volume) nao encontrada\n");
    copy($backup, $file);
    exit(2);
}
$c = str_replace($antes1, $depois1, $c);
echo "[ok] Volume: 0.20 -> 0.40\n";

$antes2 = "const dur = 0.15;";
$depois2 = "const dur = 0.18;";

if (strpos($c, $antes2) !== false) {
    $c = str_replace($antes2, $depois2, $c);
    echo "[ok] Duracao: 0.15 -> 0.18\n";
} else {
    echo "[aviso] Duracao nao encontrada - pulando\n";
}

file_put_contents($file, $c);

$lint = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($file) . ' 2>&1');
echo $lint;

if (strpos($lint, 'No syntax errors') === false) {
    fwrite(STDERR, "[ERRO] php -l falhou. Restaurando backup...\n");
    copy($backup, $file);
    exit(3);
}

echo "\n=== PATCH B-GUINCHO-BEEP-VOL APLICADO ===\n";