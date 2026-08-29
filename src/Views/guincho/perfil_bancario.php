<?php
$bp  = defined('BASE_PATH') ? BASE_PATH : '';
$g   = $guincho ?? [];
$tiposPix = ['cpf' => 'CPF', 'email' => 'E-mail', 'telefone' => 'Telefone', 'aleatoria' => 'Aleatória'];
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/tow-perfil-bancario.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">
    <div class="mini-shell mini-form">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Conta</span>
                <h1><i class="fas fa-building-columns me-2 text-primary-custom"></i>Dados bancários</h1>
                <p>Mesmo fluxo de PIX, agora com apresentação visual alinhada ao novo perfil do guincho.</p>
            </div>
        </header>

        <?php foreach (($flashMsg ?? []) as $flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <section class="mini-hero p-4 p-lg-5 mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <h2 class="mb-2 mini-title">Recebimento via PIX</h2>
                    <p class="mb-0 text-muted">A chave usada nos repasses continua isolada do restante do cadastro, mas agora com navegação e composição consistentes com o novo layout.</p>
                </div>
                <div class="col-lg-4">
                    <div class="mini-nav justify-content-lg-end">
                        <a href="<?php echo $bp; ?>/guincho/perfil"><i class="fas fa-user"></i>Conta</a>
                        <a href="<?php echo $bp; ?>/guincho/operacao"><i class="fas fa-truck-pickup"></i>Operação</a>
                        <a href="<?php echo $bp; ?>/guincho/bancario" class="active"><i class="fas fa-qrcode"></i>PIX</a>
                    </div>
                </div>
            </div>
        </section>

        <form method="POST" action="<?php echo $bp; ?>/guincho/bancario/salvar">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <section class="mini-card p-4 p-lg-5 mb-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h3 class="mb-1 mini-subtitle"><i class="fas fa-wallet me-2 text-primary-custom"></i>Configuração de Repasse</h3>
                        <p class="mb-0 text-muted">Os pagamentos aprovados continuam apontando para os dados abaixo.</p>
                    </div>
                    <span class="badge text-bg-success px-3 py-2">Repasse ativo</span>
                </div>

                <div class="mini-pane">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo da chave Pix</label>
                            <select name="chave_pix_tipo" class="form-select" required>
                                <?php foreach ($tiposPix as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($g['chave_pix_tipo'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Chave Pix</label>
                            <input type="text" name="chave_pix" class="form-control" value="<?php echo htmlspecialchars($g['chave_pix'] ?? ''); ?>" required>
                            <div class="form-text">Esse dado permanece exclusivo do módulo financeiro e segue sendo usado no repasse após corrida concluída.</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button class="btn btn-success px-4"><i class="fas fa-save me-2"></i>Salvar dados bancários</button>
                </div>
            </section>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
