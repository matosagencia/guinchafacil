<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$editando = !empty($veiculo['id']);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-veiculoform.css">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content"><?php include __DIR__ . '/partials/_panel_topbar_cliente.php'; ?>

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Conta</span>
            <h1><i class="fas fa-car me-2 veiculoform-icon-accent"></i><?php echo $editando ? 'Editar Veículo' : 'Cadastrar Veículo'; ?></h1>
            <p>Dados usados para acionar o guincho certo no momento do pedido.</p>
        </div>
        <a href="<?php echo $bp; ?>/cliente/veiculos" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </header>

    <?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo $_GET['erro'] === 'validacao' ? 'Preencha todos os campos obrigatórios.' : 'Dados inválidos. Tente novamente.'; ?>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-car me-2"></i>
                    <?php echo $editando ? 'Alterar dados do veículo' : 'Informações do veículo'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/cliente/veiculo/salvar" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">                        <?php if (!empty($triageSessionId)): ?>                        <input type="hidden" name="triage_session_id" value="<?php echo htmlspecialchars($triageSessionId); ?>">                        <?php endif; ?>
                        <?php if ($editando): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$veiculo['id']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Veículo</label>
                            <div class="row g-2">
                                <?php
                                $tipos = ['carro'=>['car','Carro'],'moto'=>['motorcycle','Moto'],
                                          'caminhao'=>['truck','Caminhão'],'van'=>['shuttle-van','Van/Utilitário']];
                                $tipoSel = $veiculo['tipo'] ?? 'carro';
                                foreach ($tipos as $val => [$ico, $lbl]):
                                ?>
                                <div class="col-6 col-sm-3">
                                    <input type="radio" name="tipo" id="tipo_<?php echo $val; ?>"
                                           value="<?php echo $val; ?>" class="btn-check"
                                           <?php echo $tipoSel === $val ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-primary w-100 veiculoform-tipo-label" for="tipo_<?php echo $val; ?>">
                                        <i class="fas fa-<?php echo $ico; ?> d-block mb-1 veiculoform-tipo-icon"></i>
                                        <?php echo $lbl; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Marca *</label>
                                <input type="hidden" name="marca" id="marcaInput"
                                       value="<?php echo htmlspecialchars($veiculo['marca'] ?? ''); ?>"
                                       placeholder="Digite pra buscar. Ex: Toyota" required>
                                <input type="hidden" name="vehicle_brand_id" id="marcaIdInput" value="<?php echo htmlspecialchars((string)($veiculo['vehicle_brand_id'] ?? '')); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Modelo *</label>
                                <input type="hidden" name="modelo" id="modeloInput"
                                       value="<?php echo htmlspecialchars($veiculo['modelo'] ?? ''); ?>"
                                       placeholder="Digite pra buscar. Ex: Corolla" required>
                                <input type="hidden" name="vehicle_model_id" id="modeloIdInput" value="<?php echo htmlspecialchars((string)($veiculo['vehicle_model_id'] ?? '')); ?>">                                <div id="vehicleBrandCards" class="row g-2"></div><div id="vehicleModelCards" class="row g-2 mt-2"></div>
                            </div>
                            <div class="col-12">
                                <p class="text-muted small mb-0">Não achou sua marca/modelo? Pode digitar do seu jeito mesmo — o catálogo visual ainda está sendo ampliado.</p>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Ano *</label>
                                <input type="number" class="form-control" name="ano"
                                       value="<?php echo htmlspecialchars($veiculo['ano'] ?? date('Y')); ?>"
                                       min="1950" max="<?php echo date('Y') + 1; ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Cor *</label>
                                <select class="form-select" name="cor" id="corSelect"
                                       value="<?php echo htmlspecialchars($veiculo['cor'] ?? ''); ?>"
                                       required><option value="">Selecione a cor</option><?php foreach (['Preto','Branco','Prata','Cinza','Vermelho','Azul','Verde','Amarelo','Marrom','Bege','Dourado','Laranja','Roxo','Outra'] as $cor): ?><option value="<?php echo $cor; ?>" <?php echo (($veiculo['cor'] ?? '') === $cor) ? 'selected' : ''; ?>><?php echo $cor; ?></option><?php endforeach; ?></select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-8 order-2">
                                <label class="form-label">Cidade do Emplacamento</label>
                                <select class="form-select" name="cidade_placa" id="cidadePlacaSelect"
                                       value="<?php echo htmlspecialchars($veiculo['cidade_placa'] ?? ''); ?>"
                                       ><option value="">Selecione primeiro a UF</option></select>
                            </div>
                            <div class="col-4 order-1">
                                <label class="form-label">UF da Placa</label>
                                <select class="form-select" name="uf_placa" id="ufPlacaSelect"><option value="">Selecione a UF</option><?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?><option value="<?php echo $uf; ?>" <?php echo (($veiculo['uf_placa'] ?? '') === $uf) ? 'selected' : ''; ?>><?php echo $uf; ?></option><?php endforeach; ?></select><?php /* */ ?>

                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Placa *</label>
                            <input type="text" class="form-control text-uppercase veiculoform-placa-input" name="placa"
                                   id="placaInput"
                                   value="<?php echo htmlspecialchars($veiculo['placa'] ?? ''); ?>"
                                   placeholder="Ex: ABC1D23 (Mercosul) ou ABC-1234"
                                   required>
                            <div class="veiculoform-placa-hint">
                                Formatos aceitos: Mercosul (ABC1D23) ou antigo (ABC-1234). Informe cidade e UF do emplacamento para auditoria.
                            </div>
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3"><i class="fas fa-cogs me-2"></i>Dados operacionais</h6>
                        <p class="text-muted small mb-3">Usados para escolher o prestador certo (guincho ou especialista) para o seu veículo.</p>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Combustível</label>
                                <select class="form-select" name="fuel_type">
                                    <option value="">Selecione</option>
                                    <?php $fuelSel = $veiculo['fuel_type'] ?? '';
                                    foreach (['flex'=>'Flex','gasolina'=>'Gasolina','etanol'=>'Etanol','diesel'=>'Diesel','gnv'=>'GNV','eletrico'=>'Elétrico'] as $val=>$lbl): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $fuelSel === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Câmbio</label>
                                <select class="form-select" name="transmission_type">
                                    <option value="">Selecione</option>
                                    <?php $transSel = $veiculo['transmission_type'] ?? '';
                                    foreach (['manual'=>'Manual','automatico'=>'Automático'] as $val=>$lbl): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $transSel === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">É elétrico ou híbrido?</label>
                            <select class="form-select" name="electric_type">
                                <?php $elecSel = $veiculo['electric_type'] ?? 'nao_eletrico';
                                foreach (['nao_eletrico'=>'Não é elétrico nem híbrido','hibrido'=>'Híbrido','eletrico_puro'=>'Elétrico puro'] as $val=>$lbl): ?>
                                <option value="<?php echo $val; ?>" <?php echo $elecSel === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-6 form-check ms-1">
                                <input type="checkbox" class="form-check-input" id="has_spare_tire" name="has_spare_tire" value="1"
                                       <?php echo !empty($veiculo['has_spare_tire']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="has_spare_tire">Possui estepe</label>
                            </div>
                            <div class="col-6 form-check ms-1">
                                <input type="checkbox" class="form-check-input" id="has_locking_bolt" name="has_locking_bolt" value="1"
                                       <?php echo !empty($veiculo['has_locking_bolt']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="has_locking_bolt">Possui parafuso antifurto na roda</label>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-2"><i class="fas fa-file-alt me-2"></i>Quer confirmar os dados do veículo?</h6>
                        <p class="text-muted small mb-3">
                            Envie o CRLV-e ou outro documento. Isso é opcional e ajuda a validar os dados e agilizar atendimentos futuros.
                            <?php if (!empty($veiculo['verification_status']) && $veiculo['verification_status'] !== 'DECLARED'): ?>
                                <br><span class="badge bg-info mt-1">Status atual: <?php echo htmlspecialchars($veiculo['verification_status']); ?></span>
                            <?php endif; ?>
                        </p>
                        <div class="mb-4">
                            <input type="file" class="form-control" name="documento" accept=".jpg,.jpeg,.png,.pdf">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $editando ? 'Salvar Alterações' : 'Cadastrar Veículo'; ?>
                        </button>

                        <?php if ($editando): ?>
                        <a href="<?php echo $bp; ?>/cliente/veiculos" class="btn btn-secondary w-100 mt-2">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<script<?php echo csp_script_nonce_attr(); ?>>
document.getElementById('placaInput').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
});

