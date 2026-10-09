# registrar-debito-mojibake.ps1
# Adiciona o débito técnico do mojibake do AdminController.php em doc/BLOCKERS.md.
# Data: 2026-10-08

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$blockers = Join-Path $Repositorio 'doc\BLOCKERS.md'

if (-not (Test-Path -LiteralPath $blockers)) {
    Write-Host "[ERRO] BLOCKERS.md nao encontrado em $blockers" -ForegroundColor Red
    exit 1
}

$bak = "$blockers.bak-registrar-debito-mojibake-$stamp"
Copy-Item -LiteralPath $blockers -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

# Verificar se o debito ja existe
$texto = Get-Content -LiteralPath $blockers -Raw -Encoding UTF8
if ($texto.Contains('DEBITO TECNICO - Mojibake no AdminController.php') -or
    $texto.Contains('DÉBITO TÉCNICO — Mojibake no AdminController.php')) {
    Write-Host "[AVISO] Debito ja registrado. Nada a fazer." -ForegroundColor Yellow
    exit 0
}

# Bloco novo (usar caracteres ASCII para evitar problemas de encoding)
$bloco = @'

---
## DEBITO TECNICO - Mojibake no AdminController.php

**Data:** 2026-10-08
**Faixa:** B
**Severidade:** baixa (cosmetico — comentarios ilegiveis; codigo funciona)
**Status:** **Registrado** - nao corrigir agora (risco alto vs beneficio baixo)

**Descricao:**
`src/Controllers/AdminController.php` tem mojibake (`AƒAE'A...`) em
comentarios e strings literais — residuo de sessoes anteriores. O
`php -l` passa; o site funciona. Nao e bug funcional.

**Impacto:**

- Leitura humana dos comentarios prejudicada.
- `Select-String` nao acha palavras com acento (ex.: `eletrica`).

**Acao futura (quando houver janela de manutencao):**

1. `git stash` tudo (garante estado limpo).
2. Restaurar `AdminController.php` do commit `8fdc9ff`.
3. Corrigir o mojibake usando **bytes** (nao `Get-Content -Raw`):

       $bytes = [System.IO.File]::ReadAllBytes($path)
       $texto = [System.Text.Encoding]::UTF8.GetString($bytes)
       # ... edita ...
       [System.IO.File]::WriteAllBytes($path,
           [System.Text.Encoding]::UTF8.GetBytes($texto))

4. Reaplicar os patches da sessao 5:
   - `fix-B-stid-v6c.ps1`
   - `fix-B-admin-faturas-controller.ps1`
   - `fix-B-admin-faturas-menu-e-comissao.ps1`
5. Commit + push.

**Por que nao corrigir agora:**

- O arquivo tem 4.400+ linhas — refatoracao de altissimo risco.
- Cada edicao com `Get-Content` + `WriteAllText` PIORA o encoding
  (aconteceu 3 vezes nas sessoes 3, 4 e 5).
- O `php -l` passa; o site funciona.
- R6 do protocolo: diff minimo, sem limpezas fora da tarefa.

**Referencia cruzada:**

- Commit `cdffcfd` (sessao 5) no `origin/main`.
- Mesmo problema pode afetar `index.php` (verificar em sessao dedicada).
'@

# Adicionar ao fim do arquivo (antes do ultimo newline)
$texto = $texto.TrimEnd() + "`r`n" + $bloco
[System.IO.File]::WriteAllText($blockers, $texto, $utf8NoBom)

Write-Host "[WRITE] $blockers" -ForegroundColor Green

# Validar
Write-Host ''
Write-Host '=== Validacao ===' -ForegroundColor Cyan
$novo = Get-Content -LiteralPath $blockers -Raw -Encoding UTF8
if ($novo.Contains('Mojibake no AdminController.php')) {
    Write-Host '[OK] Debito registrado.' -ForegroundColor Green
} else {
    Write-Host '[ALERTA] Debito NAO foi registrado.' -ForegroundColor Red
}

Write-Host ''
Write-Host 'Para reverter:' -ForegroundColor Yellow
Write-Host "  Copy-Item '$bak' '$blockers' -Force"