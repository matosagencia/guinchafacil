<?php
// install-fix-v7.php — Factory completa (JS v7 + patch backend + copy vital)
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];
function r(&$report, $tipo, $msg) { $report[] = "[$tipo] $msg"; }

// ═══════════════════════════════════════════════════════════════════
// 1. JS v7 — interceptor + controller total
// ═══════════════════════════════════════════════════════════════════
$js = <<<'JSEOF'
(function () {
    'use strict';
    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }
    function $(id) { return document.getElementById(id); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var API_URL = '/api/pre-cotacao/decisao';
    var TIPOS_VALIDOS = ['pneu','eletrica','bateria','mecanica','chaveiro','reboque'];
    var SEM_COBERTURA = !!(window.__preCotacaoSemCobertura && window.__preCotacaoSemCobertura.status);
    var WHATSAPP = window.__preCotacaoWhatsApp || '';

    // ═══ 1. Interceptar fetch (bloqueia form.js) ═══
    var fetchOriginal = window.fetch.bind(window);
    window.fetch = function (url, options) {
        var urlStr = typeof url === 'string' ? url : (url && url.url) || '';
        if (urlStr.indexOf(API_URL) !== -1) {
            var t = ($('tipo_problema') || {}).value || '';
            var isPermitida = window.__triagemPermitirFetch === true;
            var isValido = TIPOS_VALIDOS.indexOf(t.toLowerCase()) !== -1;
            if (!isPermitida && !isValido) {
                log('BLOQUEANDO fetch do form.js (tipo="' + t + '")');
                return Promise.resolve(new Response(JSON.stringify({
                    ok: true, data: { acao: 'aguardando_sintoma' }, error: null
                }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
            }
        }
        return fetchOriginal(url, options);
    };

    // ═══ 2. Hide/show + trava ═══
    function hideAll() {
        ['situacaoStage','sintomaStage','fieldVeiculoPodeMover','vehicleStage','decisionStage'].forEach(function (id) {
            var el = $(id); if (el) el.hidden = true;
        });
        var db = $('destinoBox'); if (db) db.classList.add('d-none');
    }
    function mostrar(id) { hideAll(); var el = $(id); if (el) el.hidden = false; log('mostrar:', id); }

    function travar(id, dur) {
        [16,50,150,400,800,1200].forEach(function (d) {
            if (d > (dur || 2000)) return;
            setTimeout(function () { var el = $(id); if (el && el.hidden) { el.hidden = false; } }, d);
        });
    }

    // ═══ 3. Cliques (capture global) ═══
    document.addEventListener('click', function (e) {
        var card = e.target.closest && e.target.closest('[data-choice-group]');
        if (!card) return;
        var g = card.getAttribute('data-choice-group');
        var v = card.getAttribute('data-choice-value');

        if (g === 'tipo_problema') {
            log('clique situação:', v);
            e.stopImmediatePropagation(); e.preventDefault();
            var t = $('tipo_problema'); if (t) t.value = v;
            document.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');

            if (v === 'me_orientem' || v === 'resolver_local') { mostrar('sintomaStage'); travar('sintomaStage'); }
            else if (v === 'levar_carro') { mostrar('fieldVeiculoPodeMover'); travar('fieldVeiculoPodeMover'); }
            return;
        }

        if (g === 'veiculo_pode_mover') {
            log('clique veiculo_pode_mover:', v);
            e.stopImmediatePropagation(); e.preventDefault();
            var vm = $('veiculo_pode_mover'); if (vm) vm.value = v === '1' ? '1' : '0';
            document.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
            if (v === '1') { mostrar('sintomaStage'); travar('sintomaStage'); }
            else { aplicarSintoma('reboque'); }
            return;
        }

        if (g === 'sintoma') {
            log('clique sintoma:', v);
            e.stopImmediatePropagation(); e.preventDefault();
            document.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
            var s = $('sintoma'); if (s) s.value = v;
            aplicarSintoma(MAPA[v] || 'mecanica');
            return;
        }

        if (e.target.closest('#btnSituacaoVoltar')) {
            var sint = $('sintomaStage'), fvm = $('fieldVeiculoPodeMover');
            if ((sint && !sint.hidden) || (fvm && !fvm.hidden)) {
                e.stopImmediatePropagation(); e.preventDefault();
                mostrar('situacaoStage');
            }
        }
    }, true);

    // ═══ 4. Decisão ═══
    function aplicarSintoma(tipo) {
        if (SEM_COBERTURA) { log('sem cobertura — forçando reboque'); tipo = 'reboque'; }
        log('aplicarSintoma:', tipo);
        var t = $('tipo_problema'); if (t) t.value = tipo;
        mostrar('decisionStage'); travar('decisionStage', 2500);
        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Comparando as opções para você...';

        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) { log('ERRO: lat/lng vazios'); return; }

        window.__triagemPermitirFetch = true;
        fetchOriginal(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: tipo, veiculo_pode_mover: true,
                lat_origem: Number(lat.value), lng_origem: Number(lng.value),
                categoria: 'popular'
            }})
        })
        .then(function (r) { return r.json(); })
        .then(function (p) { render(p.data || p); })
        .catch(function (err) { log('ERRO API:', err); mostrarWhatsApp(); })
        .finally(function () { window.__triagemPermitirFetch = false; });
    }

    function money(v) { return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }

    function mostrarWhatsApp() {
        var rec = $('decisionRecommendation');
        if (!rec || !WHATSAPP) return;
        rec.innerHTML = '<div style="padding:12px 0">' +
            '<p style="margin:0 0 8px"><strong>Vamos resolver direto com você.</strong></p>' +
            '<p style="margin:0 0 12px;font-size:.9rem">Fale agora com um atendente no WhatsApp — em segundos você tem ajuda de verdade.</p>' +
            '<a href="' + WHATSAPP + '" target="_blank" rel="noopener" ' +
            'style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;' +
            'padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:700">' +
            '<i class="fab fa-whatsapp" style="font-size:1.2rem"></i>Falar com atendente</a>' +
            '</div>';
        mostrar('decisionStage'); travar('decisionStage', 3000);
        var da = $('decisionAssistencia'), dr = $('decisionReboque');
        if (da) da.hidden = true;
        if (dr) dr.hidden = true;
    }

    function render(data) {
        log('render:', data);

        if (data.acao === 'encaminhar_suporte' || data.acao === 'aguardando_sintoma') {
            mostrarWhatsApp();
            return;
        }

        var ap = $('assistenciaPrice'), as = $('assistenciaSaida');
        var rd = $('reboqueDeslocamento');
        var da = $('decisionAssistencia'), dr = $('decisionReboque');
        var rec = $('decisionRecommendation');
        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};

        if (ap) ap.textContent = money(assist.custo_saida);
        if (as) as.textContent = money(assist.custo_saida);
        if (rd) rd.textContent = money(tow.custo_total);

        var semAssist = !assist.disponivel || SEM_COBERTURA;
        if (da) da.hidden = semAssist;
        if (dr) dr.hidden = false;

        if (rec) {
            if (semAssist) {
                rec.innerHTML = '<strong>Sem assistência na sua região.</strong> ' +
                    'Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher.';
            } else if (data.recomendacao === 'assistencia') {
                rec.innerHTML = '<strong>Recomendamos resolver no local</strong> — mais rápido e mais barato. ' +
                    'Se o mecânico não conseguir consertar aqui, <strong>ele mesmo reboca seu carro até a oficina</strong> ' +
                    'e o valor da assistência é <strong>abatido do conserto</strong>.';
            } else {
                rec.textContent = 'As duas opções fazem sentido. Você decide como seguir.';
            }
        }
        mostrar('decisionStage'); travar('decisionStage', 2500);
        log('render OK — A visível:', da && !da.hidden, '| B visível:', dr && !dr.hidden);
    }

    log('init v7 — interceptor + WhatsApp fallback | SEM_COBERTURA=' + SEM_COBERTURA);
})();
JSEOF;
$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
if (file_exists($jsPath)) @copy($jsPath, $jsPath . '.bak-v7-' . date('Ymd-His'));
$bytes = @file_put_contents($jsPath, $js);
r($report, $bytes === false ? 'ERRO' : 'OK', "sintomas.js v7 ($bytes bytes)");

