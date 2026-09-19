<?php /** @var array $regioes — injetado pelo AdminProspeccaoController::regioes() */ ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-map-location-dot me-2 text-primary-custom"></i>Regiões de prospecção</h1>
        <p class="page-subtitle">Cada região tem uma quota — ao ser atingida, a prospecção pausa sozinha ali.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-plus me-2"></i>Nova região</div>
    <div class="card-body">
        <form method="post" action="/admin/prospeccao/regioes/salvar" class="row g-2">
            <div class="col-md-4"><input class="form-control" name="nome" placeholder="Nome (ex: Niterói - Centro)" required></div>
            <div class="col-md-3"><input class="form-control" name="cidade" placeholder="Cidade" required></div>
            <div class="col-md-1"><input class="form-control" name="uf" placeholder="UF" maxlength="2" required></div>
            <div class="col-md-2"><input class="form-control" name="lat" placeholder="Latitude" required></div>
            <div class="col-md-2"><input class="form-control" name="lng" placeholder="Longitude" required></div>

            <div class="col-md-8">
                <input class="form-control" name="categorias_alvo"
                       placeholder="guincho, reboque, autoeletrica, borracheiro, chaveiro_automotivo, mecanico_movel">
            </div>
            <div class="col-md-2"><input class="form-control" type="number" name="quota_alvo" placeholder="Quota" value="5"></div>
            <div class="col-md-2"><input class="form-control" type="number" name="prioridade_fuseki" placeholder="Prioridade" value="100"></div>

            <div class="col-12"><button class="btn btn-primary">Salvar região</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-border-all me-2"></i>Tabuleiro atual</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Prioridade</th>
                    <th>Região</th>
                    <th>Quota</th>
                    <th>Categorias</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($regioes as $r): ?>
                    <tr>
                        <td><?= (int) $r['prioridade_fuseki'] ?></td>
                        <td><?= htmlspecialchars($r['nome']) ?> <span class="text-muted small">(<?= htmlspecialchars($r['cidade']) ?>/<?= htmlspecialchars($r['uf']) ?>)</span></td>
                        <td>
                            <?= (int) $r['quota_atingida'] ?>/<?= (int) $r['quota_alvo'] ?>
                            <div class="progress" style="height:6px">
                                <div class="progress-bar bg-success" style="width: <?= min(100, (int) $r['quota_atingida'] / max(1, (int) $r['quota_alvo']) * 100) ?>%"></div>
                            </div>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars($r['categorias_alvo']) ?></td>
                        <td><span class="badge bg-<?= $r['status'] === 'concluida' ? 'success' : 'primary' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
