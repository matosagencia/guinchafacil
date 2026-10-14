$ErrorActionPreference = 'Stop'
$root = 'C:\xampp\htdocs\guinchafacil'
Set-Location $root
# .NET NAO usa Location do PS. Forca o CurrentDirectory do processo:
[Environment]::CurrentDirectory = $root

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

Write-Host "=== fix-B-11-docs v3 (caminho absoluto) ===" -ForegroundColor Yellow
Write-Host ""

# ─────────────────────────────────────────────────────────────────
# [0/2] Sanidade
# ─────────────────────────────────────────────────────────────────
$docDir = Join-Path $root 'doc'
if (-not (Test-Path -LiteralPath $docDir)) {
    Write-Host "[0/2] ABORTADO: $docDir nao existe." -ForegroundColor Red
    exit 1
}
Write-Host "[0/2] pasta doc OK: $docDir" -ForegroundColor Green
Write-Host ""

# ─────────────────────────────────────────────────────────────────
# [1/2] doc/lanes/B.md
# ─────────────────────────────────────────────────────────────────
Write-Host "[1/2] doc/lanes/B.md" -ForegroundColor Cyan
$bPath = Join-Path $docDir 'lanes\B.md'

if (-not (Test-Path -LiteralPath $bPath)) {
    Write-Host "[1/2] NAO EXISTE: $bPath" -ForegroundColor Red
} else {
    Write-Host "[1/2] lendo: $bPath" -ForegroundColor DarkGray
    Copy-Item -LiteralPath $bPath "$bPath.bak-fix11docs-$stamp" -Force
    $b = [IO.File]::ReadAllText($bPath, [Text.Encoding]::UTF8)

    if ($b.Contains('fix-B-11 v2')) {
        Write-Host "[1/2] B.md: ja contem 'fix-B-11 v2' (SKIP)" -ForegroundColor Yellow
    } else {
        $anchor = '| 2026-10-01 | Verificação do estado da faixa B; `php -l` nos arquivos | Itens 1–3 já prontos; B-001 (404) e B-002 (`triagem`) abertos; sem diff de código PHP |'

        $novasLinhas = @(
            '| 2026-10-04 | fix-B-11 v2 — alerta toast+beep no /guincho/dashboard | OK — `checarPedidos()` passa a diffar IDs vistos (sessionStorage) e disparar toast+beep+blink de titulo; B-005 fechado |'
            '| 2026-10-04 | D3 confirmado pelo dono: pasta real e `doc/` (singular) | Sem impacto no codigo; `lanes.json` continua com `docs/` e fica pendente para o dono |'
        )

        $nl = if ($b.Contains("`r`n")) { "`r`n" } else { "`n" }

        if ($b.Contains($anchor)) {
            $injecao = $nl + ($novasLinhas -join $nl)
            $b = $b.Replace($anchor, $anchor + $injecao)
            [IO.File]::WriteAllText($bPath, $b, $utf8NoBom)
            Write-Host "[1/2] B.md atualizado" -ForegroundColor Green
        } else {
            Write-Host "[1/2] B.md: anchor 2026-10-01 nao casou. Fallback: ultima linha '| 20YY-...|'" -ForegroundColor Yellow
            $lines = $b -split "`r?`n"
            $lastIdx = -1
            for ($i = 0; $i -lt $lines.Count; $i++) {
                if ($lines[$i] -match '^\|\s*20\d{2}-\d{2}-\d{2}\s*\|') { $lastIdx = $i }
            }
            if ($lastIdx -ge 0) {
                $merged = @($lines[0..$lastIdx]) + $novasLinhas + @($lines[($lastIdx+1)..($lines.Count-1)])
                [IO.File]::WriteAllText($bPath, ($merged -join $nl), $utf8NoBom)
                Write-Host "[1/2] B.md atualizado via fallback" -ForegroundColor Green
            } else {
                Write-Host "[1/2] B.md: sem linha de historico para ancorar (SKIP)" -ForegroundColor Red
            }
        }
    }
}
Write-Host ""

