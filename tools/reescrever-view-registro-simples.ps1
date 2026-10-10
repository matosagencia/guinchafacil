# tools/reescrever-view-registro-simples.ps1
# Reescreve src/Views/auth/registro_simples.php com layout split + tema por tipo.
# ASCII-only. Faixa B. Data: 2026-10-09.

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$arquivo = Join-Path $Repositorio 'src\Views\auth\registro_simples.php'
if (-not (Test-Path -LiteralPath $arquivo)) {
    Write-Host "[AVISO] $arquivo nao existe; criando novo." -ForegroundColor Yellow
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
if (Test-Path -LiteralPath $arquivo) {
    $bak = "$arquivo.bak-split-$stamp"
    Copy-Item -LiteralPath $arquivo -Destination $bak -Force
    Write-Host "[BACKUP] $bak" -ForegroundColor Green
}

$raw = @'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$tipo = in_array(($tipo ?? ''), ['cliente','guincho','oficina'], true) ? $tipo : 'cliente';

$cfg = [
    'cliente' => [
        'eyebrow'     => 'Para quem precisa de socorro',
        'title'       => 'Sua conta em 30 segundos',
        'subtitle'    => 'Sem formul{{A_ACUTE}}rio longo. S{{O_ACUTE}} o b{{A_ACUTE}}sico pra voc{{E_ACUTE}} pedir socorro quando precisar.',
        'acc'         => '#2fb34a',
        'acc2'        => '#1f8a36',
        'acc_ring'    => 'rgba(47,179,74,.22)',
        'acc_shadow'  => 'rgba(47,179,74,.30)',
        'hero_bg'     => 'linear-gradient(135deg,#ffffff 0%,#e6f5ea 60%,#2fb34a 100%)',
        'hero_text'   => '#0a3d1a',
        'hero_muted'  => '#3d5a42',
        'back_href'   => '/',
        'back_txt'    => 'Voltar ao in{{I_ACUTE}}cio',
        'adv' => [
            ['Pre{{C_CED}}o antes de pedir', 'Veja o valor estimado antes de confirmar o atendimento.'],
            ['Atendimento 24 horas', 'Guinchos dispon{{I_ACUTE}}veis a qualquer hora, todos os dias.'],
            ['Rastreio em tempo real', 'Acompanhe o guincho no mapa at{{E_ACUTE}} chegar.'],
            ['Pagamento flex{{I_ACUTE}}vel', 'PIX, cart{{A_TILDE}}o ou dinheiro na chegada. Voc{{E_ACUTE}} escolhe.'],
            ['Sem burocracia', 'Nome, WhatsApp e e-mail. Pronto pra usar.'],
        ],
    ],
    'guincho' => [
        'eyebrow'     => 'Para empresas de guincho',
        'title'       => 'Receba chamados sem mensalidade',
        'subtitle'    => 'Comece com 3 dados. Ve{{I_ACUTE}}culo, CNH e documenta{{C_CED}}{{A_TILDE}}o voc{{E_ACUTE}} completa no painel.',
        'acc'         => '#2fb34a',
        'acc2'        => '#1f8a36',
        'acc_ring'    => 'rgba(47,179,74,.22)',
        'acc_shadow'  => 'rgba(47,179,74,.30)',
        'hero_bg'     => 'linear-gradient(135deg,#000000 0%,#0a3d1a 50%,#2fb34a 100%)',
        'hero_text'   => '#ffffff',
        'hero_muted'  => 'rgba(255,255,255,.78)',
        'back_href'   => '/parceiros/guinchos',
        'back_txt'    => 'Programa de parceiros',
        'adv' => [
            ['Zero mensalidade', 'Sem taxa de ades{{A_TILDE}}o e sem mensalidade para entrar.'],
            ['Chamados compat{{I_ACUTE}}veis', 'Voc{{E_ACUTE}} recebe pedidos da sua regi{{A_TILDE}}o e capacidade.'],
            ['Voc{{E_ACUTE}} decide quando', 'Ligue e desligue sua disponibilidade no painel.'],
            ['Repasse em D+7', 'PIX na sua chave ap{{O_ACUTE}}s a conclus{{A_TILDE}}o do atendimento.'],
            ['Sem exclusividade', 'Sua carteira e seus clientes continuam sendo seus.'],
        ],
    ],
    'oficina' => [
        'eyebrow'     => 'Para oficinas parceiras',
        'title'       => 'Encha sua agenda com clientes',
        'subtitle'    => 'Cadastro r{{A_ACUTE}}pido pra voc{{E_ACUTE}} come{{C_CED}}ar. CNPJ e endere{{C_CED}}o voc{{E_ACUTE}} completa depois, no painel.',
        'acc'         => '#f0a83b',
        'acc2'        => '#b9670c',
        'acc_ring'    => 'rgba(240,168,59,.24)',
        'acc_shadow'  => 'rgba(240,168,59,.32)',
        'hero_bg'     => 'linear-gradient(135deg,#000000 0%,#4a2400 45%,#f0a83b 100%)',
        'hero_text'   => '#ffffff',
        'hero_muted'  => 'rgba(255,235,205,.82)',
        'back_href'   => '/parceiros/oficinas',
        'back_txt'    => 'Programa de parceiros',
        'adv' => [
            ['Clientes qualificados', 'Demandas da sua regi{{A_TILDE}}o e especialidade t{{E_ACUTE}}cnica.'],
            ['Sem taxa de ades{{A_TILDE}}o', 'Entrada gratuita, sem mensalidade fixa.'],
            ['Or{{C_CED}}amento pelo painel', 'Voc{{E_ACUTE}} envia o or{{C_CED}}amento e o cliente aprova.'],
            ['Repasse via PIX', 'Em D+7 ap{{O_ACUTE}}s a conclus{{A_TILDE}}o do servi{{C_CED}}o.'],
            ['Sua agenda, seu ritmo', 'Aceite chamados s{{O_ACUTE}} quando puder atender.'],
        ],
    ],
];
$c = $cfg[$tipo];
$action = $bp . '/registro/' . $tipo;
$styleVars = '--acc:' . $c['acc'] . ';--acc2:' . $c['acc2'] . ';--acc-ring:' . $c['acc_ring'] . ';--acc-shadow:' . $c['acc_shadow'] . ';--hero-bg:' . $c['hero_bg'] . ';--hero-text:' . $c['hero_text'] . ';--hero-muted:' . $c['hero_muted'];
?>
<!doctype html><html lang="pt-BR"><head>
<?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">
<title><?php echo htmlspecialchars($c['title']); ?> | GuinchaF{{A_ACUTE}}cil</title>
<meta name="robots" content="noindex,follow">
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#0f2417;background:#f7faf8;-webkit-font-smoothing:antialiased}

.rs-wrap{min-height:100vh;display:grid;grid-template-columns:1.15fr 1fr}
@media(max-width:991px){.rs-wrap{grid-template-columns:1fr}}

.rs-hero{position:relative;overflow:hidden;background:var(--hero-bg);color:var(--hero-text);padding:56px 56px 48px;display:flex;align-items:flex-start}
@media(max-width:991px){.rs-hero{padding:40px 24px 32px}}
.rs-hero::after{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse at 15% 15%, rgba(255,255,255,.06), transparent 55%)}
.rs-hero-inner{position:relative;z-index:1;max-width:540px;width:100%}

