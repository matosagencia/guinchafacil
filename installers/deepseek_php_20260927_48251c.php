<?php
// install-fix-v8.php — corrige R$ 0,00 + auto-destino quando sem oficina
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════════
// 1. Service — flag sem_oficina + distância fallback
// ═══════════════════════════════════════════════════════════════════
$svcPath = $root . '/src/Services/DecisaoAtendimentoService.php';
$svc = file_get_contents($svcPath);
$backupSvc = $svcPath . '.bak-v8-' . date('Ymd-His');
@copy($svcPath, $backupSvc);
$report[] = "[OK] Backup: " . basename($backupSvc);

// 1a. Substituir bloco "count($oficinas) === 0" — agora retorna flag sem_oficina
$oldBlocoSemOfic = <<<'PHPEOF_OLD'
        if (count($oficinas) === 0) {
            return $this->somenteReboque($pedidoId, $tipo, 5.0,
                'Nao encontramos oficinas no raio para este tipo de servico. Reboque e a unica opcao.');
        }
PHPEOF_OLD;

$newBlocoSemOfic = <<<'PHPEOF_NEW'
        if (count($oficinas) === 0) {
            $distanciaFallback = max(5.0, (float)($opcoes['distancia_km'] ?? 5.0));
            $result = $this->somenteReboque($pedidoId, $tipo, $distanciaFallback,
                'Sem assistencia na sua regiao agora. Podemos levar seu veiculo ate a oficina que voce escolher.');
            $result['sem_oficina'] = true;
            return $result;
        }
PHPEOF_NEW;

if (strpos($svc, $oldBlocoSemOfic) !== false) {
    $svc = str_replace($oldBlocoSemOfic, $newBlocoSemOfic, $svc);
    $report[] = "[OK] Service: flag sem_oficina adicionada";
} else {
    $report[] = "[AVISO] Bloco antigo de 'count($oficinas) === 0' não encontrado";
}

// 1b. Proteger calcularReboque contra distância 0
$oldCalc = <<<'PHPEOF_CALC'
    private function calcularReboque(float $distanciaKm): float
    {
        $base        = (float)Configuracao::get('taxa_fixa', '150.00');
        $tarifaPorKm = (float)Configuracao::get('tarifa_por_km', '3.50');
        return round($base + ($tarifaPorKm * $distanciaKm), 2);
    }
PHPEOF_CALC;

$newCalc = <<<'PHPEOF_CALCNEW'
    private function calcularReboque(float $distanciaKm): float
    {
        if ($distanciaKm <= 0) $distanciaKm = 5.0; // fallback minimo
        $base        = (float)Configuracao::get('taxa_fixa', '150.00');
        $tarifaPorKm = (float)Configuracao::get('tarifa_por_km', '3.50');
        return round($base + ($tarifaPorKm * $distanciaKm), 2);
    }
PHPEOF_CALCNEW;

if (strpos($svc, $oldCalc) !== false) {
    $svc = str_replace($oldCalc, $newCalc, $svc);
    $report[] = "[OK] Service: calcularReboque com fallback 5km";
} else {
    $report[] = "[SKIP] calcularReboque já protegido";
}

file_put_contents($svcPath, $svc);
$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($svcPath) . ' 2>&1');
$report[] = "[LINT] " . trim((string)$lint);

