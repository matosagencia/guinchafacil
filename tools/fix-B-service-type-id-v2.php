<?php
/**
 * fix-B-service-type-id-v2.php
 *
 * Corrige o fallback de service_type_id na posicao correta do metodo
 * (apos $pdo = getPDO()).
 */

$root = 'C:/xampp/htdocs/guinchafacil';
$stamp = date('Ymd-His');

$ctrlFile = $root . '/src/Controllers/AdminController.php';

if (!file_exists($ctrlFile)) {
    fwrite(STDERR, "[ERRO] Controller nao encontrado: $ctrlFile\n");
    exit(1);
}

$ctrlBackup = $ctrlFile . '.bak-stidv2-' . $stamp;
copy($ctrlFile, $ctrlBackup);
echo "[ok] Backup controller: $ctrlBackup\n";

$ctrlContent = file_get_contents($ctrlFile);

if (strpos($ctrlContent, '[B-STID-01]') !== false) {
    echo "[info] fallback B-STID-01 ja existe - nada a fazer\n";
    exit(0);
}

// ---------------------------------------------------------------------
// Ancora: o trecho exato do arquivo real (linhas 1529-1536)
// ---------------------------------------------------------------------
$anchor = "        \$serviceTypeId = null;\n"
        . "        \$serviceTypeIdPost = (int)(\$_POST['service_type_id'] ?? 0);\n"
        . "        if (\$serviceTypeIdPost > 0) {\n"
        . "            \$tipoServico = ServiceType::buscarPorId(\$serviceTypeIdPost);\n"
        . "            if (\$tipoServico && !empty(\$tipoServico['active'])) {\n"
        . "                \$serviceTypeId = (int)\$tipoServico['id'];\n"
        . "            }\n"
        . "        }\n";

if (strpos($ctrlContent, $anchor) === false) {
    fwrite(STDERR, "[ERRO] Ancora serviceTypeId nao encontrada.\n");
    fwrite(STDERR, "Procurando variacao...\n");

    // Fallback: procura por apenas a linha do if
    $altAnchor = "        if (\$serviceTypeIdPost > 0) {\n"
               . "            \$tipoServico = ServiceType::buscarPorId(\$serviceTypeIdPost);\n";

    if (strpos($ctrlContent, $altAnchor) === false) {
        fwrite(STDERR, "[ERRO] Variacao tambem nao encontrada. Abortando.\n");
        copy($ctrlBackup, $ctrlFile);
        exit(2);
    }

    $anchor = $altAnchor;
    $endOfBlock = "        }\n";
    $posAnchor = strpos($ctrlContent, $anchor);
    $posEnd = strpos($ctrlContent, $endOfBlock, $posAnchor + strlen($anchor));
    if ($posEnd === false) {
        fwrite(STDERR, "[ERRO] Nao achei o fim do bloco.\n");
        copy($ctrlBackup, $ctrlFile);
        exit(3);
    }
    $insertPos = $posEnd + strlen($endOfBlock);
} else {
    $insertPos = strpos($ctrlContent, $anchor) + strlen($anchor);
}

// ---------------------------------------------------------------------
// Agora procura o $pdo = getPDO() APOS o bloco do serviceTypeId
// ---------------------------------------------------------------------
$afterAnchor = substr($ctrlContent, $insertPos, 2000);
$pdoAnchor = "        \$pdo = getPDO();\n";
$posPdo = strpos($afterAnchor, $pdoAnchor);

if ($posPdo === false) {
    fwrite(STDERR, "[ERRO] Nao achei 'pdo = getPDO();' depois do serviceTypeId.\n");
    fwrite(STDERR, "Trecho procurado:\n");
    fwrite(STDERR, substr($afterAnchor, 0, 500) . "\n");
    copy($ctrlBackup, $ctrlFile);
    exit(4);
}

// Insere o fallback DEPOIS do $pdo = getPDO();
$insertPosFinal = $insertPos + $posPdo + strlen($pdoAnchor);

$fallback = "\n"
    . "        // [B-STID-01] Fallback quando o POST nao trouxe service_type_id\n"
    . "        // (o JS public-pre-cotacao-flow.js ainda nao preenche o hidden).\n"
    . "        // Sem isso, o PedidoCoreService assume TOWING e exige destino.\n"
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

$ctrlContent = substr($ctrlContent, 0, $insertPosFinal)
             . $fallback
             . substr($ctrlContent, $insertPosFinal);

file_put_contents($ctrlFile, $ctrlContent);
echo "[ok] fallback B-STID-01 inserido depois do \$pdo = getPDO()\n";

// Validar sintaxe
$lintCtrl = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($ctrlFile) . ' 2>&1');
echo $lintCtrl;
if (strpos($lintCtrl, 'No syntax errors') === false) {
    copy($ctrlBackup, $ctrlFile);
    fwrite(STDERR, "[ERRO] php -l falhou. Restaurado.\n");
    exit(5);
}

echo "\n=== PATCH B-STID-V2 APLICADO COM SUCESSO ===\n";