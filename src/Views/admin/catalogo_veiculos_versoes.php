<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();


$modelo = $modelo ?? [];
$marca = $marca ?? [];
$versoes = $versoes ?? [];
?>
<div class="bo-page">

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow"><?php echo htmlspecialchars($marca['name'] ?? ''); ?></span>
            <h1>Versões — <?php echo htmlspecialchars($modelo['name'] ?? ''); ?></h1>
            <p>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/<?php echo (int)$marca['id']; ?>">Voltar pros modelos</a>
                — Dados técnicos usados pra classificar o veículo (peso, motorização, categoria operacional).
            </p>
        </div>
    </header>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-3">
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/versao/novo?modelo_id=<?php echo (int)$modelo['id']; ?>" class="btn btn-primary">
            Nova versão
        </a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Versão</th>
                            <th>Anos</th>
                            <th>Combustível</th>
                            <th>Câmbio</th>
                            <th>Categoria operacional</th>
                            <th>Peso (kg)</th>
                            <th>Ativa</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($versoes)): ?>
                        <tr><td colspan="8" class="text-center p-4 text-muted small">Nenhuma versão cadastrada ainda.</td></tr>
                        <?php else: foreach ($versoes as $v): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($v['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars((string)($v['start_year'] ?? '—')) . ($v['end_year'] ? '–' . htmlspecialchars((string)$v['end_year']) : ($v['start_year'] ? '+' : '')); ?></td>
                            <td><?php echo htmlspecialchars($v['fuel_type'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($v['transmission_type'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($v['categoria_nome'] ?? '—'); ?></td>
                            <td><?php echo $v['curb_weight_kg'] !== null ? (int)$v['curb_weight_kg'] : '—'; ?></td>
                            <td><?php echo !empty($v['active']) ? '<span class="badge text-bg-success">Sim</span>' : '<span class="badge text-bg-secondary">Não</span>'; ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/versao/novo?id=<?php echo (int)$v['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Versões de veículos'; $breadcrumb=['Catálogo e preços','Versões de veículos']; $assets_css=['/public/assets/css/pages/bo-admin-catalogo.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
