<?php
// install-fluxo-completo.php — correção dos 3 culpados
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];
function r(&$report, $t, $m) { $report[] = "[$t] $m"; }
function put($root, $rel, $c, &$report) {
    $full = $root . '/' . $rel;
    if (!is_dir(dirname($full))) @mkdir(dirname($full), 0777, true);
    if (file_exists($full)) @copy($full, $full . '.bak-fluxo-' . date('Ymd-His'));
    $bytes = @file_put_contents($full, $c);
    r($report, $bytes === false ? 'ERRO' : 'OK', "$rel ($bytes bytes)");
    return $bytes !== false;
}

// ═══════════════════════════════════════════════════════════════════
// 1. DecisaoAtendimentoService — filtro disponivel + retorna lista
// ═══════════════════════════════════════════════════════════════════
$svcPath = $root . '/src/Services/DecisaoAtendimentoService.php';
$svc = file_get_contents($svcPath);
@copy($svcPath, $svcPath . '.bak-fluxo-' . date('Ymd-His'));

// 1a. Query: adiciona AND o.disponivel = 1
$old1 = "                     WHERE o.ativo = 1 AND o.latitude IS NOT NULL AND o.longitude IS NOT NULL";
$new1 = "                     WHERE o.ativo = 1
                       AND o.disponivel = 1
                       AND o.latitude IS NOT NULL
                       AND o.longitude IS NOT NULL";
if (strpos($svc, $old1) !== false) {
    $svc = str_replace($old1, $new1, $svc);
    r($report, 'OK', 'Service: filtro AND o.disponivel = 1');
} else {
    r($report, 'SKIP', 'Service: query já tem filtro');
}

// 1b. Retorno: incluir lista de oficinas
$old2 = "'oficina_mais_proxima' => \$this->formatarOficina(\$oficinaMaisProxima),";
$new2 = "'oficina_mais_proxima' => \$this->formatarOficina(\$oficinaMaisProxima),\n            'oficinas_encontradas' => array_map(fn(\$o) => \$this->formatarOficina(\$o), \$oficinas),";
if (strpos($svc, $old2) !== false && strpos($svc, 'oficinas_encontradas') === false) {
    $svc = str_replace($old2, $new2, $svc);
    r($report, 'OK', 'Service: retorna lista de oficinas_encontradas');
}

// 1c. Adicionar método público oficinasProximasPublico()
if (strpos($svc, 'function oficinasProximasPublico') === false) {
    $metodo = <<<'PHPEOF'

    /** Retorna lista pública de oficinas no raio (para a camada de confirmação). */
    public function oficinasProximasPublico(float $lat, float $lng, string $tipoProblema = ''): array
    {
        $oficinas = $this->oficinasNoRaio($lat, $lng, $tipoProblema);
        return array_map(fn($o) => [
            'id' => (int)($o['provider_id'] ?? 0),
            'nome' => (string)($o['nome'] ?? ''),
            'distancia_km' => round((float)($o['distancia_km'] ?? 0), 2),
            'taxa_saida' => $this->calcularAssistencia($o),
        ], $oficinas);
    }
PHPEOF;
    $pos = strrpos($svc, '}');
    $svc = substr($svc, 0, $pos) . $metodo . "\n}\n";
    r($report, 'OK', 'Service: método oficinasProximasPublico()');
}
@file_put_contents($svcPath, $svc);
r($report, 'LINT', trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($svcPath) . ' 2>&1')));

// ═══════════════════════════════════════════════════════════════════
// 2. PedidoController — endpoint oficinasProximas()
// ═══════════════════════════════════════════════════════════════════
$ctrlPath = $root . '/src/Controllers/PedidoController.php';
$ctrl = file_get_contents($ctrlPath);
@copy($ctrlPath, $ctrlPath . '.bak-fluxo-' . date('Ymd-His'));

