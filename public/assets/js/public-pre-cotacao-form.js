(function () {
    'use strict';
    var tipo = document.getElementById('tipo_problema');
    var box = document.getElementById('destinoBox');
    var destino = document.getElementById('destino');
    var situacaoStage = document.getElementById('situacaoStage');
    var vehicleStage = document.getElementById('vehicleStage');
    var addressStage = document.querySelector('.origin-map-composition');
    var btnBack = document.getElementById('btnSituacaoVoltar');
    var btnNext = document.getElementById('btnSituacaoAvancar');
    var btnQuote = document.getElementById('btnCotacao');
    if (!tipo || !box) return;

    var currentStage = 'address';
    function showStage(stage) {
        currentStage = stage;
        var isAddress = stage === 'address';
        var isSituation = stage === 'situation';
        if (addressStage) addressStage.hidden = !isAddress;
        if (situacaoStage) situacaoStage.hidden = !isSituation;
        if (vehicleStage) vehicleStage.hidden = stage !== 'vehicle';
        if (btnBack) btnBack.style.display = isAddress ? 'none' : '';
        if (btnNext) btnNext.style.display = isSituation ? '' : 'none';
        if (btnQuote) btnQuote.style.display = stage === 'vehicle' ? '' : 'none';
        document.body.classList.toggle('public-funnel-carousel', !isAddress);
    }
    function atualizar() {
        var exige = ['colisao', 'reboque'].indexOf(tipo.value) !== -1;
        box.classList.toggle('d-none', !exige);
        if (destino) destino.required = exige;
    }
    tipo.addEventListener('change', atualizar);
    document.addEventListener('prequote:type-change', atualizar);
    showStage('address');
    if (btnNext) btnNext.addEventListener('click', function () { showStage('vehicle'); });
    if (btnBack) btnBack.addEventListener('click', function () { showStage(currentStage === 'vehicle' ? 'situation' : 'address'); });
    document.addEventListener('prequote:location-confirmed', function () { showStage('situation'); });
    atualizar();
}());
