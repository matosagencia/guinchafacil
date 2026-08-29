(function () {
    const cfg = window.CLIENTE_PEDIDO_CONFIG || {};
    const tarifaKm = Number(cfg.tarifaKm || 5);
    const basePath = String(cfg.appBasePath || '');
    const taxaFixa = Number(cfg.taxaFixa || 10);
    const distanciaAlertaKm = Number(cfg.distanciaAlertaKm || 200);

    const el = (id) => document.getElementById(id);
    const safeTrim = (v) => (v || '').trim();
    const normalizeSearchQuery = (value) => {
        let q = safeTrim(value);
        if (!q) return '';
        q = q.replace(/(?:,?\s*Brasil)+$/i, '');
        q = q.replace(/,\s*(Região Geográfica Imediata|Região Geográfica Intermediária|Região Metropolitana) [^,]+/gi, '');
        q = q.replace(/\s*,\s*/g, ', ').replace(/,\s*,+/g, ', ').trim();
        return q;
    };

    function debounce(fn, wait) {
        let t = null;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    function haversine(lat1, lon1, lat2, lon2) {
        const toRad = (deg) => deg * Math.PI / 180;
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
        return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
    }

    class ClientePedidoPage {
        constructor() {
            this.map = null;
            this.mode = null;
            this.originMarker = null;
            this.destinationMarker = null;
            this.iconOrigin = null;
            this.iconDestination = null;
            this.state = {
                origin: { lat: null, lng: null, address: '' },
                destination: { lat: null, lng: null, address: '' },
            };
        }

        init() {
            if (!el('formPedidoCliente') || typeof L === 'undefined') {
                return;
            }
            this.initMap();
            this.bindEvents();
            el('taxaFixaDisplay').value = this.formatCurrency(taxaFixa);
        }

        initMap() {
            this.map = L.map('map').setView([-22.9068, -43.1729], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(this.map);

            this.iconOrigin = L.divIcon({
                className: '',
                iconSize: [18, 18],
                iconAnchor: [9, 9],
                html: '<div style="width:18px;height:18px;border-radius:50%;background:#16a34a;border:3px solid #fff;box-shadow:0 1px 8px rgba(0,0,0,.35)"></div>'
            });
            this.iconDestination = L.divIcon({
                className: '',
                iconSize: [18, 18],
                iconAnchor: [9, 9],
                html: '<div style="width:18px;height:18px;border-radius:50%;background:#dc2626;border:3px solid #fff;box-shadow:0 1px 8px rgba(0,0,0,.35)"></div>'
            });

            this.map.on('click', (e) => {
                if (!this.mode) {
                    return;
                }
                this.setPoint(this.mode, e.latlng.lat, e.latlng.lng);
                this.reverseGeocode(this.mode, e.latlng.lat, e.latlng.lng);
            });
        }

        bindEvents() {
            el('btnUseLocation')?.addEventListener('click', () => this.useMyLocation());
            el('btnMarkOrigin')?.addEventListener('click', () => this.setMapMode('origin'));
            el('btnMarkDestination')?.addEventListener('click', () => this.setMapMode('destination'));
            el('btnOrigemMapaAux')?.addEventListener('click', () => this.setMapMode('origin'));
            el('btnDestinoMapaAux')?.addEventListener('click', () => this.setMapMode('destination'));
            el('btnBuscarOrigem')?.addEventListener('click', () => this.geocodeInput('origin'));
            el('btnBuscarDestino')?.addEventListener('click', () => this.geocodeInput('destination'));
            el('btnUsarOficina')?.addEventListener('click', () => this.applySelectedOficina());
            el('oficinaId')?.addEventListener('change', () => this.applySelectedOficina());

            const origemInput = el('enderecoOrigem');
            const destinoInput = el('enderecoDestino');
            origemInput?.addEventListener('input', debounce(() => this.loadSuggestions('origin'), 450));
            destinoInput?.addEventListener('input', debounce(() => this.loadSuggestions('destination'), 450));
            origemInput?.addEventListener('change', () => this.geocodeInput('origin'));
            destinoInput?.addEventListener('change', () => this.geocodeInput('destination'));

            ['origemNumero', 'origemComplemento', 'destinoNumero', 'destinoComplemento'].forEach((id) => {
                el(id)?.addEventListener('input', () => this.updateSummary());
            });

            el('formPedidoCliente')?.addEventListener('submit', (event) => {
                this.syncHiddenFields();
                if (!this.isReady()) {
                    event.preventDefault();
                    window.alert('Defina origem e destino válidos antes de confirmar o pedido.');
                    return;
                }
                const distancia = Number(el('distanciaKm').value || '0');
                if (distancia > distanciaAlertaKm && el('confirmarLongaDistancia').value !== '1') {
                    event.preventDefault();
                    const ok = window.confirm(`Seu destino está a mais de ${distanciaAlertaKm} km (${distancia.toFixed(1)} km). Deseja continuar mesmo assim?`);
                    if (ok) {
                        el('confirmarLongaDistancia').value = '1';
                        el('formPedidoCliente').requestSubmit();
                    }
                }
            });
        }

        setMapMode(mode) {
            this.mode = mode;
            const label = mode === 'origin' ? 'Clique no mapa para definir a origem.' : 'Clique no mapa para definir o destino.';
            el('mapStatus').textContent = label;
            el('mapModeBadge').textContent = mode === 'origin' ? 'Marcando origem' : 'Marcando destino';
            this.map.getContainer().style.cursor = 'crosshair';
        }

        useMyLocation() {
            if (!navigator.geolocation) {
                window.alert('Geolocalização não suportada neste navegador.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    this.setPoint('origin', lat, lng);
                    this.reverseGeocode('origin', lat, lng);
                    this.map.flyTo([lat, lng], 15, { duration: 0.9 });
                },
                () => window.alert('Não foi possível obter sua localização atual. Verifique a permissão do navegador.')
            );
        }

        async loadSuggestions(type) {
            const input = type === 'origin' ? el('enderecoOrigem') : el('enderecoDestino');
            const datalist = type === 'origin' ? el('listaOrigem') : el('listaDestino');
            const query = normalizeSearchQuery(input?.value);
            if (!query || query.length < 5 || !datalist) {
                return;
            }
            try {
                const res = await fetch(`${basePath}/api/geocode/search?q=${encodeURIComponent(query)}&limit=5`);
                const payload = await res.json();
                const data = payload.items || [];
                datalist.innerHTML = '';
                data.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.display_name;
                    datalist.appendChild(option);
                });
            } catch (err) {
                console.warn('[ClientePedidoPage][loadSuggestions]', err);
            }
        }

        async geocodeInput(type) {
            const input = type === 'origin' ? el('enderecoOrigem') : el('enderecoDestino');
            const query = normalizeSearchQuery(input?.value);
            if (!query || query.length < 5) {
                return;
            }
            try {
                const res = await fetch(`${basePath}/api/geocode/search?q=${encodeURIComponent(query)}&limit=1`);
                const payload = await res.json();
                const data = payload.items || [];
                if (!data[0]) {
                    window.alert(`Não foi possível localizar o endereço de ${type === 'origin' ? 'origem' : 'destino'}.`);
                    return;
                }
                this.setPoint(type, Number(data[0].lat), Number(data[0].lon), data[0].display_name);
                this.map.flyTo([Number(data[0].lat), Number(data[0].lon)], 15, { duration: 0.8 });
            } catch (err) {
                console.warn('[ClientePedidoPage][geocodeInput]', err);
            }
        }

        async reverseGeocode(type, lat, lng) {
            try {
                const res = await fetch(`${basePath}/api/geocode/reverse?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`);
                const payload = await res.json();
                const data = payload.item || null;
                if (data?.display_name) {
                    this.setPoint(type, lat, lng, data.display_name);
                }
            } catch (err) {
                console.warn('[ClientePedidoPage][reverseGeocode]', err);
            }
        }

        applySelectedOficina() {
            const select = el('oficinaId');
            const option = select?.selectedOptions?.[0];
            if (!option || !option.value) {
                return;
            }
            const endereco = option.dataset.endereco || '';
            const lat = Number(option.dataset.lat || '0');
            const lng = Number(option.dataset.lng || '0');
            if (endereco) {
                el('enderecoDestino').value = endereco;
            }
            if (lat && lng) {
                this.setPoint('destination', lat, lng, endereco);
                this.map.flyTo([lat, lng], 14, { duration: 0.8 });
            } else if (endereco) {
                this.geocodeInput('destination');
            }
        }

        setPoint(type, lat, lng, address) {
            const isOrigin = type === 'origin';
            const stateKey = isOrigin ? 'origin' : 'destination';
            this.state[stateKey].lat = Number(lat);
            this.state[stateKey].lng = Number(lng);
            if (address) {
                this.state[stateKey].address = address;
                (isOrigin ? el('enderecoOrigem') : el('enderecoDestino')).value = address;
            }

            const markerRef = isOrigin ? 'originMarker' : 'destinationMarker';
            if (this[markerRef]) {
                this.map.removeLayer(this[markerRef]);
            }
            this[markerRef] = L.marker([lat, lng], {
                icon: isOrigin ? this.iconOrigin : this.iconDestination,
            }).addTo(this.map);

            el(isOrigin ? 'latOrigem' : 'latDestino').value = String(lat);
            el(isOrigin ? 'lngOrigem' : 'lngDestino').value = String(lng);

            this.mode = null;
            this.map.getContainer().style.cursor = '';
            el('mapModeBadge').textContent = 'Ponto definido';
            el('mapStatus').textContent = `${isOrigin ? 'Origem' : 'Destino'} atualizado${address ? ': ' + address : '.'}`;
            this.updateSummary();
        }

        updateSummary() {
            this.syncHiddenFields();
            const { origin, destination } = this.state;
            if (![origin.lat, origin.lng, destination.lat, destination.lng].every((n) => typeof n === 'number' && !Number.isNaN(n))) {
                this.setSubmitState(false);
                return;
            }
            const distancia = haversine(origin.lat, origin.lng, destination.lat, destination.lng);
            const custo = taxaFixa + (distancia * tarifaKm);
            el('distanciaKm').value = distancia.toFixed(2);
            el('distanciaDisplay').value = `${distancia.toFixed(1).replace('.', ',')} km`;
            el('custoDisplay').value = this.formatCurrency(custo);
            const alerta = el('alertaDistancia');
            if (distancia > distanciaAlertaKm) {
                alerta.classList.remove('d-none');
            } else {
                alerta.classList.add('d-none');
                el('confirmarLongaDistancia').value = '0';
            }
            this.setSubmitState(this.isReady());

            if (this.originMarker && this.destinationMarker) {
                this.map.fitBounds([
                    [origin.lat, origin.lng],
                    [destination.lat, destination.lng],
                ], { padding: [50, 50] });
            }
        }

        syncHiddenFields() {
            el('latOrigem').value = this.state.origin.lat ?? '';
            el('lngOrigem').value = this.state.origin.lng ?? '';
            el('latDestino').value = this.state.destination.lat ?? '';
            el('lngDestino').value = this.state.destination.lng ?? '';
        }

        isReady() {
            return safeTrim(el('enderecoOrigem')?.value) !== ''
                && safeTrim(el('enderecoDestino')?.value) !== ''
                && !!el('latOrigem').value
                && !!el('lngOrigem').value
                && !!el('latDestino').value
                && !!el('lngDestino').value;
        }

        setSubmitState(enabled) {
            const button = el('btnSubmitPedido');
            if (!button) {
                return;
            }
            button.disabled = !enabled;
        }

        formatCurrency(value) {
            return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        new ClientePedidoPage().init();
    });
})();
