<?php
declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


/**
 * tools/security/migrate_evidence_storage.php
 *
 * Pacote L1.5 — migração pontual: move fotos de evidência que ainda estejam
 * em public/uploads/evidencias (webroot, acessível por URL pública) para
 * storage/private/evidencias (fora do webroot, só acessível via rota
 * autorizada /evidencia/{id}).
 *
 * Não apaga a pasta antiga nem os arquivos nela após mover — se algo não
 * bater, os originais continuam em public/uploads/evidencias até você
 * confirmar e limpar manualmente.
 *
 * Uso: php tools/security/migrate_evidence_storage.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/Services/Evidence/EvidenceService.php';

$oldDir = rtrim((string)UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'evidencias';
$newDir = EvidenceService::privateStorageDir();

echo "===============================================\n";
echo " GuinchaFácil — Migração de storage de evidências\n";
echo "===============================================\n\n";
echo "Origem: {$oldDir}\n";
echo "Destino: {$newDir}\n\n";

if (!is_dir($oldDir)) {
    echo "[OK] Pasta antiga não existe — nada a migrar (fluxo já estava limpo).\n";
    exit(0);
}

if (!is_dir($newDir)) {
    if (!@mkdir($newDir, 0770, true)) {
        echo "[ERRO] Não foi possível criar o diretório de destino: {$newDir}\n";
        exit(1);
    }
}

$files = glob($oldDir . DIRECTORY_SEPARATOR . '*') ?: [];
$moved = 0;
$failed = 0;
$skipped = 0;

foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }
    $basename = basename($file);
    $destino = $newDir . DIRECTORY_SEPARATOR . $basename;

    if (is_file($destino)) {
        echo "[SKIP] Já existe no destino: {$basename}\n";
        $skipped++;
        continue;
    }

    if (rename($file, $destino)) {
        echo "[OK]   Movido: {$basename}\n";
        $moved++;
    } else {
        echo "[ERRO] Falha ao mover: {$basename}\n";
        $failed++;
    }
}

echo "\n";
echo "Movidos: {$moved} | Já existentes: {$skipped} | Falhas: {$failed}\n";

if ($failed > 0) {
    echo "\n[AVISO] Alguns arquivos não puderam ser movidos. Verifique permissões e rode novamente.\n";
    exit(1);
}

if ($moved > 0) {
    echo "\n[AVISO] Os arquivos originais NÃO foram removidos de {$oldDir}.\n";
    echo "        Após confirmar que /evidencia/{id} serve corretamente os arquivos movidos,\n";
    echo "        você pode apagar manualmente o que sobrou em public/uploads/evidencias.\n";
}

echo "\nRESULTADO: OK\n";
exit(0);
