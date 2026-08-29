<?php
$bp  = defined('BASE_PATH') ? BASE_PATH : '';
$g   = $guincho ?? [];
$u   = $dadosUsuario ?? [];
$tiposPix = ['cpf' => 'CPF', 'email' => 'E-mail', 'telefone' => 'Telefone', 'aleatoria' => 'Aleatória'];
include __DIR__ . '/../layouts/header.php';

$nome = trim((string)($u['nome'] ?? 'Guincheiro'));
$iniciais = mb_strtoupper(mb_substr($nome, 0, 1) . mb_substr(strstr($nome, ' ') ?: '', 1, 1));
if (trim($iniciais) === '') {
    $iniciais = 'GU';
}
$rep = (float)($g['reputacao'] ?? 0);
$avaliacoes = (int)($g['total_avaliacoes'] ?? 0);
$aprovado = !empty($g['aprovado']);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/tow-perfil.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">
    <div class="ops-shell">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Conta</span>
                <h1><i class="fas fa-user-pen me-2 text-primary-custom"></i>Meu Perfil</h1>
                <p>Novo layout para operação, conta e PIX, sem remover nenhuma funcionalidade do cadastro atual.</p>
            </div>
        </header>

        <?php foreach ($flashMsg ?? [] as $flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <form method="POST" action="<?php echo $bp; ?>/guincho/perfil/salvar" enctype="multipart/form-data" class="ops-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <section class="ops-hero p-4 p-lg-5 mb-4">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                            <div class="ops-avatar"><?php echo htmlspecialchars($iniciais); ?></div>
                            <div>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="ops-chip <?php echo $aprovado ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo $aprovado ? 'circle-check' : 'clock'; ?>"></i>
                                        <?php echo $aprovado ? 'Cadastro aprovado' : 'Aguardando aprovação'; ?>
                                    </span>
                                    <span class="ops-chip default"><i class="fas fa-truck-pickup"></i>Perfil operacional</span>
                                    <span class="ops-chip default"><i class="fas fa-qrcode"></i>PIX integrado</span>
                                </div>
                                <h2 class="mb-1 ops-hero-title"><?php echo htmlspecialchars($nome); ?></h2>
                                <p class="mb-0 text-muted">Estrutura inspirada nas referências da pasta `screenshot`, adaptada à paleta atual do projeto e ao fluxo real do guincheiro.</p>
                            </div>
                        </div>

                        <div class="ops-stat-grid mt-4">
                            <div class="ops-stat">
                                <span class="label">Placa do guincho</span>
                                <span class="value"><?php echo htmlspecialchars($g['placa_guincho'] ?? 'Não informada'); ?></span>
                            </div>
                            <div class="ops-stat">
                                <span class="label">Raio operacional</span>
                                <span class="value"><?php echo (int)($g['raio_cobertura_km'] ?? 20); ?> km</span>
                            </div>
                            <div class="ops-stat">
                                <span class="label">Avaliação média</span>
                                <span class="value"><?php echo $rep > 0 ? '⭐ ' . number_format($rep, 1) : 'Sem nota'; ?></span>
                            </div>
                            <div class="ops-stat">
                                <span class="label">Avaliações</span>
                                <span class="value"><?php echo $avaliacoes > 0 ? $avaliacoes . ' registro(s)' : 'Nenhuma ainda'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="ops-pane h-100">
                            <div class="ops-card-header">
                                <div>
                                    <h3 class="ops-card-title"><i class="fas fa-map-location-dot me-2 text-danger"></i>Status Operacional</h3>
                                    <p class="ops-card-subtitle">Resumo rápido do cadastro e dos itens que afetam o aceite de corridas.</p>
                                </div>
                            </div>
                            <div class="d-grid gap-3">
                                <div class="d-flex justify-content-between align-items-center rounded-4 px-3 py-3 ops-summary-row">
                                    <div>
                                        <div class="small text-muted">Capacidade</div>
                                        <strong><?php echo htmlspecialchars((string)($g['capacidade_ton'] ?? '0')); ?> ton</strong>
                                    </div>
                                    <i class="fas fa-weight-hanging text-primary-custom"></i>
                                </div>
                                <div class="d-flex justify-content-between align-items-center rounded-4 px-3 py-3 ops-summary-row">
                                    <div>
                                        <div class="small text-muted">CNH</div>
                                        <strong><?php echo !empty($g['cnh_validade']) ? htmlspecialchars($g['cnh_validade']) : 'Sem validade'; ?></strong>
                                    </div>
                                    <i class="fas fa-id-card text-warning"></i>
                                </div>
                                <div class="ops-note">
                                    <div class="fw-semibold mb-1"><i class="fas fa-circle-info me-2 text-primary-custom"></i>Importante</div>
                                    <div class="small">A chave PIX, a placa, o raio e a localização operacional continuam sendo usados exatamente como antes pelo backend.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-xl-7">
                    <section class="ops-card p-4 p-lg-5 mb-4">
                        <div class="ops-card-header">
                            <div>
                                <h3 class="ops-card-title"><i class="fas fa-user me-2 text-primary-custom"></i>Dados Pessoais e Segurança</h3>
                                <p class="ops-card-subtitle">Todos os campos atuais preservados, com foco em leitura rápida e blocos mais claros.</p>
                            </div>
                        </div>

                        <div class="ops-pane">
                            <div class="mb-3">
                                <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control"
                                       value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>" required minlength="3">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control"
                                       value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" disabled>
                                <div class="form-text">O e-mail continua travado nesta tela e segue como identificador principal da conta.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Telefone / WhatsApp <span class="text-danger">*</span></label>
                                    <input type="tel" name="telefone" class="form-control"
                                           value="<?php echo htmlspecialchars($u['telefone'] ?? ''); ?>"
                                           placeholder="(11) 99999-9999" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CPF</label>
                                    <input type="text" class="form-control"
                                           value="<?php echo htmlspecialchars($u['cpf'] ?? ''); ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="ops-pane">
                            <div class="ops-note mb-3">
                                <div class="fw-semibold mb-1">Troca de senha opcional</div>
                                <div class="small">Se os campos abaixo ficarem em branco, a senha atual será mantida sem qualquer alteração.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Senha atual</label>
                                <input type="password" name="senha_atual" class="form-control" autocomplete="current-password">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nova senha</label>
                                    <input type="password" name="nova_senha" class="form-control" minlength="8" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirmar</label>
                                    <input type="password" name="confirmar_senha" class="form-control" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="ops-card p-4 p-lg-5">
                        <div class="ops-card-header">
                            <div>
                                <h3 class="ops-card-title"><i class="fas fa-truck-pickup me-2 text-primary-custom"></i>Veículo e Operação</h3>
                                <p class="ops-card-subtitle">Dados do guincho, cobertura, CNH e localização operacional em um bloco contínuo.</p>
                            </div>
                        </div>

                        <div class="ops-pane">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Placa do Guincho <span class="text-danger">*</span></label>
                                    <input type="text" name="placa_guincho" class="form-control text-uppercase"
                                           value="<?php echo htmlspecialchars($g['placa_guincho'] ?? ''); ?>"
                                           maxlength="8" required placeholder="ABC1234">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Capacidade (toneladas) <span class="text-danger">*</span></label>
                                    <input type="number" name="capacidade_ton" class="form-control"
                                           value="<?php echo htmlspecialchars($g['capacidade_ton'] ?? ''); ?>"
                                           step="0.5" min="0.5" max="100" required>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Raio de cobertura (km) <span class="text-danger">*</span></label>
                                <input type="range" name="raio_cobertura_km" class="form-range" id="raioRange"
                                       min="5" max="100" step="5"
                                       value="<?php echo (int)($g['raio_cobertura_km'] ?? 20); ?>">
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>5 km</span>
                                    <strong id="raioLabel"><?php echo (int)($g['raio_cobertura_km'] ?? 20); ?> km</strong>
                                    <span>100 km</span>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label">Número da CNH</label>
                                    <input type="text" name="cnh_numero" class="form-control"
                                           value="<?php echo htmlspecialchars($g['cnh_numero'] ?? ''); ?>"
                                           placeholder="00000000000">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Validade da CNH</label>
                                    <input type="date" name="cnh_validade" class="form-control"
                                           value="<?php echo htmlspecialchars($g['cnh_validade'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label">Cidade da Placa</label>
                                    <input type="text" class="form-control" name="cidade_placa" value="<?php echo htmlspecialchars($g['cidade_placa'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">UF</label>
                                    <input type="text" class="form-control" name="uf_placa" value="<?php echo htmlspecialchars($g['uf_placa'] ?? ''); ?>" maxlength="2">
                                </div>
                            </div>
                        </div>

                        <div class="ops-pane">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Aprovação</label>
                                    <div class="form-control-plaintext">
                                        <?php if ($aprovado): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Aprovado</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Aguardando</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nota Média</label>
                                    <div class="form-control-plaintext fw-bold text-warning">
                                        <?php echo $rep > 0 ? '⭐ ' . number_format($rep, 1) . ' (' . $avaliacoes . ' avaliações)' : '—'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Localização de Operação (Clique no mapa)</label>
                                <div id="map" class="ops-map"></div>
                                <input type="hidden" name="lat_operacao" id="lat_operacao" value="<?php echo htmlspecialchars($g['lat_operacao'] ?? '-23.5505'); ?>">
                                <input type="hidden" name="lng_operacao" id="lng_operacao" value="<?php echo htmlspecialchars($g['lng_operacao'] ?? '-46.6333'); ?>">
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-xl-5">
                    <section class="ops-card p-4 p-lg-5 mb-4">
                        <div class="ops-card-header">
                            <div>
                                <h3 class="ops-card-title"><i class="fas fa-qrcode me-2 text-primary-custom"></i>Recebimento PIX</h3>
                                <p class="ops-card-subtitle">Mesma regra operacional de repasse, agora em um bloco visual separado e mais claro.</p>
                            </div>
                        </div>

                        <div class="ops-pane">
                            <div class="mb-3">
                                <label class="form-label">Tipo da chave PIX <span class="text-danger">*</span></label>
                                <select name="chave_pix_tipo" class="form-select" required>
                                    <?php foreach ($tiposPix as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo ($g['chave_pix_tipo'] ?? '') === $val ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Chave PIX <span class="text-danger">*</span></label>
                                <input type="text" name="chave_pix" class="form-control"
                                       value="<?php echo htmlspecialchars($g['chave_pix'] ?? ''); ?>"
                                       placeholder="Sua chave PIX" required>
                                <div class="form-text">Os repasses continuam sendo enviados para esta chave após a conclusão da corrida.</div>
                            </div>
                        </div>
                    </section>

                    <section class="ops-card p-4 p-lg-5">
                        <div class="ops-card-header">
                            <div>
                                <h3 class="ops-card-title"><i class="fas fa-image me-2 text-danger"></i>Identidade Visual do Guincho</h3>
                                <p class="ops-card-subtitle">Upload da foto do caminhão preservado, agora com destaque visual próprio.</p>
                            </div>
                        </div>

                        <div class="ops-pane">
                            <?php if (!empty($g['foto_caminhao'])): ?>
                            <div class="mb-3">
                                <img src="<?php echo $bp; ?>/public/uploads/<?php echo htmlspecialchars($g['foto_caminhao']); ?>"
                                     class="img-fluid ops-thumb ops-thumb-preview" alt="Foto do caminhão">
                            </div>
                            <?php endif; ?>
                            <div class="mb-0">
                                <label class="form-label">Foto do Caminhão</label>
                                <input type="file" class="form-control" name="foto_caminhao" accept="image/*">
                                <div class="form-text">Se enviar uma nova imagem, ela substitui a atual no perfil operacional.</div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="ops-actions mb-4">
                <a href="<?php echo $bp; ?>/guincho/dashboard" class="btn btn-outline-secondary px-4">
                    <i class="fas fa-arrow-left me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary px-5">
                    <i class="fas fa-floppy-disk me-2"></i>Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script<?php echo csp_script_nonce_attr(); ?> src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
const latInput = document.getElementById('lat_operacao');
const lngInput = document.getElementById('lng_operacao');
const map = L.map('map').setView([parseFloat(latInput.value), parseFloat(lngInput.value)], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

let marker = L.marker([parseFloat(latInput.value), parseFloat(lngInput.value)], {draggable: true}).addTo(map);

map.on('click', function(e) {
    marker.setLatLng(e.latlng);
    latInput.value = e.latlng.lat;
    lngInput.value = e.latlng.lng;
});

marker.on('dragend', function(e) {
    latInput.value = e.target.getLatLng().lat;
    lngInput.value = e.target.getLatLng().lng;
});
</script>
<script<?php echo csp_script_nonce_attr(); ?>>
const raioRange = document.getElementById('raioRange');
const raioLabel = document.getElementById('raioLabel');
if (raioRange) {
    raioRange.addEventListener('input', () => raioLabel.textContent = raioRange.value + ' km');
}
</script>
