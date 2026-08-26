<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$tipoIcons = ['moto'=>'motorcycle','caminhao'=>'truck','van'=>'shuttle-van','carro'=>'car'];
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-car me-2" style="color:var(--primary)"></i>Meus Veículos
            </div>
            <div class="page-subtitle"><?php echo count($veiculos ?? []); ?> veículo(s) cadastrado(s)</div>
        </div>
        <a href="<?php echo $bp; ?>/cliente/veiculo/novo" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Adicionar Veículo
        </a>
    </div>

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
            <div class="card h-100" style="border-top:3px solid var(--primary)">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon" style="margin:0;flex-shrink:0;width:48px;height:48px;font-size:1.2rem">
                            <i class="fas fa-<?php echo $icon; ?>"></i>
                        </div>
                        <div class="flex-fill min-w-0">
                            <div style="font-weight:700;color:var(--theme-text);font-size:.95rem">
                                <?php echo htmlspecialchars($v['marca'].' '.$v['modelo']); ?>
                            </div>
                            <span class="badge badge-aguardando_pagamento" style="font-size:.71rem;margin-top:2px">
                                <?php echo htmlspecialchars($v['placa']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3" style="font-size:.8rem;color:var(--theme-muted)">
                        <span><i class="fas fa-calendar me-1"></i><?php echo htmlspecialchars($v['ano']); ?></span>
                        <span><i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($v['cor']); ?></span>
                        <?php if (!empty($v['tipo'])): ?>
                        <span><i class="fas fa-tag me-1"></i><?php echo ucfirst($v['tipo']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?php echo $bp; ?>/cliente/veiculo/editar/<?php echo (int)$v['id']; ?>"
                           class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="fas fa-edit me-1"></i>Editar
                        </a>
                        <form method="POST" action="<?php echo $bp; ?>/cliente/veiculo/deletar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger w-100"
                                    onclick="return confirm('Remover <?php echo addslashes(htmlspecialchars($v['marca'].' '.$v['modelo'])); ?>?')">
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
            <i class="fas fa-car-side fa-3x d-block mb-3" style="opacity:.2;color:var(--theme-muted)"></i>
            <h5 style="color:var(--theme-text);font-weight:700">Nenhum veículo cadastrado</h5>
            <p style="color:var(--theme-muted);font-size:.9rem;max-width:380px;margin:0 auto 1.5rem">
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
