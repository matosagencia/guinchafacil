$ErrorActionPreference = "Stop"
$file = "C:\xampp\htdocs\guinchafacil\src\Views\public\partials\_precotacao_funil.php"
Copy-Item $file "$file.bak-faseB2" -Force
$c = Get-Content $file -Raw -Encoding UTF8

if ($c.Contains('id="stage-sintoma"')) {
    Write-Host "[SKIP] stage-sintoma ja existe"
    exit 0
}

# ─── 1. Remove o triagem-servicos do bloco veiculo (linha unica, exact match) ───
$oldLine = '<div class="col-12 mt-3" id="triagem-servicos" aria-live="polite"></div>'
if ($c.Contains($oldLine)) {
    $c = $c.Replace($oldLine, '')
    Write-Host "[OK] triagem-servicos removido do veiculo"
} else {
    Write-Host "[AVISO] triagem-servicos nao achado no veiculo (segue)"
}

# ─── 2. Insere o stage-sintoma ANTES do stage-opcoes ───
$anchor = '<div class="col-12" id="stage-opcoes" hidden>'
$idx = $c.IndexOf($anchor)
if ($idx -lt 0) {
    Write-Host "[FAIL] stage-opcoes nao encontrado"
    exit 1
}

$bloco = @"
<!-- ESTAGIO 3.5: SINTOMA (tipo de problema) -->
    <div class="col-12" id="stage-sintoma" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Qual e o problema?</legend>
            <div class="choice-grid choice-grid-sintoma" id="triagem-servicos" aria-live="polite"></div>
            <div class="mt-3">
                <button type="button" class="btn btn-outline-secondary" data-action="voltar">Voltar</button>
            </div>
        </fieldset>
    </div>

    
"@

$c = $c.Substring(0, $idx) + $bloco + $c.Substring($idx)
[System.IO.File]::WriteAllText($file, $c, (New-Object System.Text.UTF8Encoding($false)))
Write-Host "[OK] stage-sintoma inserido antes de stage-opcoes"

Write-Host ""
Write-Host "--- Lint ---"
& C:\xampp\php\php.exe -l $file

Write-Host ""
Write-Host "--- Verificacao ---"
Select-String -Path $file -Pattern 'id="stage-(veiculo|sintoma|opcoes)"' | ForEach-Object { "L$($_.LineNumber): $($_.Line.Trim())" }
Select-String -Path $file -Pattern 'id="triagem-servicos"' | ForEach-Object { "L$($_.LineNumber): $($_.Line.Trim())" }