// ═══════════════════════════════════════════════════════════════════
// 2. Patch AuthController::preCotacao() — remover bloqueio de cobertura
// ═══════════════════════════════════════════════════════════════════
$ctrlPath = $root . '/src/Controllers/AuthController.php';
$ctrl = file_get_contents($ctrlPath);
$backupCtrl = $ctrlPath . '.bak-v7-' . date('Ymd-His');
@copy($ctrlPath, $backupCtrl);
r($report, 'OK', "Backup AuthController: " . basename($backupCtrl));

// Marker exato do bloco a substituir (peguei da leitura anterior)
$oldBlock = <<<'PHPEOF_OLD'
        if (($diagnosticoCobertura['pode_cobrar'] ?? true) !== true) {
            PreQuoteDemandService::registrar([
                'lat_origem' => (float)$lat,
                'lng_origem' => (float)$lng,
                'tipo_problema' => $tipo,
                'categoria' => $categoria,
            ], 'quote');
            PreQuoteDemandService::registrarSemCobertura([
                'lat_origem' => (float)$lat,
                'lng_origem' => (float)$lng,
                'tipo_problema' => $tipo,
                'categoria' => $categoria,
            ]);
            $this->setFlashMessage((string)($diagnosticoCobertura['mensagem'] ?? 'No momento não há cobertura para essa ocorrência.'), 'error');
            $this->redirect('/pre-cotacao');
            return;
        }
