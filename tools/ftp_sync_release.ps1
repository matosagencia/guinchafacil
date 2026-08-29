$root=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$base='ftp://158.69.176.63/public_html'
$cred=New-Object System.Net.NetworkCredential($env:GUINCHA_FTP_USER,$env:GUINCHA_FTP_PASS)
$skipDirs=@('.git','qa','tests','doc','logs','files','storage','node_modules')
$skipFiles=@('.env','.env.local','.env.example','id_rsa','id_rsa.pub','id_rsa.ppk','known_hosts','.ftpquota','error_log')
function Skip([string]$p){$rel=$p.Substring($root.Length).TrimStart([char[]]@('\','/'));$parts=$rel -split '[\\/]';if(($parts|Where-Object{$skipDirs -contains $_}).Count -gt 0){return $true};return $skipFiles -contains [IO.Path]::GetFileName($p)}
function Req([string]$uri,[string]$method){$r=[Net.FtpWebRequest]::Create($uri);$r.Method=$method;$r.Credentials=$cred;$r.UsePassive=$true;$r.UseBinary=$true;$r.KeepAlive=$false;return $r}
$dirs=Get-ChildItem $root -Directory -Recurse -Force|Where-Object{-not(Skip $_.FullName)}|Sort-Object FullName
foreach($d in $dirs){$rel=$d.FullName.Substring($root.Length).TrimStart([char[]]@('\','/')).Replace('\','/');try{$x=(Req "$base/$rel" ([Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse();$x.Close()}catch{}}
$files=Get-ChildItem $root -File -Recurse -Force|Where-Object{-not(Skip $_.FullName)}
$i=0;$fail=0
foreach($f in $files){$rel=$f.FullName.Substring($root.Length).TrimStart([char[]]@('\','/')).Replace('\','/');$req=Req "$base/$rel" ([Net.WebRequestMethods+Ftp]::UploadFile);$in=$null;$out=$null;try{$in=[IO.File]::OpenRead($f.FullName);$out=$req.GetRequestStream();$in.CopyTo($out);$in.Close();$out.Close();$res=$req.GetResponse();$res.Close();$i++}catch{$fail++;if($in){$in.Close()};if($out){$out.Close()};Write-Warning "$rel : $($_.Exception.Message)"};if(($i%100)-eq 0){Write-Output "enviados=$i falhas=$fail"}}
Write-Output "TOTAL_ENVIADOS=$i FALHAS=$fail"
