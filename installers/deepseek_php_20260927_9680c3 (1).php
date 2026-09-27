<?php
// install-oficina-v11.php — palette + oferta + validators + rota
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];
function r(&$report, $tag, $msg) { $report[] = "[$tag] $msg"; }
function put($root, $rel, $content, &$report) {
    $full = $root . '/' . $rel;
    if (!is_dir(dirname($full))) @mkdir(dirname($full), 0777, true);
    if (file_exists($full)) @copy($full, $full . '.bak-v11-' . date('Ymd-His'));
    $bytes = @file_put_contents($full, $content);
    r($report, $bytes === false ? 'ERRO' : 'OK', "$rel ($bytes bytes)");
}

// ═════════════════════════════════════════════════════════════════════
// 0. DIAGNÓSTICO — ver estado atual das rotas
// ═════════════════════════════════════════════════════════════════════
$indexPath = $root . '/index.php';
$index = file_get_contents($indexPath);
$report[] = "── DIAGNÓSTICO PRÉVIO ──";

preg_match_all("#/oficina/pedidos['\"]\s*=>\s*\[[^\]]*\]#", $index, $m);
$report[] = "Rotas encontradas: " . count($m[0]);
foreach ($m[0] as $rota) $report[] = "  $rota";

// ═════════════════════════════════════════════════════════════════════
// 1. themes/oficina.css — palette agressiva, no green
// ═════════════════════════════════════════════════════════════════════
$css = <<<'CSSEOF'
/* ============================================================
   GuinchaFácil — Tema OFICINA (âmbar escuro / marrom)
   Override AGRESSIVO de themes/tow.css + style.css
   Paleta: só marrom, âmbar, laranja, vermelho-terra, amarelo.
   Verde apenas na logo (externa).
   ============================================================ */

/* ─── Vars — seletor com alta especificidade pra ganhar do tow.css ─── */
html body.guincho.oficina,
html body.oficina.guincho,
html body.oficina {
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

  --primary:        #d97706;
  --primary-hover:  #b45309;
  --bs-success:     #d97706;
  --bs-success-rgb: 217,119,6;
}

/* ─── Fundo geral ─── */
html body.oficina,
html body.oficina .main-content,
html body.oficina .app-dashboard {
  background: #1a0f08 !important;
  color: #fef3e8 !important;
}

/* ─── Sidebar — marrom escuro ─── */
html body.oficina .sidebar {
  background: #1f0f08 !important;
  border-right: 1px solid #3a2318;
}
html body.oficina .sidebar .sidebar-title {
  color: #8b6f5c !important;
}
html body.oficina .sidebar .sidebar-link {
  color: #fef3e8 !important;
  border-radius: 8px;
}
html body.oficina .sidebar .sidebar-link:hover {
  background: rgba(217,119,6,.15) !important;
  color: #d97706 !important;
}
html body.oficina .sidebar .sidebar-link.active {
  background: #d97706 !important;
  color: #1a0f08 !important;
  font-weight: 700;
}
html body.oficina .sidebar .sidebar-link.active i {
  color: #1a0f08 !important;
}

/* ─── Badge "OFICINA" no header — âmbar ─── */
html body.oficina .gf-role-badge,
html body.oficina .role-badge,
html body.oficina .badge-role,
html body.oficina header .badge {
  background: #d97706 !important;
  color: #1a0f08 !important;
  font-weight: 700;
}

/* ─── Botões success — viram âmbar ─── */
html body.oficina .btn-success,
html body.oficina .btn.btn-success {
  background: #d97706 !important;
  border-color: #d97706 !important;
  color: #1a0f08 !important;
  font-weight: 600;
}
html body.oficina .btn-success:hover,
html body.oficina .btn.btn-success:hover {
  background: #b45309 !important;
  border-color: #b45309 !important;
  color: #fef3e8 !important;
}
html body.oficina .btn-outline-success {
  color: #d97706 !important;
  border-color: #d97706 !important;
}
html body.oficina .btn-outline-success:hover {
  background: #d97706 !important;
  color: #1a0f08 !important;
}