.rs-brand{display:inline-flex;align-items:center;gap:10px;text-decoration:none;color:var(--hero-text);font-weight:800;font-size:1.1rem;margin-bottom:44px}
.rs-brand img{width:40px;height:40px;border-radius:10px}
.rs-brand em{font-style:normal;color:var(--acc)}

.rs-eyebrow{font-size:.74rem;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:var(--acc);margin:0 0 10px}
.rs-hero h1{font-size:2.15rem;line-height:1.15;margin:0 0 14px;font-weight:800;letter-spacing:-.01em}
.rs-lead{font-size:1rem;line-height:1.65;color:var(--hero-muted);margin:0 0 30px}

.rs-adv{list-style:none;padding:0;margin:0 0 32px;display:flex;flex-direction:column;gap:16px}
.rs-adv li{display:flex;gap:12px;align-items:flex-start}
.rs-adv i{color:var(--acc);font-size:1.05rem;margin-top:3px;flex:0 0 auto}
.rs-adv b{display:block;font-size:.94rem;font-weight:800;color:var(--hero-text);margin-bottom:3px}
.rs-adv span{display:block;font-size:.86rem;color:var(--hero-muted);line-height:1.55}

.rs-back{display:inline-block;font-size:.85rem;color:var(--hero-muted);text-decoration:none;border-bottom:1px dotted currentColor;padding-bottom:1px}
.rs-back:hover{color:var(--hero-text)}

