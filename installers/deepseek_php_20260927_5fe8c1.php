<?php
// install-v15.php — JS conforme spec do fluxo
// APAGUE DEPOIS DE RODAR

$root = __DIR__;

$js = <<<'JSEOF'
/* ============================================================
   public-pre-cotacao-sintomas.js — v15
   Fluxo conforme spec:
     address → (confirmado) → busca oficinas → situacao (3 opções)
                                              ↓ (se 0)
                                              destino (Opção 3)
     situação → me_orientem/resolver_local → sintoma → decisão (A/B)
     situação → levar_carro                → destino → resumo
   ============================================================ */
(function () {
    'use strict';
    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }
    function $(id) { return document.getElementById(id); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var API_DECISAO  = '/api/pre-cotacao/decisao';
    var API_OFICINAS = '/api/pre-cotacao/oficinas-proximas';
    var WHATSAPP = window.__preCotacaoWhatsApp || '';
    var SEM_COBERTURA = !!(window.__preCotacaoSemCobertura && window.__preCotacaoSemCobertura.status);

    var oficinasNoRaio = null;   // null = ainda não buscou
    var tipoEscolhido = '';
    var destinoMap = null;
    var destinoMarker = null;

    // ═══════════════════════════════════════════════════════════
    // Estágios e visibilidade
    // ═══════════════════════════════════════════════════════════
    var TODOS_STAGES = ['situacaoStage','sintomaStage','decisionStage','destinoBox',
                        'destinationStage','vehicleStage','fieldVeiculoPodeMover',
                        'confirmarEnderecoStage'];

    function hideStages() {
        TODOS_STAGES.forEach(function(id){ var el=$(id); if (el) el.hidden = true; });
    }
    function mostrar(id) {
        hideStages();
        var el = $(id); if (el) el.hidden = false;
        atualizarNav();
        log('mostrar:', id);
    }
    function atualizarNav() {
        // Voltar: visível quando NÃO estamos na situação
        var btnVoltar = $('btnSituacaoVoltar');
        var btnAvancar = $('btnSituacaoAvancar');
        var btnCotacao = $('btnCotacao');
        var naSituacao = $('situacaoStage') && !$('situacaoStage').hidden;
        var noDestino = $('destinoBox') && !$('destinoBox').hidden;

        if (btnVoltar) btnVoltar.style.display = naSituacao ? 'none' : '';
        if (btnAvancar) btnAvancar.style.display = 'none'; // nunca mais usamos
        if (btnCotacao) btnCotacao.style.display = noDestino ? '' : 'none';
    }

    // ═══════════════════════════════════════════════════════════
    // Anti-form.js (se ainda em cache)
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'tipo_problema') e.stopImmediatePropagation();
    }, true);

    // ═══════════════════════════════════════════════════════════
    // ETAPA 2 — Busca silenciosa de oficinas
    // ═══════════════════════════════════════════════════════════
    function buscarOficinas() {
        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) {
            log('sem lat/lng — mostra situacao mesmo assim');
            mostrar('situacaoStage');
            return;
        }
        log('buscando oficinas no raio...');
        var url = API_OFICINAS + '?lat=' + encodeURIComponent(lat.value) + '&lng=' + encodeURIComponent(lng.value);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                oficinasNoRaio = (j.data && j.data.oficinas) || j.oficinas || [];
                log('oficinas no raio:', oficinasNoRaio.length);
                if (oficinasNoRaio.length === 0) {
                    // CASO A: pula pra Opção 3
                    log('CASO A — 0 oficinas, pulando pra Opção 3');
                    mostrar('destinoBox');
                    ensureDestinoMap();
                } else {
                    // CASO B: mostra as 3 opções
                    log('CASO B —', oficinasNoRaio.length, 'oficina(s), mostrando situação');
                    mostrar('situacaoStage');
                }
            })
            .catch(function (err) {
                log('ERRO busca — mostra situação:', err);
                mostrar('situacaoStage');
            });
    }

    // ═══════════════════════════════════════════════════════════
    // Eventos do public-pre-cotacao.js
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('prequote:location-confirmed', function () {
        log('endereço confirmado');
        buscarOficinas();
    });

    // ═══════════════════════════════════════════════════════════
    // Click em cards / botões
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        var card = e.target.closest && e.target.closest('[data-choice-group]');
        if (card) {
            var g = card.getAttribute('data-choice-group');
            var v = card.getAttribute('data-choice-value');

            // ─── ETAPA 3 — escolha entre as 3 opções ───
            if (g === 'tipo_problema') {
                e.stopImmediatePropagation(); e.preventDefault();
                log('clique situação:', v);
                var t = $('tipo_problema'); if (t) t.value = v;
                document.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');

                if (v === 'me_orientem' || v === 'resolver_local') {
                    mostrar('sintomaStage');
                } else if (v === 'levar_carro') {
                    mostrar('destinoBox');
                    ensureDestinoMap();
                }
                return;
            }

            // ─── Sintoma escolhido (Opção 1 ou 2) ───
            if (g === 'sintoma') {
                e.stopImmediatePropagation(); e.preventDefault();
                log('clique sintoma:', v);
                document.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                var s = $('sintoma'); if (s) s.value = v;
                tipoEscolhido = MAPA[v] || 'mecanica';
                aplicarSintoma(tipoEscolhido);
                return;
            }
            return;
        }

        // ─── Voltar ───
        if (e.target.closest('#btnSituacaoVoltar')) {
            e.stopImmediatePropagation(); e.preventDefault();
            log('voltar clicado');
            var noDestino = $('destinoBox') && !$('destinoBox').hidden;
            var noSintoma = $('sintomaStage') && !$('sintomaStage').hidden;
            if (noDestino || noSintoma) {
                mostrar('situacaoStage');
            } else {
                // Volta pro endereço
                if (window.MapManager) { /* opcional */ }
                location.reload();
            }
            return;
        }
    }, true);

    // ═══════════════════════════════════════════════════════════
    // Aplicar sintoma → API decisão
    // ═══════════════════════════════════════════════════════════
    function aplicarSintoma(tipo) {
        if (SEM_COBERTURA || (oficinasNoRaio && oficinasNoRaio.length === 0)) tipo = 'reboque';
        log('aplicarSintoma:', tipo);
        var t = $('tipo_problema'); if (t) t.value = tipo;

        mostrar('decisionStage');
        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Calculando valores...';

        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng) return;

        fetch(API_DECISAO, {
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
        .catch(function (err) { log('ERRO API:', err); mostrarWhatsApp(); });
    }

    function money(v) { return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }

    function mostrarWhatsApp() {
        var rec = $('decisionRecommendation');
        if (!rec) return;
        if (!WHATSAPP) { rec.textContent = 'Aguarde, vamos te transferir para o suporte.'; return; }
        rec.innerHTML = '<div style="padding:12px 0">' +
            '<p style="margin:0 0 8px"><strong>Vamos resolver direto com você.</strong></p>' +
            '<p style="margin:0 0 12px;font-size:.9rem">Fale agora com um atendente no WhatsApp.</p>' +
            '<a href="' + WHATSAPP + '" target="_blank" rel="noopener" ' +
            'style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;' +
            'padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:700">' +
            '<i class="fab fa-whatsapp" style="font-size:1.2rem"></i>Falar com atendente</a></div>';
    }

    // ═══════════════════════════════════════════════════════════
    // Render da decisão
    // ═══════════════════════════════════════════════════════════
    function render(data) {
        log('render:', data);
        if (data.acao === 'encaminhar_suporte' || data.acao === 'aguardando_sintoma') {
            mostrarWhatsApp(); return;
        }

        var ap = $('assistenciaPrice'), as = $('assistenciaSaida'), rd = $('reboqueDeslocamento');
        var da = $('decisionAssistencia'), dr = $('decisionReboque');
        var rec = $('decisionRecommendation');
        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};
        var semAssist = !assist.disponivel || data.sem_oficina === true || SEM_COBERTURA;

        // ─── Fallback: sem assistência → overlay + destino ───
        if (semAssist) {
            log('sem assistência — overlay + destino');
            hideStages();
            var valor = Number(tow.custo_total || 0);
            var aviso = document.createElement('div');
            aviso.id = '__precoOverlay';
            aviso.style.cssText = 'position:fixed;inset:0;background:rgba(15,17,21,.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';
            aviso.innerHTML = '<div style="background:#fff;padding:24px;border-radius:16px;max-width:420px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3)">' +
                '<p style="margin:0 0 12px;font-size:1.1rem;font-weight:700;color:#142018">Sem assistência na sua região</p>' +
                '<p style="margin:0 0 16px;color:#607066;font-size:.92rem;line-height:1.5">Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher.</p>' +
                '<p style="margin:0 0 8px;font-size:.85rem;color:#607066">Valor estimado do reboque</p>' +
                '<p style="margin:0;font-size:1.5rem;font-weight:800;color:#d97706">' + money(valor) + '</p>' +
                '<p style="margin:16px 0 0;font-size:.8rem;color:#607066">Abrindo destino…</p></div>';
            document.body.appendChild(aviso);
            setTimeout(function () {
                aviso.remove();
                mostrar('destinoBox');
                ensureDestinoMap();
            }, 1500);
            return;
        }

        // ─── Tem A e B ───
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

    // ═══════════════════════════════════════════════════════════
    // Mapa do destino
    // ═══════════════════════════════════════════════════════════
    function ensureDestinoMap() {
        if (destinoMap || !window.L) return;
        var box = $('destinoBox');
        if (!box) return;
        var existing = document.getElementById('destinoMapWrap');
        if (existing) { destinoMap = null; existing.remove(); }
        var wrap = document.createElement('div');
        wrap.id = 'destinoMapWrap';
        wrap.style.cssText = 'height:240px;border-radius:10px;margin-top:12px';
        box.appendChild(wrap);
        setTimeout(function () {
            destinoMap = L.map(wrap).setView([-22.9068, -43.1729], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(destinoMap);
            destinoMap.on('click', function (ev) {
                var lat = ev.latlng.lat, lng = ev.latlng.lng;
                var li = $('lat_destino'); if (li) li.value = lat;
                var lo = $('lng_destino'); if (lo) lo.value = lng;
                if (destinoMarker) destinoMarker.setLatLng(ev.latlng);
                else destinoMarker = L.marker(ev.latlng, { draggable: true }).addTo(destinoMap);
                log('destino marcado:', lat, lng);
            });
            setTimeout(function () { destinoMap.invalidateSize(); }, 100);
        }, 50);
    }

    // ═══════════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════════
    function init() {
        log('init v15');
        hideStages();
        atualizarNav();
        // Se o endereço já está preenchido, o public-pre-cotacao.js
        // vai disparar 'prequote:location-confirmed' quando o usuário confirmar.
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JSEOF;

$jsPath = $root . '/public/assets/js/public-pre-cotacao-sintomas.js';
if (file_exists($jsPath)) @copy($jsPath, $jsPath . '.bak-v15-' . date('Ymd-His'));
$bytes = @file_put_contents($jsPath, $js);

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v15</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>Installer v15 — JS conforme spec</h1>
<pre>[<?= $bytes === false ? 'ERRO' : 'OK' ?>] sintomas.js v15 (<?= (int)$bytes ?> bytes)

── FLUXO ──
1. Cliente confirma endereço (public-pre-cotacao.js)
2. Evento 'prequote:location-confirmed' → JS busca oficinas no raio
3. 0 oficinas → pula direto pra destinoBox (Opção 3)
4. 1+ → mostra situacaoStage (3 opções)
5. Me orientem/Resolver → sintomaStage
6. Sintoma escolhido → API decisão → decisionStage (A/B ou overlay)
7. Levar carro → destinoBox → Ver minha cotação

── TESTE ──
1. Reiniciar Apache (painel XAMPP → Stop → Start)
2. Ctrl+Shift+R em /pre-cotacao
3. Confirme endereço → aparece só a situação
4. Escolha "Me orientem" → aparece só o sintoma
5. Escolha "Pneu" → aparece A + B</pre>
<p class="del">APAGUE: install-v15.php</p>
</body></html>
</｜｜DSML｜｜ parameter>
</invoke>