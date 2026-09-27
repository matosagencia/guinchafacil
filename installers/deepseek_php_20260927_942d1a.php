<?php
// install-oficina-v10.php — CSS fix + página pedidos + hints + tables + bank select
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];
function r(&$report, $tag, $msg) { $report[] = "[$tag] $msg"; }
function put($root, $rel, $content, &$report, $backupTag = 'v10') {
    $full = $root . '/' . $rel;
    if (!is_dir(dirname($full))) @mkdir(dirname($full), 0777, true);
    if (file_exists($full)) @copy($full, $full . ".bak-$backupTag-" . date('Ymd-His'));
    $bytes = @file_put_contents($full, $content);
    r($report, $bytes === false ? 'ERRO' : 'OK', "$rel ($bytes bytes)");
    return $bytes !== false;
}

// ═════════════════════════════════════════════════════════════════════
// 1. FIX CSS — override em body.guincho.oficina
// ═════════════════════════════════════════════════════════════════════
$css = <<<'CSSEOF'
/* ============================================================
   GuinchaFácil — Tema Oficina (âmbar escuro / marrom)
   Aplica-se ao body.guincho.oficina (dupla classe).
   O body recebe AMBAS as classes via JS, então:
   - Todos os seletores ".tow-*", ".app-*" (escopados em body.guincho)
     continuam funcionando
   - As variáveis --theme-* são sobrescritas por esta folha
   ============================================================ */

body.guincho.oficina {
  --theme-bg:           #1a0f08;
  --theme-surface:      #2a1810;
  --theme-surface-2:    #3a2318;
  --theme-border:       #5c3d28;
  --theme-text:         #fef3e8;
  --theme-muted:        #d4b8a0;
  --theme-accent:       #d97706;
  --theme-accent-hover: #b45309;
  --theme-on-accent:    #1a0f08;
  --theme-nav:          #140a05;
  --theme-sidebar:      #1f0f08;

  --surface-light-bg:    #fef9f3;
  --surface-light-text:  #2a1810;
  --surface-light-muted: #8b6f5c;

  --primary:        var(--theme-accent);
  --primary-hover:  var(--theme-accent-hover);
}

/* ─── Hint explicativo abaixo de cada stat card ─── */
body.guincho.oficina .tow-stat {
  position: relative;
  padding-bottom: 1.6rem;
}
body.guincho.oficina .tow-stat-hint {
  position: absolute;
  bottom: .35rem;
  left: .75rem;
  right: .75rem;
  font-size: .68rem;
  line-height: 1.2;
  color: var(--theme-muted);
  opacity: .8;
  text-align: center;
}

