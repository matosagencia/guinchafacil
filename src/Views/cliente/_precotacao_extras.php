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