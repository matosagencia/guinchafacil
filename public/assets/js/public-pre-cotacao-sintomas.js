(function () {
    'use strict';

    var DEBUG = true;
    var API_DECISAO = '/api/pre-cotacao/decisao';
    var API_OFICINAS = '/api/pre-cotacao/oficinas-proximas';
    var WHATSAPP = window.__preCotacaoWhatsApp || '';
    var SEM_COBERTURA = !!(window.__preCotacaoSemCobertura && window.__preCotacaoSemCobertura.status);
    var STAGES = ['situacaoStage', 'sintomaStage', 'decisionStage', 'destinoBox', 'vehicleStage', 'fieldVeiculoPodeMover'];

    var oficinasNoRaio = null;
    var decisionPayload = null;
    var destinoMap = null;
    var destinoMarker = null;

    function log() {
        if (DEBUG && window.console) {
            console.log.apply(console, ['[triagem]'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    function $(id) {
        return document.getElementById(id);
    }

    function money(value) {
        return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function setHidden(el, hidden) {
        if (!el) return;
        el.hidden = hidden;
        el.classList.toggle('d-none', hidden);
    }

    function hideStages() {
        STAGES.forEach(function (id) { setHidden($(id), true); });
    }

    function setStage(stage) {
        document.body.classList.remove('stage-address', 'stage-situacao', 'stage-sintoma', 'stage-decisao', 'stage-destino', 'stage-veiculo');
        document.body.classList.add('stage-' + stage);
    }

    function updateNavigation(stage) {
        var back = $('btnSituacaoVoltar');
        var next = $('btnSituacaoAvancar');
        var quote = $('btnCotacao');
        if (back) back.hidden = stage === 'address';
        if (next) {
            next.hidden = true;
            next.style.setProperty('display', 'none', 'important');
            if (stage === 'address' && origemValida()) {
                next.hidden = false;
                next.textContent = 'Continuar';
                next.style.setProperty('display', 'inline-flex', 'important');
            }
        }
        if (quote) quote.hidden = !(stage === 'destino' || stage === 'veiculo');
    }

    function showAddress() {
        hideStages();
        setStage('address');
        updateNavigation('address');
        var status = $('gpsStatus');
        if (status && origemValida()) {
            status.textContent = 'Endereco ja confirmado. Voce pode continuar ou ajustar o ponto no mapa.';
        }
        log('stage:', 'address');
    }

    function showStage(stage) {
        hideStages();
        if (stage === 'situacao') setHidden($('situacaoStage'), false);
        if (stage === 'sintoma') setHidden($('sintomaStage'), false);
        if (stage === 'decisao') setHidden($('decisionStage'), false);
        if (stage === 'destino') {
            setHidden($('destinoBox'), false);
            ensureDestinoMap();
        }
        if (stage === 'veiculo') setHidden($('vehicleStage'), false);
        setStage(stage);
        updateNavigation(stage);
        log('stage:', stage);
    }

    function selectChoice(card) {
        var group = card.getAttribute('data-choice-group');
        var value = card.getAttribute('data-choice-value');
        var input = $(group);
        if (input) input.value = value;
        document.querySelectorAll('[data-choice-group="' + group + '"]').forEach(function (item) {
            var selected = item === card;
            item.classList.toggle('is-selected', selected);
            item.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    }

    function origemValida() {
        var lat = $('lat_origem');
        var lng = $('lng_origem');
        return lat && lng && lat.value && lng.value;
    }

    function categoriaAtual() {
        var categoria = $('categoria');
        return categoria && categoria.value ? categoria.value : 'popular';
    }

    function tipoParaDecisao(value) {
        if (value === 'resolver_local' || value === 'me_orientem') return 'pane_mecanica';
        if (value === 'eletrica') return 'pane_eletrica';
        if (value === 'mecanica') return 'pane_mecanica';
        if (value === 'levar_carro') return 'reboque';
        return value || 'pane_mecanica';
    }

    function buscarOficinas() {
        if (!origemValida()) {
            showStage('situacao');
            return;
        }

        var lat = $('lat_origem').value;
        var lng = $('lng_origem').value;
        var status = $('gpsStatus');
        if (status) status.textContent = 'Buscando oficinas na sua regiao...';

        fetch(API_OFICINAS + '?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), {
            headers: { Accept: 'application/json' }
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                oficinasNoRaio = (payload.data && payload.data.oficinas) || payload.oficinas || [];
                log('oficinas no raio:', oficinasNoRaio.length);
                if (oficinasNoRaio.length === 0) {
                    var tipo = $('tipo_problema');
                    var decisao = $('decisao_atendimento');
                    if (tipo) tipo.value = 'reboque';
                    if (decisao) decisao.value = 'reboque';
                    if (status) status.textContent = 'Nao encontramos oficina online perto de voce. Vamos calcular o reboque.';
                    showStage('destino');
                    return;
                }
                if (status) status.textContent = 'Encontramos oficinas na sua regiao. Escolha como quer seguir.';
                showStage('situacao');
            })
            .catch(function (error) {
                log('erro oficinas:', error);
                showStage('situacao');
            });
    }

    function carregarDecisao(tipoProblema) {
        if (!origemValida()) return Promise.resolve(null);
        var rec = $('decisionRecommendation');
        if (rec) rec.textContent = 'Comparando as opcoes para voce...';

        return fetch(API_DECISAO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                pedido_draft: {
                    tipo_problema: tipoParaDecisao(tipoProblema),
                    veiculo_pode_mover: true,
                    lat_origem: Number($('lat_origem').value),
                    lng_origem: Number($('lng_origem').value),
                    categoria: categoriaAtual(),
                    distancia_km: 5
                }
            })
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                decisionPayload = payload.data || payload;
                renderDecisao(decisionPayload);
                return decisionPayload;
            })
            .catch(function (error) {
                log('erro decisao:', error);
                mostrarWhatsApp('Nao conseguimos comparar agora. Fale com a central para continuar.');
                return null;
            });
    }

    function mostrarWhatsApp(message) {
        var rec = $('decisionRecommendation');
        if (!rec) return;
        var link = WHATSAPP || 'https://wa.me/';
        rec.innerHTML = '<div class="whatsapp-fallback" style="padding:12px 0">' +
            '<p style="margin:0 0 8px"><strong>' + (message || 'Vamos resolver direto com voce.') + '</strong></p>' +
            '<a href="' + link + '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:700">' +
            '<i class="fab fa-whatsapp" aria-hidden="true"></i>Falar com atendente</a></div>';
        setHidden($('decisionAssistencia'), true);
        setHidden($('decisionReboque'), true);
    }

    function renderDecisao(data) {
        if (!data) return;
        if (data.acao === 'encaminhar_whatsapp' || data.acao === 'encaminhar_suporte') {
            mostrarWhatsApp(data.justificativa || data.mensagem_suporte);
            return;
        }

        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};
        var rec = $('decisionRecommendation');
        var cardA = $('decisionAssistencia');
        var cardB = $('decisionReboque');
        var assistenciaPrice = $('assistenciaPrice');
        var assistenciaSaida = $('assistenciaSaida');
        var reboqueDeslocamento = $('reboqueDeslocamento');

        if (assistenciaPrice) assistenciaPrice.textContent = money(assist.custo_saida);
        if (assistenciaSaida) assistenciaSaida.textContent = money(assist.custo_saida);
        if (reboqueDeslocamento && tow.custo_total) reboqueDeslocamento.textContent = money(tow.custo_total);
        setHidden(cardA, !assist.disponivel || data.sem_oficina === true || SEM_COBERTURA);
        setHidden(cardB, false);

        [cardA, cardB].forEach(function (card) {
            if (!card) return;
            card.classList.remove('is-selected', 'is-recommended');
        });

        if (data.recomendacao === 'assistencia' && cardA && !cardA.hidden) {
            cardA.classList.add('is-recommended');
            if (rec) rec.innerHTML = '<strong>Recomendamos resolver no local</strong> - mais rapido e mais barato. Se nao resolver, voce ainda pode rebocar.';
        } else {
            if (cardB) cardB.classList.add('is-recommended');
            if (rec) rec.textContent = data.justificativa || 'Recomendamos reboque para este caso.';
        }
    }

    function escolherAssistencia() {
        var decisao = $('decisao_atendimento');
        var tipo = $('tipo_problema');
        var sintoma = $('sintoma');
        if (decisao) decisao.value = 'assistencia';
        if (tipo && (!tipo.value || tipo.value === 'me_orientem' || tipo.value === 'resolver_local')) {
            tipo.value = sintoma && sintoma.value ? sintoma.value : 'mecanica';
        }
        showStage('veiculo');
    }

    function escolherReboque() {
        var decisao = $('decisao_atendimento');
        var tipo = $('tipo_problema');
        if (decisao) decisao.value = 'reboque';
        if (tipo) tipo.value = 'reboque';
        showStage('destino');
    }

    function ensureDestinoMap() {
        if (destinoMap || !window.L) return;
        var box = $('destinoBox');
        if (!box) return;

        var wrap = $('destinoMapWrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'destinoMapWrap';
            wrap.style.cssText = 'height:240px;border-radius:10px;margin-top:12px;position:relative;z-index:1';
            box.appendChild(wrap);
        }

        setTimeout(function () {
            var latO = $('lat_origem');
            var lngO = $('lng_origem');
            var center = (latO && latO.value && lngO && lngO.value)
                ? [Number(latO.value), Number(lngO.value)]
                : [-22.9068, -43.1729];

            destinoMap = L.map(wrap).setView(center, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(destinoMap);
            destinoMarker = L.marker(center, { draggable: true }).addTo(destinoMap);
            destinoMarker.on('dragend', function () {
                var point = destinoMarker.getLatLng();
                syncDestino(point.lat, point.lng);
            });
            destinoMap.on('click', function (event) {
                destinoMarker.setLatLng(event.latlng);
                syncDestino(event.latlng.lat, event.latlng.lng);
            });
            syncDestino(center[0], center[1]);
            setTimeout(function () { destinoMap.invalidateSize(); }, 100);
        }, 50);
    }

    function distanciaKm(lat1, lng1, lat2, lng2) {
        var toRad = Math.PI / 180;
        var dLat = (lat2 - lat1) * toRad;
        var dLng = (lng2 - lng1) * toRad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * toRad) * Math.cos(lat2 * toRad) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function syncDestino(latDest, lngDest) {
        var latInput = $('lat_destino');
        var lngInput = $('lng_destino');
        if (latInput) latInput.value = latDest;
        if (lngInput) lngInput.value = lngDest;
        validarUfDestino(latDest, lngDest);
        recalcularReboque(latDest, lngDest);
    }

    function validarUfDestino(latDest, lngDest) {
        if (!origemValida()) return;
        fetch('/api/pre-cotacao/validar-uf-destino?lat_origem=' + encodeURIComponent($('lat_origem').value) +
            '&lng_origem=' + encodeURIComponent($('lng_origem').value) +
            '&lat_destino=' + encodeURIComponent(latDest) +
            '&lng_destino=' + encodeURIComponent(lngDest), { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                var data = payload.data || payload;
                var ufO = $('uf_origem_detectada');
                var ufD = $('uf_destino_detectada');
                var quote = $('btnCotacao');
                var aviso = $('ufDestinoAviso');
                if (ufO) ufO.value = data.uf_origem || '';
                if (ufD) ufD.value = data.uf_destino || '';
                if (!data.ok) {
                    if (!aviso) {
                        aviso = document.createElement('div');
                        aviso.id = 'ufDestinoAviso';
                        aviso.className = 'alert alert-warning mt-2';
                        if ($('destinoBox')) $('destinoBox').appendChild(aviso);
                    }
                    aviso.textContent = data.mensagem || 'O destino precisa ficar no mesmo estado da origem.';
                    aviso.style.display = 'block';
                    if (quote) quote.disabled = true;
                } else {
                    if (aviso) aviso.style.display = 'none';
                    if (quote) quote.disabled = false;
                }
            })
            .catch(function () {});
    }

    function recalcularReboque(latDest, lngDest) {
        if (!origemValida()) return;
        var dist = distanciaKm(Number($('lat_origem').value), Number($('lng_origem').value), Number(latDest), Number(lngDest));
        log('distancia origem-destino:', dist.toFixed(2), 'km');
        fetch(API_DECISAO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                pedido_draft: {
                    tipo_problema: 'reboque',
                    veiculo_pode_mover: true,
                    lat_origem: Number($('lat_origem').value),
                    lng_origem: Number($('lng_origem').value),
                    categoria: categoriaAtual(),
                    distancia_km: Math.max(1, dist)
                }
            })
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                var data = payload.data || payload;
                var tow = data.opcao_reboque || {};
                var price = tow.custo_total;
                if ($('precoReboqueDestino') && price) $('precoReboqueDestino').textContent = money(price);
                if ($('reboqueDeslocamento') && price) $('reboqueDeslocamento').textContent = money(price);
                if ($('precoDestinoWrap')) $('precoDestinoWrap').hidden = false;
            })
            .catch(function (error) { log('erro cota:', error); });
    }

    function onTipoProblema(value) {
        decisionPayload = null;
        if (value === 'levar_carro') {
            escolherReboque();
            return;
        }
        showStage('sintoma');
    }

    function onSintoma(value) {
        var tipo = $('tipo_problema');
        if (tipo) tipo.value = value;
        showStage('decisao');
        carregarDecisao(value);
    }

    function handleClick(event) {
        var decisionCard = event.target.closest && event.target.closest('.decision-card');
        if (decisionCard && decisionCard.id === 'decisionAssistencia') {
            event.preventDefault();
            event.stopImmediatePropagation();
            escolherAssistencia();
            return;
        }
        if (decisionCard && decisionCard.id === 'decisionReboque') {
            event.preventDefault();
            event.stopImmediatePropagation();
            escolherReboque();
            return;
        }

        var choice = event.target.closest && event.target.closest('[data-choice-group][data-choice-value]');
        if (choice) {
            var group = choice.getAttribute('data-choice-group');
            var value = choice.getAttribute('data-choice-value');
            event.preventDefault();
            event.stopImmediatePropagation();
            selectChoice(choice);
            if (group === 'tipo_problema') onTipoProblema(value);
            if (group === 'sintoma') onSintoma(value);
            return;
        }

        if (event.target.closest && event.target.closest('#btnSituacaoVoltar')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (!$('destinoBox').hidden || !$('vehicleStage').hidden) {
                showStage(decisionPayload ? 'decisao' : 'situacao');
            } else if (!$('decisionStage').hidden) {
                showStage('sintoma');
            } else if (!$('sintomaStage').hidden) {
                showStage('situacao');
            } else {
                showAddress();
            }
            return;
        }

        if (event.target.closest && event.target.closest('#btnSituacaoAvancar')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (document.body.classList.contains('stage-address') && origemValida()) {
                buscarOficinas();
            }
            return;
        }

        if (event.target.closest && event.target.closest('#btnCotacao') && !$('destinoBox').hidden && destinoMarker) {
            var point = destinoMarker.getLatLng();
            syncDestino(point.lat, point.lng);
        }
    }

    function init() {
        showAddress();
        document.addEventListener('click', handleClick, true);
        document.addEventListener('prequote:location-confirmed', buscarOficinas);
        document.addEventListener('prequote:go-destination', escolherReboque);
        document.addEventListener('prequote:destination-confirmed', function (event) {
            if (event.detail) syncDestino(Number(event.detail.lat), Number(event.detail.lng));
        });
        log('init fluxo consolidado');
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
