/**
 * Checkout veiculo:
 *  - cascata marca/modelo via /veiculo-catalogo/*
 *  - toggle form novo/existente
 *  - anti-double-submit
 */
(function () {
    'use strict';

    var BP = '';

    // ============================================================
    // 1) Toggle form novo/existente
    // ============================================================
    var formNovo = document.getElementById('form-novo-veiculo');
    var divisor  = document.getElementById('divisor-novo');
    var radiosVeiculo = document.querySelectorAll('input[name="veiculo_id"]');
    var camposFormNovo = formNovo ? formNovo.querySelectorAll('input, select, textarea') : [];

    // Guarda quais eram required originalmente
    camposFormNovo.forEach(function (el) {
        if (el.hasAttribute('required') && el.type !== 'hidden') {
            el.dataset.wasRequired = '1';
        }
    });

    function atualizarSelecaoVisual() {
        document.querySelectorAll('.veiculo-opcao').forEach(function (lbl) {
            var r = lbl.querySelector('input[type=radio]');
            if (r && r.checked) lbl.classList.add('selecionado');
            else lbl.classList.remove('selecionado');
        });
    }

    function toggleFormNovo() {
        atualizarSelecaoVisual();
        if (!formNovo || !radiosVeiculo.length) return;

        var sel = document.querySelector('input[name="veiculo_id"]:checked');
        var ehNovo = !sel || sel.value === '0';

        camposFormNovo.forEach(function (el) {
            if (el.type === 'hidden') return;
            if (ehNovo) {
                el.disabled = false;
                if (el.dataset.wasRequired === '1') el.required = true;
            } else {
                el.disabled = true;
                el.required = false;
            }
        });

        formNovo.style.display = ehNovo ? '' : 'none';
        if (divisor) divisor.style.display = ehNovo ? '' : 'none';
    }

    radiosVeiculo.forEach(function (r) {
        r.addEventListener('change', toggleFormNovo);
    });
    toggleFormNovo();

    // ============================================================
    // 2) Cascata marca/modelo
    // ============================================================
    var inputMarca   = document.getElementById('marca');
    var inputModelo  = document.getElementById('modelo');
    var listaMarcas  = document.getElementById('marcas-lista');
    var listaModelos = document.getElementById('modelos-lista');
    var hidBrand     = document.getElementById('vehicle_brand_id');
    var hidModel     = document.getElementById('vehicle_model_id');

    if (inputMarca && inputModelo && listaMarcas && listaModelos) {
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

        inputMarca.addEventListener('input', function () {
            var escolhida = null;
            Array.prototype.forEach.call(listaMarcas.options, function (opt) {
                if (opt.value === inputMarca.value) escolhida = opt;
            });
            if (hidBrand) hidBrand.value = escolhida ? escolhida.dataset.id : '';
            inputModelo.value = '';
            if (hidModel) hidModel.value = '';
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
            if (hidModel) hidModel.value = escolhido ? escolhido.dataset.id : '';
        });

        var inputPlaca = document.getElementById('placa');
        if (inputPlaca) {
            inputPlaca.addEventListener('input', function () {
                inputPlaca.value = inputPlaca.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
            });
        }
    }

    // ============================================================
    // 3) Anti-double-submit
    // ============================================================
    var form = document.querySelector('form.card-panel');
    if (form) {
        form.addEventListener('submit', function (e) {
            var btn = document.getElementById('btn-continuar');
            if (btn && btn.disabled) { e.preventDefault(); return; }
            if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
        });
    }
})();