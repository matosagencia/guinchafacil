<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$tipoIcons = ['moto'=>'motorcycle','caminhao'=>'truck','van'=>'shuttle-van','carro'=>'car'];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-veiculos.css">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Conta</span>
            <h1><i class="fas fa-car me-2 veiculo-icon-accent"></i>Meus Veículos</h1>
            <p><?php echo count($veiculos ?? []); ?> veículo(s) cadastrado(s)</p>
        </div>
        <a href="<?php echo $bp; ?>/cliente/veiculo/novo" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Adicionar Veículo
        </a>
    </header>

    <?php if (!empty($_GET['salvo'])): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Veículo salvo com sucesso!</div>
    <?php endif; ?>
    <?php if (!empty($_GET['deletado'])): ?>
    <div class="alert alert-info mb-3"><i class="fas fa-trash me-2"></i>Veículo removido.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Erro ao salvar. Verifique os dados.</div>
    <?php endif; ?>

    <?php if (!empty($veiculos)): ?>
    <div class="row g-3">
        <?php foreach ($veiculos as $v): ?>
        <?php $icon = $tipoIcons[$v['tipo'] ?? 'carro'] ?? 'car'; ?>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100 veiculo-card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon veiculo-icon">
                            <i class="fas fa-<?php echo $icon; ?>"></i>
                        </div>
                        <div class="flex-fill min-w-0">
                            <div class="veiculo-nome">
                                <?php echo htmlspecialchars($v['marca'].' '.$v['modelo']); ?>
                            </div>
                            <span class="badge badge-aguardando_pagamento veiculo-placa-badge">
                                <?php echo htmlspecialchars($v['placa']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3 veiculo-meta-row">
                        <span><i class="fas fa-calendar me-1"></i><?php echo htmlspecialchars($v['ano']); ?></span>
                        <span><i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($v['cor']); ?></span>
                        <?php if (!empty($v['tipo'])): ?>
                        <span><i class="fas fa-tag me-1"></i><?php echo ucfirst($v['tipo']); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($v['cidade_placa']) || !empty($v['uf_placa'])): ?>
                    <div class="mb-3 veiculo-meta-row">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        Emplacamento:
                        <?php echo htmlspecialchars(trim(($v['cidade_placa'] ?? '') . (!empty($v['uf_placa']) ? ' / ' . $v['uf_placa'] : ''))); ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <a href="<?php echo $bp; ?>/cliente/veiculo/editar/<?php echo (int)$v['id']; ?>"
                           class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="fas fa-edit me-1"></i>Editar
                        </a>
                        <form method="POST" action="<?php echo $bp; ?>/cliente/veiculo/deletar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger w-100"
                                    data-confirm-message="Remover <?php echo htmlspecialchars($v['marca'].' '.$v['modelo'], ENT_QUOTES, 'UTF-8'); ?>?">
                                <i class="fas fa-trash me-1"></i>Excluir
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-car-side fa-3x d-block mb-3 veiculo-empty-icon"></i>
            <h5 class="veiculo-empty-title">Nenhum veículo cadastrado</h5>
            <p class="veiculo-empty-subtitle">
                Cadastre seu veículo para poder solicitar reboque.
            </p>
            <a href="<?php echo $bp; ?>/cliente/veiculo/novo" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Adicionar Primeiro Veículo
            </a>
        </div>
    </div>
    <?php endif; ?>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
