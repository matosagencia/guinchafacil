/**
 * Cascata marca/modelo + preenchimento do vehicle_brand_id/vehicle_model_id
 * a partir dos endpoints publicos /veiculo-catalogo/*.
 */
(function () {
    'use strict';

    var BP = (document.querySelector('meta[name="base-path"]') || {}).content || '';
    var inputMarca   = document.getElementById('marca');
    var inputModelo  = document.getElementById('modelo');
    var listaMarcas  = document.getElementById('marcas-lista');
    var listaModelos = document.getElementById('modelos-lista');
    var hidBrand     = document.getElementById('vehicle_brand_id');
    var hidModel     = document.getElementById('vehicle_model_id');
    var selectAno    = document.getElementById('ano');
    var inputPlaca   = document.getElementById('placa');

    if (!inputMarca || !inputModelo) return;

    // Carrega marcas no datalist
    fetch(BP + '/veiculo-catalogo/marcas', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (marcas) {
            listaMarcas.innerHTML = '';
            marcas.forEach(function (m) {
                var opt = document.createElement('option');
                opt.value = m.name;
                opt.dataset.id = m.id;
                listaMarcas.appendChild(opt);
            });
        })
        .catch(function (e) { console.warn('[cascata] marcas', e); });

    // Quando muda a marca, carrega modelos
    inputMarca.addEventListener('input', function () {
        var escolhida = null;
        Array.prototype.forEach.call(listaMarcas.options, function (opt) {
            if (opt.value === inputMarca.value) escolhida = opt;
        });
        hidBrand.value = escolhida ? escolhida.dataset.id : '';
        inputModelo.value = '';
        hidModel.value = '';
        listaModelos.innerHTML = '';
        inputModelo.disabled = true;

        if (!escolhida) return;

        inputModelo.disabled = false;
        fetch(BP + '/veiculo-catalogo/modelos?marca_id=' + encodeURIComponent(escolhida.dataset.id), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (modelos) {
                listaModelos.innerHTML = '';
                modelos.forEach(function (mo) {
                    var opt = document.createElement('option');
                    opt.value = mo.name;
                    opt.dataset.id = mo.id;
                    listaModelos.appendChild(opt);
                });
            })
            .catch(function (e) { console.warn('[cascata] modelos', e); });
    });

    inputModelo.addEventListener('input', function () {
        var escolhido = null;
        Array.prototype.forEach.call(listaModelos.options, function (opt) {
            if (opt.value === inputModelo.value) escolhido = opt;
        });
        hidModel.value = escolhido ? escolhido.dataset.id : '';
    });

    // Placa: forca maiusculas + aceita Mercosul (ABC1D23) e antigo (ABC1234)
    if (inputPlaca) {
        inputPlaca.addEventListener('input', function () {
            inputPlaca.value = inputPlaca.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
        });
    }
})();