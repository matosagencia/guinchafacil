$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$base = 'ftp://158.69.176.63/public_html/'
$cred = New-Object System.Net.NetworkCredential($env:GUINCHA_FTP_USER, $env:GUINCHA_FTP_PASS)
$skipDirs = @('.git','qa','tests','doc','logs','files','storage','node_modules')
$skipFiles = @('.env','.env.local','.env.example','id_rsa','id_rsa.pub','known_hosts','.ftpquota','error_log')
function ShouldSkip([string]$path) {
  $rel = $path.Substring($root.Length).TrimStart([char[]]@('\','/'))
  $parts = $rel -split '[\\/]'
  if (($parts | Where-Object { $skipDirs -contains $_ }).Count -gt 0) { return $true }
  return $skipFiles -contains [IO.Path]::GetFileName($path)
}
function Request([string]$uri, [string]$method) {
  $r = [Net.FtpWebRequest]::Create($uri)
  $r.Method = $method; $r.Credentials = $cred; $r.UsePassive = $true; $r.UseBinary = $true; $r.KeepAlive = $false
  return $r
}
$local = @{}
Get-ChildItem $root -File -Recurse -Force | Where-Object { -not (ShouldSkip $_.FullName) } | ForEach-Object {
  $rel = $_.FullName.Substring($root.Length).TrimStart([char[]]@('\','/')).Replace('\','/')
  $local[$rel] = $_.Length
}
$remote = @{}
function Walk([string]$prefix) {
  try {
    $req = Request ($base + $prefix) ([Net.WebRequestMethods+Ftp]::ListDirectoryDetails)
    $resp = $req.GetResponse(); $reader = New-Object IO.StreamReader($resp.GetResponseStream()); $text = $reader.ReadToEnd(); $reader.Close(); $resp.Close()
  } catch { return }
  foreach ($line in ($text -split "`r?`n")) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    $m = [regex]::Match($line, '^(?<type>[d-])[^\s]*\s+\S+\s+\S+\s+(?<size>\d+)\s+\w+\s+\d+\s+[\d:]+\s+(?<name>.+)$')
    if (-not $m.Success) { continue }
    $name = $m.Groups['name'].Value.Trim(); if ($name -in @('.','..')) { continue }
    $rel = ($prefix + '/' + $name).Trim('/')
    if ($m.Groups['type'].Value -eq 'd') { if ($skipDirs -notcontains $name) { Walk $rel } }
    else { $remote[$rel] = [int64]$m.Groups['size'].Value }
  }
}
Walk ''
$missing = @($local.Keys | Where-Object { -not $remote.ContainsKey($_) })
$different = @($local.Keys | Where-Object { $remote.ContainsKey($_) -and $local[$_] -ne $remote[$_] })
"LOCAL=$($local.Count) REMOTE=$($remote.Count) MISSING=$($missing.Count) DIFFERENT=$($different.Count)"
if ($missing) { '-- MISSING'; $missing | Sort-Object | Select-Object -First 80 }
if ($different) { '-- SIZE_DIFFERENT'; $different | Sort-Object | Select-Object -First 80 | ForEach-Object { "$_ local=$($local[$_]) remote=$($remote[$_])" } }
