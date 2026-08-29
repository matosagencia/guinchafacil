<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$cidadesAtivas = $cidadesAtivas ?? [];
$cidadeId = $cidadeId ?? null;
$globalPorTipo = $globalPorTipo ?? [];

// §PRECO-POR-CIDADE-01: quando uma cidade está selecionada, cada linha
// pode não ter override próprio ainda (LEFT JOIN sem match => id null) —
// nesse caso mostra os valores GLOBAIS como preview (deixando claro que é
// herdado) em vez de mostrar tudo zerado, que pareceria um bug.
if ($cidadeId !== null) {
    foreach ($regras as &$r) {
        $r['_tem_override'] = $r['id'] !== null;
        if (!$r['_tem_override']) {
            $global = $globalPorTipo[(int)$r['service_type_id']] ?? null;
            if ($global) {
                foreach (['base_fee', 'pickup_km_price', 'tow_km_price', 'labor_fee', 'minimum_price', 'night_multiplier', 'holiday_multiplier', 'active'] as $campo) {
                    $r[$campo] = $global[$campo] ?? $r[$campo] ?? null;
                }
            }
        }
    }
    unset($r);
}
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <?php
    $totalRegras = count($regras ?? []);
    $regrasAtivas = count(array_filter($regras ?? [], static fn($r) => !isset($r['active']) || $r['active']));
    $regrasZeradas = count(array_filter($regras ?? [], static fn($r) => (float)($r['base_fee'] ?? 0) <= 0 && (float)($r['minimum_price'] ?? 0) <= 0));
    ?>
    <div class="ops-topbar">
        <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="tarifasBusca" placeholder="Buscar por nome ou código do serviço" autocomplete="off" aria-label="Buscar tarifas"></div>
        <div class="ops-topbar__meta">
            <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalRegras; ?> tarifas</span>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/tipos" class="ops-dashboard-link"><i class="fas fa-arrow-left me-1"></i>Tipos de serviço</a>
        </div>
    </div>

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">Catálogo estruturado</span>
            <h1>Tarifas por tipo de serviço</h1>
            <p>Configure o preço de cada serviço novo (partida auxiliar, bateria, pneu, diagnóstico elétrico, mecânica, chaveiro, combustível). O reboque continua usando o cálculo oficial já em produção (TarifaService) — os campos abaixo não o substituem.</p>
        </div>
    </header>

    <section class="ops-summary mb-4" aria-label="Resumo de tarifas">
        <article class="ops-metric">
            <span class="ops-metric__label">Total</span>
            <strong class="ops-metric__value"><?php echo $totalRegras; ?></strong>
        </article>
        <article class="ops-metric">
            <span class="ops-metric__label">Ativas</span>
            <strong class="ops-metric__value"><?php echo $regrasAtivas; ?></strong>
        </article>
        <article class="ops-metric <?php echo $regrasZeradas > 0 ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Sem taxa base/mínimo definido</span>
            <strong class="ops-metric__value"><?php echo $regrasZeradas; ?></strong>
            <?php if ($regrasZeradas > 0): ?><span class="ops-metric__trend">Revisar preço</span><?php endif; ?>
        </article>
    </section>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($cidadesAtivas)): ?>
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <label class="form-label mb-0 fw-bold"><i class="fas fa-city me-1"></i>Editando tarifas de:</label>
            <select class="form-select w-auto" onchange="window.location.href = <?php echo json_encode($bp . '/admin/catalogo-servicos/tarifas'); ?> + (this.value ? '?cidade_id=' + this.value : '');">
                <option value="">Global (padrão de todas as cidades)</option>
                <?php foreach ($cidadesAtivas as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo $cidadeId === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nome'] . '/' . $c['uf']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($cidadeId !== null): ?>
            <span class="text-muted small"><i class="fas fa-circle-info me-1"></i>Linhas com <span class="badge text-bg-secondary">Herdando do global</span> ainda não têm tarifa própria nesta cidade — salvar cria o override; onde não houver definição, prevalece a tarifa global.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header fw-bold"><i class="fas fa-tags me-2"></i>Regras de tarifa</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th style="width:100px">Taxa base (R$)</th>
                            <th style="width:110px">R$/km (deslocamento)</th>
                            <th style="width:110px">R$/km (reboque)</th>
                            <th style="width:100px">Mão de obra (R$)</th>
                            <th style="width:100px">Mínimo (R$)</th>
                            <th style="width:90px">Mult. noturno</th>
                            <th style="width:90px">Mult. feriado</th>
                            <th style="width:70px">Ativa</th>
                            <?php if ($cidadeId !== null): ?><th style="width:140px">Origem</th><?php endif; ?>
                            <th style="width:100px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($regras)): ?>
                        <tr><td colspan="10" class="text-center p-4 text-muted small">Nenhum tipo de serviço ativo cadastrado ainda.</td></tr>
                        <?php else: foreach ($regras as $r):
                            $temOverride = $cidadeId !== null && !empty($r['_tem_override']);
                        ?>
                        <tr data-search="<?php echo htmlspecialchars(strtolower(($r['name'] ?? '') . ' ' . ($r['code'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($cidadeId !== null && !$temOverride) ? 'class="text-muted"' : ''; ?>>
                            <form method="post" action="<?php echo $bp; ?>/admin/catalogo-servicos/tarifa/salvar">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="service_type_id" value="<?php echo (int)$r['service_type_id']; ?>">
                                <?php if ($cidadeId !== null): ?><input type="hidden" name="cidade_id" value="<?php echo (int)$cidadeId; ?>"><?php endif; ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($r['name']); ?></strong><br>
                                    <code class="small text-muted"><?php echo htmlspecialchars($r['code']); ?></code>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="base_fee" value="<?php echo htmlspecialchars($r['base_fee'] ?? '0.00'); ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="pickup_km_price" value="<?php echo htmlspecialchars($r['pickup_km_price'] ?? '0.00'); ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="tow_km_price" placeholder="—" value="<?php echo $r['tow_km_price'] !== null ? htmlspecialchars((string)$r['tow_km_price']) : ''; ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="labor_fee" value="<?php echo htmlspecialchars($r['labor_fee'] ?? '0.00'); ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="minimum_price" value="<?php echo htmlspecialchars($r['minimum_price'] ?? '0.00'); ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="night_multiplier" value="<?php echo htmlspecialchars($r['night_multiplier'] ?? '1.00'); ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="holiday_multiplier" value="<?php echo htmlspecialchars($r['holiday_multiplier'] ?? '1.00'); ?>"></td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input" name="active" value="1" <?php echo (!isset($r['active']) || $r['active']) ? 'checked' : ''; ?>>
                                </td>
                                <?php if ($cidadeId !== null): ?>
                                <td>
                                    <?php if ($temOverride): ?>
                                    <span class="badge text-bg-success">Específica desta cidade</span>
                                    <?php else: ?>
                                    <span class="badge text-bg-secondary">Herdando do global</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="d-flex gap-1">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Salvar"><i class="fas fa-save"></i></button>
                                </td>
                            </form>
                        </tr>
                        <?php if ($cidadeId !== null && $temOverride): ?>
                        <tr>
                            <td colspan="11" class="pt-0 pb-2 border-0">
                                <form method="post" action="<?php echo $bp; ?>/admin/catalogo-servicos/tarifa/remover-override" class="d-inline" onsubmit="return confirm('Remover o override desta cidade? Volta a valer a tarifa global.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="service_type_id" value="<?php echo (int)$r['service_type_id']; ?>">
                                    <input type="hidden" name="cidade_id" value="<?php echo (int)$cidadeId; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-rotate-left me-1"></i>Remover override e voltar pro global</button>
                                </form>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="text-muted small mt-3">
        "R$/km (deslocamento)" é cobrado do ponto de partida do prestador até o cliente. "R$/km (reboque)" só se aplica a serviços que podem terminar em reboque (deixe em branco se não houver deslocamento de carga). O valor mínimo garante um piso mesmo com taxa base baixa. Os multiplicadores noturno/feriado incidem sobre taxa base + mão de obra.
    </p>

    <script<?php echo csp_script_nonce_attr(); ?>>
    (function () {
        var busca = document.getElementById('tarifasBusca');
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
