<?php
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$osrmBaseUrl = PorThresholds::routingFrontendBaseUrl();
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-pedido-trilha.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1><i class="fas fa-route me-2 text-primary-custom"></i>Trilha POR do Pedido #<?php echo (int)($pedido['id'] ?? 0); ?></h1>
            <p>
                <span id="trailModeLabel"><?php echo htmlspecialchars((string)($routingSnapshot['mode_label'] ?? 'Visão geral')); ?></span>
                · qualidade <span id="trailTrackingQuality"><?php echo htmlspecialchars((string)($routingSnapshot['tracking_quality'] ?? 'unknown')); ?></span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo $bp; ?>/admin/pedido/<?php echo (int)($pedido['id'] ?? 0); ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Voltar ao Pedido
            </a>
        </div>
    </header>

    <div class="card mb-4">
        <div class="card-body border-bottom">
            <form method="get" action="<?php echo $bp; ?>/admin/pedido/trilha/<?php echo (int)($pedido['id'] ?? 0); ?>" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Fase</label>
                    <select name="fase" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="origem" <?php echo (($trailFilters['fase'] ?? '') === 'origem') ? 'selected' : ''; ?>>Origem</option>
                        <option value="destino" <?php echo (($trailFilters['fase'] ?? '') === 'destino') ? 'selected' : ''; ?>>Destino</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Validação</label>
                    <select name="valid_only" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="1" <?php echo (($trailFilters['valid_only'] ?? '') === '1') ? 'selected' : ''; ?>>Só válidos</option>
                        <option value="0" <?php echo (($trailFilters['valid_only'] ?? '') === '0') ? 'selected' : ''; ?>>Só rejeitados</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter me-1"></i>Filtrar
                    </button>
                    <a href="<?php echo $bp; ?>/admin/pedido/trilha/<?php echo (int)($pedido['id'] ?? 0); ?>" class="btn btn-secondary btn-sm">Limpar</a>
                </div>
            </form>
        </div>
        <div class="card-header"><i class="fas fa-map me-2"></i>Mapa da trilha completa</div>
        <div class="card-body p-0">
            <div id="trailMap" class="trilha-map"></div>
        </div>
        <div class="card-footer small text-muted d-flex flex-wrap gap-3">
            <span><i class="fas fa-minus trilha-legenda-validada"></i> Trilha validada</span>
            <span><i class="fas fa-circle trilha-legenda-rejeitado"></i> Ponto rejeitado</span>
            <span><i class="fas fa-location-dot trilha-legenda-origem-destino"></i> Origem / destino</span>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-gauge-high me-2"></i>Resumo operacional</div>
                <div class="card-body">
                    <div class="small text-muted">Rua atual</div>
                    <div class="fw-semibold mb-2" id="trailCurrentStreet"><?php echo htmlspecialchars((string)($routingSnapshot['current_street'] ?? 'Sem rua confirmada')); ?></div>
                    <div class="small text-muted">ETA</div>
                    <div class="fw-semibold mb-2" id="trailEta"><?php echo htmlspecialchars((string)($routingSnapshot['eta_label'] ?? 'Sem ETA')); ?></div>
                    <div class="small text-muted">Distância restante</div>
                    <div class="fw-semibold mb-2" id="trailRemainingDistance"><?php echo htmlspecialchars((string)($routingSnapshot['remaining_distance_label'] ?? 'Sem distância')); ?></div>
                    <div class="small text-muted">Pontos válidos / rejeitados</div>
                    <div class="fw-semibold" id="trailPointCounts"><?php echo (int)($routingSnapshot['valid_points'] ?? 0); ?> / <?php echo (int)($routingSnapshot['rejected_points'] ?? 0); ?></div>
                    <div class="small text-muted mt-2">Pontos no filtro atual</div>
                    <div class="fw-semibold" id="trailFilteredCount"><?php echo count($porTrail ?? []); ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-road me-2"></i>Ruas confirmadas</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2" id="trailRecentStreets">
                        <?php foreach (($routingSnapshot['recent_streets'] ?? []) as $street): ?>
                        <span class="badge text-bg-light border"><?php echo htmlspecialchars((string)$street); ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($routingSnapshot['recent_streets'])): ?>
                        <span class="text-muted small">Aguardando ruas confirmadas.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header"><i class="fas fa-triangle-exclamation me-2"></i>Revisão antifraude</div>
                <div class="card-body">
                    <?php
                    $reviewFlags = [];
                    foreach (($porSummary ?? []) as $faseKey => $faseResumo) {
                        if (!$faseResumo) {
                            continue;
                        }
                        if (($faseResumo['tracking_quality'] ?? '') === 'poor') {
                            $reviewFlags[] = 'Qualidade poor na fase ' . $faseKey . '.';
                        }
                        if ((int)($faseResumo['max_gap_seconds'] ?? 0) >= 180) {
                            $reviewFlags[] = 'Lacuna longa na fase ' . $faseKey . ': ' . (int)$faseResumo['max_gap_seconds'] . ' s.';
                        }
                        if ((float)($faseResumo['max_speed_kmh'] ?? 0) >= 100) {
                            $reviewFlags[] = 'Velocidade máxima alta na fase ' . $faseKey . ': ' . number_format((float)$faseResumo['max_speed_kmh'], 1, ',', '.') . ' km/h.';
                        }
                    }
                    ?>
                    <?php if (empty($reviewFlags) && empty($porRejectedSummary)): ?>
                    <div class="text-muted small">Nenhum sinal forte para revisão manual.</div>
                    <?php else: ?>
                        <?php foreach ($reviewFlags as $flag): ?>
                        <div class="alert alert-warning py-2 px-3 small mb-2"><?php echo htmlspecialchars($flag); ?></div>
                        <?php endforeach; ?>
                        <?php if (!empty($porRejectedSummary)): ?>
                        <div class="small text-muted mb-2">Top códigos de rejeição</div>
                        <div class="d-flex flex-wrap gap-2" id="trailRejectedSummary">
                            <?php foreach ($porRejectedSummary as $item): ?>
                            <span class="badge text-bg-light border">
                                <code><?php echo htmlspecialchars((string)($item['rejection_code'] ?? 'SEM-CODIGO')); ?></code>
                                · <?php echo (int)($item['total'] ?? 0); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-wave-square me-2"></i>Saltos e pontos críticos
                    <i class="fas fa-info-circle ms-1 hint-icon" title="Pontos do trajeto onde o GPS registrou uma velocidade muito alta (≥100 km/h), um salto de distância muito grande (≥1.000 m entre dois pontos consecutivos) ou foi rejeitado pelo antifraude. São os candidatos mais prováveis a indicar fraude, sinal ruim de GPS ou erro de digitação de coordenadas."></i>
                </div>
                <div class="card-body p-0">
                    <?php
                    $criticalPoints = array_values(array_filter($porTrail ?? [], static function (array $point): bool {
                        return !$point['is_valid']
                            || (float)($point['calculated_speed_kmh'] ?? 0) >= 100
                            || (float)($point['distance_raw_m'] ?? 0) >= 1000;
                    }));
                    ?>
                    <?php if (empty($criticalPoints)): ?>
                    <div class="p-3 text-muted small">Nenhum salto ou ponto crítico no filtro atual.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Seq.</th>
                                    <th>Fase</th>
                                    <th>Distância bruta</th>
                                    <th>Velocidade</th>
                                    <th>Status</th>
                                    <th>Código</th>
                                </tr>
                            </thead>
                            <tbody id="trailCriticalPointsBody">
                                <?php foreach ($criticalPoints as $point): ?>
                                <tr>
                                    <td><?php echo (int)($point['sequence_number'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars((string)($point['fase'] ?? '—')); ?></td>
                                    <td><?php echo number_format((float)($point['distance_raw_m'] ?? 0), 1, ',', '.'); ?> m</td>
                                    <td><?php echo $point['calculated_speed_kmh'] !== null ? number_format((float)$point['calculated_speed_kmh'], 1, ',', '.') . ' km/h' : '—'; ?></td>
                                    <td><?php echo !empty($point['is_valid']) ? 'Válido' : 'Rejeitado'; ?></td>
                                    <td><code><?php echo htmlspecialchars((string)($point['rejection_code'] ?? '—')); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>Pontos rejeitados
                    <i class="fas fa-info-circle ms-1 hint-icon" title="Pontos GPS que o guincho enviou mas o sistema recusou (não entraram na trilha oficial do atendimento). O código na coluna 'Código' explica o motivo — ex: POR-VAL-005 é ponto com timestamp velho/futuro demais, POR-VAL-006 é velocidade acima do limite, POR-VAL-007 é intervalo entre pontos maior que o permitido, POR-VAL-010 é ponto longe demais de qualquer via (checagem opcional contra a malha viária, desligada por padrão). Não afeta o atendimento, mas ajuda a identificar problema de sinal de GPS ou tentativa de fraude."></i>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($porRejected)): ?>
                    <div class="p-3 text-muted small">Nenhum ponto rejeitado registrado para este pedido.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Seq.</th>
                                    <th>Fase</th>
                                    <th>Código</th>
                                    <th>Rua</th>
                                    <th>Precisão</th>
                                    <th>Velocidade</th>
                                    <th>Recebido</th>
                                </tr>
                            </thead>
                            <tbody id="trailRejectedBody">
                                <?php foreach ($porRejected as $point): ?>
                                <tr>
                                    <td><?php echo (int)($point['sequence_number'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars((string)($point['fase'] ?? '—')); ?></td>
                                    <td><code><?php echo htmlspecialchars((string)($point['rejection_code'] ?? '—')); ?></code></td>
                                    <td><?php echo htmlspecialchars((string)($point['street_name'] ?? '—')); ?></td>
                                    <td><?php echo $point['accuracy_m'] !== null ? number_format((float)$point['accuracy_m'], 1, ',', '.') . ' m' : '—'; ?></td>
                                    <td><?php echo $point['calculated_speed_kmh'] !== null ? number_format((float)$point['calculated_speed_kmh'], 1, ',', '.') . ' km/h' : '—'; ?></td>
                                    <td><?php echo htmlspecialchars((string)($point['server_timestamp'] ?? '—')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-area me-2"></i>Resumo POR por fase
                    <i class="fas fa-info-circle ms-1 hint-icon" title="POR = Proof of Road (prova de rota): o resumo da qualidade do rastreamento GPS em cada etapa do atendimento. 'Fase Origem' cobre o trajeto do guincho até buscar o veículo; 'Fase Destino' cobre o trajeto até a entrega. Mostra quantos pontos foram aceitos/rejeitados e a distância validada, servindo como evidência de que o trajeto realmente aconteceu."></i>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach (['origem' => 'Fase Origem', 'destino' => 'Fase Destino'] as $faseKey => $faseLabel): ?>
                        <?php $faseResumo = $porSummary[$faseKey] ?? null; ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 trilha-fase-box">
                                <div class="fw-semibold mb-2"><?php echo htmlspecialchars($faseLabel); ?></div>
                                <?php if ($faseResumo): ?>
                                <div class="small text-muted">Qualidade: <?php echo htmlspecialchars((string)($faseResumo['tracking_quality'] ?? 'unknown')); ?></div>
                                <div class="small text-muted">Pontos: <?php echo (int)($faseResumo['valid_points'] ?? 0); ?> válidos / <?php echo (int)($faseResumo['rejected_points'] ?? 0); ?> rejeitados</div>
                                <div class="small text-muted">Distância validada: <?php echo number_format((float)($faseResumo['distance_validated_m'] ?? 0), 0, ',', '.'); ?> m</div>
                                <div class="small text-muted">Duração: <?php echo (int)($faseResumo['duration_seconds'] ?? 0); ?> s</div>
                                <div class="small text-muted">Última rua: <?php echo htmlspecialchars((string)($faseResumo['last_street'] ?? '—')); ?></div>
                                <?php else: ?>
                                <div class="text-muted small">Sem resumo disponível para esta fase.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.css">
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', function() {
    const basePath = '<?php echo addslashes($bp); ?>';
    const pedidoId = <?php echo (int)($pedido['id'] ?? 0); ?>;
    const trailFilters = {
        fase: '<?php echo addslashes((string)($trailFilters['fase'] ?? '')); ?>',
        valid_only: '<?php echo addslashes((string)($trailFilters['valid_only'] ?? '')); ?>'
    };
    const latO = <?php echo (float)($pedido['lat_origem'] ?? -23.5505); ?>;
    const lngO = <?php echo (float)($pedido['lng_origem'] ?? -46.6333); ?>;
    const latD = <?php echo (float)($pedido['lat_destino'] ?? -23.5505); ?>;
    const lngD = <?php echo (float)($pedido['lng_destino'] ?? -46.6333); ?>;
    const pedidoStatusInicial = '<?php echo addslashes((string)($pedido['status'] ?? '')); ?>';
    const initialTrail = <?php echo json_encode(array_map(static function (array $point): array {
        return [
            'latitude' => (float)($point['latitude'] ?? 0),
            'longitude' => (float)($point['longitude'] ?? 0),
            'is_valid' => !empty($point['is_valid']),
            'rejection_code' => (string)($point['rejection_code'] ?? ''),
            'street_name' => (string)($point['street_name'] ?? ''),
            'fase' => (string)($point['fase'] ?? ''),
            'sequence_number' => (int)($point['sequence_number'] ?? 0),
            'accuracy_m' => isset($point['accuracy_m']) ? (float)$point['accuracy_m'] : null,
            'calculated_speed_kmh' => isset($point['calculated_speed_kmh']) ? (float)$point['calculated_speed_kmh'] : null,
            'distance_raw_m' => isset($point['distance_raw_m']) ? (float)$point['distance_raw_m'] : null,
            'server_timestamp' => $point['server_timestamp'] ?? null,
        ];
    }, $porTrail ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const map = L.map('trailMap').setView([latO, lngO], 13);
    L.Icon.Default.imagePath = '<?php echo $bp; ?>/public/assets/img/leaflet/';
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Pinos de origem/destino: vermelho até o guincho chegar naquele ponto,
    // cinza depois — mesma convenção usada em todos os mapas do sistema.
    function statusIndicaChegouOrigem(status) {
        return ['no_local', 'em_reboque', 'concluido', 'encerrado_financeiro'].includes(String(status || ''));
    }
    function statusIndicaChegouDestino(status) {
        return ['concluido', 'encerrado_financeiro'].includes(String(status || ''));
    }
    function pinIcon(chegou, iconClass) {
        return L.divIcon({
            className: 'mapa-pin ' + (chegou ? 'mapa-pin--concluido' : 'mapa-pin--pendente'),
            html: '<i class="fas ' + iconClass + '"></i>',
            iconSize: [26, 26],
        });
    }

    const markerOrigemTrilha = L.marker([latO, lngO], { icon: pinIcon(statusIndicaChegouOrigem(pedidoStatusInicial), 'fa-location-dot') })
        .addTo(map).bindPopup('Origem');
    const markerDestinoTrilha = L.marker([latD, lngD], { icon: pinIcon(statusIndicaChegouDestino(pedidoStatusInicial), 'fa-flag-checkered') })
        .addTo(map).bindPopup('Destino');

    function atualizarPinsOrigemDestino(status) {
        markerOrigemTrilha.setIcon(pinIcon(statusIndicaChegouOrigem(status), 'fa-location-dot'));
        markerDestinoTrilha.setIcon(pinIcon(statusIndicaChegouDestino(status), 'fa-flag-checkered'));
    }

    let validTrailLayer = null;
    let rejectedLayer = L.layerGroup().addTo(map);
    let currentMarker = null;
    let plannedRouteLayer = null;
    let approachRouteLayer = null;
    let refreshTimer = null;

    // URL do serviço de roteamento OSRM-compatible, centralizada via config
    // 'por_road_match_base_url' (item #37 — antes hardcoded pro demo público
    // router.project-osrm.org, que tem rate-limit/ToS que proíbem produção).
    const OSRM_BASE_URL = <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>;

    function buildOsrmUrl(points) {
        const coords = points.map((point) => {
            return String(point.lng) + ',' + String(point.lat);
        }).join(';');
        return OSRM_BASE_URL + '/route/v1/driving/' + coords + '?overview=full&geometries=geojson';
    }

    function clearLayer(layerRef) {
        if (layerRef && map.hasLayer(layerRef)) {
            map.removeLayer(layerRef);
        }
        return null;
    }

    function drawFallbackLine(points, style) {
        // Sem fallback em linha reta: roteamento indisponível não deve virar
        // "rota" visual falsa.
        return null;
    }

    function drawRouteLine(points, style, assign) {
        if (!Array.isArray(points) || points.length < 2) {
            if (assign === 'planned') {
                plannedRouteLayer = clearLayer(plannedRouteLayer);
            } else {
                approachRouteLayer = clearLayer(approachRouteLayer);
            }
            return;
        }

        function applyLayer(layer) {
            if (assign === 'planned') {
                plannedRouteLayer = clearLayer(plannedRouteLayer);
                plannedRouteLayer = layer;
            } else {
                approachRouteLayer = clearLayer(approachRouteLayer);
                approachRouteLayer = layer;
            }
        }

        // Timeout defensivo: sem isso, uma resposta lenta do OSRM público
        // prendia o mapa sem rota nenhuma desenhada indefinidamente.
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 6000);

        fetch(buildOsrmUrl(points), { signal: controller.signal })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                const coordinates = payload && payload.routes && payload.routes[0] && payload.routes[0].geometry
                    ? payload.routes[0].geometry.coordinates
                    : null;
                if (!Array.isArray(coordinates)) {
                    applyLayer(drawFallbackLine(points, style));
                    return;
                }
                const latLngs = coordinates.map((coord) => [coord[1], coord[0]]);
                applyLayer(L.polyline(latLngs, style).addTo(map));
            })
            .catch(() => {
                applyLayer(drawFallbackLine(points, style));
            })
            .finally(() => clearTimeout(timeoutId));
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }

    function formatMetric(value, suffix, decimals) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return '—';
        }
        return Number(value).toLocaleString('pt-BR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }) + suffix;
    }

    function drawTrail(points, livePosition) {
        const validPoints = points.filter((point) => point.is_valid).map((point) => [point.latitude, point.longitude]);
        if (validTrailLayer) {
            map.removeLayer(validTrailLayer);
            validTrailLayer = null;
        }
        rejectedLayer.clearLayers();

        if (validPoints.length >= 2) {
            validTrailLayer = L.polyline(validPoints, {
                color: '#16a34a',
                weight: 5,
                opacity: 0.85
            }).addTo(map);
            map.fitBounds(validTrailLayer.getBounds(), { padding: [20, 20] });
        }

        points.filter((point) => !point.is_valid).forEach((point) => {
            L.circleMarker([point.latitude, point.longitude], {
                radius: 5,
                color: '#dc2626',
                weight: 2,
                fillColor: '#fecaca',
                fillOpacity: 0.95
            }).bindPopup(
                'Ponto rejeitado #' + point.sequence_number
                + '<br>Código: ' + (point.rejection_code || '—')
                + '<br>Fase: ' + (point.fase || '—')
                + '<br>Rua: ' + (point.street_name || '—')
            ).addTo(rejectedLayer);
        });

        if (livePosition && Number.isFinite(livePosition.lat) && Number.isFinite(livePosition.lng)) {
            if (!currentMarker) {
                currentMarker = L.marker([livePosition.lat, livePosition.lng]).addTo(map);
            } else {
                currentMarker.setLatLng([livePosition.lat, livePosition.lng]);
            }
            currentMarker.bindPopup('Posição atual do guincho');
            drawRouteLine([
                { lat: livePosition.lat, lng: livePosition.lng },
                { lat: latO, lng: lngO }
            ], {
                color: '#f59e0b',
                weight: 4,
                opacity: 0.9,
                dashArray: '10, 8'
            }, 'approach');
        } else {
            approachRouteLayer = clearLayer(approachRouteLayer);
        }
    }

    function renderRecentStreets(streets) {
        const target = document.getElementById('trailRecentStreets');
        if (!target) {
            return;
        }
        if (!streets.length) {
            target.innerHTML = '<span class="text-muted small">Aguardando ruas confirmadas.</span>';
            return;
        }
        target.innerHTML = streets.map((street) => {
            return '<span class="badge text-bg-light border">' + escapeHtml(street) + '</span>';
        }).join('');
    }

    function renderRejectedSummary(items) {
        const target = document.getElementById('trailRejectedSummary');
        if (!target) {
            return;
        }
        if (!items.length) {
            target.innerHTML = '<span class="text-muted small">Sem rejeições relevantes.</span>';
            return;
        }
        target.innerHTML = items.map((item) => {
            return '<span class="badge text-bg-light border"><code>' + escapeHtml(item.rejection_code || 'SEM-CODIGO') + '</code> · ' + escapeHtml(item.total || 0) + '</span>';
        }).join('');
    }

    function renderCriticalPoints(points) {
        const target = document.getElementById('trailCriticalPointsBody');
        if (!target) {
            return;
        }
        const critical = points.filter((point) => {
            return !point.is_valid || Number(point.calculated_speed_kmh || 0) >= 100 || Number(point.distance_raw_m || 0) >= 1000;
        });
        if (!critical.length) {
            target.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Nenhum salto ou ponto crítico no filtro atual.</td></tr>';
            return;
        }
        target.innerHTML = critical.map((point) => {
            return '<tr>'
                + '<td>' + escapeHtml(point.sequence_number) + '</td>'
                + '<td>' + escapeHtml(point.fase || '—') + '</td>'
                + '<td>' + escapeHtml(formatMetric(point.distance_raw_m, ' m', 1)) + '</td>'
                + '<td>' + escapeHtml(formatMetric(point.calculated_speed_kmh, ' km/h', 1)) + '</td>'
                + '<td>' + escapeHtml(point.is_valid ? 'Válido' : 'Rejeitado') + '</td>'
                + '<td><code>' + escapeHtml(point.rejection_code || '—') + '</code></td>'
                + '</tr>';
        }).join('');
    }

    function renderRejectedTable(points) {
        const target = document.getElementById('trailRejectedBody');
        if (!target) {
            return;
        }
        if (!points.length) {
            target.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Nenhum ponto rejeitado registrado para este pedido.</td></tr>';
            return;
        }
        target.innerHTML = points.map((point) => {
            return '<tr>'
                + '<td>' + escapeHtml(point.sequence_number) + '</td>'
                + '<td>' + escapeHtml(point.fase || '—') + '</td>'
                + '<td><code>' + escapeHtml(point.rejection_code || '—') + '</code></td>'
                + '<td>' + escapeHtml(point.street_name || '—') + '</td>'
                + '<td>' + escapeHtml(formatMetric(point.accuracy_m, ' m', 1)) + '</td>'
                + '<td>' + escapeHtml(formatMetric(point.calculated_speed_kmh, ' km/h', 1)) + '</td>'
                + '<td>' + escapeHtml(point.server_timestamp || '—') + '</td>'
                + '</tr>';
        }).join('');
    }

    function applyRealtimePayload(payload) {
        if (!payload || !payload.ok) {
            return;
        }
        const routing = payload.routing_snapshot || {};
        const pedido = payload.pedido || {};
        if (typeof atualizarPinsOrigemDestino === 'function') {
            atualizarPinsOrigemDestino(pedido.status);
        }
        const lastValid = payload.last_valid_point;
        const livePosition = lastValid
            ? { lat: Number(lastValid.latitude), lng: Number(lastValid.longitude) }
            : ((pedido.lat_guincho !== null && pedido.lng_guincho !== null)
                ? { lat: Number(pedido.lat_guincho), lng: Number(pedido.lng_guincho) }
                : null);

        const textMapping = {
            trailModeLabel: routing.mode_label || 'Visão geral',
            trailTrackingQuality: routing.tracking_quality || 'unknown',
            trailCurrentStreet: routing.current_street || 'Sem rua confirmada',
            trailEta: routing.eta_label || 'Sem ETA',
            trailRemainingDistance: routing.remaining_distance_label || 'Sem distância',
            trailPointCounts: String(routing.valid_points || 0) + ' / ' + String(routing.rejected_points || 0),
            trailFilteredCount: String((payload.por_trail || []).length)
        };
        Object.keys(textMapping).forEach((id) => {
            const node = document.getElementById(id);
            if (node) {
                node.textContent = textMapping[id];
            }
        });

        renderRecentStreets(Array.isArray(routing.recent_streets) ? routing.recent_streets : []);
        renderRejectedSummary(Array.isArray(payload.por_rejected_summary) ? payload.por_rejected_summary : []);
        renderCriticalPoints(Array.isArray(payload.por_trail) ? payload.por_trail : []);
        renderRejectedTable(Array.isArray(payload.por_rejected) ? payload.por_rejected : []);
        drawTrail(Array.isArray(payload.por_trail) ? payload.por_trail : [], livePosition);

        if (String(pedido.status || '') !== 'a_caminho') {
            approachRouteLayer = clearLayer(approachRouteLayer);
        }
    }

    function fetchRealtimeData() {
        const params = new URLSearchParams();
        params.set('limit', '400');
        if (trailFilters.fase) {
            params.set('fase', trailFilters.fase);
        }
        if (trailFilters.valid_only !== '') {
            params.set('valid_only', trailFilters.valid_only);
        }
        fetch(basePath + '/admin/pedido/status-json/' + String(pedidoId) + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then((response) => response.ok ? response.json() : null)
          .then((payload) => {
              if (payload) {
                  applyRealtimePayload(payload);
              }
          }).catch(() => {});
    }

    function scheduleRefresh() {
        if (refreshTimer) {
            clearTimeout(refreshTimer);
        }
        refreshTimer = setTimeout(fetchRealtimeData, 250);
    }

    drawRouteLine([
        { lat: latO, lng: lngO },
        { lat: latD, lng: lngD }
    ], {
        color: '#0d6efd',
        weight: 5,
        opacity: 0.8
    }, 'planned');
    drawTrail(initialTrail, null);
    if (pedidoStatusInicial === 'a_caminho' && <?php echo isset($pedido['lat_guincho'], $pedido['lng_guincho']) ? 'true' : 'false'; ?>) {
        drawRouteLine([
            { lat: <?php echo isset($pedido['lat_guincho']) ? (float)$pedido['lat_guincho'] : -23.5505; ?>, lng: <?php echo isset($pedido['lng_guincho']) ? (float)$pedido['lng_guincho'] : -46.6333; ?> },
            { lat: latO, lng: lngO }
        ], {
            color: '#f59e0b',
            weight: 4,
            opacity: 0.9,
            dashArray: '10, 8'
        }, 'approach');
    }

    if (window.EventSource) {
        const source = new EventSource(basePath + '/sse/pedido/' + String(pedidoId));
        source.addEventListener('status_update', scheduleRefresh);
        source.addEventListener('localizacao_guincho', scheduleRefresh);
        source.addEventListener('stream_close', function () {
            source.close();
        });
        window.addEventListener('beforeunload', function () {
            source.close();
        });
    }

    fetchRealtimeData();
});
</script>
