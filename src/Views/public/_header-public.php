<?php
/**
 * Header publico centralizado (Onda 5.2 - Abordagem A) — SEO-completo.
 *
 * Variaveis opcionais que cada view pode definir ANTES do require:
 *   $meta_title         - <title> sem o sufixo " | GuinchaFacil"
 *   $meta_description   - <meta name="description">
 *   $meta_canonical     - <link rel="canonical"> e og:url
 *   $meta_og_image      - og:image (default: tow-hero.jpg)
 *   $meta_og_type       - og:type (default: website)
 *   $meta_twitter_card  - twitter:card (default: summary_large_image)
 *   $meta_json_ld       - array OU array de arrays. Se array de arrays,
 *                         gera multiplos <script> JSON-LD.
 *   $body_class         - classe do <body> (default: gf-landing)
 *   $utility_cidade     - texto do link de cidade (default: Niteroi, RJ)
 *   $utility_cidade_url - URL do link (default: /guincho/niteroi-rj)
 *   $nav_atual          - 'inicio' | 'pre-cotacao' | 'onde' | 'parceiros'
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';

$meta_title         = $meta_title         ?? 'GuinchaFacil - Socorro 24h';
$meta_description   = $meta_description   ?? 'Socorro imediato 24h, cotacao rapida e uma rede de guinchos e oficinas.';
$meta_canonical     = $meta_canonical     ?? '';
$meta_og_image      = $meta_og_image      ?? ($bp . '/public/assets/img/landing/tow-hero.jpg');
$meta_og_type       = $meta_og_type       ?? 'website';
$meta_twitter_card  = $meta_twitter_card  ?? 'summary_large_image';
$meta_json_ld       = $meta_json_ld       ?? null;
$body_class         = $body_class         ?? 'gf-landing';
$utility_cidade     = $utility_cidade     ?? 'Niteroi, RJ';
$utility_cidade_url = $utility_cidade_url ?? ($bp . '/guincho/niteroi-rj');
$nav_atual          = $nav_atual          ?? '';

// Helper local: nao depende de csp_script_nonce_attr() estar carregada no fluxo publico.
$nonceAttr = function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?> | GuinchaFacil</title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($meta_canonical): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($meta_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <meta property="og:type" content="<?php echo htmlspecialchars($meta_og_type, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="GuinchaFacil">
    <meta property="og:title" content="<?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($meta_og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($meta_canonical): ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($meta_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <meta name="twitter:card" content="<?php echo htmlspecialchars($meta_twitter_card, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($meta_og_image, ENT_QUOTES, 'UTF-8'); ?>">

    <?php if ($meta_json_ld): ?>
        <?php
        // Suporta tanto um unico array quanto um array de arrays.
        $jsonLdItems = isset($meta_json_ld[0]) && is_array($meta_json_ld[0])
            ? $meta_json_ld
            : [$meta_json_ld];
        foreach ($jsonLdItems as $item):
        ?>
    <script<?php echo $nonceAttr; ?> type="application/ld+json"><?php echo json_encode($item, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php require __DIR__ . '/../components/marketing_tracking.php'; ?>

    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/tokens.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/gf-layout.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/components/gf-footer-payments.css" rel="stylesheet">
<?php if (!empty($extra_css)) foreach ((array)$extra_css as $_x) { echo '    <link rel="stylesheet" href="' . htmlspecialchars($bp . $_x, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL; } ?>
</head>
<body class="<?php echo htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8'); ?>" data-base-path="<?php echo htmlspecialchars($bp); ?>">

<div class="gf-utility-bar">
    <div class="gf-container">
        <span><span class="gf-status-dot"></span> Socorro 24h. Todos os dias. Em qualquer imprevisto.</span>
        <a href="<?php echo htmlspecialchars($utility_cidade_url, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            <?php echo htmlspecialchars($utility_cidade, ENT_QUOTES, 'UTF-8'); ?>
            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true" style="font-size:.7rem"></i>
        </a>
    </div>
</div>

<header class="gf-site-header">
    <div class="gf-container">
        <a class="gf-brand" href="<?php echo htmlspecialchars($bp); ?>/">Guincha<span>Facil</span>.</a>
        <nav class="gf-nav" aria-label="Navegacao principal">
            <a href="<?php echo htmlspecialchars($bp); ?>/"<?php echo $nav_atual === 'inicio' ? ' class="is-active"' : ''; ?>>Inicio</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/pre-cotacao"<?php echo $nav_atual === 'pre-cotacao' ? ' class="is-active"' : ''; ?>>Como funciona</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/guincho/niteroi-rj"<?php echo $nav_atual === 'onde' ? ' class="is-active"' : ''; ?>>Onde atendemos</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/parceiros"<?php echo $nav_atual === 'parceiros' ? ' class="is-active"' : ''; ?>>Seja um parceiro &nearr;</a>
        </nav>
        <a class="gf-btn gf-btn--primary gf-btn--sm" href="<?php echo htmlspecialchars($bp); ?>/pre-cotacao">Pedir guincho &nearr;</a>
    </div>
</header>