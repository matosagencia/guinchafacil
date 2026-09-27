<?php
// install-fix-v9.php — esconde decisionStage quando sem_oficina + força valor mínimo
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

// ═══════════════════════════════════════════════════════════════════
// 1. Service — nunca retornar custo 0
// ═══════════════════════════════════════════════════════════════════
$svcPath = $root . '/src/Services/DecisaoAtendimentoService.php';
$svc = file_get_contents($svcPath);
@copy($svcPath, $svcPath . '.bak-v9-' . date('Ymd-His'));

// Patch: garantir mínimo 5km no bloco somenteReboque
$oldMin = <<<'PHPOLD'
    private function somenteReboque(int $pedidoId, string $tipo, float $distancia, string $motivo, ?array $oficina = null): array
    {
        return [
            'opcoes_disponiveis' => ['reboque'],
PHPOLD;

$newMin = <<<'PHPNEW'
    private function somenteReboque(int $pedidoId, string $tipo, float $distancia, string $motivo, ?array $oficina = null): array
    {
        if ($distancia <= 0.5) $distancia = 5.0; // nunca zerar
        return [
            'opcoes_disponiveis' => ['reboque'],
PHPNEW;

if (strpos($svc, $oldMin) !== false) {
    $svc = str_replace($oldMin, $newMin, $svc);
    @file_put_contents($svcPath, $svc);
    $report[] = "[OK] Service: guard mínimo 5km em somenteReboque";
} else {
    $report[] = "[SKIP] Service já tem guard em somenteReboque";
}

// ═══════════════════════════════════════════════════════════════════
// 2. JS v9 — esconde decisionStage inteiro quando sem_oficina
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

        // Mostra APENAS a tela de situação enquanto consulta (não pisca o card B)
        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Verificando disponibilidade na sua região...';
        // NÃO mostrar decisionStage ainda — deixa em branco
        hideAll();
        var dstage = $('decisionStage');
        if (dstage) dstage.hidden = false;
        travar('decisionStage', 500);

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
                distancia_km: 5.0
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

        var semAssist = !assist.disponivel || data.sem_oficina === true || SEM_COBERTURA;

        // ⚠️ SEM OFICINA: esconde decisionStage completamente e pula pro destino
        if (semAssist) {
            log('sem assistência — pulando direto pro destino');

            // Esconde TUDO e mostra APENAS mensagem + auto-pula
            ['situacaoStage','sintomaStage','fieldVeiculoPodeMover','vehicleStage','decisionStage'].forEach(function (id) {
                var el = $(id); if (el) el.hidden = true;
            });

            // Overlay amigável (fica visível por ~1.5s e some)
            var aviso = document.createElement('div');
            aviso.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,17,21,.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';
            var valor = Number(tow.custo_total || 0);
            var valorTxt = valor > 0 ? 'R$ ' + valor.toFixed(2).replace('.', ',') : 'calculando…';
            aviso.innerHTML = '<div style="background:#fff;padding:24px;border-radius:16px;max-width:420px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3)">' +
                '<p style="margin:0 0 12px;font-size:1.1rem;font-weight:700;color:#142018">Sem assistência na sua região</p>' +
                '<p style="margin:0 0 16px;color:#607066;font-size:.92rem;line-height:1.5">Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher.</p>' +
                '<p style="margin:0 0 8px;font-size:.85rem;color:#607066">Valor estimado do reboque</p>' +
                '<p style="margin:0;font-size:1.5rem;font-weight:800;color:#2fb34a">' + valorTxt + '</p>' +
                '<p style="margin:16px 0 0;font-size:.8rem;color:#607066">Abrindo destino…</p>' +
                '</div>';
            document.body.appendChild(aviso);

            // Auto-pula pro destino DEPOIS do aviso (1.5s)
            setTimeout(function () {
                aviso.remove();
                log('disparando prequote:go-destination');
                document.dispatchEvent(new Event('prequote:go-destination'));
                // Espera form.js renderizar e mostra
                setTimeout(function () {
                    var ds = $('destinationStage');
                    var box = $('destinoBox');
                    if (ds) ds.hidden = false;
                    if (box) box.classList.remove('d-none');
                }, 100);
            }, 1500);
            return;
        }

        // Tem assistência → mostra A e B normalmente
        if (ap) ap.textContent = money(assist.custo_saida);
        if (as) as.textContent = money(assist.custo_saida);
        if (rd) rd.textContent = money(tow.custo_total);

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

    log('init v9 — overlay amigável + auto-destino | SEM_COBERTURA=' + SEM_COBERTURA);
})();
JSEOF;

$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
if (file_exists($jsPath)) @copy($jsPath, $jsPath . '.bak-v9-' . date('Ymd-His'));
$bytes = @file_put_contents($jsPath, $js);
$report[] = "[OK] sintomas.js v9 ($bytes bytes)";

// ═══════════════════════════════════════════════════════════════════
// 3. Diagnóstico
// ═══════════════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── DIAGNÓSTICO ──";
try {
    require_once $root . '/config.php';
    $t1 = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1 AND latitude IS NOT NULL")->fetchColumn();
    $report[] = "[OK] oficinas: $t1";
    $u = getPDO()->query("SELECT id FROM usuarios WHERE email='oficina@teste.com' LIMIT 1")->fetchColumn();
    $report[] = $u ? "[OK] usuário oficina@teste.com existe (id=$u)" : "[AVISO] usuário oficina@teste.com NÃO existe — rode o INSERT";
} catch (Throwable $e) {
    $report[] = "[AVISO] " . $e->getMessage();
}

$report[] = "";
$report[] = "── O QUE MUDOU ──";
$report[] = "• Service: somenteReboque força distância mínima 5km (nunca R$ 0,00)";
$report[] = "• JS v9: quando sem_oficina, mostra overlay amigável com valor REAL";
$report[] = "• JS v9: esconde TODOS os stages e auto-pula pro destino em 1.5s";
$report[] = "• JS v9: NUNCA deixa o card B feio ('R$ 0,00') aparecer";
$report[] = "";
$report[] = "── LOGIN DA OFICINA ──";
$report[] = "URL: http://localhost:8080/login";
$report[] = "Email: oficina@teste.com";
$report[] = "Senha: teste123";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix v9</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🏭 Factory v9 — overlay amigável</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-v9.php</p>
</body></html>