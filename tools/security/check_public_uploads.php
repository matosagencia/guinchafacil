<?php

/**
 * tools/security/check_public_uploads.php
 *
 * Pacote L1.1 — verificação de que public/uploads (e demais diretórios de
 * upload legados) estão protegidos contra execução de script e listagem
 * de diretório antes de liberar um release.
 *
 * Uso:
 *   php tools/security/check_public_uploads.php
 *
 * Exit code:
 *   0 = todos os diretórios de upload protegidos
 *   1 = algum diretório sem proteção adequada
 */

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

$root = dirname(__DIR__, 2);

$uploadDirs = [
    $root . '/public/uploads',
    $root . '/uploads',
];

echo "===============================================\n";
echo " GuinchaFácil — Verificação de uploads públicos\n";
echo "===============================================\n\n";

$hasCritical = false;

foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    echo "Verificando: {$dir}\n";

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        $hasCritical = true;
        echo "  [CRÍTICO] .htaccess ausente — execução de PHP e listagem de diretório não estão bloqueadas.\n";
        continue;
    }

    $content = (string) file_get_contents($htaccess);

    $blocksEngine = str_contains($content, 'php_flag engine off')
        || str_contains($content, 'SetHandler none')
        || str_contains($content, 'RemoveHandler');
    $blocksExecution = preg_match('/FilesMatch.*php/i', $content) === 1
        || str_contains($content, 'Require all denied')
        || str_contains($content, 'Deny from all');
    $blocksIndexing = str_contains($content, '-Indexes');

    if (!$blocksEngine && !$blocksExecution) {
        $hasCritical = true;
        echo "  [CRÍTICO] .htaccess existe mas não bloqueia execução de scripts PHP.\n";
    } else {
        echo "  [OK] Execução de scripts bloqueada.\n";
    }

    if (!$blocksIndexing) {
        echo "  [AVISO] .htaccess não desativa listagem de diretório (Options -Indexes).\n";
    } else {
        echo "  [OK] Listagem de diretório desativada.\n";
    }

    echo "\n";
}

// Verifica se existem arquivos .php dentro das pastas de upload (indício de
// upload malicioso já aceito no passado, ou de teste esquecido).
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $suspicious = glob($dir . '/**/*.php') ?: [];
    $suspicious = array_merge($suspicious, glob($dir . '/*.php') ?: []);
    if (!empty($suspicious)) {
        $hasCritical = true;
        echo "[CRÍTICO] Arquivos .php encontrados dentro de diretório de upload:\n";
        foreach (array_unique($suspicious) as $file) {
            echo "  - {$file}\n";
        }
        echo "\n";
    }
}

// Pacote L1.5 — evidências de pedido não devem mais existir dentro do webroot.
$evidenciasResiduais = glob($root . '/public/uploads/evidencias/*') ?: [];
$evidenciasResiduais = array_filter($evidenciasResiduais, 'is_file');
if (!empty($evidenciasResiduais)) {
    $hasCritical = true;
    echo "[CRÍTICO] " . count($evidenciasResiduais) . " arquivo(s) de evidência ainda em public/uploads/evidencias (webroot).\n";
    echo "  - Rode: php tools/security/migrate_evidence_storage.php\n\n";
}

if ($hasCritical) {
    echo "RESULTADO: FALHOU — corrija a proteção de uploads antes de liberar o release.\n";
    exit(1);
}

echo "RESULTADO: OK — diretórios de upload protegidos.\n";
exit(0);
