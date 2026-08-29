<?php
$bp        = defined('BASE_PATH') ? BASE_PATH : '';
$pedidoId  = (int)($pedido['id'] ?? 0);
$status    = $pedido['status'] ?? '';

// Mapa de progresso dos steps
$statusOrder = ['aguardando_pagamento', 'aguardando_guincho', 'a_caminho', 'no_local', 'em_reboque', 'concluido'];

// Etapa 5/7 — estados novos do fluxo ON_SITE/HYBRID não existem neste mapa
// de 6 passos (ele é fixo, usado também para reboque — não dá pra inserir
// item no meio sem deslocar os índices de 'em_reboque'/'concluido' e
// quebrar a barra de progresso do reboque). Em vez disso, cada estado novo
// é tratado como equivalente ao passo visual mais próximo, só para fins de
// exibição da barra — não afeta nenhuma lógica de transição real.
$statusEquivalenteProgresso = [
    'diagnostico_iniciado' => 'no_local',
    'diagnostico_concluido' => 'no_local',
    'autorizacao_servico_pendente' => 'no_local',
    'em_execucao_servico' => 'no_local',
    'teste_final' => 'no_local',
    'conversao_reboque_pendente' => 'em_reboque',
    'conversao_aprovada_cliente' => 'em_reboque',
    'preparacao_veiculo' => 'em_reboque',
];
$currentIdx  = array_search($statusEquivalenteProgresso[$status] ?? $status, $statusOrder, true);
$currentIdx  = $currentIdx === false ? 0 : (int)$currentIdx;

$steps = [
    ['label' => 'Pedido Criado',      'icon' => 'fa-check',          'key' => 'aguardando_pagamento'],
    ['label' => 'Buscando Guincho',   'icon' => 'fa-magnifying-glass','key' => 'aguardando_guincho'],
    ['label' => 'A Caminho',          'icon' => 'fa-route',          'key' => 'a_caminho'],
    ['label' => 'No Local',           'icon' => 'fa-map-pin',        'key' => 'no_local'],
    ['label' => 'Em Reboque',         'icon' => 'fa-truck-ramp-box', 'key' => 'em_reboque'],
    ['label' => 'Concluído',          'icon' => 'fa-flag-checkered', 'key' => 'concluido'],
];

$latOrigem  = (float)($pedido['lat_origem']   ?? -23.5505);
$lngOrigem  = (float)($pedido['lng_origem']   ?? -46.6333);
$latDestino = (float)($pedido['lat_destino']  ?? -23.5505);
$lngDestino = (float)($pedido['lng_destino']  ?? -46.6333);
$latGuincho = isset($pedido['lat_guincho']) && $pedido['lat_guincho'] !== null ? (float)$pedido['lat_guincho'] : null;
$lngGuincho = isset($pedido['lng_guincho']) && $pedido['lng_guincho'] !== null ? (float)$pedido['lng_guincho'] : null;
$guinchoUf  = strtoupper(trim((string)($pedido['guincho_uf'] ?? $pedido['uf_placa'] ?? '')));
$guinchoPlacaUf = trim((string)($pedido['guincho_placa'] ?? '')) . ($guinchoUf !== '' ? ' / ' . $guinchoUf : '');

$cancelPreview = $cancelPreview ?? ['pode' => false, 'taxa' => 0.0, 'motivo_bloqueio' => null, 'isento_ate' => null];
$routingSnapshot = $routingSnapshot ?? ['mode' => 'overview', 'current_street' => '', 'target_label' => 'Destino', 'target_address' => '', 'remaining_distance_label' => '', 'remaining_distance_m' => 0, 'eta_minutes' => 0, 'eta_label' => '', 'progress_percent' => 0, 'trail_points' => [], 'recent_streets' => []];
$pedidoAtivo   = !in_array($status, ['concluido', 'cancelado'], true);

