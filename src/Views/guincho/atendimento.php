<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$statusLabels = [
    'a_caminho'  => ['label' => 'A Caminho',    'icon' => 'fa-truck-fast',     'cor' => 'warning'],
    'no_local'   => ['label' => 'No Local',     'icon' => 'fa-map-pin',        'cor' => 'info'],
    'em_reboque' => ['label' => 'Em Reboque',   'icon' => 'fa-truck-ramp-box', 'cor' => 'primary'],
    'concluido'  => ['label' => 'Concluído',    'icon' => 'fa-circle-check',   'cor' => 'success'],
];
$proximoStatus = [
    'a_caminho'  => ['acao' => 'Cheguei ao Local',    'icone' => 'fa-map-pin'],
    'no_local'   => ['acao' => 'Iniciar Reboque',     'icone' => 'fa-truck-ramp-box'],
    'em_reboque' => ['acao' => 'Finalizar Corrida',   'icone' => 'fa-flag-checkered'],
];
$statusAtual = $pedido['status'] ?? 'a_caminho';
$info = $statusLabels[$statusAtual] ?? $statusLabels['a_caminho'];
$proximo = $proximoStatus[$statusAtual] ?? null;

$latGuincho = isset($pedido['lat_guincho']) && $pedido['lat_guincho'] !== null ? (float)$pedido['lat_guincho'] : null;
$lngGuincho = isset($pedido['lng_guincho']) && $pedido['lng_guincho'] !== null ? (float)$pedido['lng_guincho'] : null;

