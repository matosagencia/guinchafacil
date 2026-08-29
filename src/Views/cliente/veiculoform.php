<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$editando = !empty($veiculo['id']);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-veiculoform.css">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

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
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
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
                                <input type="text" class="form-control" name="marca" id="marcaInput" autocomplete="off"
                                       value="<?php echo htmlspecialchars($veiculo['marca'] ?? ''); ?>"
                                       placeholder="Digite pra buscar. Ex: Toyota" required>
                                <input type="hidden" name="vehicle_brand_id" id="marcaIdInput" value="<?php echo htmlspecialchars((string)($veiculo['vehicle_brand_id'] ?? '')); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Modelo *</label>
                                <input type="text" class="form-control" name="modelo" id="modeloInput" autocomplete="off"
                                       value="<?php echo htmlspecialchars($veiculo['modelo'] ?? ''); ?>"
                                       placeholder="Digite pra buscar. Ex: Corolla" required>
                                <input type="hidden" name="vehicle_model_id" id="modeloIdInput" value="<?php echo htmlspecialchars((string)($veiculo['vehicle_model_id'] ?? '')); ?>">
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
                                <input type="text" class="form-control" name="cor"
                                       value="<?php echo htmlspecialchars($veiculo['cor'] ?? ''); ?>"
                                       placeholder="Ex: Prata" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-8">
                                <label class="form-label">Cidade do Emplacamento</label>
                                <input type="text" class="form-control" name="cidade_placa"
                                       value="<?php echo htmlspecialchars($veiculo['cidade_placa'] ?? ''); ?>"
                                       placeholder="Ex: Rio de Janeiro">
                            </div>
                            <div class="col-4">
                                <label class="form-label">UF da Placa</label>
                                <input type="text" class="form-control text-uppercase" name="uf_placa"
                                       value="<?php echo htmlspecialchars($veiculo['uf_placa'] ?? ''); ?>"
                                       maxlength="2" placeholder="RJ">
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
</script>
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
