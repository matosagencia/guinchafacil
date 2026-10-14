$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repo = Split-Path -Parent $PSScriptRoot
$headerPath = Join-Path $repo 'src\Views\layouts\header.php'
$phpPath = 'C:\xampp\php\php.exe'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupPath = $null

$headBlock = @'
    <!-- Onda 5.1: typography + layout foundation -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/tokens.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/style.css" rel="stylesheet">
    <!-- L2.1 design system layers. -->
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/base.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/shell.css?v=20260802-2" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/components/page-head.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/gf-layout.css" rel="stylesheet">
'@

try {
    if (-not (Test-Path -LiteralPath $headerPath)) {
        throw ('[A5.1][HEADER] Missing required file: ' + $headerPath)
    }

    $current = [System.IO.File]::ReadAllText($headerPath)
    $marker = '<!-- Onda 5.1: typography + layout foundation -->'
    $tokenMatches = [regex]::Matches($current, 'public/assets/css/tokens\.css').Count

    if ($current.Contains($marker) -and $tokenMatches -eq 1 -and $current.Contains('public/assets/css/gf-layout.css')) {
        Write-Host '[A5.1][SKIP] src\Views\layouts\header.php already current'
        exit 0
    }

    $headPattern = '(?s)\s*<link href="<\?php echo htmlspecialchars\(\$bp\); \?>/public/assets/vendor/bootstrap/css/bootstrap\.min\.css" rel="stylesheet">.*?<link href="<\?php echo htmlspecialchars\(\$bp\); \?>/public/assets/css/components/page-head\.css" rel="stylesheet">'
    $matches = [regex]::Matches($current, $headPattern)
    if ($matches.Count -ne 1) {
        throw ('[A5.1][HEADER] Expected one legacy stylesheet block; found ' + $matches.Count + '. No file was changed.')
    }

    $next = [regex]::Replace($current, $headPattern, [Environment]::NewLine + $headBlock.Trim() + [Environment]::NewLine, 1)
    $next = $next.Replace('<meta name="theme-color" content="#0f172a">', '<meta name="theme-color" content="#0A0A0A">')

    if ($next -ceq $current) {
        throw '[A5.1][HEADER] No effective change was generated. No file was changed.'
    }

    $backupPath = $headerPath + '.bak-' + $stamp
    Copy-Item -LiteralPath $headerPath -Destination $backupPath -Force
    Write-Host ('[A5.1][BACKUP] ' + $backupPath)

    [System.IO.File]::WriteAllText($headerPath, $next, $utf8NoBom)
    Write-Host '[A5.1][WRITE] src\Views\layouts\header.php'

    if (-not (Test-Path -LiteralPath $phpPath)) {
        throw ('[A5.1][LINT] PHP executable not found: ' + $phpPath)
    }

    & $phpPath -l $headerPath
    if ($LASTEXITCODE -ne 0) {
        throw '[A5.1][LINT] Failed: src\Views\layouts\header.php'
    }

    Write-Host '[A5.1][LINT] OK: src\Views\layouts\header.php'
    Write-Host '[A5.1][DONE] Header stylesheet order refactored successfully.'
}
catch {
    Write-Host ('[A5.1][ERROR] ' + $_.Exception.Message)
    if ($backupPath -and (Test-Path -LiteralPath $backupPath)) {
        Copy-Item -LiteralPath $backupPath -Destination $headerPath -Force
        Write-Host '[A5.1][ROLLBACK] Restored src\Views\layouts\header.php'
    }
    exit 1
}