$cfgPenalidade = (float)(Configuracao::get('penalidade_reputacao_cancelamento', '0.25'));
include __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../components/vehicle_brand_badge.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/tow-atendimento.css">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Operação</span>
            <h1><i class="fas fa-truck-fast me-2 text-primary-custom"></i>Atendimento #<?php echo (int)$pedido['id']; ?></h1>
            <p>
                <span class="badge bg-<?php echo $info['cor']; ?>" id="badgeStatus">
                    <i class="fas <?php echo $info['icon']; ?> me-1"></i><span id="badgeStatusLabel"><?php echo $info['label']; ?></span>
                </span>
                <span class="badge bg-secondary d-none" id="badgeGps" title="Envio da sua localização para o cliente">
                    <i class="fas fa-location-crosshairs me-1"></i>GPS
                </span>
            </p>
        </div>
        <?php if ($statusAtual === 'a_caminho'): ?>
        <div>
            <button id="btnCancelarAtendimento" class="btn btn-outline-danger">
                <i class="fas fa-ban me-1"></i>Cancelar Atendimento
            </button>
        </div>
        <?php endif; ?>
    </header>

    <div id="avisoCanceladoGuincho" class="alert alert-warning d-none"></div>

    <div class="att-hero-card">
        <div class="att-hero-top">
            <div class="att-hero-driver">
                <div class="att-hero-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="att-hero-title"><?php echo htmlspecialchars($pedido['cliente_nome'] ?? 'Cliente'); ?></div>
                    <div class="att-hero-subtitle">
                        Pedido #<?php echo (int)$pedido['id']; ?> ·
                        <?php echo htmlspecialchars(($pedido['marca'] ?? '') . ' ' . ($pedido['modelo'] ?? '') . ' · ' . ($pedido['placa'] ?? '')); ?>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <span class="badge bg-<?php echo $info['cor']; ?>">
                    <i class="fas <?php echo $info['icon']; ?> me-1"></i><?php echo $info['label']; ?>
                </span>
                <?php if ($statusAtual === 'a_caminho'): ?>
                <span class="badge bg-secondary"><i class="fas fa-location-crosshairs me-1"></i>GPS ativo</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="att-hero-grid">
            <div class="att-hero-item">
                <div class="small">Origem</div>
                <div class="fw-semibold"><?php echo htmlspecialchars((string)($pedido['endereco_origem'] ?? '-')); ?></div>
            </div>
            <div class="att-hero-item">
                <div class="small">Destino</div>
                <div class="fw-semibold"><?php echo htmlspecialchars((string)($pedido['endereco_destino'] ?? '-')); ?></div>
            </div>
            <div class="att-hero-item">
                <div class="small">Valor estimado</div>
                <div class="fw-semibold text-success">R$ <?php echo number_format((float)($pedido['custo_estimado'] ?? 0), 2, ',', '.'); ?></div>
            </div>
        </div>

        <div class="att-hero-actions">
            <?php if ($proximo && $statusAtual !== 'concluido' && !$ehServicoLocal): ?>
                <button type="button" class="btn btn-<?php echo $info['cor']; ?>" onclick="document.getElementById('statusForm')?.scrollIntoView({behavior:'smooth', block:'center'});">
                    <i class="fas <?php echo $proximo['icone']; ?> me-2"></i><?php echo htmlspecialchars($proximo['acao']); ?>
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('chatBox')?.scrollIntoView({behavior:'smooth', block:'center'});">
                <i class="fas fa-comments me-2"></i>Chat
            </button>
        </div>
    </div>

    <div class="row g-4">

        <!-- COLUNA ESQUERDA: mapa -->
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-map-location-dot me-2"></i>Rota</span>
                    <small class="text-muted" id="rotaLegenda"></small>
                </div>
                <div class="card-body p-0">
                    <div id="map" class="atendimento-map"></div>
                </div>
            </div>
        </div>

        <!-- COLUNA DIREITA: pedido + ações + chat -->
        <div class="col-lg-5 d-flex flex-column gap-3">

            <!-- Dados do pedido -->
            <div class="card">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Pedido</div>
                <div class="card-body">
                    <p class="mb-1"><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nome'] ?? '-'); ?></p>
                    <p class="mb-1"><strong>Contato:</strong> <i class="fas fa-comments text-primary-custom me-1"></i>Use o chat do pedido</p>
                    <p class="mb-1"><strong>Veículo:</strong> <?php echo vehicle_identity_html($pedido['marca'] ?? '', $pedido['modelo'] ?? '', $pedido['veiculo_tipo'] ?? 'carro', $pedido['placa'] ?? '', 28); ?></p>
                    <p class="mb-1"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo htmlspecialchars($pedido['endereco_origem'] ?? '-'); ?></p>
                    <p class="mb-1"><i class="fas fa-map-pin text-success me-1"></i><?php echo htmlspecialchars($pedido['endereco_destino'] ?? '-'); ?></p>
                    <p class="mb-0"><strong class="text-success fs-5">R$ <?php echo number_format((float)($pedido['custo_estimado'] ?? 0), 2, ',', '.'); ?></strong>
                       <span class="text-muted small"> estimado</span></p>
                </div>
            </div>

            <!-- Ação de status -->
            <?php if ($ehServicoLocal && !in_array($statusAtual, ['concluido', 'cancelado', 'em_reboque'], true)): ?>
            <!-- Etapa 5 — painel de diagnóstico/orçamento/execução (serviços não-reboque) -->
            <div class="card border-info">
                <div class="card-header"><i class="fas fa-stethoscope me-2"></i>Diagnóstico e execução</div>
                <div class="card-body">

                    <?php if (!empty($flash)): ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> small">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($statusAtual === 'a_caminho'): ?>
                        <!-- Bug real (QA, 31/07/2026): faltava aqui o caso 'a_caminho' —
                             este painel (serviços ON_SITE/não-reboque) pulava direto de
                             "nenhum card" pra 'no_local', sem NENHUM jeito de o prestador
                             confirmar chegada na tela. #statusForm/#btnAtualizarStatus/
                             #statusMsg reaproveitam o MESMO listener JS já existente mais
                             abaixo (linha ~839: document.getElementById('statusForm')),
                             que já faz POST /guincho/pedido/status-atualizar/{id} — mesmo
                             endpoint usado pelo reboque. Chegada não exige foto (ver
                             qa/helpers/evidence.ts::confirmarChegada). -->
                        <form id="statusForm" enctype="multipart/form-data" data-marketing-event="service_status_update">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                            <?php if (!empty($evidenceToken['token'])): ?>
                            <input type="hidden" name="evidence_token" value="<?php echo htmlspecialchars((string)$evidenceToken['token']); ?>">
                            <?php endif; ?>
                            <button type="submit" id="btnAtualizarStatus" class="btn btn-<?php echo $info['cor']; ?> btn-lg w-100 py-3">
                                <i class="fas fa-map-pin me-2"></i>Cheguei ao Local
                            </button>
                        </form>
                        <div id="statusMsg" class="mt-2 text-muted small"></div>

                    <?php elseif ($statusAtual === 'no_local'): ?>
                        <p class="text-muted small">Você chegou ao local. Envie a foto de chegada e inicie o diagnóstico antes de executar o serviço.</p>
                        <form method="post" action="<?php echo $bp; ?>/guincho/diagnostico/iniciar/<?php echo (int)$pedido['id']; ?>" enctype="multipart/form-data" data-marketing-event="service_diagnosis_start">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <?php if (!empty($evidenceToken['token'])): ?>
                            <input type="hidden" name="evidence_token" value="<?php echo htmlspecialchars((string)$evidenceToken['token']); ?>">
                            <?php endif; ?>
                            <div class="mb-3 text-start">
                                <label class="form-label">Foto de chegada (Obrigatório)</label>
                                <input type="file" name="foto_chegada" class="form-control" accept="image/*" required>
                                <div class="form-text text-muted small">
                                    <i class="fas fa-info-circle me-1"></i> Documenta o estado do veículo antes de qualquer intervenção — prova de comparecimento (Proof-of-Service).
                                </div>
                            </div>
                            <button type="submit" class="btn btn-info btn-lg w-100 py-3">
                                <i class="fas fa-magnifying-glass me-2"></i>Iniciar diagnóstico
                            </button>
                        </form>

                    <?php elseif ($statusAtual === 'diagnostico_iniciado'): ?>
                        <form method="post" action="<?php echo $bp; ?>/guincho/diagnostico/concluir/<?php echo (int)$pedido['id']; ?>" enctype="multipart/form-data" data-marketing-event="service_diagnosis_complete">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <?php if (!empty($evidenceToken['token'])): ?>
                            <input type="hidden" name="evidence_token" value="<?php echo htmlspecialchars((string)$evidenceToken['token']); ?>">
                            <?php endif; ?>
                            <div class="mb-3 text-start">
                                <label class="form-label">Foto do parecer/orçamento <span class="text-danger">Obrigatória</span></label>
                                <input type="file" name="foto_parecer" class="form-control" accept="image/jpeg,image/png" required>
                                <div class="form-text text-muted small"><i class="fas fa-shield-halved me-1"></i>Registra o estado do veículo e o diagnóstico no local. O cliente verá o orçamento antes de pagar qualquer adicional.</div>
                            </div>
                            <div class="mb-3 text-start">
                                <label class="form-label">Resultado do diagnóstico</label>
                                <select name="resultado" class="form-select" required>
                                    <option value="RESOLVIDO_SEM_ORCAMENTO">Resolvo agora, sem custo adicional</option>
                                    <option value="REQUER_ORCAMENTO">Preciso de peça/serviço extra — cliente precisa aprovar orçamento</option>
                                    <option value="REQUER_REBOQUE">Não dá para resolver no local — precisa de reboque</option>
                                </select>
                            </div>
                            <div class="mb-3 text-start">
                                <label class="form-label">O que foi encontrado</label>
                                <textarea name="descricao" class="form-control" rows="2" placeholder="Ex.: bateria com célula morta, não segura carga"></textarea>
                            </div>
                            <div class="mb-3 text-start" id="orcamentoItens">
                                <label class="form-label">Itens do orçamento complementar <span class="text-muted">(só se marcou "preciso de peça/serviço extra")</span></label>
                                <p class="text-muted small mb-2">Se o item consome uma peça do seu estoque, selecione o produto — a baixa é automática quando o cliente aprovar. Deixe em "Sem produto (mão de obra/serviço)" para itens que não consomem estoque físico.</p>
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                <div class="border rounded p-2 mb-2">
                                    <div class="input-group input-group-sm mb-1">
                                        <input type="text" name="item_descricao[]" class="form-control" placeholder="Descrição (ex.: bateria 60Ah)">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" name="item_valor[]" class="form-control" placeholder="0,00" style="max-width:100px">
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <label class="input-group-text" for="item_produto_id_<?php echo $i; ?>">Produto</label>
                                        <select name="item_produto_id[]" id="item_produto_id_<?php echo $i; ?>" class="form-select">
                                            <option value="">Sem produto (mão de obra/serviço)</option>
                                            <?php foreach (($estoquePrestador ?? []) as $item): ?>
                                            <option value="<?php echo (int)$item['produto_id']; ?>">
                                                <?php echo htmlspecialchars((string)$item['nome']); ?> — saldo: <?php echo (int)$item['quantidade']; ?> <?php echo htmlspecialchars((string)($item['unidade'] ?? 'un')); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="input-group-text">Qtd</span>
                                        <input type="number" name="item_quantidade[]" class="form-control" placeholder="1" min="1" style="max-width:80px">
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <button type="submit" class="btn btn-info btn-lg w-100 py-3">
                                <i class="fas fa-clipboard-check me-2"></i>Concluir diagnóstico
                            </button>
                        </form>

                    <?php elseif ($statusAtual === 'autorizacao_servico_pendente'): ?>
                        <div class="alert alert-warning small mb-0">
                            <i class="fas fa-hourglass-half me-1"></i>Aguardando o cliente aprovar o orçamento complementar.
                            <?php if ($orcamento): ?>
                            <hr>
                            <strong>Total: R$ <?php echo number_format((float)$orcamento['valor_total'], 2, ',', '.'); ?></strong>
                            <ul class="mb-0 mt-1">
                                <?php foreach (($orcamento['itens'] ?? []) as $item): ?>
                                <li><?php echo htmlspecialchars($item['descricao']); ?> — R$ <?php echo number_format((float)$item['valor'], 2, ',', '.'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($statusAtual === 'em_execucao_servico'): ?>
                        <p class="text-muted small">Execute o serviço e confirme quando terminar.</p>
                        <form method="post" action="<?php echo $bp; ?>/guincho/execucao/concluir/<?php echo (int)$pedido['id']; ?>" data-marketing-event="service_execution_complete">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <button type="submit" class="btn btn-info btn-lg w-100 py-3">
                                <i class="fas fa-check-double me-2"></i>Concluí a execução
                            </button>
                        </form>

                    <?php elseif ($statusAtual === 'teste_final'): ?>
                        <form method="post" action="<?php echo $bp; ?>/guincho/teste-final/concluir/<?php echo (int)$pedido['id']; ?>" enctype="multipart/form-data" data-marketing-event="service_complete">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <?php if (!empty($evidenceToken['token'])): ?>
                            <input type="hidden" name="evidence_token" value="<?php echo htmlspecialchars((string)$evidenceToken['token']); ?>">
                            <?php endif; ?>
                            <div class="mb-3 text-start">
                                <label class="form-label">O veículo voltou a funcionar corretamente?</label>
                                <select name="resolvido" class="form-select" onchange="document.getElementById('fotoFinalBlock').classList.toggle('d-none', this.value !== '1')" required>
                                    <option value="1">Sim, funcionando normalmente</option>
                                    <option value="">Não — precisa de reboque</option>
                                </select>
                            </div>
                            <div class="mb-3 text-start" id="fotoFinalBlock">
                                <label class="form-label">Foto de conclusão (obrigatório se resolvido)</label>
                                <input type="file" name="foto_destino" class="form-control" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-info btn-lg w-100 py-3">
                                <i class="fas fa-flag-checkered me-2"></i>Confirmar resultado
                            </button>
                        </form>

                    <?php elseif ($statusAtual === 'conversao_reboque_pendente'): ?>
                        <div class="alert alert-secondary small mb-0">
                            <i class="fas fa-truck-ramp-box me-1"></i>Este atendimento precisa de reboque. Aguardando o cliente aprovar a conversão.
                        </div>

                    <?php elseif ($statusAtual === 'conversao_aprovada_cliente'): ?>
                        <div class="alert alert-secondary small mb-0">
                            <i class="fas fa-spinner fa-spin me-1"></i>Conversão aprovada — processando encaminhamento para o reboque.
                        </div>

                    <?php elseif ($statusAtual === 'preparacao_veiculo'): ?>
                        <p class="text-muted small">Conversão aprovada e você continua com este atendimento (capacidade de reboque já aprovada). Envie a foto de coleta para iniciar o reboque.</p>
                        <form method="post" action="<?php echo $bp; ?>/guincho/preparacao/concluir/<?php echo (int)$pedido['id']; ?>" enctype="multipart/form-data" data-marketing-event="tow_start">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <?php if (!empty($evidenceToken['token'])): ?>
                            <input type="hidden" name="evidence_token" value="<?php echo htmlspecialchars((string)$evidenceToken['token']); ?>">
                            <?php endif; ?>
                            <div class="mb-3 text-start">
                                <label class="form-label">Foto na Plataforma (Obrigatório)</label>
                                <input type="file" name="foto_plataforma" class="form-control" accept="image/*" required>
                            </div>
                            <button type="submit" class="btn btn-info btn-lg w-100 py-3">
                                <i class="fas fa-truck-ramp-box me-2"></i>Iniciar reboque
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
            <?php elseif ($proximo && $statusAtual !== 'concluido'): ?>
            <div class="card border-<?php echo $info['cor']; ?>">
                <div class="card-body text-center">
                    <form id="statusForm" enctype="multipart/form-data" data-marketing-event="service_status_update">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                        <?php if (!empty($evidenceToken['token'])): ?>
                        <input type="hidden" name="evidence_token" value="<?php echo htmlspecialchars((string)$evidenceToken['token']); ?>">
                        <?php endif; ?>

                        <?php if ($statusAtual === 'no_local'): ?>
                        <div class="mb-3 text-start">
                            <label class="form-label">Foto na Plataforma (Obrigatório)</label>
                            <input type="file" name="foto_plataforma" class="form-control" accept="image/*" required>
                            <div class="form-text text-muted small">
                                <i class="fas fa-info-circle me-1"></i> Enquadre o veículo inteiro e documente danos pré-existentes.
                            </div>
                        </div>
                        <?php elseif ($statusAtual === 'em_reboque'): ?>
                        <div class="mb-3 text-start">
                            <label class="form-label">Foto no Destino (Obrigatório)</label>
                            <input type="file" name="foto_destino" class="form-control" accept="image/*" required>
                            <div class="form-text text-muted small">
                                <i class="fas fa-info-circle me-1"></i> Fotografe o veículo já fora da plataforma no local de entrega.
                            </div>
                        </div>
                        <?php endif; ?>

                        <button type="submit" id="btnAtualizarStatus" class="btn btn-<?php echo $info['cor']; ?> btn-lg w-100 py-3">
                            <i class="fas <?php echo $proximo['icone']; ?> me-2"></i>
                            <?php echo htmlspecialchars($proximo['acao']); ?>
                        </button>
                    </form>
                    <div id="statusMsg" class="mt-2 text-muted small"></div>
                </div>
            </div>

            <?php else: ?>
            <div class="alert alert-success text-center mb-0">
                <i class="fas fa-circle-check fa-2x mb-2 d-block"></i>
                <strong>Corrida Concluída!</strong><br>
                O PIX será processado em breve.<br>
                <a href="<?php echo $bp; ?>/guincho/financeiro" class="btn btn-success btn-sm mt-2">Ver Financeiro</a>
            </div>
            <?php endif; ?>

            <!-- Chat -->
            <div class="card flex-grow-1">
                <div class="card-header"><i class="fas fa-comments me-2"></i>Chat com Cliente</div>
                <div class="card-body d-flex flex-column p-2 atendimento-chat-body">
                    <div id="chatBox" class="flex-grow-1 overflow-auto mb-2 atendimento-chatbox">
                        <?php foreach ($mensagens ?? [] as $msg): ?>
                        <div class="chat-msg <?php echo ((int)$msg['usuario_id'] === (int)($_SESSION['user']['id'] ?? 0)) ? 'mine' : 'other'; ?> mb-1">
                            <?php if ((int)$msg['usuario_id'] !== (int)($_SESSION['user']['id'] ?? 0)): ?>
                            <div class="sender small text-muted"><?php echo htmlspecialchars($pedido['cliente_nome'] ?? 'Cliente'); ?></div>
                            <?php endif; ?>
                            <div class="bubble"><?php echo htmlspecialchars($msg['mensagem']); ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($mensagens)): ?>
                        <div class="text-center text-muted small py-3">Nenhuma mensagem ainda.</div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="text" id="msgInput" class="form-control form-control-sm" placeholder="Mensagem...">
                        <button id="btnEnviarMsg" class="btn btn-primary btn-sm px-3"
                                data-pedido-id="<?php echo (int)$pedido['id']; ?>"
                                data-csrf="<?php echo htmlspecialchars($csrfToken); ?>">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /row -->

    <!-- Modal de cancelamento pelo guincheiro -->
    <div class="modal fade" id="modalCancelarGuincho" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban text-danger me-2"></i>Cancelar Atendimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        Cancelar um atendimento aceito aplica uma <strong>penalidade de
                        <?php echo number_format($cfgPenalidade, 2, ',', '.'); ?> ponto(s) na sua reputação</strong>
                        e o pedido volta para a fila de outros guincheiros.
                    </div>
                    <label class="form-label">Motivo (obrigatório)</label>
                    <textarea id="cancelMotivoGuincho" class="form-control" rows="2" maxlength="255"
                              placeholder="Ex.: problema mecânico no guincho"></textarea>
                    <div id="cancelErroGuincho" class="text-danger small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarCancelamentoGuincho">
                        <i class="fas fa-ban me-1"></i>Confirmar Cancelamento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPedidoCanceladoGuincho" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-circle-exclamation text-warning me-2"></i>Atendimento Cancelado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        Este pedido foi cancelado e você voltou a ficar disponível para novos chamados.
                    </div>
                    <div id="cancelamentoResumoGuincho" class="small text-muted"></div>
                </div>
                <div class="modal-footer">
                    <a href="<?php echo $bp; ?>/guincho/dashboard" class="btn btn-primary">Ir para o painel</a>
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
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/core/offline-queue.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/core/gps-resilience.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
'use strict';

