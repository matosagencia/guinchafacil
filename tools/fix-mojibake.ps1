cd C:\xampp\htdocs\guinchafacil

$p = 'tools\fix-mojibake.ps1'
$c = [System.IO.File]::ReadAllText($p, [System.Text.Encoding]::UTF8)
$c = $c.Replace([string][char]0x2014, '-')   # em-dash -> hifen
$c = $c.Replace([string][char]0x2013, '-')   # en-dash -> hifen
$c = $c.Replace([string][char]0x201C, '"')   # curly open  -> reto
$c = $c.Replace([string][char]0x201D, '"')   # curly close -> reto
[System.IO.File]::WriteAllText($p, $c, (New-Object System.Text.UTF8Encoding($false)))
Write-Host "[OK] fix-mojibake.ps1 normalizado."