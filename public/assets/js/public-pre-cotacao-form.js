(function () {
    'use strict';
    var tipo = document.getElementById('tipo_problema');
    var box = document.getElementById('destinoBox');
    var destino = document.getElementById('destino');
    var situacaoStage = document.getElementById('situacaoStage');
    var vehicleStage = document.getElementById('vehicleStage');
    var btnBack = document.getElementById('btnSituacaoVoltar');
    var btnNext = document.getElementById('btnSituacaoAvancar');
    var btnQuote = document.getElementById('btnCotacao');
    if (!tipo || !box) return;

    function showStage(stage) {
        var isSituation = stage === 'situation';
        if (situacaoStage) situacaoStage.hidden = !isSituation;
        if (vehicleStage) vehicleStage.hidden = isSituation;
        if (btnBack) btnBack.style.display = isSituation ? 'none' : '';
        if (btnNext) btnNext.style.display = isSituation ? '' : 'none';
        if (btnQuote) btnQuote.style.display = isSituation ? 'none' : '';
        var target = isSituation ? situacaoStage : vehicleStage;
        if (target) window.scrollTo({ top: target.offsetTop - 20, behavior: 'smooth' });
    }
    function atualizar() {
        var exige = ['colisao', 'reboque'].indexOf(tipo.value) !== -1;
        box.classList.toggle('d-none', !exige);
        if (destino) destino.required = exige;
    }
    tipo.addEventListener('change', atualizar);
    document.addEventListener('prequote:type-change', atualizar);
    if (situacaoStage) situacaoStage.hidden = true;
    if (vehicleStage) vehicleStage.hidden = true;
    if (btnQuote) btnQuote.style.display = 'none';
    if (btnNext) btnNext.addEventListener('click', function () { showStage('vehicle'); });
    if (btnBack) btnBack.addEventListener('click', function () { showStage('situation'); });
    document.addEventListener('prequote:location-confirmed', function () { showStage('situation'); });
    atualizar();
}());