const BP         = <?php echo json_encode($bp); ?>;
const PEDIDO_ID  = <?php echo (int)$pedido['id']; ?>;
const CSRF       = <?php echo json_encode($csrfToken); ?>;
let   STATUS     = <?php echo json_encode($statusAtual); ?>;
const LAT_ORIGEM = <?php echo (float)($pedido['lat_origem'] ?? -23.5505); ?>;
const LNG_ORIGEM = <?php echo (float)($pedido['lng_origem'] ?? -46.6333); ?>;
const LAT_DEST   = <?php echo (float)($pedido['lat_destino'] ?? -23.5505); ?>;
const LNG_DEST   = <?php echo (float)($pedido['lng_destino'] ?? -46.6333); ?>;
let   latEu      = <?php echo json_encode($latGuincho); ?>;
let   lngEu      = <?php echo json_encode($lngGuincho); ?>;

let map, rotaControl, rotaModo = null, markerEu = null;
let gpsWatchId = null, ultimoEnvioGps = 0;
let ultimoPontoBom = null;   // {payload, ts} — resiliência a queda de sensor
let gpsRetryTimer = null, gpsBackoffMs = 5000; // backoff ao perder o fix
let pollingStatusServidor = null, pollingChat = null, ssePedido = null, sseRetryTimer = null;
let gpsSequence = <?php echo (int)($porSnapshot['last_point']['sequence_number'] ?? 0); ?>;
let modalCancelamentoMostrado = false;

