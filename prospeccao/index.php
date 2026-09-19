<?php /** @var array $fila, array $regioes — injetados pelo AdminProspeccaoController::index() */ ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-plus me-2 text-primary-custom"></i>Prospecção de Parceiros</h1>
        <p class="page-subtitle">Fila do dia pronta para revisar e enviar manualmente pelo WhatsApp.</p>
    </div>
    <div>
        <a href="/admin/prospeccao/regioes" class="btn btn-outline-primary">
            <i class="fas fa-map-location-dot me-1"></i> Gerenciar regiões
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-satellite-dish me-2"></i>Buscar novos leads (SerpApi)</div>
    <div class="card-body">
        <form method="post" action="/admin/prospeccao/buscar" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Região</label>
                <select name="regiao_id" class="form-control" required>
                    <?php foreach ($regioes as $r): ?>
                        <option value="<?= (int) $r['id'] ?>">
                            <?= htmlspecialchars($r['nome']) ?> — <?= (int) $r['quota_atingida'] ?>/<?= (int) $r['quota_alvo'] ?> vagas preenchidas
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Páginas por categoria</label>
                <input type="number" name="paginas" class="form-control" min="1" max="3" value="1">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Buscar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-list-check me-2"></i>Fila do dia (<?= count($fila) ?>)</div>
    <div class="card-body">
        <?php if (empty($fila)): ?>
            <p class="text-muted">Nenhum lead na fila. Busque leads numa região ativa acima.</p>
        <?php endif; ?>

        <?php foreach ($fila as $item): $lead = $item['lead']; $regiao = $item['regiao']; $convite = $item['convite']; ?>
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap">
                    <div>
                        <strong><?= htmlspecialchars($lead['nome_negocio']) ?></strong>
                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars($lead['categoria']) ?></span>
                        <div class="text-muted small">
                            <?= htmlspecialchars($regiao['nome']) ?> ·
                            score <?= htmlspecialchars((string) $lead['score_go']) ?> ·
                            <?= (int) $convite['vagas_restantes'] ?> vaga(s) restante(s) na região
                        </div>
                    </div>
                    <div class="text-end small text-muted">
                        <?= htmlspecialchars($lead['telefone'] ?? '') ?><br>
                        <?= htmlspecialchars($lead['rating'] ?? '—') ?> ★ (<?= (int) ($lead['reviews_count'] ?? 0) ?> avaliações)
                    </div>
                </div>

                <textarea class="form-control mt-2" rows="4" readonly><?= htmlspecialchars($convite['texto']) ?></textarea>

                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <?php if ($convite['wa_link']): ?>
                        <a href="<?= htmlspecialchars($convite['wa_link']) ?>" target="_blank" class="btn btn-success btn-sm">
                            <i class="fab fa-whatsapp me-1"></i> Abrir no WhatsApp
                        </a>
                    <?php endif; ?>

                    <form method="post" action="/admin/prospeccao/lead/<?= (int) $lead['id'] ?>/enviado">
                        <input type="hidden" name="mensagem_texto" value="<?= htmlspecialchars($convite['texto']) ?>">
                        <input type="hidden" name="wa_link" value="<?= htmlspecialchars((string) $convite['wa_link']) ?>">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Marcar como enviado</button>
                    </form>

                    <form method="post" action="/admin/prospeccao/lead/<?= (int) $lead['id'] ?>/cadastrado">
                        <button type="submit" class="btn btn-outline-success btn-sm">Confirmar cadastro efetivado</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
