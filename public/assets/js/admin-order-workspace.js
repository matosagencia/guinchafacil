/**
 * Workspace reutilizável de detalhe de pedido (mapa + timeline + chat) pro
 * padrão shell-ops do admin. Extraído de admin/central_operacional.php pra
 * ser reaproveitado por qualquer tela com uma fila de pedidos clicável
 * (Central Operacional, Pedidos, Despacho, Alertas...) sem duplicar ~300
 * linhas de JS por página. Consome a mesma API real já usada pela Central
 * Operacional (src/Api/Admin/OrdersApiController.php) — GET
 * /api/admin/orders/{id}, /tracking, /timeline, /messages e POST /messages.
 *
 * Uso:
 *   AdminOrderWorkspace.init({
 *     shellId: 'opsShell',
 *     resultsId: 'opsWorklistResults',
 *     workspaceId: 'opsWorkspace',
 *     worklistSearchId: 'opsWorklistSearch',   // opcional
 *     globalSearchId: 'opsGlobalSearch',       // opcional
 *     apiBase: bp + '/api/admin/orders',
 *     csrfToken: csrfToken,
 *     worklistData: [...],       // mesmo formato de $worklist da Central Operacional
 *     autoSelectFirst: true,     // opcional, default true
 *     emptyLabel: 'Nenhum pedido selecionado.' // opcional
 *   });
 */
