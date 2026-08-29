<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$editando = !empty($produto['id']);
$cats = [
    'bateria' => 'Bateria', 'pneu' => 'Pneu', 'combustivel' => 'Combustível',
    'chaveiro' => 'Chaveiro', 'eletrica' => 'Elétrica', 'fluido' => 'Fluido', 'outro' => 'Outro',
];
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Estoque</span>
            <h1><?php echo $editando ? 'Editar produto' : 'Novo produto'; ?></h1>
        </div>
        <a href="<?php echo $bp; ?>/admin/produtos" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </header>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/produto/salvar" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                        <?php if ($editando): ?><input type="hidden" name="id" value="<?php echo (int)$produto['id']; ?>"><?php endif; ?>

                        <div class="col-md-4">
                            <label class="form-label">SKU *</label>
                            <input type="text" class="form-control text-uppercase" name="sku" required
                                   value="<?php echo htmlspecialchars($produto['sku'] ?? ''); ?>"
                                   <?php echo $editando ? 'readonly' : ''; ?> placeholder="BAT-60AH">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" name="nome" required
                                   value="<?php echo htmlspecialchars($produto['nome'] ?? ''); ?>" placeholder="Bateria 60Ah">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Categoria</label>
                            <select name="categoria" class="form-select">
                                <?php $catSel = $produto['categoria'] ?? 'bateria';
                                foreach ($cats as $val => $lbl): ?>
                                <option value="<?php echo $val; ?>" <?php echo $catSel === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Especificação</label>
                            <input type="text" class="form-control" name="especificacao"
                                   value="<?php echo htmlspecialchars($produto['especificacao'] ?? ''); ?>" placeholder="60Ah 12V">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preço de referência (R$)</label>
                            <input type="text" class="form-control" name="preco_referencia"
                                   value="<?php echo htmlspecialchars($produto['preco_referencia'] !== null ? number_format((float)($produto['preco_referencia'] ?? 0), 2, ',', '.') : ''); ?>" placeholder="520,00">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Descrição</label>
                            <input type="text" class="form-control" name="descricao"
                                   value="<?php echo htmlspecialchars($produto['descricao'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Unidade</label>
                            <input type="text" class="form-control" name="unidade"
                                   value="<?php echo htmlspecialchars($produto['unidade'] ?? 'un'); ?>">
                        </div>
                        <div class="col-md-2 form-check ms-2 align-self-end">
                            <input type="checkbox" class="form-check-input" id="active" name="active" value="1"
                                   <?php echo (!$editando || !empty($produto['active'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="active">Ativo</label>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
