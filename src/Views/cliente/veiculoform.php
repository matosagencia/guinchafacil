<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$editando = !empty($veiculo['id']);
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-car me-2" style="color:var(--primary)"></i>
                <?php echo $editando ? 'Editar Veículo' : 'Cadastrar Veículo'; ?>
            </div>
        </div>
        <a href="<?php echo $bp; ?>/cliente/veiculos" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </div>

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
                    <form method="POST" action="<?php echo $bp; ?>/cliente/veiculo/salvar">
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
                                    <label class="btn btn-outline-primary w-100" for="tipo_<?php echo $val; ?>"
                                           style="font-size:.8rem;padding:.45rem .3rem">
                                        <i class="fas fa-<?php echo $ico; ?> d-block mb-1" style="font-size:1.1rem"></i>
                                        <?php echo $lbl; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Marca *</label>
                                <input type="text" class="form-control" name="marca"
                                       value="<?php echo htmlspecialchars($veiculo['marca'] ?? ''); ?>"
                                       placeholder="Ex: Toyota" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Modelo *</label>
                                <input type="text" class="form-control" name="modelo"
                                       value="<?php echo htmlspecialchars($veiculo['modelo'] ?? ''); ?>"
                                       placeholder="Ex: Corolla" required>
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

                        <div class="mb-4">
                            <label class="form-label">Placa *</label>
                            <input type="text" class="form-control text-uppercase" name="placa"
                                   id="placaInput"
                                   value="<?php echo htmlspecialchars($veiculo['placa'] ?? ''); ?>"
                                   placeholder="Ex: ABC1D23 (Mercosul) ou ABC-1234"
                                   style="letter-spacing:.08em" required>
                            <div style="font-size:.77rem;color:var(--theme-muted);margin-top:.35rem">
                                Formatos aceitos: Mercosul (ABC1D23) ou antigo (ABC-1234)
                            </div>
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
<script>
document.getElementById('placaInput').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
});
</script>
