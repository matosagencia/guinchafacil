<?php
/**
 * fix-B-service-type-id-v4.php
 *
 * Ancora no fim do [B-GUARD-03] (guard que ja existe) e insere o
 * fallback logo depois.
 */

$root = 'C:/xampp/htdocs/guinchafacil';
$stamp = date('Ymd-His');

$ctrlFile = $root . '/src/Controllers/AdminController.php';

if (!file_exists($ctrlFile)) {
    fwrite(STDERR, "[ERRO] Controller nao encontrado: $ctrlFile\n");
    exit(1);
}

$ctrlBackup = $ctrlFile . '.bak-stidv4-' . $stamp;
copy($ctrlFile, $ctrlBackup);
echo "[ok] Backup controller: $ctrlBackup\n";

$ctrlContent = file_get_contents($ctrlFile);

if (strpos($ctrlContent, '[B-STID-01]') !== false) {
    echo "[info] fallback B-STID-01 ja existe - nada a fazer\n";
    exit(0);
}

$ehCrlf = (strpos($ctrlContent, "\r\n") !== false);
$normalized = str_replace("\r\n", "\n", $ctrlContent);

// ---------------------------------------------------------------------
// Ancora: fim do [B-GUARD-03] — trecho unico no arquivo
// ---------------------------------------------------------------------
$anchor = "            \$this->redirect('/admin/pedido/novo/funil');\n"
        . "            return;\n"
        . "        }\n"
        . "\n"
        . "        // -------------------------------------------------------------\n"
        . "        // [B-GUARD-03] Reboque sem destino -> rejeita";

// Nao achei — tenta uma variacao mais curta
if (strpos($normalized, $anchor) === false) {
    $anchor = "[B-GUARD-03]";
    $posGuard3 = strpos($normalized, $anchor);
    if ($posGuard3 === false) {
        fwrite(STDERR, "[ERRO] [B-GUARD-03] nao encontrado no controller.\n");
        copy($ctrlBackup, $ctrlFile);
        exit(2);
    }
    // Encontra o proximo "\n        }\n" apos o guard-03
    $apos = strpos($normalized, "\n        }\n", $posGuard3);
    if ($apos === false) {
        fwrite(STDERR, "[ERRO] Nao achei fechamento do [B-GUARD-03].\n");
        copy($ctrlBackup, $ctrlFile);
        exit(3);
    }
    $insertPos = $apos + strlen("\n        }\n");
} else {
    $insertPos = strpos($normalized, $anchor) + strlen($anchor);
    // Avanca ate o proximo "}\n\n"
    $fim = strpos($normalized, "        }\n", $insertPos);
    if ($fim === false) {
        fwrite(STDERR, "[ERRO] Nao achei o fim do bloco [B-GUARD-03].\n");
        copy($ctrlBackup, $ctrlFile);
        exit(4);
    }
    $insertPos = $fim + strlen("        }\n");
}

// ---------------------------------------------------------------------
// Fallback
// ---------------------------------------------------------------------
$fallback = "\n"
    . "        // [B-STID-01] Fallback quando o POST nao trouxe service_type_id\n"
    . "        // (o JS public-pre-cotacao-flow.js ainda nao preenche o hidden).\n"
    . "        // Sem isso, o PedidoCoreService assume TOWING e exige destino.\n"
    . "        if (\$serviceTypeId === null) {\n"
    . "            \$tipoParaBusca = trim((string)(\$_POST['tipo_problema'] ?? ''));\n"
    . "            if (\$tipoParaBusca !== '' && \$tipoParaBusca !== 'outro') {\n"
    . "                try {\n"
    . "                    \$pdoSvc = getPDO();\n"
    . "                    \$stmtSvc = \$pdoSvc->prepare('SELECT id FROM service_types WHERE active = 1 AND (code = ? OR slug = ? OR LOWER(name) = LOWER(?)) LIMIT 1');\n"
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

$novo = substr($normalized, 0, $insertPos)
      . $fallback
      . substr($normalized, $insertPos);

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
    exit(5);
}

echo "\n=== PATCH B-STID-V4 APLICADO COM SUCESSO ===\n";