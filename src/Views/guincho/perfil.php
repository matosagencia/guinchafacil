<?php
$bp  = defined('BASE_PATH') ? BASE_PATH : '';
$g   = $guincho     ?? [];
$u   = $dadosUsuario ?? [];
$tiposPix = ['cpf' => 'CPF', 'email' => 'E-mail', 'telefone' => 'Telefone', 'aleatoria' => 'Aleatória'];
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-user-pen me-2 text-primary-custom"></i>Meu Perfil</div>
            <div class="page-subtitle">Dados pessoais, veículo e chave PIX</div>
        </div>
    </div>

    <?php foreach ($flashMsg ?? [] as $flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
        <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>

    <form method="POST" action="<?php echo $bp; ?>/guincho/perfil/salvar">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

    <div class="row g-4">

        <!-- ── Dados Pessoais ───────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-user me-2"></i>Dados Pessoais</div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control"
                               value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>" required minlength="3">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control"
                               value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" disabled>
                        <div class="form-text">Para alterar o e-mail, entre em contato com o suporte.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Telefone / WhatsApp <span class="text-danger">*</span></label>
                        <input type="tel" name="telefone" class="form-control"
                               value="<?php echo htmlspecialchars($u['telefone'] ?? ''); ?>"
                               placeholder="(11) 99999-9999" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">CPF</label>
                        <input type="text" class="form-control"
                               value="<?php echo htmlspecialchars($u['cpf'] ?? ''); ?>" disabled>
                    </div>

                    <hr class="my-3">
                    <p class="fw-semibold small text-muted mb-2"><i class="fas fa-lock me-1"></i>Alterar senha (deixe em branco para não alterar)</p>

                    <div class="mb-3">
                        <label class="form-label">Senha atual</label>
                        <input type="password" name="senha_atual" class="form-control" autocomplete="current-password">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Nova senha</label>
                            <input type="password" name="nova_senha" class="form-control" minlength="6" autocomplete="new-password">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Confirmar</label>
                            <input type="password" name="confirmar_senha" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Veículo e Operação ───────────────────────────────── -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-truck-pickup me-2"></i>Veículo e Operação</div>
                <div class="card-body">

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Placa do Guincho <span class="text-danger">*</span></label>
                            <input type="text" name="placa_guincho" class="form-control text-uppercase"
                                   value="<?php echo htmlspecialchars($g['placa_guincho'] ?? ''); ?>"
                                   maxlength="8" required placeholder="ABC1234">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Capacidade (toneladas) <span class="text-danger">*</span></label>
                            <input type="number" name="capacidade_ton" class="form-control"
                                   value="<?php echo htmlspecialchars($g['capacidade_ton'] ?? ''); ?>"
                                   step="0.5" min="0.5" max="100" required>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Raio de cobertura (km) <span class="text-danger">*</span></label>
                        <input type="range" name="raio_cobertura_km" class="form-range" id="raioRange"
                               min="5" max="100" step="5"
                               value="<?php echo (int)($g['raio_cobertura_km'] ?? 20); ?>">
                        <div class="d-flex justify-content-between small text-muted">
                            <span>5 km</span>
                            <strong id="raioLabel"><?php echo (int)($g['raio_cobertura_km'] ?? 20); ?> km</strong>
                            <span>100 km</span>
                        </div>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label class="form-label">Número da CNH</label>
                            <input type="text" name="cnh_numero" class="form-control"
                                   value="<?php echo htmlspecialchars($g['cnh_numero'] ?? ''); ?>"
                                   placeholder="00000000000">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Validade da CNH</label>
                            <input type="date" name="cnh_validade" class="form-control"
                                   value="<?php echo htmlspecialchars($g['cnh_validade'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Status somente leitura -->
                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <label class="form-label">Aprovação</label>
                            <div class="form-control-plaintext">
                                <?php if ($g['aprovado']): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Aprovado</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Aguardando</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Nota Média</label>
                            <div class="form-control-plaintext fw-bold text-warning">
                                <?php $rep = (float)($g['reputacao'] ?? 0);
                                      echo $rep > 0 ? '⭐ ' . number_format($rep, 1) . ' (' . (int)($g['total_avaliacoes'] ?? 0) . ' avaliações)' : '—'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Dados Bancários / PIX ────────────────────────── -->
            <div class="card">
                <div class="card-header"><i class="fas fa-qrcode me-2"></i>Dados Bancários (PIX)</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tipo da chave PIX <span class="text-danger">*</span></label>
                        <select name="chave_pix_tipo" class="form-select" required>
                            <?php foreach ($tiposPix as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo ($g['chave_pix_tipo'] ?? '') === $val ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Chave PIX <span class="text-danger">*</span></label>
                        <input type="text" name="chave_pix" class="form-control"
                               value="<?php echo htmlspecialchars($g['chave_pix'] ?? ''); ?>"
                               placeholder="Sua chave PIX" required>
                        <div class="form-text"><i class="fas fa-info-circle me-1"></i>Os pagamentos serão enviados para esta chave após cada corrida concluída.</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /row -->

    <div class="d-flex justify-content-end gap-3 mt-4 mb-4">
        <a href="<?php echo $bp; ?>/guincho/dashboard" class="btn btn-outline-secondary px-4">
            <i class="fas fa-arrow-left me-2"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-primary px-5">
            <i class="fas fa-floppy-disk me-2"></i>Salvar Alterações
        </button>
    </div>

    </form>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<script>
const raioRange = document.getElementById('raioRange');
const raioLabel = document.getElementById('raioLabel');
if (raioRange) {
    raioRange.addEventListener('input', () => raioLabel.textContent = raioRange.value + ' km');
}
</script>
