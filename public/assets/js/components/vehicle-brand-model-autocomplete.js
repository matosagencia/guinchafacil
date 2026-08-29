/**
 * §CATALOGO-VISUAL-01 (02/08/2026): autocomplete de marca/modelo de veículo.
 * Decisão do usuário: campo de busca com sugestão (não grid visual), com o
 * modelo filtrado pelo fabricante já escolhido — mesmo padrão usado tanto no
 * cadastro de veículo do CLIENTE quanto no cadastro de CAMINHÃO do
 * guincheiro (ver /veiculo-catalogo/marcas e /veiculo-catalogo/modelos,
 * endpoints públicos porque o registro do guincheiro acontece antes do
 * login existir).
 *
 * Uso:
 *   initVehicleBrandModelAutocomplete({
 *     baseUrl: '',                 // BASE_PATH do app (pode ser '')
 *     marcaInput: document.getElementById('marcaInput'),
 *     marcaIdInput: document.getElementById('marcaIdInput'),
 *     modeloInput: document.getElementById('modeloInput'),
 *     modeloIdInput: document.getElementById('modeloIdInput'),
 *   });
 *
 * Não bloqueia texto livre: se a marca/modelo não estiver no catálogo, o
 * usuário digita normalmente e o campo de id correspondente fica vazio —
 * o catálogo ainda está sendo populado aos poucos pelo admin.
 */
