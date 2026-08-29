<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/AdminPricingZoneController.php
// ROADMAP socorro automotivo — Etapa 13 (preço governado por zona/cidade).
//
// Primeira tela admin sobre pricing_zones/service_price_rules (schema
// existia desde install/migration_pricing_zones_v1.sql, mas sem CRUD
// nenhum até 26/07/2026). Controller próprio (não amontoado em
// AdminController.php nem em AdminServiceCatalogController.php) porque
// zona é um conceito geográfico, não de catálogo de serviço — mesmo
// espírito de separação já usado para AdminServiceCatalogController vs
// AdminController::servicos().

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Pricing/PricingZone.php';
require_once __DIR__ . '/../Models/Pricing/ServicePriceRule.php';
require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
require_once __DIR__ . '/../Services/Logger.php';

class AdminPricingZoneController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /** Lista de zonas de precificação. */
    public function zonas(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Models/Cidade.php';
        // §CELULAS-NITEROI-01: ordena por fase de expansão (célula 1 antes
        // de célula 5), não alfabético — reflete a ordem real de domínio
        // territorial planejada, não é só uma lista de cadastro.
        $zonas = PricingZone::listarPorOrdemExpansao();
        $cidadesAtivas = Cidade::listarAtivas();
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/precificacao_zonas.php';
    }

    public function demandaTerritorial(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
        $prioridades = PreQuoteDemandService::listarPrioridades();
        $indicadoresExternos = [];
        $rankingsExternos = ['via' => [], 'bairro' => []];
        $zonasTerritoriais = [];
        try {
            $zonasTerritoriais = PricingZone::listarPorOrdemExpansao(null, true);
        } catch (Throwable $e) {
            // O ranking continua disponível mesmo sem a configuração de zonas.
        }
        try {
            $stmt = getPDO()->prepare('SELECT * FROM territorial_external_indicators WHERE city_name = ? AND uf = ? ORDER BY reference_year DESC, indicator_name');
            $stmt->execute(['Niterói', 'RJ']);
            $indicadoresExternos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            try {
                $rankStmt = getPDO()->prepare("SELECT * FROM territorial_external_rankings WHERE city_name LIKE 'Niter%' AND uf = ? ORDER BY ranking_type, occurrence_count DESC, label");
                $rankStmt->execute(['RJ']);
                foreach ($rankStmt->fetchAll(PDO::FETCH_ASSOC) as $ranking) {
                    $tipo = (string)($ranking['ranking_type'] ?? '');
                    if (isset($rankingsExternos[$tipo])) $rankingsExternos[$tipo][] = $ranking;
                }
            } catch (Throwable $e) {
                // Ranking opcional: a tela continua funcional sem a nova migração.
            }
        } catch (Throwable $e) {
            // A migração pode ainda não ter sido aplicada.
        }
        require __DIR__ . '/../Views/admin/demanda_territorial.php';
    }

    public function zonaSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $polygonGeojson = trim((string)($_POST['polygon_geojson'] ?? ''));
        $active = !empty($_POST['active']);
        $cidadeId = (int)($_POST['cidade_id'] ?? 0) ?: null;

        if ($name === '' || ($id <= 0 && $code === '')) {
            $this->setFlashMessage('Nome (e código, para zona nova) são obrigatórios.', 'error');
            $this->redirect('/admin/precificacao/zonas');
        }

        if ($polygonGeojson !== '' && PricingZone::normalizarGeojson($polygonGeojson) === null) {
            $this->setFlashMessage('O polígono colado não é um GeoJSON Polygon válido — a zona foi salva SEM polígono (não vai casar com nenhuma coordenada até isso ser corrigido).', 'error');
            $polygonGeojson = '';
        }

        if ($id > 0) {
            PricingZone::atualizar($id, $name, $active, $polygonGeojson ?: null, $cidadeId);
            Logger::log(Logger::LEVEL_INFO, 'AdminPricingZoneController', 'zonaSalvar', 'precificacao',
                "Zona de precificação #{$id} atualizada por admin #{$user['id']}",
                ['zona_id' => $id, 'admin_id' => $user['id'], 'cidade_id' => $cidadeId]);
        } else {
            $novoId = PricingZone::criar($code, $name, null, $polygonGeojson ?: null, $cidadeId);
            if (!$active) {
                PricingZone::atualizar($novoId, $name, false, $polygonGeojson ?: null, $cidadeId);
            }
            Logger::log(Logger::LEVEL_INFO, 'AdminPricingZoneController', 'zonaSalvar', 'precificacao',
                "Zona de precificação #{$novoId} ({$code}) criada por admin #{$user['id']}",
                ['zona_id' => $novoId, 'code' => $code, 'admin_id' => $user['id'], 'cidade_id' => $cidadeId]);
        }

        $this->setFlashMessage('Zona salva com sucesso.', 'success');
        $this->redirect('/admin/precificacao/zonas');
    }

    /** Regras de preço de UMA zona (service_type x categoria de veículo). */
    public function zonaRegras(int $zonaId): void
    {
        AuthService::requireAuth('admin');
        $zona = PricingZone::buscarPorId($zonaId);
        if (!$zona) {
            $this->setFlashMessage('Zona não encontrada.', 'error');
            $this->redirect('/admin/precificacao/zonas');
        }
        $regras = ServicePriceRule::listarPorZona($zonaId);
        $tiposServico = ServiceType::listarTodos();
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/precificacao_zona_regras.php';
    }

    public function regraSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $zonaId = (int)($_POST['pricing_zone_id'] ?? 0);
        $serviceTypeId = (int)($_POST['service_type_id'] ?? 0);
        $vehicleCategory = trim((string)($_POST['vehicle_category'] ?? ''));
        $vehicleCategory = $vehicleCategory !== '' ? $vehicleCategory : null;

        $zona = PricingZone::buscarPorId($zonaId);
        if (!$zona || $serviceTypeId <= 0) {
            $this->setFlashMessage('Zona ou tipo de serviço inválido.', 'error');
            $this->redirect('/admin/precificacao/zonas');
        }

        $dados = [
            'base_customer_price' => str_replace(',', '.', (string)($_POST['base_customer_price'] ?? '0')),
            'minimum_customer_price' => str_replace(',', '.', (string)($_POST['minimum_customer_price'] ?? '0')),
            'maximum_customer_price' => $_POST['maximum_customer_price'] !== '' ? str_replace(',', '.', (string)$_POST['maximum_customer_price']) : '',
            'provider_base_amount' => str_replace(',', '.', (string)($_POST['provider_base_amount'] ?? '0')),
            'platform_fee_type' => (string)($_POST['platform_fee_type'] ?? 'PERCENTAGE'),
            'platform_fee_value' => str_replace(',', '.', (string)($_POST['platform_fee_value'] ?? '0')),
            'included_distance_km' => str_replace(',', '.', (string)($_POST['included_distance_km'] ?? '0')),
            'extra_distance_price' => str_replace(',', '.', (string)($_POST['extra_distance_price'] ?? '0')),
            'included_minutes' => (string)($_POST['included_minutes'] ?? '0'),
            'extra_minute_price' => str_replace(',', '.', (string)($_POST['extra_minute_price'] ?? '0')),
            'night_multiplier' => str_replace(',', '.', (string)($_POST['night_multiplier'] ?? '1')),
            'holiday_multiplier' => str_replace(',', '.', (string)($_POST['holiday_multiplier'] ?? '1')),
            'effective_from' => (string)($_POST['effective_from'] ?? '') ?: null,
            'effective_until' => (string)($_POST['effective_until'] ?? '') ?: null,
        ];

        $novoId = ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, $dados, $vehicleCategory);

        Logger::log(Logger::LEVEL_INFO, 'AdminPricingZoneController', 'regraSalvar', 'precificacao',
            "Nova versão de regra de preço #{$novoId} criada para zona #{$zonaId}/serviço #{$serviceTypeId} por admin #{$user['id']}",
            ['regra_id' => $novoId, 'zona_id' => $zonaId, 'service_type_id' => $serviceTypeId, 'admin_id' => $user['id']]);

        $this->setFlashMessage('Regra de preço salva (nova versão criada — histórico preservado).', 'success');
        $this->redirect("/admin/precificacao/zona/{$zonaId}");
    }

    /**
     * §CELULAS-NITEROI-01 (04/08/2026): governança da expansão territorial
     * por célula — ordem de fase, status (não ativada/pedra morta/pedra
     * viva) e bairros de referência. Separado de zonaSalvar() porque é um
     * conceito diferente (planejamento de expansão, não geometria/preço).
     */
    public function expansaoSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $zona = PricingZone::buscarPorId($id);
        if (!$zona) {
            $this->setFlashMessage('Zona não encontrada.', 'error');
            $this->redirect('/admin/precificacao/zonas');
        }

        $ordemRaw = trim((string)($_POST['ordem_expansao'] ?? ''));
        $ordemExpansao = $ordemRaw !== '' ? (int)$ordemRaw : null;
        $statusExpansao = (string)($_POST['status_expansao'] ?? 'nao_ativada');
        $bairrosReferencia = trim((string)($_POST['bairros_referencia'] ?? '')) ?: null;

        PricingZone::atualizarExpansao($id, $ordemExpansao, $statusExpansao, $bairrosReferencia);
        PricingZone::atualizarMetas($id, [
            'meta_guinchos_min' => trim((string)($_POST['meta_guinchos_min'] ?? '')),
            'meta_especialistas_min' => trim((string)($_POST['meta_especialistas_min'] ?? '')),
            'meta_prestadores_min' => trim((string)($_POST['meta_prestadores_min'] ?? '')),
            'meta_prestadores_max' => trim((string)($_POST['meta_prestadores_max'] ?? '')),
            'meta_disponibilidade_simultanea' => trim((string)($_POST['meta_disponibilidade_simultanea'] ?? '')),
            'meta_atendimentos_mes1' => trim((string)($_POST['meta_atendimentos_mes1'] ?? '')),
            'meta_atendimentos_mes2' => trim((string)($_POST['meta_atendimentos_mes2'] ?? '')),
            'meta_atendimentos_mes3' => trim((string)($_POST['meta_atendimentos_mes3'] ?? '')),
            'meta_margem_operacional_min_pct' => trim((string)($_POST['meta_margem_operacional_min_pct'] ?? '')),
            'meta_margem_pos_marketing_min_pct' => trim((string)($_POST['meta_margem_pos_marketing_min_pct'] ?? '')),
            'meta_composicao_prestadores' => trim((string)($_POST['meta_composicao_prestadores'] ?? '')),
            'meta_ciclo_inicio' => trim((string)($_POST['meta_ciclo_inicio'] ?? '')),
        ]);

        Logger::log(Logger::LEVEL_INFO, 'AdminPricingZoneController', 'expansaoSalvar', 'precificacao',
            "Governança de expansão da zona/célula #{$id} atualizada por admin #{$user['id']}",
            ['zona_id' => $id, 'ordem_expansao' => $ordemExpansao, 'status_expansao' => $statusExpansao, 'admin_id' => $user['id']]);

        $this->setFlashMessage('Expansão da célula atualizada.', 'success');
        $this->redirect('/admin/precificacao/zonas');
    }

    public function regraDesativar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }
        $regraId = (int)($_POST['regra_id'] ?? 0);
        $zonaId = (int)($_POST['pricing_zone_id'] ?? 0);
        if ($regraId > 0) {
            ServicePriceRule::desativar($regraId);
            Logger::log(Logger::LEVEL_INFO, 'AdminPricingZoneController', 'regraDesativar', 'precificacao',
                "Regra de preço #{$regraId} desativada por admin #{$user['id']}",
                ['regra_id' => $regraId, 'admin_id' => $user['id']]);
        }
        $this->setFlashMessage('Regra desativada.', 'success');
        $this->redirect("/admin/precificacao/zona/{$zonaId}");
    }
}