PHPEOF_OLD;

$newBlock = <<<'PHPEOF_NEW'
        $_SESSION['pre_cotacao_sem_cobertura'] = null;
        if (($diagnosticoCobertura['pode_cobrar'] ?? true) !== true) {
            PreQuoteDemandService::registrar([
                'lat_origem' => (float)$lat,
                'lng_origem' => (float)$lng,
                'tipo_problema' => $tipo,
                'categoria' => $categoria,
            ], 'quote');
            PreQuoteDemandService::registrarSemCobertura([
                'lat_origem' => (float)$lat,
                'lng_origem' => (float)$lng,
                'tipo_problema' => $tipo,
                'categoria' => $categoria,
            ]);
            $_SESSION['pre_cotacao_sem_cobertura'] = [
                'status' => (string)($diagnosticoCobertura['status'] ?? 'sem_cobertura'),
                'mensagem' => (string)($diagnosticoCobertura['mensagem'] ?? ''),
                'timestamp' => time(),
            ];
        }
PHPEOF_NEW;

if (strpos($ctrl, $oldBlock) !== false) {
    $ctrl = str_replace($oldBlock, $newBlock, $ctrl);
    @file_put_contents($ctrlPath, $ctrl);
    r($report, 'OK', 'AuthController: bloqueio de cobertura removido (agora grava em sessão)');
} else {
    // Tenta versão com espaços em branco variados
    $pattern = '/if\s*\(\(\$diagnosticoCobertura\[.pode_cobrar.\]\s*\?\?\s*true\)\s*!==\s*true\)\s*\{[^}]*?\$this->redirect\(.\/pre-cotacao.\);\s*return;\s*\}/s';
    if (preg_match($pattern, $ctrl)) {
        $ctrl = preg_replace($pattern, $newBlock, $ctrl, 1);
        @file_put_contents($ctrlPath, $ctrl);
        r($report, 'OK', 'AuthController: patch via regex');
    } else {
        r($report, 'AVISO', 'Bloco de cobertura não encontrado — patch manual necessário');
    }
}
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($ctrlPath) . ' 2>&1');
r($report, 'LINT', trim((string)$lint));

// ═══════════════════════════════════════════════════════════════════
// 3. Patch view — injetar flags + copy do abatimento no card A
// ═══════════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
$backupView = $viewPath . '.bak-v7-' . date('Ymd-His');
@copy($viewPath, $backupView);
r($report, 'OK', "Backup view: " . basename($backupView));

// 3a. Injetar flags antes do </body>
$flags = <<<'FLAGS'

<script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?>>
window.__preCotacaoSemCobertura = <?= json_encode($_SESSION['pre_cotacao_sem_cobertura'] ?? null) ?>;
window.__preCotacaoWhatsApp = <?= json_encode(
    defined('COMPANY_WHATSAPP') && COMPANY_WHATSAPP !== ''
        ? 'https://wa.me/55' . preg_replace('/\D/', '', (string)COMPANY_WHATSAPP) . '?text=' . rawurlencode('Olá! Estou com emergência no meu carro e preciso de ajuda.')
        : ''
) ?>;
</script>
FLAGS;

