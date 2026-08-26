<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-gear me-2 text-primary-custom"></i>Configurações do Sistema</div>
            <div class="page-subtitle">Parâmetros de tarifas e comissões</div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-sliders me-2"></i>Tarifas</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/configuracoes/salvar">
                        <?php if (!empty($csrf_token)): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Taxa por Km (R$)</label>
                            <input type="number" step="0.01" class="form-control" name="tarifa_por_km"
                                   value="<?php echo htmlspecialchars($config['tarifa_por_km'] ?? '5.00'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Taxa Fixa (R$)</label>
                            <input type="number" step="0.01" class="form-control" name="taxa_fixa"
                                   value="<?php echo htmlspecialchars($config['taxa_fixa'] ?? '10.00'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comissão Plataforma (decimal 0 a 1)</label>
                            <input type="number" step="0.1" class="form-control" min="0.01" max="0.99" name="comissao_plataforma"
                                   value="<?php echo htmlspecialchars($config['comissao_plataforma'] ?? '0.15'); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Salvar Configurações
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-credit-card me-2"></i>Gateway de Pagamento</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/configuracoes/salvar">
                        <?php if (!empty($csrf_token)): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Gateway ativo</label>
                            <select class="form-select" name="gateway_pagamento">
                                <option value="mercadopago" <?php echo ($config['gateway_pagamento'] ?? 'mercadopago') === 'mercadopago' ? 'selected' : ''; ?>>Mercado Pago</option>
                                <option value="pagseguro"   <?php echo ($config['gateway_pagamento'] ?? '') === 'pagseguro' ? 'selected' : ''; ?>>PagSeguro</option>
                            </select>
                            <div class="form-text">Define qual gateway será oferecido na tela de checkout.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Salvar Gateway
                        </button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Informações</div>
                <div class="card-body">
                    <p style="color:var(--theme-muted);font-size:.88rem;">
                        Alterações nas configurações de tarifa serão aplicadas em todos os novos pedidos imediatamente.
                        Pedidos já criados não serão afetados.
                    </p>
                    <hr style="border-color:var(--theme-border)">
                    <small style="color:var(--theme-muted)">Última alteração: <?php echo date('d/m/Y H:i'); ?></small>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
