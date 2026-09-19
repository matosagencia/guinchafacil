$root=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$envFiles = @((Join-Path $root '.env.local'), (Join-Path $root '.env'))
function Get-ConfigValue([string]$name, [string]$fallback = '') {
  $value = [Environment]::GetEnvironmentVariable($name)
  if (-not [string]::IsNullOrWhiteSpace($value)) { return $value.Trim() }
  $escaped = [regex]::Escape($name)
  foreach ($file in $envFiles) {
    if (-not (Test-Path $file -PathType Leaf)) { continue }
    foreach ($line in Get-Content -LiteralPath $file) {
      if ($line -match "^\s*$escaped\s*=\s*(.*)\s*$") {
        return $matches[1].Trim().Trim('"').Trim("'")
      }
    }
  }
  return $fallback
}
$ftpHost = Get-ConfigValue 'FTP_HOST' '158.69.176.63'
$base = if ($ftpHost -match '^[a-z]+://') { $ftpHost.TrimEnd('/') } else { "ftp://$ftpHost" }
$ftpUser = Get-ConfigValue 'FTP_ACCOUNT' (Get-ConfigValue 'GUINCHA_FTP_USER')
$ftpPass = Get-ConfigValue 'FTP_PASS' (Get-ConfigValue 'GUINCHA_FTP_PASS')
if ([string]::IsNullOrWhiteSpace($ftpUser) -or [string]::IsNullOrWhiteSpace($ftpPass)) {
  throw 'FTP credentials are not configured. Set FTP_ACCOUNT and FTP_PASS in the environment or .env file.'
}
$cred = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
$skipDirs=@('.git','qa','tests','doc','logs','files','storage','node_modules')
$skipFiles=@('.env','.env.local','.env.example','id_rsa','id_rsa.pub','id_rsa.ppk','known_hosts','.ftpquota','error_log')
function Skip([string]$p){$rel=$p.Substring($root.Length).TrimStart([char[]]@('\','/'));$parts=$rel -split '[\\/]';if(($parts|Where-Object{$skipDirs -contains $_}).Count -gt 0){return $true};return $skipFiles -contains [IO.Path]::GetFileName($p)}
$requestTimeoutMs = 30000
function Req([string]$uri,[string]$method){
  $r=[Net.FtpWebRequest]::Create($uri)
  $r.Method=$method
  $r.Credentials=$cred
  $r.UsePassive=$true
  $r.UseBinary=$true
  $r.KeepAlive=$false
  $r.Timeout=$requestTimeoutMs
  $r.ReadWriteTimeout=$requestTimeoutMs
  return $r
}
$OutputEncoding = [Console]::OutputEncoding
Write-Output "FTP_BASE=$base"
$dirs=Get-ChildItem $root -Directory -Recurse -Force|Where-Object{-not(Skip $_.FullName)}|Sort-Object FullName
foreach($d in $dirs){$rel=$d.FullName.Substring($root.Length).TrimStart([char[]]@('\','/')).Replace('\','/');try{$x=(Req "$base/$rel" ([Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse();$x.Close()}catch{}}
$files=Get-ChildItem $root -File -Recurse -Force|Where-Object{-not(Skip $_.FullName)}
$i=0;$fail=0
foreach($f in $files){$rel=$f.FullName.Substring($root.Length).TrimStart([char[]]@('\','/')).Replace('\','/');$req=Req "$base/$rel" ([Net.WebRequestMethods+Ftp]::UploadFile);$in=$null;$out=$null;try{$in=[IO.File]::OpenRead($f.FullName);$out=$req.GetRequestStream();$in.CopyTo($out);$in.Close();$out.Close();$res=$req.GetResponse();$res.Close();$i++}catch{$fail++;if($in){$in.Close()};if($out){$out.Close()};Write-Warning "$rel : $($_.Exception.Message)"};if(($i%100)-eq 0){Write-Output "enviados=$i falhas=$fail"}}
Write-Output "TOTAL_ENVIADOS=$i FALHAS=$fail"
