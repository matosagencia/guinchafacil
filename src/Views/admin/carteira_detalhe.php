<?php
/**
 * Extrato detalhado de um guincheiro — a "prova" linha a linha por trás do
 * número agregado da tela /admin/carteiras (pedido explícito do usuário:
 * "quero tudo debugável nos mínimos detalhes").
 *
 * @var array $guincho
 * @var array $saldo   CarteiraService::saldoGuincho()
 * @var array $extrato CarteiraService::extratoGuincho()
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$statusPixLabel = ['pendente' => 'Pendente', 'processando' => 'Processando', 'concluido' => 'Concluído', 'falha' => 'Falha', 'falha_permanente' => 'Falha permanente'];
$statusPixBadge = ['pendente' => 'bg-secondary', 'processando' => 'bg-info text-dark', 'concluido' => 'bg-success', 'falha' => 'bg-danger', 'falha_permanente' => 'bg-danger'];
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Financeiro &bull; Carteiras</span>
            <h1><i class="fas fa-wallet me-2 text-primary-custom"></i><?php echo htmlspecialchars((string)$guincho['nome_operador']); ?></h1>
            <p><?php echo htmlspecialchars((string)($guincho['placa_guincho'] ?? '')); ?> &bull; <?php echo htmlspecialchars((string)($guincho['telefone'] ?? '')); ?></p>
        </div>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/carteiras" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </header>

    <?php if (!$saldo['ok']): ?>
    <div class="alert alert-danger">
        <i class="fas fa-triangle-exclamation me-2"></i>
        <strong>Falha ao calcular o saldo deste guincheiro.</strong> Detalhe técnico:
        <code><?php echo htmlspecialchars((string)($saldo['erro'] ?? '')); ?></code>
    </div>
    <?php else: ?>
    <div class="stat-grid mb-4">
        <div class="stat-grid__item">
            <span class="stat-grid__label">Em compensação</span>
            <strong class="stat-grid__value">R$ <?php echo number_format($saldo['saldo_em_compensacao'], 2, ',', '.'); ?></strong>
        </div>
        <div class="stat-grid__item is-success">
            <span class="stat-grid__label">Pago</span>
            <strong class="stat-grid__value">R$ <?php echo number_format($saldo['saldo_pago'], 2, ',', '.'); ?></strong>
        </div>
        <div class="stat-grid__item">
            <span class="stat-grid__label">Estornado</span>
            <strong class="stat-grid__value">R$ <?php echo number_format($saldo['saldo_estornado'], 2, ',', '.'); ?></strong>
        </div>
        <div class="stat-grid__item">
            <span class="stat-grid__label">Último repasse</span>
            <strong class="stat-grid__value" style="font-size:1.05rem;"><?php echo htmlspecialchars((string)($saldo['ultimo_repasse_em'] ?? '—')); ?></strong>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$extrato['ok']): ?>
    <div class="alert alert-danger">
        <i class="fas fa-triangle-exclamation me-2"></i>Falha ao carregar o extrato: <?php echo htmlspecialchars((string)($extrato['erro'] ?? '')); ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><strong>Extrato (linha a linha, mais recente primeiro)</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Status pedido</th>
                            <th>Valor total</th>
                            <th>Valor guincho</th>
                            <th>Status Pix</th>
                            <th>Transação Pix</th>
                            <th>Pago em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($extrato['linhas'])): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum pedido encontrado para este guincheiro.</td></tr>
                        <?php else: ?>
                        <?php foreach ($extrato['linhas'] as $r): ?>
                        <tr>
                            <td><a href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/<?php echo (int)$r['pedido_id']; ?>">#<?php echo (int)$r['pedido_id']; ?></a></td>
                            <td class="small text-muted"><?php echo htmlspecialchars((string)$r['pedido_status']); ?></td>
                            <td><?php echo $r['valor_total'] !== null ? 'R$ ' . number_format((float)$r['valor_total'], 2, ',', '.') : '—'; ?></td>
                            <td><?php echo $r['valor_guincho'] !== null ? 'R$ ' . number_format((float)$r['valor_guincho'], 2, ',', '.') : '—'; ?></td>
                            <td>
                                <?php if ($r['status_pix']): ?>
                                <span class="badge <?php echo $statusPixBadge[$r['status_pix']] ?? 'bg-secondary'; ?>"><?php echo htmlspecialchars($statusPixLabel[$r['status_pix']] ?? $r['status_pix']); ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="small text-muted"><?php echo htmlspecialchars((string)($r['id_transacao_pix'] ?? '—')); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars((string)($r['data_pagamento_guincho'] ?? '—')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
