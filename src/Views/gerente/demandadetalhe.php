<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$payload = $payload ?? [];
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_gerente.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gerência</span>
            <h1><i class="fas fa-file-signature me-2 text-primary-custom"></i>Demanda #<?php echo (int)$demanda['id']; ?></h1>
            <p><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$demanda['tipo']))); ?> — solicitada por <strong><?php echo htmlspecialchars((string)$demanda['solicitante_nome']); ?></strong> em <?php echo date('d/m/Y H:i', strtotime((string)$demanda['criado_em'])); ?></p>
        </div>
    </header>

    <?php if (!empty($_SESSION['_flash'])): $f = $_SESSION['_flash']; unset($_SESSION['_flash']); ?>
    <div class="alert alert-<?php echo ($f['type'] ?? 'info') === 'error' ? 'danger' : 'success'; ?> mb-3"><?php echo htmlspecialchars((string)$f['message']); ?></div>
    <?php endif; ?>

    <section class="fin-card p-4 p-lg-5 mb-4">
        <h3 class="fin-subtitle mb-3">Detalhes</h3>
        <table class="table table-sm">
            <tr><th style="width:220px">Pedido</th><td><?php echo $demanda['pedido_id'] ? '#' . (int)$demanda['pedido_id'] . ' — ' . htmlspecialchars((string)($demanda['endereco_origem'] ?? '')) : '—'; ?></td></tr>
            <tr><th>Status do pedido</th><td><?php echo htmlspecialchars((string)($demanda['pedido_status'] ?? '—')); ?></td></tr>
            <tr><th>Valor envolvido</th><td><?php echo $demanda['valor_envolvido'] !== null ? 'R$ ' . number_format((float)$demanda['valor_envolvido'], 2, ',', '.') : '—'; ?></td></tr>
            <tr><th>Exige dupla aprovação?</th><td><?php echo !empty($demanda['requer_dupla_aprovacao']) ? 'Sim — precisa de dois gerentes diferentes' : 'Não'; ?></td></tr>
            <tr><th>Justificativa do funcionário</th><td><?php echo nl2br(htmlspecialchars((string)$demanda['justificativa'])); ?></td></tr>
            <?php if (!empty($demanda['gerente_nome'])): ?>
            <tr><th>1ª decisão</th><td><?php echo htmlspecialchars((string)$demanda['gerente_nome']); ?> em <?php echo date('d/m/Y H:i', strtotime((string)$demanda['decidido_em'])); ?> — <?php echo htmlspecialchars((string)$demanda['nota_gerente']); ?></td></tr>
            <?php endif; ?>
        </table>

        <?php if ($demanda['tipo'] === 'alteracao_dados' && !empty($payload)): ?>
        <div class="alert alert-warning">
            <strong>Alteração solicitada:</strong> campo <code><?php echo htmlspecialchars((string)($payload['campo'] ?? '')); ?></code>
            → novo valor: <code><?php echo htmlspecialchars((string)($payload['valor_novo'] ?? '')); ?></code>
        </div>
        <?php endif; ?>

        <?php if ($demanda['tipo'] === 'conclusao_manual' && !empty($payload['comprovantes'])): ?>
        <div class="row g-3">
            <?php foreach ($payload['comprovantes'] as $c): ?>
            <div class="col-md-6">
                <p class="small text-muted mb-1">Comprovante de <?php echo htmlspecialchars((string)$c['tipo']); ?></p>
                <?php if (is_file((string)($c['stored_path'] ?? ''))): ?>
                <img src="data:image;base64,<?php echo base64_encode((string)file_get_contents($c['stored_path'])); ?>" class="img-thumbnail" style="max-height:220px">
                <?php else: ?>
                <span class="text-muted">Arquivo não encontrado.</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php if (in_array($demanda['status'], ['pendente', 'aprovada_parcial'], true)): ?>
    <section class="fin-card p-4 p-lg-5" style="max-width:640px">
        <h3 class="fin-subtitle mb-3"><i class="fas fa-gavel me-2 text-primary-custom"></i>Sua decisão</h3>
        <form method="POST" action="<?php echo $bp; ?>/gerente/demanda/decidir">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="demanda_id" value="<?php echo (int)$demanda['id']; ?>">

            <div class="mb-3">
                <label class="form-label">Nota da decisão (obrigatória, mínimo 10 caracteres)</label>
                <textarea class="form-control" name="nota" minlength="10" required rows="3" placeholder="O que você verificou antes de decidir?"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Sua senha (confirmação)</label>
                <input type="password" class="form-control" name="senha" required>
                <small class="text-muted">Necessário para auditoria — toda aprovação/rejeição fica registrada com seu nome e horário.</small>
            </div>
            <div class="btn-group w-100" role="group">
                <button type="submit" name="veredito" value="aprovar" class="btn btn-outline-success"><i class="fas fa-check me-1"></i>Aprovar</button>
                <button type="submit" name="veredito" value="rejeitar" class="btn btn-outline-danger"><i class="fas fa-xmark me-1"></i>Rejeitar</button>
            </div>
        </form>
    </section>
    <?php else: ?>
    <div class="alert alert-secondary">Esta demanda já foi decidida (<?php echo htmlspecialchars((string)$demanda['status']); ?>) e não pode ser alterada.</div>
    <?php endif; ?>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
