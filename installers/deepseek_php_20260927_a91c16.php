<?php
// install-oficina-layout.php — clona layout do guincho pro perfil oficina
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════════
// 1. themes/oficina.css — override das vars do body.guincho
// ═══════════════════════════════════════════════════════════════════
$css = <<<'CSSEOF'
/* ============================================================
   GuinchaFácil — Tema Oficina (âmbar escuro / marrom)
   Override de themes/tow.css usando body.oficina
   ============================================================ */

body.oficina {
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

  /* aliases que o resto do projeto usa */
  --primary:        var(--theme-accent);
  --primary-hover:  var(--theme-accent-hover);
}

body.oficina .app-dashboard {
  --dashboard-bg: var(--theme-surface);
}

/* Pequeno reforço: garante que o sidebar do guincho pegue a cor nova */
body.oficina .sidebar {
  background: var(--theme-sidebar);
  color: var(--theme-text);
}
body.oficina .sidebar .sidebar-title {
  color: var(--theme-muted);
}
body.oficina .sidebar .sidebar-link {
  color: var(--theme-text);
}
body.oficina .sidebar .sidebar-link.active,
body.oficina .sidebar .sidebar-link:hover {
  background: var(--theme-accent);
  color: var(--theme-on-accent);
}

/* Badge "OFICINA" no header (igual guincho tem "GUINCHO") */
body.oficina .gf-role-badge {
  background: var(--theme-accent);
  color: var(--theme-on-accent);
}
CSSEOF;

$cssPath = $root . '/public/assets/css/themes/oficina.css';
if (file_exists($cssPath)) @copy($cssPath, $cssPath . '.bak-layout-' . date('Ymd-His'));
@file_put_contents($cssPath, $css);
$report[] = "[OK] themes/oficina.css reescrito como override";

// ═══════════════════════════════════════════════════════════════════
// 2. sidebar_oficina.php — clone do sidebar_guincho
// ═══════════════════════════════════════════════════════════════════
$sidebar = <<<'PHPEOF'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$cur = $_SERVER['REQUEST_URI'] ?? '';
$mk = static function (string $chave) use ($cur): string {
    return strpos($cur, $chave) !== false ? 'active' : '';
};
$nomeOficina = $oficina['nome'] ?? ($_SESSION['user']['nome'] ?? 'Oficina');
?>
<aside class="sidebar">
    <div class="sidebar-title">Operação</div>

    <a href="<?= $bp ?>/oficina/dashboard" class="sidebar-link <?= $mk('/oficina/dashboard') ?>">
        <i class="fas fa-gauge"></i> Painel
    </a>
    <a href="<?= $bp ?>/oficina/pedidos" class="sidebar-link <?= $mk('/oficina/pedidos') ?>">
        <i class="fas fa-bell"></i> Ofertas
    </a>
    <a href="<?= $bp ?>/oficina/historico" class="sidebar-link <?= $mk('/oficina/historico') ?>">
        <i class="fas fa-clock-rotate-left"></i> Histórico
    </a>
    <a href="<?= $bp ?>/oficina/financeiro" class="sidebar-link <?= $mk('/oficina/financeiro') ?>">
        <i class="fas fa-coins"></i> Financeiro
    </a>

    <div class="sidebar-title mt-3">Conta</div>

    <a href="<?= $bp ?>/oficina/perfil" class="sidebar-link <?= $mk('/oficina/perfil') ?>">
        <i class="fas fa-user-pen"></i> Meu Perfil
    </a>
</aside>
PHPEOF;

$sidebarPath = $root . '/src/Views/layouts/sidebar_oficina.php';
if (file_exists($sidebarPath)) @copy($sidebarPath, $sidebarPath . '.bak-layout-' . date('Ymd-His'));
@file_put_contents($sidebarPath, $sidebar);
$report[] = "[OK] sidebar_oficina.php reescrito (estrutura idêntica ao guincho)";

// ═══════════════════════════════════════════════════════════════════
// 3. oficina/dashboard.php — clone do dashboard_guincho com classes .tow-*
// ═══════════════════════════════════════════════════════════════════
$dash = <<<'PHPEOF'
<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