include __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../components/vehicle_brand_badge.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-pedidostatus.css">
<?php if (!empty($pedido['especialista_atendimentos'])): ?><section class="card mb-3"><div class="card-body"><h3 class="h5"><i class="fas fa-user-gear me-2 text-warning"></i>Especialista no atendimento</h3><?php foreach($pedido['especialista_atendimentos'] as $ev): $m=json_decode((string)$ev['metadata_json'],true)?:[]; ?><div class="d-flex gap-3 align-items-center border-top py-2"><div><strong><?= htmlspecialchars($ev['nome_profissional']?:'Especialista') ?></strong><div class="small text-muted"><?= htmlspecialchars(ucfirst(str_replace('_',' ',(string)$ev['evento']))) ?> · <?= htmlspecialchars((string)$ev['criado_em']) ?></div></div><?php if(!empty($m['arquivo'])): ?><a href="<?= $bp ?>/evidencia-especialista/<?= (int)$ev['id'] ?>" target="_blank" class="btn btn-sm btn-outline-warning">Ver foto</a><?php endif; ?></div><?php endforeach; ?></div></section><?php endif; ?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Acompanhamento</span>
            <h1><i class="fas fa-truck-fast me-2 text-primary-custom"></i>Pedido #<?php echo $pedidoId; ?></h1>
            <p>Rastreamento em tempo real, chat com o guincheiro e status atualizado automaticamente.</p>
        </div>
        <?php if ($pedidoAtivo): ?>
        <div>
            <button id="btnCancelarPedido" class="btn btn-outline-danger"
                    <?php echo $cancelPreview['pode'] ? '' : 'disabled'; ?>
                    title="<?php echo htmlspecialchars($cancelPreview['motivo_bloqueio'] ?? ''); ?>">
                <i class="fas fa-ban me-1"></i>Cancelar Pedido
            </button>
        </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($flash)): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($conversaoPendente)): ?>
    <!-- Etapa 7 — conversão de socorro local para reboque aguardando decisão do cliente -->
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning-subtle"><i class="fas fa-truck-ramp-box me-2"></i>Este atendimento precisa de reboque</div>
        <div class="card-body">
            <p class="mb-2">O prestador diagnosticou que não é possível resolver no local e este veículo precisa ser rebocado.</p>
            <?php if (!empty($diagnosticoAtual['descricao'])): ?>
            <p class="text-muted small mb-2"><i class="fas fa-clipboard-list me-1"></i><?php echo htmlspecialchars($diagnosticoAtual['descricao']); ?></p>
            <?php endif; ?>
            <p class="mb-2">Para onde o veículo deve ser rebocado? O valor do reboque é calculado pela distância e cobrado separadamente do socorro já realizado.</p>
            <form method="post" id="formAprovarConversao" action="<?php echo $bp; ?>/cliente/conversao/decidir/<?php echo $pedidoId; ?>" class="mb-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="decisao" value="aprovar">
                <input type="hidden" name="destino_lat" id="conversaoDestinoLat">
                <input type="hidden" name="destino_lng" id="conversaoDestinoLng">
                <div class="input-group mb-1">
                    <input type="text" class="form-control" id="conversaoDestinoInput" name="destino_endereco" placeholder="Rua, número, bairro, cidade…" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary" id="btnBuscarDestinoConversao"><i class="fas fa-search"></i></button>
                </div>
                <div id="conversaoDestinoFeedback" class="form-text text-muted mb-2">Busque o endereço da oficina/destino do reboque antes de aprovar.</div>
                <button type="submit" class="btn btn-success" id="btnAprovarConversao" disabled><i class="fas fa-check me-1"></i>Aprovar conversão para reboque</button>
            </form>
            <form method="post" action="<?php echo $bp; ?>/cliente/conversao/decidir/<?php echo $pedidoId; ?>" onsubmit="return confirm('Recusar a conversão para reboque?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="decisao" value="recusar">
                <button type="submit" class="btn btn-outline-danger"><i class="fas fa-xmark me-1"></i>Recusar</button>
            </form>
        </div>
    </div>
    <script>
    (function () {
        const inp = document.getElementById('conversaoDestinoInput');
        const btn = document.getElementById('btnBuscarDestinoConversao');
        const feedback = document.getElementById('conversaoDestinoFeedback');
        const submitBtn = document.getElementById('btnAprovarConversao');
        if (!inp || !btn) return;

        async function geocodeConversaoDestino() {
            const q = inp.value.trim();
            if (!q) return;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; btn.disabled = true;
            try {
                const url = new URL('<?php echo $bp; ?>/geocode', window.location.origin);
                url.searchParams.set('q', q);
                const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (d && d.ok && d.result) {
                    document.getElementById('conversaoDestinoLat').value = d.result.lat;
                    document.getElementById('conversaoDestinoLng').value = d.result.lng;
                    inp.value = d.result.display_name || q;
                    feedback.innerHTML = '<i class="fas fa-check-circle text-success"></i> Destino encontrado.';
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                    feedback.innerHTML = '<i class="fas fa-triangle-exclamation text-warning"></i> Endereço não encontrado — tente ser mais específico.';
                }
            } catch {
                feedback.innerHTML = '<i class="fas fa-triangle-exclamation text-warning"></i> Falha ao buscar endereço — tente novamente.';
            } finally {
                btn.innerHTML = '<i class="fas fa-search"></i>'; btn.disabled = false;
            }
        }

        btn.addEventListener('click', geocodeConversaoDestino);
        inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); geocodeConversaoDestino(); } });
        inp.addEventListener('input', function () { submitBtn.disabled = true; });
    })();
    </script>
    <?php endif; ?>

    <?php if (!empty($orcamentoPendente)): ?>
    <!-- Etapa 5 — orçamento complementar aguardando decisão do cliente -->
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning-subtle"><i class="fas fa-file-invoice-dollar me-2"></i>Orçamento complementar pendente</div>
        <div class="card-body">
            <p class="mb-2">O prestador identificou que este serviço precisa de itens adicionais. Confirme abaixo para liberar a execução:</p>
            <ul class="mb-2">
                <?php foreach (($orcamentoPendente['itens'] ?? []) as $item): ?>
                <li><?php echo htmlspecialchars($item['descricao']); ?> — R$ <?php echo number_format((float)$item['valor'], 2, ',', '.'); ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="fw-bold">Total adicional: R$ <?php echo number_format((float)$orcamentoPendente['valor_total'], 2, ',', '.'); ?></p>
            <div class="d-flex gap-2">
                <form method="post" action="<?php echo $bp; ?>/cliente/orcamento/decidir/<?php echo $pedidoId; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="decisao" value="aprovar">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Aprovar orçamento</button>
                </form>
                <form method="post" action="<?php echo $bp; ?>/cliente/orcamento/decidir/<?php echo $pedidoId; ?>" onsubmit="return confirm('Recusar este orçamento? O prestador não poderá executar os itens adicionais.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="decisao" value="recusar">
                    <button type="submit" class="btn btn-outline-danger"><i class="fas fa-xmark me-1"></i>Recusar</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Banner de Status em Tempo Real -->
    <div id="statusBannerCliente"
         class="card status-banner-live pedido-hero-card mb-4"
         data-status="<?php echo htmlspecialchars($status); ?>"
         data-status-url="<?php echo $bp; ?>/cliente/pedido/status-json/<?php echo $pedidoId; ?>">
         <div class="card-body p-0">
            <div class="pedido-hero-top">
                <div class="pedido-hero-driver">
                    <div class="pedido-hero-photo flex-shrink-0">
                <?php if (!empty($pedido['guincho_foto'])): ?>
                    <img src="<?php echo $bp; ?>/public/uploads/<?php echo htmlspecialchars($pedido['guincho_foto']); ?>" alt="Foto do guincho">
                <?php else: ?>
                    <div class="fallback">
                        <i class="fas fa-truck"></i>
                    </div>
                <?php endif; ?>
                    </div>
                    <div>
                        <div class="pedido-hero-title" data-status-label><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?></div>
                        <div class="pedido-hero-subtitle">
                            <?php echo htmlspecialchars($pedido['guincho_operador'] ?? 'Aguardando atribuição do guincho'); ?>
                        </div>
                        <?php if ($guinchoPlacaUf !== ''): ?>
                            <div class="mt-2">
                                <span class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis fw-bold px-3 py-2">
                                    <?php echo htmlspecialchars($guinchoPlacaUf); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($pedido['guincho_tipo']) || !empty($pedido['guincho_especialidades'])): ?>
                            <div class="small text-muted mt-2">
                                <i class="fas fa-truck me-1"></i><?php echo htmlspecialchars((string)($pedido['guincho_tipo'] ?? 'Veículo operacional')); ?>
                                <?php if (!empty($pedido['guincho_especialidades'])): ?>
                                    · <?php echo htmlspecialchars(implode(', ', array_map(static fn(array $s): string => (string)($s['service_name'] ?? $s['service_code'] ?? ''), $pedido['guincho_especialidades']))); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <span class="pedido-hero-chip"><i class="fas fa-route text-primary-custom"></i><?php echo htmlspecialchars((string)($routingSnapshot['eta_label'] ?? 'ETA em atualização')); ?></span>
                    <span class="pedido-hero-chip"><i class="fas fa-road text-primary-custom"></i><?php echo htmlspecialchars((string)($routingSnapshot['remaining_distance_label'] ?? 'Trajeto em atualização')); ?></span>
                </div>
            </div>
            <div class="pedido-hero-grid">
                <div class="pedido-hero-item">
                    <div class="small">Origem</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string)($pedido['endereco_origem'] ?? 'Local da ocorrência')); ?></div>
                </div>
                <div class="pedido-hero-item">
                    <div class="small">Destino</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars((string)($pedido['endereco_destino'] ?? 'Destino informado')); ?></div>
                </div>
                <div class="pedido-hero-item">
                    <div class="small">Rua atual</div>
                    <div class="fw-semibold" data-status-extra><?php echo htmlspecialchars((string)($routingSnapshot['current_street'] ?? 'Aguardando atualização...')); ?></div>
                </div>
            </div>
            <div class="pedido-hero-actions">
                <a href="<?php echo $bp; ?>/cliente/pedido/<?php echo $pedidoId; ?>" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i>Acompanhar pedido
                </a>
                <a href="<?php echo $bp; ?>/cliente/chat/<?php echo $pedidoId; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-comments me-2"></i>Chat
                </a>
                <a href="<?php echo $bp; ?>/cliente/chat/<?php echo $pedidoId; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-comments me-2"></i>Falar pelo chat
                </a>
            </div>
         </div>
    </div>

    <!-- Aviso de cancelamento (preenchido via JS quando aplicável) -->
    <div id="avisoCancelado" class="alert alert-warning d-none mb-4"></div>

    <!-- Status steps dinâmicos -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="status-steps" id="statusSteps">
                <?php foreach ($steps as $i => $step):
                    $stepIdx = (int)array_search($step['key'], $statusOrder, true);
                    $cls = $stepIdx < $currentIdx ? 'done' : ($stepIdx === $currentIdx ? 'active' : '');
                ?>
                <div class="step <?php echo $cls; ?>" data-step-key="<?php echo $step['key']; ?>">
                    <div class="step-icon"><i class="fas <?php echo $step['icon']; ?>"></i></div>
                    <div class="step-label"><?php echo $step['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Prova de Serviço: Fotos (também atualizado via JS) -->
    <div class="card mb-4 <?php echo (empty($pedido['foto_plataforma']) && empty($pedido['foto_destino'])) ? 'd-none' : ''; ?>" id="cardProvas">
        <div class="card-header"><i class="fas fa-camera me-2"></i>Prova de Serviço</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 <?php echo empty($pedido['foto_plataforma']) ? 'd-none' : ''; ?>" id="colFotoPlataforma">
                    <p class="small text-muted">Foto na Plataforma</p>
                    <a href="<?php echo $bp; ?>/public/uploads/<?php echo htmlspecialchars($pedido['foto_plataforma'] ?? ''); ?>" target="_blank" data-foto-link>
                        <img src="<?php echo $bp; ?>/public/uploads/<?php echo htmlspecialchars($pedido['foto_plataforma'] ?? ''); ?>" class="img-thumbnail pedidostatus-thumb" data-foto-img>
                    </a>
                </div>
                <div class="col-md-6 <?php echo empty($pedido['foto_destino']) ? 'd-none' : ''; ?>" id="colFotoDestino">
                    <p class="small text-muted">Foto no Destino</p>
                    <a href="<?php echo $bp; ?>/public/uploads/<?php echo htmlspecialchars($pedido['foto_destino'] ?? ''); ?>" target="_blank" data-foto-link>
                        <img src="<?php echo $bp; ?>/public/uploads/<?php echo htmlspecialchars($pedido['foto_destino'] ?? ''); ?>" class="img-thumbnail pedidostatus-thumb" data-foto-img>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-map me-2"></i>Rastreamento em Tempo Real</span>
                    <span>
                        <span id="rotaQualidadeBadge" class="badge me-2"></span>
                        <small class="text-muted" id="rotaFrescor"></small>
                        <small class="text-muted" id="rotaLegenda"></small>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div id="map" class="map-container pedidostatus-map"></div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header"><i class="fas fa-road me-2"></i>Rota, ETA e trilha confirmada</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Rua atual</div>
                            <div class="fw-semibold" id="rotaRuaAtual"><?php echo htmlspecialchars((string)($routingSnapshot['current_street'] ?? '')); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">ETA estimado</div>
                            <div class="fw-semibold" id="rotaEta"><?php echo htmlspecialchars((string)($routingSnapshot['eta_label'] ?? '')); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Distância restante</div>
                            <div class="fw-semibold" id="rotaDistancia"><?php echo htmlspecialchars((string)($routingSnapshot['remaining_distance_label'] ?? '')); ?></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Progresso operacional</span>
                            <span id="rotaProgressoLabel"><?php echo (int)($routingSnapshot['progress_percent'] ?? 0); ?>%</span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Progresso da rota" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" id="rotaProgressoBar" style="width: <?php echo (int)($routingSnapshot['progress_percent'] ?? 0); ?>%"></div>
                        </div>
                    </div>
                    <div class="small text-muted mb-1">Ruas confirmadas pelo percurso</div>
                    <div id="rotaRuas" class="d-flex flex-wrap gap-2">
                        <?php foreach (($routingSnapshot['recent_streets'] ?? []) as $street): ?>
                        <span class="badge text-bg-light border"><?php echo htmlspecialchars((string)$street); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5 d-flex flex-column gap-4">
            <div class="card" id="cardGuincho">
                <div class="card-header"><i class="fas fa-id-card me-2"></i>Seu Guincho</div>
                <div class="card-body">
                    <?php if (!empty($pedido['guincho_operador'])): ?>
                    <p><strong>Nome:</strong> <span id="gNome"><?php echo htmlspecialchars($pedido['guincho_operador']); ?></span></p>
                    <?php if (!empty($pedido['guincho_foto'])): ?>
                    <img src="<?php echo $bp; ?>/public/uploads/<?php echo htmlspecialchars($pedido['guincho_foto']); ?>" class="img-thumbnail mb-2 pedidostatus-thumb-sm">
                    <?php endif; ?>
                    <p><strong>Veículo:</strong> <span id="gDetalhes"><?php echo vehicle_identity_html($pedido['marca'] ?? '', $pedido['modelo'] ?? '', $pedido['veiculo_tipo'] ?? 'carro', $pedido['placa'] ?? '', 28); ?></span></p>
                    <p><strong>Contato:</strong> <i class="fas fa-comments text-primary-custom me-1"></i>Falar pelo chat do pedido</p>
                    <?php else: ?>
                    <p id="guinchoAguardando" class="text-muted">Aguardando atribuição de guincho...</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card flex-fill">
                <div class="card-header"><i class="fas fa-comments me-2"></i>Chat com o Guincho</div>
                <div class="card-body p-2">
                    <div class="chat-box pedidostatus-chatbox" id="chatBox">
                        <?php foreach ($mensagens ?? [] as $msg):
                            $ehCliente = ($msg['remetente_tipo'] === 'cliente');
                            $cls       = $ehCliente ? 'mine' : 'other';
                            $nome      = $ehCliente ? 'Você' : htmlspecialchars($msg['remetente_nome'] ?? 'Guincho');
                        ?>
                        <div class="chat-msg <?php echo $cls; ?>" data-id="<?php echo (int)$msg['id']; ?>">
                            <div class="sender"><?php echo $nome; ?></div>
                            <div class="bubble"><?php echo htmlspecialchars($msg['mensagem']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <input type="text" id="msgInput" class="form-control form-control-sm"
                               placeholder="Mensagem..." maxlength="1000"
                               <?php echo $pedidoAtivo ? '' : 'disabled'; ?>>
                        <button id="btnEnviar" class="btn btn-primary btn-sm px-3"
                                <?php echo $pedidoAtivo ? '' : 'disabled'; ?>>
                            Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de cancelamento -->
    <div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban text-danger me-2"></i>Cancelar Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="cancelResumoTaxa" class="alert alert-info mb-3"></div>
                    <label class="form-label">Motivo (opcional)</label>
                    <textarea id="cancelMotivo" class="form-control" rows="2" maxlength="255"
                              placeholder="Ex.: consegui resolver o problema"></textarea>
                    <div id="cancelErro" class="text-danger small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarCancelamento">
                        <i class="fas fa-ban me-1"></i>Confirmar Cancelamento
                    </button>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet-routing-machine/leaflet-routing-machine.css">
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet-routing-machine/leaflet-routing-machine.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/routing/formatter-pt-br.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/app.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/core/gps-resilience.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    'use strict';

    var PEDIDO_ID   = <?php echo $pedidoId; ?>;
    var BP          = <?php echo json_encode($bp); ?>;
    var CSRF        = <?php echo json_encode($csrfToken ?? ''); ?>;
    var STATUS      = <?php echo json_encode($status); ?>;
    var latOrigem   = <?php echo json_encode($latOrigem); ?>;
    var lngOrigem   = <?php echo json_encode($lngOrigem); ?>;
    var latDestino  = <?php echo json_encode($latDestino); ?>;
    var lngDestino  = <?php echo json_encode($lngDestino); ?>;
    var latGuincho  = <?php echo json_encode($latGuincho); ?>;
    var lngGuincho  = <?php echo json_encode($lngGuincho); ?>;
    var ROUTING_SNAPSHOT = <?php echo json_encode($routingSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    // §LOG-FE-02 (29/07/2026): antes disso, a única forma de saber por que o
    // rastreamento virou "confiável"/"instável" era abrir o console e inspecionar
    // ROUTING_SNAPSHOT manualmente — nenhuma mudança de qualidade era logada.
    var ULTIMA_TRACKING_QUALITY_LOGADA = null;
    var CANCEL_PREVIEW = <?php echo json_encode([
        'pode' => (bool)$cancelPreview['pode'],
        'taxa' => (float)$cancelPreview['taxa'],
        'bloqueio' => $cancelPreview['motivo_bloqueio'],
        'isento_ate' => $cancelPreview['isento_ate'],
        // snapshot_id só existe depois do GET .../cancelamento-preview
        // disparado ao abrir o modal (ver initCancelamento()) — o preview
        // inicial da página não persiste snapshot.
        'snapshot_id' => null,
    ]); ?>;

    var STATUS_ORDER = ['aguardando_pagamento','aguardando_guincho','a_caminho','no_local','em_reboque','concluido'];

    var map, markerOrigem, markerDestino, markerGuincho, rotaControl, rotaModo = null;
    var trilhaConfirmadaLayer = null;
    var ultimoMsgId = 0;
    var pollingChat, pollingStatus, ssePedido = null, sseRetryTimer = null;
    var ultimoPontoGuinchoTs = 0, timerFrescor = null;
    // Projeção (dead reckoning): enquanto o GPS real do guincho não manda um
    // ponto novo, estima a posição a partir da última âncora real (server-side,
    // sem depender do relógio do dispositivo) + a velocidade média da corrida.
    // Nunca decide sozinha que o guincho "chegou" — isso continua exigindo
    // GPS real / geofence no servidor. É só uma ajuda visual + de ETA.
    var projAncora = null, projTimer = null, usandoEstimativa = false;
    var LIMIAR_PROJECAO_S = 20; // só passa a estimar depois desse tempo sem ponto real

    // Pinos de origem/destino: vermelho até o guincho chegar naquele ponto,
    // cinza depois — mesma convenção usada em todos os mapas do sistema
    // (admin/dashboard, admin/pedidodetalhe, admin/pedido_trilha, guincho/atendimento).
    function statusIndicaChegouOrigem(status) {
        return ['no_local', 'em_reboque', 'concluido', 'encerrado_financeiro'].includes(String(status || ''));
    }
    function statusIndicaChegouDestino(status) {
        return ['concluido', 'encerrado_financeiro'].includes(String(status || ''));
    }
    function pinIcon(chegou, iconClass) {
        return L.divIcon({
            className: 'mapa-pin ' + (chegou ? 'mapa-pin--concluido' : 'mapa-pin--pendente'),
            html: '<i class="fas ' + iconClass + '"></i>',
            iconSize: [26, 26],
        });
    }
    function atualizarPinsOrigemDestino(status) {
        if (markerOrigem) markerOrigem.setIcon(pinIcon(statusIndicaChegouOrigem(status), 'fa-location-dot'));
        if (markerDestino) markerDestino.setIcon(pinIcon(statusIndicaChegouDestino(status), 'fa-flag-checkered'));
    }

    function requestJson(url, options) {
        if (window.apiFetch) return window.apiFetch(url, options || {});
        return fetch(url, options || {}).then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        });
    }

    // ── Inicializa mapa ──────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        map = L.map('map').setView([latOrigem, lngOrigem], 13);
        L.Icon.Default.imagePath = '<?php echo $bp; ?>/public/assets/img/leaflet/';
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        markerOrigem  = L.marker([latOrigem, lngOrigem], { icon: pinIcon(statusIndicaChegouOrigem(STATUS), 'fa-location-dot') }).addTo(map).bindPopup('Origem');
        markerDestino = L.marker([latDestino, lngDestino], { icon: pinIcon(statusIndicaChegouDestino(STATUS), 'fa-flag-checkered') }).addTo(map).bindPopup('Destino');

        if (latGuincho != null && lngGuincho != null) {
            criarOuMoverGuincho(latGuincho, lngGuincho);
        }

        desenharRota(STATUS);
        renderRoutingSnapshot(ROUTING_SNAPSHOT);

        if (!['concluido','cancelado'].includes(STATUS)) {
            iniciarSsePedido();
            iniciarPollingStatus();
            atualizarStatus();
            timerFrescor = setInterval(renderFrescor, 5000);
            if (window.SessionManager) window.SessionManager.registerPolling(timerFrescor);
            projTimer = setInterval(tickProjecao, 3000);
            if (window.SessionManager) window.SessionManager.registerPolling(projTimer);
        } else {
            encerrarSsePedido();
        }

        scrollChat();
        document.querySelectorAll('#chatBox .chat-msg[data-id]').forEach(function (el) {
            var id = parseInt(el.dataset.id, 10);
            if (id > ultimoMsgId) ultimoMsgId = id;
        });

        var btnEnviar = document.getElementById('btnEnviar');
        var msgInput  = document.getElementById('msgInput');
        if (btnEnviar) btnEnviar.addEventListener('click', enviarMensagem);
        if (msgInput)  msgInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensagem(); }
        });

        initCancelamento();
    });

    // ── Rota dinâmica por status ─────────────────────────────
    // a_caminho ............. rota do guincho até a origem (busca do veículo)
    // no_local/em_reboque ... rota da origem até o destino (reboque)
    // demais status ......... visão geral origem→destino
    function desenharRota(status) {
        var modo, waypoints, cor, legenda;

        if (status === 'a_caminho' && markerGuincho) {
            modo      = 'guincho_origem';
            waypoints = [markerGuincho.getLatLng(), L.latLng(latOrigem, lngOrigem)];
            cor       = '#fd7e14';
            legenda   = 'Guincho → seu veículo';
        } else {
            modo      = 'origem_destino';
            waypoints = [L.latLng(latOrigem, lngOrigem), L.latLng(latDestino, lngDestino)];
            cor       = '#0d6efd';
            legenda   = 'Origem → destino';
        }

        var legendaEl = document.getElementById('rotaLegenda');
        if (legendaEl) legendaEl.textContent = legenda;

        // Evita recriar a rota se o modo não mudou (o waypoint do guincho é
        // atualizado via setWaypoints para acompanhar o movimento)
        if (rotaControl && rotaModo === modo) {
            if (modo === 'guincho_origem') rotaControl.setWaypoints(waypoints);
            return;
        }

        if (rotaControl) { map.removeControl(rotaControl); rotaControl = null; }
        rotaModo = modo;
        rotaControl = L.Routing.control({
            waypoints: waypoints,
            lineOptions: { styles: [{color: cor, weight: 6, opacity: 0.8}] },
            createMarker: function () { return null; },
            addWaypoints: false,
            draggableWaypoints: false,
            fitSelectedRoutes: true,
            show: false
        }).addTo(map);
    }

    function renderRoutingSnapshot(snapshot) {
        ROUTING_SNAPSHOT = snapshot || {};
        configurarProjecao(ROUTING_SNAPSHOT);

        var ruaAtual = document.getElementById('rotaRuaAtual');
        var eta = document.getElementById('rotaEta');
        var distancia = document.getElementById('rotaDistancia');
        var progressoLabel = document.getElementById('rotaProgressoLabel');
        var progressoBar = document.getElementById('rotaProgressoBar');
        var ruas = document.getElementById('rotaRuas');
        var rotaLegenda = document.getElementById('rotaLegenda');
        var statusExtra = document.querySelector('[data-status-extra]');
        var formatter = window.RouteFormatterPtBr || null;
        var etaLabel = ROUTING_SNAPSHOT.eta_label || '';
        var distanceLabel = ROUTING_SNAPSHOT.remaining_distance_label || '';

        if (formatter) {
            etaLabel = etaLabel || formatter.formatEta(ROUTING_SNAPSHOT.eta_minutes || 0);
            distanceLabel = distanceLabel || formatter.formatDistance(ROUTING_SNAPSHOT.remaining_distance_m || 0);
        }

        if (ruaAtual) ruaAtual.textContent = ROUTING_SNAPSHOT.current_street || 'Sem rua confirmada';
        if (eta) eta.textContent = etaLabel || 'Sem ETA';
        if (distancia) distancia.textContent = distanceLabel || 'Sem distância';
        if (progressoLabel) progressoLabel.textContent = String(ROUTING_SNAPSHOT.progress_percent || 0) + '%';
        if (progressoBar) progressoBar.style.width = String(ROUTING_SNAPSHOT.progress_percent || 0) + '%';
        if (rotaLegenda) {
            rotaLegenda.textContent = (ROUTING_SNAPSHOT.target_label || 'Destino')
                + (etaLabel ? ' · ETA ' + etaLabel : '')
                + (distanceLabel ? ' · ' + distanceLabel : '');
        }

        // §A3 — qualidade do rastreamento (backend já calculava; ficava só
        // em logs/painel admin, nunca chegava ao cliente).
        var qualidadeBadge = document.getElementById('rotaQualidadeBadge');
        if (qualidadeBadge) {
            var qualidadeInfo = {
                good: { label: 'Rastreamento confiável', cls: 'text-bg-success' },
                fair: { label: 'Rastreamento parcial', cls: 'text-bg-warning' },
                poor: { label: 'Rastreamento instável', cls: 'text-bg-danger' },
                unknown: { label: 'Rastreamento iniciando', cls: 'text-bg-secondary' }
            };
            var q = qualidadeInfo[ROUTING_SNAPSHOT.tracking_quality] || qualidadeInfo.unknown;
            qualidadeBadge.textContent = q.label;
            qualidadeBadge.className = 'badge me-2 ' + q.cls;

            if (ROUTING_SNAPSHOT.tracking_quality !== ULTIMA_TRACKING_QUALITY_LOGADA) {
                console.log('[cliente/pedidostatus] tracking_quality mudou de "' + ULTIMA_TRACKING_QUALITY_LOGADA + '" para "' + ROUTING_SNAPSHOT.tracking_quality + '"', {
                    remaining_distance_m: ROUTING_SNAPSHOT.remaining_distance_m,
                    progress_percent: ROUTING_SNAPSHOT.progress_percent,
                    eta_minutes: ROUTING_SNAPSHOT.eta_minutes,
                    current_street: ROUTING_SNAPSHOT.current_street
                });
                ULTIMA_TRACKING_QUALITY_LOGADA = ROUTING_SNAPSHOT.tracking_quality;
            }
        }
        if (statusExtra) {
            statusExtra.textContent = (ROUTING_SNAPSHOT.current_street || 'Sem rua confirmada')
                + (etaLabel ? ' · ETA ' + etaLabel : '');
        }

        if (ruas) {
            ruas.innerHTML = '';
            (ROUTING_SNAPSHOT.recent_streets || []).forEach(function (street) {
                var badge = document.createElement('span');
                badge.className = 'badge text-bg-light border';
                badge.textContent = street;
                ruas.appendChild(badge);
            });
            if (!ruas.children.length) {
                var fallback = document.createElement('span');
                fallback.className = 'text-muted small';
                fallback.textContent = 'Aguardando ruas confirmadas pelo percurso.';
                ruas.appendChild(fallback);
            }
        }

        renderTrailPolyline(ROUTING_SNAPSHOT.trail_points || []);
    }

    function renderTrailPolyline(points) {
        if (!map) return;

        if (trilhaConfirmadaLayer) {
            map.removeLayer(trilhaConfirmadaLayer);
            trilhaConfirmadaLayer = null;
        }

        if (!Array.isArray(points) || points.length < 2) {
            return;
        }

        trilhaConfirmadaLayer = L.polyline(points.map(function (point) {
            return [Number(point.lat), Number(point.lng)];
        }), {
            color: '#16a34a',
            weight: 5,
            opacity: 0.85
        }).addTo(map);
    }

    function criarOuMoverGuincho(lat, lng) {
        var pos = [lat, lng];
        if (markerGuincho) {
            markerGuincho.setLatLng(pos);
        } else {
            markerGuincho = L.marker(pos, {
                icon: L.divIcon({ className: '', html: '<i class="fas fa-truck pedidostatus-guincho-icon"></i>' })
            }).addTo(map).bindPopup('Guincho');
        }
        ultimoPontoGuinchoTs = Date.now(); // marca a chegada de posição fresca (real)
        desativarEstimativa();
        // Ponto real chegou: a âncora de projeção passa a ser este ponto,
        // agora — assim, se o sinal cair de novo, a próxima estimativa parte
        // daqui (e não de uma posição antiga).
        if (projAncora) {
            projAncora.lat = lat;
            projAncora.lng = lng;
            projAncora.tsClientAnchor = Date.now();
        }
        renderFrescor();
    }

    // ── Frescor do rastreamento (gap: cliente não via "sinal velho") ──
    // Quando o GPS/rede do guincho cai, o mapa congela no último ponto. Aqui o
    // cliente vê a idade ("atualizado há X") e um aviso quando o sinal fica
    // instável, sem depender de nenhuma mudança no servidor.
    function renderFrescor() {
        var el = document.getElementById('rotaFrescor');
        if (!el) return;
        var emRota = (STATUS === 'a_caminho' || STATUS === 'no_local' || STATUS === 'em_reboque');
        if (!ultimoPontoGuinchoTs || !markerGuincho || !emRota) { el.textContent = ''; return; }
        if (usandoEstimativa) return; // tickProjecao() já escreve o texto de posição estimada
        var idadeMs = Date.now() - ultimoPontoGuinchoTs;
        var idade = window.GpsResilience ? window.GpsResilience.ageLabel(ultimoPontoGuinchoTs) : '';
        if (idadeMs > 30000) {
            el.innerHTML = '<span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i>Sinal do guincho instável — última posição ' + idade + '</span>';
        } else {
            el.textContent = 'Atualizado ' + idade;
        }
    }

    // ── Projeção de posição (dead reckoning) ──────────────────
    function haversineMetros(lat1, lng1, lat2, lng2) {
        var toRad = function (d) { return d * Math.PI / 180; };
        var R = 6371000;
        var dLat = toRad(lat2 - lat1), dLng = toRad(lng2 - lng1);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    /** Recalcula a âncora de projeção a partir do snapshot vindo do servidor
     *  (current_lat/lng, target_lat/lng, speed_kmh, anchor_age_s — server
     *  time, sem depender do relógio do dispositivo). */
    function configurarProjecao(snapshot) {
        var modosComProjecao = ['guincho_origem', 'origem_destino'];
        if (!snapshot || modosComProjecao.indexOf(snapshot.mode) === -1
            || snapshot.anchor_age_s == null || !(snapshot.speed_kmh > 0)
            || snapshot.current_lat == null || snapshot.target_lat == null) {
            projAncora = null;
            desativarEstimativa();
            return;
        }
        projAncora = {
            lat: snapshot.current_lat,
            lng: snapshot.current_lng,
            targetLat: snapshot.target_lat,
            targetLng: snapshot.target_lng,
            speedMps: snapshot.speed_kmh / 3.6,
            tsClientAnchor: Date.now() - (snapshot.anchor_age_s * 1000)
        };
    }

    function moverMarcadorEstimado(lat, lng) {
        if (!markerGuincho) return;
        markerGuincho.setLatLng([lat, lng]);
    }

    function ativarEstimativa(idadeSegundos) {
        usandoEstimativa = true;
        if (markerGuincho && markerGuincho.setOpacity) markerGuincho.setOpacity(0.55);
        var el = document.getElementById('rotaFrescor');
        if (el) {
            var min = Math.floor(idadeSegundos / 60), seg = Math.round(idadeSegundos % 60);
            var idadeTxt = min > 0 ? (min + ' min') : (seg + 's');
            el.innerHTML = '<span class="text-warning"><i class="fas fa-route me-1"></i>Sem sinal há ' + idadeTxt + ' — posição estimada pela velocidade média</span>';
        }
    }

    function desativarEstimativa() {
        if (!usandoEstimativa) return;
        usandoEstimativa = false;
        if (markerGuincho && markerGuincho.setOpacity) markerGuincho.setOpacity(1);
    }

    /** Roda a cada poucos segundos: se o último ponto REAL está velho demais,
     *  projeta a posição ao longo da linha reta até o alvo (origem ou destino,
     *  conforme a fase) usando a velocidade média da corrida. Nunca ultrapassa
     *  o alvo e nunca muda o status do pedido — é só uma estimativa visual. */
    function tickProjecao() {
        if (!projAncora || !markerGuincho) return;
        var elapsedS = (Date.now() - projAncora.tsClientAnchor) / 1000;
        if (elapsedS < LIMIAR_PROJECAO_S) {
            desativarEstimativa();
            return;
        }
        var distTotal = haversineMetros(projAncora.lat, projAncora.lng, projAncora.targetLat, projAncora.targetLng);
        if (distTotal <= 0) { desativarEstimativa(); return; }
        var distPercorrida = Math.min(distTotal, projAncora.speedMps * elapsedS);
        var frac = distPercorrida / distTotal;
        var lat = projAncora.lat + (projAncora.targetLat - projAncora.lat) * frac;
        var lng = projAncora.lng + (projAncora.targetLng - projAncora.lng) * frac;
        moverMarcadorEstimado(lat, lng);
        ativarEstimativa(elapsedS);
    }

    // ── Steps dinâmicos ──────────────────────────────────────
    function atualizarSteps(status) {
        var idx = STATUS_ORDER.indexOf(status);
        if (idx < 0) idx = 0;
        document.querySelectorAll('#statusSteps .step').forEach(function (el) {
            var stepIdx = STATUS_ORDER.indexOf(el.dataset.stepKey);
            el.classList.remove('done', 'active');
            if (stepIdx < idx) el.classList.add('done');
            else if (stepIdx === idx) el.classList.add('active');
        });
    }

    // ── Chat ─────────────────────────────────────────────────
    // Pacote L1.9: chatSending evita reenvio por duplo-clique/duplo-Enter; a
    // idempotency key é mantida entre tentativas do MESMO texto (ex.: usuário
    // clica de novo após um timeout de rede que pode ter chegado no servidor)
    // e só é trocada quando o texto muda ou o envio é confirmado.
    var chatSending = false;
    var chatIdempotencyKey = null;
    var chatIdempotencyTexto = null;

    function gerarIdempotencyKey() {
        if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
        return 'ck-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    }

    function enviarMensagem() {
        if (chatSending) return;
        var input = document.getElementById('msgInput');
        var texto = input.value.trim();
        if (!texto) return;

        if (chatIdempotencyKey === null || chatIdempotencyTexto !== texto) {
            chatIdempotencyKey = gerarIdempotencyKey();
            chatIdempotencyTexto = texto;
        }

        chatSending = true;
        input.disabled = true;
        requestJson(BP + '/cliente/chat/' + PEDIDO_ID, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(CSRF) + '&mensagem=' + encodeURIComponent(texto)
                + '&idempotency_key=' + encodeURIComponent(chatIdempotencyKey)
        })
        .then(function (data) {
            if (data.ok) {
                input.value = '';
                chatIdempotencyKey = null;
                chatIdempotencyTexto = null;
                buscarMensagens();
            } else {
                console.warn('[pedidostatus] falha ao enviar mensagem:', data.erro);
            }
        })
        .finally(function () { chatSending = false; input.disabled = false; input.focus(); });
    }

    function buscarMensagens() {
        requestJson(BP + '/cliente/chat/mensagens/' + PEDIDO_ID + '?desde_id=' + ultimoMsgId)
        .then(function (data) {
            if (!data.ok || !data.mensagens.length) return;
            var box = document.getElementById('chatBox');
            data.mensagens.forEach(function (msg) {
                var ehCliente = (msg.remetente_tipo === 'cliente');
                var div = document.createElement('div');
                div.className = 'chat-msg ' + (ehCliente ? 'mine' : 'other');
                div.dataset.id = msg.id;
                div.innerHTML = '<div class="sender">' + (ehCliente ? 'Você' : escHtml(msg.remetente_nome || 'Guincho')) + '</div>'
                              + '<div class="bubble">' + escHtml(msg.mensagem) + '</div>';
                box.appendChild(div);
                if (msg.id > ultimoMsgId) ultimoMsgId = msg.id;
            });
            scrollChat();
        });
    }

    function iniciarPollingChat() {
        if (pollingChat) clearInterval(pollingChat);
        pollingChat = setInterval(buscarMensagens, 30000);
        if (window.SessionManager) window.SessionManager.registerPolling(pollingChat);
    }

    function scrollChat() {
        var box = document.getElementById('chatBox');
        box.scrollTop = box.scrollHeight;
    }

    // ── Status / mapa ────────────────────────────────────────
    function iniciarPollingStatus() {
        if (pollingStatus) clearInterval(pollingStatus);
        pollingStatus = setInterval(atualizarStatus, 45000);
        if (window.SessionManager) window.SessionManager.registerPolling(pollingStatus);
    }

    function encerrarSsePedido() {
        if (sseRetryTimer) {
            clearTimeout(sseRetryTimer);
            sseRetryTimer = null;
        }
        if (ssePedido) {
            ssePedido.close();
            ssePedido = null;
        }
    }

    function reagendarSsePedido() {
        if (window.SessionManager && window.SessionManager.isExpired && window.SessionManager.isExpired()) return;
        if (sseRetryTimer) clearTimeout(sseRetryTimer);
        sseRetryTimer = setTimeout(iniciarSsePedido, 4000);
    }

    function appendMensagemSse(msg) {
        if (!msg || !msg.id || msg.id <= ultimoMsgId) return;

        var box = document.getElementById('chatBox');
        if (!box) return;

        var ehCliente = Number(msg.usuario_id || 0) === Number(<?php echo (int)($_SESSION['user']['id'] ?? 0); ?>);
        var div = document.createElement('div');
        div.className = 'chat-msg ' + (ehCliente ? 'mine' : 'other');
        div.dataset.id = msg.id;
        div.innerHTML = '<div class="sender">' + (ehCliente ? 'Você' : escHtml(msg.usuario_nome || 'Guincho')) + '</div>'
                      + '<div class="bubble">' + escHtml(msg.mensagem || '') + '</div>';
        box.appendChild(div);
        ultimoMsgId = Math.max(ultimoMsgId, Number(msg.id) || 0);
        scrollChat();
    }

    function iniciarSsePedido() {
        if (!window.EventSource || ['concluido','cancelado'].includes(STATUS)) {
            iniciarPollingChat();
            return;
        }

        encerrarSsePedido();
        if (pollingChat) {
            clearInterval(pollingChat);
            pollingChat = null;
        }
        ssePedido = new EventSource(BP + '/sse/pedido/' + PEDIDO_ID + '?desde_id=' + encodeURIComponent(ultimoMsgId));

        // Diagnóstico (30/07/2026): investigação de mensagem de chat que não
        // chegava no cliente via SSE não tinha NENHUM log de ciclo de vida da
        // conexão (abriu? caiu? reconectou? recebeu o evento?) — só dava pra
        // saber pelo F12 se algo já estivesse logando. Estes console.log/warn
        // não mudam nenhum comportamento, só tornam visível no console (e no
        // trace do Playwright, já habilitado) o que está acontecendo com a
        // conexão SSE no momento exato da falha.
        console.log('[pedidostatus][SSE] conectando desde_id=' + ultimoMsgId);

        ssePedido.onopen = function () {
            console.log('[pedidostatus][SSE] conexão aberta (readyState=' + ssePedido.readyState + ')');
        };

        ssePedido.addEventListener('status_update', function () {
            console.log('[pedidostatus][SSE] status_update recebido');
            atualizarStatus();
        });

        ssePedido.addEventListener('localizacao_guincho', function (event) {
            try {
                var data = JSON.parse(event.data || '{}');
                if (data.lat == null || data.lng == null) return;
                criarOuMoverGuincho(Number(data.lat), Number(data.lng));
                if (STATUS === 'a_caminho') desenharRota(STATUS);
            } catch (e) { console.warn('[pedidostatus] falha ao processar evento SSE:', e); }
        });

        ssePedido.addEventListener('nova_mensagem', function (event) {
            try {
                var msgRecebida = JSON.parse(event.data || '{}');
                console.log('[pedidostatus][SSE] nova_mensagem recebida id=' + msgRecebida.id);
                appendMensagemSse(msgRecebida);
            } catch (e) { console.warn('[pedidostatus] falha ao processar evento SSE:', e); }
        });

        ssePedido.addEventListener('session_expired', function () {
            encerrarSsePedido();
            if (window.SessionManager) window.SessionManager.handleUnauthorized();
        });

        ssePedido.addEventListener('stream_close', function () {
            console.log('[pedidostatus][SSE] stream_close recebido — servidor encerrou o stream (ex.: MAX_DURATION) e o cliente precisa reconectar.');
            encerrarSsePedido();
            atualizarStatus();
        });

        ssePedido.onerror = function () {
            // readyState: 0=CONNECTING, 1=OPEN, 2=CLOSED — importa MUITO pra
            // diagnosticar "mensagem não chegou": se isso disparar com
            // readyState=2 no meio do teste, a conexão SSE caiu de verdade
            // (Apache/rede) e ficou até 4s (reagendarSsePedido) sem escutar
            // nada — nesse intervalo, qualquer nova_mensagem só chega depois
            // do reconnect (ou nunca, se o reconnect também falhar). Sem este
            // log não havia como distinguir "mensagem nunca foi enviada" de
            // "SSE caiu bem na hora errada".
            console.warn('[pedidostatus][SSE] onerror — readyState=' + (ssePedido ? ssePedido.readyState : 'null') + ' — reconectando em 4s.');
            encerrarSsePedido();
            iniciarPollingChat();
            reagendarSsePedido();
        };
    }

    function atualizarStatus() {
        requestJson(BP + '/cliente/pedido/status-json/' + PEDIDO_ID, { headers: { 'Accept': 'application/json' } })
        .then(function (data) {
            if (!data.ok) return;

            var statusMudou = data.status !== STATUS;
            STATUS = data.status;

            // Steps + banner acompanham qualquer transição
            if (statusMudou) atualizarSteps(STATUS);
            if (statusMudou) atualizarPinsOrigemDestino(STATUS);

            // atualiza card do guincho
            if (data.guincho_nome) {
                var el = document.getElementById('gNome');
                var aw = document.getElementById('guinchoAguardando');
                if (aw) {
                    var body = document.querySelector('#cardGuincho .card-body');
                    body.innerHTML = '<p><strong>Nome:</strong> <span id="gNome">' + escHtml(data.guincho_nome) + '</span></p>'
                        + '<p><strong>Contato:</strong> <i class="fas fa-comments text-primary-custom me-1"></i>Falar pelo chat do pedido</p>'
                        + '<p><strong>Placa:</strong> <span id="gPlaca">' + escHtml(data.guincho_placa || '—') + '</span></p>';
                } else if (el) {
                    el.textContent = data.guincho_nome;
                    var placa = document.getElementById('gPlaca');
                    if (placa) placa.textContent = data.guincho_placa || '—';
                }
            } else if (STATUS === 'aguardando_guincho') {
                // Guincho cancelou e o pedido voltou para a fila
                var bodyG = document.querySelector('#cardGuincho .card-body');
                if (bodyG && !document.getElementById('guinchoAguardando')) {
                    bodyG.innerHTML = '<p id="guinchoAguardando" class="text-muted">O guincho anterior precisou cancelar. Estamos buscando outro guincho para você, sem custo adicional...</p>';
                }
                if (markerGuincho) { map.removeLayer(markerGuincho); markerGuincho = null; }
            }

            // posição do guincho + rota conforme status
            if (data.lat_guincho != null && data.lng_guincho != null) {
                criarOuMoverGuincho(Number(data.lat_guincho), Number(data.lng_guincho));
            }
            desenharRota(STATUS);
            if (data.routing) renderRoutingSnapshot(data.routing);

            // botão/preview de cancelamento acompanha o status
            atualizarBotaoCancelar(data);

            // fotos de prova de serviço aparecem sem reload
            atualizarProvas(data);

            // encerramento
            if (STATUS === 'concluido' || STATUS === 'cancelado') {
                clearInterval(pollingChat);
                clearInterval(pollingStatus);
                encerrarSsePedido();
                var mi = document.getElementById('msgInput');
                var be = document.getElementById('btnEnviar');
                if (mi) mi.disabled = true;
                if (be) be.disabled = true;

                if (STATUS === 'cancelado') {
                    var aviso = document.getElementById('avisoCancelado');
                    if (aviso) {
                        var quem = data.cancelado_por === 'guincho' ? 'pelo guincheiro'
                                 : data.cancelado_por === 'admin' ? 'pela administração' : '';
                        var taxaTxt = Number(data.taxa_cancelamento) > 0
                            ? ' Taxa de cancelamento aplicada: R$ ' + Number(data.taxa_cancelamento).toFixed(2).replace('.', ',') + '.'
                            : '';
                        aviso.textContent = 'Este pedido foi cancelado ' + quem + '.' + taxaTxt;
                        aviso.classList.remove('d-none');
                    }
                    var btn = document.getElementById('btnCancelarPedido');
                    if (btn) btn.remove();
                }
                if (STATUS === 'concluido') {
                    setTimeout(function () { window.location.href = BP + '/cliente/avaliar/' + PEDIDO_ID; }, 1500);
                }
            }
        })
        .catch(function (e) { console.warn('[pedidostatus] falha na requisição:', e); });
    }

    function atualizarProvas(data) {
        var card = document.getElementById('cardProvas');
        if (!card) return;
        var alguma = false;
        [['foto_plataforma', 'colFotoPlataforma'], ['foto_destino', 'colFotoDestino']].forEach(function (par) {
            var foto = data[par[0]];
            var col  = document.getElementById(par[1]);
            if (foto && col) {
                var url = BP + '/public/uploads/' + foto;
                col.querySelector('[data-foto-link]').href = url;
                col.querySelector('[data-foto-img]').src   = url;
                col.classList.remove('d-none');
                alguma = true;
            } else if (col && !col.classList.contains('d-none')) {
                alguma = true;
            }
        });
        if (alguma) card.classList.remove('d-none');
    }

    // ── Cancelamento com penalidade ──────────────────────────
    function atualizarBotaoCancelar(data) {
        // Bug real corrigido aqui: esta função roda a cada ciclo do polling de
        // status (poucos segundos), e sempre zerava CANCEL_PREVIEW.snapshot_id
        // — mesmo com o modal de cancelamento JÁ ABERTO e com um snapshot_id
        // válido recém-obtido em .../cancelamento-preview. Se um tick do
        // polling caísse entre o preview carregar e o clique em "Confirmar"
        // (situação normal: um usuário real lê o resumo da taxa antes de
        // confirmar), o snapshot_id sumia e o clique era bloqueado no próprio
        // JS com "Solicite o preview de cancelamento novamente." — sem NUNCA
        // chegar ao servidor. Agora, enquanto o modal estiver aberto, o
        // polling só atualiza o botão externo (pode/bloqueio), sem tocar no
        // snapshot_id que o modal já tem em mãos.
        var modalEl = document.getElementById('modalCancelar');
        var modalAberto = !!(modalEl && modalEl.classList.contains('show'));

        CANCEL_PREVIEW = {
            pode: !!data.cancel_pode,
            taxa: Number(data.cancel_taxa || 0),
            bloqueio: data.cancel_bloqueio || null,
            isento_ate: data.cancel_isento_ate || null,
            snapshot_id: modalAberto ? CANCEL_PREVIEW.snapshot_id : null
        };
        var btn = document.getElementById('btnCancelarPedido');
        if (!btn) return;
        btn.disabled = !CANCEL_PREVIEW.pode;
        btn.title = CANCEL_PREVIEW.bloqueio || '';
    }

    function textoResumoTaxa() {
        if (CANCEL_PREVIEW.taxa > 0) {
            return 'Como o guincho já está a caminho, será cobrada uma taxa de cancelamento de '
                 + '<strong>R$ ' + CANCEL_PREVIEW.taxa.toFixed(2).replace('.', ',') + '</strong>. '
                 + 'O restante do valor pago será estornado automaticamente.';
        }
        var extra = CANCEL_PREVIEW.isento_ate
            ? ' (cancelamento gratuito até ' + escHtml(CANCEL_PREVIEW.isento_ate) + ')'
            : '';
        return 'Nenhuma taxa será cobrada' + extra + '. Se houver pagamento aprovado, o estorno integral será solicitado automaticamente.';
    }

    function initCancelamento() {
        var btn = document.getElementById('btnCancelarPedido');
        if (!btn) return;

        // Pacote L1.6: o backend agora exige um snapshot_id (obtido via
        // GET .../cancelamento-preview) para confirmar o cancelamento —
        // ele registra a versão da fórmula, os fatores e um hash do
        // cálculo no momento do preview, para auditoria e para evitar que
        // o valor cobrado mude entre o preview e a confirmação. O botão
        // sozinho (CANCEL_PREVIEW vindo do polling de status) só tem
        // pode/taxa/bloqueio — sem snapshot_id — então buscamos o preview
        // de verdade (que persiste o snapshot) ao abrir o modal.
        var confirmBtn = document.getElementById('btnConfirmarCancelamento');

        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            document.getElementById('cancelErro').textContent = '';
            document.getElementById('cancelResumoTaxa').innerHTML = 'Calculando taxa de cancelamento...';
            confirmBtn.disabled = true;
            var modal = new bootstrap.Modal(document.getElementById('modalCancelar'));
            modal.show();

            // Rota real (index.php): prefixo + id no final, igual a
            // /cliente/pedido/status-json/{id} — NÃO /cliente/pedido/{id}/cancelamento-preview
            // (isso estava 404 silencioso e travava o botão de confirmar para sempre).
            requestJson(BP + '/cliente/cancelamento-preview/' + PEDIDO_ID, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function (data) {
                if (!data.ok || !data.pode || !data.snapshot_id) {
                    document.getElementById('cancelResumoTaxa').innerHTML = '';
                    document.getElementById('cancelErro').textContent =
                        (data && data.motivo_bloqueio) || (data && data.erro) || 'Cancelamento indisponível no momento.';
                    return;
                }
                CANCEL_PREVIEW = {
                    pode: true,
                    taxa: Number(data.taxa || 0),
                    bloqueio: data.motivo_bloqueio || null,
                    isento_ate: data.isento_ate || null,
                    snapshot_id: data.snapshot_id
                };
                document.getElementById('cancelResumoTaxa').innerHTML = textoResumoTaxa();
                confirmBtn.disabled = false;
            })
            .catch(function () {
                document.getElementById('cancelResumoTaxa').innerHTML = '';
                document.getElementById('cancelErro').textContent = 'Erro de conexão ao calcular a taxa. Tente novamente.';
            });
        });

        confirmBtn.addEventListener('click', function () {
            var self = this;
            if (!CANCEL_PREVIEW.snapshot_id) {
                document.getElementById('cancelErro').textContent = 'Solicite o preview de cancelamento novamente.';
                return;
            }
            self.disabled = true;
            // O rótulo do campo diz "opcional", mas o backend
            // (ClienteController::cancelarPedido) exige motivo não-vazio —
            // sem isso o cancelamento falhava silenciosamente com "Informe o
            // motivo do cancelamento." mesmo o usuário nunca vendo esse erro
            // como bloqueio, já que o campo parecia livre pra deixar em
            // branco. Mantém o campo realmente opcional pro usuário e
            // preenche um motivo padrão quando ele não escreve nada.
            var motivo = document.getElementById('cancelMotivo').value.trim() || 'Cliente não informou motivo.';
            requestJson(BP + '/cliente/cancelar/' + PEDIDO_ID, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: 'csrf_token=' + encodeURIComponent(CSRF)
                    + '&motivo=' + encodeURIComponent(motivo)
                    + '&snapshot_id=' + encodeURIComponent(CANCEL_PREVIEW.snapshot_id)
            })
            .then(function (data) {
                if (data.ok) {
                    window.location.href = BP + '/cliente/historico?cancelado=1';
                } else {
                    document.getElementById('cancelErro').textContent = data.erro || 'Não foi possível cancelar.';
                    self.disabled = false;
                }
            })
            .catch(function () {
                document.getElementById('cancelErro').textContent = 'Erro de conexão. Tente novamente.';
                self.disabled = false;
            });
        });
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    document.addEventListener('guincha:session-expired', encerrarSsePedido);
    window.addEventListener('beforeunload', encerrarSsePedido);
})();
</script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/atendimento-status.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', () => {
    if (window.AtendimentoStatus) {
        AtendimentoStatus.start({ bannerSelector: '#statusBannerCliente' });
    }
});
</script>