/* ─── Tabela de listas (histórico/financeiro) ─── */
body.guincho.oficina .ofi-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .88rem;
}
body.guincho.oficina .ofi-table thead th {
  background: var(--theme-surface-2);
  color: var(--theme-text);
  padding: .65rem .75rem;
  text-align: left;
  font-weight: 600;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  border-bottom: 1px solid var(--theme-border);
}
body.guincho.oficina .ofi-table tbody td {
  padding: .75rem;
  border-bottom: 1px solid var(--theme-border);
  color: var(--theme-text);
  vertical-align: middle;
}
body.guincho.oficina .ofi-table tbody tr:hover {
  background: var(--theme-surface-2);
}
body.guincho.oficina .ofi-table .badge {
  display: inline-block;
  padding: .2rem .55rem;
  border-radius: 999px;
  font-size: .72rem;
  font-weight: 600;
}
body.guincho.oficina .ofi-table .badge-ok { background: #166534; color: #dcfce7; }
body.guincho.oficina .ofi-table .badge-info { background: var(--theme-accent); color: var(--theme-on-accent); }
body.guincho.oficina .ofi-table .badge-warn { background: #92400e; color: #fef3c7; }
body.guincho.oficina .ofi-table .badge-off { background: #7c2d12; color: #fee2e2; }

/* ─── Filtros (barra de busca/select) ─── */
body.guincho.oficina .ofi-filter {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  align-items: flex-end;
  margin-bottom: 1rem;
}
body.guincho.oficina .ofi-filter label {
  font-size: .78rem;
  color: var(--theme-muted);
  display: block;
  margin-bottom: .2rem;
}
body.guincho.oficina .ofi-filter input,
body.guincho.oficina .ofi-filter select {
  min-height: 38px;
  border-radius: 8px;
  border: 1px solid var(--theme-border);
  background: var(--theme-surface-2);
  color: var(--theme-text);
  padding: .35rem .65rem;
  font-size: .88rem;
}
body.guincho.oficina .ofi-filter input:focus,
body.guincho.oficina .ofi-filter select:focus {
  outline: 0;
  border-color: var(--theme-accent);
  box-shadow: 0 0 0 3px rgba(217,119,6,.2);
}
body.guincho.oficina .ofi-empty {
  text-align: center;
  padding: 3rem 1rem;
  color: var(--theme-muted);
}
body.guincho.oficina .ofi-empty i { font-size: 2.5rem; display: block; margin-bottom: .75rem; opacity: .4; }
CSSEOF;
put($root, 'public/assets/css/themes/oficina.css', $css, $report);

// ═════════════════════════════════════════════════════════════════════
// 2. OficinaController — adicionar pedidosPage() + financeiro()
// ═════════════════════════════════════════════════════════════════════
$ctrlPath = $root . '/src/Controllers/OficinaController.php';
$ctrl = file_get_contents($ctrlPath);

if (strpos($ctrl, 'function pedidosPage') === false) {
    // Injeta método antes do último }
    $metodo = <<<'PHPEOF'

    /** Página HTML com todos os pedidos disponíveis (não é API). */
    public function pedidosPage(): void
    {
        $oficina = $this->getOficina();
        $pedidos = $this->buscarPedidosProximos($oficina);
        $csrfToken = AuthService::gerarCsrfToken();
        $filtroTipo = (string)($_GET['tipo'] ?? '');
        $filtroDist = (int)($_GET['dist'] ?? 0);
        require __DIR__ . '/../Views/oficina/pedidos.php';
    }

    /** Financeiro com filtro + tabela. */
    public function financeiroPage(): void
    {
        $oficina = $this->getOficina();
        $oficinaId = (int)$oficina['id'];

        $mes = (int)($_GET['mes'] ?? date('m'));
        $ano = (int)($_GET['ano'] ?? date('Y'));
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim    = date('Y-m-t', strtotime($inicio));

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT * FROM oficina_repasses
             WHERE oficina_id = ? AND criado_em BETWEEN ? AND ?
             ORDER BY criado_em DESC"
        );
        $stmt->execute([$oficinaId, $inicio . ' 00:00:00', $fim . ' 23:59:59']);
        $repasses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totais = ['bruto'=>0.0,'taxa'=>0.0,'liquido'=>0.0,'pago'=>0.0,'a_receber'=>0.0];
        foreach ($repasses as $r) {
            $totais['bruto']   += (float)$r['valor_bruto'];
            $totais['taxa']    += (float)$r['taxa_plataforma'];
            $totais['liquido'] += (float)$r['valor_liquido'];
            if ($r['status'] === 'pago')         $totais['pago']      += (float)$r['valor_liquido'];
            elseif ($r['status'] === 'pendente') $totais['a_receber'] += (float)$r['valor_liquido'];
        }

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/oficina/financeiro.php';
    }
PHPEOF;

    // Insere antes do último } do arquivo
    $pos = strrpos($ctrl, '}');
    $ctrl = substr($ctrl, 0, $pos) . $metodo . "\n}\n";
    file_put_contents($ctrlPath, $ctrl);
    r($report, 'OK', 'OficinaController: pedidosPage + financeiroPage adicionados');
} else {
    r($report, 'SKIP', 'OficinaController: métodos já existem');
}
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($ctrlPath) . ' 2>&1');
r($report, 'LINT', trim((string)$lint));

// ═════════════════════════════════════════════════════════════════════
// 3. View pedidos.php (nova)
// ═════════════════════════════════════════════════════════════════════
$pedidosView = <<<'PHPEOF'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$tipoLabels = [
    'pneu' => 'Pneu', 'eletrica' => 'Elétrica', 'bateria' => 'Bateria',
    'mecanica' => 'Mecânica', 'chaveiro' => 'Chaveiro', 'reboque' => 'Reboque',
];
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/components/dashboard.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-dashboard.css">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('guincho','oficina');});</script>

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
<main class="main-content">
    <div class="app-dashboard">

        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Ofertas</span>
                <h1>Pedidos disponíveis</h1>
                <p>Pedidos dentro do seu raio de atendimento, ordenados por distância.</p>
            </div>
        </header>

        <form method="get" class="ofi-filter">
            <div>
                <label for="f-tipo">Tipo de problema</label>
                <select name="tipo" id="f-tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipoLabels as $k => $v): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= ($filtroTipo === $k) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f-dist">Distância máx (km)</label>
                <select name="dist" id="f-dist">
                    <option value="0">Qualquer</option>
                    <?php foreach ([5,10,15,20,30] as $d): ?>
                        <option value="<?= $d ?>" <?= ($filtroDist === $d) ? 'selected' : '' ?>><?= $d ?> km</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-success" style="min-height:38px;padding:.35rem 1rem">Filtrar</button>
            <a href="<?= $bp ?>/oficina/pedidos" class="btn btn-outline-secondary" style="min-height:38px;padding:.35rem 1rem">Limpar</a>
        </form>

        <?php
        $pedidosFiltrados = array_filter($pedidos ?? [], function ($p) use ($filtroTipo, $filtroDist) {
            if ($filtroTipo !== '' && strtolower((string)($p['tipo_problema'] ?? '')) !== $filtroTipo) return false;
            if ($filtroDist > 0 && (float)($p['distancia_km'] ?? 0) > $filtroDist) return false;
            return true;
        });
        ?>

        <section class="tow-card p-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-inbox me-2"></i>Lista de pedidos</h3>
                    <p class="tow-panel-subtitle"><?= count($pedidosFiltrados) ?> de <?= count($pedidos ?? []) ?> pedidos</p>
                </div>
            </div>

            <?php if (empty($pedidosFiltrados)): ?>
                <div class="ofi-empty">
                    <i class="fas fa-satellite-dish"></i>
                    <p class="mb-0">Nenhum pedido disponível no momento.</p>
                    <p class="small mb-0">Ative o modo Online para receber ofertas.</p>
                </div>
            <?php else: ?>
                <table class="ofi-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tipo</th>
                            <th>Endereço</th>
                            <th>Distância</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidosFiltrados as $p): ?>
                            <tr>
                                <td><strong>#<?= (int)$p['id'] ?></strong></td>
                                <td><?= htmlspecialchars($tipoLabels[strtolower($p['tipo_problema'] ?? '')] ?? (string)($p['tipo_problema'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars(mb_substr((string)($p['endereco_origem'] ?? ''), 0, 60)) ?></td>
                                <td><?= number_format((float)($p['distancia_km'] ?? 0), 1, ',', '.') ?> km</td>
                                <td><span class="badge badge-info"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($p['status'] ?? '')))) ?></span></td>
                                <td class="text-end">
                                    <a href="<?= $bp ?>/oficina/pedido/<?= (int)$p['id'] ?>/aceitar" class="btn btn-sm btn-success">
                                        <i class="fas fa-check me-1"></i>Aceitar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </div>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
