<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$st = (string)($pedido['status'] ?? '');
$podeChegada  = in_array($st, ['oficina_aceitou','oficina_a_caminho'], true);
$podeCancelar = $podeChegada;
$cfgPenalidade = (float)(Configuracao::get('penalidade_reputacao_cancelamento_oficina', '0.25'));
$podeOrcar    = $st === 'no_local';
$podeIniciar  = $st === 'orcamento_aprovado';
$podeConcluir = $st === 'em_execucao_servico';

$labels = [
    'oficina_aceitou'      => 'Aceito',
    'oficina_a_caminho'    => 'A caminho',
    'no_local'             => 'No local',
    'orcamento_enviado'    => 'Orcamento enviado',
    'orcamento_aprovado'   => 'Orcamento aprovado',
    'em_execucao_servico'  => 'Em execucao',
    'concluido'            => 'Concluido',
    'cancelado'            => 'Cancelado',
];
$statusLabel = $labels[$st] ?? ucfirst(str_replace('_', ' ', $st));
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/components/dashboard.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-dashboard.css">
<style>
.gf-stepper { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
.gf-stepper-step { flex:1; text-align:center; position:relative; }
.gf-stepper-dot { width:44px; height:44px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#2a1a10; color:#7C6B5C; border:2px solid #5c3d28; font-size:1rem; transition:all .2s ease; }
.gf-stepper-step.is-done .gf-stepper-dot { background:#16a34a; border-color:#16a34a; color:#fff; }
.gf-stepper-step.is-active .gf-stepper-dot { background:#d97706; border-color:#d97706; color:#fff; box-shadow:0 0 0 4px rgba(217,119,6,.18); }
.gf-stepper-label { margin-top:.5rem; font-size:.78rem; color:#7C6B5C; font-weight:500; }
.gf-stepper-step.is-done .gf-stepper-label,
.gf-stepper-step.is-active .gf-stepper-label { color:#fef3e8; font-weight:600; }
.gf-stepper-step:not(:last-child)::after { content:''; position:absolute; top:22px; left:calc(50% + 22px); right:calc(-50% + 22px); height:2px; background:#5c3d28; z-index:0; }
.gf-stepper-step.is-done:not(:last-child)::after { background:#16a34a; }
@media (max-width:575px) { .gf-stepper-label{font-size:.68rem} .gf-stepper-dot{width:34px;height:34px;font-size:.8rem} .gf-stepper-step:not(:last-child)::after{top:17px;left:calc(50% + 17px);right:calc(-50% + 17px)} }
.gf-fact { display:flex; flex-direction:column; gap:.2rem; }
.gf-fact-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:#a89686; font-weight:600; }
.gf-fact-value { color:#fef3e8; font-size:.95rem; line-height:1.35; }
</style>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>document.body.classList.add('theme-oficina');</script>

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
<main class="main-content">
    <div class="app-dashboard" style="max-width:900px">

        <header class="page-head mb-4">
            <div>
                <a href="<?= $bp ?>/oficina/dashboard" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
                <span class="eyebrow">Atendimento</span>
                <h1>Pedido #<?= (int)$pedido['id'] ?></h1>
                <p>Status atual: <strong><?= htmlspecialchars($statusLabel) ?></strong></p>
            </div>
            <?php if ($podeCancelar): ?>
            <div>
                <button type="button" id="btnCancelarAtendimentoOficina" class="btn btn-outline-danger">
                    <i class="fas fa-ban me-1"></i>Cancelar Atendimento
                </button>
            </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($_SESSION['_flash'])): foreach ((array)$_SESSION['_flash'] as $f): unset($_SESSION['_flash']); ?>
            <div class="alert alert-<?= $f['type'] === 'success' ? 'success' : 'danger' ?> mb-3">
                <?= htmlspecialchars($f['message']) ?>
            </div>
        <?php endforeach; endif; ?>

        <section class="tow-card p-4 mb-4">
            <?php
            $etapas = [
                ['aceito',    'Aceito',    'fa-check'],
                ['chegada',   'Cheguei',   'fa-location-dot'],
                ['orcamento', 'Orcamento', 'fa-file-invoice-dollar'],
                ['servico',   'Servico',   'fa-screwdriver-wrench'],
                ['concluido', 'Concluido', 'fa-flag-checkered'],
            ];
            $atual = match(true) {
                $st === 'oficina_a_caminho' => 1,
                $st === 'no_local' => 2,
                in_array($st, ['orcamento_enviado','orcamento_aprovado'], true) => 2,
                $st === 'em_execucao_servico' => 3,
                $st === 'concluido' => 4,
                default => 0,
            };
            ?>
            <div class="gf-stepper">
                <?php foreach ($etapas as $i => [$slug,$lbl,$ic]):
                    $cls = $i < $atual ? 'is-done' : ($i === $atual ? 'is-active' : '');
                ?>
                <div class="gf-stepper-step <?= $cls ?>">
                    <div class="gf-stepper-dot"><i class="fas <?= $ic ?>"></i></div>
                    <div class="gf-stepper-label"><?= $lbl ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="tow-card p-4 mb-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-map-location-dot me-2"></i>Cliente e local</h3>
                    <p class="tow-panel-subtitle">Enderecos e detalhes do atendimento</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="gf-fact">
                        <span class="gf-fact-label">Origem</span>
                        <span class="gf-fact-value"><?= htmlspecialchars((string)($pedido['endereco_origem'] ?? '-')) ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gf-fact">
                        <span class="gf-fact-label">Destino</span>
                        <span class="gf-fact-value"><?= htmlspecialchars((string)($pedido['endereco_destino'] ?? '-')) ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gf-fact">
                        <span class="gf-fact-label">Tipo</span>
                        <span class="gf-fact-value"><?= htmlspecialchars((string)($pedido['tipo_problema'] ?? '-')) ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gf-fact">
                        <span class="gf-fact-label">Distancia</span>
                        <span class="gf-fact-value"><?= number_format((float)($pedido['distancia_km'] ?? 0), 1, ',', '.') ?> km</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gf-fact">
                        <span class="gf-fact-label">Valor estimado</span>
                        <span class="gf-fact-value">R$ <?= number_format((float)($pedido['custo_estimado'] ?? 0), 2, ',', '.') ?></span>
                    </div>
                </div>
                <div class="col-12">
                    <div class="gf-fact">
                        <span class="gf-fact-label">Descricao</span>
                        <span class="gf-fact-value"><?= htmlspecialchars((string)($pedido['descricao_problema'] ?? '-')) ?></span>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($podeChegada): ?>
        <section class="tow-card p-4 mb-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-location-dot me-2"></i>Cheguei no cliente</h3>
                    <p class="tow-panel-subtitle">Foto obrigatoria + GPS</p>
                </div>
            </div>
            <input type="file" id="fotoChegada" accept="image/*" capture="environment" class="form-control mb-2">
            <textarea id="obsChegada" class="form-control mb-3" rows="2" placeholder="Observacao (opcional)"></textarea>
            <button class="btn btn-success" id="btnCheguei">
                <i class="fas fa-location-dot me-1"></i>Registrar chegada
            </button>
        </section>
        <?php endif; ?>

        <?php if ($podeOrcar): ?>
        <section class="tow-card p-4 mb-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-file-invoice-dollar me-2"></i>Enviar orcamento</h3>
                    <p class="tow-panel-subtitle">Se o cliente recusar, o sistema oferece reboque com desconto</p>
                </div>
            </div>
            <form method="POST" action="<?= $bp ?>/oficina/pedido/<?= (int)$pedido['id'] ?>/orcamento">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Valor R$</label>
                        <input type="number" step="0.01" min="0.01" name="valor_total" class="form-control" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small">Descricao</label>
                        <input type="text" name="descricao" class="form-control" required placeholder="Ex: Troca de bateria + teste">
                    </div>
                </div>
                <button class="btn btn-success">
                    <i class="fas fa-paper-plane me-1"></i>Enviar
                </button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($podeIniciar): ?>
        <section class="tow-card p-4 mb-4">
            <div class="alert alert-success mb-3">
                <i class="fas fa-check-circle me-1"></i>Orcamento aprovado. Inicie o servico.
            </div>
            <button class="btn btn-success" id="btnIniciar">
                <i class="fas fa-play me-1"></i>Iniciar servico
            </button>
        </section>
        <?php endif; ?>

        <?php if ($podeConcluir): ?>
        <section class="tow-card p-4 mb-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-flag-checkered me-2"></i>Concluir atendimento</h3>
                    <p class="tow-panel-subtitle">Foto final obrigatoria</p>
                </div>
            </div>
            <input type="file" id="fotoConclusao" accept="image/*" capture="environment" class="form-control mb-3">
            <button class="btn btn-success" id="btnConcluir">
                <i class="fas fa-flag-checkered me-1"></i>Concluir
            </button>
        </section>
        <?php endif; ?>

    </div>

    <?php if ($podeCancelar): ?>
    <!-- Modal de cancelamento pela oficina -->
    <div class="modal fade" id="modalCancelarOficina" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-ban text-danger me-2"></i>Cancelar Atendimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        Cancelar um atendimento aceito aplica uma
                        <strong>penalidade de <?= number_format($cfgPenalidade, 2, ',', '.') ?> ponto(s) na sua reputação</strong>
                        e o pedido volta para a fila de outros prestadores.
                    </div>
                    <label class="form-label">Motivo (obrigatório)</label>
                    <select id="cancelMotivoOficina" class="form-select mb-2" required>
                        <option value="">Selecione o motivo...</option>
                        <option value="Sem equipe disponível no momento">Sem equipe disponível no momento</option>
                        <option value="Capacidade operacional cheia">Capacidade operacional cheia</option>
                        <option value="Distância ou acesso inviável">Distância ou acesso inviável</option>
                        <option value="Não consegui contato com o cliente">Não consegui contato com o cliente</option>
                        <option value="Não atendo esse tipo de serviço">Não atendo esse tipo de serviço</option>
                        <option value="Veículo incompatível com meus equipamentos">Veículo incompatível com meus equipamentos</option>
                        <option value="Erro operacional / informação incorreta">Erro operacional / informação incorreta</option>
                        <option value="Outro">Outro (descrever abaixo)</option>
                    </select>
                    <textarea id="cancelMotivoOficinaOutro" class="form-control" rows="2" maxlength="200" placeholder="Descreva o motivo" style="display:none"></textarea>
                    <div class="text-danger small mt-2" id="cancelErroOficina"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarCancelamentoOficina">
                        <i class="fas fa-ban me-1"></i>Confirmar Cancelamento
                    </button>
                </div>
            </div>
        </div>
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

    // ── Cancelamento pela oficina ───────────────────────────────
    (function(){
        const btn = document.getElementById('btnCancelarAtendimentoOficina');
        if (!btn) return;
        const sel = document.getElementById('cancelMotivoOficina');
        const outro = document.getElementById('cancelMotivoOficinaOutro');
        const err = document.getElementById('cancelErroOficina');
        const btnConf = document.getElementById('btnConfirmarCancelamentoOficina');

        sel.addEventListener('change', function(){
            outro.style.display = (sel.value === 'Outro') ? '' : 'none';
        });

        btn.addEventListener('click', function(){
            err.textContent = '';
            sel.value = '';
            outro.value = '';
            outro.style.display = 'none';
            new bootstrap.Modal(document.getElementById('modalCancelarOficina')).show();
        });

        btnConf.addEventListener('click', async function(){
            err.textContent = '';
            let motivo = (sel.value || '').trim();
            if (!motivo) { err.textContent = 'Selecione o motivo do cancelamento.'; return; }
            if (motivo === 'Outro') {
                motivo = (outro.value || '').trim();
                if (motivo.length < 5) { err.textContent = 'Descreva o motivo (mínimo 5 caracteres).'; return; }
            }
            this.disabled = true;
            try {
                const body = new URLSearchParams({ csrf_token: CSRF, motivo });
                const r = await fetch(BP + '/oficina/pedido/' + PID + '/cancelar', {
                    method: 'POST', body, headers: { 'Accept': 'application/json' }
                });
                const j = await r.json();
                if (j.ok) {
                    window.location.href = BP + '/oficina/dashboard?cancelado=1';
                    return;
                }
                err.textContent = j.erro || 'Não foi possível cancelar.';
                this.disabled = false;
            } catch (e) {
                err.textContent = 'Erro de conexão. Tente novamente.';
                this.disabled = false;
            }
        });
    })();
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>