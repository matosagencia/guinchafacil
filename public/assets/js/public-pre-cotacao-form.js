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
    var addressToggle = document.createElement('button'); addressToggle.type='button'; addressToggle.className='address-toggle'; addressToggle.setAttribute('aria-expanded','true'); addressToggle.textContent='Recolher busca';
    var orientationStage = document.createElement('div'); orientationStage.id = 'orientationStage'; orientationStage.className = 'col-12 choice-fieldset orientation-stage'; orientationStage.hidden = true; orientationStage.innerHTML = '<fieldset><legend class="label">Como podemos orientar você?</legend><p class="muted mb-3">Para este problema, escolha o caminho que faz mais sentido agora.</p><div class="choice-grid"><button type="button" class="choice-card" data-solution="workshop"><strong>Oficina próxima</strong><small>Uma oficina parceira vai até você ou recebe o veículo.</small></button><button type="button" class="choice-card" data-solution="mobile"><strong>Mecânico no local</strong><small>Um profissional avalia a pane onde o veículo está.</small></button><button type="button" class="choice-card" data-solution="tow"><strong>Reboque</strong><small>Leve o veículo para o destino que você escolher.</small></button></div></fieldset>';
    var solutionInput = document.createElement('input'); solutionInput.type = 'hidden'; solutionInput.name = 'solucao_preferida'; solutionInput.id = 'solucao_preferida';
    var form = document.querySelector('form[data-marketing-event="generate_lead"]'); if (form) form.appendChild(solutionInput);
    var btnBack = document.getElementById('btnSituacaoVoltar');
    var btnNext = document.getElementById('btnSituacaoAvancar');
    var btnQuote = document.getElementById('btnCotacao');
    var destinationStage = document.createElement('div'); destinationStage.id='destinationStage'; destinationStage.className='destination-stage'; destinationStage.hidden=true; destinationStage.innerHTML='<div id="destinationMapPanel" class="destination-map-panel"><div class="destination-map-head"><strong>Para onde levar o veículo?</strong><span>Informe e confirme o endereço de entrega.</span></div><div id="destinationMap" class="destination-map"></div></div>';
    var destinationMapPanel = destinationStage.firstElementChild; var destinationMap=null; var destinationMarker=null;
    if (!tipo || !box) return;

    var currentStage = 'address';
    function showStage(stage) {
        currentStage = stage;
        var isAddress = stage === 'address';
        var isSituation = stage === 'situation';
        var isOrientation = stage === 'orientation';
        var isDestination = stage === 'destination';
        if (addressStage) addressStage.hidden = !isAddress;
        if (situacaoStage) situacaoStage.hidden = !isSituation;
        orientationStage.hidden = !isOrientation;
        destinationStage.hidden = !isDestination;
        if (vehicleStage) vehicleStage.hidden = stage !== 'vehicle';
        if (btnBack) btnBack.style.display = isAddress ? 'none' : '';
        if (btnNext) { btnNext.style.display = (isSituation || isOrientation || isDestination) ? '' : 'none'; btnNext.textContent = isOrientation || isDestination ? 'Continuar para o veículo' : 'Avançar'; }
        if (btnQuote) btnQuote.style.display = stage === 'vehicle' ? '' : 'none';
        if (isDestination && box) box.classList.remove('d-none');
        if (gpsButton) gpsButton.style.display = isAddress ? '' : 'none';
        document.body.classList.toggle('public-funnel-carousel', !isAddress);
        if (isDestination && window.L && !destinationMap) { destinationMap=L.map('destinationMap',{zoomControl:true}).setView([-22.9068,-43.1729],11); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors',maxZoom:19}).addTo(destinationMap); setTimeout(function(){destinationMap.invalidateSize();},50); }
    }
    function atualizar() {
        var exige = ['colisao', 'reboque'].indexOf(tipo.value) !== -1;
        box.classList.toggle('d-none', !exige);
        if (destino) destino.required = exige;
    }
    tipo.addEventListener('change', atualizar);
    document.addEventListener('prequote:type-change', atualizar);
    if (originForm && gpsButton) { var addressLabel = originForm.querySelector('label.label[for="localizacao"]'); if (addressLabel) addressLabel.insertAdjacentElement('afterend', gpsButton); originForm.insertBefore(addressToggle, originForm.firstElementChild); }
    addressToggle.addEventListener('click', function () { var collapsed = originForm.classList.toggle('is-collapsed'); addressToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true'); addressToggle.textContent = collapsed ? 'Expandir busca' : 'Recolher busca'; });
    if (originForm && originMapPanel) { originForm.classList.add('origin-address-card'); originMapPanel.insertBefore(originForm, originMapPanel.firstElementChild); }
    if (form && vehicleStage) { form.insertBefore(destinationStage, vehicleStage); destinationMapPanel.appendChild(box); box.classList.remove('d-none'); }
    if (situacaoStage) situacaoStage.parentNode.insertBefore(orientationStage, vehicleStage);
    orientationStage.querySelectorAll('[data-solution]').forEach(function (card) { card.addEventListener('click', function () { solutionInput.value = card.dataset.solution; orientationStage.querySelectorAll('[data-solution]').forEach(function (item) { item.classList.toggle('is-selected', item === card); }); }); });
    showStage('address');
    if (btnNext) btnNext.addEventListener('click', function () { if (currentStage === 'situation') showStage(tipo.value === 'outro' ? 'orientation' : tipo.value === 'colisao' ? 'destination' : 'vehicle'); else if (currentStage === 'orientation' && solutionInput.value) showStage(solutionInput.value === 'tow' ? 'destination' : 'vehicle'); else if (currentStage === 'destination' && destino && destino.value.trim()) showStage('vehicle'); });
    if (btnBack) btnBack.addEventListener('click', function () { showStage(currentStage === 'vehicle' ? (tipo.value === 'colisao' || solutionInput.value === 'tow' ? 'destination' : 'situation') : currentStage === 'destination' ? 'situation' : currentStage === 'orientation' ? 'situation' : 'address'); });
    document.addEventListener('prequote:location-confirmed', function () { showStage('situation'); });
    document.addEventListener('prequote:go-destination', function () { showStage('destination'); });
    document.addEventListener('prequote:destination-confirmed', function (event) { if (destinationMap && event.detail) { destinationMap.setView([event.detail.lat,event.detail.lng],16); if (destinationMarker) destinationMarker.setLatLng([event.detail.lat,event.detail.lng]); else destinationMarker=L.marker([event.detail.lat,event.detail.lng],{draggable:true}).addTo(destinationMap); } });
    atualizar();
}());
