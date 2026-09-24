(function () {
    'use strict';

    var tipo = document.getElementById('tipo_problema');
    var box = document.getElementById('destinoBox');
    var destino = document.getElementById('destino');
    var situacaoStage = document.getElementById('situacaoStage');
    var vehicleStage = document.getElementById('vehicleStage');
    var addressStage = document.querySelector('.origin-map-composition');
    var gpsButton = document.getElementById('btnGps');
    var originMapPanel = document.getElementById('originMapPanel');
    var originForm = addressStage ? addressStage.querySelector(':scope > .col-12:first-child') : null;
    var form = document.querySelector('form[data-marketing-event="generate_lead"]');
    var btnBack = document.getElementById('btnSituacaoVoltar');
    var btnNext = document.getElementById('btnSituacaoAvancar');
    var btnQuote = document.getElementById('btnCotacao');
    var destinationStage = document.createElement('div');
    var destinationMap = null;
    var destinationMarker = null;
    var currentStage = 'address';

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
    destinationStage.innerHTML = '<div id="destinationMapPanel" class="destination-map-panel"><div class="destination-map-head"><strong>Para onde levar o veículo?</strong><span>Informe e confirme o endereço de entrega.</span></div><div id="destinationMap" class="destination-map"></div></div>';
    var destinationMapPanel = destinationStage.firstElementChild;

    function precisaDestino() {
        return ['colisao', 'reboque'].indexOf(tipo.value) !== -1;
    }

    function atualizarDestinoObrigatorio() {
        var exige = precisaDestino();
        box.classList.toggle('d-none', !exige && currentStage !== 'destination');
        if (destino) destino.required = exige;
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
        var isVehicle = stage === 'vehicle';

        if (addressStage) addressStage.hidden = !isAddress;
        if (situacaoStage) situacaoStage.hidden = !isSituation;
        destinationStage.hidden = !isDestination;
        if (vehicleStage) vehicleStage.hidden = !isVehicle;
        if (gpsButton) gpsButton.style.display = isAddress ? '' : 'none';
        if (btnBack) btnBack.style.display = isAddress ? 'none' : '';
        if (btnNext) {
            btnNext.style.display = (isSituation || isDestination) ? '' : 'none';
            btnNext.textContent = isDestination ? 'Continuar para o veículo' : 'Avançar';
        }
        if (btnQuote) btnQuote.style.display = isVehicle ? '' : 'none';
        if (isDestination) {
            box.classList.remove('d-none');
            ensureDestinationMap();
        }
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

    form.insertBefore(destinationStage, vehicleStage);
    destinationMapPanel.appendChild(box);

    tipo.addEventListener('change', atualizarDestinoObrigatorio);
    document.addEventListener('prequote:type-change', atualizarDestinoObrigatorio);

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if (currentStage === 'situation') {
                showStage(precisaDestino() ? 'destination' : 'vehicle');
            } else if (currentStage === 'destination' && destino && destino.value.trim()) {
                showStage('vehicle');
            }
        });
    }

    if (btnBack) {
        btnBack.addEventListener('click', function () {
            if (currentStage === 'vehicle') {
                showStage(precisaDestino() ? 'destination' : 'situation');
            } else if (currentStage === 'destination') {
                showStage('situation');
            } else {
                showStage('address');
            }
        });
    }

    document.addEventListener('prequote:location-confirmed', function () { showStage('situation'); });
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

    showStage('address');
}());
