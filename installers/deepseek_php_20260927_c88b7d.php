<?php
// install-v23.php — corrige cor de texto no modal de confirmação de localização
// APAGUE DEPOIS DE RODAR

$root = __DIR__;

// ═══════════════════════════════════════════════════════════════════
// 1. CSS — força texto escuro no modal (fundo branco)
// ═══════════════════════════════════════════════════════════════════
$cssPath = $root . '/public/assets/css/themes/oficina.css';
$css = file_get_contents($cssPath);
@copy($cssPath, $cssPath . '.bak-v23-' . date('Ymd-His'));

if (strpos($css, '/* v23: modal de confirmação') === false) {
    $bloco = <<<'CSS'

/* v23: modal de confirmação de localização — força contraste correto
   (o modal do Bootstrap tem fundo branco; body.guincho tem texto claro.
   Isso resolvia em branco-no-branco) */
#modalConfirmarLocalizacao .modal-content {
    background: #ffffff !important;
    color: #142018 !important;
}
#modalConfirmarLocalizacao .modal-content * {
    color: #142018 !important;
}
#modalConfirmarLocalizacao .modal-header {
    background: #f4f8f5;
    border-bottom: 1px solid #e3ede5;
}
#modalConfirmarLocalizacao .modal-title {
    color: #248f3a !important;
    font-weight: 700;
}
#modalConfirmarLocalizacao .modal-body {
    background: #ffffff;
}
#modalConfirmarLocalizacao .modal-body p {
    color: #405247 !important;
}
#modalConfirmarLocalizacao .modal-body .text-muted {
    color: #607066 !important;
}
#modalConfirmarLocalizacao .modal-body .text-primary-custom,
#modalConfirmarLocalizacao .modal-body i.text-primary-custom {
    color: #248f3a !important;
}
#modalConfirmarLocalizacao .modal-footer {
    background: #f4f8f5;
    border-top: 1px solid #e3ede5;
}
#modalConfirmarLocalizacao .btn-primary {
    background: #2fb34a !important;
    border-color: #2fb34a !important;
    color: #ffffff !important;
    font-weight: 600;
}
#modalConfirmarLocalizacao .btn-primary:hover {
    background: #248f3a !important;
    border-color: #248f3a !important;
}
#modalConfirmarLocalizacao #confirmarLocalizacaoStatus {
    color: #607066 !important;
}
#modalConfirmarLocalizacao #confirmarLocalizacaoErro {
    color: #842029 !important;
}
CSS;
    $css .= "\n" . $bloco;
    @file_put_contents($cssPath, $css);
    echo "[OK] CSS do modal adicionado\n";
} else {
    echo "[SKIP] CSS do modal já existe\n";
}

// ═══════════════════════════════════════════════════════════════════
// 2. VIEW — simplifica o texto do modal
// ═══════════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/guincho/dashboard.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v23-' . date('Ymd-His'));

// Bloco antigo (texto longo)
$oldBody = <<<'HTML'
                    <div class="modal-body">
                        <p class="mb-2">Pra receber ofertas de pedidos perto de você, precisamos confirmar onde você está agora.</p>
                        <p class="text-muted small mb-2">Isso é pedido a cada login (e a cada poucas horas) porque sua posição pode mudar — sem uma localização atual, o sistema não consegue calcular corretamente a distância até os pedidos.</p>
                        <p class="text-muted small mb-3"><i class="fas fa-hand-pointer me-1"></i>Se o pino não estiver exatamente onde você está, arraste-o no mapa até o ponto certo antes de confirmar.</p>
                        <div id="mapConfirmarLocalizacao" style="height: 300px; border-radius: 14px; overflow: hidden; border: 1px solid var(--theme-border);"></div>
                        <div id="confirmarLocalizacaoStatus" class="small text-muted mt-2"></div>
                        <div id="confirmarLocalizacaoErro" class="alert alert-danger small d-none mt-2"></div>
                    </div>
HTML;

// Novo bloco (texto curto e direto)
$newBody = <<<'HTML'
                    <div class="modal-body">
                        <p class="mb-3" style="font-size:1.05rem">
                            <i class="fas fa-hand-pointer me-2"></i>
                            <strong>Arraste o pino no mapa até sua localização atual</strong>
                            — se ele já não estiver no lugar certo.
                        </p>
                        <div id="mapConfirmarLocalizacao" style="height: 340px; border-radius: 14px; overflow: hidden; border: 1px solid #e3ede5;"></div>
                        <div id="confirmarLocalizacaoStatus" class="small mt-2" style="color:#607066"></div>
                        <div id="confirmarLocalizacaoErro" class="alert alert-danger small d-none mt-2"></div>
                    </div>
HTML;

if (strpos($view, 'Pra receber ofertas de pedidos perto de você') !== false) {
    $view = str_replace($oldBody, $newBody, $view);
    @file_put_contents($viewPath, $view);
    echo "[OK] Texto do modal simplificado\n";
} else {
    // Tentativa genérica com regex
    $pattern = '#<div class="modal-body">\s*<p class="mb-2">Pra receber ofertas.*?</div>\s*<div id="confirmarLocalizacaoErro"[^>]*></div>\s*</div>#s';
    if (preg_match($pattern, $view)) {
        $view = preg_replace($pattern, $newBody, $view);
        @file_put_contents($viewPath, $view);
        echo "[OK] Texto do modal simplificado (via regex)\n";
    } else {
        echo "[AVISO] Não achei o bloco do modal — patch manual necessário\n";
    }
}

// ═══════════════════════════════════════════════════════════════════
// 3. Valida sintaxe
// ═══════════════════════════════════════════════════════════════════
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1');
echo "[LINT] " . trim((string)$lint) . "\n";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v23</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}
</style>
</head><body>
<h1>🎨 Installer v23 — Modal legível</h1>
<pre>
── O QUE MUDOU ──
• CSS: força contraste escuro dentro do modal (fundo branco)
• Texto: reduzido a 1 instrução curta e direta
• Botão "Confirmar posição" ganha cor verde Guinchafácil

── TESTE ──
1. Reinicia Apache (painel XAMPP: Stop → Start)
2. Ctrl+Shift+R em /guincho/dashboard
3. Clica "Online" → o modal deve abrir
4. Texto preto legível sobre fundo branco
5. Instrução única: "Arraste o pino no mapa até sua localização atual"
6. Clica "Confirmar posição" → vira Online
</pre>
<p class="del">APAGUE: install-v23.php</p>
</body></html>