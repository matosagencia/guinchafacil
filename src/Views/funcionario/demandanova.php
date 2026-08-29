<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$tipoPreSelecionado = $tipoPreSelecionado ?? '';
$pedidoIdPre = $pedidoIdPre ?? 0;
$guinchoIdPre = $guinchoIdPre ?? 0;
$paymentJobIdPre = $paymentJobIdPre ?? 0;
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_funcionario.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Atendimento</span>
            <h1><i class="fas fa-square-plus me-2 text-primary-custom"></i>Nova demanda</h1>
            <p>Preencha e envie — nada acontece até um gerente aprovar.</p>
        </div>
    </header>

    <?php
    $flash = $_SESSION['_flash'] ?? null;
    if ($flash) { unset($_SESSION['_flash']); }
    ?>
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3" style="max-width:720px">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>

    <section class="fin-card p-4 p-lg-5" style="max-width:720px">
        <form method="POST" action="<?php echo $bp; ?>/funcionario/demanda/criar" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="mb-3">
                <label class="form-label">Tipo de demanda</label>
                <select class="form-select" name="tipo" id="tipoDemanda" required>
                    <option value="">Selecione...</option>
                    <option value="cancelamento" <?php echo $tipoPreSelecionado === 'cancelamento' ? 'selected' : ''; ?>>Cancelamento de pedido</option>
                    <option value="conclusao_manual" <?php echo $tipoPreSelecionado === 'conclusao_manual' ? 'selected' : ''; ?>>Conclusão manual assistida (GPS/servidor indisponível)</option>
                    <option value="pagamento" <?php echo $tipoPreSelecionado === 'pagamento' ? 'selected' : ''; ?>>Reprocessar repasse (pagamento)</option>
                    <option value="reembolso" <?php echo $tipoPreSelecionado === 'reembolso' ? 'selected' : ''; ?>>Reembolso / estorno</option>
                    <option value="alteracao_dados" <?php echo $tipoPreSelecionado === 'alteracao_dados' ? 'selected' : ''; ?>>Alteração de dados sensíveis</option>
                </select>
            </div>

            <div class="campo-tipo" data-tipos="cancelamento conclusao_manual reembolso">
                <div class="mb-3">
                    <label class="form-label">Pedido</label>
                    <input type="number" class="form-control" name="pedido_id" value="<?php echo $pedidoIdPre ?: ''; ?>" placeholder="ID do pedido">
                </div>
            </div>

            <div class="campo-tipo" data-tipos="pagamento">
                <div class="mb-3">
                    <label class="form-label">Job de repasse</label>
                    <input type="number" class="form-control" name="payment_job_id" value="<?php echo $paymentJobIdPre ?: ''; ?>" placeholder="ID do job">
                </div>
            </div>

            <div class="campo-tipo" data-tipos="reembolso">
                <div class="mb-3">
                    <label class="form-label">Valor a estornar (opcional — em branco = valor total)</label>
                    <input type="number" step="0.01" class="form-control" name="valor_envolvido" placeholder="Ex.: 45.90">
                </div>
            </div>

            <div class="campo-tipo" data-tipos="conclusao_manual">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Comprovante de coleta</label>
                        <input type="file" class="form-control" name="comprovante_coleta" accept="image/jpeg,image/png">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Comprovante de entrega</label>
                        <input type="file" class="form-control" name="comprovante_entrega" accept="image/jpeg,image/png">
                    </div>
                </div>
            </div>

            <div class="campo-tipo" data-tipos="alteracao_dados">
                <div class="mb-3">
                    <label class="form-label">Campo a alterar</label>
                    <select class="form-select" name="campo" id="campoAlteracao">
                        <option value="pedido.observacao_interna">Observação interna do pedido</option>
                        <option value="guincho.chave_pix">Chave PIX do guincheiro</option>
                        <option value="guincho.chave_pix_tipo">Tipo da chave PIX do guincheiro</option>
                    </select>
                </div>
                <div class="mb-3" id="campoPedidoId">
                    <label class="form-label">Pedido</label>
                    <input type="number" class="form-control" name="pedido_id" placeholder="ID do pedido">
                </div>
                <div class="mb-3 d-none" id="campoGuinchoId">
                    <label class="form-label">Guincho</label>
                    <input type="number" class="form-control" name="guincho_id" placeholder="ID do guincho">
                </div>
                <div class="mb-3">
                    <label class="form-label">Novo valor</label>
                    <input type="text" class="form-control" name="valor_novo" placeholder="Novo valor do campo">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Justificativa (mínimo 20 caracteres)</label>
                <textarea class="form-control" name="justificativa" minlength="20" required rows="3" placeholder="Explique o motivo da solicitação com detalhes suficientes para o gerente avaliar."></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Enviar demanda</button>
        </form>
    </section>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>

<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('tipoDemanda');
    var grupos = document.querySelectorAll('.campo-tipo');

    // Vários grupos reaproveitam o mesmo name="pedido_id" (cancelamento/
    // conclusao_manual/reembolso E alteracao_dados têm cada um o seu campo
    // Pedido). Um <input> escondido com display:none ainda é serializado no
    // submit — com dois campos de mesmo name, o PHP fica com o ÚLTIMO valor
    // do POST, não o do grupo visível. Por isso, além de esconder o grupo,
    // desabilitamos os campos dele: input/select/textarea disabled não entra
    // no submit, então só o grupo realmente visível manda seu valor.
    function setGrupoAtivo(g, ativo) {
        g.style.display = ativo ? '' : 'none';
        g.querySelectorAll('input, select, textarea').forEach(function (campo) {
            campo.disabled = !ativo;
        });
    }

    function atualizar() {
        var tipo = select.value;
        grupos.forEach(function (g) {
            var tipos = (g.getAttribute('data-tipos') || '').split(' ');
            setGrupoAtivo(g, tipos.indexOf(tipo) !== -1);
        });
        atualizarAlteracao();
    }

    select.addEventListener('change', atualizar);

    var campoAlteracao = document.getElementById('campoAlteracao');
    var campoPedidoId = document.getElementById('campoPedidoId');
    var campoGuinchoId = document.getElementById('campoGuinchoId');
    function atualizarAlteracao() {
        if (!campoAlteracao || select.value !== 'alteracao_dados') return;
        var ehGuincho = campoAlteracao.value.indexOf('guincho.') === 0;
        campoPedidoId.classList.toggle('d-none', ehGuincho);
        campoGuinchoId.classList.toggle('d-none', !ehGuincho);
        campoPedidoId.querySelectorAll('input').forEach(function (campo) { campo.disabled = ehGuincho; });
        campoGuinchoId.querySelectorAll('input').forEach(function (campo) { campo.disabled = !ehGuincho; });
    }
    if (campoAlteracao) {
        campoAlteracao.addEventListener('change', atualizarAlteracao);
    }

    atualizar();
});
</script>
