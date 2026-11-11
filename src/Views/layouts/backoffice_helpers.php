<?php
/** Helpers de apresentação do shell Lovable. Nenhuma decisão de negócio aqui. */
if (!function_exists('gfBoH')) {
    function gfBoH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('gfBoHref')) {
    function gfBoHref(string $path): string {
        return str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_contains($path, "\\") ? $path : '#';
    }
}
if (!function_exists('gfBoIcon')) {
    function gfBoIcon(string $name, int $size=18): string {
        $paths = [
            'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
            'truck' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
            'wallet' => '<path d="M19 7V4a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15a1 1 0 0 1 1 1v3h-3a2 2 0 0 0 0 4h3v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5"/>',
            'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>',
            'user-round' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
            'list-checks' => '<path d="m3 5 2 2 4-4M11 6h10m-8 6 2 2 4-4m2 3h10m-18 6 2 2 4-4m2 3h10"/>',
            'plus-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>',
            'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
            'bell' => '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
            'car' => '<path d="M5 17h14l2-6-2-5H5l-2 5 2 6Z"/><path d="M5 17v3M19 17v3"/><circle cx="7.5" cy="14" r="1"/><circle cx="16.5" cy="14" r="1"/>',
            'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
            'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
            'radio-tower' => '<path d="M4.9 19.1A10 10 0 0 1 2 12a10 10 0 0 1 2.9-7.1"/><path d="M7.8 16.2a6 6 0 0 1 0-8.4"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4"/><path d="M19.1 4.9A10 10 0 0 1 22 12a10 10 0 0 1-2.9 7.1"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'map' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/>',
            'megaphone' => '<path d="m3 11 18-5v12L3 14v-3Z"/><path d="M11.6 16.8 13 21H8l-1.7-5"/>',
            'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.9 1.9-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20h-2.68v-.09a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.9-1.9.06-.06A1.7 1.7 0 0 0 7.8 15a1.7 1.7 0 0 0-1.56-1.03H6v-2.68h.24A1.7 1.7 0 0 0 7.8 10a1.7 1.7 0 0 0-.34-1.88L7.4 8.06l1.9-1.9.06.06a1.7 1.7 0 0 0 1.88.34A1.7 1.7 0 0 0 12.27 5V4h2.68v1a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.9 1.9-.06.06A1.7 1.7 0 0 0 19.4 10c.25.62.86 1.03 1.53 1.03H21v2.68h-.07A1.7 1.7 0 0 0 19.4 15Z"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'send' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
            'clipboard-check' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
            'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'circle-check' => '<circle cx="12" cy="12" r="10"/><path d="m8 12 2.5 2.5L16 9"/>',
            'star' => '<path d="m12 3 2.78 5.63 6.22.9-4.5 4.38 1.06 6.19L12 17.18 6.44 20.1 7.5 13.91 3 9.53l6.22-.9L12 3Z"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92Z"/>',
            'external-link' => '<path d="M15 3h6v6M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        ];
        $path = $paths[$name] ?? $paths['layout-dashboard'];
        $size = max(12, min(32, $size));
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
    }
}
if (!function_exists('gfBoStatus')) {
    function gfBoStatus(string $status): string {
        $map = ['aguardando_pagamento'=>['Aguardando pagamento','warning'],'aguardando_guincho'=>['Buscando guincho','info'],'a_caminho'=>['A caminho','info'],'no_local'=>['No local',''],'em_reboque'=>['Em reboque',''],'concluido'=>['Concluído',''],'encerrado_financeiro'=>['Encerrado',''],'cancelado'=>['Cancelado','danger'],'em_compensacao'=>['Em compensação','warning'],'liberado'=>['Liberado',''],'reservado_saque'=>['Reservado para saque','info'],'pago'=>['Pago',''],'bloqueado'=>['Bloqueado','danger'],'falha'=>['Falha','danger']];
        [$label,$class] = $map[$status] ?? [$status, 'info'];
        return '<span class="bo-status'.($class ? ' '.htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span>';
    }
}
