<?php
// install-fix-partial.php — reescreve _precotacao_extras.php do zero via PHP
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ── Conteúdo alvo, em NOWDOC (bytes literais, sem interpretação) ──
$correto = <<<'PARTIAL'
<?php
// File: guinchafacil/src/Views/cliente/_precotacao_extras.php
// Renderiza: (1) barra de trust badges e (2) botao flutuante do WhatsApp.
//
// COMO USAR - basta 1 linha em qualquer view do wizard:
//   require __DIR__ . '/_precotacao_extras.php';
//
// Le automaticamente do .env:
//   COMPANY_WHATSAPP / WHATSAPP_DEFAULT_TO / WHATSAPP_FALLBACK_TO

if (!isset($copy)) {
    $copy = require __DIR__ . '/_copy_precotacao.php';
}

// Resolve numero de WhatsApp (prioridade: empresa > fallback > default)
$whatsappRaw = '';
foreach (['COMPANY_WHATSAPP', 'WHATSAPP_DEFAULT_TO', 'WHATSAPP_FALLBACK_TO'] as $const) {
    if (defined($const) && trim((string)constant($const)) !== '') {
        $whatsappRaw = (string)constant($const);
        break;
    }
}
$whatsappDigits = preg_replace('/\D/', '', $whatsappRaw);
if ($whatsappDigits !== '' && strlen($whatsappDigits) <= 11) {
    $whatsappDigits = '55' . $whatsappDigits;
}

// Monta mensagem pre-preenchida
$msgBase = $copy['whatsapp_msg'] ?? 'Ola! Preciso de ajuda com meu carro.';
$latMsg = isset($prefillLat) && $prefillLat !== null ? $prefillLat : null;
$lngMsg = isset($prefillLng) && $prefillLng !== null ? $prefillLng : null;
if ($latMsg !== null && $lngMsg !== null) {
    $msgBase .= "\n\nMinha localizacao: https://maps.google.com/?q={$latMsg},{$lngMsg}";
}

$whatsUrl = $whatsappDigits !== ''
    ? 'https://wa.me/' . $whatsappDigits . '?text=' . rawurlencode($msgBase)
    : '';

// Link de CSS (uma vez por request)
static $cssInjected = false;
if (!$cssInjected) {
    $cssInjected = true;
    $bp = defined('BASE_PATH') ? BASE_PATH : '';
    echo '<link rel="stylesheet" href="' . htmlspecialchars($bp) . '/public/assets/css/components/precotacao-trust.css">';
}
?>

<?php if (!empty($copy['trust_nota']) || !empty($copy['trust_resposta']) || !empty($copy['trust_cobertura'])): ?>
<div class="preco-trust-bar" role="list">
    <?php if (!empty($copy['trust_nota']) && strpos($copy['trust_nota'], '[SUBSTITUA') === false): ?>
        <span class="preco-trust-item" role="listitem">
            <i class="fas fa-star" aria-hidden="true"></i>
            <span><?= htmlspecialchars($copy['trust_nota']) ?></span>
        </span>
    <?php endif; ?>
    <?php if (!empty($copy['trust_resposta'])): ?>
        <span class="preco-trust-item" role="listitem">
            <i class="fas fa-clock" aria-hidden="true"></i>
            <span><?= htmlspecialchars($copy['trust_resposta']) ?></span>
        </span>
    <?php endif; ?>
    <?php if (!empty($copy['trust_cobertura'])): ?>
        <span class="preco-trust-item" role="listitem">
            <i class="fas fa-shield-heart" aria-hidden="true"></i>
            <span><?= htmlspecialchars($copy['trust_cobertura']) ?></span>
        </span>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($whatsUrl !== ''): ?>
