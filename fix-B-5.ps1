$ErrorActionPreference = 'Stop'
Set-Location 'C:\xampp\htdocs\guinchafacil'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$php = 'C:\xampp\php\php.exe'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

$ctrlPath = (Resolve-Path 'src\Controllers\ClienteController.php').Path
$ctrlBak  = "$ctrlPath.bak-fix5-$stamp"
Copy-Item $ctrlPath $ctrlBak
Write-Host "[backup] $ctrlBak" -ForegroundColor Cyan

if (-not ((& $php -l $ctrlPath 2>&1 | Out-String) -match 'No syntax errors')) {
    Write-Host "Controller corrompido — restaure primeiro." -ForegroundColor Red
    exit 1
}

$c = [IO.File]::ReadAllText($ctrlPath)

if ($c.Contains('MOTOR-UNICO-02')) {
    Write-Host "Ja aplicado — SKIP" -ForegroundColor Yellow
    exit 0
}

$oldBloco = @'
        $modalidadeResolvidaPreview = (new ModalidadeResolver())->resolver($tipo, [
            'endereco_destino' => $endDest,
        ]);
        $reboqueExigeDestino = $modalidadeResolvidaPreview === ModalidadeResolver::REBOQUE_PRANCHA;
        if ($reboqueExigeDestino && ($latDest === 0.0 || $lngDest === 0.0 || !$latValida($latDest) || !$lngValida($lngDest))) {
            $this->redirect('/cliente/pedido/novo?erro=coordenadas_destino');
        }
'@

$newBloco = @'
        // §MOTOR-UNICO-02: quando o motor publico manda decisao_atendimento
        // = 'assistencia' ou 'local', o cliente escolheu "Resolver no local"
        // no stage opcoes — nao forcar destino mesmo que ModalidadeResolver
        // do tipo_problema diga REBOQUE_PRANCHA.
        $decisaoAtendimentoMotor = strtolower(trim((string)($_POST['decisao_atendimento'] ?? '')));
        $motorForcouAssistenciaLocal = in_array($decisaoAtendimentoMotor, ['assistencia', 'local'], true);

        if ($motorForcouAssistenciaLocal) {
            $reboqueExigeDestino = false;
        } else {
            $modalidadeResolvidaPreview = (new ModalidadeResolver())->resolver($tipo, [
                'endereco_destino' => $endDest,
            ]);
            $reboqueExigeDestino = $modalidadeResolvidaPreview === ModalidadeResolver::REBOQUE_PRANCHA;
        }

        if ($reboqueExigeDestino && ($latDest === 0.0 || $lngDest === 0.0 || !$latValida($latDest) || !$lngValida($lngDest))) {
            $this->redirect('/cliente/pedido/novo?erro=coordenadas_destino');
        }

        // Pedido ON_SITE: espelha origem nas coords de destino (mesmo ponto).
        if (!$reboqueExigeDestino && ($latDest === 0.0 || $lngDest === 0.0)) {
            $latDest = $latOrigem;
            $lngDest = $lngOrigem;
            if ($endDest === '' || $endDest === 'Destino informado') {
                $endDest = $endOrigem;
            }
        }
'@

$oldBloco = $oldBloco -replace "`r?`n", "`r`n"
$newBloco = $newBloco -replace "`r?`n", "`r`n"

if ($c.Contains($oldBloco)) {
    $c = $c.Replace($oldBloco, $newBloco)
    Write-Host "[1] bloco substituido" -ForegroundColor Green
} else {
    Write-Host "Bloco exato nao casou — tentando regex flexivel" -ForegroundColor Yellow
    $pattern = "(?s)\\\$modalidadeResolvidaPreview = \(new ModalidadeResolver\(\)\)->resolver\(\\\$tipo, \[\s*'endereco_destino' => \\\$endDest,\s*\]\);\s*\\\$reboqueExigeDestino = \\\$modalidadeResolvidaPreview === ModalidadeResolver::REBOQUE_PRANCHA;\s*if \(\\\$reboqueExigeDestino && \(\\\$latDest === 0\.0 \|\| \\\$lngDest === 0\.0 \|\| !\\\$latValida\(\\\$latDest\) \|\| !\\\$lngValida\(\\\$lngDest\)\)\) \{\s*\\\$this->redirect\('/cliente/pedido/novo\?erro=coordenadas_destino'\);\s*\}"
    $c2 = [regex]::Replace($c, $pattern, $newBloco, 1)
    if ($c2 -eq $c) {
        Write-Host "Regex falhou — abortando" -ForegroundColor Red
        Copy-Item $ctrlBak $ctrlPath -Force
        exit 1
    }
    $c = $c2
    Write-Host "[1] bloco substituido (regex)" -ForegroundColor Green
}

[IO.File]::WriteAllText($ctrlPath, $c, $utf8NoBom)

$lint = (& $php -l $ctrlPath 2>&1 | Out-String)
if ($lint -match 'No syntax errors') {
    Write-Host ""
    Write-Host "OK — aplicado." -ForegroundColor Green
    Write-Host "Backup: $ctrlBak" -ForegroundColor Cyan
} else {
    Copy-Item $ctrlBak $ctrlPath -Force
    Write-Host "php -l FALHOU — revertido" -ForegroundColor Red
    Write-Host $lint
    exit 1
}