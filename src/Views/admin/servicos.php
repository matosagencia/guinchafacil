<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$corLabels = ['tow' => 'Verde', 'battery' => 'Amarelo', 'tire' => 'Azul', 'fuel' => 'Laranja', 'schedule' => 'Verde'];
$tipoLabels = [
    'eletrica' => 'Problema elétrico',
    'pneu' => 'Pneu',
    'colisao' => 'Colisão/Acidente',
    'bateria' => 'Bateria',
    'combustivel' => 'Combustível',
    'outro' => 'Outro',
];
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gestão</span>
            <h1>Catálogo de serviços</h1>
            <p>Atalhos exibidos no painel do cliente (Reboque, Bateria, Pneu, etc.) — editáveis aqui, sem precisar mexer em código.</p>
        </div>
        <a href="<?php echo $bp; ?>/admin/servico/novo" class="btn btn-primary btn-sm">
            <i class="fas fa-circle-plus me-1"></i>Novo serviço
        </a>
    </header>

    <?php if (!empty($_GET['salvo'])): ?>
        <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Serviço salvo com sucesso.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['removido'])): ?>
        <div class="alert alert-info mb-3"><i class="fas fa-check-circle me-2"></i>Serviço removido.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Ordem</th>
                            <th>Ícone</th>
                            <th>Nome</th>
                            <th>Tipo de problema</th>
                            <th>Cor</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($servicos)): ?>
                        <tr><td colspan="7" class="text-center p-4 text-muted small">Nenhum serviço cadastrado ainda.</td></tr>
                        <?php else: foreach ($servicos as $s): ?>
                        <tr>
                            <td><?php echo (int)$s['ordem']; ?></td>
                            <td><i class="fas <?php echo htmlspecialchars($s['icone']); ?>"></i></td>
                            <td>
                                <strong><?php echo htmlspecialchars($s['nome']); ?></strong>
                                <?php if (!empty($s['descricao'])): ?><br><span class="small text-muted"><?php echo htmlspecialchars($s['descricao']); ?></span><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($tipoLabels[$s['tipo_problema']] ?? $s['tipo_problema']); ?></td>
                            <td><?php echo htmlspecialchars($corLabels[$s['cor']] ?? $s['cor']); ?></td>
                            <td>
                                <span class="badge <?php echo $s['ativo'] ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?php echo $s['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo $bp; ?>/admin/servico/novo?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form method="post" action="<?php echo $bp; ?>/admin/servico/alternar" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Ativar/Desativar">
                                        <i class="fas fa-toggle-on"></i>
                                    </button>
                                </form>
                                <form method="post" action="<?php echo $bp; ?>/admin/servico/remover" class="d-inline" onsubmit="return confirm('Remover este serviço do catálogo?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
