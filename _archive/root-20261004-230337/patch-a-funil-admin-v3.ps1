param(
    [string]$AppRoot = 'C:\xampp\htdocs\guinchafacil'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$phase = 'init'
$targetReplaced = $false
$tempPath = $null
$backupPath = $null

function Write-PhaseLog {
    param([string]$Phase, [string]$Message)
    Write-Host "[AdminFlowV3][$Phase] $Message"
}

try {
    $phase = 'preflight'
    $jsPath = Join-Path $AppRoot 'public\assets\js\public-pre-cotacao-flow.js'

    if (-not (Test-Path -LiteralPath $jsPath -PathType Leaf)) {
        throw "Arquivo não encontrado: $jsPath"
    }

    $node = Get-Command 'node.exe' -ErrorAction SilentlyContinue
    if (-not $node) {
        $node = Get-Command 'node' -ErrorAction SilentlyContinue
    }
    if (-not $node) {
        throw 'Node.js não encontrado no PATH. Nenhuma alteração foi feita.'
    }

    $content = [System.IO.File]::ReadAllText($jsPath, [System.Text.Encoding]::UTF8)

    $hasSkip = $content.Contains('OPTS.skipVeiculo === true')
    $hasLoaded = $content.Contains("gf:reboques-loaded")
    $hasConsult = $content.Contains("apiGet('/reboques-proximos'")

    if ($hasSkip -and $hasLoaded -and $hasConsult) {
        Write-PhaseLog 'idempotent' 'As alterações já estão aplicadas; nenhum arquivo foi alterado.'
        exit 0
    }

    if ($hasLoaded -xor $hasConsult) {
        throw 'Patch parcial de consulta de reboques detectado. Nenhuma alteração foi feita.'
    }

    $apiLine = "    var API = (window.__gfBasePath || '').replace(/\/$/, '') + '/api/pre-cotacao';"
    $optionsMarker = 'var OPTS = window.__gfFlowOptions || {};'

    if (-not $content.Contains($optionsMarker)) {
        $apiIndex = $content.IndexOf($apiLine, [System.StringComparison]::Ordinal)
        if ($apiIndex -lt 0 -or $apiIndex -ne $content.LastIndexOf($apiLine, [System.StringComparison]::Ordinal)) {
            throw 'Âncora única da API não encontrada. Nenhuma alteração foi feita.'
        }

        $newline = if ($content.Contains("`r`n")) { "`r`n" } else { "`n" }
        $optionsBlock = @'
    var OPTS = window.__gfFlowOptions || {};
    var VEHICLE_CATEGORIES = ['popular', 'moto', 'suv', 'caminhonete', 'eletrico'];
'@
        $content = $content.Insert(
            $apiIndex + $apiLine.Length,
            $newline + $newline + $optionsBlock.TrimEnd()
        )
    }

    if (-not $hasSkip) {
        $modePattern = "(?s)        if \(modo === 'levar_carro'\) \{\r?\n            showStage\('veiculo'\);\r?\n        \} else \{\r?\n            showStage\('veiculo'\); // triagem de servico agora acontece DEPOIS do veiculo\r?\n        \}"

        $matches = [System.Text.RegularExpressions.Regex]::Matches($content, $modePattern)
        if ($matches.Count -ne 1) {
            throw "Bloco do seletor de modo encontrado $($matches.Count) vezes; esperado 1."
        }

        $modeReplacement = @'
        var categoriaInformada = String(OPTS.veiculoCategoria || '').toLowerCase();
        if (OPTS.skipVeiculo === true && VEHICLE_CATEGORIES.indexOf(categoriaInformada) !== -1) {
            STATE.veiculo = categoriaInformada;
            $$('[data-categoria]').forEach(function (b) {
                b.classList.toggle('is-selected', b.getAttribute('data-categoria') === categoriaInformada);
            });
            log('veiculo predefinido ->', categoriaInformada);
            showStage('sintoma');
            carregarTriagemServico();
            return;
        }
        // Sem categoria válida, mantém a escolha manual de veículo.
        showStage('veiculo');
'@

        $content = [System.Text.RegularExpressions.Regex]::Replace(
            $content,
            $modePattern,
            [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $modeReplacement },
            1
        )
    }

    if (-not $hasConsult) {
        $destinationStart = $content.IndexOf('    // Destino', [System.StringComparison]::Ordinal)
        if ($destinationStart -lt 0) {
            throw 'Seção de destino não encontrada.'
        }

        $listenerStart = $content.IndexOf(
            "    document.addEventListener('gf:address-confirmed', function (ev) {",
            $destinationStart,
            [System.StringComparison]::Ordinal
        )
        $sectionEnd = $content.IndexOf(
            "    // ----------------------------------------------------------------`n    // Submissao final",
            $listenerStart,
            [System.StringComparison]::Ordinal
        )
        if ($sectionEnd -lt 0) {
            $sectionEnd = $content.IndexOf(
                "    // ----------------------------------------------------------------`r`n    // Submissao final",
                $listenerStart,
                [System.StringComparison]::Ordinal
            )
        }
        if ($listenerStart -lt 0 -or $sectionEnd -lt 0 -or $sectionEnd -le $listenerStart) {
            throw 'Não encontrei os limites exatos do listener de destino. Nenhuma alteração foi feita.'
        }

        $destinationReplacement = @'
    document.addEventListener('gf:address-confirmed', function (ev) {
        var d = ev.detail || {};
        if (d.role !== 'destino') return;
        log('destino confirmado', d);
        STATE.destino = d;

        function carregarCotacaoReboque() {
            apiGet('/opcoes', {
                modo: 'reboque',
                lat: STATE.endereco.lat,
                lng: STATE.endereco.lng,
                lat_destino: d.lat,
                lng_destino: d.lng,
            }).then(function (j) {
                if (j && j.ok && j.data && j.data.disponivel && j.data.valor_reboque != null) {
                    STATE.valorCotado = j.data.valor_reboque;
                } else {
                    STATE.valorCotado = null;
                }
                preencherHiddenFields('reboque');
                showStage('cotacao');
            }).catch(function (e) {
                log('erro cotacao reboque', e);
                STATE.valorCotado = null;
                preencherHiddenFields('reboque');
                showStage('cotacao');
            });
        }

        if (OPTS.consultarReboques !== true) {
            carregarCotacaoReboque();
            return;
        }

        apiGet('/reboques-proximos', {
            lat_origem: STATE.endereco.lat,
            lng_origem: STATE.endereco.lng,
            lat_destino: d.lat,
            lng_destino: d.lng,
            categoria: STATE.veiculo || 'popular'
        }).then(function (j) {
            STATE.reboquesDisponiveis = (j && j.ok && j.data && j.data.guinchos) || [];
        }).catch(function (e) {
            log('erro consulta reboques proximos', e);
            STATE.reboquesDisponiveis = [];
        }).then(function () {
            document.dispatchEvent(new CustomEvent('gf:reboques-loaded', {
                detail: { guinchos: STATE.reboquesDisponiveis }
            }));
            carregarCotacaoReboque();
        });
    });

'@

        $content = $content.Substring(0, $listenerStart) +
                   $destinationReplacement +
                   $content.Substring($sectionEnd)
    }

    if (-not $content.Contains($optionsMarker) -or
        -not $content.Contains('OPTS.skipVeiculo === true') -or
        -not $content.Contains("apiGet('/reboques-proximos'") -or
        -not $content.Contains('gf:reboques-loaded')) {
        throw 'Verificação dos marcadores do patch falhou.'
    }

    $phase = 'backup'
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $backupPath = "$jsPath.bak-funil-admin-v3-$stamp"
    Copy-Item -LiteralPath $jsPath -Destination $backupPath

    $tempPath = Join-Path (Split-Path -Parent $jsPath) (
        '.public-pre-cotacao-flow-' + [guid]::NewGuid().ToString('N') + '.tmp.js'
    )
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)

    $phase = 'syntax-check-before-install'
    [System.IO.File]::WriteAllText($tempPath, $content, $utf8NoBom)
    & $node.Source --check $tempPath
    if ($LASTEXITCODE -ne 0) {
        throw "node --check falhou antes da instalação (exit code $LASTEXITCODE)."
    }

    $phase = 'install'
    Move-Item -LiteralPath $tempPath -Destination $jsPath -Force
    $tempPath = $null
    $targetReplaced = $true

    $phase = 'syntax-check-after-install'
    & $node.Source --check $jsPath
    if ($LASTEXITCODE -ne 0) {
        throw "node --check falhou após a instalação (exit code $LASTEXITCODE)."
    }

    Write-PhaseLog 'success' "Patch aplicado. Backup: $backupPath"
    Write-PhaseLog 'behavior' 'A etapa de veículo é pulada somente com categoria válida; a consulta de reboques é opcional e emite gf:reboques-loaded.'
    Write-PhaseLog 'note' 'Se o endpoint ainda não estiver ativo, a lista será emitida vazia e o cálculo normal da cotação continuará.'
}
catch {
    Write-PhaseLog $phase ("FALHA: " + $_.Exception.Message)

    if ($targetReplaced -and $backupPath -and (Test-Path -LiteralPath $backupPath)) {
        try {
            Copy-Item -LiteralPath $backupPath -Destination $jsPath -Force
            Write-PhaseLog 'rollback' "Arquivo restaurado: $backupPath"
        }
        catch {
            Write-PhaseLog 'rollback-error' ("Restauração automática falhou: " + $_.Exception.Message)
            Write-Host "[AdminFlowV3][manual-rollback] Copy-Item -LiteralPath '$backupPath' -Destination '$jsPath' -Force"
        }
    }

    if ($tempPath -and (Test-Path -LiteralPath $tempPath)) {
        Remove-Item -LiteralPath $tempPath -Force -ErrorAction SilentlyContinue
    }

    exit 1
}