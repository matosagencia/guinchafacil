/**
<<<<<<< HEAD
 * Checkout veiculo:
 *  - cascata marca/modelo via /veiculo-catalogo/*
 *  - toggle form novo/existente
 *  - anti-double-submit
=======
 * Checkout veiculo — cascata + toggle form novo/existente + debug.
 * Versao: v10 (2026-09-29)
>>>>>>> main
 */
(function () {
    'use strict';

<<<<<<< HEAD
    var BP = '';
=======
    var DEBUG = true;
    function log() {
        if (DEBUG) {
            var args = Array.prototype.slice.call(arguments);
            args.unshift('[checkout-veiculo]');
            console.log.apply(console, args);
        }
    }

    log('iniciado');
>>>>>>> main

    // ============================================================
    // 1) Toggle form novo/existente
    // ============================================================
    var formNovo = document.getElementById('form-novo-veiculo');
    var divisor  = document.getElementById('divisor-novo');
    var radiosVeiculo = document.querySelectorAll('input[name="veiculo_id"]');
<<<<<<< HEAD
    var camposFormNovo = formNovo ? formNovo.querySelectorAll('input, select, textarea') : [];

    // Guarda quais eram required originalmente
    camposFormNovo.forEach(function (el) {
        if (el.hasAttribute('required') && el.type !== 'hidden') {
            el.dataset.wasRequired = '1';
        }
    });

    function atualizarSelecaoVisual() {
=======

    log('formNovo:', !!formNovo, 'divisor:', !!divisor, 'radios:', radiosVeiculo.length);

    if (formNovo) {
        // Marca os required originais
        formNovo.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.hasAttribute('required') && el.type !== 'hidden') {
                el.dataset.wasRequired = '1';
            }
        });
        log('required originais marcados');
    }

    function atualizarVisual() {
>>>>>>> main
        document.querySelectorAll('.veiculo-opcao').forEach(function (lbl) {
            var r = lbl.querySelector('input[type=radio]');
            if (r && r.checked) lbl.classList.add('selecionado');
            else lbl.classList.remove('selecionado');
        });
    }

<<<<<<< HEAD
    function toggleFormNovo() {
        atualizarSelecaoVisual();
        if (!formNovo || !radiosVeiculo.length) return;

        var sel = document.querySelector('input[name="veiculo_id"]:checked');
        var ehNovo = !sel || sel.value === '0';

        camposFormNovo.forEach(function (el) {
=======
    function aplicarToggle() {
        if (!formNovo) { log('sem formNovo, abort'); return; }

        atualizarVisual();

        var sel = document.querySelector('input[name="veiculo_id"]:checked');
        var valor = sel ? sel.value : '0';
        var ehNovo = (valor === '0');

        log('toggle: valor selecionado =', valor, 'ehNovo =', ehNovo);

        formNovo.querySelectorAll('input, select, textarea').forEach(function (el) {
>>>>>>> main
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
<<<<<<< HEAD
    }

    radiosVeiculo.forEach(function (r) {
        r.addEventListener('change', toggleFormNovo);
    });
    toggleFormNovo();
=======

        var reqVisiveis = 0;
        formNovo.querySelectorAll('[required]').forEach(function (el) {
            if (!el.disabled) reqVisiveis++;
        });
        log('required visiveis:', reqVisiveis, 'display:', formNovo.style.display);
    }

    if (radiosVeiculo.length) {
        document.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'veiculo_id') {
                log('change detectado:', e.target.value);
                aplicarToggle();
            }
        });
    }

    aplicarToggle();
>>>>>>> main

    // ============================================================
    // 2) Cascata marca/modelo
    // ============================================================
    var inputMarca   = document.getElementById('marca');
    var inputModelo  = document.getElementById('modelo');
    var listaMarcas  = document.getElementById('marcas-lista');
    var listaModelos = document.getElementById('modelos-lista');
    var hidBrand     = document.getElementById('vehicle_brand_id');
    var hidModel     = document.getElementById('vehicle_model_id');
<<<<<<< HEAD

    if (inputMarca && inputModelo && listaMarcas && listaModelos) {
        fetch(BP + '/veiculo-catalogo/marcas', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (marcas) {
=======
    var inputPlaca   = document.getElementById('placa');

    if (inputMarca && inputModelo && listaMarcas && listaModelos) {
        log('carregando marcas...');
        fetch('/veiculo-catalogo/marcas', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (marcas) {
                log('marcas carregadas:', marcas.length);
>>>>>>> main
                listaMarcas.innerHTML = '';
                marcas.forEach(function (m) {
                    var opt = document.createElement('option');
                    opt.value = m.name;
                    opt.dataset.id = m.id;
                    listaMarcas.appendChild(opt);
                });
            })
<<<<<<< HEAD
            .catch(function (e) { console.warn('[cascata] marcas', e); });
=======
            .catch(function (e) { console.warn('[checkout-veiculo] marcas', e); });
>>>>>>> main

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

<<<<<<< HEAD
            inputModelo.disabled = false;
            fetch(BP + '/veiculo-catalogo/modelos?marca_id=' + encodeURIComponent(escolhida.dataset.id), {
=======
            log('marca escolhida:', escolhida.value, 'id:', escolhida.dataset.id);
            inputModelo.disabled = false;
            fetch('/veiculo-catalogo/modelos?marca_id=' + encodeURIComponent(escolhida.dataset.id), {
>>>>>>> main
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (modelos) {
<<<<<<< HEAD
=======
                    log('modelos carregados:', modelos.length);
>>>>>>> main
                    listaModelos.innerHTML = '';
                    modelos.forEach(function (mo) {
                        var opt = document.createElement('option');
                        opt.value = mo.name;
                        opt.dataset.id = mo.id;
                        listaModelos.appendChild(opt);
                    });
                })
<<<<<<< HEAD
                .catch(function (e) { console.warn('[cascata] modelos', e); });
=======
                .catch(function (e) { console.warn('[checkout-veiculo] modelos', e); });
>>>>>>> main
        });

        inputModelo.addEventListener('input', function () {
            var escolhido = null;
            Array.prototype.forEach.call(listaModelos.options, function (opt) {
                if (opt.value === inputModelo.value) escolhido = opt;
            });
            if (hidModel) hidModel.value = escolhido ? escolhido.dataset.id : '';
        });

<<<<<<< HEAD
        var inputPlaca = document.getElementById('placa');
=======
>>>>>>> main
        if (inputPlaca) {
            inputPlaca.addEventListener('input', function () {
                inputPlaca.value = inputPlaca.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
            });
        }
<<<<<<< HEAD
    }

    // ============================================================
    // 3) Anti-double-submit
=======
    } else {
        log('campos de marca/modelo nao encontrados');
    }

    // ============================================================
    // 3) Anti-double-submit + debug do submit
>>>>>>> main
    // ============================================================
    var form = document.querySelector('form.card-panel');
    if (form) {
        form.addEventListener('submit', function (e) {
<<<<<<< HEAD
=======
            log('submit disparado');
            var fd = new FormData(form);
            var entries = {};
            fd.forEach(function (v, k) { entries[k] = v; });
            log('payload:', entries);

>>>>>>> main
            var btn = document.getElementById('btn-continuar');
            if (btn && btn.disabled) { e.preventDefault(); return; }
            if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
        });
    }
})();