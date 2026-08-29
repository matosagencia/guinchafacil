<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
require_once __DIR__ . '/../../components/header.php';
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 bg-dark text-light">
                <div class="card-body p-4">
                    <h3 class="card-title mb-4">Completar Cadastro</h3>
                    <p class="text-muted">Para começar a receber chamados, precisamos de alguns dados adicionais do seu veículo.</p>
                    
                    <?php if (!empty($flash)): ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'info'; ?> mb-4">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo $bp; ?>/guincho/perfil/completar" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <!-- Campos de Veículo (baseado no registro original Step 2) -->
                        <h5 class="mt-4"><i class="fas fa-truck me-2"></i>Dados do Veículo</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Placa *</label>
                                <input type="text" class="form-control" name="placa_guincho" required maxlength="7">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Capacidade (ton) *</label>
                                <input type="number" step="0.1" class="form-control" name="capacidade_ton" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade do Emplacamento</label>
                                <input type="text" class="form-control" name="cidade_placa">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">UF</label>
                                <input type="text" class="form-control" name="uf_placa" maxlength="2">
                            </div>
                        </div>

                        <!-- Documentos (baseado no registro original Step 3) -->
                        <h5 class="mt-4"><i class="fas fa-file-alt me-2"></i>Documentos</h5>
                        <div class="mb-3">
                            <label class="form-label">CNH Frente</label>
                            <input type="file" class="form-control" name="doc_cnh_frente" accept="image/*,.pdf">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CNH Verso</label>
                            <input type="file" class="form-control" name="doc_cnh_verso" accept="image/*,.pdf">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Foto do Veículo</label>
                            <input type="file" class="form-control" name="foto_veiculo" accept="image/*">
                        </div>

                        <!-- PIX -->
                        <h5 class="mt-4"><i class="fas fa-qrcode me-2"></i>Dados Pix</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="chave_pix_tipo">
                                    <option value="cpf">CPF</option>
                                    <option value="cnpj">CNPJ</option>
                                    <option value="email">E-mail</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Chave</label>
                                <input type="text" class="form-control" name="chave_pix" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 mt-4">Concluir Cadastro</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../components/footer.php'; ?>
