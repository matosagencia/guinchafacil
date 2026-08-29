<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-usuarioedit.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1><i class="fas fa-user-edit me-2 text-primary-custom"></i>Editar Usuário</h1>
            <p>
                <?php echo htmlspecialchars($usuario['nome'] ?? ''); ?> —
                <span class="badge-perfil <?php echo $usuario['tipo'] ?? ''; ?>"><?php echo ucfirst($usuario['tipo'] ?? ''); ?></span>
            </p>
        </div>
        <a href="<?php echo $bp; ?>/admin/usuario/<?php echo (int)$usuario['id']; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar ao Perfil
        </a>
    </header>

    <?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3">
        <?php
        $erros = [
            '1'               => 'Dados inválidos. Verifique os campos obrigatórios.',
            'email_duplicado' => 'E-mail ou CPF já está em uso por outro usuário.',
            'sem_permissao'   => 'Sem permissão para executar esta ação.',
        ];
        echo htmlspecialchars($erros[$_GET['erro']] ?? 'Erro desconhecido.');
        ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['salvo'])): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Dados salvos com sucesso!</div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <!-- Dados básicos -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-id-card me-2"></i>Dados Pessoais</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/usuario/atualizar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$usuario['id']; ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome"
                                       value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail *</label>
                                <input type="email" class="form-control" name="email"
                                       value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" name="telefone" id="telefoneInput"
                                       value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>"
                                       placeholder="(11) 99999-9999">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" class="form-control" name="cpf" id="cpfInput"
                                       value="<?php echo htmlspecialchars($usuario['cpf'] ?? ''); ?>"
                                       placeholder="000.000.000-00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status da Conta</label>
                            <select class="form-select" name="ativo">
                                <option value="1" <?php echo ($usuario['ativo'] ?? 1) ? 'selected' : ''; ?>>✅ Ativo</option>
                                <option value="0" <?php echo !($usuario['ativo'] ?? 1) ? 'selected' : ''; ?>>⛔ Suspenso</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Salvar Dados Pessoais
                        </button>
                    </form>
                </div>
            </div>

            <!-- Alterar senha -->
            <div class="card">
                <div class="card-header"><i class="fas fa-lock me-2"></i>Alterar Senha</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/usuario/senha">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$usuario['id']; ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nova Senha *</label>
                                <input type="password" class="form-control" name="senha"
                                       id="novaSenha" minlength="8" placeholder="Mínimo 8 caracteres">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmar Senha *</label>
                                <input type="password" class="form-control" name="confirmar"
                                       id="confirmarSenha">
                                <div id="senhaFeedback" class="small mt-1 usuarioedit-senha-feedback"></div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 mt-3" id="btnSenha">
                            <i class="fas fa-key me-2"></i>Alterar Senha
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <!-- Info do usuário -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Informações do Sistema</div>
                <div class="card-body">
                    <table class="table table-sm mb-0 usuarioedit-info-table">
                        <tr><td class="usuarioedit-info-label">ID</td><td>#<?php echo (int)$usuario['id']; ?></td></tr>
                        <tr><td class="usuarioedit-info-label">Tipo de Perfil</td>
                            <td><span class="badge-perfil <?php echo $usuario['tipo'] ?? ''; ?>"><?php echo ucfirst($usuario['tipo'] ?? ''); ?></span></td></tr>
                        <tr><td class="usuarioedit-info-label">Cadastrado em</td>
                            <td><?php echo isset($usuario['criado_em']) ? date('d/m/Y H:i', strtotime($usuario['criado_em'])) : '—'; ?></td></tr>
                        <tr><td class="usuarioedit-info-label">Último Login</td>
                            <td><?php echo !empty($usuario['ultimo_login']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca'; ?></td></tr>
                    </table>
                </div>
            </div>

            <?php if (!empty($extra) && $usuario['tipo'] === 'guincho'): ?>
            <!-- Dados do guincho (se for guincho) -->
            <div class="card">
                <div class="card-header"><i class="fas fa-truck me-2"></i>Dados do Guincho</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/guincho/atualizar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$extra['id']; ?>">

                        <div class="mb-3">
                            <label class="form-label">Placa do Reboque</label>
                            <input type="text" class="form-control text-uppercase" name="placa_guincho"
                                   value="<?php echo htmlspecialchars($extra['placa_guincho'] ?? ''); ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-8">
                                <label class="form-label">Cidade do Emplacamento</label>
                                <input type="text" class="form-control" name="cidade_placa"
                                       value="<?php echo htmlspecialchars($extra['cidade_placa'] ?? ''); ?>"
                                       placeholder="Ex: Rio de Janeiro">
                            </div>
                            <div class="col-4">
                                <label class="form-label">UF</label>
                                <input type="text" class="form-control text-uppercase" name="uf_placa"
                                       value="<?php echo htmlspecialchars($extra['uf_placa'] ?? ''); ?>"
                                       maxlength="2" placeholder="RJ">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CNH Número</label>
                            <input type="text" class="form-control" name="cnh_numero"
                                   value="<?php echo htmlspecialchars($extra['cnh_numero'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Validade CNH</label>
                            <input type="date" class="form-control" name="cnh_validade"
                                   value="<?php echo htmlspecialchars($extra['cnh_validade'] ?? ''); ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label">Chave Pix</label>
                                <input type="text" class="form-control" name="chave_pix"
                                       value="<?php echo htmlspecialchars($extra['chave_pix'] ?? ''); ?>">
                            </div>
                            <div class="col-5">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="chave_pix_tipo">
                                    <?php foreach (['cpf','cnpj','email','telefone','aleatoria'] as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php echo ($extra['chave_pix_tipo'] ?? 'cpf') === $t ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($t); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacidade (ton)</label>
                            <input type="number" step="0.1" class="form-control" name="capacidade_ton"
                                   value="<?php echo htmlspecialchars($extra['capacidade_ton'] ?? ''); ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Latitude Operacional</label>
                                <input type="number" step="0.00000001" class="form-control" name="lat_operacao"
                                       value="<?php echo htmlspecialchars($extra['lat_operacao'] ?? ''); ?>"
                                       placeholder="-23.55050000">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Longitude Operacional</label>
                                <input type="number" step="0.00000001" class="form-control" name="lng_operacao"
                                       value="<?php echo htmlspecialchars($extra['lng_operacao'] ?? ''); ?>"
                                       placeholder="-46.63330000">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aprovado</label>
                            <select class="form-select" name="aprovado">
                                <option value="1" <?php echo ($extra['aprovado'] ?? 0) ? 'selected' : ''; ?>>✅ Aprovado</option>
                                <option value="0" <?php echo !($extra['aprovado'] ?? 0) ? 'selected' : ''; ?>>⏳ Pendente</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Salvar Dados do Guincho
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ficha 360: relacionamentos operacionais do usuario -->
    <?php
    $fmtData = static function ($value): string {
        return !empty($value) ? date('d/m/Y H:i', strtotime((string)$value)) : '—';
    };
    $fmtValor = static function ($value): string {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    };
    ?>
    <section class="usuarioedit-relacionamentos mt-4" aria-labelledby="relacionamentosTitulo">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <span class="eyebrow">Visao 360</span>
                <h2 id="relacionamentosTitulo" class="h4 mb-1"><i class="fas fa-project-diagram me-2 text-primary-custom"></i>Relacionamentos do cadastro</h2>
                <p class="text-muted mb-0">Veiculos, oficinas, pedidos, pagamentos e evidencias associadas a este usuario.</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between"><span><i class="fas fa-car me-2"></i>Veiculos cadastrados</span><span class="badge bg-secondary"><?php echo count($veiculosAssociados); ?></span></div>
                    <div class="card-body p-0">
                        <?php if (empty($veiculosAssociados)): ?><p class="text-muted p-3 mb-0">Nenhum veiculo associado.</p>
                        <?php else: ?><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Veiculo</th><th>Placa</th><th>Ano</th><th>Status</th></tr></thead><tbody>
                            <?php foreach ($veiculosAssociados as $v): ?><tr><td><?php echo htmlspecialchars(trim(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? '')) ?: '—'); ?></td><td><?php echo htmlspecialchars($v['placa'] ?? '—'); ?></td><td><?php echo htmlspecialchars($v['ano'] ?? '—'); ?></td><td><?php echo htmlspecialchars($v['verification_status'] ?? ($v['ativo'] ?? 1 ? 'Ativo' : 'Inativo')); ?></td></tr><?php endforeach; ?>
                        </tbody></table></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between"><span><i class="fas fa-warehouse me-2"></i>Oficinas cadastradas</span><span class="badge bg-secondary"><?php echo count($oficinasAssociadas); ?></span></div>
                    <div class="card-body p-0">
                        <?php if (empty($oficinasAssociadas)): ?><p class="text-muted p-3 mb-0">Nenhuma oficina associada.</p>
                        <?php else: ?><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Nome</th><th>Endereco</th><th>Localizacao</th></tr></thead><tbody>
                            <?php foreach ($oficinasAssociadas as $o): ?><tr><td><?php echo htmlspecialchars($o['nome'] ?? '—'); ?></td><td><?php echo htmlspecialchars($o['endereco'] ?? '—'); ?></td><td><?php echo is_numeric($o['latitude'] ?? null) && is_numeric($o['longitude'] ?? null) ? 'GPS salvo' : 'Sem GPS'; ?></td></tr><?php endforeach; ?>
                        </tbody></table></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($pedidosClienteAssociados)): ?><div class="col-12"><div class="card"><div class="card-header"><i class="fas fa-life-ring me-2"></i>Pedidos realizados como cliente (<?php echo count($pedidosClienteAssociados); ?>)</div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>ID</th><th>Problema</th><th>Veiculo</th><th>Status</th><th>Origem</th><th>Valor</th><th>Data</th></tr></thead><tbody>
                <?php foreach ($pedidosClienteAssociados as $p): ?><tr><td><a href="<?php echo $bp; ?>/admin/pedido/<?php echo (int)$p['id']; ?>">#<?php echo (int)$p['id']; ?></a></td><td><?php echo htmlspecialchars($p['tipo_problema'] ?? '—'); ?></td><td><?php echo htmlspecialchars(trim(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? '') . ' ' . ($p['placa'] ?? '')) ?: '—'); ?></td><td><?php echo htmlspecialchars($p['status'] ?? '—'); ?></td><td><?php echo htmlspecialchars($p['endereco_origem'] ?? '—'); ?></td><td><?php echo $fmtValor($p['custo_estimado'] ?? 0); ?></td><td><?php echo $fmtData($p['criado_em'] ?? null); ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div><?php endif; ?>

            <?php if (!empty($pedidosPrestadorAssociados)): ?><div class="col-12"><div class="card"><div class="card-header"><i class="fas fa-truck me-2"></i>Pedidos atendidos como prestador (<?php echo count($pedidosPrestadorAssociados); ?>)</div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>ID</th><th>Cliente</th><th>Problema</th><th>Veiculo</th><th>Status</th><th>Valor</th><th>Data</th></tr></thead><tbody>
                <?php foreach ($pedidosPrestadorAssociados as $p): ?><tr><td><a href="<?php echo $bp; ?>/admin/pedido/<?php echo (int)$p['id']; ?>">#<?php echo (int)$p['id']; ?></a></td><td><?php echo htmlspecialchars($p['cliente_nome'] ?? '—'); ?></td><td><?php echo htmlspecialchars($p['tipo_problema'] ?? '—'); ?></td><td><?php echo htmlspecialchars(trim(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? '') . ' ' . ($p['placa'] ?? '')) ?: '—'); ?></td><td><?php echo htmlspecialchars($p['status'] ?? '—'); ?></td><td><?php echo $fmtValor($p['custo_estimado'] ?? 0); ?></td><td><?php echo $fmtData($p['criado_em'] ?? null); ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div></div><?php endif; ?>

            <div class="col-xl-6"><div class="card h-100"><div class="card-header"><i class="fas fa-credit-card me-2"></i>Pagamentos associados</div><div class="card-body p-0">
                <?php if (empty($pagamentosAssociados)): ?><p class="text-muted p-3 mb-0">Nenhum pagamento associado.</p><?php else: ?><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Pedido</th><th>Status</th><th>Metodo</th><th>Valor</th><th>Data</th></tr></thead><tbody><?php foreach ($pagamentosAssociados as $pg): ?><tr><td>#<?php echo (int)($pg['pedido_id'] ?? 0); ?></td><td><?php echo htmlspecialchars($pg['status'] ?? '—'); ?></td><td><?php echo htmlspecialchars($pg['metodo'] ?? '—'); ?></td><td><?php echo $fmtValor($pg['valor_total'] ?? 0); ?></td><td><?php echo $fmtData($pg['criado_em'] ?? null); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </div></div></div>

            <?php if ($usuario['tipo'] === 'guincho'): ?><div class="col-xl-6"><div class="card h-100"><div class="card-header"><i class="fas fa-tools me-2"></i>Especialidades e avaliações</div><div class="card-body"><div class="small text-muted mb-2">Capacidades declaradas</div><?php if (empty($capacidadesAssociadas)): ?><p class="text-muted">Nenhuma capacidade cadastrada.</p><?php else: ?><div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($capacidadesAssociadas as $cap): ?><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($cap['service_name'] ?? $cap['service_code'] ?? 'Servico'); ?> · <?php echo htmlspecialchars($cap['approval_status'] ?? 'PENDING'); ?></span><?php endforeach; ?></div><?php endif; ?><div class="small text-muted mb-2">Avaliações recebidas</div><?php if (empty($avaliacoesAssociadas)): ?><p class="text-muted mb-0">Nenhuma avaliação recebida.</p><?php else: ?><p class="mb-0"><strong><?php echo count($avaliacoesAssociadas); ?></strong> avaliação(ões) registrada(s).</p><?php endif; ?></div></div></div><?php endif; ?>

            <div class="col-12"><div class="card"><div class="card-header"><i class="fas fa-shield-alt me-2"></i>Auditoria e documentos</div><div class="card-body"><div class="row g-3"><div class="col-md-4"><strong>Eventos de auditoria</strong><br><span class="text-muted"><?php echo count($logsAssociados); ?> evento(s) recente(s)</span></div><?php if ($usuario['tipo'] === 'guincho' && !empty($extra)): ?><div class="col-md-4"><strong>Cadastro operacional</strong><br><span class="text-muted"><?php echo !empty($extra['placa_guincho']) ? 'Placa informada' : 'Placa pendente'; ?> · <?php echo !empty($extra['foto_veiculo']) ? 'Foto informada' : 'Foto pendente'; ?></span></div><div class="col-md-4"><strong>Documentos</strong><br><span class="text-muted"><?php echo !empty($extra['doc_cnh_frente']) ? 'CNH recebida' : 'CNH pendente'; ?> · <?php echo !empty($extra['aprovado']) ? 'Cadastro aprovado' : 'Em análise'; ?></span></div><?php else: ?><div class="col-md-8"><span class="text-muted">Documentos sensíveis não são exibidos nesta visão; consulte as telas específicas de validação quando necessário.</span></div><?php endif; ?></div></div></div></div>
        </div>
    </section>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<script<?php echo csp_script_nonce_attr(); ?>>
// Validação senha
document.getElementById('confirmarSenha').addEventListener('input', function() {
    const s = document.getElementById('novaSenha').value;
    const c = this.value;
    const fb = document.getElementById('senhaFeedback');
    const btn = document.getElementById('btnSenha');
    if (!c) { fb.textContent = ''; return; }
    if (s === c) {
        fb.style.color = 'var(--primary)';
        fb.textContent = '✓ Senhas conferem';
        btn.disabled = false;
    } else {
        fb.style.color = '#f87171';
        fb.textContent = '✗ Senhas não conferem';
        btn.disabled = true;
    }
});

// Máscara CPF
document.getElementById('cpfInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').slice(0,11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{0,3})/, '$1.$2');
    this.value = v;
});

// Máscara telefone
document.getElementById('telefoneInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').slice(0,11);
    if (v.length > 6) v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    this.value = v;
});
</script>
