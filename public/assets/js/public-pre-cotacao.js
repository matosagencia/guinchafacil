(function () {
    'use strict';

    const gps = document.getElementById('btnGps');
    const status = document.getElementById('gpsStatus');
    const lat = document.getElementById('lat_origem');
    const lng = document.getElementById('lng_origem');
    const address = document.getElementById('localizacao');
    const number = document.getElementById('numero_origem');
    const destination = document.getElementById('destino');
    const destinationNumber = document.getElementById('numero_destino');
    const mapPanel = document.getElementById('originMapPanel');
    const mapAddress = document.getElementById('pinAddressStatus');
    let originMap = null;
    let originMarker = null;

    if (!gps || !status || !lat || !lng) return;

    function extractHouseNumber(value) {
        const matches = String(value || '').match(/(?:^|,|\s)(\d+[A-Za-z]?)(?=\s*(?:,|$))/g);
        return matches && matches.length ? matches[matches.length - 1].replace(/[^0-9A-Za-z]/g, '') : '';
    }

    function composeQuery(input, numberInput) {
        const base = input ? input.value.trim() : '';
        const num = numberInput ? numberInput.value.trim() : '';
        return base && num ? base + ', nº ' + num : base;
    }
    function ensureNoNumberOption(numberInput) { if (!numberInput) return null; const id = numberInput.id + '_sem_numero'; if (document.getElementById(id)) return document.getElementById(id); const wrap = document.createElement('label'); wrap.className = 'address-no-number'; const check = document.createElement('input'); check.type = 'checkbox'; check.id = id; check.name = id; wrap.appendChild(check); wrap.appendChild(document.createTextNode(' Sem número neste local')); numberInput.parentElement.appendChild(wrap); check.addEventListener('change', function () { numberInput.required = !check.checked; numberInput.disabled = check.checked; if (check.checked) numberInput.value = ''; }); return check; }

    function streetOnly(value) { return String(value || '').split(',')[0].replace(/\s+(?:n[ºo°.]?\s*)?\d+[A-Za-z]?\s*$/i, '').trim(); }
    async function reversePin(latValue, lngValue) {
        try { const res = await fetch((document.body.dataset.basePath || '') + '/geocode/public/reverse?lat=' + encodeURIComponent(latValue) + '&lng=' + encodeURIComponent(lngValue), { headers: { Accept: 'application/json' } }); const result = (await res.json()).result || {}; if (result.display_name) address.value = streetOnly(result.display_name); if (result.house_number) { number.value = result.house_number; if (mapAddress) mapAddress.textContent = 'Endereço confirmado pelo pin. Número encontrado: ' + result.house_number + '.'; } else if (mapAddress) mapAddress.textContent = 'Ponto confirmado. Revise o número informado.'; } catch (e) { if (mapAddress) mapAddress.textContent = 'Ponto ajustado no mapa. Revise rua e número antes de continuar.'; }
    }
    function showOriginMap(latValue, lngValue, zoom, announce) {
        if (!mapPanel || !window.L || !Number.isFinite(Number(latValue)) || !Number.isFinite(Number(lngValue))) return;
        mapPanel.hidden = false;
        if (!originMap) { originMap = L.map('originMap', { zoomControl: true }).setView([latValue, lngValue], zoom || 16); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(originMap); } else originMap.setView([latValue, lngValue], Math.max(originMap.getZoom(), zoom || 16));
        if (!originMarker) { originMarker = L.marker([latValue, lngValue], { draggable: true }).addTo(originMap); originMarker.on('dragend', function () { const point = originMarker.getLatLng(); lat.value = point.lat.toFixed(7); lng.value = point.lng.toFixed(7); if (mapAddress) mapAddress.textContent = 'Pin ajustado. Confirmando o endereço…'; reversePin(point.lat, point.lng); }); } else originMarker.setLatLng([latValue, lngValue]);
        setTimeout(function () { originMap.invalidateSize(); }, 50); if (announce !== false) document.dispatchEvent(new Event('prequote:location-confirmed'));
    }

    function setupAddressAutocomplete(input, latInput, lngInput, label, numberInput) {
        if (!input || !latInput || !lngInput) return;
        const wrapper = input.parentElement;
        wrapper.style.position = 'relative';
        const list = document.createElement('div');
        list.className = 'public-address-suggestions col-12';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        wrapper.appendChild(list);
        let timer = null;
        let requestId = 0;
        let selected = false;
        const noNumber = ensureNoNumberOption(numberInput);
        if (noNumber) noNumber.addEventListener('change', function () { selected = false; latInput.value = ''; lngInput.value = ''; clearTimeout(timer); timer = setTimeout(search, 500); });

        function clearList() {
            list.innerHTML = '';
            list.hidden = true;
        }

        function choose(item) {
            input.value = item.display_name || '';
            if (numberInput) { numberInput.disabled = false; numberInput.value = item.house_number || extractHouseNumber(input.value); numberInput.required = true; }
            if (noNumber) noNumber.checked = false;
            latInput.value = item.lat;
            lngInput.value = item.lng;
            selected = true;
            clearList();
            if (label === 'origem') {
                status.textContent = 'Endereço confirmado' + (item.cidade ? ' em ' + item.cidade : '') + '. Agora escolha como resolver.';
                showOriginMap(Number(item.lat), Number(item.lng), 16);
                document.dispatchEvent(new Event('prequote:location-confirmed'));
            } else if (label === 'destino') {
                document.dispatchEvent(new CustomEvent('prequote:destination-confirmed', { detail: { lat: Number(item.lat), lng: Number(item.lng) } }));
            }
        }

        async function search() {
            const query = composeQuery(input, numberInput);
            const hasAddress = Boolean(input && input.value.trim());
            const hasNumber = Boolean((numberInput && numberInput.value.trim()) || (noNumber && noNumber.checked));
            if (query.length < 4 || selected) {
                clearList();
                return;
            }
            if (!hasAddress || !hasNumber) {
                status.textContent = 'Informe também o número da rua para localizar o ponto exato.';
                clearList();
                return;
            }

            const current = ++requestId;
            try {
                const res = await fetch((document.body.dataset.basePath || '') + '/geocode/public?q=' + encodeURIComponent(query), {
                    headers: { Accept: 'application/json' },
                });
                if (current !== requestId) return;
                const payload = await res.json();
                const items = payload.items || [];
                list.innerHTML = '';
                if (!items.length) {
                    clearList();
                    return;
                }
                items.forEach(function (item) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'public-address-suggestion';
                    button.textContent = item.display_name || 'Endereço encontrado';
                    button.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        choose(item);
                    });
                    list.appendChild(button);
                });
                list.hidden = false;
            } catch (e) {
                clearList();
            }
        }

        input.addEventListener('input', function () {
            selected = false;
            latInput.value = '';
            lngInput.value = '';
            clearTimeout(timer);
            timer = setTimeout(search, 500);
        });

        if (numberInput) {
            numberInput.addEventListener('input', function () {
                selected = false;
                latInput.value = '';
                lngInput.value = '';
                clearTimeout(timer);
                timer = setTimeout(search, 500);
            });
        }

        input.addEventListener('blur', function () {
            setTimeout(clearList, 180);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') clearList();
        });
    }

    setupAddressAutocomplete(address, lat, lng, 'origem', number);
    setupAddressAutocomplete(destination, document.getElementById('lat_destino'), document.getElementById('lng_destino'), 'destino', destinationNumber);
    status.textContent = 'Preencha a rua e o número. Se não houver número, marque “Sem número neste local”.';
    showOriginMap(-22.9068, -43.1729, 11, false);

    document.querySelectorAll('[data-choice-group][data-choice-value]').forEach(function (card) {
        card.addEventListener('click', function () {
            const group = card.dataset.choiceGroup;
            const hidden = document.getElementById(group);
            if (hidden) hidden.value = card.dataset.choiceValue;
            document.querySelectorAll('[data-choice-group="' + group + '"]').forEach(function (item) {
                const active = item === card;
                item.classList.toggle('is-selected', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            if (group === 'tipo_problema') { document.dispatchEvent(new Event('prequote:type-change')); if (card.dataset.choiceValue === 'colisao') document.dispatchEvent(new Event('prequote:go-destination')); }
        });
    });

    gps.addEventListener('click', function () {
        if (!navigator.geolocation) {
            status.textContent = 'Seu navegador nao oferece localizacao automatica.';
            return;
        }
        gps.disabled = true;
        status.textContent = 'Obtendo sua localizacao...';
        navigator.geolocation.getCurrentPosition(function (position) {
            lat.value = position.coords.latitude;
            lng.value = position.coords.longitude;
            if (address) address.value = 'Localizacao atual confirmada';
            status.textContent = 'Localizacao confirmada. Agora informe a situacao.';
            showOriginMap(position.coords.latitude, position.coords.longitude, 16);
            gps.disabled = false;
        }, function () {
            status.textContent = 'Nao foi possivel obter o GPS. Autorize a localizacao e tente novamente.';
            gps.disabled = false;
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
    });
}());
