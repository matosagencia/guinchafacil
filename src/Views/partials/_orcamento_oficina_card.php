<?php
// src/Views/partials/_orcamento_oficina_card.php
// Espera: $orcamentoOficina (array|null), $pedido (array)
if (empty($orcamentoOficina)) return;
$bpCard = defined('BASE_PATH') ? BASE_PATH : '';
$csrfCard = class_exists('AuthService') ? AuthService::gerarCsrfToken() : ($_SESSION['_csrf_token'] ?? '');
?>
<div class="card border-warning mb-3" id="cardOrcamentoOficina"
     data-pedido-id="<?= (int)$pedido['id'] ?>"
     data-orcamento-id="<?= (int)$orcamentoOficina['id'] ?>">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <h5 class="mb-0">
                <i class="fas fa-file-invoice-dollar text-warning me-1"></i>
                Orcamento da oficina
            </h5>
            <span class="badge text-bg-warning">Aguardando sua resposta</span>
        </div>

        <p class="mb-1">
            <strong><?= htmlspecialchars((string)$orcamentoOficina['oficina_nome']) ?></strong>
            enviou o seguinte orcamento:
        </p>

        <p class="mb-2 small text-muted">
            <?= nl2br(htmlspecialchars((string)$orcamentoOficina['descricao'])) ?>
        </p>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <div class="small text-muted">Valor proposto</div>
                <div style="font-size:1.5rem;font-weight:700;color:#B45309">
                    R$ <?= number_format((float)$orcamentoOficina['valor_total'], 2, ',', '.') ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-success flex-grow-1" id="btnAprovarOrcamento">
                <i class="fas fa-check me-1"></i> Aprovar orcamento
            </button>
            <button class="btn btn-outline-danger flex-grow-1" id="btnRecusarOrcamento">
                <i class="fas fa-xmark me-1"></i> Recusar e pedir guincho
            </button>
        </div>

        <p class="small text-muted mt-2 mb-0">
            <i class="fas fa-circle-info me-1"></i>
            Se recusar, voce podera chamar o reboque com desconto especial.
        </p>

        <div id="orcamentoFeedback" class="alert mt-3 d-none" role="alert"></div>
    </div>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    const card = document.getElementById('cardOrcamentoOficina');
    if (!card) return;
    const BP     = <?= json_encode($bpCard) ?>;
    const CSRF   = <?= json_encode($csrfCard) ?>;
    const PEDIDO = card.dataset.pedidoId;
    const ORC    = card.dataset.orcamentoId;
    const fb     = document.getElementById('orcamentoFeedback');

    async function responder(decisao) {
        fb.classList.add('d-none');
        const btns = card.querySelectorAll('button');
        btns.forEach(b => b.disabled = true);

        try {
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('orcamento_id', ORC);
            fd.append('decisao', decisao);

            const r = await fetch(BP + '/cliente/pedido/' + PEDIDO + '/orcamento-oficina/responder', {
                method: 'POST', body: fd, headers: { 'Accept': 'application/json' }
            });
            const d = await r.json();

            if (!d.ok) {
                fb.textContent = d.erro || 'Falha ao processar.';
                fb.className = 'alert alert-danger mt-3';
                fb.classList.remove('d-none');
                btns.forEach(b => b.disabled = false);
                return;
            }

            fb.textContent = d.mensagem || 'Feito!';
            fb.className = 'alert alert-success mt-3';
            fb.classList.remove('d-none');
            setTimeout(() => location.reload(), 1500);
        } catch (e) {
            fb.textContent = 'Erro de conexao.';
            fb.className = 'alert alert-danger mt-3';
            fb.classList.remove('d-none');
            btns.forEach(b => b.disabled = false);
        }
    }

    document.getElementById('btnAprovarOrcamento')?.addEventListener('click', () => responder('aprovar'));
    document.getElementById('btnRecusarOrcamento')?.addEventListener('click', () => responder('recusar'));
})();
</script>