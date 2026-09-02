(function () {
    'use strict';

    var cells = Array.isArray(window.__territorioPainel) ? window.__territorioPainel : [];
    var cities = Array.isArray(window.__territorioCidades) ? window.__territorioCidades : [];
    var citySelect = document.getElementById('territorioCidade');
    var cellSelect = document.getElementById('territorioCelula');
    if (!citySelect || !cellSelect || !cells.length) return;

    var map = null;
    var polygonLayer = null;
    var overlayLayers = [];
    var overlayIcons = null;
    var overlayEpoch = 0;
    var charts = [];
    var statusExpansaoLabel = { nao_ativada: 'Não ativada', pedra_morta: 'Pedra morta', pedra_viva: 'Pedra viva' };
    var statusExpansaoBadge = { nao_ativada: 'text-bg-secondary', pedra_morta: 'text-bg-danger', pedra_viva: 'text-bg-success' };

    function esc(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function money(value) {
        return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function cellsForCity(cityId) {
        var id = Number(cityId);
        return id > 0 ? cells.filter(function (cell) { return Number(cell.cidade_id) === id; }) : cells;
    }

    function fillCities() {
        var ids = {};
        cells.forEach(function (cell) {
            if (Number(cell.cidade_id) > 0) ids[Number(cell.cidade_id)] = true;
        });
        var available = cities.filter(function (city) { return ids[Number(city.id)]; });
        citySelect.innerHTML = available.map(function (city) {
            return '<option value="' + Number(city.id) + '">' + esc(city.nome + '/' + city.uf) + '</option>';
        }).join('');
        if (!available.length) citySelect.innerHTML = '<option value="">Todas as cidades</option>';
    }

    function destroyCharts() {
        charts.forEach(function (chart) { chart.destroy(); });
        charts = [];
    }

    // §CELULAS-NITEROI-01 (04/08/2026): semáforo de 4 cores pedido pelo
    // usuário pros gráficos de meta — verde = bateu a meta, azul = superou,
    // amarelo = perto (≥80%), vermelho = abaixo. "Perto" em 80% é um limiar
    // deliberadamente conservador (não é meta oficial do piloto, é só corte
    // visual de alerta) — documentado aqui pra não parecer número mágico
    // escondido.
    var LIMIAR_PERTO_DA_META = 0.8;
    function classificarMeta(value, goal) {
        if (goal === null || goal === undefined || goal <= 0) return null;
        var ratio = value / goal;
        if (ratio > 1.0001) return { cor: '#0d6efd', label: 'Superou a meta', chave: 'superou' };
        if (ratio >= 1) return { cor: '#22c55e', label: 'Na meta', chave: 'na_meta' };
        if (ratio >= LIMIAR_PERTO_DA_META) return { cor: '#f59e0b', label: 'Perto da meta', chave: 'perto' };
        return { cor: '#dc3545', label: 'Abaixo da meta', chave: 'abaixo' };
    }

    function drawProgress(canvasId, current, target, footerId, emptyText, unidadeLabel) {
        var canvas = document.getElementById(canvasId);
        var footer = document.getElementById(footerId);
        if (!canvas || target === null || target === undefined) {
            if (footer) footer.textContent = emptyText;
            return;
        }
        var value = Math.max(0, Number(current || 0));
        var goal = Math.max(0, Number(target || 0));
        if (goal <= 0) {
            if (footer) footer.textContent = emptyText;
            return;
        }
        var status = classificarMeta(value, goal);
        var reached = Math.min(value, goal);
        var pct = Math.round((value / goal) * 100);
        charts.push(new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Realizado', 'Restante'],
                datasets: [{
                    data: [reached, Math.max(0, goal - reached)],
                    backgroundColor: [status.cor, 'rgba(255,255,255,.12)'],
                    borderWidth: 0
                }]
            },
            plugins: [{
                id: 'territorioCenterText',
                afterDraw: function (chart) {
                    var meta = chart.getDatasetMeta(0).data[0];
                    if (!meta) return;
                    var ctx = chart.ctx;
                    var x = meta.x;
                    var y = meta.y;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = getComputedStyle(document.body).getPropertyValue('--theme-text') || '#e8e8e8';
                    ctx.font = '700 20px sans-serif';
                    ctx.fillText(String(value), x, y - 7);
                    ctx.font = '11px sans-serif';
                    ctx.fillText('de ' + String(goal), x, y + 12);
                    ctx.restore();
                }
            }],
            options: {
                responsive: true,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function () { return status.label + ' (' + pct + '%)'; },
                            label: function (ctx) {
                                if (ctx.dataIndex === 0) return 'Realizado: ' + value + (unidadeLabel ? ' ' + unidadeLabel : '');
                                return 'Falta' + (goal - value > 0 ? ': ' + (goal - value) : ' nada') + (unidadeLabel ? ' ' + unidadeLabel : '') + ' para a meta de ' + goal;
                            }
                        }
                    }
                }
            }
        }));
        if (footer) {
            footer.innerHTML = '<strong>' + value + '</strong> de ' + goal +
                ' — <span style="color:' + status.cor + '">' + esc(status.label) + '</span> (' + pct + '%)';
        }
    }

    // §CELULAS-NITEROI-01 (04/08/2026): guinchos/clientes/rotas dentro do
    // mapa de leitura da célula — pedido do usuário. Reaproveita o mesmo
    // endpoint /admin/dashboard/mapa-json do "Mapa operacional ao vivo"
    // (agora em /admin/central), mas filtra client-side pra só os pontos
    // DENTRO do polígono da célula selecionada, via ray-casting (mesmo
    // algoritmo do ZonePricingService::resolverZonaPorCoordenada no PHP —
    // só funciona pra Polygon simples, mesma limitação já documentada em
    // toda a stack de célula).
    function pointInRing(lat, lng, ring) {
        var inside = false;
        for (var i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            var xi = ring[i][0], yi = ring[i][1];
            var xj = ring[j][0], yj = ring[j][1];
            var intersect = ((yi > lat) !== (yj > lat)) &&
                (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    }

    function getOverlayIcons() {
        if (overlayIcons) return overlayIcons;
        overlayIcons = {
            cliente: L.divIcon({ className: 'mapa-marker mapa-marker--cliente', html: '<i class="fas fa-map-pin"></i>', iconSize: [24, 24] }),
            clienteConcluido: L.divIcon({ className: 'mapa-marker mapa-marker--cliente-concluido', html: '<i class="fas fa-map-pin"></i>', iconSize: [24, 24] }),
            destino: L.divIcon({ className: 'mapa-marker mapa-marker--cliente-concluido', html: '<i class="fas fa-flag-checkered"></i>', iconSize: [24, 24] }),
            guincho: L.divIcon({ className: 'mapa-marker mapa-marker--guincho', html: '<i class="fas fa-truck-pickup"></i>', iconSize: [24, 24] }),
            guinchoAtivo: L.divIcon({ className: 'mapa-marker mapa-marker--guincho-ativo', html: '<i class="fas fa-truck-fast"></i>', iconSize: [24, 24] }),
            guinchoReboque: L.divIcon({ className: 'mapa-marker mapa-marker--guincho-reboque', html: '<i class="fas fa-truck-fast"></i>', iconSize: [24, 24] }),
        };
        return overlayIcons;
    }

    function clearOverlay() {
        overlayLayers.forEach(function (layer) { if (map) map.removeLayer(layer); });
        overlayLayers = [];
        var info = document.getElementById('territorioMapaOverlayInfo');
        if (info) info.textContent = '';
    }

    function drawRoadRoute(from, to, style, epoch, onLayer) {
        var baseUrl = String(window.__osrmBaseUrl || '').replace(/\/$/, '');
        if (!baseUrl) return;
        var controller = new AbortController();
        var timeout = window.setTimeout(function () { controller.abort(); }, 6000);
        fetch(baseUrl + '/route/v1/driving/' + from.lng + ',' + from.lat + ';' + to.lng + ',' + to.lat + '?overview=full&geometries=geojson', { signal: controller.signal })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (payload) {
                if (epoch !== overlayEpoch) return;
                var coordinates = payload && payload.routes && payload.routes[0] && payload.routes[0].geometry
                    ? payload.routes[0].geometry.coordinates : null;
                if (!Array.isArray(coordinates) || coordinates.length < 2) return;
                var roadLayer = L.polyline(coordinates.map(function (c) { return [c[1], c[0]]; }), style).addTo(map);
                if (typeof onLayer === 'function') onLayer(roadLayer);
            })
            .catch(function () {})
            .finally(function () { window.clearTimeout(timeout); });
    }

    function loadTerritorioOverlay(cell) {
        if (!map || !cell || !cell.polygon_geojson) { clearOverlay(); return; }
        var ring;
        try {
            var geo = JSON.parse(cell.polygon_geojson);
            ring = (geo && geo.type === 'Polygon') ? geo.coordinates[0] : null;
        } catch (error) {
            ring = null;
        }
        if (!ring) { clearOverlay(); return; }

        var basePath = window.__basePath || '';
        var epoch = ++overlayEpoch;
        var cellId = cell.id;

        fetch(basePath + '/admin/dashboard/mapa-json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then(function (resp) { return resp.ok ? resp.json() : null; })
            .then(function (payload) {
                // Descarta resposta atrasada de uma troca de célula/poll anterior.
                if (epoch !== overlayEpoch || !payload || payload.ok !== true) return;
                if (!cellSelect || Number(cellSelect.value) !== Number(cellId)) return;

                clearOverlay();
                var icons = getOverlayIcons();
                var totalGuinchos = 0;
                var totalClientes = 0;

                (payload.clientes || []).forEach(function (c) {
                    if (!pointInRing(Number(c.lat), Number(c.lng), ring)) return;
                    var concluido = ['no_local', 'em_reboque'].indexOf(String(c.status || '')) !== -1;
                    var marker = L.marker([c.lat, c.lng], { icon: concluido ? icons.clienteConcluido : icons.cliente })
                        .bindPopup('<strong>Pedido #' + c.pedido_id + '</strong><br>' + esc(c.label || '') + '<br>' + esc(c.endereco || ''));
                    marker.addTo(map);
                    overlayLayers.push(marker);
                    totalClientes++;

                    // Rota planejada do atendimento, independente de já haver
                    // GPS do guincho. Antes o overlay só conseguia desenhar o
                    // trecho do prestador, deixando o mapa sem linha quando o
                    // pedido ainda estava aguardando deslocamento.
                    if (c.lat_destino !== null && c.lng_destino !== null) {
                        if (pointInRing(Number(c.lat_destino), Number(c.lng_destino), ring)) {
                            var destinoMarker = L.marker([Number(c.lat_destino), Number(c.lng_destino)], { icon: icons.destino })
                                .bindPopup('<strong>Destino do pedido #' + c.pedido_id + '</strong><br>' + esc(c.endereco_destino || ''));
                            destinoMarker.addTo(map);
                            overlayLayers.push(destinoMarker);
                        }
                        drawRoadRoute(
                            { lat: Number(c.lat), lng: Number(c.lng) },
                            { lat: Number(c.lat_destino), lng: Number(c.lng_destino) },
                            { color: '#2563eb', weight: 3, opacity: 0.72 },
                            epoch,
                            function (layer) { overlayLayers.push(layer); }
                        );
                    }
                });

                (payload.guinchos || []).forEach(function (g) {
                    if (!pointInRing(Number(g.lat), Number(g.lng), ring)) return;
                    var emReboque = g.em_atendimento && g.rota && g.rota.indo_para === 'destino';
                    var icon = !g.em_atendimento ? icons.guincho : (emReboque ? icons.guinchoReboque : icons.guinchoAtivo);
                    var statusTxt = g.em_atendimento ? ('Em atendimento — Pedido #' + g.pedido_id) : 'Disponível';
                    var marker = L.marker([g.lat, g.lng], { icon: icon })
                        .bindPopup('<strong>' + esc(g.label || '') + '</strong><br>' + esc(g.placa || '') + '<br>' + statusTxt);
                    marker.addTo(map);
                    overlayLayers.push(marker);
                    totalGuinchos++;

                    // Rota: trilha GPS validada + trecho restante calculado na
                    // malha viária. Enquanto o OSRM responde, uma linha de
                    // fallback permanece visível para nunca deixar o mapa vazio.
                    if (g.em_atendimento && g.rota) {
                        var trail = g.rota.trail || [];
                        if (trail.length >= 2) {
                            var trailColor = g.rota.indo_para === 'destino' ? '#0d6efd' : '#f59e0b';
                            var trailLine = L.polyline(trail.map(function (p) { return [p.lat, p.lng]; }), {
                                color: trailColor, weight: 3, opacity: 0.8,
                            }).addTo(map);
                            overlayLayers.push(trailLine);
                        }
                        if (g.rota.target_lat !== null && g.rota.target_lng !== null) {
                            var ultimo = trail.length ? trail[trail.length - 1] : { lat: g.lat, lng: g.lng };
                            var destStyle = g.rota.indo_para === 'destino'
                                ? { color: '#0d6efd', dashArray: '6,8', weight: 3, opacity: 0.7 }
                                : { color: '#f59e0b', dashArray: '6,8', weight: 3, opacity: 0.7 };
                            drawRoadRoute(ultimo, { lat: Number(g.rota.target_lat), lng: Number(g.rota.target_lng) }, destStyle, epoch, function (layer) {
                                overlayLayers.push(layer);
                            });
                        }
                    }
                });

                var info = document.getElementById('territorioMapaOverlayInfo');
                if (info) {
                    info.textContent = totalGuinchos + ' guincho(s) e ' + totalClientes + ' cliente(s) dentro desta célula agora.';
                }
            })
            .catch(function (error) {
                console.warn('Falha ao carregar guinchos/clientes da célula:', error);
            });
    }

    function renderMap(cell) {
        var mapNode = document.getElementById('territorioMapa');
        var emptyNode = document.getElementById('territorioMapaSemPoligono');
        if (!mapNode || !window.L) return;

        if (!map) {
            map = L.map(mapNode, { zoomControl: true, doubleClickZoom: true }).setView([-22.8832, -43.1034], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
        }
        if (polygonLayer) {
            map.removeLayer(polygonLayer);
            polygonLayer = null;
        }

        var hasPolygon = Boolean(cell && cell.polygon_geojson);
        mapNode.classList.toggle('d-none', !hasPolygon);
        if (emptyNode) emptyNode.classList.toggle('d-none', hasPolygon);
        if (!hasPolygon) { clearOverlay(); return; }

        try {
            polygonLayer = L.geoJSON(JSON.parse(cell.polygon_geojson), {
                style: { color: '#0d6efd', weight: 3, fillOpacity: 0.24 }
            }).addTo(map);
            map.fitBounds(polygonLayer.getBounds().pad(0.08));
            window.setTimeout(function () { map.invalidateSize(); }, 50);
            loadTerritorioOverlay(cell);
        } catch (error) {
            mapNode.classList.add('d-none');
            clearOverlay();
            if (emptyNode) {
                emptyNode.textContent = 'Polígono inválido para esta célula.';
                emptyNode.classList.remove('d-none');
            }
        }
    }

    // §CELULAS-NITEROI-01 (04/08/2026): composição de oferta como gráfico de
    // barras (pedido do usuário) em vez de lista de texto — mesmo semáforo
    // de 4 cores dos donuts. Item "não modelado ainda" (oficina parceira,
    // reserva operacional) entra como barra cinza em 0, nunca inventando um
    // valor — o hint explica o motivo ao passar o mouse.
    var COR_NAO_MODELADO = 'rgba(255,255,255,.18)';
    function drawComposicao(itens) {
        var canvas = document.getElementById('territorioChartComposicao');
        if (!canvas || !itens.length) return;

        var labels = itens.map(function (item) { return item.label; });
        var valores = itens.map(function (item) { return item.computavel ? Number(item.atual || 0) : 0; });
        var metas = itens.map(function (item) { return Number(item.meta || 0); });
        var cores = itens.map(function (item) {
            if (!item.computavel) return COR_NAO_MODELADO;
            var status = classificarMeta(item.atual, item.meta);
            return status ? status.cor : COR_NAO_MODELADO;
        });

        charts.push(new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: valores,
                    backgroundColor: cores,
                    borderRadius: 4,
                    barPercentage: 0.7,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: Math.max.apply(null, metas.concat([1])),
                        ticks: { color: getComputedStyle(document.body).getPropertyValue('--theme-text') || '#e8e8e8', precision: 0 },
                        grid: { color: 'rgba(255,255,255,.08)' }
                    },
                    y: {
                        ticks: { color: getComputedStyle(document.body).getPropertyValue('--theme-text') || '#e8e8e8' },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (ctxArr) { return ctxArr[0].label; },
                            label: function (ctx) {
                                var item = itens[ctx.dataIndex];
                                if (!item.computavel) {
                                    return 'Não modelado ainda no cadastro do prestador (meta: ' + item.meta + ')';
                                }
                                var status = classificarMeta(item.atual, item.meta) || { label: '' };
                                return item.atual + ' de ' + item.meta + ' — ' + status.label;
                            }
                        }
                    }
                }
            }
        }));
    }

    function renderCell(id) {
        var cell = cells.find(function (item) { return Number(item.id) === Number(id); });
        if (!cell) return;

        renderMap(cell);
        destroyCharts();

        var details = document.getElementById('territorioDetalhes');
        if (details) {
            var statusAtual = cell.status_expansao || 'nao_ativada';
            var sugerida = cell.classificacao_sugerida;
            var sugeridaHtml = '';
            if (sugerida && sugerida !== statusAtual) {
                sugeridaHtml = '<div class="small text-warning mt-1"><i class="fas fa-lightbulb me-1"></i>Sugestão ao vivo pela meta de oferta: <strong>' +
                    esc(statusExpansaoLabel[sugerida] || sugerida) + '</strong> (status atual ainda não foi ajustado pelo admin)</div>';
            } else if (sugerida) {
                sugeridaHtml = '<div class="small text-muted mt-1"><i class="fas fa-check me-1"></i>Status confirmado bate com a meta de oferta calculada ao vivo.</div>';
            } else {
                sugeridaHtml = '<div class="small text-danger mt-1"><i class="fas fa-lock me-1"></i>Pedra viva bloqueada: defina as metas separadas de guinchos e especialistas.</div>';
            }
            // §COBERTURA-RAIO-01 (05/08/2026): quando a taxa de cancelamento
            // por timeout (30 min sem aceite) passa do limiar, isso já
            // segurou a classificação em pedra_morta mesmo com composição/
            // margem ok — deixa isso explícito, senão parece que o painel
            // "esqueceu" de promover a célula.
            if (cell.timeout_bloqueou_classificacao) {
                sugeridaHtml += '<div class="small text-danger mt-1"><i class="fas fa-triangle-exclamation me-1"></i>Taxa de cancelamento por timeout (' +
                    cell.taxa_timeout_pct + '%) acima do limite de ' + cell.taxa_timeout_max_pct +
                    '% — isso está segurando a célula em "pedra morta" mesmo com a oferta cadastrada em dia.</div>';
            }

            details.innerHTML =
                '<div class="d-flex justify-content-between align-items-start mb-2">' +
                    '<div><strong>' + esc(cell.name) + '</strong><div class="text-muted small">' + esc(cell.code) + '</div></div>' +
                    '<span class="badge ' + (statusExpansaoBadge[statusAtual] || 'text-bg-secondary') + '">' + esc(statusExpansaoLabel[statusAtual] || statusAtual) + '</span>' +
                '</div>' +
                sugeridaHtml +
                '<div class="row g-2 small mb-3 mt-2">' +
                    '<div class="col-6">Receita bruta<strong class="d-block">' + money(cell.receita_bruta) + '</strong></div>' +
                    '<div class="col-6">Margem operacional<strong class="d-block ' + (Number(cell.margem_operacional) >= 0 ? 'text-success' : 'text-danger') + '">' + money(cell.margem_operacional) + (cell.margem_operacional_pct === null ? '' : ' (' + cell.margem_operacional_pct + '%)') + '</strong></div>' +
                    '<div class="col-6">Repassado<strong class="d-block">' + money(cell.repassado_prestadores) + '</strong></div>' +
                    '<div class="col-6">Comissão<strong class="d-block">' + money(cell.comissao_plataforma) + '</strong></div>' +
                    '<div class="col-6">Perdas/estornos<strong class="d-block text-danger">' + money(cell.perdas_estorno_valor) + ' (' + cell.perdas_estorno_qtd + ')</strong></div>' +
                    '<div class="col-6">Pedidos pagos<strong class="d-block">' + cell.pedidos_pagos + '</strong></div>' +
                    '<div class="col-6">Guinchos<strong class="d-block">' + (cell.guinchos_homologados || 0) + ' / ' + (cell.meta_guinchos_min === null ? '—' : cell.meta_guinchos_min) + '</strong></div>' +
                    '<div class="col-6">Especialistas<strong class="d-block">' + (cell.especialistas_homologados || 0) + ' / ' + (cell.meta_especialistas_min === null ? '—' : cell.meta_especialistas_min) + '</strong></div>' +
                    '<div class="col-6">Timeout de aceite (30min)<strong class="d-block ' + (cell.taxa_timeout_pct !== null && cell.taxa_timeout_pct > cell.taxa_timeout_max_pct ? 'text-danger' : '') + '">' +
                        (cell.taxa_timeout_pct === null ? 'sem dado' : cell.taxa_timeout_pct + '% (' + cell.pedidos_timeout_cancelados + ')') + '</strong></div>' +
                '</div>' +
                '<div class="small text-muted mb-1">Composição de oferta (piloto 90 dias)' + (cell.oferta_possui_item_nao_modelado ? ' — itens em cinza ainda não são rastreados no cadastro do prestador' : '') + ':</div>' +
                '<canvas id="territorioChartComposicao" height="170"></canvas>';
        }

        drawComposicao(cell.oferta_composicao || []);

        drawProgress('territorioChartPedidos', cell.pedidos_pagos, cell.meta_atendimentos_90d,
            'territorioMetaPedidos',
            'Sem meta de atendimentos definida ainda para esta célula.', 'pedidos');

        drawProgress('territorioChartPrestadores', cell.prestadores_homologados, cell.meta_prestadores_min,
            'territorioMetaPrestadores',
            'Sem meta de prestadores definida ainda para esta célula.', 'prestadores');

        var financeCanvas = document.getElementById('territorioChartFinanceiro');
        var financeFooter = document.getElementById('territorioMetaFinanceiro');
        var financeValues = [
            Number(cell.repassado_prestadores || 0),
            Number(cell.comissao_plataforma || 0),
            Number(cell.perdas_estorno_valor || 0)
        ];
        var financeTotal = financeValues.reduce(function (sum, value) { return sum + value; }, 0);
        if (financeCanvas && financeTotal > 0) {
            charts.push(new Chart(financeCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Repassado', 'Comissão', 'Perdas'],
                    datasets: [{ data: financeValues, backgroundColor: ['#0d6efd', '#20c997', '#dc3545'], borderWidth: 0 }]
                },
                options: {
                    responsive: true,
                    cutout: '68%',
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } }
                }
            }));
            if (financeFooter) financeFooter.textContent = 'Total distribuído: ' + money(financeTotal);
        } else if (financeFooter) {
            financeFooter.textContent = 'Sem dados financeiros para esta célula.';
        }
    }

    function fillCells() {
        var selectedCity = citySelect.value;
        var available = cellsForCity(selectedCity);
        cellSelect.innerHTML = available.map(function (cell) {
            return '<option value="' + Number(cell.id) + '">Fase ' +
                (cell.ordem_expansao || '—') + ' · ' + esc(cell.name) + '</option>';
        }).join('');
        if (available.length) renderCell(available[0].id);
    }

    fillCities();
    if (cells[0] && cells[0].cidade_id) citySelect.value = String(cells[0].cidade_id);
    fillCells();

    citySelect.addEventListener('change', fillCells);
    cellSelect.addEventListener('change', function () { renderCell(cellSelect.value); });

    // §CELULAS-NITEROI-01 (04/08/2026): "Metas & Território" precisa ser AO
    // VIVO — chamado pelo polling de /admin/dashboard/json (ver
    // dashboard.php: syncDashboard() → payload.territorio_painel). Troca o
    // dado em memória e re-renderiza só a célula que já está selecionada,
    // sem resetar o seletor de cidade/célula escolhido pelo admin.
    window.AdminTerritorioMetas = {
        updateData: function (novasCelulas) {
            if (!Array.isArray(novasCelulas) || !novasCelulas.length) return;
            cells = novasCelulas;
            var celulaSelecionadaId = cellSelect.value;
            renderCell(celulaSelecionadaId);
        }
    };
})();