(function (global) {
    function createWorkspace(options) {
        var shell = document.getElementById(options.shellId);
        var results = document.getElementById(options.resultsId);
        var workspace = document.getElementById(options.workspaceId);
        if (!shell || !results || !workspace) return null;

        var OPS_API_BASE = options.apiBase;
        // Deriva a base de URL do admin (ex.: '/admin') a partir da apiBase
        // ('<bp>/api/admin/orders') pra montar links absolutos (ex.: pro
        // Despacho) sem precisar de mais uma opção explícita em cada página
        // que usa este módulo (Central Operacional, Pedidos).
        var OPS_ADMIN_BASE = (options.apiBase || '').replace(/\/api\/admin\/orders$/, '');
        var CSRF = options.csrfToken;
        var emptyLabel = options.emptyLabel || 'Nenhum pedido selecionado.';

        var data = options.worklistData || [];
        var byId = {};
        data.forEach(function (o) { byId[o.id] = o; });

        var currentOrderId = null;
        var currentMap = null;
        var loadToken = 0;

        function escapeHtml(value) {
            var el = document.createElement('div');
            el.textContent = String(value == null ? '' : value);
            return el.innerHTML;
        }

        async function apiGet(path) {
            var res = await fetch(OPS_API_BASE + path, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            var body = await res.json().catch(function () { return null; });
            if (!res.ok || !body || body.ok !== true) {
                throw new Error((body && body.error) || ('HTTP ' + res.status));
            }
            return body.data;
        }

        async function apiPostMessage(orderId, message) {
            var res = await fetch(OPS_API_BASE + '/' + orderId + '/messages', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': CSRF,
                },
                body: new URLSearchParams({ message: message, csrf_token: CSRF }),
            });
            var body = await res.json().catch(function () { return null; });
            if (!res.ok || !body || body.ok !== true) {
                throw new Error((body && body.error) || ('HTTP ' + res.status));
            }
            return body.data;
        }

        function renderSkeleton(worklistItem) {
            workspace.innerHTML =
                '<div class="ops-empty-state" style="padding:60px 20px">' +
                '<i class="fas fa-circle-notch fa-spin"></i>Carregando ' + escapeHtml((worklistItem && worklistItem.codigo) || '') + '…</div>';
        }

        function renderError(message) {
            workspace.innerHTML =
                '<div class="ops-empty-state" style="padding:60px 20px">' +
                '<i class="fas fa-triangle-exclamation"></i>' + escapeHtml(message) + '</div>';
        }

        function renderShell(detail, worklistItem) {
            var badgeClass = 'ops-badge--' + escapeHtml((detail.status_info && detail.status_info.css) || (worklistItem && worklistItem.status_css) || 'audit');
            var statusLabel = (detail.status_info && detail.status_info.label) || (worklistItem && worklistItem.status_label) || '';
            var elapsedMin = Math.floor(((detail.sla && detail.sla.status_elapsed_seconds) || 0) / 60);
            // Pedido sem prestador ainda: dá acesso direto à tela de Despacho
            // pra este pedido específico, sem precisar sair da Central e
            // procurar de novo na fila de lá.
            var needsDispatch = detail.status === 'aguardando_guincho' && !detail.guincho_id;
            var isTerminal = detail.status === 'concluido' || detail.status === 'cancelado';
            var canConcludeManually = ['a_caminho', 'no_local', 'em_reboque'].indexOf(detail.status) !== -1;
            var manageBase = OPS_ADMIN_BASE + '/admin/pedido/' + detail.id;
            var actionsHtml = '';

            if (needsDispatch) {
                actionsHtml += '  <a class="ops-btn" data-admin-action="dispatch" href="' + escapeHtml(OPS_ADMIN_BASE + '/admin/despacho?pedido_id=' + detail.id) + '"><i class="fas fa-route"></i> Despachar</a>';
            }
            if (!isTerminal) {
                actionsHtml += '  <a class="ops-btn" data-admin-action="status" href="' + escapeHtml(manageBase + '?acao=status') + '"><i class="fas fa-pen-to-square"></i> Alterar status</a>';
                if (!detail.guincho_id) {
                    actionsHtml += '  <a class="ops-btn" data-admin-action="assign" href="' + escapeHtml(manageBase + '?acao=atribuir') + '"><i class="fas fa-truck"></i> Atribuir prestador</a>';
                }
                if (canConcludeManually) {
                    actionsHtml += '  <a class="ops-btn" data-admin-action="manual-conclusion" href="' + escapeHtml(manageBase + '?acao=concluir-manual') + '"><i class="fas fa-user-shield"></i> Concluir manualmente</a>';
                }
                actionsHtml += '  <a class="ops-btn" data-admin-action="cancel" href="' + escapeHtml(manageBase + '?acao=cancelar') + '" style="border-color:#dc3545;color:#ff7b86"><i class="fas fa-ban"></i> Cancelar pedido</a>';
            }
            actionsHtml += '  <a class="ops-btn" data-admin-action="manage" href="' + escapeHtml(manageBase) + '"><i class="fas fa-sliders"></i> Gestão completa</a>';

            workspace.innerHTML =
                '<header class="ops-order-header">' +
                '  <div>' +
                '    <button type="button" class="ops-back-link" data-action="ops-clear-selection">' +
                '      <i class="fas fa-arrow-left"></i> Todos os pedidos' +
                '    </button>' +
                '    <h1>' + escapeHtml('GF-' + detail.id) + '</h1>' +
                '    <p>Há ' + elapsedMin + ' min neste status</p>' +
                '  </div>' +
                '  <div class="ops-order-header__actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end">' +
                '    <span class="ops-badge ' + badgeClass + '">' + escapeHtml(statusLabel) + '</span>' +
                     actionsHtml +
                '  </div>' +
                '</header>' +
                '<div class="ops-order-facts">' +
                '  <div class="ops-order-fact"><span class="ops-order-fact__label">Origem</span><span class="ops-order-fact__value">' + escapeHtml(detail.endereco_origem || '—') + '</span></div>' +
                '  <div class="ops-order-fact"><span class="ops-order-fact__label">Destino</span><span class="ops-order-fact__value">' + escapeHtml(detail.endereco_destino || '—') + '</span></div>' +
                '  <div class="ops-order-fact"><span class="ops-order-fact__label">Cliente</span><span class="ops-order-fact__value">' + escapeHtml(detail.cliente_nome || '—') + '</span></div>' +
                '  <div class="ops-order-fact"><span class="ops-order-fact__label">Prestador</span><span class="ops-order-fact__value">' + escapeHtml(detail.provider_name || 'Sem prestador atribuído') + '</span></div>' +
                '  <div class="ops-order-fact"><span class="ops-order-fact__label">Veículo</span><span class="ops-order-fact__value">' + escapeHtml([detail.marca, detail.modelo, detail.placa].filter(Boolean).join(' · ') || '—') + '</span></div>' +
                '  <div class="ops-order-fact"><span class="ops-order-fact__label">Pagamento</span><span class="ops-order-fact__value">' + escapeHtml((detail.payment && detail.payment.status) || '—') + '</span></div>' +
                '  <div class="ops-order-fact"><span class="ops-order-fact__label">Distância validada</span><span class="ops-order-fact__value">' + escapeHtml(Math.round((detail.distance && detail.distance.validated_m) || 0)) + ' m</span></div>' +
                '  <div class="ops-order-fact"><span class="ops-order-fact__label">Serviço</span><span class="ops-order-fact__value">' + escapeHtml((detail.service && detail.service.name) || '—') + '</span></div>' +
                '</div>' +
                '<div class="ops-tabs" role="tablist">' +
                '  <button type="button" class="ops-tab is-active" data-tab="mapa">Mapa</button>' +
                '  <button type="button" class="ops-tab" data-tab="timeline">Timeline</button>' +
                '  <button type="button" class="ops-tab" data-tab="chat">Chat</button>' +
                '</div>' +
                '<div class="ops-tab-panel is-active" data-panel="mapa">' +
                '  <div class="ops-map-workspace" style="margin:0">' +
                '    <div class="ops-map-canvas" id="opsMapCanvas" style="padding:0"></div>' +
                '  </div>' +
                '</div>' +
                '<div class="ops-tab-panel" data-panel="timeline">' +
                '  <div id="opsTimelineContent" class="ops-empty-state"><i class="fas fa-circle-notch fa-spin"></i>Carregando timeline…</div>' +
                '</div>' +
                '<div class="ops-tab-panel" data-panel="chat">' +
                '  <div id="opsChatMessages" style="max-height:320px;overflow-y:auto;margin-bottom:14px"></div>' +
                '  <form id="opsChatForm" style="display:flex;gap:8px">' +
                '    <input type="text" id="opsChatInput" placeholder="Escrever mensagem administrativa…" maxlength="2000" style="flex:1;height:38px;padding:0 12px;border:1px solid var(--theme-border,#232c35);border-radius:var(--radius-xs);background:var(--theme-surface-2,#171d24);color:var(--theme-text)">' +
                '    <button type="submit" class="ops-btn"><i class="fas fa-paper-plane"></i> Enviar</button>' +
                '  </form>' +
                '</div>';

            var backLink = workspace.querySelector('[data-action="ops-clear-selection"]');
            if (backLink) backLink.addEventListener('click', function () { selectOrder(null); });

            workspace.querySelectorAll('.ops-tab').forEach(function (tabBtn) {
                tabBtn.addEventListener('click', function () {
                    workspace.querySelectorAll('.ops-tab').forEach(function (t) { t.classList.remove('is-active'); });
                    workspace.querySelectorAll('.ops-tab-panel').forEach(function (p) { p.classList.remove('is-active'); });
                    tabBtn.classList.add('is-active');
                    workspace.querySelector('[data-panel="' + tabBtn.dataset.tab + '"]').classList.add('is-active');
                    if (tabBtn.dataset.tab === 'mapa' && currentMap) {
                        window.setTimeout(function () { currentMap.invalidateSize(); }, 50);
                    }
                });
            });

            var chatForm = document.getElementById('opsChatForm');
            if (chatForm) {
                chatForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    var input = document.getElementById('opsChatInput');
                    var message = input.value.trim();
                    if (!message) return;
                    try {
                        await apiPostMessage(detail.id, message);
                        input.value = '';
                        await loadMessages(detail.id);
                    } catch (err) {
                        window.alert('Falha ao enviar mensagem: ' + err.message);
                    }
                });
            }
        }

        function renderMap(detail, tracking) {
            var canvas = document.getElementById('opsMapCanvas');
            if (!canvas || typeof L === 'undefined') return;
            canvas.style.height = '420px';
            var lat = Number(detail.lat_origem) || -22.9068;
            var lng = Number(detail.lng_origem) || -43.1729;
            var map = L.map(canvas).setView([lat, lng], 14);
            currentMap = map;
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

            var pin = function (className, iconClass) {
                return L.divIcon({
                    className: 'mapa-pin ' + className,
                    html: '<i class="fas ' + iconClass + '"></i>',
                    iconSize: [28, 28],
                });
            };
            var originIcon = pin('mapa-pin--pendente', 'fa-location-dot');
            var destinationIcon = pin('mapa-pin--pendente', 'fa-flag-checkered');
            var providerIcon = pin('mapa-pin--provider', 'fa-truck-fast');

            var bounds = [];
            if (detail.lat_origem && detail.lng_origem) {
                L.marker([Number(detail.lat_origem), Number(detail.lng_origem)], { icon: originIcon }).addTo(map).bindPopup('Origem: ' + (detail.endereco_origem || ''));
                bounds.push([Number(detail.lat_origem), Number(detail.lng_origem)]);
            }
            if (detail.lat_destino && detail.lng_destino) {
                L.marker([Number(detail.lat_destino), Number(detail.lng_destino)], { icon: destinationIcon }).addTo(map).bindPopup('Destino: ' + (detail.endereco_destino || ''));
                bounds.push([Number(detail.lat_destino), Number(detail.lng_destino)]);
            }
            var points = (tracking && tracking.points) || [];
            var validPoints = points.filter(function (p) { return p.is_valid; }).map(function (p) { return [Number(p.latitude), Number(p.longitude)]; });
            if (validPoints.length > 1) {
                L.polyline(validPoints, { color: '#24d451', weight: 4 }).addTo(map);
                bounds = bounds.concat(validPoints);
            }

            var routeBase = String(options.osrmBaseUrl || '').replace(/\/$/, '');
            var route = function (from, to, style) {
                if (!from || !to) return;
                if (!routeBase) return;
                var controller = new AbortController();
                var timeout = window.setTimeout(function () { controller.abort(); }, 6000);
                fetch(routeBase + '/route/v1/driving/' + from.lng + ',' + from.lat + ';' + to.lng + ',' + to.lat + '?overview=full&geometries=geojson', { signal: controller.signal })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (payload) {
                        var coordinates = payload && payload.routes && payload.routes[0] && payload.routes[0].geometry
                            ? payload.routes[0].geometry.coordinates : null;
                        if (!Array.isArray(coordinates) || coordinates.length < 2) return;
                        L.polyline(coordinates.map(function (c) { return [c[1], c[0]]; }), style).addTo(map);
                    })
                    .catch(function () {})
                    .finally(function () { window.clearTimeout(timeout); });
            };
            var origin = detail.lat_origem && detail.lng_origem ? { lat: Number(detail.lat_origem), lng: Number(detail.lng_origem) } : null;
            var destination = detail.lat_destino && detail.lng_destino ? { lat: Number(detail.lat_destino), lng: Number(detail.lng_destino) } : null;
            route(origin, destination, { color: '#2563eb', weight: 4, opacity: 0.72 });
            if (detail.location && detail.location.latitude) {
                L.marker([Number(detail.location.latitude), Number(detail.location.longitude)], {
                    icon: providerIcon,
                }).addTo(map).bindPopup('Última posição conhecida');
                bounds.push([Number(detail.location.latitude), Number(detail.location.longitude)]);
                var target = String(detail.status || '') === 'a_caminho' ? origin : destination;
                route({ lat: Number(detail.location.latitude), lng: Number(detail.location.longitude) }, target, { color: '#f59e0b', weight: 4, opacity: 0.78, dashArray: '8,6' });
            }
            if (bounds.length > 1) {
                map.fitBounds(bounds, { padding: [30, 30] });
            }
            window.setTimeout(function () { map.invalidateSize(); }, 100);
        }

        function renderTimeline(timeline) {
            var el = document.getElementById('opsTimelineContent');
            if (!el) return;
            var events = (timeline && timeline.events) || [];
            if (!events.length) {
                el.className = 'ops-empty-state';
                el.innerHTML = '<i class="fas fa-clock"></i>Nenhum evento registrado para este pedido.';
                return;
            }
            el.className = '';
            el.innerHTML = '<ul class="ops-timeline">' + events.map(function (ev) {
                return '<li><time>' + escapeHtml(ev.at) + '</time>' + escapeHtml(ev.label || ev.type) + '</li>';
            }).join('') + '</ul>';
        }

        async function loadMessages(orderId) {
            var el = document.getElementById('opsChatMessages');
            if (!el) return;
            try {
                var msgData = await apiGet('/' + orderId + '/messages');
                var messages = msgData.messages || [];
                el.innerHTML = messages.length
                    ? messages.map(function (m) {
                        return '<div style="padding:8px 0;border-bottom:1px solid var(--theme-border,#232c35)">' +
                            '<strong style="font-size:.82rem">' + escapeHtml(m.usuario_nome) + '</strong> ' +
                            '<small style="color:var(--theme-muted)">' + escapeHtml(m.criado_em) + '</small>' +
                            '<div style="font-size:.86rem;margin-top:2px">' + escapeHtml(m.mensagem) + '</div></div>';
                    }).join('')
                    : '<div class="ops-empty-state"><i class="fas fa-comments"></i>Nenhuma mensagem ainda.</div>';
            } catch (err) {
                el.innerHTML = '<div class="ops-empty-state"><i class="fas fa-triangle-exclamation"></i>Falha ao carregar chat.</div>';
            }
        }

        async function loadOrderDetail(orderId, worklistItem) {
            var myToken = ++loadToken;
            renderSkeleton(worklistItem);
            try {
                var detail = await apiGet('/' + orderId);
                if (myToken !== loadToken) return;
                renderShell(detail, worklistItem);

                var tracking = null, timeline = null;
                try { tracking = await apiGet('/' + orderId + '/tracking'); } catch (e) { /* segue sem tracking */ }
                if (myToken !== loadToken) return;
                renderMap(detail, tracking);

                try { timeline = await apiGet('/' + orderId + '/timeline'); } catch (e) { /* segue sem timeline */ }
                if (myToken !== loadToken) return;
                renderTimeline(timeline);

                await loadMessages(orderId);
            } catch (err) {
                if (myToken === loadToken) renderError('Falha ao carregar pedido: ' + err.message);
            }
        }

        function selectOrder(orderId) {
            currentOrderId = orderId;
            var buttons = results.querySelectorAll('[data-order-id]');
            buttons.forEach(function (btn) {
                var match = Number(btn.dataset.orderId) === orderId;
                btn.setAttribute('aria-selected', String(match));
            });
            if (!orderId) {
                workspace.innerHTML = '<div class="ops-empty-state" style="padding:80px 20px">' +
                    '<i class="fas fa-inbox"></i>' + escapeHtml(emptyLabel) + '</div>';
            } else {
                loadOrderDetail(orderId, byId[orderId] || { codigo: 'GF-' + orderId });
            }
            if (window.matchMedia('(max-width: 767px)').matches) {
                shell.classList.toggle('has-selection', !!orderId);
            }
        }

        results.addEventListener('click', function (event) {
            var item = event.target.closest('[data-order-id]');
            if (!item) { return; }
            selectOrder(Number(item.dataset.orderId));
        });

        function applyFilter(term) {
            var t = term.trim().toLowerCase();
            results.querySelectorAll('[data-order-id]').forEach(function (btn) {
                var blob = btn.dataset.searchBlob || '';
                btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
            });
        }

        var worklistSearch = options.worklistSearchId ? document.getElementById(options.worklistSearchId) : null;
        var globalSearch = options.globalSearchId ? document.getElementById(options.globalSearchId) : null;
        if (worklistSearch) {
            worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
        }
        if (globalSearch) {
            globalSearch.addEventListener('input', function (e) {
                if (worklistSearch) { worklistSearch.value = e.target.value; }
                applyFilter(e.target.value);
            });
        }

        if (options.autoSelectFirst !== false && data.length > 0) {
            selectOrder(data[0].id);
        }

        return { selectOrder: selectOrder };
    }

    global.AdminOrderWorkspace = { init: createWorkspace };
})(window);
