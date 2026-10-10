# tools/fix-login-split.ps1
# Reescreve src/Views/auth/login.php com layout split no tema cliente (branco/verde).
# ASCII-only. Faixa B.

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$dir = Join-Path $Repositorio 'src\Views\auth'
if (-not (Test-Path -LiteralPath $dir)) { Write-Host "[ERRO] $dir"; exit 1 }

$arquivo = Join-Path $dir 'login.php'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

if (Test-Path -LiteralPath $arquivo) {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $bak = "$arquivo.bak-split-$stamp"
    Copy-Item -LiteralPath $arquivo -Destination $bak -Force
    Write-Host "[BACKUP] $bak" -ForegroundColor Green
}

$raw = @'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$retorno = $retorno ?? '/';
$vantagens = [
    ['fa-clock','Socorro 24h','Atendimento a qualquer hora, todos os dias.'],
    ['fa-tag','Pre{{C_CED}}o antes do aceite','Voc{{E_ACUTE}} v{{E_ACUTE}} o valor antes de confirmar o pedido.'],
    ['fa-map-location-dot','Rastreio em tempo real','Acompanhe seu guincho at{{E_ACUTE}} a chegada.'],
    ['fa-shield-halved','Profissionais verificados','Todos passam por an{{A_ACUTE}}lise e avalia{{C_CED}}{{A_TILDE}}o.'],
    ['fa-wallet','Sem mensalidade','Voc{{E_ACUTE}} paga s{{O_ACUTE}} o servi{{C_CED}}o que pedir.'],
];
?>
<!doctype html><html lang="pt-BR"><head>
<?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">
<title>Entrar | GuinchaF{{A_ACUTE}}cil</title>
<meta name="robots" content="noindex,follow">
<style>
:root{--acc:#1f8a36;--acc2:#2fb34a;--hero-bg:linear-gradient(145deg,#ffffff 0%,#d5f5dd 45%,#4ccf6a 100%);--hero-fg:#0f3d1a;--hero-muted:rgba(15,61,26,.72);--badge-bg:rgba(47,179,74,.15);--badge-bd:rgba(47,179,74,.35);--badge-fg:#0f5c25;--form-bg:#f4f8f5;--card-bg:#ffffff;--text-main:#14201a;--text-muted:#5f6f66;--input-bd:#d9e2dc;--input-bg:#ffffff;--input-fg:#14201a}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--form-bg);color:var(--text-main)}
.reg-wrapper{min-height:100vh;display:grid;grid-template-columns:1fr 1fr}
@media(max-width:991px){.reg-wrapper{grid-template-columns:1fr}.reg-hero{display:none!important}}
.reg-hero{position:relative;overflow:hidden;background:var(--hero-bg);padding:3rem;color:var(--hero-fg);display:flex;flex-direction:column;justify-content:center}
.reg-hero:before{content:"";position:absolute;inset:0;background:radial-gradient(ellipse at 20% 85%,rgba(255,255,255,.08),transparent 60%),radial-gradient(ellipse at 85% 15%,rgba(255,255,255,.06),transparent 55%);pointer-events:none}
.hero-content{position:relative;z-index:1;max-width:560px}
.hero-logo{display:flex;align-items:center;gap:.75rem;margin-bottom:2rem}
.hero-logo img{width:48px;height:48px;border-radius:12px}
.hero-logo .brand{font-size:1.4rem;font-weight:800}
.hero-logo .brand span{color:var(--acc2)}
.hero-badge{display:inline-flex;align-items:center;gap:.5rem;padding:.4rem .9rem;border-radius:20px;background:var(--badge-bg);border:1px solid var(--badge-bd);color:var(--badge-fg);font-size:.76rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:1.1rem}
.hero-title{font-size:2rem;font-weight:800;line-height:1.18;margin:0 0 .9rem;color:var(--hero-fg)}
.hero-title span{color:var(--acc2)}
.hero-lead{font-size:.98rem;line-height:1.65;color:var(--hero-muted);margin:0 0 1.8rem}
.hero-vantagens{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.85rem}
.hero-vantagens li{display:flex;gap:.8rem;align-items:flex-start}
.hero-vantagens li i{width:34px;height:34px;flex-shrink:0;border-radius:9px;background:var(--badge-bg);border:1px solid var(--badge-bd);color:var(--badge-fg);display:flex;align-items:center;justify-content:center;font-size:.9rem}
.hero-vantagens li b{display:block;font-size:.94rem;font-weight:700;color:var(--hero-fg);margin-bottom:2px}
.hero-vantagens li small{font-size:.82rem;color:var(--hero-muted);line-height:1.5}
.reg-form-side{background:var(--form-bg);display:flex;flex-direction:column;overflow-y:auto}
.reg-form-nav{padding:1.1rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--input-bd)}
.reg-form-nav .logo-sm{font-size:1.05rem;font-weight:700;color:var(--text-main);text-decoration:none}
.reg-form-nav .logo-sm span{color:var(--acc2)}
.reg-form-nav .btn-create{font-size:.82rem;color:var(--text-muted);text-decoration:none;border:1px solid var(--input-bd);padding:.45rem .85rem;border-radius:9px}
.reg-form-nav .btn-create:hover{border-color:var(--acc2);color:var(--text-main)}
.reg-form-body{flex:1;padding:2.4rem 2rem;max-width:520px;width:100%;margin:0 auto}
.reg-eyebrow{font-size:.72rem;font-weight:800;letter-spacing:1.3px;text-transform:uppercase;color:var(--acc2);margin:0 0 .4rem}
.reg-h1{font-size:1.5rem;font-weight:800;margin:0 0 .35rem;color:var(--text-main)}
.reg-sub{color:var(--text-muted);font-size:.9rem;margin:0 0 1.6rem}
.reg-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.reg-field label{font-size:.82rem;font-weight:700;color:var(--text-main)}
.reg-field input{width:100%;padding:13px 15px;border:1px solid var(--input-bd);border-radius:11px;font-size:1rem;color:var(--input-fg);background:var(--input-bg);outline:none;transition:border-color .15s,box-shadow .15s}
.reg-field input:focus{border-color:var(--acc2);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc2) 22%,transparent)}
.reg-field input::placeholder{color:var(--text-muted);opacity:.65}
.reg-forgot{display:block;text-align:right;font-size:.8rem;color:var(--acc);margin:-4px 0 12px;text-decoration:none}
.reg-forgot:hover{text-decoration:underline}
.reg-btn{width:100%;padding:14px;border:0;border-radius:11px;background:linear-gradient(135deg,var(--acc2),var(--acc));color:#fff;font-weight:800;font-size:1rem;cursor:pointer;box-shadow:0 8px 22px color-mix(in srgb,var(--acc2) 30%,transparent);transition:transform .1s}
.reg-btn:hover{transform:translateY(-1px)}
.reg-or{display:flex;align-items:center;gap:12px;color:var(--text-muted);font-size:.76rem;margin:18px 0;text-transform:uppercase;letter-spacing:.14em}
.reg-or:before,.reg-or:after{content:"";flex:1;height:1px;background:var(--input-bd)}
.reg-google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border:1px solid var(--input-bd);border-radius:11px;background:var(--card-bg);color:var(--text-main);font-weight:700;font-size:.94rem;text-decoration:none}
.reg-google:hover{background:#f7f9f8}
.reg-google svg{width:20px;height:20px}
.reg-foot{margin-top:20px;font-size:.8rem;color:var(--text-muted);text-align:center}
.reg-foot a{color:var(--acc);font-weight:700;text-decoration:none}
.reg-alert{background:#fef3f2;color:#a12722;border:1px solid #fdd7d3;padding:12px 14px;border-radius:10px;margin-bottom:16px;font-size:.88rem}
</style>
</head><body class="cliente login-publico">
<div class="reg-wrapper">

  <section class="reg-hero" aria-label="Vantagens">
    <div class="hero-content">
      <div class="hero-logo">
        <img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-48.png" alt="GuinchaF{{A_ACUTE}}cil">
        <div class="brand">Guincha<span>F{{A_ACUTE}}cil</span></div>
      </div>
      <div class="hero-badge"><i class="fas fa-bolt"></i> Bem-vindo de volta</div>
      <h1 class="hero-title">Entre para continuar<br><span>seu atendimento.</span></h1>
      <p class="hero-lead">Sua conta GuinchaF{{A_ACUTE}}cil {{E_ACUTE}} gratuita e leva 30 segundos para criar. Continue de onde parou.</p>
      <ul class="hero-vantagens">
        <?php foreach ($vantagens as $v): ?>
        <li>
          <i class="fas <?php echo htmlspecialchars($v[0]); ?>"></i>
          <div>
            <b><?php echo htmlspecialchars($v[1]); ?></b>
            <small><?php echo htmlspecialchars($v[2]); ?></small>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="reg-form-side">
    <nav class="reg-form-nav">
      <a class="logo-sm" href="<?php echo htmlspecialchars($bp); ?>/">Guincha<span>F{{A_ACUTE}}cil</span></a>
      <a class="btn-create" href="<?php echo htmlspecialchars($bp); ?>/registro/cliente"><i class="fas fa-user-plus"></i> Criar conta</a>
    </nav>
    <div class="reg-form-body">
      <?php if (!empty($flash)): ?><div class="reg-alert"><?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?></div><?php endif; ?>
      <p class="reg-eyebrow">Acessar conta</p>
      <h1 class="reg-h1">Entrar</h1>
      <p class="reg-sub">Use seu e-mail e senha, ou continue com o Google.</p>

      <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/login" novalidate autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)($csrf_token ?? '')); ?>">
        <input type="hidden" name="retorno" value="<?php echo htmlspecialchars((string)$retorno, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="reg-field">
          <label for="lg_email">E-mail</label>
          <input type="email" id="lg_email" name="email" required autocomplete="email" placeholder="voce@email.com">
        </div>
        <div class="reg-field">
          <label for="lg_senha">Senha</label>
          <input type="password" id="lg_senha" name="senha" required autocomplete="current-password" placeholder="Sua senha">
        </div>
        <a class="reg-forgot" href="<?php echo htmlspecialchars($bp); ?>/senha/esqueceu">Esqueci minha senha</a>

        <button class="reg-btn" type="submit"><i class="fas fa-right-to-bracket"></i> Entrar</button>
      </form>

      <div class="reg-or">ou</div>
      <a class="reg-google" href="<?php echo htmlspecialchars($bp); ?>/auth/google?retorno=<?php echo rawurlencode((string)$retorno); ?>">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
        Continuar com Google
      </a>

      <p class="reg-foot">
        Ainda n{{A_TILDE}}o tem conta?
        <a href="<?php echo htmlspecialchars($bp); ?>/registro/cliente">Criar conta gr{{A_ACUTE}}tis</a>
        &nbsp;&middot;&nbsp;
        <a href="<?php echo htmlspecialchars($bp); ?>/parceiros">Sou parceiro</a>
      </p>
    </div>
  </section>

</div>
<script<?php echo csp_script_nonce_attr(); ?>>
(function(){
  var form = document.querySelector('.reg-form-side form');
  if (form) {
    form.addEventListener('submit', function(){
      var b = form.querySelector('button[type="submit"]');
      if (b) { b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...'; }
    });
  }
})();
</script>
</body></html>
'@

$acc = @{
    '{{A_ACUTE}}' = [string][char]0x00E1
    '{{A_TILDE}}' = [string][char]0x00E3
    '{{C_CED}}'   = [string][char]0x00E7
    '{{E_ACUTE}}' = [string][char]0x00E9
    '{{I_ACUTE}}' = [string][char]0x00ED
    '{{O_ACUTE}}' = [string][char]0x00F3
    '{{U_ACUTE}}' = [string][char]0x00FA
}
foreach ($k in $acc.Keys) { $raw = $raw.Replace($k, $acc[$k]) }

if ($raw -match '\{\{[A-Z_]+\}\}') {
    Write-Host "[ERRO] placeholder nao substituido." -ForegroundColor Red
    exit 1
}

[System.IO.File]::WriteAllText($arquivo, $raw, $utf8NoBom)
Write-Host "[WRITE] $arquivo" -ForegroundColor Green

& 'C:\xampp\php\php.exe' -l $arquivo
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup." -ForegroundColor Red
    if ($bak) { Copy-Item -LiteralPath $bak -Destination $arquivo -Force }
    exit 1
}
Write-Host ""
Write-Host "[DONE] login.php reescrito no tema cliente (split branco/verde)." -ForegroundColor Green
if ($bak) { Write-Host "Backup: $bak" -ForegroundColor Yellow; Write-Host "Reverter: Copy-Item '$bak' '$arquivo' -Force" }