// §A4 — antes cada ponto de uso escapava só "<" na mão
// (`.replace(/</g,'&lt;')`), inconsistente com o escapeHtml() completo já
// usado em public/assets/js/atendimento-status.js. Não era explorável (sem
// "<" não dá pra abrir tag), mas era escape incompleto mesmo assim.
function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
}

function requestJson(url, options) {
    if (window.apiFetch) return window.apiFetch(url, options || {});
    return fetch(url, options || {}).then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    });
}

// Pinos de origem/destino: vermelho até chegar naquele ponto, cinza depois —
// mesma convenção usada em todos os mapas do sistema (admin/dashboard,
// admin/pedidodetalhe, admin/pedido_trilha, cliente/pedidostatus).
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

// ── Mapa + rota dinâmica ─────────────────────────────────────────
// a_caminho ............. minha posição → origem (busca do veículo)
// no_local/em_reboque ... origem → destino (reboque)
document.addEventListener('DOMContentLoaded', () => {
    map = L.map('map').setView([LAT_ORIGEM, LNG_ORIGEM], 13);
    L.Icon.Default.imagePath = BP + '/public/assets/img/leaflet/';
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Pinos de origem/destino: vermelho até eu chegar naquele ponto, cinza
    // depois — mesma convenção usada em todos os mapas do sistema. Como
    // qualquer mudança de status aqui recarrega a página (ver
    // iniciarPollingStatusServidor abaixo), a cor já nasce certa a cada load.
    L.marker([LAT_ORIGEM, LNG_ORIGEM], { icon: pinIcon(statusIndicaChegouOrigem(STATUS), 'fa-location-dot') }).addTo(map).bindPopup('Origem');
    L.marker([LAT_DEST, LNG_DEST], { icon: pinIcon(statusIndicaChegouDestino(STATUS), 'fa-flag-checkered') }).addTo(map).bindPopup('Destino');

    if (latEu != null && lngEu != null) criarOuMoverEu(latEu, lngEu);
    desenharRota();

    if (!['concluido', 'cancelado'].includes(STATUS)) {
        iniciarGps();
        iniciarSsePedido();
        iniciarPollingStatusServidor();
    }
});