// ═══════════════════════════════════════════════════════════════════
// 2. JS v8 — auto-destino + copy empática
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
    var ULTIMO_RESULTADO = null;

    // ═══ 1. Interceptar fetch ═══
    var fetchOriginal = window.fetch.bind(window);
    window.fetch = function (url, options) {
        var urlStr = typeof url === 'string' ? url : (url && url.url) || '';
        if (urlStr.indexOf(API_URL) !== -1) {
            var t = ($('tipo_problema') || {}).value || '';
            var isPermitida = window.__triagemPermitirFetch === true;
            var isValido = TIPOS_VALIDOS.indexOf(t.toLowerCase()) !== -1;
            if (!isPermitida && !isValido) {
                log('BLOQUEANDO fetch (tipo="' + t + '")');
                return Promise.resolve(new Response(JSON.stringify({
                    ok: true, data: { acao: 'aguardando_sintoma' }, error: null
                }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
            }
        }
        return fetchOriginal(url, options);
    };

    // ═══ 2. Hide/show ═══
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
            setTimeout(function () { var el = $(id); if (el && el.hidden) el.hidden = false; }, d);
        });
    }

    // ═══ 3. Cliques ═══
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

    // ═══ 4. Aplicar sintoma ═══
    function aplicarSintoma(tipo) {
        if (SEM_COBERTURA) { log('sem cobertura — forçando reboque'); tipo = 'reboque'; }
        log('aplicarSintoma:', tipo);
        var t = $('tipo_problema'); if (t) t.value = tipo;
        mostrar('decisionStage'); travar('decisionStage', 2500);
        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Verificando oficinas na sua região...';

        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) { log('ERRO: lat/lng vazios'); return; }

        window.__triagemPermitirFetch = true;
        fetchOriginal(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: tipo, veiculo_pode_mover: true,
                lat_origem: Number(lat.value), lng_origem: Number(lng.value),
                categoria: 'popular',
                distancia_km: 5.0  // fallback mínimo
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
            '<p style="margin:0 0 12px;font-size:.9rem">Fale agora com um atendente no WhatsApp.</p>' +
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

    // ═══ 5. Render ═══
    function render(data) {
        log('render:', data);
        ULTIMO_RESULTADO = data;

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

        // ⚠ sem_oficina OU sem assistência = vai direto pro destino
        var semAssist = !assist.disponivel || data.sem_oficina === true || SEM_COBERTURA;

        if (semAssist) {
            log('sem assistência — indo pro destino automaticamente');
            if (da) da.hidden = true;
            if (dr) dr.hidden = true;

            if (rec) {
                rec.innerHTML = '<div style="padding:8px 0">' +
                    '<p style="margin:0 0 8px;font-size:1.05rem"><strong>Sem assistência na sua região.</strong></p>' +
                    '<p style="margin:0 0 8px;color:#607066">Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher. ' +
                    'É só nos dizer pra onde.</p>' +
                    '<p style="margin:0;font-size:.85rem;color:#607066">Valor estimado do reboque: <strong>R$ ' +
                    Number(tow.custo_total || 0).toFixed(2).replace('.', ',') + '</strong></p>' +
                    '</div>';
            }

            // Auto-avança pro destino (o form.js tem esse listener)
            setTimeout(function () {
                log('disparando prequote:go-destination');
                document.dispatchEvent(new Event('prequote:go-destination'));
            }, 400);
            return;
        }

        // Tem assistência → mostra A e B
        if (da) da.hidden = false;
        if (dr) dr.hidden = false;

        if (rec) {
            if (data.recomendacao === 'assistencia') {
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

    log('init v8 — interceptor + auto-destino | SEM_COBERTURA=' + SEM_COBERTURA);
})();
JSEOF;

$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
if (file_exists($jsPath)) @copy($jsPath, $jsPath . '.bak-v8-' . date('Ymd-His'));
$bytes = @file_put_contents($jsPath, $js);
$report[] = "[OK] sintomas.js v8 ($bytes bytes)";

// ═══════════════════════════════════════════════════════════════════
// 3. Diagnóstico
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── DIAGNÓSTICO ──";
try {
    require_once $root . '/config.php';
    $t1 = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1 AND latitude IS NOT NULL")->fetchColumn();
    $t2 = (int)getPDO()->query("SELECT COUNT(*) FROM oficina_servicos WHERE ativo=1")->fetchColumn();
    $report[] = "[OK] oficinas geolocalizadas: $t1";
    $report[] = "[OK] oficina_servicos ativos: $t2";
} catch (Throwable $e) {
    $report[] = "[AVISO] " . $e->getMessage();
}

$report[] = "";
$report[] = "── O QUE MUDOU ──";
$report[] = "• Service: retorna flag sem_oficina quando não acha oficina no raio";
$report[] = "• Service: calcularReboque protege contra distância 0 (usa 5km)";
$report[] = "• JS v8: quando sem_oficina=true, auto-dispara pro destinationStage";
$report[] = "• JS v8: copy empática ('Sem assistência aqui, mas levamos seu carro')";
$report[] = "• JS v8: passa distancia_km=5.0 na chamada da API (evita R$ 0,00)";
$report[] = "";
$report[] = "── TESTE ──";
$report[] = "1. Ctrl+Shift+R em /pre-cotacao";
$report[] = "2. Endereço FORA do raio (Rua da Lapa) → Me orientem → Pneu";
$report[] = "3. Deve: mostrar msg 'Sem assistência aqui' + auto-pular pro destino";
$report[] = "4. Endereço DENTRO do raio (Rua da Gamboa) → Me orientem → Pneu";
$report[] = "5. Deve: mostrar card A + card B com valores";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix v8</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🏭 Factory v8 — Sem oficina → auto-destino</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-v8.php</p>
</body></html>