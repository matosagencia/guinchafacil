<?php
$bp  = defined('BASE_PATH') ? BASE_PATH : '';
$g   = $guincho ?? [];
include __DIR__ . '/../layouts/header.php';
$rep = (float)($g['reputacao'] ?? 0);
$aprovado = !empty($g['aprovado']);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/tow-perfil-operacao.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">
    <div class="ops-mini-shell ops-mini-form">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Conta</span>
                <h1><i class="fas fa-truck-pickup me-2 text-primary-custom"></i>Dados do guincho</h1>
                <p>Mesmo fluxo operacional, agora com um layout coerente com a nova experiência do perfil.</p>
            </div>
        </header>

        <?php foreach (($flashMsg ?? []) as $flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <section class="ops-mini-hero p-4 p-lg-5 mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <h2 class="mb-2 ops-mini-title">Configuração Operacional</h2>
                    <p class="mb-0 text-muted">Placa, capacidade, raio, CNH e status continuam iguais no backend. Aqui muda só a camada visual e a legibilidade.</p>
                </div>
                <div class="col-lg-5">
                    <div class="ops-mini-nav justify-content-lg-end">
                        <a href="<?php echo $bp; ?>/guincho/perfil"><i class="fas fa-user"></i>Conta</a>
                        <a href="<?php echo $bp; ?>/guincho/operacao" class="active"><i class="fas fa-truck-pickup"></i>Operação</a>
                        <a href="<?php echo $bp; ?>/guincho/bancario"><i class="fas fa-qrcode"></i>PIX</a>
                    </div>
                </div>
            </div>
        </section>

        <form method="POST" action="<?php echo $bp; ?>/guincho/operacao/salvar">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <section class="ops-mini-card p-4 p-lg-5 mb-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="ops-mini-stat">
                            <span class="label">Status</span>
                            <span class="value"><?php echo $aprovado ? 'Aprovado' : 'Aguardando aprovação'; ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ops-mini-stat">
                            <span class="label">Raio atual</span>
                            <span class="value"><?php echo (int)($g['raio_cobertura_km'] ?? 20); ?> km</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="ops-mini-stat">
                            <span class="label">Reputação</span>
                            <span class="value"><?php echo $rep > 0 ? '⭐ ' . number_format($rep,1) . ' / 5' : '—'; ?></span>
                        </div>
                    </div>
                </div>

                <div class="ops-mini-pane">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Placa do guincho</label>
                            <input type="text" name="placa_guincho" class="form-control text-uppercase" value="<?php echo htmlspecialchars($g['placa_guincho'] ?? ''); ?>" maxlength="8" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cidade do Emplacamento</label>
                            <input type="text" name="cidade_placa" class="form-control" value="<?php echo htmlspecialchars($g['cidade_placa'] ?? ''); ?>" placeholder="Ex: Rio de Janeiro">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">UF</label>
                            <input type="text" name="uf_placa" class="form-control text-uppercase" value="<?php echo htmlspecialchars($g['uf_placa'] ?? ''); ?>" maxlength="2" placeholder="RJ">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Capacidade (toneladas)</label>
                            <input type="number" name="capacidade_ton" class="form-control" value="<?php echo htmlspecialchars($g['capacidade_ton'] ?? ''); ?>" step="0.5" min="0.5" max="100" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Raio de cobertura (km)</label>
                            <input type="range" name="raio_cobertura_km" class="form-range" id="raioRange" min="5" max="100" step="5" value="<?php echo (int)($g['raio_cobertura_km'] ?? 20); ?>">
                            <div class="small text-muted"><strong id="raioLabel"><?php echo (int)($g['raio_cobertura_km'] ?? 20); ?> km</strong></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Latitude Operacional</label>
                            <input type="number" step="0.00000001" name="lat_operacao" class="form-control" value="<?php echo htmlspecialchars($g['lat_operacao'] ?? ''); ?>" placeholder="-23.55050000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Longitude Operacional</label>
                            <input type="number" step="0.00000001" name="lng_operacao" class="form-control" value="<?php echo htmlspecialchars($g['lng_operacao'] ?? ''); ?>" placeholder="-46.63330000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número da CNH</label>
                            <input type="text" name="cnh_numero" class="form-control" value="<?php echo htmlspecialchars($g['cnh_numero'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Validade da CNH</label>
                            <input type="date" name="cnh_validade" class="form-control" value="<?php echo htmlspecialchars($g['cnh_validade'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status de aprovação</label>
                            <div class="form-control-plaintext"><?php echo $aprovado ? '<span class="badge bg-success">Aprovado</span>' : '<span class="badge bg-warning text-dark">Aguardando aprovação</span>'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reputação</label>
                            <div class="form-control-plaintext"><?php echo $rep > 0 ? '⭐ ' . number_format($rep,1) . ' / 5' : '—'; ?></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Salvar dados do guincho</button>
                </div>
            </section>
        </form>
    </div>
</main>
<script<?php echo csp_script_nonce_attr(); ?>>
document.getElementById('raioRange')?.addEventListener('input', function(){ const l=document.getElementById('raioLabel'); if(l) l.textContent=this.value+' km'; });
document.querySelector('input[name="uf_placa"]')?.addEventListener('input', function(){ this.value = this.value.toUpperCase().replace(/[^A-Z]/g,'').slice(0,2); });
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
