param([Parameter(Mandatory)][string]$Lane)
git fetch origin --quiet 2>$null
$base = (git describe --tags --abbrev=0 --match "ok-*" 2>$null)
if (-not $base) { $base = "origin/main" }
Write-Host "lane_guard base: $base" -ForegroundColor Cyan
$lanesPath = "doc/lanes.json"
$lanes = Get-Content $lanesPath -Raw | ConvertFrom-Json
$allowed = $lanes.$Lane
$changed = git diff --name-only "$base..HEAD"
$bad = $changed | Where-Object { $f=$_; -not ($allowed | Where-Object { $f -like $_ }) }
if ($bad) { Write-Host "FORA DA FAIXA ${Lane}:" -ForegroundColor Red; $bad; exit 1 }
Write-Host "lane_guard OK" -ForegroundColor Green