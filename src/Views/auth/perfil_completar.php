<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$tipo = in_array(($tipo ?? ''), ['cliente','guincho','oficina','especialista'], true) ? $tipo : 'cliente';
$isCliente    = $tipo === 'cliente';
$isGuincho    = $tipo === 'guincho';
$isOficina    = $tipo === 'oficina';
$isEspecialista = $tipo === 'especialista';

$accent   = $isOficina ? '#f0a83b' : ($isCliente ? '#2fb34a' : '#2fb34a');
$accent2  = $isOficina ? '#b9670c' : ($isCliente ? '#1f8a36' : '#1f8a36');

$labelTipo = [
    'cliente'      => 'Cliente',
    'guincho'      => 'Guincho',
    'oficina'      => 'Oficina',
    'especialista' => 'Especialista',
][$tipo];

$salvo = $salvo ?? [];
$val = function(string $k, string $default = '') use ($salvo): string {
    return htmlspecialchars((string)($salvo[$k] ?? $default), ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
<title>Completar perfil | GuinchaFácil</title>
<meta name="robots" content="noindex,follow">
<style>
:root{--acc:<?php echo $accent; ?>;--acc2:<?php echo $accent2; ?>}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:#f4f8f5;color:#14201a;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;padding:32px 16px}
.pc-wrap{max-width:720px;margin:0 auto}
.pc-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.pc-brand{display:flex;align-items:center;gap:10px;font-weight:800;color:#14201a;text-decoration:none}
.pc-brand img{width:36px;height:36px;border-radius:10px}
.pc-brand span{color:var(--acc2)}
.pc-logout{font-size:.82rem;color:#8a978f;text-decoration:none}
.pc-card{background:#fff;border:1px solid #e4ebe6;border-radius:18px;padding:28px 26px;box-shadow:0 10px 30px rgba(15,40,25,.06)}
.pc-eyebrow{font-size:.74rem;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:var(--acc2);margin:0 0 6px}
.pc-h1{font-size:1.45rem;font-weight:800;margin:0 0 8px}
.pc-sub{color:#5f6f66;font-size:.92rem;margin:0 0 22px}
.pc-section{display:flex;align-items:center;gap:8px;font-size:.72rem;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:var(--acc2);margin:26px 0 12px;padding-bottom:8px;border-bottom:1px solid #e4ebe6}
.pc-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:640px){.pc-row{grid-template-columns:1fr}}
.pc-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.pc-field label{font-size:.82rem;font-weight:700;color:#3d4a42}
.pc-field input,.pc-field select,.pc-field textarea{width:100%;padding:12px 14px;border:1px solid #d9e2dc;border-radius:10px;font-size:1rem;color:#14201a;background:#fff;outline:none}
.pc-field input:focus,.pc-field select:focus{border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 20%,transparent)}
.pc-btn{width:100%;padding:15px;border:0;border-radius:12px;background:linear-gradient(135deg,var(--acc),var(--acc2));color:#fff;font-weight:800;font-size:1rem;cursor:pointer;margin-top:12px}
.pc-btn:disabled{opacity:.55;cursor:progress}
.pc-alert{background:#fef3f2;color:#a12722;border:1px solid #fdd7d3;padding:12px 14px;border-radius:10px;margin-bottom:16px;font-size:.88rem}
.pc-alert.ok{background:#ecfdf3;color:#065f2b;border-color:#a7f3c4}
.pc-warn{background:#fff7ed;color:#8a4f0c;border:1px solid #fcd9a8;padding:12px 14px;border-radius:10px;margin-bottom:16px;font-size:.88rem}
.pc-hint{font-size:.78rem;color:#8a978f;margin-top:4px}
</style>
</head><body>
<div class="pc-wrap">
  <div class="pc-head">
    <a class="pc-brand" href="<?php echo htmlspecialchars($bp); ?>/"><img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-48.png" alt="GuinchaFácil"><div>Guincha<span>Fácil</span></div></a>
    <a class="pc-logout" href="<?php echo htmlspecialchars($bp); ?>/logout"><i class="fas fa-right-from-bracket"></i> Sair</a>
  </div>

  <div class="pc-card">
    <p class="pc-eyebrow">Passo 2 de 2 &middot; Perfil <?php echo htmlspecialchars($labelTipo); ?></p>
    <h1 class="pc-h1">Complete seus dados</h1>
    <p class="pc-sub">Esses dados são obrigatórios para liberar o painel e processar pagamentos. Nada é compartilhado publicamente.</p>

    <?php if (!empty($flash)): ?><div class="pc-alert <?php echo (($flash['type'] ?? '') === 'success') ? 'ok' : ''; ?>"><?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?></div><?php endif; ?>
    <?php if (!empty($pendenteAprovacao)): ?><div class="pc-warn"><i class="fas fa-clock"></i> Sua documentação está em análise. Vocé será notificado quando for aprovado.</div><?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/perfil/completar" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)($csrf_token ?? '')); ?>">

      <div class="pc-section"><i class="fas fa-user"></i>Dados pessoais</div>
      <div class="pc-row">
        <div class="pc-field"><label>Nome completo *</label><input name="nome" required minlength="3" value="<?php echo $val('nome'); ?>"></div>
        <div class="pc-field"><label>CPF *</label><input name="cpf" id="pc_cpf" required maxlength="14" inputmode="numeric" placeholder="000.000.000-00" value="<?php echo $val('cpf'); ?>"></div>
        <div class="pc-field"><label>Telefone / WhatsApp *</label><input name="telefone" id="pc_tel" required inputmode="tel" value="<?php echo $val('telefone'); ?>"></div>
        <div class="pc-field"><label>Data de nascimento</label><input type="date" name="nascimento" value="<?php echo $val('nascimento'); ?>"></div>
      </div>

      <div class="pc-section"><i class="fas fa-lock"></i>Senha de acesso</div>
      <div class="pc-row">
        <div class="pc-field"><label>Senha *</label><input type="password" name="senha" minlength="8" required><div class="pc-hint">Mínimo 8 caracteres.</div></div>
        <div class="pc-field"><label>Confirmar senha *</label><input type="password" name="confirmar_senha" minlength="8" required></div>
      </div>

      <div class="pc-section"><i class="fas fa-map-marker-alt"></i>Endereço</div>
      <div class="pc-row">
        <div class="pc-field"><label>CEP *</label><input name="cep" id="pc_cep" required maxlength="9" inputmode="numeric" placeholder="00000-000" value="<?php echo $val('cep'); ?>"></div>
        <div class="pc-field"><label>Logradouro *</label><input name="logradouro" required value="<?php echo $val('logradouro'); ?>"></div>
        <div class="pc-field"><label>Número *</label><input name="numero" required value="<?php echo $val('numero'); ?>"></div>
        <div class="pc-field"><label>Complemento</label><input name="complemento" value="<?php echo $val('complemento'); ?>"></div>
        <div class="pc-field"><label>Bairro *</label><input name="bairro" required value="<?php echo $val('bairro'); ?>"></div>
        <div class="pc-field"><label>Cidade *</label><input name="cidade" required value="<?php echo $val('cidade'); ?>"></div>
        <div class="pc-field"><label>Estado (UF) *</label>
          <select name="estado" required>
            <option value="">Selecione</option>
            <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
            <option value="<?php echo $uf; ?>" <?php echo (($salvo['estado'] ?? '') === $uf) ? 'selected' : ''; ?>><?php echo $uf; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php if ($isGuincho || $isOficina): ?>
      <div class="pc-section"><i class="fas fa-wallet"></i>Repasse</div>
      <div class="pc-row">
        <div class="pc-field"><label>Chave PIX *</label><input name="chave_pix" required value="<?php echo $val('chave_pix'); ?>"></div>
        <div class="pc-field"><label>Tipo de chave *</label>
          <select name="chave_pix_tipo" required>
            <?php foreach (['cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'E-mail','telefone'=>'Telefone','aleatoria'=>'Aleatória'] as $k=>$v): ?>
            <option value="<?php echo $k; ?>" <?php echo (($salvo['chave_pix_tipo'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($isGuincho): ?>
      <div class="pc-section"><i class="fas fa-truck"></i>Veículo e CNH</div>
      <div class="pc-row">
        <div class="pc-field"><label>Placa do reboque *</label><input name="placa_guincho" required maxlength="8" style="text-transform:uppercase" value="<?php echo $val('placa_guincho'); ?>"></div>
        <div class="pc-field"><label>Capacidade (ton) *</label><input type="number" step="0.1" min="0.5" max="50" name="capacidade_ton" required value="<?php echo $val('capacidade_ton'); ?>"></div>
        <div class="pc-field"><label>Número da CNH *</label><input name="cnh_numero" required maxlength="11" value="<?php echo $val('cnh_numero'); ?>"></div>
        <div class="pc-field"><label>Validade da CNH *</label><input type="date" name="cnh_validade" required value="<?php echo $val('cnh_validade'); ?>"></div>
        <div class="pc-field"><label>Raio de atendimento (km) *</label><input type="number" min="5" max="200" name="raio_cobertura_km" required value="<?php echo $val('raio_cobertura_km', '30'); ?>"></div>
        <div class="pc-field"><label>CNH frente (arquivo) *</label><input type="file" name="doc_cnh_frente" accept="image/*,.pdf"></div>
        <div class="pc-field"><label>CNH verso (arquivo) *</label><input type="file" name="doc_cnh_verso" accept="image/*,.pdf"></div>
      </div>
      <?php endif; ?>

      <?php if ($isOficina): ?>
      <div class="pc-section"><i class="fas fa-warehouse"></i>Dados da oficina</div>
      <div class="pc-row">
        <div class="pc-field"><label>CNPJ *</label><input name="cnpj" required maxlength="18" inputmode="numeric" placeholder="00.000.000/0000-00" value="<?php echo $val('cnpj'); ?>"></div>
        <div class="pc-field"><label>Razão social *</label><input name="razao_social" required value="<?php echo $val('razao_social'); ?>"></div>
      </div>
      <?php endif; ?>

      <button class="pc-btn" type="submit" id="pc_btn"><i class="fas fa-check"></i> Salvar e continuar</button>
    </form>
  </div>
</div>
<script<?php echo csp_script_nonce_attr(); ?>>
(function(){
  function maskCpf(el){ el.addEventListener('input', function(){ var v = el.value.replace(/\D/g,'').slice(0,11); el.value = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, function(_,a,b,c,d){ return a+'.'+b+'.'+c+(d?'-'+d:''); }); }); }
  function maskCep(el){ el.addEventListener('input', function(){ var v = el.value.replace(/\D/g,'').slice(0,8); el.value = v.replace(/(\d{5})(\d{0,3})/, function(_,a,b){ return b? a+'-'+b : a; }); }); }
  function maskTel(el){ el.addEventListener('input', function(){ var d = el.value.replace(/\D/g,'').slice(0,11); el.value = d.length<=10 ? d.replace(/^(\d{2})(\d{0,4})(\d{0,4}).*$/,function(_,a,b,c){return '('+a+')'+(b?' '+b:'')+(c?'-'+c:'');}) : d.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/,function(_,a,b,c){return '('+a+') '+b+(c?'-'+c:'');}); }); }
  var cpf = document.getElementById('pc_cpf'); if(cpf) maskCpf(cpf);
  var tel = document.getElementById('pc_tel'); if(tel) maskTel(tel);
  var cep = document.getElementById('pc_cep'); if(cep) maskCep(cep);
})();
</script>
</body></html>