function criarOuMoverEu(lat, lng) {
    if (markerEu) {
        markerEu.setLatLng([lat, lng]);
    } else {
        markerEu = L.marker([lat, lng], {
            icon: L.divIcon({ className: '', html: '<i class="fas fa-truck atendimento-guincho-icon"></i>' })
        }).addTo(map).bindPopup('Você');
    }
}

function desenharRota() {
    let modo, waypoints, cor, legenda;

    if (STATUS === 'a_caminho' && latEu != null && lngEu != null) {
        modo      = 'eu_origem';
        waypoints = [L.latLng(latEu, lngEu), L.latLng(LAT_ORIGEM, LNG_ORIGEM)];
        cor       = '#fd7e14';
        legenda   = 'Você → local do cliente';
    } else {
        modo      = 'origem_destino';
        waypoints = [L.latLng(LAT_ORIGEM, LNG_ORIGEM), L.latLng(LAT_DEST, LNG_DEST)];
        cor       = '#0d6efd';
        legenda   = 'Origem → destino';
    }

    const legendaEl = document.getElementById('rotaLegenda');
    if (legendaEl) legendaEl.textContent = legenda;

    if (rotaControl && rotaModo === modo) {
        if (modo === 'eu_origem') rotaControl.setWaypoints(waypoints);
        return;
    }
    if (rotaControl) { map.removeControl(rotaControl); rotaControl = null; }
    rotaModo = modo;
    rotaControl = L.Routing.control({
        waypoints,
        lineOptions: { styles: [{color: cor, weight: 6, opacity: 0.8}] },
        createMarker: () => null,
        addWaypoints: false,
        draggableWaypoints: false,
        fitSelectedRoutes: true,
        show: false
    }).addTo(map);
}

// ── GPS contínuo: envia posição ao servidor p/ o cliente ver ─────

