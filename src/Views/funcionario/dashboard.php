<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; include __DIR__ . '/../layouts/header.php'; ?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_funcionario.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Atendimento</span>
            <h1><i class="fas fa-headset me-2 text-primary-custom"></i>Painel do funcionário</h1>
            <p>Você cria demandas de cancelamento, conclusão manual, pagamento, alteração de dados e reembolso — um gerente aprova ou rejeita antes de qualquer ação real acontecer.</p>
        </div>
    </header>

    <div class="alert alert-light border mb-4">
        <i class="fas fa-shield-halved me-2"></i>
        <strong>Como funciona:</strong> nada que você solicitar aqui é executado na hora. Toda demanda fica <span class="badge bg-secondary">pendente</span> até um gerente revisar. Demandas de maior valor ou que alteram dados sensíveis exigem dois gerentes diferentes.
    </div>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['pendente', 'Pendentes', 'fa-hourglass-half', 'warning'],
            ['aprovada_parcial', 'Aguardando 2ª aprovação', 'fa-user-clock', 'info'],
            ['aprovada', 'Aprovadas', 'fa-check', 'primary'],
            ['executada', 'Executadas', 'fa-flag-checkered', 'success'],
            ['rejeitada', 'Rejeitadas', 'fa-xmark', 'danger'],
            ['falhou', 'Falharam na execução', 'fa-triangle-exclamation', 'dark'],
        ];
        foreach ($cards as [$key, $label, $icon, $cor]):
        ?>
        <div class="col-6 col-xl-2">
            <div class="stat-card">
                <div class="stat-icon text-<?php echo $cor; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                <div class="stat-value"><?php echo (int)($resumo[$key] ?? 0); ?></div>
                <div class="stat-label"><?php echo htmlspecialchars($label); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <section class="fin-card p-4 p-lg-5">
        <h3 class="mb-3 fin-subtitle"><i class="fas fa-clock-rotate-left me-2 text-primary-custom"></i>Minhas últimas demandas</h3>
        <?php if (empty($minhas)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
            Nenhuma demanda criada ainda. Vá em <a href="<?php echo $bp; ?>/funcionario/pedidos">Pedidos</a> ou <a href="<?php echo $bp; ?>/funcionario/financeiro">Financeiro</a> para solicitar uma ação.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>#</th><th>Tipo</th><th>Pedido</th><th>Status</th><th>Criada em</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($minhas, 0, 10) as $d): ?>
                    <tr>
                        <td>#<?php echo (int)$d['id']; ?></td>
                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$d['tipo']))); ?></td>
                        <td><?php echo $d['pedido_id'] ? '#' . (int)$d['pedido_id'] : '—'; ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars((string)$d['status']); ?></span></td>
                        <td><?php echo date('d/m/Y H:i', strtotime((string)$d['criado_em'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
