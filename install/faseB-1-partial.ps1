$ErrorActionPreference = "Stop"
$file = "C:\xampp\htdocs\guinchafacil\src\Views\public\partials\_precotacao_funil.php"
Copy-Item $file "$file.bak-faseB" -Force
$c = Get-Content $file -Raw -Encoding UTF8

if ($c.Contains('id="stage-sintoma"')) {
    Write-Host "[SKIP] stage-sintoma ja existe"
    exit 0
}

$old = @"
            <!-- Container populado via JS (chamada /triagem-servicos) -->
            <div class="col-12 mt-3" id="triagem-servicos" aria-live="polite"></div>
        </fieldset>
    </div>

    <!-- --- ESTAGIO 4: OPCOES (comparativa A/B) --- -->
"@

$new = @"
        </fieldset>
    </div>

    <!-- --- ESTAGIO 3.5: SINTOMA (tipo de problema) --- -->
    <div class="col-12" id="stage-sintoma" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Qual e o problema?</legend>
            <div class="choice-grid choice-grid-sintoma" id="triagem-servicos" aria-live="polite"></div>
            <div class="mt-3">
                <button type="button" class="btn btn-outline-secondary" data-action="voltar">Voltar</button>
            </div>
        </fieldset>
    </div>

    <!-- --- ESTAGIO 4: OPCOES (comparativa A/B) --- -->
"@

if (-not $c.Contains($old)) {
    Write-Host "[FAIL] anchor triagem-servicos nao encontrado"
    Select-String -Path $file -Pattern "triagem-servicos" -Context 3,3 | ForEach-Object { $_.Line }
    exit 1
}

$c = $c.Replace($old, $new)
[System.IO.File]::WriteAllText($file, $c, (New-Object System.Text.UTF8Encoding($false)))
Write-Host "[OK] stage-sintoma adicionado ao partial"

& C:\xampp\php\php.exe -l $file
Select-String -Path $file -Pattern 'id="stage-(veiculo|sintoma|opcoes)"' | ForEach-Object { "L$($_.LineNumber): $($_.Line.Trim())" }