if (strpos($ctrl, 'function oficinasProximas') === false) {
    $metodo = <<<'PHPEOF'

    /** GET /api/pre-cotacao/oficinas-proximas?lat=X&lng=Y&tipo=Z */
    public function oficinasProximas(): void
    {
        $this->json(function (): array {
            $lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            $tipo = strtolower(trim((string)($_GET['tipo'] ?? '')));
            if ($lat === false || $lng === false) {
                throw new InvalidArgumentException('Informe lat/lng.');
            }
            $svc = new DecisaoAtendimentoService();
            return [
                'ok' => true,
                'oficinas' => $svc->oficinasProximasPublico((float)$lat, (float)$lng, $tipo),
            ];
        });
    }
PHPEOF;
    $pos = strrpos($ctrl, '}');
    $ctrl = substr($ctrl, 0, $pos) . $metodo . "\n}\n";
    @file_put_contents($ctrlPath, $ctrl);
    r($report, 'OK', 'PedidoController: oficinasProximas()');
} else {
    r($report, 'SKIP', 'PedidoController: já existe');
}
r($report, 'LINT', trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($ctrlPath) . ' 2>&1')));

// ═══════════════════════════════════════════════════════════════════
// 3. index.php — nova rota
// ═══════════════════════════════════════════════════════════════════
$indexPath = $root . '/index.php';
$index = file_get_contents($indexPath);
@copy($indexPath, $indexPath . '.bak-fluxo-' . date('Ymd-His'));

if (strpos($index, '/api/pre-cotacao/oficinas-proximas') === false) {
    $index = str_replace(
        "'/api/pre-cotacao/decisao' => ['PedidoController', 'decisaoPreCotacao', null],",
        "'/api/pre-cotacao/decisao' => ['PedidoController', 'decisaoPreCotacao', null],\n        '/api/pre-cotacao/oficinas-proximas' => ['PedidoController', 'oficinasProximas', null],",
        $index
    );
    @file_put_contents($indexPath, $index);
    r($report, 'OK', 'index.php: rota /api/pre-cotacao/oficinas-proximas');
} else {
    r($report, 'SKIP', 'index.php: rota já existe');
}

// ═══════════════════════════════════════════════════════════════════
// 4. VIEW — esconder decisionStage via CSS + nova camada
// ═══════════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-fluxo-' . date('Ymd-His'));

// 4a. CSS anti-flash (injeta no <head>, ANTES de qualquer script)
$cssAntiFlash = <<<'CSS'
<style>
/* Anti-flash: esconde decisionStage e destinoBox até o JS liberar */
#decisionStage, #destinoBox, #destinationStage { display: none !important; }
body.prequote-decisao-visivel #decisionStage { display: block !important; }
body.prequote-destino-visivel #destinoBox,
body.prequote-destino-visivel #destinationStage { display: block !important; }
</style>
CSS;
if (strpos($view, 'prequote-decisao-visivel') === false) {
    $view = str_replace('</head>', $cssAntiFlash . "\n</head>", $view);
    r($report, 'OK', 'View: CSS anti-flash injetado');
}

// 4b. Novo stage de confirmação de endereço (antes do sintomaStage)
$confirmarEndereco = <<<'HTML'

<div class="col-12" id="confirmarEnderecoStage" hidden>
<fieldset class="choice-fieldset">
<legend class="label">Confirme seu endereço</legend>
<div id="confirmarEnderecoTexto" style="font-size:1rem;color:#405247;margin:.5rem 0 1rem;padding:.75rem 1rem;background:#f4f8f5;border-radius:10px">
  Você informou que o veículo está na <strong id="enderecoConfirmar">—</strong>. Está correto?