function ofi_saudacao(): string {
    $h = (int)date('G');
    if ($h < 12) return 'Bom dia';
    if ($h < 18) return 'Boa tarde';
    return 'Boa noite';
}
$nome = trim((string)($_SESSION['user']['nome'] ?? 'Oficina'));
$online = !empty($oficina['disponivel']);
$raio = (int)($oficina['raio_atendimento_km'] ?? 10);
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/components/dashboard.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-dashboard.css">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('oficina');});</script>

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
                <p class="tow-hero-subtitle">
                    Raio de atendimento: <?= $raio ?> km
                </p>
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
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/pedidos">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-inbox"></i></div>
                        <div class="tow-stat-value"><?= count($pedidos) ?></div>
                        <div class="tow-stat-label">Pedidos próximos</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <div class="tow-stat">
                    <div class="tow-stat-icon"><i class="fas fa-route"></i></div>
                    <div class="tow-stat-value"><?= $raio ?> km</div>
                    <div class="tow-stat-label">Raio de cobertura</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/financeiro">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-coins"></i></div>
                        <div class="tow-stat-value">R$ --</div>
                        <div class="tow-stat-label">A receber</div>
                    </div>
                </a>
            </div>
        </div>

        <section class="tow-card p-4">
            <div class="tow-panel-header">
                <div>
                    <h3 class="tow-panel-title">
                        <i class="fas fa-bolt me-2 text-primary-custom"></i>Pedidos disponíveis
                    </h3>
                    <p class="tow-panel-subtitle">Ordenados por distância até você</p>
                </div>
            </div>

            <?php if (empty($pedidos)): ?>
                <div class="text-center py-5" style="color:var(--theme-muted)">
                    <i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i>
                    <p class="mb-0">Nenhum pedido disponível no momento.</p>
                    <p class="small mb-0">Ative o modo Online para receber ofertas.</p>
                </div>
            <?php else: foreach ($pedidos as $p): ?>
                <div class="tow-offer mb-3" data-pedido-id="<?= (int)$p['id'] ?>">
                    <div class="tow-offer-head">
                        <div>
                            <span class="tow-offer-eyebrow">Pedido #<?= (int)$p['id'] ?></span>
                            <h4 class="tow-offer-title">
                                <?= htmlspecialchars((string)($p['tipo_problema'] ?? 'Socorro')) ?>
                            </h4>
                            <p class="tow-offer-subtitle">
                                <?= htmlspecialchars((string)($p['endereco_origem'] ?? '')) ?>
                            </p>
                        </div>
                    </div>
                    <div class="tow-offer-metrics">
                        <div class="tow-offer-metric">
                            <span>Distância</span>
                            <strong><?= number_format((float)($p['distancia_km'] ?? 0), 1, ',', '.') ?> km</strong>
                        </div>
                        <div class="tow-offer-metric">
                            <span>Categoria</span>
                            <strong><?= htmlspecialchars((string)($p['categoria'] ?? '—')) ?></strong>
                        </div>
                    </div>
                    <div class="tow-offer-actions">
                        <a href="<?= $bp ?>/oficina/pedido/<?= (int)$p['id'] ?>/aceitar" class="btn btn-success flex-grow-1">
                            <i class="fas fa-check me-1"></i> Aceitar
                        </a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </section>

    </div>
</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    var BP = '<?= $bp ?>';
    var CSRF = '<?= htmlspecialchars($csrfToken ?? '') ?>';
    var toggle = document.getElementById('toggleDisponivel');
    var label = document.getElementById('labelDisponivel');
    if (!toggle) return;

    toggle.addEventListener('change', async function(){
        var d = this.checked ? 1 : 0;
        this.disabled = true;
        label.textContent = 'Salvando...';
        try {
            var fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('disponivel', d ? '1' : '0');
            var r = await fetch(BP + '/oficina/disponibilidade', { method:'POST', body:fd, headers:{Accept:'application/json'} });
            var j = await r.json();
            if (j.ok) { label.textContent = j.disponivel ? 'Online' : 'Offline'; }
            else { this.checked = !d; label.textContent = !d ? 'Online' : 'Offline'; }
        } catch (e) { this.checked = !d; label.textContent = !d ? 'Online' : 'Offline'; }
        finally { this.disabled = false; }
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
PHPEOF;

$dashPath = $root . '/src/Views/oficina/dashboard.php';
if (file_exists($dashPath)) @copy($dashPath, $dashPath . '.bak-layout-' . date('Ymd-His'));
@file_put_contents($dashPath, $dash);
$report[] = "[OK] oficina/dashboard.php reescrito com classes .tow-*";

// ═══════════════════════════════════════════════════════════════════
// 4. Lint
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── VALIDAÇÃO ──";
foreach (['src/Views/layouts/sidebar_oficina.php', 'src/Views/oficina/dashboard.php'] as $f) {
    $full = $root . '/' . $f;
    if (!file_exists($full)) continue;
    $out = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($full) . ' 2>&1');
    $report[] = "[LINT] $f: " . trim((string)$out);
}

// ═══════════════════════════════════════════════════════════════════
// 5. Verifica se os CSS base do guincho existem
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── CSS BASE ──";
foreach (['themes/tow.css', 'components/dashboard.css', 'pages/tow-dashboard.css'] as $c) {
    $full = $root . '/public/assets/css/' . $c;
    $report[] = file_exists($full)
        ? "[OK] public/assets/css/$c (" . filesize($full) . " bytes)"
        : "[ERRO] public/assets/css/$c NÃO EXISTE";
}

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Layout Oficina</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🎨 Layout Oficina — clone do guincho</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-oficina-layout.php</p>
</body></html>