/** Monta o payload de um ponto POR a partir de uma posição do geolocation. */
function montarPontoPor(pos) {
    gpsSequence += 1;
    return {
        csrf_token: CSRF,
        pedido_id: String(PEDIDO_ID),
        latitude: String(pos.coords.latitude),
        longitude: String(pos.coords.longitude),
        accuracy_m: String(pos.coords.accuracy || ''),
        speed_mps: String(pos.coords.speed ?? ''),
        heading_deg: String(pos.coords.heading ?? ''),
        device_timestamp: String(pos.timestamp || Date.now()),
        sequence: String(gpsSequence),
        client_point_id: (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('pt-' + Date.now() + '-' + gpsSequence)
    };
}

/** Envia um ponto ao endpoint real de rastreamento. Rejeita em falha de rede/servidor. */
function enviarPontoPor(ponto) {
    const body = new URLSearchParams(ponto);
    return requestJson(BP + '/guincho/localizacao', { method: 'POST', body });
}

let gpsFlushTimer = null;

/** Tenta reenviar pontos que ficaram na fila offline (IndexedDB) por falha de rede anterior. */
function tentarEsvaziarFilaPor() {
    if (!window.PorOfflineQueue) return;
    window.PorOfflineQueue.flush(enviarPontoPor).catch(() => {});
}

/** Trata um fix bem-sucedido do GPS: move o pino, guarda o último ponto bom e
 *  envia ao servidor (respeitando o throttle). Usado tanto pelo watchPosition
 *  quanto pelas retentativas de recuperação (getCurrentPosition). */
function processarFixGps(pos) {
    // GPS voltou: zera o backoff e cancela a retentativa agendada.
    gpsBackoffMs = 5000;
    if (gpsRetryTimer) { clearTimeout(gpsRetryTimer); gpsRetryTimer = null; }

    latEu = pos.coords.latitude;
    lngEu = pos.coords.longitude;
    criarOuMoverEu(latEu, lngEu);
    if (STATUS === 'a_caminho') desenharRota();

    const badge = document.getElementById('badgeGps');
    const nivel = window.GpsResilience ? window.GpsResilience.accuracyLevel(pos.coords.accuracy) : { key: 'good' };

    const ponto = montarPontoPor(pos);
    ultimoPontoBom = { payload: ponto, ts: Date.now() };
    // Persiste a última posição boa (sobrevive a reload / queda de sinal).
    if (window.GpsResilience && nivel.key !== 'poor') {
        window.GpsResilience.saveLastGood(PEDIDO_ID, { lat: latEu, lng: lngEu, accuracy: pos.coords.accuracy, ts: Date.now() });
    }

    // Throttle: no máximo 1 envio a cada 10s.
    const agora = Date.now();
    if (agora - ultimoEnvioGps < 10000) return;
    ultimoEnvioGps = agora;

    enviarPontoPor(ponto)
        .then(d => {
            if (badge) {
                badge.classList.remove('d-none', 'bg-danger');
                const preciso = nivel.key === 'good' && !!d.ok && d.accepted !== false;
                badge.classList.toggle('bg-success', preciso);
                badge.classList.toggle('bg-warning', !preciso);
                badge.title = preciso ? 'Enviando sua localização ao cliente'
                    : (nivel.key === 'good' ? 'Ponto recebido, aguardando validação' : 'Sinal fraco (' + nivel.label + ') — enviando mesmo assim');
            }
        })
        .catch(() => {
            // Falha de REDE: não perde o ponto — guarda no IndexedDB e reenvia depois.
            if (window.PorOfflineQueue) window.PorOfflineQueue.add(ponto).catch(() => {});
            if (badge) { badge.classList.remove('d-none', 'bg-success'); badge.classList.add('bg-warning'); badge.title = 'Sem conexão — pontos guardados localmente'; }
        });
}

/** Falha de SENSOR GPS (permissão/sem fix/timeout): sinaliza e agenda
 *  retentativa com backoff, em vez de desistir. A última posição boa continua
 *  persistida — o cliente vê "sinal instável" com a idade do último ponto. */
function tratarErroGps(err) {
    const badge = document.getElementById('badgeGps');
    const msg = window.GpsResilience ? window.GpsResilience.geoErrorMessage(err) : 'Ative o GPS para o cliente acompanhar você';
    if (badge) { badge.classList.remove('d-none', 'bg-success', 'bg-warning'); badge.classList.add('bg-danger'); badge.title = msg; }

    if (gpsRetryTimer || !navigator.geolocation) return;
    gpsRetryTimer = setTimeout(() => {
        gpsRetryTimer = null;
        // maximumAge alto: aceita um fix em cache recente para atravessar
        // buracos curtos de sinal sem esperar um fix novo do zero.
        navigator.geolocation.getCurrentPosition(processarFixGps, tratarErroGps,
            { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 });
        gpsBackoffMs = Math.min(gpsBackoffMs * 2, 60000); // até 1 min entre tentativas
    }, gpsBackoffMs);
}

/** Envia o último ponto bom via sendBeacon antes de a aba morrer/ir a background,
 *  e tenta esvaziar a fila offline. Fecha a lacuna do ponto corrente perdido
 *  quando o SO mata a página no mobile. */
function flushAntesDeSair() {
    tentarEsvaziarFilaPor();
    if (ultimoPontoBom && window.GpsResilience) {
        window.GpsResilience.beacon(BP + '/guincho/localizacao', ultimoPontoBom.payload);
    }
}

function iniciarGps() {
    if (!navigator.geolocation) return;

    // Reenvia pontos pendentes assim que a conexão voltar, e periodicamente
    // como rede de segurança (o evento 'online' do navegador nem sempre é
    // confiável em redes móveis instáveis).
    window.addEventListener('online', tentarEsvaziarFilaPor);
    gpsFlushTimer = setInterval(tentarEsvaziarFilaPor, 20000);
    if (window.SessionManager) window.SessionManager.registerPolling(gpsFlushTimer);
    tentarEsvaziarFilaPor();

    // À prova de fechamento: garante o último ponto quando a aba some.
    window.addEventListener('pagehide', flushAntesDeSair);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flushAntesDeSair();
    });

    gpsWatchId = navigator.geolocation.watchPosition(
        processarFixGps,
        tratarErroGps,
        { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
    );
}

function pararGps() {
    if (gpsWatchId !== null && navigator.geolocation) {
        navigator.geolocation.clearWatch(gpsWatchId);
        gpsWatchId = null;
    }
    if (gpsFlushTimer) {
        clearInterval(gpsFlushTimer);
        gpsFlushTimer = null;
    }
    if (gpsRetryTimer) {
        clearTimeout(gpsRetryTimer);
        gpsRetryTimer = null;
    }
    window.removeEventListener('online', tentarEsvaziarFilaPor);
    window.removeEventListener('pagehide', flushAntesDeSair);
}

function iniciarPollingStatusServidor() {
    if (pollingStatusServidor) clearInterval(pollingStatusServidor);
    pollingStatusServidor = setInterval(verificarStatusServidor, 45000);
    if (window.SessionManager) window.SessionManager.registerPolling(pollingStatusServidor);
}

