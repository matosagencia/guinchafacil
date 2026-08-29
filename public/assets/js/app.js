(function (window) {
    'use strict';

    class ApiError extends Error {
        constructor(message, status, payload) {
            super(message);
            this.name = 'ApiError';
            this.status = status || 0;
            this.payload = payload || null;
        }
    }

    async function apiFetch(url, options) {
        options = options || {};
        if (window.SessionManager && window.SessionManager.isExpired()) {
            throw new ApiError('SESSION_EXPIRED', 401, null);
        }

        const headers = new Headers(options.headers || {});
        if (!headers.has('Accept')) headers.set('Accept', 'application/json');
        if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');

        const response = await fetch(url, Object.assign({}, options, {
            headers: headers,
            credentials: options.credentials || 'same-origin',
            cache: options.cache || 'no-store'
        }));

        if (response.status === 401) {
            if (window.SessionManager) window.SessionManager.handleUnauthorized();
            throw new ApiError('SESSION_EXPIRED', 401, null);
        }

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.toLowerCase().includes('application/json')) {
            const text = await response.text();
            throw new ApiError('Resposta inválida da API.', response.status, { preview: text.substring(0, 500) });
        }

        let payload;
        try {
            payload = await response.json();
        } catch (error) {
            throw new ApiError('JSON inválido recebido da API.', response.status, null);
        }

        if (!response.ok) {
            throw new ApiError(payload.mensagem || payload.erro || ('HTTP ' + response.status), response.status, payload);
        }
        return payload;
    }

    window.ApiError = ApiError;
    window.apiFetch = apiFetch;
})(window);

(function (window, document) {
    'use strict';

    class AjaxService {
        static post(url, data, callback) {
            window.apiFetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(data => callback(data))
            .catch(error => console.error('Error:', error));
        }

        static get(url, callback) {
            window.apiFetch(url)
            .then(data => callback(data))
            .catch(error => console.error('Error:', error));
        }
    }

    class MaskUtils {
        static digitsOnly(value, maxLength) {
            let digits = String(value || '').replace(/\D/g, '');
            if (maxLength && digits.length > maxLength) {
                digits = digits.slice(0, maxLength);
            }
            return digits;
        }

        static applyCPF(input) {
            if (!input) return;
            input.addEventListener('input', (e) => {
                const v = MaskUtils.digitsOnly(e.target.value, 11);
                if (v.length <= 3) {
                    e.target.value = v;
                    return;
                }
                if (v.length <= 6) {
                    e.target.value = v.replace(/^(\d{3})(\d+)/, '$1.$2');
                    return;
                }
                if (v.length <= 9) {
                    e.target.value = v.replace(/^(\d{3})(\d{3})(\d+)/, '$1.$2.$3');
                    return;
                }
                e.target.value = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*/, '$1.$2.$3-$4');
            });
        }


        static applyPhone(input) {
            if (!input) return;
            input.addEventListener('input', (e) => {
                const v = MaskUtils.digitsOnly(e.target.value, 11);
                if (v.length === 0) {
                    e.target.value = '';
                } else if (v.length <= 2) {
                    e.target.value = '(' + v;
                } else if (v.length <= 6) {
                    e.target.value = v.replace(/^(\d{2})(\d+)/, '($1) $2');
                } else if (v.length <= 10) {
                    e.target.value = v.replace(/^(\d{2})(\d{4})(\d+)/, '($1) $2-$3');
                } else {
                    e.target.value = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
                }
            });
        }
    }

    class AddressService {
        static normalizeAddressForSearch(raw) {
            return String(raw || '')
                .replace(/,\s*Brasil(\s*,\s*Brasil)+/ig, ', Brasil')
                .replace(/,\s*Regi[aã]o Geogr[aá]fica Imediata[^,]*/ig, '')
                .replace(/,\s*Regi[aã]o Metropolitana[^,]*/ig, '')
                .replace(/,\s*Regi[aã]o Geogr[aá]fica Intermedi[aá]ria[^,]*/ig, '')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/(^,|,$)/g, '');
        }

        static searchByCEP(cep, callback) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => response.json())
            .then(data => callback(data))
            .catch(error => console.error('Error:', error));
        }
    }

    class LocationService {
        static getCurrentPosition(successCallback, errorCallback) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(successCallback, errorCallback);
            } else if (typeof errorCallback === 'function') {
                errorCallback('Geolocation is not supported by this browser.');
            }
        }
    }

    class MapManager {
        static init(mapId) {
            if (!window.L) return null;
            const map = L.map(mapId).setView([-23.55052, -46.633308], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
            }).addTo(map);

            // Bug real corrigido: o Leaflet calcula o tamanho interno do mapa
            // (tiles/painel de animação) só na hora do init. Se o viewport
            // muda depois (resize da janela, DevTools responsivo, rotação de
            // celular) sem invalidateSize(), o mapa mantém geometria antiga
            // e "vaza" para fora do container, causando scroll horizontal
            // na página inteira em telas estreitas.
            let resizeTimer = null;
            const onResize = () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    try { map.invalidateSize(); } catch (e) { /* mapa já destruído */ }
                }, 150);
            };
            window.addEventListener('resize', onResize);
            // §A4 — guardados na própria instância pra destroy() conseguir
            // desligar exatamente este listener/observer (não um genérico).
            map._gfResizeHandler = onResize;

            if (window.ResizeObserver) {
                const container = document.getElementById(mapId);
                if (container) {
                    const observer = new ResizeObserver(onResize);
                    observer.observe(container);
                    map._gfResizeObserver = observer;
                }
            }

            return map;
        }

        /**
         * §A4 — sem isso, telas que recriam o container do mapa em loop
         * (ex.: guincho/dashboard.php chamando MapManager.init() de novo a
         * cada evento SSE/poll de nova oferta) vazavam um listener de
         * `resize` e um ResizeObserver por ciclo, presos a uma instância
         * Leaflet já fora do DOM — memória crescendo ao longo de um turno
         * longo do motorista. Chamar antes de recriar o container.
         */
        static destroy(map) {
            if (!map) return;
            if (map._gfResizeHandler) {
                window.removeEventListener('resize', map._gfResizeHandler);
            }
            if (map._gfResizeObserver) {
                map._gfResizeObserver.disconnect();
            }
            try { map.remove(); } catch (e) { /* mapa já removido */ }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        MaskUtils.applyCPF(document.getElementById('cpf'));
        MaskUtils.applyPhone(document.getElementById('telefone'));
        MaskUtils.applyCPF(document.getElementById('f_cpf'));
        MaskUtils.applyPhone(document.getElementById('f_tel'));
    });

    // Exposição compatível com o código legado, sem declarações globais duplicadas.
    // ChatManager/CostCalculator/StatusPoller foram removidas daqui: eram
    // classes vazias sem nenhum uso em todo o projeto (grep confirmou zero
    // referências) — chat, cálculo de custo e polling de status já são
    // implementados diretamente em cada view (guincho/atendimento.php,
    // cliente/pedidostatus.php, cliente-pedido.js). Mantê-las só confundia
    // quem lesse o código achando que havia uma API central não usada.
    window.MapManager = window.MapManager || MapManager;
    window.LocationService = window.LocationService || LocationService;
    window.AjaxService = window.AjaxService || AjaxService;
    window.MaskUtils = window.MaskUtils || MaskUtils;
    window.AddressService = window.AddressService || AddressService;
})(window, document);
