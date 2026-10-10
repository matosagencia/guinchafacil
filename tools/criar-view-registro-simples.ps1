# tools/criar-view-registro-simples.ps1
# Cria src/Views/auth/registro_simples.php (view unificada 3 campos).
# ASCII-only. Faixa B. Data: 2026-10-09.

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$dir = Join-Path $Repositorio 'src\Views\auth'
if (-not (Test-Path -LiteralPath $dir)) { Write-Host "[ERRO] $dir"; exit 1 }

$arquivo = Join-Path $dir 'registro_simples.php'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

$raw = @'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$tipo = in_array(($tipo ?? ''), ['cliente','guincho','oficina'], true) ? $tipo : 'cliente';

$cfg = [
    'cliente' => [
        'eyebrow'  => 'Para clientes',
        'title'    => 'Criar conta gr{{A_ACUTE}}tis',
        'subtitle' => 'S{{O_ACUTE}} precisamos de 3 dados. CPF e endere{{C_CED}}o ficam pro pagamento.',
        'accent'   => '#2fb34a',
        'accent2'  => '#1f8a36',
        'home'     => '/',
        'home_txt' => 'P{{A_ACUTE}}gina inicial',
    ],
    'guincho' => [
        'eyebrow'  => 'Para guinchos',
        'title'    => 'Criar conta de guincho',
        'subtitle' => 'Comece simples. Voc{{E_ACUTE}} completa ve{{I_ACUTE}}culo e documenta{{C_CED}}{{A_TILDE}}o no painel.',
        'accent'   => '#2fb34a',
        'accent2'  => '#1f8a36',
        'home'     => '/parceiros/guinchos',
        'home_txt' => 'Programa de parceiros',
    ],
    'oficina' => [
        'eyebrow'  => 'Para oficinas',
        'title'    => 'Criar conta de oficina',
        'subtitle' => 'Comece simples. Voc{{E_ACUTE}} completa CNPJ e endere{{C_CED}}o no painel.',
        'accent'   => '#f0a83b',
        'accent2'  => '#b9670c',
        'home'     => '/parceiros/oficinas',
        'home_txt' => 'Programa de parceiros',
    ],
];
$c = $cfg[$tipo];
$action = $bp . '/registro/simples/' . $tipo;
?>
<!doctype html><html lang="pt-BR"><head>
<?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">
<title><?php echo htmlspecialchars($c['title']); ?> | GuinchaF{{A_ACUTE}}cil</title>
<meta name="robots" content="noindex,follow">
<style>
:root{--acc:<?php echo $c['accent']; ?>;--acc2:<?php echo $c['accent2']; ?>}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:#07180b;color:#e8fcea;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:32px 16px}
.reg-top{width:100%;max-width:520px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.reg-brand{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;font-weight:800;font-size:1.05rem}
.reg-brand img{width:36px;height:36px;border-radius:10px}
.reg-brand span{color:var(--acc)}
.reg-login{font-size:.86rem;color:rgba(232,252,234,.7);text-decoration:none;border:1px solid rgba(255,255,255,.15);padding:8px 14px;border-radius:10px}
.reg-login:hover{background:rgba(255,255,255,.04);color:#fff}
.reg-card{width:100%;max-width:520px;background:#fff;border-radius:18px;padding:32px 28px;box-shadow:0 20px 60px rgba(0,0,0,.45);color:#14201a}
.reg-eyebrow{font-size:.74rem;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:var(--acc2);margin:0 0 6px}
.reg-h1{font-size:1.5rem;font-weight:800;margin:0 0 6px;color:#14201a}
.reg-sub{color:#5f6f66;font-size:.92rem;margin:0 0 22px}
.reg-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.reg-field label{font-size:.82rem;font-weight:700;color:#3d4a42;letter-spacing:.02em}
.reg-field input{width:100%;padding:14px 16px;border:1px solid #d9e2dc;border-radius:12px;font-size:1rem;color:#14201a;background:#fff;outline:none;transition:border-color .15s,box-shadow .15s}
.reg-field input:focus{border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 20%,transparent)}
.reg-btn{width:100%;padding:15px;border:0;border-radius:12px;background:linear-gradient(135deg,var(--acc),var(--acc2));color:#fff;font-weight:800;font-size:1rem;cursor:pointer;box-shadow:0 8px 20px color-mix(in srgb,var(--acc) 35%,transparent);transition:transform .1s}
.reg-btn:hover{transform:translateY(-1px)}
.reg-btn:disabled{opacity:.55;transform:none;cursor:progress}
.reg-or{display:flex;align-items:center;gap:12px;color:#8a978f;font-size:.8rem;margin:20px 0;text-transform:uppercase;letter-spacing:.12em}
.reg-or:before,.reg-or:after{content:"";flex:1;height:1px;background:#e4ebe6}
.reg-google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px;border:1px solid #d9e2dc;border-radius:12px;background:#fff;color:#3c4043;font-weight:700;font-size:.95rem;text-decoration:none}
.reg-google:hover{background:#f7f9f8}
.reg-google svg{width:20px;height:20px}
.reg-foot{margin-top:20px;font-size:.82rem;color:#8a978f;text-align:center}
.reg-foot a{color:var(--acc2);font-weight:700;text-decoration:none}
.reg-alert{background:#fef3f2;color:#a12722;border:1px solid #fdd7d3;padding:12px 14px;border-radius:10px;margin-bottom:16px;font-size:.88rem}
</style>
</head><body>
<div class="reg-top">
  <a class="reg-brand" href="<?php echo htmlspecialchars($bp); ?>/"><img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-48.png" alt="GuinchaF{{A_ACUTE}}cil"><div>Guincha<span>F{{A_ACUTE}}cil</span></div></a>
  <a class="reg-login" href="<?php echo htmlspecialchars($bp); ?>/login<?php echo !empty($retorno) && $retorno !== '/' ? '?retorno=' . rawurlencode($retorno) : ''; ?>"><i class="fas fa-right-to-bracket"></i> J{{A_ACUTE}} tenho conta</a>
</div>
<div class="reg-card">
  <?php if (!empty($flash)): ?><div class="reg-alert"><?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?></div><?php endif; ?>
  <p class="reg-eyebrow"><?php echo htmlspecialchars($c['eyebrow']); ?></p>
  <h1 class="reg-h1"><?php echo htmlspecialchars($c['title']); ?></h1>
  <p class="reg-sub"><?php echo htmlspecialchars($c['subtitle']); ?></p>

  <form method="POST" action="<?php echo htmlspecialchars($action); ?>" novalidate autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)($csrf_token ?? '')); ?>">
    <input type="hidden" name="retorno" value="<?php echo htmlspecialchars((string)($retorno ?? '/'), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">

    <div class="reg-field">
      <label for="rs_nome">Nome completo *</label>
      <input type="text" id="rs_nome" name="nome" required minlength="3" maxlength="120" autocomplete="name" placeholder="Maria Silva">
    </div>
    <div class="reg-field">
      <label for="rs_tel">WhatsApp *</label>
      <input type="tel" id="rs_tel" name="telefone" required autocomplete="tel" inputmode="tel" placeholder="(21) 99999-9999">
    </div>
    <div class="reg-field">
      <label for="rs_email">E-mail *</label>
      <input type="email" id="rs_email" name="email" required autocomplete="email" placeholder="voce@email.com">
    </div>

    <button class="reg-btn" type="submit" id="rs_btn"><i class="fas fa-arrow-right"></i> Continuar</button>
  </form>

  <div class="reg-or">ou</div>
  <a class="reg-google" href="<?php echo htmlspecialchars($bp); ?>/auth/google?retorno=<?php echo rawurlencode($retorno ?? '/'); ?>">
    <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
    Continuar com Google
  </a>

  <p class="reg-foot">Ao continuar, voc{{E_ACUTE}} concorda com os <a href="<?php echo htmlspecialchars($bp); ?>/termos-servico.php" target="_blank" rel="noopener">Termos</a> e a <a href="<?php echo htmlspecialchars($bp); ?>/politica-privacidade.php" target="_blank" rel="noopener">Privacidade</a>.</p>
</div>
<script<?php echo csp_script_nonce_attr(); ?>>
(function(){
  var tel = document.getElementById('rs_tel');
  if (!tel) return;
  tel.addEventListener('input', function(){
    var d = tel.value.replace(/\D/g,'').slice(0,11);
    if (d.length <= 10) tel.value = d.replace(/^(\d{2})(\d{0,4})(\d{0,4}).*$/, function(_,a,b,c){ return '('+a+')'+ (b? ' '+b : '') + (c? '-'+c : ''); });
    else tel.value = d.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/, function(_,a,b,c){ return '('+a+') '+b+ (c? '-'+c : ''); });
  });
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
}
foreach ($k in $acc.Keys) { $raw = $raw.Replace($k, $acc[$k]) }

[System.IO.File]::WriteAllText($arquivo, $raw, $utf8NoBom)
Write-Host "[WRITE] $arquivo" -ForegroundColor Green

& 'C:\xampp\php\php.exe' -l $arquivo
if ($LASTEXITCODE -ne 0) { Write-Host "[ERRO] php -l falhou. Removendo." -ForegroundColor Red; Remove-Item -LiteralPath $arquivo -Force; exit 1 }
Write-Host "[OK] registro_simples.php criado e validado." -ForegroundColor Green