<a href="<?= htmlspecialchars($whatsUrl) ?>"
   target="_blank" rel="noopener"
   class="pc-whats-float"
   aria-label="<?= htmlspecialchars($copy['whatsapp_cta'] ?? 'Falar no WhatsApp') ?>">
    <i class="fab fa-whatsapp" aria-hidden="true"></i>
    <span class="pc-whats-label">
        <span class="pc-whats-eyebrow"><?= htmlspecialchars($copy['whatsapp_floating'] ?? '') ?></span>
        <?= htmlspecialchars($copy['whatsapp_cta'] ?? 'Falar agora') ?>
    </span>
</a>
<?php endif; ?>
PARTIAL;

// ── 1. Escreve o partial ──
$partialPath = $root . '/src/Views/cliente/_precotacao_extras.php';
if (file_exists($partialPath)) {
    $bak = $partialPath . '.bak-' . date('Ymd-His');
    @copy($partialPath, $bak);
    $report[] = "[OK] Backup: " . basename($bak);
}
$bytes = @file_put_contents($partialPath, $correto);
if ($bytes === false) {
    $report[] = "[ERRO] Falha ao salvar partial";
} else {
    $report[] = "[OK] _precotacao_extras.php reescrito ($bytes bytes)";
}

// ── 2. Confirma hex ──
$b = file_get_contents($partialPath);
$hex = '';
for ($i = 0; $i < min(8, strlen($b)); $i++) {
    $hex .= strtoupper(str_pad(dechex(ord($b[$i])), 2, '0', STR_PAD_LEFT)) . ' ';
}
$report[] = "[HEX] Primeiros 8 bytes: " . trim($hex);
$report[] = "[HEX] Esperado: 3C 3F 70 68 70 0A 2F 2F";

// ── 3. Valida sintaxe ──
$lintOut = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($partialPath) . ' 2>&1');
$report[] = "[LINT] " . trim((string)$lintOut);

// ── 4. Verifica _copy_precotacao.php ──
$copyPath = $root . '/src/Views/cliente/_copy_precotacao.php';
if (file_exists($copyPath)) {
    $b2 = file_get_contents($copyPath);
    $hex2 = '';
    for ($i = 0; $i < min(8, strlen($b2)); $i++) {
        $hex2 .= strtoupper(str_pad(dechex(ord($b2[$i])), 2, '0', STR_PAD_LEFT)) . ' ';
    }
    $report[] = "[OK] _copy_precotacao.php existe";
    $report[] = "[HEX] Primeiros 8 bytes: " . trim($hex2);
    $lint2 = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($copyPath) . ' 2>&1');
    $report[] = "[LINT] " . trim((string)$lint2);
} else {
    $report[] = "[AVISO] _copy_precotacao.php NAO existe";
}

// ── 5. UTF-8 check: verifica se pre-cotacao.php tem bytes de replacement char ──
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
if (file_exists($viewPath)) {
    $vBytes = file_get_contents($viewPath);
    // UTF-8 replacement char (ef bf bd) ou latin1 sequences (c3 83 c2 xx)
    $hasMojibake = (strpos($vBytes, "\xEF\xBF\xBD") !== false)
                || (strpos($vBytes, "\xC3\x83\xC2") !== false);
    if ($hasMojibake) {
        $report[] = "[AVISO] pre-cotacao.php tem bytes de mojibake (UTF-8 corrompido)";
        $report[] = "        Sugestao: reescrever o arquivo com encoding UTF-8 limpo";
    } else {
        $report[] = "[OK] pre-cotacao.php sem mojibake detectado";
    }
}

// ── 6. Limpa OPcache se possivel ──
if (function_exists('opcache_reset')) {
    opcache_reset();
    $report[] = "[OK] OPcache resetado";
} else {
    $report[] = "[INFO] OPcache nao disponivel neste PHP";
}

// ── Render ──
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix Partial</title>
<style>
body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}
pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}
h1{color:#22c55e;margin:0 0 16px}
.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:8px}
</style>
</head><body>
<h1>Fix — Partial de Extras (via PHP)</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-partial.php</p>
</body></html>