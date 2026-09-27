(function () {
    'use strict';
    var situacaoStage = document.getElementById('situacaoStage');
    var sintomaStage = document.getElementById('sintomaStage');
    var tipoInput = document.getElementById('tipo_problema');
    var btnAvancar = document.getElementById('btnSituacaoAvancar');
    if (!situacaoStage || !sintomaStage || !tipoInput || !btnAvancar) return;

    var MAPA = { pneu: 'pneu', eletrica: 'eletrica', bateria: 'bateria', mecanica: 'mecanica', chaveiro: 'chaveiro' };

    situacaoStage.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (card) {
        card.addEventListener('click', function () {
            var s = card.getAttribute('data-choice-value');
            situacaoStage.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                c.classList.remove('is-selected');
                c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected');
            card.setAttribute('aria-pressed', 'true');
            tipoInput.value = s;
            if (s === 'me_orientem' || s === 'resolver_local' || s === 'levar_carro') {
                situacaoStage.hidden = true;
                sintomaStage.hidden = false;
            }
        });
    });

    sintomaStage.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (card) {
        card.addEventListener('click', function () {
            var s = card.getAttribute('data-choice-value') || '';
            var tipo = MAPA[s] || 'mecanica';
            sintomaStage.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                c.classList.remove('is-selected');
                c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected');
            card.setAttribute('aria-pressed', 'true');
            var sInput = document.getElementById('sintoma');
            if (sInput) sInput.value = s;
            tipoInput.value = tipo;
            sintomaStage.hidden = true;
            situacaoStage.hidden = false;
            tipoInput.dispatchEvent(new Event('change', { bubbles: true }));
            setTimeout(function () { btnAvancar.click(); }, 60);
        });
    });
})();