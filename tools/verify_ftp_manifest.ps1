$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
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
$fallbackTargets = @(
  'config.php',
  'index.php',
  'public/assets/js/admin-order-workspace.js',
  'public/assets/js/admin-territorio-metas.js',
  'public/assets/js/especialista-localizacao.js',
  'public/assets/js/push-notifications.js',
  'public/manifest.json',
  'public/sw.js',
  'src/Controllers/AdminController.php',
  'src/Controllers/AdminFinanceAttributionController.php',
  'src/Controllers/EspecialistaController.php',
  'src/Controllers/PushActionController.php',
  'src/Controllers/PushSubscriptionController.php',
  'src/Models/Cidade.php',
  'src/Models/PushSubscription.php',
  'src/Models/Usuario.php',
  'src/Services/EspecialistaDispatchService.php',
  'src/Services/POR/PorThresholds.php',
  'src/Services/PushActionTokenService.php',
  'src/Services/PushNotificationService.php',
  'src/Services/PushVapidService.php',
  'src/Services/TelegramNotificationService.php',
  'src/Services/Payment/PagamentoAprovacaoService.php',
  'src/Services/Pedido/PedidoTransitionService.php',
  'src/Services/Security/ConfigSecurityService.php',
  'src/Views/admin/central_operacional.php',
  'src/Views/admin/cidades.php',
  'src/Views/admin/dashboard.php',
  'src/Views/admin/configuracoes.php',
  'src/Views/admin/marketing_central.php',
  'src/Views/admin/marketing_prospeccao.php',
  'src/Views/admin/pedido_trilha.php',
  'src/Views/admin/pedidodetalhe.php',
  'src/Views/admin/usuarioedit.php',
  'src/Views/cliente/pedidonovo.php',
  'src/Views/components/admin_nav_operacional.php',
  'src/Views/especialista/dashboard.php',
  'src/Views/guincho/dashboard.php',
  'src/Views/layouts/header.php',
  'install/migrate.php',
  'install/migration_push_subscriptions_v1.sql',
  'install/migration_telegram_fallback_v1.sql',
  'install/migration_prospeccao_parceiros_v1.sql',
  'install/migration_prospeccao_parceiros_v2_historico.sql'
)
if ($remote.Count -eq 0) {
  Write-Output 'REMOTE_LISTING_UNAVAILABLE_FALLING_BACK_TO_TARGETS'
  foreach ($target in $fallbackTargets) {
    $uri = ($base.TrimEnd('/') + '/' + $target)
    try {
      $req = Request $uri ([Net.WebRequestMethods+Ftp]::GetFileSize)
      $resp = $req.GetResponse()
      $resp.Close()
      $remote[$target] = 1
    } catch {}
  }
}
$missing = @($local.Keys | Where-Object { -not $remote.ContainsKey($_) })
$different = @($local.Keys | Where-Object { $remote.ContainsKey($_) -and $local[$_] -ne $remote[$_] })
"LOCAL=$($local.Count) REMOTE=$($remote.Count) MISSING=$($missing.Count) DIFFERENT=$($different.Count)"
if ($missing) { '-- MISSING'; $missing | Sort-Object | Select-Object -First 80 }
if ($different) { '-- SIZE_DIFFERENT'; $different | Sort-Object | Select-Object -First 80 | ForEach-Object { "$_ local=$($local[$_]) remote=$($remote[$_])" } }
