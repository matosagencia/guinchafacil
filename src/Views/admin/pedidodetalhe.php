<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$statusLabels = [
    'aguardando_pagamento' => 'Aguardando Pagamento',
    'aguardando_guincho'   => 'Aguardando Guincho',
    'a_caminho'            => 'A Caminho',
    'no_local'             => 'No Local',
    'em_reboque'           => 'Em Reboque',
    'concluido'            => 'Concluído',
    'cancelado'            => 'Cancelado',
];
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <?php if (!empty($_GET['criado'])): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Pedido criado com sucesso!</div>
    <?php endif; ?>

    <?php if (($_GET['msg'] ?? '') === 'pix_reprocessado'): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Pix reprocessado com sucesso!</div>
    <?php elseif (($_GET['msg'] ?? '') === 'pix_falha'): ?>
    <div class="alert alert-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Falha no reprocessamento Pix. Verifique os logs.</div>
    <?php endif; ?>

    <?php
    // Exibe botão de reprocessamento se houver pagamento com status_pix=falha (§4.3)
    $pagFalha = null;
    if (!empty($pedido['id'])) {
        try {
            $stmtPix = getPDO()->prepare("SELECT id FROM pagamentos WHERE pedido_id = ? AND status_pix = 'falha' LIMIT 1");
            $stmtPix->execute([(int)$pedido['id']]);
            $pagFalha = $stmtPix->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $pagFalha = null; }
    }
    ?>
    <?php if ($pagFalha): ?>
    <div class="alert alert-warning mb-3 d-flex align-items-center justify-content-between">
        <span><i class="fas fa-exclamation-triangle me-2"></i>Repasse Pix falhou para este pedido.</span>
        <form method="POST" action="<?php echo $bp; ?>/admin/pix/reprocessar/<?php echo (int)$pedido['id']; ?>" class="ms-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <button type="submit" class="btn btn-warning btn-sm">
                <i class="fas fa-redo me-1"></i>Reprocessar Pix
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-file-alt me-2 text-primary-custom"></i>
                Pedido #<?php echo (int)($pedido['id'] ?? 0); ?>
                <span class="badge badge-<?php echo htmlspecialchars($pedido['status'] ?? ''); ?> ms-2">
                    <?php echo htmlspecialchars($statusLabels[$pedido['status'] ?? ''] ?? ucfirst($pedido['status'] ?? '')); ?>
                </span>
            </div>
            <div class="page-subtitle">
                Criado em <?php echo isset($pedido['criado_em']) ? date('d/m/Y \à\s H:i', strtotime($pedido['criado_em'])) : '—'; ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo $bp; ?>/admin/pedidos" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
            <?php if (!in_array($pedido['status'] ?? '', ['concluido','cancelado'])): ?>
            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalStatus">
                <i class="fas fa-edit me-1"></i>Alterar Status
            </button>
            <?php if (empty($pedido['guincho_id'])): ?>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalGuincho">
                <i class="fas fa-truck me-1"></i>Atribuir Guincho
            </button>
            <?php endif; ?>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                <i class="fas fa-ban me-1"></i>Cancelar
            </button>
            <?php endif; ?>
            <a href="<?php echo $bp; ?>/admin/pedido/novo" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>Novo Pedido
            </a>
        </div>
    </div>

    <!-- Modal: Alterar Status -->
    <div class="modal fade" id="modalStatus" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background:var(--theme-surface);border:1px solid var(--theme-border);color:var(--theme-text)">
                <div class="modal-header" style="border-color:var(--theme-border)">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Alterar Status do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo $bp; ?>/admin/pedido/status">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                        <label class="form-label">Novo Status</label>
                        <select class="form-select" name="status" required>
                            <?php
                            $todosStatus = [
                                'aguardando_pagamento' => 'Aguardando Pagamento',
                                'aguardando_guincho'   => 'Aguardando Guincho',
                                'a_caminho'            => 'A Caminho',
                                'no_local'             => 'No Local',
                                'em_reboque'           => 'Em Reboque',
                                'concluido'            => 'Concluído',
                            ];
                            foreach ($todosStatus as $val => $lbl):
                            ?>
                            <option value="<?php echo $val; ?>" <?php echo ($pedido['status'] ?? '') === $val ? 'selected' : ''; ?>>
                                <?php echo $lbl; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer" style="border-color:var(--theme-border)">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fas fa-check me-1"></i>Confirmar Alteração
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Atribuir Guincho -->
    <?php if (empty($pedido['guincho_id'])): ?>
    <div class="modal fade" id="modalGuincho" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background:var(--theme-surface);border:1px solid var(--theme-border);color:var(--theme-text)">
                <div class="modal-header" style="border-color:var(--theme-border)">
                    <h5 class="modal-title"><i class="fas fa-truck me-2"></i>Atribuir Guincho ao Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo $bp; ?>/admin/pedido/atribuir">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                        <label class="form-label">Selecionar Guincho Disponível</label>
                        <select class="form-select" name="guincho_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($guinchoDisponiveis ?? [] as $g): ?>
                            <option value="<?php echo (int)$g['id']; ?>">
                                <?php echo htmlspecialchars($g['nome_operador'] . ' — Placa: ' . $g['placa_guincho']); ?>
                                (Rep: <?php echo number_format($g['reputacao'] ?? 0, 1); ?>⭐)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($guinchoDisponiveis)): ?>
                        <div class="alert alert-warning mt-2 mb-0 py-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Nenhum guincho disponível no momento.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer" style="border-color:var(--theme-border)">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm" <?php echo empty($guinchoDisponiveis) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check me-1"></i>Atribuir Guincho
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Cancelar Pedido -->
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background:var(--theme-surface);border:1px solid var(--theme-border);color:var(--theme-text)">
                <div class="modal-header" style="border-color:var(--theme-border)">
                    <h5 class="modal-title text-danger"><i class="fas fa-ban me-2"></i>Cancelar Pedido #<?php echo (int)$pedido['id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo $bp; ?>/admin/pedido/cancelar">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                        <p>Tem certeza que deseja cancelar este pedido? Esta ação não pode ser desfeita.</p>
                    </div>
                    <div class="modal-footer" style="border-color:var(--theme-border)">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Não, manter</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fas fa-ban me-1"></i>Sim, cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Coluna esquerda: info do pedido -->
        <div class="col-lg-4">

            <!-- Cliente e Veículo -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-user me-2"></i>Cliente & Veículo</div>
                <div class="card-body">
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:35%">Cliente</td>
                            <td><?php echo htmlspecialchars($pedido['cliente_nome'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Telefone</td>
                            <td><?php echo htmlspecialchars($pedido['cliente_telefone'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Veículo</td>
                            <td><?php echo htmlspecialchars(($pedido['marca'] ?? '') . ' ' . ($pedido['modelo'] ?? '') . ' - ' . ($pedido['placa'] ?? '')); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Cor</td>
                            <td><?php echo htmlspecialchars($pedido['cor'] ?? '—'); ?></td></tr>
                    </table>
                    <?php if (!empty($pedido['cliente_id'])): ?>
                    <div class="mt-2">
                        <a href="<?php echo $bp; ?>/admin/usuario/<?php echo (int)$pedido['cliente_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fas fa-user me-1"></i>Ver Perfil do Cliente
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Guincho -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-truck me-2"></i>Guincheiro</div>
                <div class="card-body">
                    <?php if (!empty($pedido['guincho_operador'])): ?>
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:35%">Nome</td>
                            <td><?php echo htmlspecialchars($pedido['guincho_operador']); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Telefone</td>
                            <td><?php echo htmlspecialchars($pedido['guincho_telefone'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Placa</td>
                            <td><?php echo htmlspecialchars($pedido['guincho_placa'] ?? '—'); ?></td></tr>
                    </table>
                    <?php if (!empty($pedido['guincho_id'])): ?>
                    <div class="mt-2">
                        <a href="<?php echo $bp; ?>/admin/guincho/<?php echo (int)$pedido['guincho_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fas fa-truck me-1"></i>Ver Perfil do Guincho
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div style="color:var(--theme-muted);font-size:.88rem;text-align:center;padding:8px 0">
                        <i class="fas fa-clock me-1"></i>Aguardando atribuição de guincho
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Financeiro -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-coins me-2"></i>Financeiro</div>
                <div class="card-body">
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:50%">Distância</td>
                            <td><?php echo number_format($pedido['distancia_km'] ?? 0, 1); ?> km</td></tr>
                        <tr><td style="color:var(--theme-muted)">Custo Estimado</td>
                            <td>R$ <?php echo number_format($pedido['custo_estimado'] ?? 0, 2, ',', '.'); ?></td></tr>
                        <?php if (!empty($pedido['custo_final'])): ?>
                        <tr><td style="color:var(--theme-muted)">Custo Final</td>
                            <td><strong>R$ <?php echo number_format($pedido['custo_final'], 2, ',', '.'); ?></strong></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Detalhes do problema -->
            <div class="card">
                <div class="card-header"><i class="fas fa-triangle-exclamation me-2"></i>Problema Reportado</div>
                <div class="card-body">
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:35%">Tipo</td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pedido['tipo_problema'] ?? '—'))); ?></td></tr>
                        <?php if (!empty($pedido['descricao_problema'])): ?>
                        <tr><td style="color:var(--theme-muted)">Descrição</td>
                            <td><?php echo nl2br(htmlspecialchars($pedido['descricao_problema'])); ?></td></tr>
                        <?php endif; ?>
                    </table>
                    <div class="mt-3">
                        <div style="font-size:.8rem;color:var(--theme-muted);margin-bottom:4px"><i class="fas fa-map-marker-alt me-1"></i>Origem</div>
                        <div style="font-size:.88rem"><?php echo htmlspecialchars($pedido['endereco_origem'] ?? '—'); ?></div>
                    </div>
                    <div class="mt-2">
                        <div style="font-size:.8rem;color:var(--theme-muted);margin-bottom:4px"><i class="fas fa-flag me-1"></i>Destino</div>
                        <div style="font-size:.88rem"><?php echo htmlspecialchars($pedido['endereco_destino'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna direita: mapa + chat -->
        <div class="col-lg-8">

            <!-- Mapa da rota -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-map me-2"></i>Rota do Atendimento</div>
                <div class="card-body p-0">
                    <div id="map" style="height:320px;border-radius:0 0 12px 12px;min-height:260px"></div>
                </div>
            </div>

            <!-- Chat -->
            <div class="card">
                <div class="card-header"><i class="fas fa-comments me-2"></i>Chat do Atendimento</div>
                <div class="card-body p-0">
                    <div class="chat-box p-3" id="chatBox" style="height:260px;overflow-y:auto">
                        <?php if (empty($mensagens)): ?>
                        <div style="text-align:center;color:var(--theme-muted);padding:24px 0">
                            <i class="fas fa-comments fa-2x mb-2 d-block"></i>
                            Nenhuma mensagem neste atendimento.
                        </div>
                        <?php else: foreach ($mensagens as $m): ?>
                        <div class="chat-msg other mb-2">
                            <div style="font-size:.72rem;color:var(--theme-muted);margin-bottom:2px">
                                <?php echo htmlspecialchars($m['usuario_nome'] ?? ''); ?>
                                &bull; <?php echo date('H:i', strtotime($m['criado_em'])); ?>
                            </div>
                            <div class="chat-bubble"><?php echo nl2br(htmlspecialchars($m['mensagem'])); ?></div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const latO = <?php echo (float)($pedido['lat_origem']  ?? -23.5505); ?>;
    const lngO = <?php echo (float)($pedido['lng_origem']  ?? -46.6333); ?>;
    const latD = <?php echo (float)($pedido['lat_destino'] ?? -23.5505); ?>;
    const lngD = <?php echo (float)($pedido['lng_destino'] ?? -46.6333); ?>;
    const map  = L.map('map').setView([latO, lngO], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    const iconO = L.divIcon({ html:'<div style="background:#2fb34a;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4)"></div>', iconAnchor:[7,7] });
    const iconD = L.divIcon({ html:'<div style="background:#ef4444;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4)"></div>', iconAnchor:[7,7] });
    L.marker([latO, lngO], {icon: iconO}).addTo(map).bindPopup('Origem: <?php echo addslashes(htmlspecialchars($pedido['endereco_origem'] ?? '')); ?>');
    if (latD !== latO || lngD !== lngO) {
        L.marker([latD, lngD], {icon: iconD}).addTo(map).bindPopup('Destino: <?php echo addslashes(htmlspecialchars($pedido['endereco_destino'] ?? '')); ?>');
        L.polyline([[latO,lngO],[latD,lngD]], {color:'#2fb34a',weight:3,dashArray:'6,4'}).addTo(map);
        map.fitBounds([[latO,lngO],[latD,lngD]], {padding:[40,40]});
    }
    const cb = document.getElementById('chatBox');
    if (cb) cb.scrollTop = cb.scrollHeight;
});
</script>
