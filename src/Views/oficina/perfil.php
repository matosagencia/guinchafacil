<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$servicosDisponiveis = [
    'borracharia'     => ['Pneu furado / troca',   'fa-circle-notch'],
    'eletrica'        => ['Eletrica automotiva',   'fa-bolt'],
    'bateria'         => ['Bateria / partida',     'fa-car-battery'],
    'mecanica'        => ['Mecanica geral',        'fa-gears'],
    'chaveiro'        => ['Chaveiro automotivo',   'fa-key'],
    'funilaria'       => ['Funilaria e pintura',   'fa-spray-can'],
    'ar_condicionado' => ['Ar-condicionado',       'fa-snowflake'],
    'injecao'         => ['Injecao eletronica',    'fa-microchip'],
    'suspensao'       => ['Suspensao / freios',    'fa-arrows-up-down'],
];
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<script>document.body.classList.add('theme-oficina');</script>
<div class="main-wrapper" style="display:flex;min-height:100vh">
    <?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
    <main class="main-content" style="flex:1;padding:1.5rem;max-width:960px">
        <h1 style="font-size:1.4rem;font-weight:700;color:var(--ofi-primary-darker);margin-bottom:1rem"><i class="fas fa-user-gear me-2"></i>Perfil da Oficina</h1>
        <?php if (!empty($_SESSION['_flash'])): foreach ((array)$_SESSION['_flash'] as $f): unset($_SESSION['_flash']); ?>
            <div class="alert alert-<?= $f['type'] === 'success' ? 'success' : 'danger' ?> mb-3"><?= htmlspecialchars($f['message']) ?></div>
        <?php endforeach; endif; ?>

        <form method="POST" action="<?= $bp ?>/oficina/perfil/salvar" class="ofi-card mb-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <h2 class="ofi-card-title mb-3">Dados da oficina</h2>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label small">Nome</label><input type="text" name="nome" class="form-control" value="<?= htmlspecialchars((string)$oficina['nome']) ?>" required></div>
                <div class="col-md-4"><label class="form-label small">Telefone</label><input type="tel" name="telefone" class="form-control" value="<?= htmlspecialchars((string)($oficina['telefone'] ?? '')) ?>"></div>
                <div class="col-12"><label class="form-label small">Endereco</label><input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars((string)$oficina['endereco']) ?>" required></div>
                <div class="col-md-4"><label class="form-label small">Latitude</label><input type="text" name="latitude" id="latitude" class="form-control" value="<?= htmlspecialchars((string)($oficina['latitude'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label small">Longitude</label><input type="text" name="longitude" id="longitude" class="form-control" value="<?= htmlspecialchars((string)($oficina['longitude'] ?? '')) ?>"></div>
                <div class="col-md-4 d-flex align-items-end"><button type="button" id="btnUsarGps" class="btn-ofi-outline w-100"><i class="fas fa-location-crosshairs me-1"></i> Usar GPS</button></div>
                <div class="col-12">
                    <label class="form-label small"><i class="fas fa-route me-1"></i>Raio: <strong id="raioLabel"><?= (int)($oficina['raio_atendimento_km'] ?? 10) ?> km</strong></label>
                    <input type="range" name="raio_atendimento_km" id="raioRange" class="form-range" min="1" max="100" step="1" value="<?= (int)($oficina['raio_atendimento_km'] ?? 10) ?>">
                </div>
            </div>
            <hr class="my-4">
            <h2 class="ofi-card-title mb-3"><i class="fas fa-pix me-1"></i> Recebimento (PIX)</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">Tipo</label>
                    <select name="pix_tipo" class="form-select">
                        <option value="">--</option>
                        <?php foreach (['cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'E-mail','telefone'=>'Telefone','aleatoria'=>'Aleatoria'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($oficina['pix_tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label small">Chave PIX</label><input type="text" name="pix_chave" class="form-control" value="<?= htmlspecialchars((string)($oficina['pix_chave'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label small">Banco</label><input type="text" name="banco_codigo" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_codigo'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label small">Agencia</label><input type="text" name="banco_agencia" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_agencia'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label small">Conta</label><input type="text" name="banco_conta" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_conta'] ?? '')) ?>"></div>
            </div>
            <div class="text-end mt-4"><button type="submit" class="btn-ofi-primary"><i class="fas fa-save me-1"></i> Salvar</button></div>
        </form>

        <form method="POST" action="<?= $bp ?>/oficina/servicos/salvar" class="ofi-card">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <h2 class="ofi-card-title mb-1">Servicos oferecidos</h2>
            <p class="ofi-card-sub mb-3">Marque tudo que sua oficina faz.</p>
            <div class="row g-2">
                <?php foreach ($servicosDisponiveis as $slug => [$label, $icone]): ?>
                <div class="col-md-4 col-sm-6">
                    <label class="ofi-servico-item <?= in_array($slug, $servicosAtuais, true) ? 'is-checked' : '' ?>">
                        <input type="checkbox" name="servicos[]" value="<?= $slug ?>" <?= in_array($slug, $servicosAtuais, true) ? 'checked' : '' ?>>
                        <i class="fas <?= $icone ?>"></i><span><?= htmlspecialchars($label) ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-end mt-4"><button type="submit" class="btn-ofi-primary"><i class="fas fa-save me-1"></i> Salvar servicos</button></div>
        </form>
    </main>
</div>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    const r = document.getElementById('raioRange'); const l = document.getElementById('raioLabel');
    if (r) r.addEventListener('input', () => l.textContent = r.value + ' km');
    document.getElementById('btnUsarGps')?.addEventListener('click', () => {
        if (!navigator.geolocation) return alert('GPS indisponivel.');
        navigator.geolocation.getCurrentPosition(p => {
            document.getElementById('latitude').value  = p.coords.latitude.toFixed(7);
            document.getElementById('longitude').value = p.coords.longitude.toFixed(7);
        }, () => alert('Sem localizacao.'));
    });
    document.querySelectorAll('.ofi-servico-item input').forEach(c => {
        c.addEventListener('change', () => c.closest('.ofi-servico-item').classList.toggle('is-checked', c.checked));
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>