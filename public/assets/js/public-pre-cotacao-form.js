(function () {
    'use strict';

    var tipo = document.getElementById('tipo_problema');
    var box = document.getElementById('destinoBox');
    var destino = document.getElementById('destino');
    var situacaoStage = document.getElementById('situacaoStage');
    var fieldVeiculoPodeMover = document.getElementById('fieldVeiculoPodeMover');
    var veiculoPodeMover = document.getElementById('veiculo_pode_mover');
    var decisionStage = document.getElementById('decisionStage');
    var vehicleStage = document.getElementById('vehicleStage');
    var addressStage = document.querySelector('.origin-map-composition');
    var gpsButton = document.getElementById('btnGps');
    var originMapPanel = document.getElementById('originMapPanel');
    var originForm = addressStage ? addressStage.querySelector(':scope > .col-12:first-child') : null;
    var form = document.querySelector('form[data-marketing-event="generate_lead"]');
    var btnBack = document.getElementById('btnSituacaoVoltar');
    var btnNext = document.getElementById('btnSituacaoAvancar');
    var btnQuote = document.getElementById('btnCotacao');
    var lat = document.getElementById('lat_origem');
    var lng = document.getElementById('lng_origem');
    var decisionInput = document.getElementById('decisao_atendimento');
    var decisionRecommendation = document.getElementById('decisionRecommendation');
    var decisionAssistencia = document.getElementById('decisionAssistencia');
    var decisionReboque = document.getElementById('decisionReboque');
    var assistenciaPrice = document.getElementById('assistenciaPrice');
    var assistenciaSaida = document.getElementById('assistenciaSaida');
    var reboquePrice = document.getElementById('reboquePrice');
    var decisionFallbackText = document.getElementById('decisionFallbackText');
    var destinationStage = document.createElement('div');
    var destinationMap = null;
    var destinationMarker = null;
    var currentStage = 'address';
    var decisionPayload = null;
    var semOficinaProxima = false;

    if (!tipo || !box || !form || !vehicleStage) return;

    var addressToggle = document.createElement('button');
    addressToggle.type = 'button';
    addressToggle.className = 'address-toggle';
    addressToggle.setAttribute('aria-expanded', 'true');
    addressToggle.textContent = 'Recolher busca';

    var solutionInput = document.createElement('input');
    solutionInput.type = 'hidden';
    solutionInput.name = 'solucao_preferida';
    solutionInput.id = 'solucao_preferida';
    form.appendChild(solutionInput);

    destinationStage.id = 'destinationStage';
    destinationStage.className = 'destination-stage';
    destinationStage.hidden = true;
    destinationStage.innerHTML = '<div id="destinationMapPanel" class="destination-map-panel"><div class="destination-map-head"><strong>Para onde levar o veiculo?</strong><span>Informe e confirme o endereco de entrega.</span></div><div id="destinationMap" class="destination-map"></div></div>';
    var destinationMapPanel = destinationStage.firstElementChild;

    function precisaDestino() {
        return ['colisao', 'reboque'].indexOf(tipo.value) !== -1 || (decisionInput && decisionInput.value === 'reboque');
    }

    function atualizarDestinoObrigatorio() {
        var exige = precisaDestino();
        box.classList.toggle('d-none', !exige && currentStage !== 'destination');
        if (destino) destino.required = exige;
    }

    function atualizarVisibilidadeVeiculoPodeMover() {
        if (!fieldVeiculoPodeMover) return;
        fieldVeiculoPodeMover.hidden = tipo.value !== 'colisao';
    }

    function money(value) {
        return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function tipoParaDecisao() {
        var value = tipo.value || 'outro';
        if (value === 'mecanica' || value === 'outro') return 'pane_mecanica';
        if (value === 'eletrica') return 'pane_eletrica';
        if (value === 'combustivel') return 'pane_seca';
        return value;
    }

    async function carregarDecisao() {
        if (!decisionStage || !lat || !lng || !lat.value || !lng.value) return null;
        if (decisionRecommendation) decisionRecommendation.textContent = 'Comparando as opcoes para voce...';
        try {
            var res = await fetch(decisionStage.dataset.decisionUrl || '/api/pre-cotacao/decisao', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    pedido_draft: {
                        tipo_problema: tipoParaDecisao(),
                        veiculo_pode_mover: veiculoPodeMover ? veiculoPodeMover.value === '1' : true,
                        lat_origem: Number(lat.value),
                        lng_origem: Number(lng.value),
                        categoria: document.getElementById('categoria') ? document.getElementById('categoria').value : 'popular'
                    }
                })
            });
            var payload = await res.json();
            decisionPayload = payload.data || payload;
            renderDecisao(decisionPayload);
            return decisionPayload;
        } catch (e) {
            if (decisionRecommendation) decisionRecommendation.textContent = 'Nao conseguimos comparar agora. Voce ainda pode seguir com reboque.';
            if (decisionAssistencia) decisionAssistencia.hidden = true;
            if (decisionReboque) decisionReboque.classList.add('is-recommended');
            return null;
        }
    }

    function renderDecisao(data) {
        var assist = data.opcao_assistencia || {};
        var tow = data.opcao_reboque || {};
        if (assistenciaPrice) assistenciaPrice.textContent = money(assist.custo_saida);
        if (assistenciaSaida) assistenciaSaida.textContent = money(assist.custo_saida);
        if (reboquePrice) reboquePrice.textContent = money(tow.custo_total);
        if (decisionAssistencia) decisionAssistencia.hidden = !assist.disponivel;
        [decisionAssistencia, decisionReboque].forEach(function (card) {
            if (!card) return;
            card.classList.remove('is-recommended', 'is-selected');
        });
        if (!assist.disponivel) {
            if (decisionRecommendation) decisionRecommendation.textContent = 'Nao encontramos profissionais proximos. Reboque e o caminho mais rapido.';
            if (decisionReboque) decisionReboque.classList.add('is-recommended');
        } else if (data.recomendacao === 'assistencia') {
            if (decisionRecommendation) decisionRecommendation.textContent = 'Recomendamos resolver no local - mais rapido e mais barato';
            if (decisionAssistencia) decisionAssistencia.classList.add('is-recommended');
        } else if (data.recomendacao === 'reboque') {
            if (decisionRecommendation) decisionRecommendation.textContent = 'Recomendamos reboque para este caso.';
            if (decisionReboque) decisionReboque.classList.add('is-recommended');
        } else if (decisionRecommendation) {
            decisionRecommendation.textContent = 'As duas opcoes fazem sentido. Voce decide como seguir.';
        }
        if (decisionFallbackText) {
            decisionFallbackText.textContent = assist.disponivel && data.recomendacao === 'assistencia'
                ? 'Se o profissional nao conseguir resolver no local, voce pode converter para reboque com 21% de desconto.'
                : '';
        }
    }

    function escolherDecisao(choice) {
        if (decisionInput) decisionInput.value = choice;
        [decisionAssistencia, decisionReboque].forEach(function (card) {
            if (!card) return;
            card.classList.toggle('is-selected', card.dataset.decisionChoice === choice);
        });
        solutionInput.value = choice === 'assistencia' ? 'assistencia_local' : 'reboque';
    }

    function ensureDestinationMap() {
        if (!window.L || destinationMap) return;
        destinationMap = L.map('destinationMap', { zoomControl: true }).setView([-22.9068, -43.1729], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(destinationMap);
        setTimeout(function () { destinationMap.invalidateSize(); }, 50);
    }

    function showStage(stage) {
        currentStage = stage;
        var isAddress = stage === 'address';
        var isSituation = stage === 'situation';
        var isDestination = stage === 'destination';
        var isDecision = stage === 'decision';
        var isVehicle = stage === 'vehicle';

        if (addressStage) addressStage.hidden = !isAddress;
        if (situacaoStage) situacaoStage.hidden = !isSituation;
        destinationStage.hidden = !isDestination;
        if (decisionStage) decisionStage.hidden = !isDecision;
        if (vehicleStage) vehicleStage.hidden = !isVehicle;
        if (gpsButton) gpsButton.style.display = isAddress ? '' : 'none';
        if (btnBack) btnBack.style.display = isAddress ? 'none' : '';
        if (btnNext) {
            btnNext.style.display = (isSituation || isDestination || isDecision) ? '' : 'none';
            btnNext.textContent = isDecision ? 'Continuar' : (isDestination ? 'Continuar para o veiculo' : 'Avancar');
        }
        if (btnQuote) btnQuote.style.display = isVehicle ? '' : 'none';
        if (isDestination) {
            box.classList.remove('d-none');
            ensureDestinationMap();
        }
        if (isDecision && !decisionPayload) carregarDecisao();
        document.body.classList.toggle('public-funnel-carousel', !isAddress);
        atualizarDestinoObrigatorio();
    }

    if (originForm && gpsButton) {
        var addressLabel = originForm.querySelector('label.label[for="localizacao"]');
        if (addressLabel) addressLabel.insertAdjacentElement('afterend', gpsButton);
        originForm.insertBefore(addressToggle, originForm.firstElementChild);
    }

    addressToggle.addEventListener('click', function () {
        if (!originForm) return;
        var collapsed = originForm.classList.toggle('is-collapsed');
        addressToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        addressToggle.textContent = collapsed ? 'Expandir busca' : 'Recolher busca';
    });

    if (originForm && originMapPanel) {
        originForm.classList.add('origin-address-card');
        originMapPanel.insertBefore(originForm, originMapPanel.firstElementChild);
    }

    form.insertBefore(destinationStage, decisionStage || vehicleStage);
    destinationMapPanel.appendChild(box);

tipo.addEventListener('change', async function () {
    decisionPayload = null;
    semOficinaProxima = false;
    atualizarDestinoObrigatorio();
    atualizarVisibilidadeVeiculoPodeMover();

    if (!lat || !lng || !lat.value || !lng.value) return;

    var preCheck = await carregarDecisao();
    if (preCheck && preCheck.opcoes_disponiveis && preCheck.opcoes_disponiveis.length === 1
        && preCheck.opcoes_disponiveis[0] === 'reboque') {
        semOficinaProxima = true;
        var field = document.getElementById('fieldVeiculoPodeMover');
        if (field) field.hidden = true;
    } else {
        atualizarVisibilidadeVeiculoPodeMover();
    }
});
document.addEventListener('prequote:type-change', function () {
    decisionPayload = null;
    semOficinaProxima = false;
    atualizarDestinoObrigatorio();
    atualizarVisibilidadeVeiculoPodeMover();
});

    if (btnNext) {
    btnNext.addEventListener('click', function () {
        if (currentStage === 'situation') {
            if (semOficinaProxima) {
                escolherDecisao('reboque');
                if (destino && !destino.value.trim()) {
                    showStage('destination');
                } else {
                    showStage('vehicle');
                }
                return;
            }
            showStage(precisaDestino() ? 'destination' : 'decision');
        } else if (currentStage === 'destination' && destino && destino.value.trim()) {
            if (semOficinaProxima) {
                showStage('vehicle');
            } else {
                showStage('decision');
            }
        } else if (currentStage === 'decision') {
            if (!decisionInput || !decisionInput.value) {
                escolherDecisao((decisionPayload && decisionPayload.recomendacao === 'assistencia') ? 'assistencia' : 'reboque');
            }
            if (decisionInput && decisionInput.value === 'reboque' && destino && !destino.value.trim()) {
                showStage('destination');
                return;
            }
            showStage('vehicle');
        }
    });
}

    if (btnBack) {
        btnBack.addEventListener('click', function () {
            if (currentStage === 'vehicle') {
                showStage('decision');
            } else if (currentStage === 'decision') {
                showStage(precisaDestino() ? 'destination' : 'situation');
            } else if (currentStage === 'destination') {
                showStage('situation');
            } else {
                showStage('address');
            }
        });
    }

    document.addEventListener('prequote:location-confirmed', function () {
        decisionPayload = null;
        showStage('situation');
    });
    document.addEventListener('prequote:go-destination', function () { showStage('destination'); });
    document.addEventListener('prequote:destination-confirmed', function (event) {
        if (!destinationMap || !event.detail) return;
        destinationMap.setView([event.detail.lat, event.detail.lng], 16);
        if (destinationMarker) {
            destinationMarker.setLatLng([event.detail.lat, event.detail.lng]);
        } else {
            destinationMarker = L.marker([event.detail.lat, event.detail.lng], { draggable: true }).addTo(destinationMap);
        }
    });
    [decisionAssistencia, decisionReboque].forEach(function (card) {
        if (!card) return;
        card.addEventListener('click', function () { escolherDecisao(card.dataset.decisionChoice); });
    });

    document.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (card) {
        card.addEventListener('click', function () {
            if (veiculoPodeMover) veiculoPodeMover.value = card.dataset.choiceValue || '1';
        });
    });

    atualizarVisibilidadeVeiculoPodeMover();
    showStage('address');
}());
