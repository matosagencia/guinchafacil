/**
 * gf-address-picker v2 (mapa grande + painel flutuante)
 * data-role="origem|destino"
 */
(function () {
    'use strict';

    var MIN_CHARS = 3;
    var DEBOUNCE_MS = 280;

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function pickApiBase(el) {
        var base = el.getAttribute('data-api-base');
        if (base) return base;
        var bp = (window.__gfBasePath || '').replace(/\/$/, '');
        return bp + '/api/pre-cotacao/endereco';
    }

    function Picker(root) {
        this.root = root;
        this.role = root.getAttribute('data-role') || 'origem';
        this.apiBase = pickApiBase(root);
        this.input = root.querySelector('[data-ap-input]');
        this.number = root.querySelector('[data-ap-number]');
        this.gps = root.querySelector('[data-ap-gps]');
        this.status = root.querySelector('[data-ap-status]');
        this.panel = root.querySelector('[data-ap-panel]');
        this.toggle = root.querySelector('[data-ap-toggle]');
        this.list = null;
        this.confirmBox = root.querySelector('[data-ap-confirm]');
        this.confirmText = root.querySelector('[data-ap-confirm-text]');
        this.confirmBtn = root.querySelector('[data-ap-confirm-btn]');
        this.map = null;
        this.mapReady = false;
        this.timer = null;
        this.abort = null;
        this.cache = {};
        this.selected = null;
        this._boot();
    }

    Picker.prototype._boot = function () {
        var self = this;
        if (!self.input) return;

        self.input.addEventListener('input', function () { self._onInput(); });
        self.input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') self._closeList();
        });
        if (self.number) {
            self.number.addEventListener('input', function () { self._refreshConfirm(); });
        }
        document.addEventListener('click', function (e) {
            if (!self.root.contains(e.target)) self._closeList();
        });
        if (self.gps) {
            self.gps.addEventListener('click', function () { self._useGps(); });
        }
        if (self.toggle && self.panel) {
            self.toggle.addEventListener('click', function () {
                var collapsed = self.panel.classList.toggle('is-collapsed');
                self.toggle.innerHTML = collapsed
                    ? '<i class="fas fa-chevron-down"></i> Expandir busca'
                    : '<i class="fas fa-chevron-up"></i> Recolher busca';
            });
        }
        if (self.confirmBtn) {
            self.confirmBtn.addEventListener('click', function () { self._confirm(); });
        }

        self._setStatus('Digite a rua ou arraste o mapa para ajustar o pin.');
        self._initMap();
    };

    Picker.prototype._showSpinner = function (msg) {
        if (!this.status) return;
        this.status.innerHTML = String.fromCharCode(60) + 'span class=' + String.fromCharCode(34) + 'gf-ap__spinner' + String.fromCharCode(34) + String.fromCharCode(62) + String.fromCharCode(60) + '/span' + String.fromCharCode(62) + (msg || 'Aguarde...');
        this.status.classList.add('is-loading');
    };

    Picker.prototype._hideSpinner = function (msg) {
        if (!this.status) return;
        this.status.textContent = msg || '';
        this.status.classList.remove('is-loading');
    };

    Picker.prototype._setStatus = function (msg) {
        if (this.status) this.status.textContent = msg || '';
    };

    Picker.prototype._initMap = function () {
        var self = this;
        if (!window.L) {
            self._setStatus('(mapa nao disponivel)');
            return;
        }
        var mapEl = self.root.querySelector('[data-ap-map]');
        if (!mapEl) return;

        var lat = parseFloat(self.root.getAttribute('data-ap-init-lat')) || -22.9068;
        var lng = parseFloat(self.root.getAttribute('data-ap-init-lng')) || -43.1729;

        var map = window.L.map(mapEl, { zoomControl: true, attributionControl: true })
            .setView([lat, lng], 15);
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        self.map = map;
        self.mapReady = true;

        var t = null;
        map.on('moveend', function () {
            if (t) clearTimeout(t);
            t = setTimeout(function () {
                var c = map.getCenter();
                self._reverseAndFill(c.lat, c.lng, true);
            }, 550);
        });

        setTimeout(function () { map.invalidateSize(); }, 150);
        // reverse inicial (silencioso) - popula o pino e o cartao
        self._reverseAndFill(lat, lng, false);
    };

    Picker.prototype._onInput = function () {
        var self = this;
        var q = String(self.input.value || '').replace(/\s+/g, ' ').trim();
        if (self.timer) clearTimeout(self.timer);
        if (self.confirmBox) self.confirmBox.hidden = true;
        console.log('[gf-ap debug] _onInput q=', q, 'len=', q.length); if (q.length < MIN_CHARS) { self._closeList(); return; }
        self.timer = setTimeout(function () { self._fetchSuggest(q); }, DEBOUNCE_MS);
    };

    Picker.prototype._fetchSuggest = function (q) {
        var self = this;
        console.log('[gf-ap debug] _fetchSuggest q=', q); if (self.cache[q]) { self._renderList(self.cache[q]); return; }
        if (self.abort) { try { self.abort.abort(); } catch (e) {} }
        self.abort = ('AbortController' in window) ? new AbortController() : null;
        var url = self.apiBase + '/sugestoes?q=' + encodeURIComponent(q) + '&limit=8';
        fetch(url, { headers: { 'Accept': 'application/json' }, signal: self.abort ? self.abort.signal : undefined })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var items = (j && j.data && j.data.items) || [];
                self.cache[q] = items;
                console.log('[gf-ap debug] _fetchSuggest then items=', items.length); var vAtual = String(self.input.value || '').trim(); if (vAtual.length >= MIN_CHARS) { self._renderList(items); } else { console.log('[gf-ap debug] input limpo, ignora', q); }
            })
            .catch(function () {});
    };

    Picker.prototype._renderList = function (items) {
        var self = this;
        console.log('[gf-ap debug] _renderList items=', (items || []).length); self._closeList();
        if (!items || !items.length) {
            self._setStatus('Nenhum endereco encontrado. Tente incluir a cidade.');
            return;
        }
        var list = document.createElement('div');
        list.className = 'gf-ap__list';
        items.forEach(function (it) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'gf-ap__item';
            btn.innerHTML = '<strong>' + escapeHtml(it.principal || '') + '</strong>'
                          + '<small>' + escapeHtml(it.secundario || '') + '</small>';
            btn.addEventListener('click', function () { self._choose(it); });
            list.appendChild(btn);
        });
        // Insere dentro do painel, embaixo do input
        if (self.panel) self.panel.appendChild(list); else self.root.appendChild(list);
        self.list = list;
    };

    Picker.prototype._closeList = function () {
        if (this.list && this.list.parentNode) this.list.parentNode.removeChild(this.list);
        this.list = null;
    };

    Picker.prototype._choose = function (item) {
        var self = this;
        self.selected = item;
        self._closeList();
        self.input.value = item.principal || '';
        if (item.numero && self.number) self.number.value = item.numero;
        if (self.map && item.lat && item.lng) {
            // Marca pra não disparar moveend → reverse
            self._skipNextMoveend = true;
            self.map.setView([item.lat, item.lng], 17);
        }
        self._setStatus('');
        self._refreshConfirm();
    };

    Picker.prototype._useGps = function () {
        var self = this;
        if (!navigator.geolocation) {
            self._setStatus('Geolocalizacao nao disponivel.');
            return;
        }
        self._setStatus('Obtendo localizacao...');
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var lat = pos.coords.latitude, lng = pos.coords.longitude;
                if (self.map) {
                    self._skipNextMoveend = true;
                    self.map.setView([lat, lng], 17);
                }
                self._reverseAndFill(lat, lng, false);
            },
            function () { self._setStatus('Nao foi possivel obter sua localizacao.'); },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    };

    Picker.prototype._reverseAndFill = function (lat, lng, fromDrag) {
        var self = this;
        if (fromDrag && self._skipNextMoveend) {
            self._skipNextMoveend = false;
            return;
        }
        var url = self.apiBase + '/reverso?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng);
        if (!fromDrag) self._showSpinner('Aguarde...');
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var item = j && j.data && j.data.item;
                if (!item) {
                    item = { principal: 'Local aproximado', secundario: '',
                             lat: lat, lng: lng, numero: null, precisa_numero: true };
                }
                self.selected = item;
                self.input.value = item.principal || '';
                if (item.numero && self.number) self.number.value = item.numero;
                self._hideSpinner(fromDrag ? 'Pino ajustado. Confirme abaixo.' : '');
                self._refreshConfirm();
            })
            .catch(function () {
                if (!fromDrag) self._setStatus('Falha ao identificar o endereco.');
            });
    };

    Picker.prototype._refreshConfirm = function () {
        var self = this;
        var item = self.selected;
        if (!item) { if (self.confirmBox) self.confirmBox.hidden = true; return; }
        var numero = (self.number && self.number.value) ? String(self.number.value).trim() : '';
        var label = item.principal || '';
        if (numero && label.indexOf(numero) === -1) label += ', ' + numero;
        var sub = item.secundario || '';
        if (self.confirmText) {
            self.confirmText.innerHTML = '<strong>' + escapeHtml(label) + '</strong>'
                + (sub ? '<br><span class="gf-ap__confirm-sub">' + escapeHtml(sub) + '</span>' : '');
        }
        if (self.confirmBox) self.confirmBox.hidden = false;
    };

    Picker.prototype._confirm = function () {
        var self = this;
        var item = self.selected;
        if (!item) return;
        var numero = (self.number && self.number.value) ? String(self.number.value).trim() : '';
        var label = item.principal || '';
        if (numero && label.indexOf(numero) === -1) label += ', ' + numero;
        var detail = {
            role: self.role,
            lat: Number(item.lat),
            lng: Number(item.lng),
            label: label,
            numero: numero || (item.numero || ''),
            cidade: item.cidade || '',
            uf: item.uf || ''
        };
        try {
            document.dispatchEvent(new CustomEvent('gf:address-confirmed', { detail: detail }));
        } catch (e) {
            var ev = document.createEvent('CustomEvent');
            ev.initCustomEvent('gf:address-confirmed', true, true, detail);
            document.dispatchEvent(ev);
        }
        self._setStatus('Endereco confirmado.');
    };

    function bootAll() {
        var nodes = document.querySelectorAll('.gf-ap[data-role]');
        Array.prototype.forEach.call(nodes, function (n) {
            if (n.__gfApBooted) return;
            n.__gfApBooted = true;
            new Picker(n);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAll);
    } else {
        bootAll();
    }
    window.gfAddressPicker = { boot: bootAll, Picker: Picker };
})();