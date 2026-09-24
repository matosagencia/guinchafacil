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
    var orientationStage = document.createElement('div'); orientationStage.id = 'orientationStage'; orientationStage.className = 'col-12 choice-fieldset orientation-stage'; orientationStage.hidden = true; orientationStage.innerHTML = '<fieldset><legend class="label">Como podemos orientar você?</legend><p class="muted mb-3">Para este problema, escolha o caminho que faz mais sentido agora.</p><div class="choice-grid"><button type="button" class="choice-card" data-solution="workshop"><strong>Oficina próxima</strong><small>Uma oficina parceira vai até você ou recebe o veículo.</small></button><button type="button" class="choice-card" data-solution="mobile"><strong>Mecânico no local</strong><small>Um profissional avalia a pane onde o veículo está.</small></button><button type="button" class="choice-card" data-solution="tow"><strong>Reboque</strong><small>Leve o veículo para o destino que você escolher.</small></button></div></fieldset>';
    var solutionInput = document.createElement('input'); solutionInput.type = 'hidden'; solutionInput.name = 'solucao_preferida'; solutionInput.id = 'solucao_preferida';
    var form = document.querySelector('form[data-marketing-event="generate_lead"]'); if (form) form.appendChild(solutionInput);
    var btnBack = document.getElementById('btnSituacaoVoltar');
    var btnNext = document.getElementById('btnSituacaoAvancar');
    var btnQuote = document.getElementById('btnCotacao');
    if (!tipo || !box) return;

    var currentStage = 'address';
    function showStage(stage) {
        currentStage = stage;
        var isAddress = stage === 'address';
        var isSituation = stage === 'situation';
        var isOrientation = stage === 'orientation';
        if (addressStage) addressStage.hidden = !isAddress;
        if (situacaoStage) situacaoStage.hidden = !isSituation;
        orientationStage.hidden = !isOrientation;
        if (vehicleStage) vehicleStage.hidden = stage !== 'vehicle';
        if (btnBack) btnBack.style.display = isAddress ? 'none' : '';
        if (btnNext) { btnNext.style.display = (isSituation || isOrientation) ? '' : 'none'; btnNext.textContent = isOrientation ? 'Continuar para o veículo' : 'Avançar'; }
        if (btnQuote) btnQuote.style.display = stage === 'vehicle' ? '' : 'none';
        if (gpsButton) gpsButton.style.display = isAddress ? '' : 'none';
        document.body.classList.toggle('public-funnel-carousel', !isAddress);
    }
    function atualizar() {
        var exige = ['colisao', 'reboque'].indexOf(tipo.value) !== -1;
        box.classList.toggle('d-none', !exige);
        if (destino) destino.required = exige;
    }
    tipo.addEventListener('change', atualizar);
    document.addEventListener('prequote:type-change', atualizar);
    if (originForm && gpsButton) { var addressLabel = originForm.querySelector('label.label[for="localizacao"]'); if (addressLabel) addressLabel.insertAdjacentElement('afterend', gpsButton); }
    if (originForm && originMapPanel) { originForm.classList.add('origin-address-card'); originMapPanel.insertBefore(originForm, originMapPanel.firstElementChild); }
    if (situacaoStage) situacaoStage.parentNode.insertBefore(orientationStage, vehicleStage);
    orientationStage.querySelectorAll('[data-solution]').forEach(function (card) { card.addEventListener('click', function () { solutionInput.value = card.dataset.solution; orientationStage.querySelectorAll('[data-solution]').forEach(function (item) { item.classList.toggle('is-selected', item === card); }); }); });
    showStage('address');
    if (btnNext) btnNext.addEventListener('click', function () { if (currentStage === 'situation') showStage(tipo.value === 'outro' ? 'orientation' : 'vehicle'); else if (currentStage === 'orientation' && solutionInput.value) showStage('vehicle'); });
    if (btnBack) btnBack.addEventListener('click', function () { showStage(currentStage === 'vehicle' ? (tipo.value === 'outro' ? 'orientation' : 'situation') : currentStage === 'orientation' ? 'situation' : 'address'); });
    document.addEventListener('prequote:location-confirmed', function () { showStage('situation'); });
    atualizar();
}());