</div>
<div class="choice-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
  <button type="button" class="choice-card is-selected" id="btnEnderecoSim" style="padding:1rem">
    <i class="fas fa-check-circle" style="color:#2fb34a;font-size:1.5rem;margin-bottom:.35rem"></i>
    <strong>Sim, está correto</strong>
    <small>Buscar oficinas nesta localização</small>
  </button>
  <button type="button" class="choice-card" id="btnEnderecoNao" style="padding:1rem">
    <i class="fas fa-pen-to-square" style="color:#f59e0b;font-size:1.5rem;margin-bottom:.35rem"></i>
    <strong>Não, corrigir</strong>
    <small>Voltar e ajustar o pin no mapa</small>
  </button>
</div>
<div id="buscandoOficinas" class="text-center py-3" hidden>
  <i class="fas fa-spinner fa-spin fa-2x" style="color:#2fb34a"></i>
  <p class="mt-2 mb-0" style="color:#607066">Buscando oficinas na sua região...</p>
</div>
<div id="resultadoBusca" class="mt-3" hidden></div>
</fieldset>
</div>

HTML;
if (strpos($view, 'id="confirmarEnderecoStage"') === false) {
    $view = str_replace('<div class="col-12" id="sintomaStage"', $confirmarEndereco . '<div class="col-12" id="sintomaStage"', $view);
    r($report, 'OK', 'View: camada de confirmação de endereço');
}

