<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$csrf = AuthService::gerarCsrfToken();
$nome = htmlspecialchars((string)($_SESSION['user']['nome'] ?? 'Especialista'));
$aprovado = $especialista && (int)$especialista['aprovado'] === 1;
$online = $especialista && (int)$especialista['disponivel'] === 1;
$reputacao = (float)($especialista['reputacao'] ?? 0);
$avaliacoes = (int)($especialista['total_avaliacoes'] ?? 0);
$atendimentos = is_array($atendimentos ?? null) ? $atendimentos : [];
?>
<link rel="stylesheet" href="<?= htmlspecialchars($bp) ?>/public/assets/css/themes/especialista.css">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= htmlspecialchars($bp) ?>/public/assets/js/especialista-localizacao.js" defer></script>
<div class="shell especialista-shell">
    <?php include __DIR__ . '/../layouts/sidebar_especialista.php'; ?>
    <main class="main-content especialista-dashboard-main">
        <header class="page-head mb-4">
            <div><span class="eyebrow">Painel operacional</span><h1>Olá, <?= $nome ?></h1><p>Chamados, localização, evidências e repasses em um só lugar.</p></div>
            <div class="d-flex align-items-center gap-2"><span class="badge <?= $online ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $online ? 'Online' : 'Offline' ?></span><form method="post" action="<?= htmlspecialchars($bp) ?>/especialista/disponibilidade"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="disponivel" value="<?= $online ? '0' : '1' ?>"><button class="btn btn-warning"><i class="fas fa-power-off me-1"></i><?= $online ? 'Ficar offline' : 'Ficar online' ?></button></form></div>
        </header>
        <?php foreach ($_SESSION['_flash'] ?? [] as $flash): unset($_SESSION['_flash']); ?><div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endforeach; ?>
        <?php if (!$especialista): ?><div class="alert alert-warning">Perfil operacional não encontrado. Contate o administrador.</div>
        <?php elseif (!$aprovado): ?><div class="alert alert-info">Seu cadastro está em análise. As ferramentas serão liberadas após a homologação.</div><?php endif; ?>
        <section class="especialista-hero mb-4" id="perfil"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><span class="text-uppercase small">Status do perfil</span><h2 class="h4 mb-1"><?= $aprovado ? 'Pronto para atender' : 'Cadastro em análise' ?></h2><span class="text-muted">GPS e evidências são registrados durante cada atendimento.</span></div><div class="text-end"><div class="especialista-stars" aria-label="<?= number_format($reputacao, 1, ',', '.') ?> de 5 estrelas"><?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star<?= $i <= round($reputacao) ? '' : ' opacity-50' ?>"></i><?php endfor; ?></div><small><?= number_format($reputacao, 1, ',', '.') ?> · <?= $avaliacoes ?> avaliações</small></div></div></section>
        <section id="ferramentas" class="mb-4"><h2 class="h5 mb-3">Ferramentas rápidas</h2><div class="row g-3">
            <div class="col-sm-6 col-xl-3"><a class="especialista-tool" href="#chamados"><i class="fas fa-clipboard-list"></i><div><strong>Chamados</strong><span><?= count($atendimentos) ?> em sua fila</span></div></a></div>
            <div class="col-sm-6 col-xl-3"><a class="especialista-tool" href="#localizacao"><i class="fas fa-location-dot"></i><div><strong>Localização</strong><span>GPS contínuo e geofence</span></div></a></div>
            <div class="col-sm-6 col-xl-3"><a class="especialista-tool" href="#evidencias"><i class="fas fa-camera"></i><div><strong>Evidências</strong><span>Chegada e diagnóstico</span></div></a></div>
            <div class="col-sm-6 col-xl-3"><a class="especialista-tool" href="#ganhos"><i class="fas fa-wallet"></i><div><strong>Ganhos e repasses</strong><span>Resumo financeiro</span></div></a></div>
        </div></section>
        <section id="chamados"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Chamados recentes</h2><span class="text-muted small">Atualização automática</span></div>
        <?php if (empty($atendimentos)): ?><div class="card"><div class="card-body text-center py-5"><i class="fas fa-inbox fa-2x mb-3" style="color:var(--theme-accent)"></i><h3 class="h6">Nenhum chamado no momento</h3><p class="text-muted mb-0">Fique online para receber novas ofertas de atendimento.</p></div></div><?php endif; ?>
        <div class="row g-3">
        <?php foreach ($atendimentos as $a): ?><div class="col-12 col-xl-6"><article class="card h-100"><div class="card-body"><div class="d-flex justify-content-between gap-2"><strong><?= htmlspecialchars((string)$a['servico_nome']) ?></strong><span class="badge text-bg-warning"><?= htmlspecialchars((string)$a['status']) ?></span></div><p class="mt-2 mb-3"><?= htmlspecialchars((string)$a['tipo_problema']) ?> — <?= htmlspecialchars((string)$a['endereco_origem']) ?></p>
        <?php if ($a['status'] === 'ofertado'): ?><form method="post" action="<?= $bp ?>/especialista/atendimento/aceitar/<?= (int)$a['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><button class="btn btn-warning">Aceitar chamado</button></form>
        <?php elseif ($a['status'] === 'a_caminho'): ?><form id="localizacao" method="post" enctype="multipart/form-data" action="<?= $bp ?>/especialista/atendimento/chegada/<?= (int)$a['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="lat" value="0"><input type="hidden" name="lng" value="0"><input type="hidden" name="accuracy" value="0"><label class="form-label">Foto de chegada *</label><input class="form-control mb-2" type="file" name="foto_chegada" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-warning">Registrar chegada</button></form>
        <?php elseif ($a['status'] === 'no_local'): ?><form id="evidencias" method="post" enctype="multipart/form-data" action="<?= $bp ?>/especialista/atendimento/diagnostico/<?= (int)$a['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><label class="form-label">Diagnóstico *</label><textarea class="form-control mb-2" name="descricao" required></textarea><select class="form-select mb-2" name="resultado"><option value="resolvido">Resolvido</option><option value="orcamento">Orçamento</option><option value="reboque">Necessita reboque</option></select><label class="form-label">Foto do diagnóstico *</label><input class="form-control mb-2" type="file" name="foto_diagnostico" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-warning">Enviar diagnóstico</button></form>
        <?php elseif (in_array($a['status'], ['aceito','em_diagnostico','em_execucao'], true)): ?><form method="post" action="<?= $bp ?>/especialista/atendimento/status/<?= (int)$a['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><select name="status" class="form-select mb-2"><option value="a_caminho">A caminho</option><option value="resolvido">Resolvido</option><option value="necessita_reboque">Necessita reboque</option></select><button class="btn btn-warning">Atualizar status</button></form><?php endif; ?></div></article></div><?php endforeach; ?></div></section>
        <section id="ganhos" class="mt-4"><div class="card"><div class="card-body"><h2 class="h6">Resumo de ganhos e repasses</h2><p class="text-muted mb-0">Os valores ficam disponíveis após a conclusão e confirmação do pagamento do incidente.</p></div></div></section>
    </main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
