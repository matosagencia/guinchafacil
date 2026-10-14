param([string]$Repositorio = (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)))
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$phpPath = 'C:\xampp\php\php.exe'
$payloadRoot = Split-Path -Parent $PSScriptRoot
$changes = New-Object System.Collections.ArrayList
function Install-View {
    param([string]$Relative)
    $source = Join-Path $payloadRoot $Relative
    $target = Join-Path $Repositorio $Relative
    if (-not (Test-Path -LiteralPath $source)) { throw ('[A5.2F2][PAYLOAD] Missing: ' + $source) }
    $content = [System.IO.File]::ReadAllText($source)
    if (Test-Path -LiteralPath $target) {
        if ([System.IO.File]::ReadAllText($target) -ceq $content) { Write-Host ('[A5.2F2][SKIP] ' + $Relative); return }
        $backup = $target + '.bak-' + $stamp
        Copy-Item -LiteralPath $target -Destination $backup -Force
        [void]$script:changes.Add(@{Target=$target;Backup=$backup;Existed=$true})
        Write-Host ('[A5.2F2][BACKUP] ' + $backup)
    } else { [void]$script:changes.Add(@{Target=$target;Backup=$null;Existed=$false}) }
    [System.IO.File]::WriteAllText($target, $content, $script:utf8NoBom)
    Write-Host ('[A5.2F2][WRITE] ' + $Relative)
}
try {
    if (-not (Test-Path -LiteralPath $phpPath)) { throw ('[A5.2F2][CONFIG] PHP not found: ' + $phpPath) }
    $files = @('src\Views\public\parceiros\index.php','src\Views\public\parceiros\guinchos.php','src\Views\public\parceiros\oficinas.php')
    foreach ($file in $files) { Install-View $file }
    foreach ($file in $files) { & $phpPath -l (Join-Path $Repositorio $file); if ($LASTEXITCODE -ne 0) { throw ('[A5.2F2][LINT] Failed: ' + $file) }; Write-Host ('[A5.2F2][LINT] OK: ' + $file) }
    Write-Host '[A5.2F2][DONE] Partner public pages installed successfully.'
} catch {
    Write-Host ('[A5.2F2][ERROR] ' + $_.Exception.Message)
    for ($i=$changes.Count-1;$i -ge 0;$i--) { $c=$changes[$i]; if($c.Existed){Copy-Item -LiteralPath $c.Backup -Destination $c.Target -Force;Write-Host ('[A5.2F2][ROLLBACK] Restored '+$c.Target)}elseif(Test-Path -LiteralPath $c.Target){Remove-Item -LiteralPath $c.Target -Force;Write-Host ('[A5.2F2][ROLLBACK] Removed '+$c.Target)} }
    exit 1
}
