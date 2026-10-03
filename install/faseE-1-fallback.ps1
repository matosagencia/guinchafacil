$ErrorActionPreference = "Stop"
$file = "C:\xampp\htdocs\guinchafacil\public\assets\js\public-pre-cotacao-flow.js"
Copy-Item $file "$file.bak-faseE" -Force
$c = Get-Content $file -Raw -Encoding UTF8

# ─── Substitui o bloco "if (!d.disponivel)" dentro de carregarOpcoes ───
$old = @"
            if (!d.disponivel) {
                // Sem oficina para o servico. Se tem guincho, oferece reboque; senao WhatsApp.
                if (STATE.cobertura && STATE.cobertura.tem_guincho) {
                    wrap.innerHTML = '<div class='alert alert-warning'>' +
                        'Sem assistencia para ' + escapeHtml(slug || 'esse servico') +
                        ' na sua regiao agora. Mas temos guincho disponivel.</div>' +
                        '<button type='button' class='btn-main w-100' data-action='aceitar-reboque'>Quero rebocar</button>';
                    return;
                }
                renderWhatsApp(wrap);
                return;
            }
"@

$new = @"
            if (!d.disponivel) {
                var fallbackTipo = d.fallback_tipo || 'suporte';
                var mensagem = d.mensagem || 'Sem cobertura para esse servico na sua regiao.';

                if (fallbackTipo === 'guincho') {
                    // Modo orientacao sem oficina MAS com guincho: oferece reboque
                    wrap.innerHTML = '<div class='alert alert-warning'>' +
                        escapeHtml(mensagem) + ' Mas temos guincho disponivel.</div>' +
                        '<button type='button' class='btn-main w-100' data-action='aceitar-reboque'>Quero rebocar</button>' +
                        '<button type='button' class='btn btn-outline-secondary w-100 mt-2' data-action='voltar'>Voltar</button>';
                    return;
                }

                // 'suporte' em todos os modos: WhatsApp
                renderWhatsApp(wrap, mensagem);
                return;
            }
"@

if ($c.Contains($old)) {
    $c = $c.Replace($old, $new)
    Write-Host "[OK] FIX 1: bloco de fallback atualizado (guincho vs suporte)"
} else {
    Write-Host "[FAIL] anchor 'if (!d.disponivel)' nao encontrado"
    Write-Host "        Rode e me manda:"
    Write-Host "          Select-String -Path $file -Pattern 'nao conseguiu|!d.disponivel' -Context 3,10"
    exit 1
}

# ─── renderWhatsApp aceita mensagem custom ───
$oldWA = @"
    function renderWhatsApp(wrap) {
"@
$newWA = @"
    function renderWhatsApp(wrap, mensagemCustom) {
"@

if ($c.Contains($newWA)) {
    Write-Host "[SKIP] renderWhatsApp ja aceita mensagem"
} elseif ($c.Contains($oldWA)) {
    $c = $c.Replace($oldWA, $newWA)
    # Atualiza a mensagem dentro da funcao
    $oldMsg = "'Nenhuma oficina ou guincho disponivel agora. Vamos te atender pelo WhatsApp.'"
    $newMsg = "escapeHtml(mensagemCustom || 'Nenhuma oficina ou guincho disponivel agora. Vamos te atender pelo WhatsApp.')"
    if ($c.Contains($oldMsg)) {
        $c = $c.Replace($oldMsg, $newMsg)
        Write-Host "[OK] FIX 2: renderWhatsApp aceita mensagem custom"
    }
} else {
    Write-Host "[AVISO] renderWhatsApp — formato inesperado, segue sem custom"
}

# ─── Salva ───
[System.IO.File]::WriteAllText($file, $c, (New-Object System.Text.UTF8Encoding($false)))

# ─── Bump ───
$view = "C:\xampp\htdocs\guinchafacil\src\Views\public\pre-cotacao.php"
Copy-Item $view "$view.bak-bump6" -Force
$v = Get-Content $view -Raw -Encoding UTF8
$v = $v.Replace('flow.js?v=20260929-5', 'flow.js?v=20260929-6')
$v = $v.Replace('address-picker.js?v=20260929-5', 'address-picker.js?v=20260929-6')
$v = $v.Replace('address-picker.css?v=20260929-5', 'address-picker.css?v=20260929-6')
[System.IO.File]::WriteAllText($view, $v, (New-Object System.Text.UTF8Encoding($false)))
Write-Host "[OK] versoes bumpadas para -6"

Write-Host ""
node --check $file
if ($LASTEXITCODE -ne 0) { exit 1 }
Write-Host "[OK] sintaxe valida"