PHPEOF;
put($root, 'src/Views/oficina/pedidos.php', $pedidosView, $report);

// ═════════════════════════════════════════════════════════════════════
// 4. Dashboard com hints nos stat cards
// ═════════════════════════════════════════════════════════════════════
$dash = <<<'PHPEOF'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
function ofi_saudacao(): string { $h = (int)date('G'); if ($h < 12) return 'Bom dia'; if ($h < 18) return 'Boa tarde'; return 'Boa noite'; }
$nome = trim((string)($_SESSION['user']['nome'] ?? 'Oficina'));
$online = !empty($oficina['disponivel']);
$raio = (int)($oficina['raio_atendimento_km'] ?? 10);
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/components/dashboard.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-dashboard.css">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('guincho','oficina');});</script>

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
<main class="main-content">
    <div class="app-dashboard">

        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Painel operacional</span>
                <h1>Pronto para o próximo atendimento</h1>
                <p>Ofertas, atendimentos e status em tempo real.</p>
            </div>
        </header>

        <section class="tow-hero mb-4">
            <div class="tow-hero-info">
                <span class="tow-hero-eyebrow"><?= htmlspecialchars(ofi_saudacao()) ?></span>
                <h2 class="tow-hero-title"><?= htmlspecialchars($nome) ?></h2>
                <p class="tow-hero-subtitle">Raio de atendimento: <?= $raio ?> km</p>
            </div>
            <div class="tow-hero-switch">
                <span id="labelDisponivel"><?= $online ? 'Online' : 'Offline' ?></span>
                <label class="toggle-switch mb-0">
                    <input type="checkbox" id="toggleDisponivel" <?= $online ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/historico">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="tow-stat-value"><?= (int)$atendimentosHoje ?></div>
                        <div class="tow-stat-label">Atendimentos hoje</div>
                        <div class="tow-stat-hint">Concluídos hoje</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/pedidos">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-inbox"></i></div>
                        <div class="tow-stat-value"><?= count($pedidos) ?></div>
                        <div class="tow-stat-label">Pedidos próximos</div>
                        <div class="tow-stat-hint">Dentro do seu raio</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/perfil">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-route"></i></div>
                        <div class="tow-stat-value"><?= $raio ?> km</div>
                        <div class="tow-stat-label">Raio de cobertura</div>
                        <div class="tow-stat-hint">Ajuste no Perfil</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/financeiro">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-coins"></i></div>
                        <div class="tow-stat-value">R$ --</div>
                        <div class="tow-stat-label">A receber</div>
                        <div class="tow-stat-hint">Repasses pendentes</div>
                    </div>
                </a>
            </div>
        </div>

        <section class="tow-card p-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-bolt me-2"></i>Pedidos disponíveis</h3>
                    <p class="tow-panel-subtitle">Ordenados por distância até você</p>
                </div>
                <a href="<?= $bp ?>/oficina/pedidos" class="btn btn-sm btn-outline-light">Ver todos</a>
            </div>

            <?php if (empty($pedidos)): ?>
                <div class="ofi-empty">
                    <i class="fas fa-satellite-dish"></i>
                    <p class="mb-0">Nenhum pedido disponível no momento.</p>
                    <p class="small mb-0">Ative o modo Online para receber ofertas.</p>
                </div>
            <?php else: ?>
                <table class="ofi-table">
                    <thead>
                        <tr><th>#</th><th>Tipo</th><th>Endereço</th><th>Distância</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($pedidos, 0, 5) as $p): ?>
                            <tr>
                                <td>#<?= (int)$p['id'] ?></td>
                                <td><?= htmlspecialchars((string)($p['tipo_problema'] ?? '—')) ?></td>
                                <td><?= htmlspecialchars(mb_substr((string)($p['endereco_origem'] ?? ''), 0, 50)) ?></td>
                                <td><?= number_format((float)($p['distancia_km'] ?? 0), 1, ',', '.') ?> km</td>
                                <td class="text-end">
                                    <a href="<?= $bp ?>/oficina/pedido/<?= (int)$p['id'] ?>/aceitar" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </div>
