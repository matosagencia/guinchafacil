$ErrorActionPreference = "Stop"
$file = "C:\xampp\htdocs\guinchafacil\public\assets\js\public-pre-cotacao-flow.js"
Copy-Item $file "$file.bak-faseB" -Force
$c = Get-Content $file -Raw -Encoding UTF8

$oldStages = "var STAGES = ['endereco', 'modo', 'veiculo', 'opcoes', 'destino', 'cotacao'];"
$newStages = "var STAGES = ['endereco', 'modo', 'veiculo', 'sintoma', 'opcoes', 'destino', 'cotacao'];"
if ($c.Contains($newStages)) {
    Write-Host "[SKIP] STAGES ja inclui sintoma"
} elseif ($c.Contains($oldStages)) {
    $c = $c.Replace($oldStages, $newStages)
    Write-Host "[OK] STAGES inclui sintoma"
} else {
    Write-Host "[FAIL] var STAGES nao encontrado"
    exit 1
}

$oldV = @"
        if (STATE.modo === 'levar_carro') {
            carregarOpcoes('reboque');
        } else {
            carregarOpcoes('');
        }
"@

$newV = @"
        if (STATE.modo === 'levar_carro') {
            showStage('destino');
        } else {
            showStage('sintoma');
            carregarTriagemServico();
        }
"@

if ($c.Contains("showStage('sintoma');")) {
    Write-Host "[SKIP] vehicle click ja redireciona pra sintoma"
} elseif ($c.Contains($oldV)) {
    $c = $c.Replace($oldV, $newV)
    Write-Host "[OK] vehicle click redireciona correto"
} else {
    Write-Host "[FAIL] anchor vehicle click nao encontrado"
    Select-String -Path $file -Pattern "STATE.modo === 'levar_carro'" -Context 0,6 | ForEach-Object { $_.Line; $_.Context.PostContext }
    exit 1
}

[System.IO.File]::WriteAllText($file, $c, (New-Object System.Text.UTF8Encoding($false)))

node --check $file
if ($LASTEXITCODE -ne 0) { exit 1 }
Write-Host "[OK] sintaxe valida"