function iniciarPollingChat() {
    if (pollingChat) clearInterval(pollingChat);
    pollingChat = setInterval(carregarMensagens, 30000);
    if (window.SessionManager) window.SessionManager.registerPolling(pollingChat);
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

function formatMoney(value) {
    return 'R$ ' + Number(value || 0).toFixed(2).replace('.', ',');
}

function mostrarModalCancelamento(data) {
    if (modalCancelamentoMostrado) return;
    modalCancelamentoMostrado = true;

    const resumo = document.getElementById('cancelamentoResumoGuincho');
    if (resumo) {
        const quem = data.cancelado_por === 'cliente' ? 'pelo cliente'
            : data.cancelado_por === 'admin' ? 'pela administração'
            : 'pelo fluxo operacional';
        let html = `<p class="mb-2">O atendimento foi cancelado ${quem}.</p>`;
        if (Number(data.taxa_cancelamento || 0) > 0) {
            html += `<p class="mb-2">Foi aplicada ao cliente uma retenção parcial de <strong>${formatMoney(data.taxa_cancelamento)}</strong>.</p>`;
            html += '<p class="mb-0">O eventual repasse parcial ao guincho segue validação financeira/operacional.</p>';
        } else {
            html += '<p class="mb-0">Não houve taxa de cancelamento aplicada ao cliente.</p>';
        }
        if (data.motivo_cancelamento) {
            html += `<p class="mt-2 mb-0"><strong>Motivo registrado:</strong> ${escapeHtml(data.motivo_cancelamento)}</p>`;
        }
        resumo.innerHTML = html;
    }

    const modalEl = document.getElementById('modalPedidoCanceladoGuincho');
    if (modalEl && window.bootstrap) {
        new bootstrap.Modal(modalEl).show();
    }
}

function appendMensagemCliente(msg) {
    if (!msg || !msg.id || msg.id <= ultimoMsgId) return;
    const box = document.getElementById('chatBox');
    if (!box) return;

    ultimoMsgId = Math.max(ultimoMsgId, Number(msg.id) || 0);
    const div = document.createElement('div');
    div.className = `chat-msg ${Number(msg.usuario_id || 0) === Number(<?php echo (int)($_SESSION['user']['id'] ?? 0); ?>) ? 'mine' : 'other'} mb-1`;
    div.innerHTML = Number(msg.usuario_id || 0) === Number(<?php echo (int)($_SESSION['user']['id'] ?? 0); ?>)
        ? `<div class="bubble">${escapeHtml(msg.mensagem)}</div>`
        : `<div class="sender small text-muted">${escapeHtml(msg.usuario_nome || 'Cliente')}</div><div class="bubble">${escapeHtml(msg.mensagem)}</div>`;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
}

function iniciarSsePedido() {
    if (!window.EventSource || ['concluido', 'cancelado'].includes(STATUS)) {
        iniciarPollingChat();
        return;
    }

    encerrarSsePedido();
    if (pollingChat) {
        clearInterval(pollingChat);
        pollingChat = null;
    }
    ssePedido = new EventSource(`${BP}/sse/pedido/${PEDIDO_ID}?desde_id=${encodeURIComponent(ultimoMsgId)}`);

    ssePedido.addEventListener('status_update', async () => {
        await verificarStatusServidor();
    });

    ssePedido.addEventListener('nova_mensagem', event => {
        try {
            appendMensagemCliente(JSON.parse(event.data || '{}'));
        } catch (e) { console.warn('[guincho/atendimento] falha:', e); }
    });

    ssePedido.addEventListener('session_expired', () => {
        encerrarSsePedido();
        if (window.SessionManager) window.SessionManager.handleUnauthorized();
    });

    ssePedido.addEventListener('stream_close', () => {
        encerrarSsePedido();
        verificarStatusServidor();
    });

    ssePedido.onerror = () => {
        encerrarSsePedido();
        iniciarPollingChat();
        reagendarSsePedido();
    };
}

// ── Polling de status: detecta cancelamento pelo cliente/admin ───
async function verificarStatusServidor() {
    try {
        const data = await requestJson(`${BP}/guincho/pedido/status-json/${PEDIDO_ID}`, { headers: { 'Accept': 'application/json' } });
        if (!data.ok) return;

        if (data.status === 'cancelado') {
            pararGps();
            encerrarSsePedido();
            const aviso = document.getElementById('avisoCanceladoGuincho');
            const quem  = data.cancelado_por === 'cliente' ? 'pelo cliente'
                        : data.cancelado_por === 'admin' ? 'pela administração' : '';
            if (aviso) {
                aviso.textContent = `Este atendimento foi cancelado ${quem}. Você já está disponível para novos pedidos.`;
                aviso.classList.remove('d-none');
            }
            mostrarModalCancelamento(data);
            const form = document.getElementById('statusForm');
            if (form) form.closest('.card').classList.add('d-none');
            const btnC = document.getElementById('btnCancelarAtendimento');
            if (btnC) btnC.remove();
            setTimeout(() => window.location.href = BP + '/guincho/dashboard', 4000);
            return;
        }

        // Sincroniza se o status mudou por fora (ex.: outro dispositivo)
        if (data.status !== STATUS) window.location.reload();
    } catch (e) { console.warn('[guincho/atendimento] falha ao sincronizar status:', e); }
}

// ── Botão de atualizar status (guard para status concluído) ──────
const statusForm = document.getElementById('statusForm');
if (statusForm) statusForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('btnAtualizarStatus');
    const msg = document.getElementById('statusMsg');

    btn.disabled = true;
    msg.textContent = 'Processando...';

    // Bug real: o evidence_token embutido no HTML é emitido uma única vez, no
    // carregamento da página (GuinchoController::atendimento). Se o
    // guincheiro dirigir da origem até o destino sem recarregar a tela — o
    // fluxo normal —, esse nonce ainda aponta pro ponto GPS de quando a
    // página abriu (perto da origem), e o backend rejeita a foto de entrega
    // por geofence, quebrando a conclusão do atendimento. Por isso, sempre
    // que o formulário exige uma foto de evidência, buscamos um nonce novo
    // (vinculado ao ponto GPS mais recente) imediatamente antes do envio.
    const evidenceInput = this.querySelector('input[name="evidence_token"]');
    if (evidenceInput) {
        try {
            const nonceData = await requestJson(`${BP}/guincho/evidencia-nonce/${PEDIDO_ID}`);
            if (nonceData.ok && nonceData.evidence_token) {
                evidenceInput.value = nonceData.evidence_token;
            }
        } catch (nonceErr) {
            console.warn('[guincho/atendimento] falha ao renovar nonce de evidência:', nonceErr);
        }
    }

    const formData = new FormData(this);

    try {
        const data = await requestJson(`${BP}/guincho/pedido/status-atualizar/${PEDIDO_ID}`, {
            method: 'POST',
            body: formData
        });

        if (data.ok) {
            window.location.reload();
        } else {
            // §LOG-FE-01 (29/07/2026): esse erro (ex.: bloqueio de geofence
            // ao tentar avançar pra "No Local") só aparecia como texto na
            // tela por alguns segundos e sumia no próximo reload/poll — sem
            // nada no console pra quem abrisse o F12 depois do fato, nem
            // qualquer jeito de correlacionar com o log do servidor
            // (Logger::log já registra o mesmo erro em app-*.jsonl/app_logs
            // desde a correção de GuinchoController::atualizarStatus).
            console.error('[guincho/atendimento] status-atualizar recusado:', {
                pedido_id: PEDIDO_ID,
                erro: data.erro || 'Erro ao atualizar status.'
            });
            msg.textContent = data.erro || 'Erro ao atualizar status.';
            btn.disabled = false;
        }
    } catch (err) {
        console.error('[guincho/atendimento] falha de conexão ao atualizar status:', err);
        msg.textContent = 'Erro de conexão.';
        btn.disabled = false;
    }
});

