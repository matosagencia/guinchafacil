<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-guinhodetalhe.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>
                <i class="fas fa-truck me-2 text-primary-custom"></i>
                <?php echo htmlspecialchars($usuario['nome'] ?? 'Guincho'); ?>
                <span class="badge-perfil guincho ms-2">Guincho</span>
                <?php if (!$guincho['aprovado']): ?>
                <span class="badge badge-aguardando_guincho ms-2">Pendente Aprovação</span>
                <?php endif; ?>
            </h1>
            <p>Placa: <?php echo htmlspecialchars($guincho['placa_guincho'] ?? '—'); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo $bp; ?>/admin/guinchos" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Guinchos
            </a>
            <a href="<?php echo $bp; ?>/admin/usuario/editar/<?php echo $usuario['id'] ?? 0; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit me-1"></i>Editar
            </a>
            <a href="<?php echo $bp; ?>/admin/usuario/<?php echo $usuario['id'] ?? 0; ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-user me-1"></i>Ver Usuário
            </a>
            <a href="<?php echo $bp; ?>/admin/avaliacoes?guincho_id=<?php echo (int)($guincho['id'] ?? 0); ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-star me-1"></i>Ver avaliações
            </a>
        </div>
    </header>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-id-badge me-2"></i>Dados do Operador</div>
                <div class="card-body">
                    <table class="table table-sm mb-0 ghd-table">
                        <tr><td class="ghd-label ghd-label--35">Nome</td><td><?php echo htmlspecialchars($usuario['nome'] ?? '—'); ?></td></tr>
                        <tr><td class="ghd-label">E-mail</td><td><?php echo htmlspecialchars($usuario['email'] ?? '—'); ?></td></tr>
                        <tr><td class="ghd-label">Telefone</td><td><?php echo htmlspecialchars($usuario['telefone'] ?? '—'); ?></td></tr>
                        <tr><td class="ghd-label">CPF</td><td><?php echo htmlspecialchars($usuario['cpf'] ?? '—'); ?></td></tr>
                        <tr><td class="ghd-label">Cadastro</td><td><?php echo date('d/m/Y', strtotime($usuario['criado_em'] ?? 'now')); ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-truck me-2"></i>Dados do Veículo</div>
                <div class="card-body">
                    <table class="table table-sm mb-0 ghd-table">
                        <tr><td class="ghd-label ghd-label--40">Placa</td><td><?php echo htmlspecialchars($guincho['placa_guincho'] ?? '—'); ?></td></tr>
                        <tr><td class="ghd-label">Emplacamento</td><td><?php echo htmlspecialchars(trim(($guincho['cidade_placa'] ?? '') . (!empty($guincho['uf_placa']) ? ' / ' . $guincho['uf_placa'] : '')) ?: '—'); ?></td></tr>
                        <tr><td class="ghd-label">CNH Nº</td><td><?php echo htmlspecialchars($guincho['cnh_numero'] ?? '—'); ?></td></tr>
                        <tr><td class="ghd-label">Validade CNH</td><td><?php echo isset($guincho['cnh_validade']) ? date('d/m/Y', strtotime($guincho['cnh_validade'])) : '—'; ?></td></tr>
                        <tr><td class="ghd-label">Capacidade</td><td><?php echo htmlspecialchars($guincho['capacidade_ton'] ?? '—'); ?> ton</td></tr>
                        <tr><td class="ghd-label">Chave Pix</td><td><?php echo htmlspecialchars($guincho['chave_pix'] ?? '—'); ?></td></tr>
                        <tr><td class="ghd-label">Local Operacional</td><td><?php echo isset($guincho['lat_operacao'], $guincho['lng_operacao']) ? htmlspecialchars(number_format((float)$guincho['lat_operacao'], 6, ',', '.') . ' / ' . number_format((float)$guincho['lng_operacao'], 6, ',', '.')) : '—'; ?></td></tr>
                        <tr><td class="ghd-label">Reputação</td><td><?php echo number_format($guincho['reputacao'] ?? 0, 1); ?>/5 (<?php echo (int)($guincho['total_avaliacoes'] ?? 0); ?> avals)</td></tr>
                    </table>
                </div>
            </div>

            <?php if (!$guincho['aprovado']): ?>
            <div class="card">
                <div class="card-header ghd-pendente-header"><i class="fas fa-triangle-exclamation me-2"></i>Aprovação Pendente</div>
                <div class="card-body">
                    <p class="ghd-pendente-text">Revise os documentos antes de aprovar ou rejeitar este guincho.</p>
                    <div class="d-flex gap-2">
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/aprovar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $guincho['id']; ?>">
                            <button class="btn btn-primary w-100">
                                <i class="fas fa-check me-1"></i>Aprovar
                            </button>
                        </form>
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/rejeitar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $guincho['id']; ?>">
                            <button class="btn btn-danger w-100" data-confirm-message="Rejeitar e suspender este guincho?">
                                <i class="fas fa-times me-1"></i>Rejeitar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documentos -->
            <?php if (!empty($guincho['doc_cnh_frente']) || !empty($guincho['foto_veiculo'])): ?>
            <div class="card mt-4">
                <div class="card-header"><i class="fas fa-file-image me-2"></i>Documentos Enviados</div>
                <div class="card-body d-flex flex-column gap-2">
                    <?php // §SEC-UPL-02: link autenticado via ArquivoController::servir(), não mais caminho direto de /uploads (arquivo agora fica fora do webroot). ?>
                    <?php if (!empty($guincho['doc_cnh_frente'])): ?>
                    <a href="<?php echo $bp; ?>/arquivo/<?php echo (int)$guincho['id']; ?>?tipo=doc_cnh_frente" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-id-card me-1"></i>CNH Frente
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($guincho['doc_cnh_verso'])): ?>
                    <a href="<?php echo $bp; ?>/arquivo/<?php echo (int)$guincho['id']; ?>?tipo=doc_cnh_verso" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-id-card me-1"></i>CNH Verso
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($guincho['foto_veiculo'])): ?>
                    <a href="<?php echo $bp; ?>/arquivo/<?php echo (int)$guincho['id']; ?>?tipo=foto_veiculo" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-truck me-1"></i>Foto do Veículo
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Serviços prestados -->
        <div class="col-md-7">
            <?php
            $modoLbl = ['TOWING' => 'Reboque', 'ON_SITE' => 'No local', 'HYBRID' => 'Híbrido'];
            $statusBadge = function ($s) {
                $map = ['APPROVED' => ['success', 'Aprovada'], 'PENDING' => ['warning', 'Pendente'],
                        'SUSPENDED' => ['secondary', 'Suspensa'], 'REJECTED' => ['danger', 'Rejeitada']];
                [$cor, $txt] = $map[$s] ?? ['secondary', $s];
                return '<span class="badge text-bg-' . $cor . '">' . htmlspecialchars($txt) . '</span>';
            };
            $catLbl = ['automovel_passeio' => 'Automóvel', 'moto' => 'Moto', 'utilitario' => 'Utilitário', 'caminhao_leve' => 'Caminhão leve'];
            ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-toolbox me-2"></i>Serviços prestados</span>
                    <a href="<?php echo $bp; ?>/admin/catalogo-servicos/capacidades" class="btn btn-sm btn-primary">
                        <i class="fas fa-user-check me-1"></i>Aprovar serviços
                    </a>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        <i class="fas fa-circle-info me-1"></i>Serviços marcados como <span class="badge text-bg-warning">Pendente</span> são aprovados na
                        <a href="<?php echo $bp; ?>/admin/catalogo-servicos/capacidades">fila de capacidades</a>. O reboque é aprovado ao aprovar o prestador na fila de pendentes.
                    </p>
                    <!-- Reboque (status especial) -->
                    <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded" style="border:1px solid var(--theme-border,#333)">
                        <div>
                            <strong><i class="fas fa-truck-pickup me-1"></i>Reboque (guincho)</strong>
                            <?php if (!empty($guincho['placa_guincho'])): ?>
                            <span class="text-muted small ms-2">placa <?php echo htmlspecialchars($guincho['placa_guincho']); ?><?php echo !empty($guincho['capacidade_ton']) ? ' · ' . number_format((float)$guincho['capacidade_ton'], 1) . ' ton' : ''; ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ((int)($guincho['reboque_aprovado'] ?? 0) === 1): ?>
                                <span class="badge text-bg-success">Reboque aprovado</span>
                            <?php elseif ((int)($guincho['oferece_reboque'] ?? 0) === 1): ?>
                                <span class="badge text-bg-warning">Reboque em análise</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Não oferece reboque</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Demais serviços (capacidades declaradas) -->
                    <?php if (empty($capacidades)): ?>
                    <div class="text-muted small">Nenhum serviço no local declarado.</div>
                    <?php else: foreach ($capacidades as $c): $stId = (int)$c['service_type_id']; ?>
                    <div class="p-2 mb-2 rounded" style="border:1px solid var(--theme-border,#333)">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <strong><?php echo htmlspecialchars($c['service_name'] ?? $c['service_code'] ?? '—'); ?></strong>
                                <span class="text-muted small ms-1"><?php echo htmlspecialchars($c['service_code'] ?? ''); ?></span>
                                <span class="badge text-bg-info ms-1"><?php echo htmlspecialchars($modoLbl[$c['attendance_mode'] ?? ''] ?? ($c['attendance_mode'] ?? '')); ?></span>
                            </div>
                            <?php echo $statusBadge($c['approval_status'] ?? ''); ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <?php if (isset($c['coverage_radius_km']) && $c['coverage_radius_km'] !== null && $c['coverage_radius_km'] !== ''): ?>
                                <i class="fas fa-location-crosshairs me-1"></i>Raio de atendimento: <?php echo (int)$c['coverage_radius_km']; ?> km
                            <?php else: ?>
                                <i class="fas fa-location-crosshairs me-1"></i>Raio não informado
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($compatPorServico[$stId])): ?>
                        <div class="mt-2">
                            <div class="small fw-bold mb-1">Veículos que atende:</div>
                            <?php foreach ($compatPorServico[$stId] as $cv): ?>
                            <div class="small mb-1" style="padding-left:.5rem;border-left:2px solid var(--primary,#2fb34a)">
                                <?php echo htmlspecialchars($catLbl[$cv['vehicle_category']] ?? $cv['vehicle_category']); ?>
                                — <?php echo $statusBadge($cv['approval_status'] ?? ''); ?>
                                <?php
                                $flags = [];
                                if (!empty($cv['supports_electric'])) $flags[] = 'elétrico';
                                if (!empty($cv['supports_hybrid'])) $flags[] = 'híbrido';
                                if (!empty($cv['supports_locked_wheels'])) $flags[] = 'rodas travadas';
                                if (!empty($cv['supports_damaged_vehicle'])) $flags[] = 'batido';
                                if (!empty($cv['supports_subsoil_access'])) $flags[] = 'subsolo';
                                if (($cv['max_vehicle_weight_kg'] ?? null) !== null) $flags[] = 'até ' . (int)$cv['max_vehicle_weight_kg'] . ' kg';
                                echo $flags ? '<span class="text-muted"> · ' . htmlspecialchars(implode(', ', $flags)) . '</span>' : '';
                                ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-list-check me-2"></i>Atendimentos (últimos 15)</div>
                <div class="card-body p-0">
                    <?php if (empty($pedidos)): ?>
                    <div class="p-4 text-center ghd-empty">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Nenhum atendimento encontrado.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>#</th><th>Cliente</th><th>Problema</th><th>Status</th><th>Valor</th><th>Data</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($pedidos as $p): ?>
                                <tr>
                                    <td><?php echo $p['id']; ?></td>
                                    <td><?php echo htmlspecialchars($p['cliente_nome'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($p['tipo_problema'] ?? '—')); ?></td>
                                    <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo str_replace('_',' ',ucfirst($p['status'])); ?></span></td>
                                    <td>R$ <?php echo number_format($p['custo_estimado'] ?? 0, 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($p['criado_em'])); ?></td>
                                    <td><a href="<?php echo $bp; ?>/admin/pedido/<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
