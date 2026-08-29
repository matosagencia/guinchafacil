<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Atendimento</span>
            <h1><i class="fas fa-boxes-stacked me-2"></i>Meu estoque</h1>
            <p>Quantidade e preço dos produtos que você tem disponível (ex.: baterias). Usado nos orçamentos de socorro no local.</p>
        </div>
    </header>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="fas fa-box me-2"></i>Itens em estoque</div>
        <div class="card-body p-0">
            <?php if (empty($meuEstoque)): ?>
            <div class="p-3 text-muted small">Você ainda não tem produtos em estoque. Adicione abaixo.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr>
                        <th>Produto</th><th>Espec.</th><th style="width:120px">Quantidade</th>
                        <th style="width:150px">Preço venda (R$)</th><th style="width:90px"></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($meuEstoque as $e): ?>
                        <tr>
                            <form method="POST" action="<?php echo $bp; ?>/guincho/estoque/salvar">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="produto_id" value="<?php echo (int)$e['produto_id']; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($e['nome']); ?></strong>
                                    <div class="text-muted small"><?php echo htmlspecialchars($e['sku']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars((string)($e['especificacao'] ?? '')); ?></td>
                                <td><input type="number" min="0" step="1" class="form-control form-control-sm" name="quantidade" value="<?php echo (int)$e['quantidade']; ?>"></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="preco_venda"
                                           value="<?php echo $e['preco_venda'] !== null ? number_format((float)$e['preco_venda'], 2, ',', '.') : ''; ?>"
                                           placeholder="ref. <?php echo $e['preco_referencia'] !== null ? number_format((float)$e['preco_referencia'], 2, ',', '.') : '—'; ?>">
                                </td>
                                <td><button class="btn btn-sm btn-primary"><i class="fas fa-save"></i></button></td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($disponiveis)): ?>
    <div class="card">
        <div class="card-header fw-bold"><i class="fas fa-plus me-2"></i>Adicionar produto ao estoque</div>
        <div class="card-body">
            <form method="POST" action="<?php echo $bp; ?>/guincho/estoque/salvar" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="col-md-5">
                    <label class="form-label mb-1">Produto</label>
                    <select name="produto_id" class="form-select" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($disponiveis as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['nome'] . ' (' . $p['sku'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Quantidade</label>
                    <input type="number" min="0" step="1" class="form-control" name="quantidade" value="1">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Preço venda (R$)</label>
                    <input type="text" class="form-control" name="preco_venda" placeholder="opcional">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100"><i class="fas fa-plus"></i></button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