# ─────────────────────────────────────────────────────────────────
# [2/2] doc/BLOCKERS.md
# ─────────────────────────────────────────────────────────────────
Write-Host "[2/2] doc/BLOCKERS.md" -ForegroundColor Cyan
$blPath = Join-Path $docDir 'BLOCKERS.md'

if (-not (Test-Path -LiteralPath $blPath)) {
    Write-Host "[2/2] NAO EXISTE: $blPath" -ForegroundColor Red
} else {
    Write-Host "[2/2] lendo: $blPath" -ForegroundColor DarkGray
    Copy-Item -LiteralPath $blPath "$blPath.bak-fix11docs-$stamp" -Force
    $bl = [IO.File]::ReadAllText($blPath, [Text.Encoding]::UTF8)

    if ($bl.Contains('BLOCKER B-005')) {
        Write-Host "[2/2] BLOCKERS.md: B-005 ja existe (SKIP)" -ForegroundColor Yellow
    } else {
        $bloco = @'

---
## BLOCKER B-005 — Alerta de novo pedido nao dispara no dashboard do guincho

**Data:** 2026-10-04
**Faixa:** B
**Severidade:** media (UX do guincho)
**Status:** **RESOLVIDO em 2026-10-04** — `checarPedidos()` so re-renderizava; faltava disparar toast/beep/blink.

**Sintoma:**
Pedido criado com `status = aguardando_guincho` nao gerava alerta visual/sonoro no dashboard do guincho logado. O pedido aparecia na lista (apos poll de 45 s ou SSE), mas sem alerta destacado.

**Diagnostico (verificado):**
- Rota `/guincho/pedidos-disponiveis` existe (`index.php` L324 -> `GuinchoController::pedidosDisponiveis`).
- `ClienteController` L963 grava `status='aguardando_guincho'`; backend filtra corretamente.
- `dashboard.php` L1614-1634: `checarPedidos()` chamava apenas `renderOferta()` e `renderFila()`. Sem toast, sem beep, sem blink de titulo. Este era o bug.
- Infra pronta: SSE `/sse/pedidos` (L1646) + poll 45 s (L1698) + listener `novo_pedido` (L1658).

**Correcao (fix-B-11 v2):**
- Helpers `gfLerVistos`/`gfSalvarVistos` (sessionStorage `gf_pedidos_vistos_v1`) + `gfBeep` + `gfToastNovos` + `gfPiscarTitulo`.
- `checarPedidos()` agora faz diff de IDs: se `novos.length > 0` e nao for a primeira rodada, dispara beep + toast verde + (se aba em background) pisca titulo.

**Fora de escopo (registro):**
- `src/Controllers/GuinchoController.php` NAO esta em `docs/lanes.json` (nem em `_sem_faixa_definida`). Nao foi alterado. Se precisar de mudanca futura, o dono define a faixa.

**Arquivo alterado:** `src/Views/guincho/dashboard.php`
'@
        $nl = if ($bl.Contains("`r`n")) { "`r`n" } else { "`n" }
        $blocoNorm = ($bloco -replace "`r?`n", $nl).TrimEnd()
        $bl = $bl.TrimEnd() + $nl + $blocoNorm + $nl
        [IO.File]::WriteAllText($blPath, $bl, $utf8NoBom)
        Write-Host "[2/2] BLOCKERS.md: B-005 registrado" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "=== OK ===" -ForegroundColor Green
Write-Host "Backups: sufixo .bak-fix11docs-$stamp" -ForegroundColor Cyan
Write-Host ""
Write-Host "VERIFICAR:" -ForegroundColor Cyan
Write-Host "  Select-String '$bPath' -Pattern 'fix-B-11 v2' | Select -First 2" -ForegroundColor Yellow
Write-Host "  Select-String '$blPath' -Pattern 'BLOCKER B-005' | Select -First 1" -ForegroundColor Yellow