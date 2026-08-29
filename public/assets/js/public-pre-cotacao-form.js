(function () {
    'use strict';
    var tipo = document.getElementById('tipo_problema');
    var box = document.getElementById('destinoBox');
    var destino = document.getElementById('destino');
    if (!tipo || !box) return;
    function atualizar() {
        var exige = ['colisao', 'reboque'].indexOf(tipo.value) !== -1;
        box.classList.toggle('d-none', !exige);
        if (destino) destino.required = exige;
    }
    tipo.addEventListener('change', atualizar);
    document.addEventListener('prequote:type-change', atualizar);
    atualizar();
}());
