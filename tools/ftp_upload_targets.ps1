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

$targets = @(
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

function Request([string]$uri, [string]$method) {
  $r = [Net.FtpWebRequest]::Create($uri)
  $r.Method = $method
  $r.Credentials = $cred
  $r.UsePassive = $true
  $r.UseBinary = $true
  $r.KeepAlive = $false
  $r.Timeout = 30000
  $r.ReadWriteTimeout = 30000
  return $r
}

function Ensure-RemoteDir([string]$relativeDir) {
  if ([string]::IsNullOrWhiteSpace($relativeDir)) { return }
  $segments = $relativeDir -split '/'
  $current = ''
  foreach ($segment in $segments) {
    if ([string]::IsNullOrWhiteSpace($segment)) { continue }
    $current = if ($current) { "$current/$segment" } else { $segment }
    try {
      $resp = (Request "$base/$current" ([Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse()
      $resp.Close()
    } catch {}
  }
}

function Upload-File([string]$relativePath) {
  $fullPath = Join-Path $root $relativePath
  $parentDir = Split-Path $relativePath -Parent
  Ensure-RemoteDir $parentDir
  $request = Request "$base/$relativePath" ([Net.WebRequestMethods+Ftp]::UploadFile)
  $input = $null
  $output = $null
  try {
    $input = [IO.File]::OpenRead($fullPath)
    $output = $request.GetRequestStream()
    $input.CopyTo($output)
    $output.Close()
    $input.Close()
    $response = $request.GetResponse()
    $response.Close()
    Write-Output "uploaded $relativePath"
  } catch {
    if ($input) { $input.Close() }
    if ($output) { $output.Close() }
    throw "Upload failed for $relativePath : $($_.Exception.Message)"
  }
}

foreach ($target in $targets) {
  if (Test-Path (Join-Path $root $target) -PathType Leaf) {
    Upload-File $target
    continue
  }

  if (Test-Path (Join-Path $root $target) -PathType Container) {
    Get-ChildItem (Join-Path $root $target) -File -Recurse -Force | ForEach-Object {
      $relative = $_.FullName.Substring($root.Length).TrimStart([char[]]@('\','/')).Replace('\','/')
      Upload-File $relative
    }
    continue
  }

  Write-Warning "missing target $target"
}

Write-Output 'FTP_TARGET_UPLOAD_DONE'
