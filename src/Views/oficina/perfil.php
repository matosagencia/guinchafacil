<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$bancos = [
    '001'=>'001 — Banco do Brasil','003'=>'003 — Banco da Amazônia','004'=>'004 — Banco do Nordeste',
    '021'=>'021 — Banestes','033'=>'033 — Santander','036'=>'036 — Bradesco BBI','037'=>'037 — Banpará',
    '041'=>'041 — Banrisul','047'=>'047 — Banese','070'=>'070 — BRB','077'=>'077 — Banco Inter',
    '104'=>'104 — Caixa Econômica Federal','136'=>'136 — Unicred','197'=>'197 — Stone',
    '208'=>'208 — BTG Pactual','212'=>'212 — Banco Original','218'=>'218 — BS2','224'=>'224 — Banco Fibra',
    '237'=>'237 — Bradesco','246'=>'246 — Banco ABC Brasil','260'=>'260 — Nubank','290'=>'290 — PagSeguro',
    '318'=>'318 — Banco BMG','323'=>'323 — Mercado Pago','336'=>'336 — C6 Bank','341'=>'341 — Itaú Unibanco',
    '356'=>'356 — Banco Real','380'=>'380 — PicPay','389'=>'389 — Banco Mercantil','399'=>'399 — HSBC',
    '412'=>'412 — Banco Capital','422'=>'422 — Banco Safra','453'=>'453 — Banco Rural',
    '633'=>'633 — Banco Rendimento','637'=>'637 — Banco Sofisa','655'=>'655 — Banco Votorantim',
    '707'=>'707 — Banco Daycoval','745'=>'745 — Citibank','748'=>'748 — Sicredi','756'=>'756 — Sicoob',
];