// ── Cancelamento pelo guincheiro ─────────────────────────────────
const btnCancelar = document.getElementById('btnCancelarAtendimento');
if (btnCancelar) {
    btnCancelar.addEventListener('click', () => {
        document.getElementById('cancelErroGuincho').textContent = '';
        new bootstrap.Modal(document.getElementById('modalCancelarGuincho')).show();
    });

    document.getElementById('btnConfirmarCancelamentoGuincho').addEventListener('click', async function () {
        const motivo = document.getElementById('cancelMotivoGuincho').value.trim();
        const erroEl = document.getElementById('cancelErroGuincho');
        if (!motivo) { erroEl.textContent = 'Informe o motivo do cancelamento.'; return; }

        this.disabled = true;
        try {
            const body = new URLSearchParams({ csrf_token: CSRF, motivo });
            const data = await requestJson(`${BP}/guincho/cancelar/${PEDIDO_ID}`, { method: 'POST', body });
            if (data.ok) {
                pararGps();
                window.location.href = BP + '/guincho/dashboard?cancelado=1';
            } else {
                erroEl.textContent = data.erro || 'Não foi possível cancelar.';
                this.disabled = false;
            }
        } catch (e) {
            erroEl.textContent = 'Erro de conexão. Tente novamente.';
            this.disabled = false;
        }
    });
}

// ── Chat ─────────────────────────────────────────────────────────
let ultimoMsgId = <?php echo !empty($mensagens) ? (int)end($mensagens)['id'] : 0; ?>;

async function carregarMensagens() {
    try {
        const data = await requestJson(`${BP}/guincho/chat/${PEDIDO_ID}?desde_id=${ultimoMsgId}`);
        if (data.ok && data.mensagens.length > 0) {
            const box = document.getElementById('chatBox');
            data.mensagens.forEach(msg => {
                ultimoMsgId = Math.max(ultimoMsgId, msg.id);
                const div = document.createElement('div');
                div.className = 'chat-msg other mb-1';
                div.innerHTML = `<div class="sender small text-muted">Cliente</div><div class="bubble">${escapeHtml(msg.mensagem)}</div>`;
                box.appendChild(div);
            });
            box.scrollTop = box.scrollHeight;
        }
    } catch(e) { console.warn('[guincho/atendimento] falha no chat:', e); }
}
// Pacote L1.9 — mesma lógica de idempotency key do cliente (pedidostatus.php):
// chatSending evita duplo-clique/duplo-Enter, e a key é mantida entre
// tentativas do MESMO texto até confirmar sucesso ou o texto mudar.
let chatSending = false;
let chatIdempotencyKey = null;
let chatIdempotencyTexto = null;

function gerarIdempotencyKey() {
    if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
    return 'ck-' + Date.now() + '-' + Math.random().toString(36).slice(2);
}

document.getElementById('btnEnviarMsg')?.addEventListener('click', async () => {
    if (chatSending) return;
    const input = document.getElementById('msgInput');
    const msg = input.value.trim();
    const btn = document.getElementById('btnEnviarMsg');
    if (!msg) return;

    if (chatIdempotencyKey === null || chatIdempotencyTexto !== msg) {
        chatIdempotencyKey = gerarIdempotencyKey();
        chatIdempotencyTexto = msg;
    }

    chatSending = true;
    input.disabled = true; btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('mensagem', msg);
        fd.append('csrf_token', btn.dataset.csrf);
        fd.append('idempotency_key', chatIdempotencyKey);
        const data = await requestJson(`${BP}/guincho/chat/${PEDIDO_ID}`, { method: 'POST', body: fd });
        if (data.ok) {
            if (data.id) ultimoMsgId = Math.max(ultimoMsgId, Number(data.id) || 0);
            const box = document.getElementById('chatBox');
            const div = document.createElement('div');
            div.className = 'chat-msg mine mb-1';
            div.innerHTML = `<div class="bubble">${escapeHtml(msg)}</div>`;
            box.appendChild(div);
            box.scrollTop = box.scrollHeight;
            input.value = '';
            chatIdempotencyKey = null;
            chatIdempotencyTexto = null;
        } else {
            console.warn('[guincho/atendimento] envio de mensagem recusado:', data.erro);
        }
    } catch(e) { console.warn('[guincho/atendimento] falha ao enviar mensagem:', e); }
    chatSending = false;
    input.disabled = false; btn.disabled = false;
    input.focus();
});

// Scroll inicial do chat
document.getElementById('chatBox').scrollTop = 99999;
document.addEventListener('guincha:session-expired', encerrarSsePedido);
window.addEventListener('beforeunload', encerrarSsePedido);
})();
</script>
