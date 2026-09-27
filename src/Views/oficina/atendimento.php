<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$st = (string)($pedido['status'] ?? '');
$podeChegada  = in_array($st, ['oficina_aceitou','oficina_a_caminho'], true);
$podeOrcar    = $st === 'no_local';
$podeIniciar  = $st === 'orcamento_aprovado';
$podeConcluir = $st === 'em_execucao_servico';
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<script>document.body.classList.add('theme-oficina');</script>
<div class="main-wrapper" style="display:flex;min-height:100vh">
    <?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
    <main class="main-content" style="flex:1;padding:1.5rem;max-width:900px">
        <a href="<?= $bp ?>/oficina/dashboard" class="btn-ofi-outline mb-3"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
        <?php if (!empty($_SESSION['_flash'])): foreach ((array)$_SESSION['_flash'] as $f): unset($_SESSION['_flash']); ?>
            <div class="alert alert-<?= $f['type'] === 'success' ? 'success' : 'danger' ?> mb-3"><?= htmlspecialchars($f['message']) ?></div>
        <?php endforeach; endif; ?>

        <h1 style="font-size:1.3rem;font-weight:700;color:var(--ofi-primary-darker)">
            Pedido #<?= (int)$pedido['id'] ?>
            <span class="ofi-badge ofi-badge--info ms-2"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$st))) ?></span>
        </h1>

        <div class="ofi-card mb-3">
            <div class="ofi-fluxo">
                <?php
                $etapas = [['aceito','Aceito','fa-check'],['chegada','Cheguei','fa-location-dot'],['orcamento','Orcamento','fa-file-invoice-dollar'],['servico','Servico','fa-screwdriver-wrench'],['concluido','Concluido','fa-flag-checkered']];
                $atual = match(true) {
                    $st === 'oficina_a_caminho' => 1,
                    $st === 'no_local' => 2,
                    in_array($st, ['orcamento_enviado','orcamento_aprovado'], true) => 2,
                    $st === 'em_execucao_servico' => 3,
                    $st === 'concluido' => 4,
                    default => 0,
                };
                foreach ($etapas as $i => [$slug,$lbl,$ic]):
                    $cls = $i < $atual ? 'is-done' : ($i === $atual ? 'is-active' : '');
                ?>
                <div class="ofi-fluxo-step <?= $cls ?>">
                    <div class="ofi-fluxo-dot"><i class="fas <?= $ic ?>"></i></div>
                    <div class="ofi-fluxo-label"><?= $lbl ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ofi-card mb-3">
            <h2 class="ofi-card-title mb-3">Cliente e local</h2>
            <div class="row g-3">
                <div class="col-md-6"><div class="ofi-card-sub">Origem</div><div><?= htmlspecialchars((string)($pedido['endereco_origem'] ?? '-')) ?></div></div>
                <div class="col-md-6"><div class="ofi-card-sub">Destino</div><div><?= htmlspecialchars((string)($pedido['endereco_destino'] ?? '-')) ?></div></div>
                <div class="col-md-6"><div class="ofi-card-sub">Tipo</div><div><?= htmlspecialchars((string)($pedido['tipo_problema'] ?? '-')) ?></div></div>
                <div class="col-md-6"><div class="ofi-card-sub">Descricao</div><div><?= htmlspecialchars((string)($pedido['descricao'] ?? '-')) ?></div></div>
            </div>
        </div>

        <?php if ($podeChegada): ?>
        <div class="ofi-card mb-3">
            <h2 class="ofi-card-title mb-1">Cheguei no cliente</h2>
            <p class="ofi-card-sub mb-3">Foto obrigatoria + GPS.</p>
            <input type="file" id="fotoChegada" accept="image/*" capture="environment" class="form-control mb-2">
            <textarea id="obsChegada" class="form-control mb-2" rows="2" placeholder="Observacao (opcional)"></textarea>
            <button class="btn-ofi-primary" id="btnCheguei"><i class="fas fa-location-dot me-1"></i> Registrar chegada</button>
        </div>
        <?php endif; ?>

        <?php if ($podeOrcar): ?>
        <div class="ofi-card mb-3">
            <h2 class="ofi-card-title mb-1">Enviar orcamento</h2>
            <p class="ofi-card-sub mb-3">Se o cliente recusar, o sistema oferece reboque com desconto.</p>
            <form method="POST" action="<?= $bp ?>/oficina/pedido/<?= (int)$pedido['id'] ?>/orcamento">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="row g-2 mb-2">
                    <div class="col-md-4"><label class="form-label small">Valor R$</label><input type="number" step="0.01" min="0.01" name="valor_total" class="form-control" required></div>
                    <div class="col-md-8"><label class="form-label small">Descricao</label><input type="text" name="descricao" class="form-control" required placeholder="Ex: Troca de bateria + teste"></div>
                </div>
                <button class="btn-ofi-primary"><i class="fas fa-paper-plane me-1"></i> Enviar</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($podeIniciar): ?>
        <div class="ofi-card mb-3">
            <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> Orcamento aprovado. Inicie o servico.</div>
            <button class="btn-ofi-primary" id="btnIniciar"><i class="fas fa-play me-1"></i> Iniciar servico</button>
        </div>
        <?php endif; ?>

        <?php if ($podeConcluir): ?>
        <div class="ofi-card mb-3">
            <h2 class="ofi-card-title mb-1">Concluir atendimento</h2>
            <p class="ofi-card-sub mb-3">Foto final obrigatoria.</p>
            <input type="file" id="fotoConclusao" accept="image/*" capture="environment" class="form-control mb-2">
            <button class="btn-ofi-primary" id="btnConcluir"><i class="fas fa-flag-checkered me-1"></i> Concluir</button>
        </div>
        <?php endif; ?>
    </main>