$servicosDisponiveis = [
    'borracharia'=>['Pneu / borracharia','fa-circle-notch'],'eletrica'=>['Elétrica automotiva','fa-bolt'],
    'bateria'=>['Bateria / partida','fa-car-battery'],'mecanica'=>['Mecânica geral','fa-gears'],
    'chaveiro'=>['Chaveiro automotivo','fa-key'],'funilaria'=>['Funilaria e pintura','fa-spray-can'],
    'ar_condicionado'=>['Ar-condicionado','fa-snowflake'],'injecao'=>['Injeção eletrônica','fa-microchip'],
    'suspensao'=>['Suspensão / freios','fa-arrows-up-down'],
];
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/components/dashboard.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-dashboard.css">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('guincho','oficina');});</script>

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
<main class="main-content">
    <div class="app-dashboard" style="max-width:960px">

        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Conta</span>
                <h1>Meu perfil</h1>
                <p>Dados operacionais, recebimento e serviços oferecidos.</p>
            </div>
        </header>

        <?php if (!empty($_SESSION['_flash'])): foreach ((array)$_SESSION['_flash'] as $f): unset($_SESSION['_flash']); ?>
            <div class="alert alert-<?= $f['type'] === 'success' ? 'success' : 'danger' ?> mb-3"><?= htmlspecialchars($f['message']) ?></div>
        <?php endforeach; endif; ?>

        <form method="POST" action="<?= $bp ?>/oficina/perfil/salvar" class="tow-card p-4 mb-4" id="formPerfil">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <h3 class="tow-panel-title mb-3"><i class="fas fa-store me-2"></i>Dados da oficina</h3>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label small">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars((string)$oficina['nome']) ?>" required></div>
                <div class="col-md-4"><label class="form-label small">Telefone</label>
                    <input type="tel" name="telefone" id="inpTel" class="form-control" value="<?= htmlspecialchars((string)($oficina['telefone'] ?? '')) ?>"></div>
                <div class="col-12"><label class="form-label small">Endereço completo</label>
                    <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars((string)$oficina['endereco']) ?>" required></div>
                <div class="col-md-4"><label class="form-label small">Latitude</label>
                    <input type="text" name="latitude" id="latitude" class="form-control" value="<?= htmlspecialchars((string)($oficina['latitude'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label small">Longitude</label>
                    <input type="text" name="longitude" id="longitude" class="form-control" value="<?= htmlspecialchars((string)($oficina['longitude'] ?? '')) ?>"></div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" id="btnUsarGps" class="btn btn-outline-success w-100"><i class="fas fa-location-crosshairs me-1"></i>Usar GPS</button></div>
                <div class="col-12">
                    <label class="form-label small">Raio de atendimento: <strong id="raioLabel"><?= (int)($oficina['raio_atendimento_km'] ?? 10) ?> km</strong></label>
                    <input type="range" name="raio_atendimento_km" id="raioRange" class="form-range" min="1" max="100" step="1" value="<?= (int)($oficina['raio_atendimento_km'] ?? 10) ?>">
                </div>
            </div>

            <hr class="my-4">

            <h3 class="tow-panel-title mb-3"><i class="fas fa-pix me-2"></i>Recebimento (PIX)</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">Tipo da chave</label>
                    <select name="pix_tipo" id="pixTipo" class="form-select">
                        <option value="">— Selecione —</option>
                        <?php foreach (['cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'E-mail','telefone'=>'Telefone','aleatoria'=>'Chave aleatória'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($oficina['pix_tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Chave PIX</label>
                    <input type="text" name="pix_chave" id="pixChave" class="form-control" value="<?= htmlspecialchars((string)($oficina['pix_chave'] ?? '')) ?>">
                    <div class="invalid-feedback d-block" id="pixFeedback" style="font-size:.78rem;min-height:1em"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Banco</label>
                    <input type="text" name="banco_codigo" id="bancoBusca" class="form-control" list="listaBancos"
                           value="<?= htmlspecialchars((string)($oficina['banco_codigo'] ?? '')) ?>" placeholder="Digite o código ou nome">
                    <datalist id="listaBancos">
                        <?php foreach ($bancos as $cod => $label): ?>
                            <option value="<?= htmlspecialchars($cod) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-4"><label class="form-label small">Agência</label>
                    <input type="text" name="banco_agencia" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_agencia'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label small">Conta (com dígito)</label>
                    <input type="text" name="banco_conta" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_conta'] ?? '')) ?>" placeholder="12345-6"></div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar dados</button>
            </div>
        </form>

        <form method="POST" action="<?= $bp ?>/oficina/servicos/salvar" class="tow-card p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <h3 class="tow-panel-title mb-1">Serviços oferecidos</h3>
            <p class="tow-panel-subtitle mb-3">Marque tudo que sua oficina faz.</p>
            <div class="row g-2">
                <?php foreach ($servicosDisponiveis as $slug => [$label, $icone]): ?>
                    <div class="col-md-4 col-sm-6">
                        <label class="d-flex align-items-center gap-2 p-3" style="border:1px solid #5c3d28;border-radius:10px;cursor:pointer;background:#3a2318">
                            <input type="checkbox" name="servicos[]" value="<?= $slug ?>" <?= in_array($slug, $servicosAtuais, true) ? 'checked':'' ?>>
                            <i class="fas <?= $icone ?>" style="color:#d97706"></i>
                            <span style="color:#fef3e8"><?= htmlspecialchars($label) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-end mt-4">
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar serviços</button>
            </div>
        </form>

    </div>
</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    var r = document.getElementById('raioRange'); var l = document.getElementById('raioLabel');
    if (r) r.addEventListener('input', function(){ l.textContent = r.value + ' km'; });
    document.getElementById('btnUsarGps')?.addEventListener('click', function(){
        if (!navigator.geolocation) return alert('GPS indisponível.');
        navigator.geolocation.getCurrentPosition(function(p){
            document.getElementById('latitude').value  = p.coords.latitude.toFixed(7);
            document.getElementById('longitude').value = p.coords.longitude.toFixed(7);
        }, function(){ alert('Sem localização.'); });
    });

    // ─── Validador de PIX ───
    var pixTipo  = document.getElementById('pixTipo');
    var pixChave = document.getElementById('pixChave');
    var pixFb    = document.getElementById('pixFeedback');

    function soDigitos(s) { return (s||'').replace(/\D/g,''); }
    function validaCPF(c){
        c = soDigitos(c); if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false;
        var s=0; for (var i=0;i<9;i++) s+=parseInt(c[i])*(10-i);
        var d1=(s*10)%11; if (d1===10) d1=0; if (d1!=c[9]) return false;
        s=0; for (i=0;i<10;i++) s+=parseInt(c[i])*(11-i);
        var d2=(s*10)%11; if (d2===10) d2=0; return d2==c[10];
    }
    function validaCNPJ(c){
        c = soDigitos(c); if (c.length !== 14) return false;
        var b=[5,4,3,2,9,8,7,6,5,4,3,2], s=0;
        for (var i=0;i<12;i++) s+=parseInt(c[i])*b[i];
        var d1=s%11<2?0:11-(s%11); if (d1!=c[12]) return false;
        b=[6,5,4,3,2,9,8,7,6,5,4,3,2]; s=0;
        for (i=0;i<13;i++) s+=parseInt(c[i])*b[i];
        var d2=s%11<2?0:11-(s%11); return d2==c[13];
    }
    function validaEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
    function validaTelefone(v){ var d=soDigitos(v); return d.length===10 || d.length===11; }
    function validaAleatoria(v){ return v && v.length>=32; }

    function validarPix() {
        if (!pixTipo || !pixChave || !pixFb) return true;
        var t = pixTipo.value, v = (pixChave.value||'').trim();
        if (!t) { pixFb.textContent=''; pixFb.className='invalid-feedback d-block'; return true; }
        if (!v) { pixFb.textContent='Informe a chave.'; pixFb.className='invalid-feedback d-block text-warning'; return false; }
        var ok=false, msg='';
        if (t==='cpf')         { ok=validaCPF(v);      msg = ok?'CPF válido':'CPF inválido'; }
        else if (t==='cnpj')   { ok=validaCNPJ(v);     msg = ok?'CNPJ válido':'CNPJ inválido'; }
        else if (t==='email')  { ok=validaEmail(v);    msg = ok?'E-mail válido':'E-mail inválido'; }
        else if (t==='telefone'){ ok=validaTelefone(v);msg = ok?'Telefone válido':'Telefone inválido'; }
        else if (t==='aleatoria'){ ok=validaAleatoria(v);msg = ok?'Chave aleatória OK':'Use a chave aleatória completa (32+ caracteres)'; }
        pixFb.textContent = msg;
        pixFb.className = 'invalid-feedback d-block ' + (ok?'text-success':'text-danger');
        return ok;
    }
    if (pixTipo)  pixTipo.addEventListener('change', validarPix);
    if (pixChave) pixChave.addEventListener('input',  validarPix);
    document.getElementById('formPerfil')?.addEventListener('submit', function(e){
        if (!validarPix()) { e.preventDefault(); alert('Corrija a chave PIX antes de salvar.'); }
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>