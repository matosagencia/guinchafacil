$ErrorActionPreference = "Stop"
$file = "C:\xampp\htdocs\guinchafacil\public\assets\js\public-pre-cotacao-flow.js"
Copy-Item $file "$file.bak-eco" -Force
$c = Get-Content $file -Raw -Encoding UTF8

$old = @"
        html += '</div>';

        if (d.economia_estimada != null && d.economia_estimada > 0) {
            html += '<div class="economia-box">' +
                '<i class="fas fa-piggy-bank me-1"></i> ' +
                'Escolhendo <strong>resolver no local</strong> voce economiza <strong>' + money(d.economia_estimada) + '</strong>.' +
                '</div>';
        }
"@

$new = @"
        if (d.economia_estimada != null && d.economia_estimada > 0) {
            html += '<div class="economia-box">' +
                '<i class="fas fa-piggy-bank me-1"></i> ' +
                'Escolhendo <strong>resolver no local</strong> voce economiza <strong>' + money(d.economia_estimada) + '</strong>.' +
                '</div>';
        }

        html += '</div>';
"@

if ($c.Contains($new)) {
    Write-Host "[SKIP] economia-box ja esta dentro do grid"
    exit 0
} elseif ($c.Contains($old)) {
    $c = $c.Replace($old, $new)
    Write-Host "[OK] economia-box movida pra dentro do grid"
} else {
    Write-Host "[FAIL] anchor do economia-box nao encontrado"
    exit 1
}

[System.IO.File]::WriteAllText($file, $c, (New-Object System.Text.UTF8Encoding($false)))

node --check $file
if ($LASTEXITCODE -ne 0) { exit 1 }
Write-Host "[OK] sintaxe valida"

# Bump de versao pra forcar reload
$view = "C:\xampp\htdocs\guinchafacil\src\Views\public\pre-cotacao.php"
Copy-Item $view "$view.bak-bump3" -Force
$v = Get-Content $view -Raw -Encoding UTF8
$v = $v.Replace('flow.js?v=20260929-2', 'flow.js?v=20260929-3')
$v = $v.Replace('address-picker.js?v=20260929-2', 'address-picker.js?v=20260929-3')
$v = $v.Replace('address-picker.css?v=20260929-2', 'address-picker.css?v=20260929-3')
[System.IO.File]::WriteAllText($view, $v, (New-Object System.Text.UTF8Encoding($false)))
Write-Host "[OK] versoes bumpadas para -3"