@file_put_contents($viewPath, $view);
r($report, 'LINT', trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1')));

// ═══════════════════════════════════════════════════════════════════
// 5. JS v13 — fluxo com confirmação + oficinas online
// ═══════════════════════════════════════════════════════════════════
$js = <<<'JSEOF'
(function () {
    'use strict';
    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }
    function $(id) { return document.getElementById(id); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var API_DECISAO = '/api/pre-cotacao/decisao';
    var API_OFICINAS = '/api/pre-cotacao/oficinas-proximas';
    var WHATSAPP = window.__preCotacaoWhatsApp || '';
    var SEM_COBERTURA = !!(window.__preCotacaoSemCobertura && window.__preCotacaoSemCobertura.status);

    // ═══ Anti-flash: esconde decisionStage via CSS (backup) ═══
    document.addEventListener('DOMContentLoaded', function(){
        document.body.classList.remove('prequote-decisao-visivel');
        document.body.classList.remove('prequote-destino-visivel');
    });

    // ═══ Intercepta fetch pra bloquear form.js ═══
    var fetchOriginal = window.fetch.bind(window);
    window.fetch = function (url, options) {
        var urlStr = typeof url === 'string' ? url : (url && url.url) || '';
        if (urlStr.indexOf(API_DECISAO) !== -1 && window.__triagemPermitirFetch !== true) {
            log('BLOQUEANDO fetch do form.js');
            return Promise.resolve(new Response(JSON.stringify({
                ok: true, data: { acao: 'aguardando_sintoma' }, error: null
            }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
        }
        return fetchOriginal(url, options);
    };

    function hideAll() {
        ['situacaoStage','sintomaStage','fieldVeiculoPodeMover','vehicleStage','decisionStage','confirmarEnderecoStage'].forEach(function (id) {
            var el = $(id); if (el) el.hidden = true;
        });
        document.body.classList.remove('prequote-decisao-visivel','prequote-destino-visivel');
    }
    function mostrar(id) {
        hideAll();
        var el = $(id); if (el) el.hidden = false;
        if (id === 'decisionStage') document.body.classList.add('prequote-decisao-visivel');
        log('mostrar:', id);
    }

    // ═══ Cliques (capture) ═══
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
            if (v === 'me_orientem' || v === 'resolver_local') mostrar('sintomaStage');
            else if (v === 'levar_carro') mostrar('fieldVeiculoPodeMover');
            return;
        }

        if (g === 'veiculo_pode_mover') {
            e.stopImmediatePropagation(); e.preventDefault();
            var vm = $('veiculo_pode_mover'); if (vm) vm.value = v === '1' ? '1' : '0';
            document.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
            if (v === '1') mostrar('sintomaStage');
            else aplicarSintoma('reboque');
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
            mostrar('confirmarEnderecoStage');
            var end = $('enderecoConfirmar');
            if (end) end.textContent = ($('inputOrigem')||{}).value || ($('endereco_origem')||{}).value || 'endereço informado';
            // Guarda tipo pro próximo passo
            window.__tipoEscolhido = MAPA[v] || 'mecanica';
            return;
        }

        if (e.target.closest('#btnEnderecoNao')) {
            e.stopImmediatePropagation(); e.preventDefault();
            mostrar('situacaoStage');
            return;
        }

        if (e.target.closest('#btnEnderecoSim')) {
            e.stopImmediatePropagation(); e.preventDefault();
            buscarOficinas();
            return;
        }

        if (e.target.closest('#btnSituacaoVoltar')) {
            var sint = $('sintomaStage'), fvm = $('fieldVeiculoPodeMover'), conf = $('confirmarEnderecoStage');
            if (conf && !conf.hidden) { mostrar('sintomaStage'); return; }
            if ((sint && !sint.hidden) || (fvm && !fvm.hidden)) {
                e.stopImmediatePropagation(); e.preventDefault();
                mostrar('situacaoStage');
            }
        }
    }, true);

    // ═══ Busca de oficinas ═══
    function buscarOficinas() {
        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) { alert('Localização não confirmada.'); return; }

        var busca = $('buscandoOficinas'); if (busca) busca.hidden = false;
        var res = $('resultadoBusca'); if (res) { res.hidden = true; res.innerHTML = ''; }

        var url = API_OFICINAS + '?lat=' + encodeURIComponent(lat.value) + '&lng=' + encodeURIComponent(lng.value) + '&tipo=' + encodeURIComponent(window.__tipoEscolhido || '');

        fetchOriginal(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (busca) busca.hidden = true;
                var oficinas = (j.data && j.data.oficinas) || j.oficinas || [];
                log('oficinas encontradas:', oficinas.length);
                if (oficinas.length > 0) {
                    // Tem oficinas online → mostra "Me orientem" (com assistência)
                    aplicarSintoma(window.__tipoEscolhido || 'mecanica');
                } else {
                    // Sem oficinas online → fallback reboque direto
                    if (res) {
                        res.hidden = false;
                        res.innerHTML = '<div class="alert alert-warning mb-0" style="font-size:.95rem">' +
                            '<strong>Sem oficinas online na sua região.</strong><br>' +
                            '<span style="color:#607066;font-size:.88rem">Mas a gente resolve: um guincho leva seu carro até a oficina que você escolher.</span></div>';
                    }
                    setTimeout(function () { aplicarSintoma('reboque'); }, 1200);
                }
            })
            .catch(function (err) {
                log('ERRO busca:', err);
                if (busca) busca.hidden = true;
                aplicarSintoma(window.__tipoEscolhido || 'mecanica');
            });
    }

    // ═══ Aplica sintoma e chama API ═══
    function aplicarSintoma(tipo) {
        if (SEM_COBERTURA) tipo = 'reboque';
        log('aplicarSintoma:', tipo);
        var t = $('tipo_problema'); if (t) t.value = tipo;

        mostrar('decisionStage');
        document.body.classList.add('prequote-decisao-visivel');
        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Calculando valores...';

        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng) return;

        window.__triagemPermitirFetch = true;
        fetchOriginal(API_DECISAO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: tipo, veiculo_pode_mover: true,
                lat_origem: Number(lat.value), lng_origem: Number(lng.value),
                categoria: 'popular', distancia_km: 5.0
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
        if (!rec) return;
        if (!WHATSAPP) {
            rec.textContent = 'Aguarde, vamos te transferir para o suporte.';
            return;
        }
        rec.innerHTML = '<div style="padding:12px 0">' +
            '<p style="margin:0 0 8px"><strong>Vamos resolver direto com você.</strong></p>' +
            '<p style="margin:0 0 12px;font-size:.9rem">Fale agora com um atendente no WhatsApp.</p>' +
            '<a href="' + WHATSAPP + '" target="_blank" rel="noopener" ' +
            'style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;' +
            'padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:700">' +
            '<i class="fab fa-whatsapp" style="font-size:1.2rem"></i>Falar com atendente</a></div>';
        var da = $('decisionAssistencia'); if (da) da.hidden = true;
        var dr = $('decisionReboque'); if (dr) dr.hidden = true;
    }

    function render(data) {
        log('render:', data);
        if (data.acao === 'encaminhar_suporte' || data.acao === 'aguardando_sintoma') { mostrarWhatsApp(); return; }

        var ap = $('assistenciaPrice'), as = $('assistenciaSaida'), rd = $('reboqueDeslocamento');
        var da = $('decisionAssistencia'), dr = $('decisionReboque');
        var rec = $('decisionRecommendation');
        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};
        var semAssist = !assist.disponivel || data.sem_oficina === true || SEM_COBERTURA;

        if (semAssist) {
            log('sem assistência → destino');
            hideAll();
            var valor = Number(tow.custo_total || 0);
            var aviso = document.createElement('div');
            aviso.style.cssText = 'position:fixed;inset:0;background:rgba(15,17,21,.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';
            aviso.innerHTML = '<div style="background:#fff;padding:24px;border-radius:16px;max-width:420px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3)">' +
                '<p style="margin:0 0 12px;font-size:1.1rem;font-weight:700;color:#142018">Sem assistência na sua região</p>' +
                '<p style="margin:0 0 16px;color:#607066;font-size:.92rem;line-height:1.5">Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher.</p>' +
                '<p style="margin:0 0 8px;font-size:.85rem;color:#607066">Valor estimado</p>' +
                '<p style="margin:0;font-size:1.5rem;font-weight:800;color:#d97706">' + money(valor) + '</p>' +
                '<p style="margin:16px 0 0;font-size:.8rem;color:#607066">Abrindo destino…</p></div>';
            document.body.appendChild(aviso);
            setTimeout(function () {
                aviso.remove();
                document.dispatchEvent(new Event('prequote:go-destination'));
                document.body.classList.add('prequote-destino-visivel');
                setTimeout(function () {
                    var ds = $('destinationStage'); if (ds) ds.hidden = false;
                    var box = $('destinoBox'); if (box) box.classList.remove('d-none');
                }, 100);
            }, 1500);
            return;
        }

        // A + B
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
        log('render OK — A:', da && !da.hidden, '| B:', dr && !dr.hidden);
    }

    log('init v13 — fluxo com confirmação de endereço');
})();
JSEOF;
put($root, 'public/assets/js/public-pre-cotacao-sintomas.js', $js, $report);

// ═══════════════════════════════════════════════════════════════════
// 6. Diagnóstico
// ═══════════════════════════════════════════════════════════════════
r($report, '', '── DIAGNÓSTICO ──');
try {
    require_once $root . '/config.php';
    $ofs = getPDO()->query("SELECT id, nome, ativo, disponivel, latitude, longitude FROM oficinas")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ofs as $o) {
        r($report, 'INFO', "  #{$o['id']} {$o['nome']} — ativo={$o['ativo']} disp={$o['disponivel']}");
    }
} catch (Throwable $e) { r($report, 'AVISO', $e->getMessage()); }

r($report, '', '── PRÓXIMO ──');
r($report, 'INFO', '1. Reiniciar Apache pelo painel XAMPP (Stop → Start)');
r($report, 'INFO', '2. Login oficina, ativar Online');
r($report, 'INFO', '3. Testar fluxo completo em /pre-cotacao');

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fluxo Completo</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔥 Installer — Fluxo completo (3 culpados)</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fluxo-completo.php</p>
</body></html>