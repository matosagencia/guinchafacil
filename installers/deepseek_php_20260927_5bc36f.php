<?php
// install-v22.php — remove duplicata + fallback WhatsApp sem guincho online
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════════
// 1. VIEW — remove duplicata do #precoDestinoWrap
// ═══════════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v22-' . date('Ymd-His'));

$openTag = '<div class="col-12 mt-3" id="precoDestinoWrap"';
$pos1 = strpos($view, $openTag);
$pos2 = $pos1 !== false ? strpos($view, $openTag, $pos1 + 1) : false;

if ($pos2 !== false) {
    // Encontra o fim do segundo bloco (3 fechamentos de div consecutivos)
    if (preg_match('#</div>\s*</div>\s*</div>#s', $view, $m, PREG_OFFSET_CAPTURE, $pos2)) {
        $end = $m[0][1] + strlen($m[0][0]);
        $view = substr($view, 0, $pos2) . substr($view, $end);
        @file_put_contents($viewPath, $view);
        $report[] = "[OK] Duplicata #precoDestinoWrap removida (2 → 1)";
    } else {
        $report[] = "[ERRO] Achou 2 blocos mas não o fim do segundo";
    }
} else {
    $report[] = "[SKIP] Só há 1 bloco #precoDestinoWrap";
}
$report[] = "[LINT] pre-cotacao.php: " . trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1'));

// ═══════════════════════════════════════════════════════════════════
// 2. SERVICE — adiciona reboquesOnlineNoRaio() + regra WhatsApp
// ═══════════════════════════════════════════════════════════════════
$svcPath = $root . '/src/Services/DecisaoAtendimentoService.php';
$svc = file_get_contents($svcPath);
@copy($svcPath, $svcPath . '.bak-v22-' . date('Ymd-His'));

// 2a. Adiciona método reboquesOnlineNoRaio antes do último }
if (strpos($svc, 'function reboquesOnlineNoRaio') === false) {
    $metodo = <<<'PHPEOF'

    /**
     * v22: conta guinchos aprovados, disponíveis e dentro do raio de cobertura.
     * Retorna 1 em caso de erro (não bloqueia o fluxo por falha de schema).
     */
    private function reboquesOnlineNoRaio(float $lat, float $lng): int
    {
        try {
            $stmt = getPDO()->query(
                "SELECT id, lat_atual, lng_atual, COALESCE(raio_cobertura_km, 50) AS raio
                   FROM guinchos
                  WHERE aprovado = 1
                    AND disponivel = 1
                    AND lat_atual IS NOT NULL
                    AND lng_atual IS NOT NULL"
            );
            $count = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $dist = GeoService::haversine($lat, $lng, (float)$g['lat_atual'], (float)$g['lng_atual']);
                if ($dist <= (float)$g['raio']) $count++;
            }
            return $count;
        } catch (Throwable $e) {
            error_log('[reboquesOnlineNoRaio] ' . $e->getMessage());
            return 1; // erro de schema → não bloqueia
        }
    }
PHPEOF;
    $pos = strrpos($svc, '}');
    $svc = substr($svc, 0, $pos) . $metodo . "\n}\n";
    $report[] = "[OK] Service: reboquesOnlineNoRaio() adicionado";
} else {
    $report[] = "[SKIP] Service: já tem reboquesOnlineNoRaio";
}

// 2b. Injeta checagem após $oficinas = ...
$anchor = '$oficinas = $this->oficinasNoRaio($lat, $lng, $tipo);';
if (strpos($svc, 'encaminhar_whatsapp') === false && strpos($svc, $anchor) !== false) {
    $injecao = $anchor . <<<'PHPEOF'

        // v22: se precisa de reboque e não há guincho online → WhatsApp
        $reboquesOnline = $this->reboquesOnlineNoRaio($lat, $lng);
        $precisaReboque = ($tipo === 'reboque') || (count($oficinas) === 0);
        if ($precisaReboque && $reboquesOnline === 0) {
            return [
                'acao' => 'encaminhar_whatsapp',
                'opcoes_disponiveis' => [],
                'opcao_assistencia' => ['disponivel' => false],
                'opcao_reboque' => ['disponivel' => false],
                'recomendacao' => null,
                'justificativa' => 'Nenhum guincho online na sua região agora. Vamos te ajudar direto pelo WhatsApp.',
                'mensagem_suporte' => 'Fale com nossa central no WhatsApp para solicitar um guincho.',
                'reboques_online' => 0,
                'desconto_fallback_percentual' => $this->descontoPercentual(),
                'oficina_mais_proxima' => null,
            ];
        }
PHPEOF;
    $svc = str_replace($anchor, $injecao, $svc);
    $report[] = "[OK] Service: regra 'sem guincho online → WhatsApp' injetada";
} else {
    $report[] = "[SKIP] Service: regra já presente ou anchor não encontrado";
}
@file_put_contents($svcPath, $svc);
$report[] = "[LINT] DecisaoAtendimentoService.php: " . trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($svcPath) . ' 2>&1'));

// ═══════════════════════════════════════════════════════════════════
// 3. JS — handle encaminhar_whatsapp
// ═══════════════════════════════════════════════════════════════════
$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
$js = file_get_contents($jsPath);
@copy($jsPath, $jsPath . '.bak-v22-' . date('Ymd-His'));

$oldCheck = "if (data.acao === 'encaminhar_suporte' || data.acao === 'aguardando_sintoma') { mostrarWhatsApp(); return; }";
$newCheck = "if (data.acao === 'encaminhar_suporte' || data.acao === 'aguardando_sintoma' || data.acao === 'encaminhar_whatsapp') { mostrarWhatsApp(); return; }";

if (strpos($js, 'encaminhar_whatsapp') === false && strpos($js, $oldCheck) !== false) {
    $js = str_replace($oldCheck, $newCheck, $js);
    @file_put_contents($jsPath, $js);
    $report[] = "[OK] JS: encaminhar_whatsapp tratado";
} else {
    $report[] = "[SKIP] JS já tem encaminhar_whatsapp";
}

// ═══════════════════════════════════════════════════════════════════
// Diagnóstico
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── DIAGNÓSTICO ──";
try {
    require_once $root . '/config.php';
    $t1 = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1 AND disponivel=1")->fetchColumn();
    $t2 = (int)getPDO()->query("SELECT COUNT(*) FROM guinchos WHERE aprovado=1 AND disponivel=1")->fetchColumn();
    $report[] = "[INFO] oficinas online: $t1";
    $report[] = "[INFO] guinchos online: $t2";
} catch (Throwable $e) {
    $report[] = "[AVISO] " . $e->getMessage();
}

$report[] = "";
$report[] = "── TESTE ──";
$report[] = "1. Reinicia Apache (painel XAMPP)";
$report[] = "2. npx playwright test tests/e2e/fluxo-1a.spec.js --reporter=list --headed";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v22</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔥 Installer v22 — Duplicata + WhatsApp fallback</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v22.php</p>
</body></html>