const ufPlacaInput = document.querySelector('input[name="uf_placa"]');
if (ufPlacaInput) {
    ufPlacaInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2);
    });
}
</script><script<?php echo csp_script_nonce_attr(); ?>>(function(){var s=document.getElementById('corSelect');if(!s)return;s.style.display='none';var w=document.createElement('div');w.className='position-relative';s.parentNode.insertBefore(w,s);w.appendChild(s);var b=document.createElement('button');b.type='button';b.className='form-select text-start';b.textContent='Selecione a cor';w.appendChild(b);var l=document.createElement('div');l.style='display:none;position:absolute;z-index:30;left:0;right:0;max-height:250px;overflow:auto;background:#fff;border:1px solid #ced4da;border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,.15);padding:4px';w.appendChild(l);var colors={Preto:'#111',Branco:'#fff',Prata:'#c4c8cc',Cinza:'#777',Vermelho:'#d62828',Azul:'#1769aa',Verde:'#218739',Amarelo:'#f2c400',Marrom:'#795548',Bege:'#d8c39b',Dourado:'#c99a22',Laranja:'#ed7d24',Roxo:'#743b9b',Outra:'linear-gradient(135deg,#e33 0 25%,#fc3 25% 50%,#3a6 50% 75%,#48c 75%)'};function sw(name){return '<span style="display:inline-block;width:22px;height:22px;border-radius:50%;border:1px solid #adb5bd;background:'+ (colors[name]||'#fff') +'"></span>'}Array.prototype.forEach.call(s.options,function(o){if(!o.value)return;var x=document.createElement('button');x.type='button';x.className='w-100 border-0 bg-white text-start d-flex align-items-center gap-2 p-2';x.innerHTML=sw(o.value)+'<span>COR - '+o.text+'</span>';x.onclick=function(){s.value=o.value;b.innerHTML=sw(o.value)+'<span class="ms-2">COR - '+o.text+'</span>';l.style.display='none';s.dispatchEvent(new Event('change'))};l.appendChild(x);if(o.selected)b.innerHTML=sw(o.value)+'<span class="ms-2">COR - '+o.text+'</span>'});b.onclick=function(){l.style.display=l.style.display==='block'?'none':'block'};document.addEventListener('click',function(e){if(!w.contains(e.target))l.style.display='none'})})();</script><script<?php echo csp_script_nonce_attr(); ?>>document.getElementById('vehicleBrandCards').style.display='none';document.getElementById('vehicleModelCards').style.display='none';</script><script<?php echo csp_script_nonce_attr(); ?>>(function(){  var base='<?php echo addslashes($bp); ?>', brand=document.getElementById('marcaInput'), brandId=document.getElementById('marcaIdInput'), model=document.getElementById('modeloInput'), modelId=document.getElementById('modeloIdInput'), brandBox=document.getElementById('vehicleBrandCards'), modelBox=document.getElementById('vehicleModelCards');  if(!brand||!model||!brandBox||!modelBox)return;  brandBox.innerHTML='';modelBox.innerHTML='';  function combo(input,id,box,label,loader){    input.type='text';input.readOnly=false;input.className='form-control';input.placeholder='Pesquisar '+label+'...';    var wrap=document.createElement('div');wrap.className='position-relative';input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);var list=document.createElement('div');list.className='vehicle-select-menu';list.style='display:none;position:absolute;z-index:20;left:0;right:0;max-height:260px;overflow:auto;background:#fff;border:1px solid #ced4da;border-radius:8px;box-shadow:0 8px 22px rgba(0,0,0,.14);padding:4px';wrap.appendChild(list);    var data=[];function render(){var q=input.value.toLowerCase().trim();list.innerHTML='';data.filter(function(x){return !q||x.name.toLowerCase().indexOf(q)>=0}).forEach(function(x){var b=document.createElement('button');b.type='button';b.className='w-100 border-0 bg-white text-start d-flex align-items-center gap-2 p-2';b.innerHTML=(x.logo_path||x.image_path)?'<img src="'+(x.logo_path||x.image_path)+'" style="width:38px;height:30px;object-fit:contain" alt="">':'';b.innerHTML+='<span>'+x.name+'</span>';b.onclick=function(){input.value=x.name;id.value=x.id;list.style.display='none';input.dispatchEvent(new Event('change'))};list.appendChild(b)});list.style.display=list.children.length?'block':'none'}    input.addEventListener('focus',function(){render()});input.addEventListener('input',function(){id.value='';render()});document.addEventListener('click',function(e){if(!wrap.contains(e.target))list.style.display='none'});loader(function(a){data=a||[];if(input.value)render()});return function(){return data};  }  var getBrands=combo(brand,brandId,brandBox,'a marca',function(done){fetch(base+'/veiculo-catalogo/marcas').then(function(r){return r.json()}).then(done)});  combo(model,modelId,modelBox,'o modelo',function(done){brand.addEventListener('change',function(){model.value='';modelId.value='';fetch(base+'/veiculo-catalogo/modelos?marca_id='+brandId.value).then(function(r){return r.json()}).then(done)});if(brandId.value)fetch(base+'/veiculo-catalogo/modelos?marca_id='+brandId.value).then(function(r){return r.json()}).then(done);else done([])});})();</script><script<?php echo csp_script_nonce_attr(); ?>>(function(){var b=document.getElementById('marcaInput'),bi=document.getElementById('marcaIdInput'),m=document.getElementById('modeloInput'),mi=document.getElementById('modeloIdInput'),uf=document.getElementById('ufPlacaSelect'),c=document.getElementById('cidadePlacaSelect'),bb=document.getElementById('vehicleBrandCards'),mb=document.getElementById('vehicleModelCards'),base='<?php echo addslashes($bp); ?>';if(!bb)return;function add(x,img,t,ti){var q=document.createElement('button');q.type='button';q.className='btn btn-outline-secondary text-start d-flex align-items-center gap-2 col-6 col-md-4';if(img){var i=document.createElement('img');i.src=img;i.style='width:38px;height:30px;object-fit:contain';q.appendChild(i)}var s=document.createElement('span');s.textContent=x.name;q.appendChild(s);q.onclick=function(){t.value=x.name;ti.value=x.id;t.dispatchEvent(new Event('change'))};return q}fetch(base+'/veiculo-catalogo/marcas').then(function(r){return r.json()}).then(function(a){a.forEach(function(x){bb.appendChild(add(x,x.logo_path,b,bi))})});b.addEventListener('change',function(){mb.innerHTML='';m.value='';mi.value='';fetch(base+'/veiculo-catalogo/modelos?marca_id='+bi.value).then(function(r){return r.json()}).then(function(a){a.forEach(function(x){mb.appendChild(add(x,x.image_path,m,mi))})})});function cities(){if(!uf.value){c.innerHTML='<option value="">Selecione primeiro a UF</option>';return}c.innerHTML='<option>Carregando...</option>';fetch(base+'/veiculo-catalogo/cidades?uf='+uf.value).then(function(r){return r.json()}).then(function(a){c.innerHTML='<option value="">Selecione a cidade</option>';a.forEach(function(x){var o=document.createElement('option');o.value=x.nome;o.textContent=x.nome;c.appendChild(o)})})}uf.addEventListener('change',cities);if(uf.value)cities()})();</script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/components/vehicle-brand-model-autocomplete.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
initVehicleBrandModelAutocomplete({
    baseUrl: '<?php echo addslashes($bp); ?>',
    marcaInput: document.getElementById('marcaInput'),
    marcaIdInput: document.getElementById('marcaIdInput'),
    modeloInput: document.getElementById('modeloInput'),
    modeloIdInput: document.getElementById('modeloIdInput'),
});
</script>
