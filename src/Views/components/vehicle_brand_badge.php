<?php
declare(strict_types=1);

// §CATALOGO-VISUAL-01: badge de inicial pra marca sem logo cadastrado —
// decisão do usuário (02/08/2026) foi NÃO usar logos oficiais de marca por
// enquanto (risco de marca registrada). Cor determinística a partir do nome
// (mesma marca sempre cai na mesma cor, sem precisar guardar isso no banco).
if (!function_exists('vehicle_brand_badge_color')) {
    function vehicle_brand_badge_color(string $nome): string
    {
        $paleta = ['#2563eb', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#d97706', '#16a34a', '#0d9488', '#0891b2', '#4f46e5'];
        $indice = crc32(strtolower(trim($nome))) % count($paleta);
        return $paleta[$indice];
    }
}

if (!function_exists('vehicle_brand_local_logo_path')) {
    /**
     * Resolve somente assets locais previamente autorizados pelo administrador.
     * Não monta URL externa e não baixa logos automaticamente.
     */
    function vehicle_brand_local_logo_path(string $nome): ?string
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: $nome), '-'));
        if ($slug === '') return null;
        $root = dirname(__DIR__, 3);
        $public = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'vehicle-brands';
        foreach (['svg', 'png', 'webp'] as $ext) {
            $file = $public . DIRECTORY_SEPARATOR . $slug . '.' . $ext;
            if (is_file($file)) return '/public/assets/img/vehicle-brands/' . $slug . '.' . $ext;
        }
        return null;
    }
}

if (!function_exists('vehicle_type_icon_class')) {
    function vehicle_type_icon_class(?string $tipo): string
    {
        $tipo = strtolower(trim((string)$tipo));
        if (str_contains($tipo, 'moto')) return 'fa-motorcycle';
        if (str_contains($tipo, 'camin') || str_contains($tipo, 'pickup') || str_contains($tipo, 'picape')) return 'fa-truck-pickup';
        if (str_contains($tipo, 'van')) return 'fa-van-shuttle';
        if (str_contains($tipo, 'elétr') || str_contains($tipo, 'eletr')) return 'fa-charging-station';
        return 'fa-car-side';
    }
}

if (!function_exists('vehicle_brand_badge_html')) {
    /**
     * Renderiza o "avatar" de uma marca: <img> se $logoPath vier preenchido,
     * senão um círculo colorido com a(s) inicial(is) do nome. $tamanhoPx
     * controla width/height (px) do elemento.
     */
    function vehicle_brand_badge_html(string $nome, ?string $logoPath = null, int $tamanhoPx = 56): string
    {
        $logoPath = $logoPath ?: vehicle_brand_local_logo_path($nome);
        if (!empty($logoPath)) {
            return '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8')
                . '" style="width:' . $tamanhoPx . 'px;height:' . $tamanhoPx . 'px;object-fit:contain;border-radius:10px;background:#fff;padding:4px;">';
        }
        $inicial = mb_strtoupper(mb_substr(trim($nome), 0, 1, 'UTF-8'), 'UTF-8');
        $cor = vehicle_brand_badge_color($nome);
        $fonte = (int)round($tamanhoPx * 0.42);
        return '<span style="display:inline-flex;align-items:center;justify-content:center;width:' . $tamanhoPx . 'px;height:' . $tamanhoPx . 'px;'
            . 'border-radius:10px;background:' . $cor . ';color:#fff;font-weight:700;font-size:' . $fonte . 'px;">'
            . htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('vehicle_model_placeholder_html')) {
    /** Placeholder genérico (silhueta) pra modelo sem imagem cadastrada. */
    function vehicle_model_placeholder_html(int $tamanhoPx = 96): string
    {
        return '<span style="display:inline-flex;align-items:center;justify-content:center;width:' . $tamanhoPx . 'px;height:' . (int)round($tamanhoPx * 0.66) . 'px;'
            . 'border-radius:8px;background:#eef1f4;color:#9aa5b1;font-size:' . (int)round($tamanhoPx * 0.36) . 'px;">'
            . '<i class="fas fa-car-side"></i></span>';
    }
}

if (!function_exists('vehicle_identity_html')) {
    function vehicle_identity_html(?string $marca, ?string $modelo, ?string $tipo = null, ?string $placa = null, int $tamanhoPx = 28): string
    {
        $marca = trim((string)$marca);
        $texto = htmlspecialchars(trim((string)$modelo . ($placa !== null && trim($placa) !== '' ? ' · ' . trim($placa) : '')), ENT_QUOTES, 'UTF-8');
        return '<span class="vehicle-identity d-inline-flex align-items-center gap-2"><i class="fas ' . htmlspecialchars(vehicle_type_icon_class($tipo), ENT_QUOTES, 'UTF-8') . ' text-primary-custom" aria-hidden="true"></i>'
            . vehicle_brand_badge_html($marca !== '' ? $marca : 'Fabricante', null, $tamanhoPx) . '<span>' . $texto . '</span></span>';
    }
}
