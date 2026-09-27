/* ============================================================
   public-pre-cotacao-sintomas.js — v18 FINAL
   Controle por body class: stage-address | stage-situacao |
   stage-sintoma | stage-decisao | stage-destino
   ============================================================ */
(function () {
    'use strict';
    var DEBUG = true;
    function log() { if (DEBUG) console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments))); }
    function $(id) { return document.getElementById(id); }

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };
    var API_DECISAO   = '/api/pre-cotacao/decisao';
    var API_OFICINAS  = '/api/pre-cotacao/oficinas-proximas';
    var WHATSAPP = window.__preCotacaoWhatsApp || '';
    var SEM_COBERTURA = !!(window.__preCotacaoSemCobertura && window.__preCotacaoSemCobertura.status);

    var oficinasNoRaio = null;
    var tipoEscolhido = '';
    var destinoMap = null;
    var destinoMarker = null;

    // ═══ Stages ═══
    var STAGES = ['situacaoStage','sintomaStage','decisionStage','destinoBox',
                  'destinationStage','vehicleStage','fieldVeiculoPodeMover'];

    function hideStages() {
        STAGES.forEach(function(id){ var el=$(id); if (el) el.hidden = true; });
    }

    function setStage(nome) {
        // nome: address | situacao | sintoma | decisao | destino
        document.body.classList.remove('stage-address','stage-situacao','stage-sintoma','stage-decisao','stage-destino');
        document.body.classList.add('stage-' + nome);
    }

    function mostrar(id) {
        hideStages();
        var el = $(id); if (el) el.hidden = false;

        // Mapeia id→nome de estágio
        var mapa = {
            'situacaoStage': 'situacao',
            'sintomaStage': 'sintoma',
            'decisionStage': 'decisao',
            'destinoBox': 'destino'
        };
        if (mapa[id]) setStage(mapa[id]);
        log('mostrar:', id, '| stage:', mapa[id] || id);
    }

    // ═══ Anti-form.js ═══
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'tipo_problema') e.stopImmediatePropagation();
    }, true);

    // ═══ Busca oficinas ═══
    function buscarOficinas() {
        var lat = $('lat_origem'), lng = $('lng_origem');
        if (!lat || !lng || !lat.value || !lng.value) { mostrar('situacaoStage'); return; }
        log('buscando oficinas...');
        var url = API_OFICINAS + '?lat=' + encodeURIComponent(lat.value) + '&lng=' + encodeURIComponent(lng.value);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                oficinasNoRaio = (j.data && j.data.oficinas) || j.oficinas || [];
                log('oficinas no raio:', oficinasNoRaio.length);
                if (oficinasNoRaio.length === 0) {
                    log('CASO A — 0 oficinas → destino');
                    tipoEscolhido = 'reboque';
                    mostrar('destinoBox');
                    ensureDestinoMap();
                } else {
                    log('CASO B —', oficinasNoRaio.length, '→ situação');
                    mostrar('situacaoStage');
                }
            })
            .catch(function (err) { log('ERRO busca:', err); mostrar('situacaoStage'); });
    }

    document.addEventListener('prequote:location-confirmed', function () {
        log('endereço confirmado');
        buscarOficinas();
    });

    // ═══ Click global ═══
    document.addEventListener('click', function (e) {
        // Card A/B
        var dcard = e.target.closest && e.target.closest('.decision-card');
        if (dcard) {
            e.stopImmediatePropagation(); e.preventDefault();
            var id = dcard.id;
            log('clique decision-card:', id);

            document.querySelectorAll('.decision-card').forEach(function (c) {
                c.classList.remove('is-selected');
            });
            dcard.classList.add('is-selected');

            if (id === 'decisionAssistencia') {
                log('assistência → cadastro');
                var formAcc = document.querySelector('form[action$="/pre-cotacao/aceitar"]');
                if (formAcc) formAcc.submit();
                else location.href = '/registro/cliente?retorno=%2Fcliente%2Fpedido%2Fnovo';
            } else if (id === 'decisionReboque') {
                log('reboque → destino');
                tipoEscolhido = 'reboque';
                var di = $('decisao_atendimento'); if (di) di.value = 'reboque';
                mostrar('destinoBox');
                ensureDestinoMap();
            }
            return;
        }

        // Cards de situação/sintoma
        var card = e.target.closest && e.target.closest('[data-choice-group]');
        if (card) {
            var g = card.getAttribute('data-choice-group');
            var v = card.getAttribute('data-choice-value');

            if (g === 'tipo_problema') {
                e.stopImmediatePropagation(); e.preventDefault();
                log('clique situação:', v);
                var t = $('tipo_problema'); if (t) t.value = v;
                document.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                if (v === 'me_orientem' || v === 'resolver_local') mostrar('sintomaStage');
                else if (v === 'levar_carro') {
                    tipoEscolhido = 'reboque';
                    var di = $('decisao_atendimento'); if (di) di.value = 'reboque';
                    mostrar('destinoBox');
                    ensureDestinoMap();
                }
                return;
            }

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
        }

        // Voltar
        if (e.target.closest('#btnSituacaoVoltar')) {
            e.stopImmediatePropagation(); e.preventDefault();
            var noDestino = $('destinoBox') && !$('destinoBox').hidden;
            var noDecision = $('decisionStage') && !$('decisionStage').hidden;
            var noSintoma = $('sintomaStage') && !$('sintomaStage').hidden;
            if (noDecision) { mostrar('sintomaStage'); return; }
            if (noDestino || noSintoma) { mostrar('situacaoStage'); return; }
            location.reload();
            return;
        }

        // btnCotacao — só envia o form se destinoBox visível
        if (e.target.closest('#btnCotacao')) {
            var ld = $('lat_destino'), lnd = $('lng_destino');
            if ((!ld || !ld.value) && destinoMarker) {
                var pos = destinoMarker.getLatLng();
                if (ld) ld.value = pos.lat;
                if (lnd) lnd.value = pos.lng;
            }
            return;
        }
    }, true);

    // ═══ Aplicar sintoma ═══
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
            log('sem assistência → overlay + destino');
            hideStages();
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
                tipoEscolhido = 'reboque';
                var di = $('decisao_atendimento'); if (di) di.value = 'reboque';
                mostrar('destinoBox');
                ensureDestinoMap();
            }, 1500);
            return;
        }

        if (ap) ap.textContent = money(assist.custo_saida);
        if (as) as.textContent = money(assist.custo_saida);
        if (rd) rd.textContent = money(tow.custo_total);
        if (da) da.hidden = false;
        if (dr) dr.hidden = false;

        if (rec) {
            rec.innerHTML = '<strong>Recomendamos resolver no local</strong> — mais rápido e mais barato. ' +
                'Se o mecânico não conseguir consertar aqui, <strong>ele mesmo reboca seu carro até a oficina</strong> ' +
                'e o valor da assistência é <strong>abatido do conserto</strong>.';
        }
        if (da) da.classList.add('is-recommended');
        log('render OK');
    }

    function ensureDestinoMap() {
        if (destinoMap || !window.L) return;
        var box = $('destinoBox');
        if (!box) return;
        var old = document.getElementById('destinoMapWrap');
        if (old) old.remove();
        var wrap = document.createElement('div');
        wrap.id = 'destinoMapWrap';
        wrap.style.cssText = 'height:240px;border-radius:10px;margin-top:12px;position:relative;z-index:1';
        box.appendChild(wrap);
        setTimeout(function () {
            var latO = $('lat_origem'), lngO = $('lng_origem');
            var center = (latO && latO.value && lngO && lngO.value)
                ? [Number(latO.value), Number(lngO.value)]
                : [-22.9068, -43.1729];
            destinoMap = L.map(wrap).setView(center, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(destinoMap);
            var iconDest = L.divIcon({
                html: '<i class="fas fa-flag-checkered" style="color:#2fb34a;font-size:28px;text-shadow:0 0 3px rgba(0,0,0,.6)"></i>',
                iconSize: [28, 32], iconAnchor: [14, 32], className: ''
            });
            destinoMarker = L.marker(center, { draggable: true, icon: iconDest }).addTo(destinoMap);
            function sync(lat, lng) {
                var ld = $('lat_destino'); if (ld) ld.value = lat;
                var lnd = $('lng_destino'); if (lnd) lnd.value = lng;
                calcCota(lat, lng);
            }
            destinoMarker.on('dragend', function () { var p = destinoMarker.getLatLng(); sync(p.lat, p.lng); });
            destinoMap.on('click', function (ev) { destinoMarker.setLatLng(ev.latlng); sync(ev.latlng.lat, ev.latlng.lng); });
            sync(center[0], center[1]);
            setTimeout(function () { destinoMap.invalidateSize(); }, 100);
        }, 50);
    }

    function calcCota(latDest, lngDest) {
        var latO = $('lat_origem'), lngO = $('lng_origem');
        if (!latO || !lngO) return;
        var dLat = (latDest - Number(latO.value)) * Math.PI / 180;
        var dLng = (lngDest - Number(lngO.value)) * Math.PI / 180;
        var lat1 = Number(latO.value) * Math.PI / 180;
        var lat2 = latDest * Math.PI / 180;
        var a = Math.sin(dLat/2)**2 + Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLng/2)**2;
        var dist = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        log('distância origem→destino:', dist.toFixed(2), 'km');
        fetch(API_DECISAO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ pedido_draft: {
                tipo_problema: 'reboque', veiculo_pode_mover: true,
                lat_origem: Number(latO.value), lng_origem: Number(lngO.value),
                categoria: 'popular', distancia_km: Math.max(1, dist)
            }})
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            var d = j.data || j;
            var tow = d.opcao_reboque || {};
            var rd = $('reboqueDeslocamento');
            if (rd && tow.custo_total) rd.textContent = money(tow.custo_total);
            log('cota atualizada:', tow.custo_total);
        })
        .catch(function (e) { log('erro cota:', e); });
    }

    function init() {
        log('init v18');
        hideStages();
        setStage('address');
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();