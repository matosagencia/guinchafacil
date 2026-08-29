<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Parceiro</span>
            <h1><i class="fas fa-truck-pickup me-2"></i>Tornar-se guincheiro</h1>
            <p>Você já atende como especialista. Adicione o reboque para receber também chamados de guincho.</p>
        </div>
    </header>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <?php if ($jaEhGuincho): ?>
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-circle-check fa-2x text-success mb-2"></i>
                    <h5>Você já é guincheiro aprovado</h5>
                    <p class="text-muted mb-0">Você recebe chamados de reboque normalmente. Ajuste seu raio em "Serviços que ofereço".</p>
                </div>
            </div>

            <?php elseif ($emAnalise): ?>
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-hourglass-half fa-2x text-warning mb-2"></i>
                    <h5>Solicitação em análise</h5>
                    <p class="text-muted mb-0">Recebemos seus dados de reboque. Assim que o admin aprovar, você passa a receber chamados de guincho. Você continua atendendo seus serviços de especialista normalmente enquanto isso.</p>
                </div>
            </div>

            <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <p class="mb-2"><i class="fas fa-circle-info me-1 text-primary"></i>Para virar guincheiro, informe os dados do seu reboque e a CNH. Nossa equipe confere e libera o reboque para você.</p>
                    <p class="small text-muted mb-0">Sem mensalidade. Você não perde seus serviços de especialista — só ganha os chamados de reboque também.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-bold"><i class="fas fa-truck me-2"></i>Dados do reboque</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/guincho/tornar-se-guincho/salvar" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="col-md-4">
                            <label class="form-label">Placa do reboque *</label>
                            <input type="text" class="form-control text-uppercase" name="placa_guincho" required placeholder="ABC1D23" maxlength="8">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Cidade do emplacamento</label>
                            <input type="text" class="form-control" name="cidade_placa" placeholder="Ex: Rio de Janeiro">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">UF</label>
                            <input type="text" class="form-control text-uppercase" name="uf_placa" maxlength="2" placeholder="RJ">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Capacidade (toneladas) *</label>
                            <input type="number" step="0.1" min="0" class="form-control" name="capacidade_ton" required placeholder="Ex: 8">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Número da CNH *</label>
                            <input type="text" class="form-control" name="cnh_numero" required placeholder="Só números">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Validade da CNH *</label>
                            <input type="date" class="form-control" name="cnh_validade" required>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Enviar para aprovação</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
