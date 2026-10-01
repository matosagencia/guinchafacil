param([Parameter(Mandatory)][string]$Lane)
$lanesPath = "doc/lanes.json"
$lanes = Get-Content $lanesPath -Raw | ConvertFrom-Json
$allowed = $lanes.$Lane
$changed = git diff --name-only origin/main...HEAD
$bad = $changed | Where-Object { $f=$_; -not ($allowed | Where-Object { $f -like $_ }) }
if ($bad) { Write-Host "FORA DA FAIXA ${Lane}:" -ForegroundColor Red; $bad; exit 1 }
Write-Host "lane_guard OK" -ForegroundColor Green