/**
 * public/assets/js/quick-rescue.js
 * Pacote L1.8 — reescrito para seguir o contrato do plano (seção 4.9):
 *   POST /cliente/pedido/rascunho
 *   payload: { endereco_origem, lat_origem, lng_origem, source: 'gps'|'autocomplete' }
 *   resposta: { ok, draft_id, redirect }
 *
 * Regras seguidas aqui:
 *  - o formulário NUNCA troca sua action por string replace — a action é sempre
 *    a rota de rascunho; a navegação pós-sucesso usa o `redirect` que o backend
 *    devolve na resposta;
 *  - digitar endereço e confirmar dispara geocode real via backend (/geocode?q=),
 *    não é mais um "preventDefault + simulação";
 *  - GPS continua usando reverse geocode via backend (/geocode/reverse).
 */
(function () {
    function geocodeText(baseUrl, query) {
        const target = new URL(baseUrl, window.location.origin);
        target.searchParams.set('q', query);
        return fetch(target.toString(), { headers: { 'Accept': 'application/json' } }).then((r) => r.json());
    }

    function reverseGeocode(url, lat, lng) {
        const target = new URL(url, window.location.origin);
        target.searchParams.set('lat', lat);
        target.searchParams.set('lng', lng);
        return fetch(target.toString(), { headers: { 'Accept': 'application/json' } }).then((r) => r.json());
    }

    function postDraft(actionUrl, csrfToken, payload) {
        const body = new URLSearchParams({
            csrf_token: csrfToken,
            endereco_origem: payload.endereco_origem,
            lat_origem: payload.lat_origem != null ? String(payload.lat_origem) : '',
            lng_origem: payload.lng_origem != null ? String(payload.lng_origem) : '',
            source: payload.source,
        });
        return fetch(actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: body.toString(),
        }).then((r) => r.json());
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('[data-quick-rescue]');
        if (!form) return;

        const input = form.querySelector('[data-quick-rescue-input]');
        const gpsBtn = form.querySelector('[data-quick-rescue-gps]');
        const reverseUrl = form.dataset.reverseUrl || '';
        const geocodeUrl = form.dataset.geocodeUrl || '/geocode';
        const draftFieldLat = form.querySelector('[name="lat_origem"]');
        const draftFieldLng = form.querySelector('[name="lng_origem"]');
        const csrfField = form.querySelector('[name="csrf_token"]');
        const submit = form.querySelector('[type="submit"]');
        // A rota de rascunho é sempre esta — nunca é reescrita em runtime.
        const draftActionUrl = form.getAttribute('action');

        let isLocated = false;
        let lastSource = 'autocomplete';
        let submitting = false;

        function updateSubmitButton() {
            if (!submit) return;
            if (isLocated) {
                submit.textContent = 'Pedir Socorro';
                submit.classList.remove('btn-primary');
                submit.classList.add('btn-danger');
            } else {
                submit.textContent = 'Localizar';
                submit.classList.remove('btn-danger');
                submit.classList.add('btn-primary');
            }
        }

        function setCoords(lat, lng) {
            if (draftFieldLat) draftFieldLat.value = lat != null ? Number(lat).toFixed(7) : '';
            if (draftFieldLng) draftFieldLng.value = lng != null ? Number(lng).toFixed(7) : '';
        }

        if (gpsBtn && reverseUrl && navigator.geolocation) {
            gpsBtn.addEventListener('click', () => {
                gpsBtn.disabled = true;
                gpsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                navigator.geolocation.getCurrentPosition(async (pos) => {
                    const { latitude, longitude } = pos.coords;
                    setCoords(latitude, longitude);
                    try {
                        const result = await reverseGeocode(reverseUrl, latitude, longitude);
                        if (result && result.ok && result.result && result.result.display_name && input) {
                            input.value = result.result.display_name;
                            isLocated = true;
                            lastSource = 'gps';
                            updateSubmitButton();
                        }
                    } catch (e) {
                        console.warn('[quick-rescue] falha no reverse geocode:', e);
                    }
                    if (submit) submit.focus();
                    gpsBtn.disabled = false;
                    gpsBtn.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
                }, () => {
                    gpsBtn.disabled = false;
                    gpsBtn.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
                }, { enableHighAccuracy: true, timeout: 10000 });
            });
        }

        if (input) {
            input.addEventListener('input', () => {
                isLocated = false;
                setCoords(null, null);
                updateSubmitButton();
            });
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitting) return;

            const texto = input ? input.value.trim() : '';
            if (texto === '') return;

            if (!isLocated) {
                // Primeiro clique: resolve o endereço digitado via geocoding real do backend.
                submitting = true;
                if (submit) submit.disabled = true;
                try {
                    const result = await geocodeText(geocodeUrl, texto);
                    if (result && result.ok && result.result) {
                        if (input && result.result.display_name) input.value = result.result.display_name;
                        setCoords(result.result.lat, result.result.lng);
                        isLocated = true;
                        lastSource = 'autocomplete';
                        updateSubmitButton();
                    } else {
                        console.warn('[quick-rescue] endereço não encontrado pelo geocoding.');
                    }
                } catch (e) {
                    console.warn('[quick-rescue] falha no geocode do texto digitado:', e);
                } finally {
                    submitting = false;
                    if (submit) submit.disabled = false;
                }
                return;
            }

            // Segundo clique (já localizado): grava o rascunho via AJAX e segue o
            // `redirect` devolvido pelo backend — a action do form nunca muda.
            submitting = true;
            if (submit) submit.disabled = true;
            try {
                const payload = {
                    endereco_origem: texto,
                    lat_origem: draftFieldLat ? draftFieldLat.value || null : null,
                    lng_origem: draftFieldLng ? draftFieldLng.value || null : null,
                    source: lastSource,
                };
                const result = await postDraft(draftActionUrl, csrfField ? csrfField.value : '', payload);
                if (result && result.ok) {
                    window.location.assign(result.redirect || '/cliente/pedido/novo');
                    return;
                }
                console.warn('[quick-rescue] falha ao gravar rascunho:', result && result.erro);
            } catch (e) {
                console.warn('[quick-rescue] falha ao enviar rascunho:', e);
            } finally {
                submitting = false;
                if (submit) submit.disabled = false;
            }
        });
    });
})();
