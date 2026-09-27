<?php
// File: guinchafacil/src/Views/cliente/pedidostatus.php
// View que faltava. Mostra status do pedido + orcamento da oficina + acoes.

$bp = defined('BASE_PATH') ? BASE_PATH : '';
$pedido = $pedido ?? [];
$csrfToken = $csrfToken ?? (class_exists('AuthService') ? AuthService::gerarCsrfToken() : '');
$flash = $flash ?? null;

$statusLabels = [
    'aguardando_pagamento'         => ['Aguardando pagamento',       'warning'],
    'aguardando_guincho'           => ['Buscando prestador',         'info'],
    'aguardando_oficina'           => ['Buscando oficina',           'info'],
    'a_caminho'                    => ['Prestador a caminho',        'primary'],
    'no_local'                     => ['Prestador no local',         'primary'],
    'em_reboque'                   => ['Em reboque',                 'primary'],
    'oficina_aceitou'              => ['Oficina aceitou',            'primary'],
    'oficina_a_caminho'            => ['Oficina a caminho',          'primary'],
    'orcamento_enviado'            => ['Aguardando sua aprovacao',   'warning'],
    'orcamento_aprovado'           => ['Orcamento aprovado',         'success'],
    'em_execucao_servico'          => ['Servico em andamento',       'primary'],
    'diagnostico_iniciado'         => ['Diagnostico iniciado',       'primary'],
    'diagnostico_concluido'        => ['Diagnostico concluido',      'info'],
    'autorizacao_servico_pendente' => ['Aguardando autorizacao',     'warning'],
    'teste_final'                  => ['Teste final',                'primary'],
    'conversao_reboque_pendente'   => ['Reboque pendente',           'warning'],
    'conversao_aprovada_cliente'   => ['Reboque aprovado',           'success'],
    'preparacao_veiculo'           => ['Preparando veiculo',         'primary'],
    'concluido'                    => ['Concluido',                  'success'],
    'cancelado'                    => ['Cancelado',                  'danger'],
];
$st = (string)($pedido['status'] ?? '');
[$stLabel, $stColor] = $statusLabels[$st] ?? [ucfirst(str_replace('_',' ',$st)), 'secondary'];

include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content" style="padding:1.5rem">

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-3">
            <?= htmlspecialchars((string)$flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0">Pedido #<?= (int)($pedido['id'] ?? 0) ?></h1>
            <p class="text-muted small mb-0">
                <?= htmlspecialchars((string)($pedido['tipo_problema'] ?? 'outro')) ?>
                &middot; <?= htmlspecialchars((string)date('d/m/Y H:i', strtotime((string)($pedido['criado_em'] ?? 'now')))) ?>
            </p>
        </div>
        <span class="badge text-bg-<?= $stColor ?>" style="font-size:.85rem;padding:.5rem .9rem">
            <?= htmlspecialchars($stLabel) ?>
        </span>
    </div>

    <?php require __DIR__ . '/../partials/_orcamento_oficina_card.php'; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="fas fa-route text-primary-custom me-1"></i>Rota</h2>
                    <div class="mb-2">
                        <div class="text-muted small">Origem</div>
                        <div><?= htmlspecialchars((string)($pedido['endereco_origem'] ?? '-')) ?></div>
                    </div>
                    <div>
                        <div class="text-muted small">Destino</div>
                        <div><?= htmlspecialchars((string)($pedido['endereco_destino'] ?? '-')) ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($pedido['descricao'])): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-2"><i class="fas fa-comment-dots text-primary-custom me-1"></i>Descricao</h2>
                    <p class="mb-0"><?= nl2br(htmlspecialchars((string)$pedido['descricao'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($pedido['guincho_operador']) || !empty($pedido['oficina_nome'])): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="fas fa-user text-primary-custom me-1"></i>Prestador</h2>
                    <?php if (!empty($pedido['guincho_operador'])): ?>
                        <div class="mb-1"><strong>Guincho:</strong> <?= htmlspecialchars((string)$pedido['guincho_operador']) ?></div>
                        <?php if (!empty($pedido['guincho_placa'])): ?>
                            <div class="mb-1"><strong>Placa:</strong> <?= htmlspecialchars((string)$pedido['guincho_placa']) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="fas fa-coins text-primary-custom me-1"></i>Valor estimado</h2>
                    <div style="font-size:1.6rem;font-weight:700;color:#2fb34a">
                        R$ <?= number_format((float)($pedido['custo_estimado'] ?? 0), 2, ',', '.') ?>
                    </div>
                    <p class="text-muted small mb-0">O valor final pode variar apos a avaliacao no local.</p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body d-grid gap-2">
                    <a href="<?= $bp ?>/cliente/historico" class="btn btn-outline-secondary">
                        <i class="fas fa-list me-1"></i> Meus pedidos
                    </a>
                    <?php if (in_array($st, ['aguardando_pagamento','aguardando_guincho','aguardando_oficina'], true)): ?>
                        <button type="button" class="btn btn-outline-danger" id="btnCancelarPedido">
                            <i class="fas fa-times me-1"></i> Cancelar pedido
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (in_array($st, ['a_caminho','no_local','em_reboque','oficina_a_caminho','oficina_aceitou'], true)): ?>
    <div class="alert alert-info mt-3 mb-0">
        <i class="fas fa-satellite-dish me-1"></i>
        Acompanhe a localizacao em tempo real. Atualizamos automaticamente.
    </div>
    <?php endif; ?>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>