.rs-form-wrap{background:#f7faf8;display:flex;align-items:center;justify-content:center;padding:40px 24px;overflow-y:auto}
@media(max-width:991px){.rs-form-wrap{padding:24px 16px}}

.rs-card{width:100%;max-width:460px;background:#fff;border-radius:20px;padding:36px 32px;box-shadow:0 20px 60px rgba(8,30,15,.08);border:1px solid #e8efe9}
@media(max-width:480px){.rs-card{padding:28px 22px}}

.rs-card-eyebrow{font-size:.72rem;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:var(--acc2);margin:0 0 6px}
.rs-card h2{font-size:1.4rem;font-weight:800;margin:0 0 6px;color:#0f2417}
.rs-card-sub{font-size:.88rem;color:#5f7a68;margin:0 0 22px;line-height:1.55}

.rs-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.rs-field label{font-size:.82rem;font-weight:700;color:#3d4a42;letter-spacing:.01em}
.rs-field input{width:100%;padding:13px 15px;border:1px solid #d9e2dc;border-radius:11px;font-size:.98rem;color:#0f2417;background:#fff;outline:none;transition:border-color .15s, box-shadow .15s}
.rs-field input:focus{border-color:var(--acc);box-shadow:0 0 0 3px var(--acc-ring)}

.rs-btn{width:100%;padding:14px;border:0;border-radius:11px;background:linear-gradient(135deg, var(--acc), var(--acc2));color:#fff;font-weight:800;font-size:.98rem;cursor:pointer;box-shadow:0 8px 22px var(--acc-shadow);transition:transform .12s, box-shadow .12s;margin-top:6px}
.rs-btn:hover{transform:translateY(-1px);box-shadow:0 10px 26px var(--acc-shadow)}
.rs-btn:disabled{opacity:.6;transform:none;cursor:progress}

.rs-or{display:flex;align-items:center;gap:12px;color:#8a978f;font-size:.72rem;margin:20px 0;text-transform:uppercase;letter-spacing:.14em;font-weight:700}
.rs-or::before,.rs-or::after{content:"";flex:1;height:1px;background:#e4ebe6}

.rs-google{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border:1px solid #d9e2dc;border-radius:11px;background:#fff;color:#3c4043;font-weight:700;font-size:.93rem;text-decoration:none;transition:background .15s, border-color .15s}
.rs-google:hover{background:#f7f9f8;border-color:#c8d4cc}
.rs-google svg{width:19px;height:19px;flex:0 0 auto}

.rs-foot{margin-top:18px;font-size:.78rem;color:#8a978f;text-align:center;line-height:1.55}
.rs-foot a{color:var(--acc2);font-weight:700;text-decoration:none}
.rs-foot a:hover{text-decoration:underline}

.rs-alert{background:#fef3f2;color:#a12722;border:1px solid #fdd7d3;padding:11px 14px;border-radius:10px;margin-bottom:14px;font-size:.86rem}

.rs-login{margin-top:16px;text-align:center;font-size:.86rem;color:#5f7a68}
.rs-login a{color:var(--acc2);font-weight:700;text-decoration:none}
</style>
</head><body>
<div class="rs-wrap" style="<?php echo htmlspecialchars($styleVars, ENT_QUOTES, 'UTF-8'); ?>">

  <aside class="rs-hero">
    <div class="rs-hero-inner">
      <a class="rs-brand" href="<?php echo htmlspecialchars($bp); ?>/">
        <img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-48.png" alt="GuinchaF{{A_ACUTE}}cil">
        <span>Guincha<em>F{{A_ACUTE}}cil</em></span>
      </a>
      <p class="rs-eyebrow"><?php echo htmlspecialchars($c['eyebrow']); ?></p>
      <h1><?php echo htmlspecialchars($c['title']); ?></h1>
      <p class="rs-lead"><?php echo htmlspecialchars($c['subtitle']); ?></p>
      <ul class="rs-adv">
        <?php foreach ($c['adv'] as $item): ?>
        <li>
          <i class="fas fa-circle-check" aria-hidden="true"></i>
          <div><b><?php echo htmlspecialchars($item[0]); ?></b><span><?php echo htmlspecialchars($item[1]); ?></span></div>
        </li>
        <?php endforeach; ?>
      </ul>
      <a class="rs-back" href="<?php echo htmlspecialchars($bp . $c['back_href']); ?>">&larr; <?php echo htmlspecialchars($c['back_txt']); ?></a>
    </div>
  </aside>

  <main class="rs-form-wrap">
    <div class="rs-card">
      <p class="rs-card-eyebrow">Criar conta</p>
      <h2><?php echo htmlspecialchars($c['title']); ?></h2>
      <p class="rs-card-sub"><?php echo htmlspecialchars($c['subtitle']); ?></p>

      <?php if (!empty($flash)): ?><div class="rs-alert"><?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?></div><?php endif; ?>

      <form method="POST" action="<?php echo htmlspecialchars($action); ?>" novalidate autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)($csrf_token ?? '')); ?>">
        <input type="hidden" name="retorno" value="<?php echo htmlspecialchars((string)($retorno ?? '/'), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">

        <div class="rs-field">
          <label for="rs_nome">Nome completo *</label>
          <input type="text" id="rs_nome" name="nome" required minlength="3" maxlength="120" autocomplete="name" placeholder="Maria Silva">
        </div>
        <div class="rs-field">
          <label for="rs_tel">WhatsApp *</label>
          <input type="tel" id="rs_tel" name="telefone" required autocomplete="tel" inputmode="tel" placeholder="(21) 99999-9999">
        </div>
        <div class="rs-field">
          <label for="rs_email">E-mail *</label>
          <input type="email" id="rs_email" name="email" required autocomplete="email" placeholder="voce@email.com">
        </div>

        <button class="rs-btn" type="submit" id="rs_btn"><i class="fas fa-arrow-right" aria-hidden="true"></i> Continuar</button>
      </form>

      <div class="rs-or">ou</div>
      <a class="rs-google" href="<?php echo htmlspecialchars($bp); ?>/auth/google?retorno=<?php echo rawurlencode((string)($retorno ?? '/')); ?>">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
        Continuar com Google
      </a>

      <p class="rs-foot">Ao continuar, voc{{E_ACUTE}} concorda com os <a href="<?php echo htmlspecialchars($bp); ?>/termos-servico.php" target="_blank" rel="noopener">Termos</a> e a <a href="<?php echo htmlspecialchars($bp); ?>/politica-privacidade.php" target="_blank" rel="noopener">Política de Privacidade</a>.</p>

      <p class="rs-login">J{{A_ACUTE}} tem conta? <a href="<?php echo htmlspecialchars($bp); ?>/login<?php echo !empty($retorno) && $retorno !== '/' ? '?retorno=' . rawurlencode((string)$retorno) : ''; ?>">Entrar</a></p>
    </div>
  </main>

</div>
<script<?php echo csp_script_nonce_attr(); ?>>
(function(){
  var tel = document.getElementById('rs_tel');
  if (tel) {
    tel.addEventListener('input', function(){
      var d = tel.value.replace(/\D/g,'').slice(0,11);
      if (d.length <= 10) tel.value = d.replace(/^(\d{2})(\d{0,4})(\d{0,4}).*$/, function(_,a,b,c){ return '('+a+')'+(b?' '+b:'')+(c?'-'+c:''); });
      else tel.value = d.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/, function(_,a,b,c){ return '('+a+') '+b+(c?'-'+c:''); });
    });
  }
  var form = document.querySelector('.rs-card form');
  if (form) {
    form.addEventListener('submit', function(){
      var b = document.getElementById('rs_btn');
      if (b) { b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando conta...'; }
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
}
foreach ($k in $acc.Keys) { $raw = $raw.Replace($k, $acc[$k]) }

[System.IO.File]::WriteAllText($arquivo, $raw, $utf8NoBom)
Write-Host "[WRITE] $arquivo" -ForegroundColor Green

& 'C:\xampp\php\php.exe' -l $arquivo
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup." -ForegroundColor Red
    if ($bak) { Copy-Item -LiteralPath $bak -Destination $arquivo -Force }
    exit 1
}
Write-Host "[OK] registro_simples.php reescrito com layout split + tema por tipo." -ForegroundColor Green