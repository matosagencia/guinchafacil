<?php

/**
 * tools/security/check_secrets.php
 *
 * Pacote L1.1 — verificação de segredos antes de empacotar/liberar um release.
 *
 * Varre a raiz do projeto em busca de artefatos que não deveriam existir
 * (ou não deveriam existir com conteúdo real) num pacote de distribuição:
 *   - .env / .env.backup dentro do webroot
 *   - .env.local dentro do webroot com valores reais (não placeholder)
 *   - ZIPs residuais dentro de files/
 *   - fallback inseguro de dotenv habilitado
 *   - variáveis obrigatórias ausentes/placeholder para o ambiente resolvido
 *
 * Uso:
 *   php tools/security/check_secrets.php
 *
 * Exit code:
 *   0 = nenhum problema crítico
 *   1 = problema crítico encontrado (não deve seguir para release)
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
require_once $root . '/src/Services/Security/ConfigSecurityService.php';

$audit = ConfigSecurityService::auditManagedEnvironment($root, $_ENV);

echo "===============================================\n";
echo " GuinchaFácil — Verificação de segredos (release)\n";
echo "===============================================\n\n";

$hasCritical = false;

if (!empty($audit['critical'])) {
    $hasCritical = true;
    echo "[CRÍTICO] Bloqueadores de release:\n";
    foreach ($audit['critical'] as $item) {
        echo "  - {$item}\n";
    }
    echo "\n";
}

// Verificação adicional: .env.local presente DENTRO do webroot com conteúdo
// que não seja apenas o placeholder de desativação criado pelo Pacote L1.1.
$embeddedPath = $audit['runtime']['embedded_env_local_path'];
if (is_file($embeddedPath)) {
    $content = (string) file_get_contents($embeddedPath);
    $looksDeactivated = str_contains($content, 'ESTE ARQUIVO FOI DESATIVADO');
    if (!$looksDeactivated) {
        $hasCritical = true;
        echo "[CRÍTICO] .env.local dentro do webroot contém conteúdo não desativado:\n";
        echo "  - {$embeddedPath}\n";
        echo "  - Mova as credenciais reais para fora do webroot (pasta irmã 'guinchafacil-secrets/').\n\n";
    }
}

if (!empty($audit['warnings'])) {
    echo "[AVISO] Pontos de atenção:\n";
    foreach ($audit['warnings'] as $item) {
        echo "  - {$item}\n";
    }
    echo "\n";
}

if (!empty($audit['recommendations'])) {
    echo "[RECOMENDAÇÃO]\n";
    foreach ($audit['recommendations'] as $item) {
        echo "  - {$item}\n";
    }
    echo "\n";
}

// ZIPs residuais em files/ (histórico de packs anteriores)
$filesDir = $root . '/files';
$zips = is_dir($filesDir) ? glob($filesDir . '/*.zip') : [];
if (!empty($zips)) {
    echo "[AVISO] ZIPs residuais encontrados em files/ (não devem ir para release):\n";
    foreach ($zips as $zip) {
        echo "  - " . basename($zip) . "\n";
    }
    echo "\n";
}

// qa/node_modules empacotado fisicamente
if (is_dir($root . '/qa/node_modules')) {
    echo "[AVISO] qa/node_modules presente na árvore do projeto.\n";
    echo "  - Não é versionado pelo git, mas será incluído em qualquer cópia/zip manual da pasta.\n";
    echo "  - Exclua esse diretório antes de empacotar um release manual.\n\n";
}

if ($hasCritical) {
    echo "RESULTADO: FALHOU — corrija os itens críticos antes de liberar o release.\n";
    exit(1);
}

echo "RESULTADO: OK — nenhum bloqueador crítico de segredos encontrado.\n";
exit(0);
