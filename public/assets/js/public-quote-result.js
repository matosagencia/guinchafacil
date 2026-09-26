(function () {
    'use strict';

    function init() {
        var node = document.getElementById('publicQuoteMap');
        if (!node || !window.L) return;

        var origin = [Number(node.dataset.latOrigin), Number(node.dataset.lngOrigin)];
        var destination = [Number(node.dataset.latDestination), Number(node.dataset.lngDestination)];
        if (!origin.every(Number.isFinite) || !destination.every(Number.isFinite)) return;

        var status = document.getElementById('quoteRouteStatus');
        var map = L.map(node, { zoomControl: true }).fitBounds([origin, destination], { padding: [28, 28] });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);
        L.marker(origin).addTo(map).bindPopup('Origem do veiculo');
        L.marker(destination).addTo(map).bindPopup('Destino do veiculo');

        var base = (node.dataset.routeBase || '').replace(/\/$/, '');
        var url = base + '/route/v1/driving/' + origin[1] + ',' + origin[0] + ';' + destination[1] + ',' + destination[0] + '?overview=full&geometries=geojson';
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('route');
                return response.json();
            })
            .then(function (payload) {
                var coordinates = payload.routes && payload.routes[0] && payload.routes[0].geometry && payload.routes[0].geometry.coordinates;
                if (!Array.isArray(coordinates) || coordinates.length < 2) throw new Error('empty');
                var route = L.polyline(coordinates.map(function (point) { return [point[1], point[0]]; }), {
                    color: '#22a447',
                    weight: 5,
                    opacity: 0.9,
                }).addTo(map);
                map.fitBounds(route.getBounds(), { padding: [28, 28] });
                if (status) status.textContent = 'Rota estimada por vias';
            })
            .catch(function () {
                if (status) status.textContent = 'Rota por vias indisponivel agora';
            });

        window.setTimeout(function () { map.invalidateSize(); }, 100);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
