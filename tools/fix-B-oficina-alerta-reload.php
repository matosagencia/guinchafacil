<?php
/**
 * fix-B-oficina-alerta-reload.php
 *
 * Corrige o bug do beep da oficina: o checarPedidos() chama
 * location.reload() ANTES de retornar, entao o __gfOficinaChecarAlerta
 * nunca e chamado.
 *
 * Fix: chama o alerta antes do reload, espera 500ms, e so entao recarrega.
 */

$root = 'C:/xampp/htdocs/guinchafacil';
$stamp = date('Ymd-His');

$file = $root . '/src/Views/oficina/dashboard.php';

if (!file_exists($file)) {
    fwrite(STDERR, "[ERRO] Arquivo nao encontrado: $file\n");
    exit(1);
}

$backup = $file . '.bak-alertareload-' . $stamp;
copy($file, $backup);
echo "[ok] Backup: $backup\n";

$c = file_get_contents($file);

// Se ja tem o fix, pula
if (strpos($c, '[B-OFICINA-ALERTA-RELOAD]') !== false) {
    echo "[info] fix ja existe - nada a fazer\n";
    exit(0);
}

$antes = <<<'JS'
            if (pedidos.length) {
                // Recarrega a página se houver pedido novo diferente
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(pedidos[0].id) !== String(atualId)) {
                    location.reload();
                    return pedidos;
                }
            }
JS;

$antes = str_replace("\r\n", "\n", $antes);

$depois = <<<'JS'
            if (pedidos.length) {
                // Recarrega a página se houver pedido novo diferente
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(pedidos[0].id) !== String(atualId)) {
                    // [B-OFICINA-ALERTA-RELOAD] Dispara o alerta ANTES do reload
                    // para o beep/toast saírem. Sem isso, o location.reload() mata
                    // o JS antes do alerta rodar.
                    if (window.__gfOficinaChecarAlerta) {
                        try { window.__gfOficinaChecarAlerta(pedidos); } catch (e) {}
                    }
                    setTimeout(function() { location.reload(); }, 800);
                    return pedidos;
                }
            }
JS;

$depois = str_replace("\r\n", "\n", $depois);

// Normaliza o arquivo
$cNorm = str_replace("\r\n", "\n", $c);

if (strpos($cNorm, $antes) === false) {
    fwrite(STDERR, "[ERRO] Ancora nao encontrada no dashboard da oficina.\n");
    fwrite(STDERR, "Procurando variacao...\n");

    // Fallback: procura apenas a linha do location.reload()
    $altAnchor = "                if (String(pedidos[0].id) !== String(atualId)) {\n                    location.reload();\n                    return pedidos;\n                }";
    $altAnchor = str_replace("\r\n", "\n", $altAnchor);

    if (strpos($cNorm, $altAnchor) === false) {
        fwrite(STDERR, "[ERRO] Variacao tambem nao encontrada. Abortando.\n");
        copy($backup, $file);
        exit(2);
    }

    $altDepois = "                if (String(pedidos[0].id) !== String(atualId)) {\n"
               . "                    // [B-OFICINA-ALERTA-RELOAD]\n"
               . "                    if (window.__gfOficinaChecarAlerta) {\n"
               . "                        try { window.__gfOficinaChecarAlerta(pedidos); } catch (e) {}\n"
               . "                    }\n"
               . "                    setTimeout(function() { location.reload(); }, 800);\n"
               . "                    return pedidos;\n"
               . "                }";
    $altDepois = str_replace("\r\n", "\n", $altDepois);

    $cNorm = str_replace($altAnchor, $altDepois, $cNorm);
} else {
    $cNorm = str_replace($antes, $depois, $cNorm);
}

// Devolve para CRLF
$c = str_replace("\n", "\r\n", $cNorm);

file_put_contents($file, $c);
echo "[ok] fix aplicado\n";

$lint = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($file) . ' 2>&1');
echo $lint;
if (strpos($lint, 'No syntax errors') === false) {
    copy($backup, $file);
    fwrite(STDERR, "[ERRO] php -l falhou. Restaurado.\n");
    exit(3);
}

echo "\n=== PATCH B-OFICINA-ALERTA-RELOAD APLICADO ===\n";