<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$editando = !empty($servico);
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gestão</span>
            <h1><?php echo $editando ? 'Editar serviço' : 'Novo serviço'; ?></h1>
            <p>Este item aparece como atalho no painel do cliente.</p>
        </div>
        <a href="<?php echo $bp; ?>/admin/servicos" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </header>

    <?php if (!empty($_GET['erro'])): ?>
        <div class="alert alert-danger mb-3">Preencha ao menos a chave e o nome do serviço.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/servico/salvar">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                <?php if ($editando): ?><input type="hidden" name="id" value="<?php echo (int)$servico['id']; ?>"><?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Chave interna (sem espaços)</label>
                        <input type="text" class="form-control" name="chave" required maxlength="40"
                               value="<?php echo htmlspecialchars((string)($servico['chave'] ?? '')); ?>"
                               placeholder="ex: reboque_agora">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nome exibido</label>
                        <input type="text" class="form-control" name="nome" required maxlength="80"
                               value="<?php echo htmlspecialchars((string)($servico['nome'] ?? '')); ?>"
                               placeholder="ex: Reboque agora">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrição curta</label>
                        <input type="text" class="form-control" name="descricao" maxlength="160"
                               value="<?php echo htmlspecialchars((string)($servico['descricao'] ?? '')); ?>"
                               placeholder="ex: Atendimento imediato">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo de problema (pré-seleciona no pedido)</label>
                        <?php $tipoAtual = (string)($servico['tipo_problema'] ?? 'outro'); ?>
                        <select class="form-select" name="tipo_problema">
                            <option value="outro" <?php echo $tipoAtual === 'outro' ? 'selected' : ''; ?>>Outro / genérico</option>
                            <option value="bateria" <?php echo $tipoAtual === 'bateria' ? 'selected' : ''; ?>>Bateria</option>
                            <option value="pneu" <?php echo $tipoAtual === 'pneu' ? 'selected' : ''; ?>>Pneu</option>
                            <option value="combustivel" <?php echo $tipoAtual === 'combustivel' ? 'selected' : ''; ?>>Combustível</option>
                            <option value="colisao" <?php echo $tipoAtual === 'colisao' ? 'selected' : ''; ?>>Colisão/Acidente</option>
                            <option value="eletrica" <?php echo $tipoAtual === 'eletrica' ? 'selected' : ''; ?>>Elétrica</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ícone (Font Awesome, ex: fa-bolt)</label>
                        <input type="text" class="form-control" name="icone" maxlength="60"
                               value="<?php echo htmlspecialchars((string)($servico['icone'] ?? 'fa-truck-pickup')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cor do ícone</label>
                        <?php $corAtual = (string)($servico['cor'] ?? 'tow'); ?>
                        <select class="form-select" name="cor">
                            <option value="tow" <?php echo $corAtual === 'tow' ? 'selected' : ''; ?>>Verde</option>
                            <option value="battery" <?php echo $corAtual === 'battery' ? 'selected' : ''; ?>>Amarelo</option>
                            <option value="tire" <?php echo $corAtual === 'tire' ? 'selected' : ''; ?>>Azul</option>
                            <option value="fuel" <?php echo $corAtual === 'fuel' ? 'selected' : ''; ?>>Laranja</option>
                            <option value="schedule" <?php echo $corAtual === 'schedule' ? 'selected' : ''; ?>>Verde (alternativo)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ordem de exibição</label>
                        <input type="number" class="form-control" name="ordem" min="1" max="999"
                               value="<?php echo (int)($servico['ordem'] ?? 100); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativoCheck" value="1"
                                   <?php echo (!isset($servico) || !empty($servico['ativo'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativoCheck">Visível no painel do cliente</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-4">
                    <i class="fas fa-floppy-disk me-1"></i>Salvar
                </button>
            </form>
        </div>
    </div>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