</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    var BP = '<?= $bp ?>'; var CSRF = '<?= htmlspecialchars($csrfToken ?? '') ?>';
    var toggle = document.getElementById('toggleDisponivel');
    var label = document.getElementById('labelDisponivel');
    if (!toggle) return;
    toggle.addEventListener('change', async function(){
        var d = this.checked ? 1 : 0; this.disabled = true; label.textContent = 'Salvando...';
        try {
            var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('disponivel', d ? '1' : '0');
            var r = await fetch(BP + '/oficina/disponibilidade', { method:'POST', body:fd, headers:{Accept:'application/json'} });
            var j = await r.json();
            if (j.ok) label.textContent = j.disponivel ? 'Online' : 'Offline';
            else { this.checked = !d; label.textContent = !d ? 'Online' : 'Offline'; }
        } catch (e) { this.checked = !d; label.textContent = !d ? 'Online' : 'Offline'; }
        finally { this.disabled = false; }
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
PHPEOF;
put($root, 'src/Views/oficina/dashboard.php', $dash, $report);

// ═════════════════════════════════════════════════════════════════════
// 5. View perfil.php com bank select
// ═════════════════════════════════════════════════════════════════════
$perfilView = <<<'PHPEOF'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$bancos = [
    '001' => '001 — Banco do Brasil',
    '003' => '003 — Banco da Amazônia',
    '004' => '004 — Banco do Nordeste',
    '021' => '021 — Banestes',
    '033' => '033 — Santander',
    '036' => '036 — Banco Bradesco BBI',
    '037' => '037 — Banpará',
    '041' => '041 — Banrisul',
    '047' => '047 — Banese',
    '070' => '070 — BRB',
    '077' => '077 — Banco Inter',
    '104' => '104 — Caixa Econômica Federal',
    '136' => '136 — Unicred',
    '197' => '197 — Stone',
    '208' => '208 — BTG Pactual',
    '212' => '212 — Banco Original',
    '218' => '218 — Banco BS2',
    '224' => '224 — Banco Fibra',
    '237' => '237 — Bradesco',
    '246' => '246 — Banco ABC Brasil',
    '260' => '260 — Nubank',
    '290' => '290 — PagSeguro',
    '318' => '318 — Banco BMG',
    '323' => '323 — Mercado Pago',
    '336' => '336 — C6 Bank',
    '341' => '341 — Itaú Unibanco',
    '356' => '356 — Banco Real',
    '380' => '380 — PicPay',
    '389' => '389 — Banco Mercantil do Brasil',
    '399' => '399 — HSBC',
    '412' => '412 — Banco Capital',
    '422' => '422 — Banco Safra',
    '453' => '453 — Banco Rural',
    '633' => '633 — Banco Rendimento',
    '637' => '637 — Banco Sofisa',
    '655' => '655 — Banco Votorantim',
    '707' => '707 — Banco Daycoval',
    '745' => '745 — Citibank',
    '748' => '748 — Sicredi',
    '756' => '756 — Sicoob',
];

$servicosDisponiveis = [
    'borracharia'     => ['Pneu / borracharia',   'fa-circle-notch'],
    'eletrica'        => ['Elétrica automotiva',  'fa-bolt'],
    'bateria'         => ['Bateria / partida',    'fa-car-battery'],
    'mecanica'        => ['Mecânica geral',       'fa-gears'],
    'chaveiro'        => ['Chaveiro automotivo',  'fa-key'],
    'funilaria'       => ['Funilaria e pintura',  'fa-spray-can'],
    'ar_condicionado' => ['Ar-condicionado',      'fa-snowflake'],
    'injecao'         => ['Injeção eletrônica',   'fa-microchip'],
    'suspensao'       => ['Suspensão / freios',   'fa-arrows-up-down'],
];
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/components/dashboard.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-dashboard.css">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('guincho','oficina');});</script>

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
<main class="main-content">
    <div class="app-dashboard" style="max-width:960px">

        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Conta</span>
                <h1>Meu perfil</h1>
                <p>Dados operacionais, recebimento e serviços oferecidos.</p>
            </div>
        </header>

        <?php if (!empty($_SESSION['_flash'])): foreach ((array)$_SESSION['_flash'] as $f): unset($_SESSION['_flash']); ?>
            <div class="alert alert-<?= $f['type'] === 'success' ? 'success' : 'danger' ?> mb-3"><?= htmlspecialchars($f['message']) ?></div>
        <?php endforeach; endif; ?>

        <form method="POST" action="<?= $bp ?>/oficina/perfil/salvar" class="tow-card p-4 mb-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <h3 class="tow-panel-title mb-3"><i class="fas fa-store me-2"></i>Dados da oficina</h3>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars((string)$oficina['nome']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Telefone</label>
                    <input type="tel" name="telefone" class="form-control" value="<?= htmlspecialchars((string)($oficina['telefone'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small">Endereço completo</label>
                    <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars((string)$oficina['endereco']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Latitude</label>
                    <input type="text" name="latitude" id="latitude" class="form-control" value="<?= htmlspecialchars((string)($oficina['latitude'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Longitude</label>
                    <input type="text" name="longitude" id="longitude" class="form-control" value="<?= htmlspecialchars((string)($oficina['longitude'] ?? '')) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" id="btnUsarGps" class="btn btn-outline-light w-100"><i class="fas fa-location-crosshairs me-1"></i>Usar GPS</button>
                </div>
                <div class="col-12">
                    <label class="form-label small">Raio de atendimento: <strong id="raioLabel"><?= (int)($oficina['raio_atendimento_km'] ?? 10) ?> km</strong></label>
                    <input type="range" name="raio_atendimento_km" id="raioRange" class="form-range" min="1" max="100" step="1" value="<?= (int)($oficina['raio_atendimento_km'] ?? 10) ?>">
                </div>
            </div>

            <hr class="my-4">

            <h3 class="tow-panel-title mb-3"><i class="fas fa-pix me-2"></i>Recebimento (PIX)</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">Tipo da chave</label>
                    <select name="pix_tipo" class="form-select">
                        <option value="">— Selecione —</option>
                        <?php foreach (['cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'E-mail','telefone'=>'Telefone','aleatoria'=>'Chave aleatória'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($oficina['pix_tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Chave PIX</label>
                    <input type="text" name="pix_chave" class="form-control" value="<?= htmlspecialchars((string)($oficina['pix_chave'] ?? '')) ?>" placeholder="Ex: 21999998888">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Banco</label>
                    <select name="banco_codigo" class="form-select">
                        <option value="">— Selecione —</option>
                        <?php foreach ($bancos as $cod => $label): ?>
                            <option value="<?= htmlspecialchars($cod) ?>" <?= ($oficina['banco_codigo'] ?? '') === $cod ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Agência</label>
                    <input type="text" name="banco_agencia" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_agencia'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Conta (com dígito)</label>
                    <input type="text" name="banco_conta" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_conta'] ?? '')) ?>" placeholder="12345-6">
                </div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar dados</button>
            </div>
        </form>

        <form method="POST" action="<?= $bp ?>/oficina/servicos/salvar" class="tow-card p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <h3 class="tow-panel-title mb-1">Serviços oferecidos</h3>
            <p class="tow-panel-subtitle mb-3">Marque tudo que sua oficina faz.</p>
            <div class="row g-2">
                <?php foreach ($servicosDisponiveis as $slug => [$label, $icone]): ?>
                    <div class="col-md-4 col-sm-6">
                        <label class="d-flex align-items-center gap-2 p-3" style="border:1px solid var(--theme-border);border-radius:10px;cursor:pointer">
                            <input type="checkbox" name="servicos[]" value="<?= $slug ?>" <?= in_array($slug, $servicosAtuais, true) ? 'checked' : '' ?>>
                            <i class="fas <?= $icone ?>" style="color:var(--theme-accent)"></i>
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-end mt-4">
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar serviços</button>
            </div>
        </form>

    </div>
</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    var r = document.getElementById('raioRange'); var l = document.getElementById('raioLabel');
    if (r) r.addEventListener('input', function(){ l.textContent = r.value + ' km'; });
    var gps = document.getElementById('btnUsarGps');
    if (gps) gps.addEventListener('click', function(){
        if (!navigator.geolocation) return alert('GPS indisponível.');
        navigator.geolocation.getCurrentPosition(function(p){
            document.getElementById('latitude').value  = p.coords.latitude.toFixed(7);
            document.getElementById('longitude').value = p.coords.longitude.toFixed(7);
        }, function(){ alert('Sem localização.'); });
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
PHPEOF;
put($root, 'src/Views/oficina/perfil.php', $perfilView, $report);

// ═════════════════════════════════════════════════════════════════════
// 6. Rotas — trocar pedidosDisponiveis (JSON) por pedidosPage (HTML)
// ═════════════════════════════════════════════════════════════════════
$indexPath = $root . '/index.php';
$index = file_get_contents($indexPath);
@copy($indexPath, $indexPath . '.bak-v10-' . date('Ymd-His'));

$old = "        '/oficina/pedidos'               => ['OficinaController', 'pedidosDisponiveis',  'oficina'],";
$new = "        '/oficina/pedidos'               => ['OficinaController', 'pedidosPage',         'oficina'],";

if (strpos($index, $old) !== false) {
    $index = str_replace($old, $new, $index);
    @file_put_contents($indexPath, $index);
    r($report, 'OK', 'index.php: rota /oficina/pedidos → pedidosPage (HTML)');
} else {
    r($report, 'AVISO', 'Não achei a rota exata — verificar manualmente');
}

// ═════════════════════════════════════════════════════════════════════
// 7. Diagnóstico
// ═════════════════════════════════════════════════════════════════════
r($report, '', '── DIAGNÓSTICO ──');
try {
    require_once $root . '/config.php';
    $t1 = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1")->fetchColumn();
    r($report, 'OK', "oficinas ativas: $t1");
} catch (Throwable $e) {
    r($report, 'AVISO', $e->getMessage());
}

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Layout v10</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🎨 Installer v10 — CSS + página pedidos + hints + tables + bank select</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-oficina-v10.php</p>
</body></html>