/* ─── Inputs / forms — fundo marrom ─── */
html body.oficina .form-control,
html body.oficina .form-select,
html body.oficina input[type="text"],
html body.oficina input[type="tel"],
html body.oficina input[type="email"],
html body.oficina input[type="number"],
html body.oficina textarea,
html body.oficina select {
  background: #3a2318 !important;
  border: 1px solid #5c3d28 !important;
  color: #fef3e8 !important;
}
html body.oficina .form-control:focus,
html body.oficina .form-select:focus {
  background: #3a2318 !important;
  border-color: #d97706 !important;
  box-shadow: 0 0 0 3px rgba(217,119,6,.2) !important;
  color: #fef3e8 !important;
}
html body.oficina .form-control::placeholder { color: #8b6f5c; }
html body.oficina .form-label,
html body.oficina label { color: #d4b8a0; }
html body.oficina .form-range::-webkit-slider-thumb { background: #d97706; }

/* ─── Cards tow — fundo marrom, não preto ─── */
html body.oficina .tow-card {
  background: #2a1810 !important;
  border: 1px solid #3a2318 !important;
  border-radius: 12px;
}
html body.oficina .tow-panel-title { color: #fef3e8; }
html body.oficina .tow-panel-subtitle { color: #d4b8a0; }

/* ─── Hero ─── */
html body.oficina .tow-hero {
  background: linear-gradient(135deg, #2a1810 0%, #3a2318 100%) !important;
  border: 1px solid #5c3d28 !important;
  border-radius: 14px;
}
html body.oficina .tow-hero-title { color: #fef3e8 !important; }
html body.oficina .tow-hero-eyebrow { color: #d97706 !important; }
html body.oficina .tow-hero-subtitle { color: #d4b8a0 !important; }

/* ─── Toggle online/offline — âmbar quando ativo ─── */
html body.oficina .toggle-switch input:checked + .toggle-slider,
html body.oficina .toggle-slider::before { /* fallback */ }
html body.oficina .toggle-switch input:checked ~ .toggle-slider {
  background: #d97706 !important;
}

/* ─── Stat cards — fundo âmbar destaque + hint ─── */
html body.oficina .tow-stat {
  background: linear-gradient(135deg, #d97706 0%, #b45309 100%) !important;
  color: #1a0f08 !important;
  border: none !important;
  border-radius: 12px;
  position: relative;
  padding-bottom: 1.8rem;
  box-shadow: 0 4px 14px rgba(217,119,6,.25);
  transition: transform .15s ease;
}
html body.oficina .tow-stat:hover { transform: translateY(-2px); }
html body.oficina .tow-stat-value {
  color: #1a0f08 !important;
  font-weight: 800;
}
html body.oficina .tow-stat-label {
  color: rgba(26,15,8,.75) !important;
  font-weight: 600;
}
html body.oficina .tow-stat-icon {
  color: #1a0f08 !important;
}
html body.oficina .tow-stat-hint {
  position: absolute;
  bottom: .35rem;
  left: .75rem;
  right: .75rem;
  font-size: .68rem;
  line-height: 1.2;
  color: rgba(26,15,8,.6);
  text-align: center;
  font-style: italic;
}

/* ─── Tabelas ─── */
html body.oficina .ofi-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .88rem;
}
html body.oficina .ofi-table thead th {
  background: #3a2318 !important;
  color: #fef3e8 !important;
  padding: .65rem .75rem;
  text-align: left;
  font-weight: 600;
  font-size: .76rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  border-bottom: 1px solid #5c3d28;
}
html body.oficina .ofi-table tbody td {
  padding: .75rem;
  border-bottom: 1px solid #3a2318;
  color: #fef3e8;
  vertical-align: middle;
}
html body.oficina .ofi-table tbody tr:hover { background: rgba(217,119,6,.08); }

/* ─── Badges de status ─── */
html body.oficina .badge-info { background: #d97706 !important; color: #1a0f08 !important; }
html body.oficina .badge-ok { background: #166534 !important; color: #dcfce7 !important; }
html body.oficina .badge-warn { background: #92400e !important; color: #fef3c7 !important; }
html body.oficina .badge-off { background: #7c2d12 !important; color: #fee2e2 !important; }

/* ─── Filtros ─── */
html body.oficina .ofi-filter {
  display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; margin-bottom: 1rem;
}
html body.oficina .ofi-filter input,
html body.oficina .ofi-filter select {
  min-height: 38px; border-radius: 8px; border: 1px solid #5c3d28;
  background: #3a2318; color: #fef3e8; padding: .35rem .65rem;
}

/* ─── Empty state ─── */
html body.oficina .ofi-empty {
  text-align: center; padding: 3rem 1rem; color: #d4b8a0;
}
html body.oficina .ofi-empty i {
  font-size: 2.5rem; display: block; margin-bottom: .75rem; opacity: .5;
}

/* ─── Offer card ─── */
html body.oficina .tow-offer {
  background: #fef9f3 !important;
  color: #2a1810 !important;
  border: 1px solid #d97706 !important;
  border-radius: 14px;
  padding: 1rem 1.15rem;
  margin-bottom: 1rem;
  box-shadow: 0 6px 20px rgba(217,119,6,.2);
}
html body.oficina .tow-offer .tow-offer-eyebrow { color: #b45309 !important; font-weight: 700; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; }
html body.oficina .tow-offer .tow-offer-title { color: #2a1810 !important; margin: .2rem 0 .1rem; font-weight: 800; }
html body.oficina .tow-offer .tow-offer-subtitle { color: #8b6f5c !important; font-size: .85rem; }
html body.oficina .tow-offer .tow-offer-timer {
  background: #d97706; color: #fff; padding: .3rem .65rem; border-radius: 999px;
  font-family: monospace; font-weight: 700; font-size: .85rem;
}
html body.oficina .tow-offer .tow-offer-metrics { display: flex; gap: 1rem; margin: .75rem 0; flex-wrap: wrap; }
html body.oficina .tow-offer .tow-offer-metric {
  flex: 1 1 100px; background: rgba(217,119,6,.08); border-radius: 8px; padding: .5rem .7rem;
}
html body.oficina .tow-offer .tow-offer-metric span { display: block; font-size: .7rem; color: #8b6f5c; text-transform: uppercase; }
html body.oficina .tow-offer .tow-offer-metric strong { color: #2a1810; font-size: 1rem; }
html body.oficina .tow-offer .tow-offer-actions { display: flex; gap: .5rem; margin-top: .75rem; }
html body.oficina .tow-offer .tow-offer-actions .btn-outline-secondary {
  background: transparent !important; color: #8b6f5c !important; border: 1px solid #8b6f5c !important;
}
html body.oficina .tow-offer .tow-offer-actions .btn-outline-secondary:hover {
  background: #8b6f5c !important; color: #fef9f3 !important;
}
CSSEOF;
put($root, 'public/assets/css/themes/oficina.css', $css, $report);

// ═════════════════════════════════════════════════════════════════════
// 2. Partial — _offer_card.php (reutilizável)
// ═════════════════════════════════════════════════════════════════════
$offer = <<<'PHPEOF'
<?php
// src/Views/oficina/_offer_card.php
// Espera: $p (pedido), $bp, $csrfToken
?>
<div class="tow-offer" data-pedido-id="<?= (int)$p['id'] ?>">
  <div class="tow-offer-head d-flex justify-content-between align-items-start">
    <div>
      <span class="tow-offer-eyebrow"><i class="fas fa-bolt me-1"></i>Nova solicitação</span>
      <h4 class="tow-offer-title">Pedido #<?= (int)$p['id'] ?></h4>
      <p class="tow-offer-subtitle mb-0">
        <?= htmlspecialchars((string)($p['tipo_problema'] ?? 'Socorro')) ?> ·
        <?= htmlspecialchars(mb_substr((string)($p['endereco_origem'] ?? ''), 0, 60)) ?>
      </p>
    </div>
    <?php if (!empty($p['expira_em'])): ?>
      <span class="tow-offer-timer" data-expira="<?= htmlspecialchars((string)$p['expira_em']) ?>">--:--</span>
    <?php endif; ?>
  </div>

  <div class="tow-offer-metrics">
    <div class="tow-offer-metric">
      <span>Distância</span>
      <strong><?= number_format((float)($p['distancia_km'] ?? 0), 1, ',', '.') ?> km</strong>
    </div>
    <div class="tow-offer-metric">
      <span>Valor estimado</span>
      <strong>R$ <?= number_format((float)($p['custo_estimado'] ?? 0), 2, ',', '.') ?></strong>
    </div>
    <div class="tow-offer-metric">
      <span>Categoria</span>
      <strong><?= htmlspecialchars((string)($p['categoria'] ?? '—')) ?></strong>
    </div>
  </div>

  <div class="tow-offer-actions">
    <form method="post" action="<?= $bp ?>/oficina/recusar/<?= (int)$p['id'] ?>" class="flex-grow-1 m-0">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <button type="submit" class="btn btn-outline-secondary w-100">
        <i class="fas fa-xmark me-1"></i>Recusar
      </button>
    </form>
    <a href="<?= $bp ?>/oficina/pedido/<?= (int)$p['id'] ?>/aceitar" class="btn btn-success flex-grow-1">
      <i class="fas fa-check me-1"></i>Aceitar
    </a>
  </div>
</div>
PHPEOF;
put($root, 'src/Views/oficina/_offer_card.php', $offer, $report);

// ═════════════════════════════════════════════════════════════════════
// 3. OficinaController — adicionar recusar() + garantir pedidosPage()
// ═════════════════════════════════════════════════════════════════════
$ctrlPath = $root . '/src/Controllers/OficinaController.php';
$ctrl = file_get_contents($ctrlPath);
@copy($ctrlPath, $ctrlPath . '.bak-v11-' . date('Ymd-His'));

// 3a. Adicionar recusar() se não existir
if (strpos($ctrl, 'function recusar') === false) {
    $metodoRecusar = <<<'PHPEOF'

    /** Recusa um pedido (adiciona a uma lista de skip por sessão). */
    public function recusar(int $id): void
    {
        $oficina = $this->getOficina();
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->redirect('/oficina/dashboard');
        }
        if (!isset($_SESSION['oficina_recusados']) || !is_array($_SESSION['oficina_recusados'])) {
            $_SESSION['oficina_recusados'] = [];
        }
        $_SESSION['oficina_recusados'][(int)$id] = time();
        // Expira lista em 1h
        foreach ($_SESSION['oficina_recusados'] as $pid => $ts) {
            if (time() - $ts > 3600) unset($_SESSION['oficina_recusados'][$pid]);
        }
        $this->setFlashMessage('Pedido recusado.', 'info');
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/oficina/dashboard');
    }

    /** Página HTML com todos os pedidos disponíveis. */
    public function pedidosPage(): void
    {
        $oficina = $this->getOficina();
        $todos = $this->buscarPedidosProximos($oficina);
        $recusados = $_SESSION['oficina_recusados'] ?? [];
        $pedidos = array_values(array_filter($todos, fn($p) => !isset($recusados[(int)$p['id']])));
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/oficina/pedidos.php';
    }
PHPEOF;

    $pos = strrpos($ctrl, '}');
    $ctrl = substr($ctrl, 0, $pos) . $metodoRecusar . "\n}\n";
    file_put_contents($ctrlPath, $ctrl);
    r($report, 'OK', 'OficinaController: recusar() + pedidosPage() adicionados');
} else {
    // Já existe recusar; só garante pedidosPage
    if (strpos($ctrl, 'function pedidosPage') === false) {
        $metodo = <<<'PHPEOF'

    public function pedidosPage(): void
    {
        $oficina = $this->getOficina();
        $todos = $this->buscarPedidosProximos($oficina);
        $recusados = $_SESSION['oficina_recusados'] ?? [];
        $pedidos = array_values(array_filter($todos, fn($p) => !isset($recusados[(int)$p['id']])));
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/oficina/pedidos.php';
    }
PHPEOF;
        $pos = strrpos($ctrl, '}');
        $ctrl = substr($ctrl, 0, $pos) . $metodo . "\n}\n";
        file_put_contents($ctrlPath, $ctrl);
        r($report, 'OK', 'OficinaController: pedidosPage() adicionado');
    } else {
        r($report, 'SKIP', 'OficinaController: métodos já existem');
    }
}
r($report, 'LINT', trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($ctrlPath) . ' 2>&1')));

// ═════════════════════════════════════════════════════════════════════
// 4. Dashboard — com offer card + polling
// ═════════════════════════════════════════════════════════════════════
$dash = <<<'PHPEOF'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
function ofi_saudacao(): string { $h = (int)date('G'); if ($h < 12) return 'Bom dia'; if ($h < 18) return 'Boa tarde'; return 'Boa noite'; }
$nome = trim((string)($_SESSION['user']['nome'] ?? 'Oficina'));
$online = !empty($oficina['disponivel']);
$raio = (int)($oficina['raio_atendimento_km'] ?? 10);
$recusados = $_SESSION['oficina_recusados'] ?? [];
$pedidosVisiveis = array_values(array_filter($pedidos ?? [], fn($p) => !isset($recusados[(int)$p['id']])));
$primeiroPedido = $pedidosVisiveis[0] ?? null;
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
                        <div class="tow-stat-hint">Concluídos no dia</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/pedidos">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-inbox"></i></div>
                        <div class="tow-stat-value"><?= count($pedidosVisiveis) ?></div>
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
                    <h3 class="tow-panel-title"><i class="fas fa-bolt me-2"></i>Nova solicitação</h3>
                    <p class="tow-panel-subtitle">Aceite antes que outro prestador receba</p>
                </div>
                <a href="<?= $bp ?>/oficina/pedidos" class="btn btn-sm btn-outline-success">Ver todos</a>
            </div>

            <div id="ofertaAtivaContainer">
                <?php if ($primeiroPedido): ?>
                    <?php $p = $primeiroPedido; include __DIR__ . '/_offer_card.php'; ?>
                <?php else: ?>
                    <div class="ofi-empty">
                        <i class="fas fa-satellite-dish"></i>
                        <p class="mb-0">Aguardando novas solicitações...</p>
                        <p class="small mb-0"><?= $online ? 'Você está Online.' : 'Ative o modo Online para receber ofertas.' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </div>
</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    var BP = '<?= $bp ?>';
    var CSRF = '<?= htmlspecialchars($csrfToken ?? '') ?>';
    var ONLINE = <?= $online ? 'true' : 'false' ?>;

    var toggle = document.getElementById('toggleDisponivel');
    var label = document.getElementById('labelDisponivel');

    function iniciarTimer() {
        var t = document.getElementById('ofertaTimer');
        if (!t || !t.dataset.expira) return;
        var exp = new Date(t.dataset.expira.replace(' ', 'T')).getTime();
        function tick() {
            var el = document.getElementById('ofertaTimer');
            if (!el) return;
            var s = Math.max(0, Math.floor((exp - Date.now())/1000));
            if (s === 0) { el.textContent = '00:00'; return; }
            el.textContent = String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');
            setTimeout(tick, 1000);
        }
        tick();
    }

    async function checarPedidos() {
        if (!ONLINE) return;
        try {
            var r = await fetch(BP + '/oficina/pedidos?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            // pedidos-disponiveis é JSON; /oficina/pedidos é HTML.
            var r2 = await fetch(BP + '/oficina/pedidos-disponiveis?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            var j = await r2.json();
            var container = document.getElementById('ofertaAtivaContainer');
            if (!container || !j.ok) return;
            if (j.pedidos && j.pedidos.length) {
                // Recarrega a página se houver pedido novo diferente
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(j.pedidos[0].id) !== String(atualId)) {
                    location.reload();
                }
            }
        } catch (e) { /* silencioso */ }
    }

    if (toggle) {
        toggle.addEventListener('change', async function(){
            var d = this.checked ? 1 : 0;
            this.disabled = true; label.textContent = 'Salvando...';
            try {
                var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('disponivel', d ? '1':'0');
                var r = await fetch(BP + '/oficina/disponibilidade', { method:'POST', body:fd, headers:{Accept:'application/json'} });
                var j = await r.json();
                if (j.ok) { label.textContent = j.disponivel ? 'Online':'Offline'; ONLINE = !!j.disponivel; }
                else { this.checked = !d; label.textContent = !d ? 'Online':'Offline'; }
            } catch(e) { this.checked = !d; label.textContent = !d ? 'Online':'Offline'; }
            finally { this.disabled = false; }
        });
    }

    iniciarTimer();
    setInterval(checarPedidos, 15000);
    checarPedidos();
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
PHPEOF;
put($root, 'src/Views/oficina/dashboard.php', $dash, $report);

// ═════════════════════════════════════════════════════════════════════
// 5. pedidos.php — mesmo card + filtros
// ═════════════════════════════════════════════════════════════════════
$pedidos = <<<'PHPEOF'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$tipoLabels = [
    'pneu'=>'Pneu','eletrica'=>'Elétrica','bateria'=>'Bateria',
    'mecanica'=>'Mecânica','chaveiro'=>'Chaveiro','reboque'=>'Reboque',
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
                <p>Pedidos dentro do seu raio, ordenados por distância.</p>
            </div>
        </header>

        <section class="tow-card p-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-inbox me-2"></i>Lista de pedidos</h3>
                    <p class="tow-panel-subtitle"><?= count($pedidos ?? []) ?> pedido(s)</p>
                </div>
            </div>

            <?php if (empty($pedidos)): ?>
                <div class="ofi-empty">
                    <i class="fas fa-satellite-dish"></i>
                    <p class="mb-0">Nenhum pedido disponível agora.</p>
                    <p class="small mb-0">Assim que aparecer, você vê aqui pra aceitar em 1 clique.</p>
                </div>
            <?php else: foreach ($pedidos as $p): ?>
                <?php include __DIR__ . '/_offer_card.php'; ?>
            <?php endforeach; endif; ?>
        </section>

    </div>
</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    var BP = '<?= $bp ?>';
    function iniciarTimers() {
        document.querySelectorAll('.tow-offer-timer').forEach(function(t){
            if (!t.dataset.expira) return;
            var exp = new Date(t.dataset.expira.replace(' ','T')).getTime();
            function tick() {
                var s = Math.max(0, Math.floor((exp - Date.now())/1000));
                if (s === 0) { t.textContent = '00:00'; return; }
                t.textContent = String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');
                setTimeout(tick, 1000);
            }
            tick();
        });
    }
    iniciarTimers();
    setInterval(function(){ location.reload(); }, 30000);
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
PHPEOF;
put($root, 'src/Views/oficina/pedidos.php', $pedidos, $report);

// ═════════════════════════════════════════════════════════════════════
// 6. perfil.php — validators + bank select
// ═════════════════════════════════════════════════════════════════════
$perfil = <<<'PHPEOF'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$bancos = [
    '001'=>'001 — Banco do Brasil','003'=>'003 — Banco da Amazônia','004'=>'004 — Banco do Nordeste',
    '021'=>'021 — Banestes','033'=>'033 — Santander','036'=>'036 — Bradesco BBI','037'=>'037 — Banpará',
    '041'=>'041 — Banrisul','047'=>'047 — Banese','070'=>'070 — BRB','077'=>'077 — Banco Inter',
    '104'=>'104 — Caixa Econômica Federal','136'=>'136 — Unicred','197'=>'197 — Stone',
    '208'=>'208 — BTG Pactual','212'=>'212 — Banco Original','218'=>'218 — BS2','224'=>'224 — Banco Fibra',
    '237'=>'237 — Bradesco','246'=>'246 — Banco ABC Brasil','260'=>'260 — Nubank','290'=>'290 — PagSeguro',
    '318'=>'318 — Banco BMG','323'=>'323 — Mercado Pago','336'=>'336 — C6 Bank','341'=>'341 — Itaú Unibanco',
    '356'=>'356 — Banco Real','380'=>'380 — PicPay','389'=>'389 — Banco Mercantil','399'=>'399 — HSBC',
    '412'=>'412 — Banco Capital','422'=>'422 — Banco Safra','453'=>'453 — Banco Rural',
    '633'=>'633 — Banco Rendimento','637'=>'637 — Banco Sofisa','655'=>'655 — Banco Votorantim',
    '707'=>'707 — Banco Daycoval','745'=>'745 — Citibank','748'=>'748 — Sicredi','756'=>'756 — Sicoob',
];

$servicosDisponiveis = [
    'borracharia'=>['Pneu / borracharia','fa-circle-notch'],'eletrica'=>['Elétrica automotiva','fa-bolt'],
    'bateria'=>['Bateria / partida','fa-car-battery'],'mecanica'=>['Mecânica geral','fa-gears'],
    'chaveiro'=>['Chaveiro automotivo','fa-key'],'funilaria'=>['Funilaria e pintura','fa-spray-can'],
    'ar_condicionado'=>['Ar-condicionado','fa-snowflake'],'injecao'=>['Injeção eletrônica','fa-microchip'],
    'suspensao'=>['Suspensão / freios','fa-arrows-up-down'],
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

        <form method="POST" action="<?= $bp ?>/oficina/perfil/salvar" class="tow-card p-4 mb-4" id="formPerfil">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <h3 class="tow-panel-title mb-3"><i class="fas fa-store me-2"></i>Dados da oficina</h3>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label small">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars((string)$oficina['nome']) ?>" required></div>
                <div class="col-md-4"><label class="form-label small">Telefone</label>
                    <input type="tel" name="telefone" id="inpTel" class="form-control" value="<?= htmlspecialchars((string)($oficina['telefone'] ?? '')) ?>"></div>
                <div class="col-12"><label class="form-label small">Endereço completo</label>
                    <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars((string)$oficina['endereco']) ?>" required></div>
                <div class="col-md-4"><label class="form-label small">Latitude</label>
                    <input type="text" name="latitude" id="latitude" class="form-control" value="<?= htmlspecialchars((string)($oficina['latitude'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label small">Longitude</label>
                    <input type="text" name="longitude" id="longitude" class="form-control" value="<?= htmlspecialchars((string)($oficina['longitude'] ?? '')) ?>"></div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" id="btnUsarGps" class="btn btn-outline-success w-100"><i class="fas fa-location-crosshairs me-1"></i>Usar GPS</button></div>
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
                    <select name="pix_tipo" id="pixTipo" class="form-select">
                        <option value="">— Selecione —</option>
                        <?php foreach (['cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'E-mail','telefone'=>'Telefone','aleatoria'=>'Chave aleatória'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($oficina['pix_tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Chave PIX</label>
                    <input type="text" name="pix_chave" id="pixChave" class="form-control" value="<?= htmlspecialchars((string)($oficina['pix_chave'] ?? '')) ?>">
                    <div class="invalid-feedback d-block" id="pixFeedback" style="font-size:.78rem;min-height:1em"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Banco</label>
                    <input type="text" name="banco_codigo" id="bancoBusca" class="form-control" list="listaBancos"
                           value="<?= htmlspecialchars((string)($oficina['banco_codigo'] ?? '')) ?>" placeholder="Digite o código ou nome">
                    <datalist id="listaBancos">
                        <?php foreach ($bancos as $cod => $label): ?>
                            <option value="<?= htmlspecialchars($cod) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-4"><label class="form-label small">Agência</label>
                    <input type="text" name="banco_agencia" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_agencia'] ?? '')) ?>"></div>
                <div class="col-md-4"><label class="form-label small">Conta (com dígito)</label>
                    <input type="text" name="banco_conta" class="form-control" value="<?= htmlspecialchars((string)($oficina['banco_conta'] ?? '')) ?>" placeholder="12345-6"></div>
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
                        <label class="d-flex align-items-center gap-2 p-3" style="border:1px solid #5c3d28;border-radius:10px;cursor:pointer;background:#3a2318">
                            <input type="checkbox" name="servicos[]" value="<?= $slug ?>" <?= in_array($slug, $servicosAtuais, true) ? 'checked':'' ?>>
                            <i class="fas <?= $icone ?>" style="color:#d97706"></i>
                            <span style="color:#fef3e8"><?= htmlspecialchars($label) ?></span>
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
    document.getElementById('btnUsarGps')?.addEventListener('click', function(){
        if (!navigator.geolocation) return alert('GPS indisponível.');
        navigator.geolocation.getCurrentPosition(function(p){
            document.getElementById('latitude').value  = p.coords.latitude.toFixed(7);
            document.getElementById('longitude').value = p.coords.longitude.toFixed(7);
        }, function(){ alert('Sem localização.'); });
    });

    // ─── Validador de PIX ───
    var pixTipo  = document.getElementById('pixTipo');
    var pixChave = document.getElementById('pixChave');
    var pixFb    = document.getElementById('pixFeedback');

    function soDigitos(s) { return (s||'').replace(/\D/g,''); }
    function validaCPF(c){
        c = soDigitos(c); if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false;
        var s=0; for (var i=0;i<9;i++) s+=parseInt(c[i])*(10-i);
        var d1=(s*10)%11; if (d1===10) d1=0; if (d1!=c[9]) return false;
        s=0; for (i=0;i<10;i++) s+=parseInt(c[i])*(11-i);
        var d2=(s*10)%11; if (d2===10) d2=0; return d2==c[10];
    }
    function validaCNPJ(c){
        c = soDigitos(c); if (c.length !== 14) return false;
        var b=[5,4,3,2,9,8,7,6,5,4,3,2], s=0;
        for (var i=0;i<12;i++) s+=parseInt(c[i])*b[i];
        var d1=s%11<2?0:11-(s%11); if (d1!=c[12]) return false;
        b=[6,5,4,3,2,9,8,7,6,5,4,3,2]; s=0;
        for (i=0;i<13;i++) s+=parseInt(c[i])*b[i];
        var d2=s%11<2?0:11-(s%11); return d2==c[13];
    }
    function validaEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
    function validaTelefone(v){ var d=soDigitos(v); return d.length===10 || d.length===11; }
    function validaAleatoria(v){ return v && v.length>=32; }

    function validarPix() {
        if (!pixTipo || !pixChave || !pixFb) return true;
        var t = pixTipo.value, v = (pixChave.value||'').trim();
        if (!t) { pixFb.textContent=''; pixFb.className='invalid-feedback d-block'; return true; }
        if (!v) { pixFb.textContent='Informe a chave.'; pixFb.className='invalid-feedback d-block text-warning'; return false; }
        var ok=false, msg='';
        if (t==='cpf')         { ok=validaCPF(v);      msg = ok?'CPF válido':'CPF inválido'; }
        else if (t==='cnpj')   { ok=validaCNPJ(v);     msg = ok?'CNPJ válido':'CNPJ inválido'; }
        else if (t==='email')  { ok=validaEmail(v);    msg = ok?'E-mail válido':'E-mail inválido'; }
        else if (t==='telefone'){ ok=validaTelefone(v);msg = ok?'Telefone válido':'Telefone inválido'; }
        else if (t==='aleatoria'){ ok=validaAleatoria(v);msg = ok?'Chave aleatória OK':'Use a chave aleatória completa (32+ caracteres)'; }
        pixFb.textContent = msg;
        pixFb.className = 'invalid-feedback d-block ' + (ok?'text-success':'text-danger');
        return ok;
    }
    if (pixTipo)  pixTipo.addEventListener('change', validarPix);
    if (pixChave) pixChave.addEventListener('input',  validarPix);
    document.getElementById('formPerfil')?.addEventListener('submit', function(e){
        if (!validarPix()) { e.preventDefault(); alert('Corrija a chave PIX antes de salvar.'); }
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
PHPEOF;
put($root, 'src/Views/oficina/perfil.php', $perfil, $report);

// ═════════════════════════════════════════════════════════════════════
// 7. ROTAS — garantir pedidosPage + adicionar recusar
// ═════════════════════════════════════════════════════════════════════
$index = file_get_contents($indexPath);
@copy($indexPath, $indexPath . '.bak-v11-' . date('Ymd-His'));

// 7a. GET: /oficina/pedidos → pedidosPage
$index = preg_replace(
    "#\s*'/oficina/pedidos'\s*=>\s*\['OficinaController'\s*,\s*'pedidosDisponiveis'\s*,\s*'oficina'\],#",
    "\n        '/oficina/pedidos'               => ['OficinaController', 'pedidosPage',         'oficina'],",
    $index
);

// 7b. POST: adicionar /oficina/recusar/{id}
if (strpos($index, "'/oficina/recusar/") === false) {
    $index = str_replace(
        "        '/oficina/pedido/{id}/atualizar'  => ['OficinaController', 'atualizarStatus',     'oficina'],",
        "        '/oficina/pedido/{id}/atualizar'  => ['OficinaController', 'atualizarStatus',     'oficina'],\n        '/oficina/recusar/{id}'           => ['OficinaController', 'recusar',              'oficina'],",
        $index
    );
}

@file_put_contents($indexPath, $index);
r($report, 'OK', 'index.php: rotas ajustadas (pedidosPage + recusar)');
r($report, 'LINT', trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($indexPath) . ' 2>&1')));

// ═════════════════════════════════════════════════════════════════════
// 8. Verificação final
// ═════════════════════════════════════════════════════════════════════
$report[] = '';
$report[] = '── VERIFICAÇÃO FINAL ──';
$final = file_get_contents($indexPath);
if (preg_match("#/oficina/pedidos'\s*=>\s*\['OficinaController'\s*,\s*'pedidosPage'#", $final)) {
    r($report, '✅', "Rota /oficina/pedidos → pedidosPage OK");
} else {
    r($report, '❌', "Rota /oficina/pedidos AINDA aponta pra pedidosDisponiveis");
}
if (strpos($final, "'/oficina/recusar/{id}'") !== false) {
    r($report, '✅', "Rota /oficina/recusar/{id} presente");
} else {
    r($report, '⚠️', "Rota /oficina/recusar/{id} NÃO foi adicionada");
}

$report[] = '';
$report[] = '── PRÓXIMO PASSO ──';
$report[] = '1. Reiniciar Apache pelo painel do XAMPP (Stop → Start)';
$report[] = '2. Ctrl+Shift+R em todas as páginas /oficina/*';
$report[] = '3. Verificar paleta (deve estar 100% marrom/âmbar, sem verde)';

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v11</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔥 Installer v11 — palette + oferta + validators</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-oficina-v11.php</p>
</body></html>