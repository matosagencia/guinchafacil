<?php
// install-fix-redirect.php — adiciona case 'oficina' no redirectByProfile()
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$path = $root . '/src/Controllers/AuthController.php';
$report = [];

if (!file_exists($path)) die('ERRO: AuthController não encontrado');
$content = file_get_contents($path);

// Backup
$bak = $path . '.bak-redirect-' . date('Ymd-His');
@copy($path, $bak);
$report[] = "[OK] Backup: " . basename($bak);

// Bloco a procurar (o match atual sem 'oficina')
$oldBlock = <<<'PHPEOF'
        $dest = match($tipo) {
            'admin'       => '/admin/dashboard',
            'guincho'     => '/guincho/dashboard',
            'cliente'     => '/cliente/dashboard',
            'funcionario' => '/funcionario/dashboard',
            'gerente'     => '/gerente/dashboard',
            'especialista'=> '/especialista/dashboard',
            // Nunca aponte o default de volta pra '/login': se o usuário já
            // está autenticado (isAuthenticated()==true) e cai aqui, o
            // loginForm() redireciona de novo pra cá — loop infinito
            // (ERR_TOO_MANY_REDIRECTS). Isso pegou tipos novos (funcionario/
            // gerente) que ainda não existiam neste match. Melhor destino
            // seguro pra um tipo desconhecido é logout, não login.
            default       => '/logout',
        };
PHPEOF;

$newBlock = <<<'PHPEOF'
        $dest = match($tipo) {
            'admin'       => '/admin/dashboard',
            'guincho'     => '/guincho/dashboard',
            'cliente'     => '/cliente/dashboard',
            'funcionario' => '/funcionario/dashboard',
            'gerente'     => '/gerente/dashboard',
            'especialista'=> '/especialista/dashboard',
            'oficina'     => '/oficina/dashboard',
            // Nunca aponte o default de volta pra '/login': se o usuário já
            // está autenticado (isAuthenticated()==true) e cai aqui, o
            // loginForm() redireciona de novo pra cá — loop infinito
            // (ERR_TOO_MANY_REDIRECTS). Isso pegou tipos novos (funcionario/
            // gerente) que ainda não existiam neste match. Melhor destino
            // seguro pra um tipo desconhecido é logout, não login.
            default       => '/logout',
        };
PHPEOF;

if (strpos($content, "'oficina'     => '/oficina/dashboard'") !== false) {
    $report[] = "[SKIP] Case 'oficina' já existe";
} elseif (strpos($content, $oldBlock) !== false) {
    $content = str_replace($oldBlock, $newBlock, $content);
    @file_put_contents($path, $content);
    $report[] = "[OK] Case 'oficina' adicionado ao redirectByProfile";
} else {
    // Fallback: regex
    $pattern = "/(\$dest\s*=\s*match\s*\(\$tipo\)\s*\{[^}]*?'especialista'\s*=>\s*'\/especialista\/dashboard',)/s";
    if (preg_match($pattern, $content)) {
        $content = preg_replace(
            $pattern,
            "$1\n            'oficina'     => '/oficina/dashboard',",
            $content,
            1
        );
        @file_put_contents($path, $content);
        $report[] = "[OK] Case 'oficina' adicionado via regex";
    } else {
        $report[] = "[ERRO] Não consegui localizar o match do redirectByProfile";
    }
}

// Valida
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($path) . ' 2>&1');
$report[] = "[LINT] " . trim((string)$lint);

// Confirma
$final = file_get_contents($path);
if (strpos($final, "'oficina'     => '/oficina/dashboard'") !== false) {
    $report[] = "[VERIFY] ✅ Case 'oficina' confirmado no arquivo";
} else {
    $report[] = "[VERIFY] ❌ Case 'oficina' NÃO está no arquivo";
}

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix Redirect</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>Fix — Redirect do perfil oficina</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-redirect.php</p>
</body></html>