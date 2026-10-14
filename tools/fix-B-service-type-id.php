<?php
/**
 * fix-B-service-type-id-v3.php
 *
 * Normaliza CRLF -> LF antes de procurar a ancora.
 */

$root = 'C:/xampp/htdocs/guinchafacil';
$stamp = date('Ymd-His');

$ctrlFile = $root . '/src/Controllers/AdminController.php';

if (!file_exists($ctrlFile)) {
    fwrite(STDERR, "[ERRO] Controller nao encontrado: $ctrlFile\n");
    exit(1);
}

$ctrlBackup = $ctrlFile . '.bak-stidv3-' . $stamp;
copy($ctrlFile, $ctrlBackup);
echo "[ok] Backup controller: $ctrlBackup\n";

$ctrlContent = file_get_contents($ctrlFile);

if (strpos($ctrlContent, '[B-STID-01]') !== false) {
    echo "[info] fallback B-STID-01 ja existe - nada a fazer\n";
    exit(0);
}

// Salva line ending original
$ehCrlf = (strpos($ctrlContent, "\r\n") !== false);

// Normaliza para LF
$normalized = str_replace("\r\n", "\n", $ctrlContent);

// ---------------------------------------------------------------------
// Ancora com indentacao real (8 espacos)
// ---------------------------------------------------------------------
$anchor = "        \$serviceTypeId = null;\n"
        . "        \$serviceTypeIdPost = (int)(\$_POST['service_type_id'] ?? 0);\n"
        . "        if (\$serviceTypeIdPost > 0) {\n"
        . "            \$tipoServico = ServiceType::buscarPorId(\$serviceTypeIdPost);\n"
        . "            if (\$tipoServico && !empty(\$tipoServico['active'])) {\n"
        . "                \$serviceTypeId = (int)\$tipoServico['id'];\n"
        . "            }\n"
        . "        }\n";

$posAnchor = strpos($normalized, $anchor);

if ($posAnchor === false) {
    fwrite(STDERR, "[ERRO] Ancora serviceTypeId nao encontrada (mesmo apos normalizar).\n");
    fwrite(STDERR, "Primeiros 500 chars do trecho com serviceTypeId:\n");
    $idx = strpos($normalized, 'serviceTypeIdPost');
    if ($idx !== false) {
        fwrite(STDERR, substr($normalized, max(0, $idx - 200), 700) . "\n");
    } else {
        fwrite(STDERR, "[ERRO] 'serviceTypeIdPost' nem existe no arquivo!\n");
    }
    copy($ctrlBackup, $ctrlFile);
    exit(2);
}

$insertPos = $posAnchor + strlen($anchor);

// ---------------------------------------------------------------------
// Procura $pdo = getPDO(); depois do bloco
// ---------------------------------------------------------------------
$after = substr($normalized, $insertPos, 3000);
$pdoAnchor = "        \$pdo = getPDO();\n";
$posPdo = strpos($after, $pdoAnchor);

if ($posPdo === false) {
    fwrite(STDERR, "[ERRO] Nao achei 'pdo = getPDO();' depois do bloco.\n");
    fwrite(STDERR, "Trecho:\n");
    fwrite(STDERR, substr($after, 0, 800) . "\n");
    copy($ctrlBackup, $ctrlFile);
    exit(3);
}

$insertFinal = $insertPos + $posPdo + strlen($pdoAnchor);

$fallback = "\n"
    . "        // [B-STID-01] Fallback quando o POST nao trouxe service_type_id\n"
    . "        if (\$serviceTypeId === null) {\n"
    . "            \$tipoParaBusca = trim((string)(\$_POST['tipo_problema'] ?? ''));\n"
    . "            if (\$tipoParaBusca !== '' && \$tipoParaBusca !== 'outro') {\n"
    . "                try {\n"
    . "                    \$stmtSvc = \$pdo->prepare('SELECT id FROM service_types WHERE active = 1 AND (code = ? OR slug = ? OR LOWER(name) = LOWER(?)) LIMIT 1');\n"
    . "                    \$stmtSvc->execute([\$tipoParaBusca, \$tipoParaBusca, \$tipoParaBusca]);\n"
    . "                    \$svcFallback = (int)\$stmtSvc->fetchColumn();\n"
    . "                    if (\$svcFallback > 0) {\n"
    . "                        \$serviceTypeId = \$svcFallback;\n"
    . "                        error_log('[B-STID-01] service_type_id resolvido por fallback: ' . \$svcFallback . ' (tipo_problema=' . \$tipoParaBusca . ')');\n"
    . "                    }\n"
    . "                } catch (Throwable \$eSvc) {\n"
    . "                    error_log('[B-STID-01] falha no fallback: ' . \$eSvc->getMessage());\n"
    . "                }\n"
    . "            }\n"
    . "        }\n";

$novo = substr($normalized, 0, $insertFinal)
      . $fallback
      . substr($normalized, $insertFinal);

// Devolve para CRLF se era CRLF
if ($ehCrlf) {
    $novo = str_replace("\n", "\r\n", $novo);
}

file_put_contents($ctrlFile, $novo);
echo "[ok] fallback B-STID-01 inserido\n";

$lintCtrl = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($ctrlFile) . ' 2>&1');
echo $lintCtrl;
if (strpos($lintCtrl, 'No syntax errors') === false) {
    copy($ctrlBackup, $ctrlFile);
    fwrite(STDERR, "[ERRO] php -l falhou. Restaurado.\n");
    exit(4);
}

echo "\n=== PATCH B-STID-V3 APLICADO COM SUCESSO ===\n";