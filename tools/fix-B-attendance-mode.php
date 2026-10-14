<?php
/**
 * fix-B-attendance-mode.php
 *
 * Aplica SO a alteracao de attendance_mode no Pedido.php.
 * Roda DEPOIS do merge da Faixa A.
 */

$file = 'C:/xampp/htdocs/guinchafacil/src/Models/Pedido.php';

if (!file_exists($file)) {
    fwrite(STDERR, "[ERRO] Arquivo nao encontrado: $file\n");
    exit(1);
}

$stamp  = date('Ymd-His');
$backup = $file . '.bak-attendance-' . $stamp;
copy($file, $backup);
echo "[ok] Backup: $backup\n";

$c = file_get_contents($file);

// Idempotente: so aplica se ainda NAO tem
if (strpos($c, "attendance_mode IN ('ON_SITE', 'HYBRID')") !== false) {
    echo "[info] attendance_mode ja presente - nada a fazer\n";
    exit(0);
}

// Ancora: o WHERE da fila
$antes = '. "  AND p.expiracao_aceite > {$agora}\n"';
$depois = '. "  AND p.attendance_mode IN (\'ON_SITE\', \'HYBRID\')\n"'
        . "\n                . " . '"  AND p.expiracao_aceite > {$agora}\n"';

if (strpos($c, $antes) === false) {
    fwrite(STDERR, "[ERRO] Ancora do expiracao_aceite nao encontrada\n");
    exit(2);
}

$c = str_replace($antes, $depois, $c);
file_put_contents($file, $c);

$lint = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($file) . ' 2>&1');
echo $lint;

if (strpos($lint, 'No syntax errors') === false) {
    fwrite(STDERR, "[ERRO] php -l falhou. Restaurando backup...\n");
    copy($backup, $file);
    exit(3);
}

echo "\n=== PATCH B-ATTENDANCE-MODE APLICADO ===\n";