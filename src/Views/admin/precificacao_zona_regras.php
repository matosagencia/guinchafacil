<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
// vehicle_category aqui casa com veiculos.tipo (carro/moto/caminhao/van/onibus/outro)
// direto — não com a categoria tarifária do TarifaService (que funde
// carro+moto em "popular"), justamente para permitir diferenciar preço de
// moto vs carro (ver ZonePricingService/ClienteController §DESLOCAMENTO-01).
$categorias = ['' => 'Qualquer tipo de veículo (coringa)', 'carro' => 'Carro', 'moto' => 'Moto', 'caminhao' => 'Caminhão', 'van' => 'Van', 'onibus' => 'Ônibus', 'outro' => 'Outro'];
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <?php $totalRegrasZona = count($regras ?? []); ?>
    <div class="ops-topbar">
        <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="regrasZonaBusca" placeholder="Buscar por serviço ou categoria" autocomplete="off" aria-label="Buscar regras da zona"></div>
        <div class="ops-topbar__meta">
            <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalRegrasZona; ?> regras vigentes</span>
            <?php if (empty($zona['polygon_geojson'])): ?><span style="color:var(--danger,#dc3545);font-weight:700;"><i class="fas fa-triangle-exclamation me-1"></i>Sem polígono</span><?php endif; ?>
        </div>
    </div>

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">Precificação por zona</span>
            <h1>Regras — <?php echo htmlspecialchars($zona['name']); ?> <code class="small text-muted"><?php echo htmlspecialchars($zona['code']); ?></code></h1>
            <p>
                Cada regra vale para um tipo de serviço (+ categoria de veículo opcional) DENTRO desta zona.
                Salvar cria uma NOVA VERSÃO (histórico preservado — pedidos antigos continuam com o preço que
                usaram na hora, ver <code>order_price_snapshots</code>); a versão mais recente ativa é a vigente.
                <?php if (empty($zona['polygon_geojson'])): ?>
                    <strong class="text-danger">Esta zona ainda não tem polígono desenhado — nenhuma regra abaixo tem efeito até isso ser corrigido em "Zonas de precificação".</strong>
                <?php endif; ?>
            </p>
        </div>
        <a href="<?php echo $bp; ?>/admin/precificacao/zonas" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar para zonas
        </a>
    </header>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="fas fa-circle-plus me-2"></i>Nova regra / nova versão</div>
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/precificacao/regra/salvar" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="pricing_zone_id" value="<?php echo (int)$zona['id']; ?>">

                <div class="col-md-4">
                    <label class="form-label">Tipo de serviço</label>
                    <select name="service_type_id" class="form-select" required>
                        <?php foreach ($tiposServico as $t): ?>
                        <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?> (<?php echo htmlspecialchars($t['code']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Categoria de veículo</label>
                    <select name="vehicle_category" class="form-select">
                        <?php foreach ($categorias as $valor => $rotulo): ?>
                        <option value="<?php echo htmlspecialchars($valor); ?>"><?php echo htmlspecialchars($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Preço base (R$)</label>
                    <input type="text" class="form-control" name="base_customer_price" value="0.00">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Mínimo (R$)</label>
                    <input type="text" class="form-control" name="minimum_customer_price" value="0.00">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Máximo (R$, opcional)</label>
                    <input type="text" class="form-control" name="maximum_customer_price" placeholder="—">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Km incluídos</label>
                    <input type="text" class="form-control" name="included_distance_km" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">R$/km excedente</label>
                    <input type="text" class="form-control" name="extra_distance_price" value="0.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Minutos incluídos</label>
                    <input type="text" class="form-control" name="included_minutes" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">R$/minuto excedente</label>
                    <input type="text" class="form-control" name="extra_minute_price" value="0.00">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Mult. noturno</label>
                    <input type="text" class="form-control" name="night_multiplier" value="1.00">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mult. feriado</label>
                    <input type="text" class="form-control" name="holiday_multiplier" value="1.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Repasse base prestador (R$)</label>
                    <input type="text" class="form-control" name="provider_base_amount" value="0.00">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Comissão (tipo)</label>
                    <select name="platform_fee_type" class="form-select">
                        <option value="PERCENTAGE">% do valor</option>
                        <option value="FIXED">Valor fixo (R$)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Comissão (valor)</label>
                    <input type="text" class="form-control" name="platform_fee_value" value="0.20" placeholder="0.20 = 20%">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Vigência início (opcional)</label>
                    <input type="date" class="form-control" name="effective_from">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vigência fim (opcional)</label>
                    <input type="date" class="form-control" name="effective_until">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Salvar nova versão</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold"><i class="fas fa-tags me-2"></i>Regras vigentes (versão ativa mais recente por serviço/categoria)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Categoria</th>
                            <th>Base</th>
                            <th>Mínimo</th>
                            <th>Km incl.</th>
                            <th>R$/km exc.</th>
                            <th>Comissão</th>
                            <th>Versão</th>
                            <th style="width:70px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($regras)): ?>
                        <tr><td colspan="9" class="text-center p-4 text-muted small">Nenhuma regra ativa para esta zona ainda.</td></tr>
                    <?php else: foreach ($regras as $r): ?>
                        <tr data-search="<?php echo htmlspecialchars(strtolower($r['service_name'] . ' ' . $r['service_code'] . ' ' . ($r['vehicle_category'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                            <td><?php echo htmlspecialchars($r['service_name']); ?> <code class="small text-muted"><?php echo htmlspecialchars($r['service_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($r['vehicle_category'] ?? 'qualquer'); ?></td>
                            <td>R$ <?php echo number_format((float)$r['base_customer_price'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format((float)$r['minimum_customer_price'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format((float)$r['included_distance_km'], 1, ',', '.'); ?> km</td>
                            <td>R$ <?php echo number_format((float)$r['extra_distance_price'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars((string)$r['platform_fee_type']); ?>: <?php echo htmlspecialchars((string)$r['platform_fee_value']); ?></td>
                            <td>v<?php echo (int)$r['version']; ?></td>
                            <td>
                                <form method="post" action="<?php echo $bp; ?>/admin/precificacao/regra/desativar" onsubmit="return confirm('Desativar esta regra? A zona volta a usar o cálculo global para este serviço/categoria.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="regra_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="hidden" name="pricing_zone_id" value="<?php echo (int)$zona['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script<?php echo csp_script_nonce_attr(); ?>>
    (function () {
        var busca = document.getElementById('regrasZonaBusca');
        var linhas = Array.prototype.slice.call(document.querySelectorAll('tr[data-search]'));
        if (!busca) return;
        busca.addEventListener('input', function () {
            var query = busca.value.trim().toLowerCase();
            linhas.forEach(function (linha) {
                linha.hidden = Boolean(query) && (linha.getAttribute('data-search') || '').indexOf(query) < 0;
            });
        });
    })();
    </script>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