(function (global) {
    'use strict';

    function normalizar(texto) {
        return String(texto == null ? '' : texto)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function criarDropdown(inputEl) {
        var pai = inputEl.parentElement;
        if (getComputedStyle(pai).position === 'static') {
            pai.style.position = 'relative';
        }
        var dropdown = document.createElement('div');
        dropdown.className = 'vehicle-autocomplete-dropdown';
        dropdown.style.cssText = 'display:none;position:absolute;left:0;right:0;top:100%;z-index:1000;'
            + 'background:#fff;border:1px solid #ccd3da;border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.12);'
            + 'max-height:240px;overflow-y:auto;margin-top:2px;';
        pai.appendChild(dropdown);
        return dropdown;
    }

    function renderItens(dropdown, itens, tipo, onSelecionar) {
        dropdown.innerHTML = '';
        if (itens.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        itens.slice(0, 30).forEach(function (item) {
            var linha = document.createElement('button');
            linha.type = 'button';
            linha.className = 'vehicle-autocomplete-item';
            linha.style.cssText = 'display:flex;align-items:center;gap:8px;width:100%;text-align:left;'
                + 'padding:8px 12px;border:0;background:transparent;cursor:pointer;font-size:.92rem;';
            linha.onmouseenter = function () { linha.style.background = '#f2f5f8'; };
            linha.onmouseleave = function () { linha.style.background = 'transparent'; };

            if (tipo === 'marca') {
                var avatar = document.createElement('span');
                if (item.logo_path) {
                    avatar.innerHTML = '<img src="' + item.logo_path + '" alt="" style="width:24px;height:24px;object-fit:contain;">';
                } else {
                    var inicial = (item.name || '?').trim().charAt(0).toUpperCase();
                    avatar.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;'
                        + 'border-radius:6px;background:#2563eb;color:#fff;font-weight:700;font-size:.72rem;';
                    avatar.textContent = inicial;
                }
                linha.appendChild(avatar);
            } else if (item.image_path) {
                var img = document.createElement('img');
                img.src = item.image_path;
                img.alt = '';
                img.style.cssText = 'width:32px;height:22px;object-fit:contain;';
                linha.appendChild(img);
            }

            var texto = document.createElement('span');
            texto.textContent = item.name;
            linha.appendChild(texto);

            linha.addEventListener('click', function () { onSelecionar(item); });
            dropdown.appendChild(linha);
        });
        dropdown.style.display = 'block';
    }

    function initVehicleBrandModelAutocomplete(opts) {
        var baseUrl = opts.baseUrl || '';
        var marcaInput = opts.marcaInput;
        var marcaIdInput = opts.marcaIdInput;
        var modeloInput = opts.modeloInput;
        var modeloIdInput = opts.modeloIdInput;
        if (!marcaInput || !marcaIdInput || !modeloInput || !modeloIdInput) return;

        var marcas = [];
        var marcasCarregadas = false;
        var modelos = [];
        var modeloBaseId = null;

        var dropdownMarca = criarDropdown(marcaInput);
        var dropdownModelo = criarDropdown(modeloInput);

        function carregarMarcas(callback) {
            if (marcasCarregadas) { callback(); return; }
            fetch(baseUrl + '/veiculo-catalogo/marcas')
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    marcas = Array.isArray(data) ? data : [];
                    marcasCarregadas = true;
                    callback();
                })
                .catch(function () { marcas = []; marcasCarregadas = true; callback(); });
        }

        function carregarModelos(marcaId, callback) {
            if (!marcaId) { modelos = []; modeloBaseId = null; callback(); return; }
            fetch(baseUrl + '/veiculo-catalogo/modelos?marca_id=' + encodeURIComponent(marcaId))
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    modelos = Array.isArray(data) ? data : [];
                    modeloBaseId = marcaId;
                    callback();
                })
                .catch(function () { modelos = []; modeloBaseId = marcaId; callback(); });
        }

        function selecionarMarca(item) {
            marcaInput.value = item.name;
            marcaIdInput.value = item.id;
            dropdownMarca.style.display = 'none';
            modeloInput.value = '';
            modeloIdInput.value = '';
            carregarModelos(item.id, function () {});
        }

        function selecionarModelo(item) {
            modeloInput.value = item.name;
            modeloIdInput.value = item.id;
            dropdownModelo.style.display = 'none';
        }

        marcaInput.addEventListener('input', function () {
            marcaIdInput.value = '';
            var termo = normalizar(marcaInput.value);
            carregarMarcas(function () {
                if (termo === '') { dropdownMarca.style.display = 'none'; return; }
                var filtradas = marcas.filter(function (m) { return normalizar(m.name).indexOf(termo) !== -1; });
                renderItens(dropdownMarca, filtradas, 'marca', selecionarMarca);
            });
        });

        marcaInput.addEventListener('focus', function () {
            if (marcaInput.value.trim() === '') return;
            marcaInput.dispatchEvent(new Event('input'));
        });

        modeloInput.addEventListener('input', function () {
            modeloIdInput.value = '';
            var termo = normalizar(modeloInput.value);
            if (modeloBaseId !== (marcaIdInput.value ? Number(marcaIdInput.value) : null)) {
                carregarModelos(marcaIdInput.value ? Number(marcaIdInput.value) : null, function () {
                    if (termo === '' || modelos.length === 0) { dropdownModelo.style.display = 'none'; return; }
                    var filtrados = modelos.filter(function (m) { return normalizar(m.name).indexOf(termo) !== -1; });
                    renderItens(dropdownModelo, filtrados, 'modelo', selecionarModelo);
                });
                return;
            }
            if (termo === '' || modelos.length === 0) { dropdownModelo.style.display = 'none'; return; }
            var filtrados = modelos.filter(function (m) { return normalizar(m.name).indexOf(termo) !== -1; });
            renderItens(dropdownModelo, filtrados, 'modelo', selecionarModelo);
        });

        modeloInput.addEventListener('focus', function () {
            if (modeloInput.value.trim() === '') return;
            modeloInput.dispatchEvent(new Event('input'));
        });

        document.addEventListener('click', function (event) {
            if (!dropdownMarca.contains(event.target) && event.target !== marcaInput) dropdownMarca.style.display = 'none';
            if (!dropdownModelo.contains(event.target) && event.target !== modeloInput) dropdownModelo.style.display = 'none';
        });

        // Pré-carrega modelos se o formulário já vier com uma marca escolhida
        // (edição de um veículo/caminhão já cadastrado).
        if (marcaIdInput.value) {
            carregarModelos(Number(marcaIdInput.value), function () {});
        }
    }

    global.initVehicleBrandModelAutocomplete = initVehicleBrandModelAutocomplete;
})(window);
