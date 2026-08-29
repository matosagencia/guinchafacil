<?php
$bp  = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$statusBadge = [
    'PENDING'   => 'text-bg-warning',
    'APPROVED'  => 'text-bg-success',
    'SUSPENDED' => 'text-bg-secondary',
    'REJECTED'  => 'text-bg-danger',
];
$statusLabel = [
    'PENDING'   => 'Em análise',
    'APPROVED'  => 'Aprovada',
    'SUSPENDED' => 'Suspensa',
    'REJECTED'  => 'Rejeitada',
];
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">
    <div class="mini-shell">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Operação</span>
                <h1><i class="fas fa-toolbox me-2 text-primary-custom"></i>Quais serviços você oferece?</h1>
                <p>Marque os serviços que você consegue atender. Cada capacidade passa por análise antes de começar a receber ofertas desse tipo.</p>
            </div>
        </header>

        <?php foreach (($flashMsg ?? []) as $flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <form method="post" action="<?php echo $bp; ?>/guincho/capacidades/salvar">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">

            <div class="row g-3">
                <?php foreach ($tiposServico as $t): ?>
                <?php $minha = $capacidadesPorTipo[(int)$t['id']] ?? null; ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="service_type_id[]"
                                       id="svc<?php echo (int)$t['id']; ?>" value="<?php echo (int)$t['id']; ?>"
                                       <?php echo $minha ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="svc<?php echo (int)$t['id']; ?>">
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </label>
                                <?php if ($minha): ?>
                                <span class="badge <?php echo $statusBadge[$minha['approval_status']] ?? 'text-bg-secondary'; ?> ms-2">
                                    <?php echo $statusLabel[$minha['approval_status']] ?? $minha['approval_status']; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="small text-muted mb-2 mt-1"><?php echo htmlspecialchars((string)($t['description'] ?? '')); ?></p>
                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small mb-0">Raio de atendimento (km)</label>
                                    <input type="number" min="0" class="form-control form-control-sm"
                                           name="coverage_radius_km[<?php echo (int)$t['id']; ?>]"
                                           value="<?php echo htmlspecialchars((string)($minha['coverage_radius_km'] ?? '')); ?>"
                                           placeholder="Até onde você atende este serviço">
                                </div>
                                <div class="col-12 col-md-6 d-flex align-items-end">
                                    <p class="small text-muted mb-1">
                                        <i class="fas fa-circle-info me-1"></i>O preço deste serviço é definido pela GuinchaFácil (não por você).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Enviar para análise</button>
            </div>
        </form>
    </div>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
