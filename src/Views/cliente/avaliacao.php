<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div><div class="page-title"><i class="fas fa-star me-2 text-primary-custom"></i>Avaliar Atendimento</div></div>
        <a href="<?php echo $bp; ?>/cliente/historico" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-star me-2"></i>Sua Avaliação</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/cliente/avaliar_pedido">
                        <?php if (!empty($csrf_token)): ?><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>"><?php endif; ?>
                        <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($pedidoId ?? ''); ?>">
                        <div class="mb-4 text-center">
                            <label class="form-label d-block mb-3">Nota do Atendimento</label>
                            <div class="star-rating justify-content-center">
                                <input type="radio" name="estrelas" id="estrela5" value="5"><label for="estrela5">★</label>
                                <input type="radio" name="estrelas" id="estrela4" value="4"><label for="estrela4">★</label>
                                <input type="radio" name="estrelas" id="estrela3" value="3"><label for="estrela3">★</label>
                                <input type="radio" name="estrelas" id="estrela2" value="2"><label for="estrela2">★</label>
                                <input type="radio" name="estrelas" id="estrela1" value="1"><label for="estrela1">★</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comentário (opcional)</label>
                            <textarea class="form-control" name="comentario" rows="4" placeholder="Conta como foi seu atendimento..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Enviar Avaliação</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
