<?php
/**
 * Partial: bloco "Oficina / Decisão" no admin/pedidodetalhe.php
 * Consome APENAS o Contrato Pedido v1 (docs/CONTRATOS.md).
 * Campos vêm de Pedido::buscarPorId() (p.*) após migration da faixa A.
 *
 * Campos: oficina_id, oficina_nome, triagem,
 *         custo_assistencia, custo_reboque, recomendacao, origem_funil
 *
 * Regra: não consulta tabela. Não faz JOIN. Não inventa coluna.
 */

/** @var array<string,mixed> $pedido */

$fmtMoney = static function ($v): string {
    if ($v === null || $v === '') return '—';
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
};

$fmtText = static function ($v): string {
    return ($v === null || $v === '') ? '—' : (string)$v;
};

$oficinaId     = $pedido['oficina_id']         ?? null;
$oficinaNome   = $pedido['oficina_nome']       ?? null;
$triagem       = $pedido['triagem']            ?? null;
$custoAssist   = $pedido['custo_assistencia']  ?? null;
$custoReboque  = $pedido['custo_reboque']      ?? null;
$recomendacao  = $pedido['recomendacao']       ?? null;
$origemFunil   = $pedido['origem_funil']       ?? null;

$badgeOrigem = $origemFunil === 'oficina'
    ? '<span class="badge bg-primary">oficina</span>'
    : ($origemFunil === 'especialista'
        ? '<span class="badge bg-secondary">especialista</span>'
        : '<span class="text-muted">—</span>');

$badgeRecomendacao = $recomendacao === 'assistencia'
    ? '<span class="badge bg-success">assistência</span>'
    : ($recomendacao === 'reboque'
        ? '<span class="badge bg-warning text-dark">reboque</span>'
        : '<span class="text-muted">—</span>');
?>
<div class="card mb-3" id="pedido-oficina-detalhe">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Oficina &amp; Decisão</strong>
        <span>Origem do funil: <?= $badgeOrigem ?></span>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Oficina</dt>
            <dd class="col-sm-8">
                <?= htmlspecialchars($fmtText($oficinaNome), ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($oficinaId)): ?>
                    <small class="text-muted">(ID <?= (int)$oficinaId ?>)</small>
                <?php endif; ?>
            </dd>

            <dt class="col-sm-4">Triagem</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($fmtText($triagem), ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-4">Recomendação</dt>
            <dd class="col-sm-8"><?= $badgeRecomendacao ?></dd>

            <dt class="col-sm-4">Custo assistência</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($fmtMoney($custoAssist), ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-4">Custo reboque</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($fmtMoney($custoReboque), ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
    </div>
</div>