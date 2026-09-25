<?php
declare(strict_types=1);

$config = $config ?? [];
$config += [
    'mode' => $mode ?? 'public',
    'api' => ['quote' => '/pedido/cotar', 'create' => '/pedido/criar'],
    'csrf' => $csrfToken ?? '',
    'admin' => [
        'allowCustomerSelection' => ($mode ?? '') === 'admin',
        'allowProviderAssignment' => ($mode ?? '') === 'admin',
    ],
];
?>
<widget-pedido-socorro data-config="<?= htmlspecialchars(json_encode($config, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"></widget-pedido-socorro>
<script src="/public/assets/js/widget-pedido-socorro.js" defer></script>