</div>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    const BP = '<?= $bp ?>'; const CSRF = '<?= htmlspecialchars($csrfToken) ?>'; const PID = <?= (int)$pedido['id'] ?>;
    async function gps(){ return new Promise(r => {
        if (!navigator.geolocation) return r({});
        navigator.geolocation.getCurrentPosition(p => r({lat:p.coords.latitude,lng:p.coords.longitude}), () => r({}), {enableHighAccuracy:true,timeout:8000});
    }); }
    async function enviar(tipo, inp, obs, prox){
        const file = inp?.files?.[0];
        if (tipo === 'chegada' && !file) return alert('Anexe a foto.');
        if (tipo === 'servico_concluido' && !file) return alert('Anexe a foto final.');
        const g = await gps();
        const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('tipo', tipo); fd.append('observacao', obs || '');
        if (g.lat) fd.append('latitude', g.lat);
        if (g.lng) fd.append('longitude', g.lng);
        if (file) fd.append('foto', file);
        const r = await fetch(BP + '/oficina/pedido/' + PID + '/evidencia', {method:'POST',body:fd,headers:{Accept:'application/json'}});
        const j = await r.json();
        if (!j.ok) return alert(j.erro || 'Falha.');
        if (prox) {
            const f2 = new FormData(); f2.append('csrf_token', CSRF); f2.append('status', prox);
            await fetch(BP + '/oficina/pedido/' + PID + '/atualizar', {method:'POST',body:f2});
        }
        location.reload();
    }
    document.getElementById('btnCheguei')?.addEventListener('click', () => enviar('chegada', document.getElementById('fotoChegada'), document.getElementById('obsChegada').value, 'no_local'));
    document.getElementById('btnIniciar')?.addEventListener('click', () => enviar('servico_iniciado', null, null, 'em_execucao_servico'));
    document.getElementById('btnConcluir')?.addEventListener('click', () => enviar('servico_concluido', document.getElementById('fotoConclusao'), null, 'concluido'));
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>