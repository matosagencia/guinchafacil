<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$csrfToken = $csrfToken ?? AuthService::gerarCsrfToken();
$u = $dadosUsuario ?? [];
$e = $especialista ?? [];
$endereco = $enderecoBase ?? [];
$pixPendente = !empty($e['chave_pix_pendente']);
?>
<link rel="stylesheet" href="<?= htmlspecialchars($bp) ?>/public/assets/css/themes/especialista.css">
<div class="shell especialista-shell">
    <?php include __DIR__ . '/../layouts/sidebar_especialista.php'; ?>
    <main class="main-content especialista-dashboard-main">
        <header class="page-head mb-4"><div><span class="eyebrow">Conta</span><h1><i class="fas fa-user-pen me-2"></i>Meu perfil</h1><p>Atualize seus dados operacionais com a mesma experiencia do painel do guincho.</p></div></header>
        <?php foreach ($flashMsg ?? [] as $flash): ?><div class="alert alert-<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($flash['message'] ?? '') ?></div><?php endforeach; ?>
        <form method="post" action="<?= htmlspecialchars($bp) ?>/especialista/perfil/salvar" class="row g-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="col-lg-8"><section class="card h-100"><div class="card-body p-4"><h2 class="h5 mb-4">Dados do especialista</h2>
                <div class="row g-3"><div class="col-md-6"><label class="form-label">Nome profissional</label><input class="form-control" name="nome_profissional" value="<?= htmlspecialchars($e['nome_profissional'] ?? $u['nome'] ?? '') ?>" required></div>
                <div class="col-md-6"><label class="form-label">Telefone</label><input class="form-control" name="telefone" value="<?= htmlspecialchars($u['telefone'] ?? '') ?>" required></div>
                <div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control" value="<?= htmlspecialchars($u['email'] ?? '') ?>" disabled></div>
                <div class="col-md-6"><label class="form-label">CPF/CNPJ</label><input class="form-control" value="<?= htmlspecialchars($e['cpf_cnpj'] ?? $u['cpf'] ?? '') ?>" disabled></div>
                <div class="col-12"><label class="form-label">Apresentacao profissional</label><textarea class="form-control" name="bio" rows="5" maxlength="2000"><?= htmlspecialchars($e['bio'] ?? '') ?></textarea></div></div>
            </div></section></div>
            <div class="col-lg-4">
                <section class="card mb-4"><div class="card-body p-4"><h2 class="h5 mb-3"><i class="fas fa-location-dot me-2"></i>Operacao</h2><p class="small text-muted mb-3">Esse endereco define o epicentro da busca e do raio de atendimento.</p><div class="row g-3">
                    <div class="col-md-5"><label class="form-label">CEP</label><input class="form-control" name="cep" value="<?= htmlspecialchars($endereco['cep'] ?? '') ?>" required></div>
                    <div class="col-md-7"><label class="form-label">Logradouro</label><input class="form-control" name="logradouro" value="<?= htmlspecialchars($endereco['logradouro'] ?? '') ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Numero</label><input class="form-control" name="numero" value="<?= htmlspecialchars($endereco['numero'] ?? '') ?>" required></div>
                    <div class="col-md-8"><label class="form-label">Complemento</label><input class="form-control" name="complemento" value="<?= htmlspecialchars($endereco['complemento'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Bairro</label><input class="form-control" name="bairro" value="<?= htmlspecialchars($endereco['bairro'] ?? '') ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Cidade</label><input class="form-control" name="cidade" value="<?= htmlspecialchars($endereco['cidade'] ?? '') ?>" required></div>
                    <div class="col-md-2"><label class="form-label">UF</label><input class="form-control" name="estado" maxlength="2" value="<?= htmlspecialchars($endereco['estado'] ?? '') ?>" required></div>
                    <div class="col-12"><label class="form-label">Raio de atendimento (km)</label><input class="form-control" type="number" min="1" max="200" step="0.5" name="raio_atendimento_km" value="<?= htmlspecialchars((string)($e['raio_atendimento_km'] ?? 10)) ?>"></div>
                </div></div></section>
                <section class="card"><div class="card-body p-4"><h2 class="h5 mb-2"><i class="fas fa-qrcode me-2"></i>Recebimento Pix</h2><?php if ($pixPendente): ?><div class="alert alert-warning small">Ha uma alteracao de Pix aguardando 24 horas de seguranca.</div><?php endif; ?><label class="form-label">Tipo da chave</label><select class="form-select mb-3" name="chave_pix_tipo" required><?php foreach(['cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'E-mail','telefone'=>'Telefone','aleatoria'=>'Aleatoria'] as $v=>$label): ?><option value="<?= $v ?>" <?= ($e['chave_pix_tipo_pendente'] ?? $e['chave_pix_tipo'] ?? '') === $v ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><label class="form-label">Chave Pix</label><input class="form-control" name="chave_pix" value="<?= htmlspecialchars((string)($e['chave_pix_pendente'] ?? $e['chave_pix'] ?? '')) ?>" required><div class="form-text">Uma nova chave so passa a receber repasses apos 24 horas.</div></div></section></div>
            <div class="col-12 d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="<?= htmlspecialchars($bp) ?>/especialista/dashboard">Cancelar</a><button class="btn btn-warning"><i class="fas fa-save me-1"></i>Salvar alteracoes</button></div>
        </form>
    </main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>