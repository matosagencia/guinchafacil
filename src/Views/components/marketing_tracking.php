<?php
if (!class_exists('Configuracao')) require_once __DIR__ . '/../../Models/Configuracao.php';
$marketingEnabled = (string) Configuracao::get('marketing_tracking_enabled', '1') === '1';
$googleAdsId = trim((string) Configuracao::get('marketing_google_ads_id', 'AW-18387802162'));
$googleAdsLabel = trim((string) Configuracao::get('marketing_google_ads_conversion_label', ''));
$ga4Id = trim((string) Configuracao::get('marketing_ga4_measurement_id', 'G-0FFGZ5G576'));
$metaPixelId = trim((string) Configuracao::get('marketing_meta_pixel_id', ''));
$pageEvent = isset($marketingPageEvent) ? trim((string) $marketingPageEvent) : '';
$userType = isset($tipo) && in_array((string) $tipo, ['cliente', 'guincho'], true) ? (string) $tipo : 'visitante';
$nonceAttr = function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '';
if ($marketingEnabled && ($googleAdsId !== '' || $ga4Id !== '' || $metaPixelId !== '')):
?>
<script<?php echo $nonceAttr; ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/marketing-tracking.js?v=20260815-2" defer data-enabled="1" data-google-ads-id="<?php echo htmlspecialchars($googleAdsId, ENT_QUOTES, 'UTF-8'); ?>" data-google-ads-label="<?php echo htmlspecialchars($googleAdsLabel, ENT_QUOTES, 'UTF-8'); ?>" data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>" data-page-event="<?php echo htmlspecialchars($pageEvent, ENT_QUOTES, 'UTF-8'); ?>" data-user-type="<?php echo htmlspecialchars($userType, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
