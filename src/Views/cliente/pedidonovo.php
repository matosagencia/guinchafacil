<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div><div class="page-title"><i class="fas fa-circle-exclamation me-2 text-primary-custom"></i>Nova Solicitação de Socorro</div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-map-pin me-2"></i>Sua Localização</div>
                <div class="card-body p-0">
                    <div id="map" class="map-container" style="height:320px;border-radius:0 0 12px 12px;min-height:240px"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-file-alt me-2"></i>Detalhes do Pedido</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/cliente/pedido/criar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                        <input type="hidden" name="lat" id="lat">
                        <input type="hidden" name="lng" id="lng">
                        <div class="mb-3">
                            <label class="form-label">Veículo</label>
                            <select class="form-select" name="veiculo_id" required>
                                <option value="">Selecione seu veículo...</option>
                                <?php if (!empty($veiculos)): foreach ($veiculos as $v): ?>
                                <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['marca'].' '.$v['modelo'].' — '.$v['placa']); ?></option>
                                <?php endforeach; else: ?>
                                <option value="" disabled>Nenhum veículo — <a href="/cliente/veiculo/novo">Cadastrar</a></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Problema</label>
                            <select class="form-select" name="tipo_problema" required>
                                <option value="">Selecione o problema...</option>
                                <option value="bateria"><i class="fas fa-car-battery"></i> Bateria</option>
                                <option value="pneu">Pneu Furado</option>
                                <option value="acidente">Acidente</option>
                                <option value="combustivel">Sem Combustível</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Destino (Oficina)</label>
                            <select class="form-select" name="oficina_id" required>
                                <option value="">Selecione o destino...</option>
                                <?php if (!empty($oficinas)): foreach ($oficinas as $o): ?>
                                <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['nome']); ?></option>
                                <?php endforeach; else: ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-socorro w-100 d-block text-center">
                            <i class="fas fa-circle-exclamation me-2"></i>Confirmar Pedido de Socorro
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?php echo $bp; ?>/public/assets/js/app.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => { if (typeof MapManager !== 'undefined') MapManager.init('map'); });</script>
