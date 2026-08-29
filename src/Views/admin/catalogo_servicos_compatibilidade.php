<?php
// header.php sobrescreve $tipo com o perfil do usuário; capturamos o tipo de
// serviço (passado pelo controller) antes do include para não perdê-lo.
$servicoTipo = $tipo ?? null;
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$stId = isset($servicoTipo['id']) ? (int)$servicoTipo['id'] : 0;
$reqGeral = $requisitosPorCategoria['_geral'] ?? [];
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Catálogo estruturado</span>
            <h1>Compatibilidade prestador × veículo</h1>
            <p>Defina quais categorias de veículo cada prestador atende por serviço e os requisitos do serviço. Enquanto um serviço não tiver capacidades configuradas, ele fica aberto a todos os prestadores aprovados (comportamento atual do reboque).</p>
        </div>
        <a href="<?php echo $bp; ?>/admin/catalogo-servicos/tipos" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Tipos de serviço
        </a>
    </header>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="<?php echo $bp; ?>/admin/catalogo-servicos/compatibilidade" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label mb-1">Serviço</label>
                    <select name="service_type_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($tipos as $t): ?>
                        <option value="<?php echo (int)$t['id']; ?>" <?php echo (int)$t['id'] === $stId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['code']); ?> · <?php echo htmlspecialchars($t['attendance_mode']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <noscript><button class="btn btn-primary w-100">Selecionar</button></noscript>
                </div>
            </form>
        </div>
    </div>

    <?php if ($stId > 0): ?>

    <!-- Peças/produtos pré-selecionados (orçamento) -->
    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="fas fa-box-open me-2"></i>Peças sugeridas no orçamento (pré-seleção)</div>
        <div class="card-body p-0">
            <?php if (empty($produtosSugeridos)): ?>
            <div class="p-3 text-muted small">Nenhuma peça associada a este serviço. (Reboque e diagnósticos normalmente não consomem peça.)</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Peça</th><th>Categoria</th><th>Especificação</th><th style="width:150px">Preço médio (R$)</th></tr></thead>
                    <tbody>
                    <?php foreach ($produtosSugeridos as $ps): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($ps['nome']); ?></strong> <span class="text-muted small"><?php echo htmlspecialchars($ps['sku']); ?></span></td>
                            <td><span class="badge text-bg-info"><?php echo htmlspecialchars($ps['categoria']); ?></span></td>
                            <td class="small"><?php echo htmlspecialchars((string)($ps['especificacao'] ?? '')); ?></td>
                            <td><?php echo $ps['preco_referencia'] !== null ? number_format((float)$ps['preco_referencia'], 2, ',', '.') . ' / ' . htmlspecialchars($ps['unidade']) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-2 text-muted small">Preço médio de referência — o prestador pode ajustar no estoque dele. O cliente aprova o orçamento no app antes do serviço.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Requisitos do serviço (regra geral) -->
    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="fas fa-clipboard-check me-2"></i>Requisitos do serviço (regra geral)</div>
        <div class="card-body">
            <form method="POST" action="<?php echo $bp; ?>/admin/catalogo-servicos/requisito/salvar" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="service_type_id" value="<?php echo $stId; ?>">
                <input type="hidden" name="vehicle_category" value="">
                <?php
                $reqFlags = [
                    'requires_platform' => 'Exige plataforma',
                    'requires_winch' => 'Exige guincho/cabo',
                    'requires_dolly' => 'Exige dolly/patins',
                    'requires_battery_tester' => 'Exige testador de bateria',
                    'requires_jump_starter' => 'Exige partida auxiliar',
                    'requires_hydraulic_jack' => 'Exige macaco hidráulico',
                ];
                foreach ($reqFlags as $campo => $label): ?>
                <div class="col-md-4 form-check ms-2">
                    <input type="checkbox" class="form-check-input" id="rg_<?php echo $campo; ?>" name="<?php echo $campo; ?>" value="1"
                           <?php echo !empty($reqGeral[$campo]) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="rg_<?php echo $campo; ?>"><?php echo $label; ?></label>
                </div>
                <?php endforeach; ?>
                <div class="col-md-4">
                    <label class="form-label mb-1">Capacidade mínima da unidade (kg)</label>
                    <input type="number" step="1" min="0" class="form-control" name="minimum_unit_capacity_kg"
                           value="<?php echo htmlspecialchars((string)($reqGeral['minimum_unit_capacity_kg'] ?? '')); ?>">
                </div>
                <div class="col-md-4 form-check ms-2 align-self-end">
                    <input type="checkbox" class="form-check-input" id="rg_elec" name="electric_certification_required" value="1"
                           <?php echo !empty($reqGeral['electric_certification_required']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="rg_elec">Exige certificação p/ elétrico</label>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Salvar requisitos</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Capacidades veiculares por prestador -->
    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="fas fa-truck me-2"></i>Capacidades já configuradas</div>
        <div class="card-body p-0">
            <?php if (empty($capacidades)): ?>
            <div class="p-3 text-muted small">Nenhuma capacidade veicular configurada — este serviço está aberto a todos os prestadores aprovados.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr>
                        <th>Prestador</th><th>Categoria</th><th>Status</th>
                        <th>Elétrico</th><th>Híbrido</th><th>Rodas travadas</th><th>Batido</th><th>Subsolo</th><th>Confirmar</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($capacidades as $c):
                        $chk = fn($k) => !empty($c[$k]) ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-minus text-muted"></i>'; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['prestador_nome']); ?></td>
                            <td><?php echo htmlspecialchars($categorias[$c['vehicle_category']] ?? $c['vehicle_category']); ?></td>
                            <td><span class="badge bg-<?php echo $c['approval_status'] === 'APPROVED' ? 'success' : ($c['approval_status'] === 'SUSPENDED' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars($c['approval_status']); ?></span><?php echo (int)$c['enabled'] === 1 ? '' : ' <span class="badge bg-dark">off</span>'; ?></td>
                            <td><?php echo $chk('supports_electric'); ?></td>
                            <td><?php echo $chk('supports_hybrid'); ?></td>
                            <td><?php echo $chk('supports_locked_wheels'); ?></td>
                            <td><?php echo $chk('supports_damaged_vehicle'); ?></td>
                            <td><?php echo $chk('supports_subsoil_access'); ?></td>
                            <td><?php echo $chk('requires_manual_confirmation'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Adicionar/editar capacidade -->
    <div class="card">
        <div class="card-header fw-bold"><i class="fas fa-plus me-2"></i>Adicionar / atualizar capacidade</div>
        <div class="card-body">
            <form method="POST" action="<?php echo $bp; ?>/admin/catalogo-servicos/capacidade-veicular/salvar" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="service_type_id" value="<?php echo $stId; ?>">
                <div class="col-md-5">
                    <label class="form-label mb-1">Prestador</label>
                    <select name="provider_id" class="form-select" required>
                        <option value="">Selecione…</option>
                        <?php foreach ($prestadores as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Categoria de veículo</label>
                    <select name="vehicle_category" class="form-select" required>
                        <?php foreach ($categorias as $val => $lbl): ?>
                        <option value="<?php echo $val; ?>"><?php echo htmlspecialchars($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select name="approval_status" class="form-select">
                        <option value="APPROVED">Aprovada</option>
                        <option value="PENDING">Pendente</option>
                        <option value="SUSPENDED">Suspensa</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Peso máx. do veículo (kg)</label>
                    <input type="number" step="1" min="0" class="form-control" name="max_vehicle_weight_kg" placeholder="opcional">
                </div>
                <?php
                $capFlags = [
                    'enabled' => 'Habilitada',
                    'supports_electric' => 'Atende elétrico',
                    'supports_hybrid' => 'Atende híbrido',
                    'supports_locked_wheels' => 'Atende rodas travadas',
                    'supports_damaged_vehicle' => 'Atende veículo batido',
                    'supports_subsoil_access' => 'Atende garagem/subsolo',
                    'requires_manual_confirmation' => 'Exige confirmação manual',
                ];
                foreach ($capFlags as $campo => $label): ?>
                <div class="col-md-4 form-check ms-2">
                    <input type="checkbox" class="form-check-input" id="cap_<?php echo $campo; ?>" name="<?php echo $campo; ?>" value="1"
                           <?php echo in_array($campo, ['enabled','supports_electric','supports_hybrid'], true) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="cap_<?php echo $campo; ?>"><?php echo $label; ?></label>
                </div>
                <?php endforeach; ?>
                <div class="col-12">
                    <button class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Salvar capacidade</button>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
