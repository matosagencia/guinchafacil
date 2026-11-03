/* public/assets/js/pre-cotacao-map.js
   §23 · Mapa do aside + pino vermelho + rota via OSRM proxy. v20261016-6. */

(function () {
    'use strict';
    if (!window.L) return;

    var el = document.getElementById('gfMap');
    if (!el) return;

    var latInput = document.getElementById('lat_origem');
    var lngInput = document.getElementById('lng_origem');
    var lat = parseFloat(latInput && latInput.value) || -22.8967;
    var lng = parseFloat(lngInput && lngInput.value) || -43.1230;

    var map = L.map(el, {
        center: [lat, lng],
        zoom: 13,
        zoomControl: true,
        scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap',
    }).addTo(map);

    var origemMarker = L.marker([lat, lng], { draggable: true }).addTo(map);
    var destinoMarker = null;
    var rotaLine = null;

    function destinoEmEdicao() {
        var etapa = document.querySelector('#gfPreqForm .gf-step[data-step="5"]');
        return !!(etapa && etapa.classList.contains('is-active'));
    }

    function atualizarDestinoPorMapa(latValue, lngValue) {
        var latDestino = document.getElementById('lat_destino');
        var lngDestino = document.getElementById('lng_destino');
        if (latDestino) latDestino.value = Number(latValue).toFixed(7);
        if (lngDestino) lngDestino.value = Number(lngValue).toFixed(7);
        fetch('/geocode/public/reverse?lat=' + encodeURIComponent(latValue) + '&lng=' + encodeURIComponent(lngValue), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (raw) {
                var data = raw && raw.data ? raw.data : raw;
                var label = data && (data.display_name || data.label || data.endereco);
                var destino = document.getElementById('destino');
                if (label && destino) {
                    destino.value = label;
                    destino.dispatchEvent(new Event('change', { bubbles: true }));
                }
            })
            .catch(function () {});
        redesenharRota();
    }

    origemMarker.on('dragend', function () {
        var p = origemMarker.getLatLng();
        if (latInput) latInput.value = p.lat.toFixed(7);
        if (lngInput) lngInput.value = p.lng.toFixed(7);
        reverseGeocode(p.lat, p.lng);
        if (destinoMarker) redesenharRota();
    });

    map.on('click', function (event) {
        if (!destinoEmEdicao()) return;
        window.__gfMapSetDest(event.latlng.lat, event.latlng.lng, true);
        atualizarDestinoPorMapa(event.latlng.lat, event.latlng.lng);
    });

    function reverseGeocode(la, ln) {
        var elL = document.getElementById('mapLabel');
        if (elL) elL.textContent = 'Atualizando endereço…';
        fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + la + '&lon=' + ln + '&accept-language=pt-BR')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var a = (d && d.address) || {};
                var partes = [a.road, a.house_number, a.suburb, a.city || a.town || a.village].filter(Boolean);
                var loc = document.getElementById('localizacao');
                if (loc && partes.length) {
                    loc.value = partes.join(', ');
                    // Mantém autocomplete/validação e leitores assistivos sincronizados
                    // quando o endereço é alterado pelo pino, mesmo que já houvesse texto.
                    loc.dispatchEvent(new Event('input', { bubbles: true }));
                    loc.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (elL) elL.textContent = partes.length ? partes.join(', ') : 'Confira no mapa';
            })
            .catch(function () { if (elL) elL.textContent = 'Confira no mapa'; });
    }

    window.__gfMapSet = function (la, ln) {
        map.setView([la, ln], 15);
        origemMarker.setLatLng([la, ln]);
        if (destinoMarker) redesenharRota();
        setTimeout(function () { map.invalidateSize(); }, 100);
    };

    window.__gfMapSetDest = function (la, ln, fromMap) {
        var destinoLatLng = [la, ln];

        if (destinoMarker) {
            destinoMarker.setLatLng(destinoLatLng);
        } else {
            var redIcon = L.divIcon({
                className: 'map-pin-dest',
                html: '<span></span>',
                iconSize: [20, 20],
                iconAnchor: [10, 10],
            });
            destinoMarker = L.marker(destinoLatLng, { icon: redIcon, draggable: true }).addTo(map);
            destinoMarker.on('dragend', function () {
                var p = destinoMarker.getLatLng();
                atualizarDestinoPorMapa(p.lat, p.lng);
            });
        }

        if (fromMap) {
            var destino = document.getElementById('destino');
            if (destino) destino.focus();
        }

        redesenharRota();

        var group = L.featureGroup([origemMarker, destinoMarker]);
        map.fitBounds(group.getBounds().pad(0.18));
        setTimeout(function () { map.invalidateSize(); }, 120);
    };

    // ─── Rota via proxy OSRM local ─────────────────────────────
    // A rota exibida deve ser viaria; nao desenhar linha reta quando o
    // provedor nao responder, pois isso induz o cliente a erro.
    function redesenharRota() {
        if (!destinoMarker) return;
        var o = origemMarker.getLatLng();
        var d = destinoMarker.getLatLng();

        // Formato OSRM: lng,lat;lng,lat
        var coords = o.lng.toFixed(6) + ',' + o.lat.toFixed(6) + ';' +
                     d.lng.toFixed(6) + ',' + d.lat.toFixed(6);
        var url = '/api/routing/osrm/route/v1/driving/' + coords +
                  '?overview=full&geometries=geojson';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                var coordsOut = null;
                if (data && data.routes && data.routes[0] && data.routes[0].geometry) {
                    var g = data.routes[0].geometry;
                    if (g.type === 'LineString' && Array.isArray(g.coordinates)) {
                        coordsOut = g.coordinates.map(function (c) { return [c[1], c[0]]; });
                    }
                }
                if (coordsOut) {
                    desenharRota(coordsOut, o, d);
                } else {
                    removerRota();
                }
            })
            .catch(function () { removerRota(); });
    }

    function desenharRota(pontos, o, d) {
        // Se OSRM retornou geometria real, usa; senão, linha reta entre os 2 pins
        if (!pontos || pontos.length < 2) return;
        var latlngs = pontos;

        if (rotaLine) {
            rotaLine.setLatLngs(latlngs);
        } else {
            rotaLine = L.polyline(latlngs, {
                color: '#0d5a2e',
                weight: 4,
                opacity: 0.75,
            }).addTo(map);
        }
    }

    function removerRota() {
        if (rotaLine) { map.removeLayer(rotaLine); rotaLine = null; }
    }

    window.__gfMapClearDest = function () {
        if (destinoMarker) { map.removeLayer(destinoMarker); destinoMarker = null; }
        removerRota();
    };

    setTimeout(function () { map.invalidateSize(); }, 250);
})();
