<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$labels = ['oficina_aceitou'=>'Aceito','oficina_a_caminho'=>'A caminho','no_local'=>'No local','orcamento_enviado'=>'Orcamento enviado','orcamento_aprovado'=>'Orcamento aprovado','em_execucao_servico'=>'Em andamento','concluido'=>'Concluido','cancelado'=>'Cancelado'];
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<script>document.body.classList.add('theme-oficina');</script>
<div class="main-wrapper" style="display:flex;min-height:100vh">
    <?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
    <main class="main-content" style="flex:1;padding:1.5rem">
        <h1 style="font-size:1.4rem;font-weight:700;color:var(--ofi-primary-darker);margin-bottom:1rem"><i class="fas fa-clock-rotate-left me-2"></i>Historico</h1>
        <div class="ofi-card">
            <?php if (empty($pedidos)): ?>
                <p class="text-center py-4" style="color:#7C6B5C"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Nenhum atendimento ainda.</p>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Pedido</th><th>Data</th><th>Origem</th><th>Tipo</th><th class="text-end">Valor</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($pedidos as $p): $st = (string)($p['status'] ?? ''); $badge = $st === 'concluido' ? 'ofi-badge--online' : ($st === 'cancelado' ? 'ofi-badge--offline' : 'ofi-badge--info'); ?>
                        <tr>
                            <td><strong>#<?= (int)$p['id'] ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($p['criado_em'] ?? 'now')) ?></td>
                            <td><small><?= htmlspecialchars(mb_substr((string)($p['endereco_origem'] ?? '-'), 0, 50)) ?></small></td>
                            <td><?= htmlspecialchars((string)($p['tipo_problema'] ?? '-')) ?></td>
                            <td class="text-end">R$ <?= number_format((float)($p['custo_estimado'] ?? 0), 2, ',', '.') ?></td>
                            <td><span class="ofi-badge <?= $badge ?>"><?= htmlspecialchars($labels[$st] ?? ucfirst(str_replace('_',' ',$st))) ?></span></td>
                            <td><a href="<?= $bp ?>/oficina/pedido/<?= (int)$p['id'] ?>" class="btn-ofi-outline" style="padding:.3rem .7rem;font-size:.8rem">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>