if (strpos($view, '__preCotacaoSemCobertura') === false) {
    $view = str_replace('</body>', $flags . "\n</body>", $view);
    r($report, 'OK', 'View: flags WhatsApp + sem_cobertura injetadas');
} else {
    r($report, 'SKIP', 'View: flags já existem');
}

// 3b. Atualizar copy do card A
$oldCopyA = 'Se ele n&atilde;o conseguir resolver no local, voc&ecirc; pode pedir reboque com 21% de desconto.';
$newCopyA = 'Se ele n&atilde;o conseguir resolver no local, <b>ele mesmo reboca seu ve&iacute;culo at&eacute; a oficina parceira</b> e o valor da assist&ecirc;ncia &eacute; <b>abatido do conserto</b>.';

if (strpos($view, $oldCopyA) !== false) {
    $view = str_replace($oldCopyA, $newCopyA, $view);
    r($report, 'OK', 'View: copy do card A atualizada (abatimento)');
} else {
    r($report, 'SKIP', 'View: copy do card A já atualizada ou padrão diferente');
}

@file_put_contents($viewPath, $view);
$lint2 = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1');
r($report, 'LINT', trim((string)$lint2));

// ═══════════════════════════════════════════════════════════════════
// 4. Copy central — adicionar abatimento
// ═══════════════════════════════════════════════════════════════════
$copyPath = $root . '/src/Views/cliente/_copy_precotacao.php';
$copy = file_get_contents($copyPath);
@copy($copyPath, $copyPath . '.bak-v7-' . date('Ymd-His'));

$oldCopy = "'comparativo_assist_nota'   => 'Se precisar de pe&ccedil;a ou de levar at&eacute; a oficina, abatemos esse valor do reparo.',";
$newCopy = "'comparativo_assist_nota'   => 'Se o mec&acirc;nico n&atilde;o conseguir consertar aqui, ele mesmo reboca seu ve&iacute;culo at&eacute; a oficina parceira e o valor da assist&ecirc;ncia &eacute; abatido do conserto.',";

if (strpos($copy, "'comparativo_assist_nota'") !== false && strpos($copy, "abatido do conserto") === false) {
    $copy = preg_replace(
        "/'comparativo_assist_nota'\s*=>\s*'[^']*',/",
        $newCopy,
        $copy,
        1
    );
    @file_put_contents($copyPath, $copy);
    r($report, 'OK', '_copy_precotacao.php: texto do abatimento atualizado');
} else {
    r($report, 'SKIP', '_copy_precotacao.php: já atualizado');
}

// ═══════════════════════════════════════════════════════════════════
// 5. Diagnóstico
// ═══════════════════════════════════════════════════════════════════
r($report, 'INFO', '');
r($report, 'INFO', '── DIAGNÓSTICO ──');
try {
    require_once $root . '/config.php';
    $t1 = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1 AND latitude IS NOT NULL")->fetchColumn();
    $t2 = (int)getPDO()->query("SELECT COUNT(*) FROM oficina_servicos WHERE ativo=1")->fetchColumn();
    r($report, 'OK', "oficinas geolocalizadas: $t1");
    r($report, 'OK', "oficina_servicos ativos: $t2");
} catch (Throwable $e) {
    r($report, 'AVISO', $e->getMessage());
}

r($report, 'INFO', '');
r($report, 'INFO', '── PRÓXIMOS PASSOS ──');
r($report, 'INFO', '1. Reiniciar Apache pelo painel do XAMPP (Stop → Start)');
r($report, 'INFO', '2. Ctrl+Shift+R em /pre-cotacao');
r($report, 'INFO', '3. F12 → Console → testar os 3 fluxos: Me orientem / Resolver local / Levar carro');

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Factory v7</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}
.ok{color:#22c55e}.aviso{color:#f59e0b}.erro{color:#ef4444}</style>
</head><body>
<h1>🏭 Factory v7 — Fluxo completo + WhatsApp + copy vital</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-v7.php</p>
</body></html>