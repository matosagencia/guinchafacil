<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/AdminServiceCatalogController.php
// ROADMAP socorro automotivo — Etapa 1 (domínio) / Etapa 9 (admin).
// §12.3 do roadmap: "Não adicionar tudo ao AdminController.php" — controller
// especializado próprio para catálogo de serviços e aprovação de capacidades
// do prestador. Distinto de AdminController::servicos()/servicoForm(), que
// administra `catalogo_servicos` (atalhos rápidos do painel do cliente, ver
// src/Models/ServicoCatalogo.php) — tabela e propósito diferentes.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Catalog/ServiceCategory.php';
require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
require_once __DIR__ . '/../Models/Catalog/ProviderCapability.php';
require_once __DIR__ . '/../Models/Catalog/ProviderEquipment.php';
require_once __DIR__ . '/../Models/Catalog/ServicePricingRule.php';
require_once __DIR__ . '/../Models/Dispatch/ProviderServiceVehicleCapability.php';
require_once __DIR__ . '/../Models/Dispatch/ServiceVehicleRequirement.php';
require_once __DIR__ . '/../Models/ServiceTypeProduto.php';
require_once __DIR__ . '/../Services/Catalog/SystemServiceProtectionService.php';
require_once __DIR__ . '/../Services/Logger.php';

class AdminServiceCatalogController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /** Lista categorias + tipos de serviço do catálogo estruturado (Fundamento 1). */
    public function tipos(): void
    {
        AuthService::requireAuth('admin');
        $categorias = ServiceCategory::listarTodas();
        $tipos = ServiceType::listarTodos();
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/catalogo_servicos_tipos.php';
    }

    public function tipoForm(): void
    {
        AuthService::requireAuth('admin');
        $id = (int)($_GET['id'] ?? 0);
        $tipo = $id > 0 ? ServiceType::buscarPorId($id) : null;
        $categorias = ServiceCategory::listarAtivas();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/catalogo_servicos_tipo_form.php';
    }

    public function tipoSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $dados = [
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'code' => (string)($_POST['code'] ?? ''),
            'name' => trim((string)($_POST['name'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'attendance_mode' => (string)($_POST['attendance_mode'] ?? 'ON_SITE'),
            'requires_destination' => !empty($_POST['requires_destination']),
            'allows_conversion_to_towing' => !empty($_POST['allows_conversion_to_towing']),
            'requires_diagnostic' => !empty($_POST['requires_diagnostic']),
            'requires_parts' => !empty($_POST['requires_parts']),
            'requires_before_evidence' => !empty($_POST['requires_before_evidence']),
            'requires_after_evidence' => !empty($_POST['requires_after_evidence']),
            'estimated_duration_minutes' => (int)($_POST['estimated_duration_minutes'] ?? 30),
            'active' => !empty($_POST['active']),
        ];

        if ($dados['category_id'] <= 0 || $dados['name'] === '') {
            $this->setFlashMessage('Categoria e nome são obrigatórios.', 'error');
            $this->redirect('/admin/catalogo-servicos/tipos');
        }

        if (!in_array($dados['attendance_mode'], ['TOWING', 'ON_SITE', 'HYBRID'], true)) {
            $dados['attendance_mode'] = 'ON_SITE';
        }

        if ($id > 0) {
            // Etapa 16 — proteção de serviço de sistema (reboque). Não pode ser
            // desativado; se for protegido, força active=1 e bloqueia a
            // tentativa de desativar (SRV-SYS-001), independentemente da UI.
            $existente = ServiceType::buscarPorId($id);
            if ($existente) {
                try {
                    SystemServiceProtectionService::assertActiveChangeAllowed($existente, (bool)$dados['active']);
                } catch (\DomainException $e) {
                    $this->setFlashMessage($e->getMessage(), 'error');
                    $this->redirect('/admin/catalogo-servicos/tipos');
                }
                if (SystemServiceProtectionService::isProtected($existente)) {
                    $dados['active'] = true; // serviço de sistema nunca fica inativo
                }
            }
            ServiceType::atualizar($id, $dados);
            Logger::log(Logger::LEVEL_INFO, 'AdminServiceCatalogController', 'tipoSalvar', 'catalogo_servicos',
                "Tipo de serviço #{$id} atualizado por admin #{$user['id']}",
                ['service_type_id' => $id, 'admin_id' => $user['id']]);
        } else {
            if ($dados['code'] === '') {
                $this->setFlashMessage('Código é obrigatório para criar um novo tipo de serviço.', 'error');
                $this->redirect('/admin/catalogo-servicos/tipos');
            }
            try {
                $novoId = ServiceType::criar($dados);
                Logger::log(Logger::LEVEL_INFO, 'AdminServiceCatalogController', 'tipoSalvar', 'catalogo_servicos',
                    "Novo tipo de serviço criado: #{$novoId} ({$dados['code']}) por admin #{$user['id']}",
                    ['service_type_id' => $novoId, 'code' => $dados['code'], 'admin_id' => $user['id']]);
            } catch (\PDOException $e) {
                Logger::exception('AdminServiceCatalogController', 'tipoSalvar', 'catalogo_servicos', $e, ['code' => $dados['code']]);
                $this->setFlashMessage('Já existe um tipo de serviço com esse código.', 'error');
                $this->redirect('/admin/catalogo-servicos/tipos');
            }
        }

        $this->setFlashMessage('Tipo de serviço salvo com sucesso.', 'success');
        $this->redirect('/admin/catalogo-servicos/tipos');
    }

    /** Fila de aprovação de capacidades declaradas pelos prestadores (§3.3 do roadmap). */
    public function capacidades(): void
    {
        AuthService::requireAuth('admin');
        $pdo = getPDO();
        $stmt = $pdo->query(
            "SELECT pc.*, st.name AS service_name, st.code AS service_code, g.id AS guincho_id, u.nome AS prestador_nome
             FROM provider_capabilities pc
             JOIN service_types st ON st.id = pc.service_type_id
             JOIN guinchos g ON g.id = pc.provider_id
             JOIN usuarios u ON u.id = g.usuario_id
             ORDER BY FIELD(pc.approval_status, 'PENDING', 'APPROVED', 'SUSPENDED', 'REJECTED'), pc.updated_at DESC"
        );
        $capacidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $capacidadesPorGuincho = [];
        foreach ($capacidades as $capacidade) {
            $guinchoId = (int)$capacidade['guincho_id'];
            $capacidadesPorGuincho[$guinchoId]['guincho_id'] = $guinchoId;
            $capacidadesPorGuincho[$guinchoId]['prestador_nome'] = (string)$capacidade['prestador_nome'];
            $capacidadesPorGuincho[$guinchoId]['itens'][] = $capacidade;
        }
        $capacidadesPorGuincho = array_values($capacidadesPorGuincho);

        // Resumo pra faixa de métricas no topo (mesmo padrão visual do
        // .ops-summary da Central Operacional).
        $resumoCapacidades = [
            'guinchos' => count($capacidadesPorGuincho),
            'total' => count($capacidades),
            'pendentes' => count(array_filter($capacidades, static fn($c) => $c['approval_status'] === 'PENDING')),
            'aprovadas' => count(array_filter($capacidades, static fn($c) => $c['approval_status'] === 'APPROVED')),
            'rejeitadas' => count(array_filter($capacidades, static fn($c) => $c['approval_status'] === 'REJECTED')),
        ];

        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/catalogo_servicos_capacidades.php';
    }

    public function capacidadeDecidir(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $capabilityId = (int)($_POST['capability_id'] ?? 0);
        $acao = (string)($_POST['acao'] ?? '');
        // §CAPACIDADES-SHELL-01: preserva qual guincho estava selecionado no
        // painel de detalhe (arquitetura lista+workspace da Central
        // Operacional) através do redirect pós-ação, pra não perder o
        // contexto que o admin estava revisando.
        $retornoGuinchoId = (int)($_POST['retorno_guincho_id'] ?? 0);
        $querystring = $retornoGuinchoId > 0 ? ('?guincho_id=' . $retornoGuinchoId) : '';

        if ($capabilityId <= 0 || !in_array($acao, ['aprovar', 'suspender', 'rejeitar'], true)) {
            $this->setFlashMessage('Ação inválida.', 'error');
            $this->redirect('/admin/catalogo-servicos/capacidades' . $querystring);
        }

        $ok = match ($acao) {
            'aprovar' => ProviderCapability::aprovar($capabilityId, (int)$user['id']),
            'suspender' => ProviderCapability::suspender($capabilityId, (int)$user['id']),
            'rejeitar' => ProviderCapability::rejeitar($capabilityId, (int)$user['id']),
            default => false,
        };

        $this->setFlashMessage($ok ? 'Capacidade atualizada.' : 'Falha ao atualizar capacidade.', $ok ? 'success' : 'error');
        $this->redirect('/admin/catalogo-servicos/capacidades' . $querystring);
    }

    /**
     * Tarifas por tipo de serviço (Fundamento 9 do roadmap). MVP: uma regra
     * global por service_type. Não substitui TarifaService (reboque), que
     * segue sendo a fonte de verdade financeira do fluxo de reboque em
     * produção — esta tela cobre os NOVOS serviços (chaveiro, bateria,
     * pneu, diagnóstico elétrico, mecânica, combustível etc.).
     */
    /**
     * §PRECO-POR-CIDADE-01: seletor opcional de cidade-alvo, mesmo padrão
     * de /admin/configuracoes e /admin/planejamento. Sem cidade selecionada
     * mostra/edita a regra GLOBAL (cidade_id NULL) de sempre; com cidade
     * selecionada, mostra a regra ESPECÍFICA daquela cidade quando existir
     * — e deixa claro na tela quando ainda NÃO existe override (prevalece
     * a tarifa global, ver ServicePricingRule::buscarPorServiceType).
     */
    public function tarifas(): void
    {
        AuthService::requireAuth('admin');
        require_once __DIR__ . '/../Models/Cidade.php';
        $cidadesAtivas = Cidade::listarAtivas();
        $cidadeId = (int)($_GET['cidade_id'] ?? 0) ?: null;
        $regrasGlobais = ServicePricingRule::listarComTipos(null);
        $regras = $cidadeId !== null ? ServicePricingRule::listarComTipos($cidadeId) : $regrasGlobais;
        // Mapa rápido service_type_id -> regra global, pra view marcar quais
        // linhas da cidade selecionada ainda NÃO têm override próprio.
        $globalPorTipo = [];
        foreach ($regrasGlobais as $g) { $globalPorTipo[(int)$g['service_type_id']] = $g; }
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/catalogo_servicos_tarifas.php';
    }

    public function tarifaSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $serviceTypeId = (int)($_POST['service_type_id'] ?? 0);
        $cidadeId = (int)($_POST['cidade_id'] ?? 0) ?: null;
        $querystringVolta = $cidadeId !== null ? '?cidade_id=' . $cidadeId : '';
        if ($serviceTypeId <= 0) {
            $this->setFlashMessage('Tipo de serviço inválido.', 'error');
            $this->redirect('/admin/catalogo-servicos/tarifas' . $querystringVolta);
        }

        $tipo = ServiceType::buscarPorId($serviceTypeId);
        if (!$tipo) {
            $this->setFlashMessage('Tipo de serviço não encontrado.', 'error');
            $this->redirect('/admin/catalogo-servicos/tarifas' . $querystringVolta);
        }

        $dados = [
            'base_fee' => (float)str_replace(',', '.', (string)($_POST['base_fee'] ?? '0')),
            'pickup_km_price' => (float)str_replace(',', '.', (string)($_POST['pickup_km_price'] ?? '0')),
            'tow_km_price' => $_POST['tow_km_price'] !== '' && isset($_POST['tow_km_price'])
                ? (float)str_replace(',', '.', (string)$_POST['tow_km_price'])
                : null,
            'labor_fee' => (float)str_replace(',', '.', (string)($_POST['labor_fee'] ?? '0')),
            'minimum_price' => (float)str_replace(',', '.', (string)($_POST['minimum_price'] ?? '0')),
            'night_multiplier' => (float)str_replace(',', '.', (string)($_POST['night_multiplier'] ?? '1')),
            'holiday_multiplier' => (float)str_replace(',', '.', (string)($_POST['holiday_multiplier'] ?? '1')),
            'active' => !empty($_POST['active']),
        ];

        ServicePricingRule::salvar($serviceTypeId, $dados, $cidadeId);

        Logger::log(Logger::LEVEL_INFO, 'AdminServiceCatalogController', 'tarifaSalvar', 'catalogo_servicos',
            "Tarifa do tipo de serviço #{$serviceTypeId} ({$tipo['code']}) salva por admin #{$user['id']}" . ($cidadeId !== null ? " (cidade #{$cidadeId})" : ' (global)'),
            ['service_type_id' => $serviceTypeId, 'code' => $tipo['code'], 'admin_id' => $user['id'], 'cidade_id' => $cidadeId, 'dados' => $dados]);

        $this->setFlashMessage('Tarifa salva com sucesso.', 'success');
        $this->redirect('/admin/catalogo-servicos/tarifas' . $querystringVolta);
    }

    /**
     * Remove o override de uma cidade específica pra um tipo de serviço,
     * voltando a valer a tarifa GLOBAL pra ela (ver
     * ServicePricingRule::buscarPorServiceType, que cai pro cidade_id IS
     * NULL quando não encontra o específico). Nunca remove a regra
     * global — só overrides de cidade (cidade_id > 0).
     */
    public function tarifaRemoverOverride(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }
        $serviceTypeId = (int)($_POST['service_type_id'] ?? 0);
        $cidadeId = (int)($_POST['cidade_id'] ?? 0);
        if ($serviceTypeId > 0 && $cidadeId > 0) {
            ServicePricingRule::removerOverrideCidade($serviceTypeId, $cidadeId);
            Logger::log(Logger::LEVEL_INFO, 'AdminServiceCatalogController', 'tarifaRemoverOverride', 'catalogo_servicos',
                "Override de tarifa removido: tipo #{$serviceTypeId}, cidade #{$cidadeId}, por admin #{$user['id']}",
                ['service_type_id' => $serviceTypeId, 'cidade_id' => $cidadeId, 'admin_id' => $user['id']]);
            $this->setFlashMessage('Override de cidade removido — voltou a valer a tarifa global.', 'success');
        }
        $this->redirect('/admin/catalogo-servicos/tarifas?cidade_id=' . $cidadeId);
    }

    // ─── Etapa 16 — compatibilidade prestador × veículo (admin CRUD) ────────
    // Categorias operacionais do MVP (Etapa 14/15) — VARCHAR, sem catálogo.
    private const CATEGORIAS = [
        'automovel_passeio' => 'Automóvel de passeio',
        'moto'              => 'Motocicleta',
        'utilitario'        => 'Utilitário/Van',
        'caminhao_leve'     => 'Caminhão leve',
    ];

    /**
     * Tela única de compatibilidade por serviço: requisitos do serviço +
     * capacidades veiculares dos prestadores para aquele serviço.
     */
    public function compatibilidade(): void
    {
        AuthService::requireAuth('admin');
        $tipos = ServiceType::listarTodos();
        $serviceTypeId = (int)($_GET['service_type_id'] ?? 0);
        if ($serviceTypeId <= 0 && !empty($tipos)) {
            $serviceTypeId = (int)$tipos[0]['id'];
        }
        $tipo = $serviceTypeId > 0 ? ServiceType::buscarPorId($serviceTypeId) : null;

        $requisitos = $serviceTypeId > 0 ? ServiceVehicleRequirement::listarPorServico($serviceTypeId) : [];
        $requisitosPorCategoria = [];
        foreach ($requisitos as $r) {
            $requisitosPorCategoria[$r['vehicle_category'] ?? '_geral'] = $r;
        }

        // Prestadores (guinchos) + capacidades veiculares já configuradas p/ este serviço.
        $pdo = getPDO();
        $stmt = $pdo->query(
            "SELECT g.id, u.nome FROM guinchos g JOIN usuarios u ON u.id = g.usuario_id
             WHERE g.aprovado = 1 ORDER BY u.nome ASC"
        );
        $prestadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $capacidades = [];
        if ($serviceTypeId > 0) {
            $stmt = $pdo->prepare(
                "SELECT psvc.*, u.nome AS prestador_nome
                 FROM provider_service_vehicle_capabilities psvc
                 JOIN guinchos g ON g.id = psvc.provider_id
                 JOIN usuarios u ON u.id = g.usuario_id
                 WHERE psvc.service_type_id = ?
                 ORDER BY u.nome ASC, psvc.vehicle_category ASC"
            );
            $stmt->execute([$serviceTypeId]);
            $capacidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Peças/produtos pré-selecionados para este serviço (orçamento).
        $produtosSugeridos = $serviceTypeId > 0 ? ServiceTypeProduto::listarPorServico($serviceTypeId) : [];

        $categorias = self::CATEGORIAS;
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/catalogo_servicos_compatibilidade.php';
    }

    public function requisitoSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $serviceTypeId = (int)($_POST['service_type_id'] ?? 0);
        $categoria = (string)($_POST['vehicle_category'] ?? '');
        if ($serviceTypeId <= 0) {
            $this->setFlashMessage('Serviço inválido.', 'error');
            $this->redirect('/admin/catalogo-servicos/compatibilidade');
        }
        if ($categoria !== '' && !isset(self::CATEGORIAS[$categoria])) {
            $categoria = '';
        }
        ServiceVehicleRequirement::salvar($serviceTypeId, $categoria !== '' ? $categoria : null, [
            'requires_platform' => !empty($_POST['requires_platform']),
            'requires_winch' => !empty($_POST['requires_winch']),
            'requires_dolly' => !empty($_POST['requires_dolly']),
            'requires_battery_tester' => !empty($_POST['requires_battery_tester']),
            'requires_jump_starter' => !empty($_POST['requires_jump_starter']),
            'requires_hydraulic_jack' => !empty($_POST['requires_hydraulic_jack']),
            'minimum_unit_capacity_kg' => $_POST['minimum_unit_capacity_kg'] ?? '',
            'electric_certification_required' => !empty($_POST['electric_certification_required']),
            'active' => true,
        ]);
        Logger::log(Logger::LEVEL_INFO, 'AdminServiceCatalogController', 'requisitoSalvar', 'catalogo_servicos',
            "Requisito veicular salvo (serviço #{$serviceTypeId}, categoria '{$categoria}') por admin #{$user['id']}",
            ['service_type_id' => $serviceTypeId, 'vehicle_category' => $categoria, 'admin_id' => $user['id']]);
        $this->setFlashMessage('Requisito salvo.', 'success');
        $this->redirect('/admin/catalogo-servicos/compatibilidade?service_type_id=' . $serviceTypeId);
    }

    public function capacidadeVeicularSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $serviceTypeId = (int)($_POST['service_type_id'] ?? 0);
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $categoria = (string)($_POST['vehicle_category'] ?? '');
        if ($serviceTypeId <= 0 || $providerId <= 0 || !isset(self::CATEGORIAS[$categoria])) {
            $this->setFlashMessage('Dados de capacidade inválidos.', 'error');
            $this->redirect('/admin/catalogo-servicos/compatibilidade?service_type_id=' . $serviceTypeId);
        }
        ProviderServiceVehicleCapability::salvar($providerId, $serviceTypeId, $categoria, [
            'approval_status' => in_array($_POST['approval_status'] ?? '', ['PENDING','APPROVED','SUSPENDED','REJECTED'], true) ? $_POST['approval_status'] : 'APPROVED',
            'enabled' => !empty($_POST['enabled']) ? 1 : 0,
            'max_vehicle_weight_kg' => ($_POST['max_vehicle_weight_kg'] ?? '') !== '' ? (float)$_POST['max_vehicle_weight_kg'] : null,
            'supports_electric' => !empty($_POST['supports_electric']) ? 1 : 0,
            'supports_hybrid' => !empty($_POST['supports_hybrid']) ? 1 : 0,
            'supports_locked_wheels' => !empty($_POST['supports_locked_wheels']) ? 1 : 0,
            'supports_damaged_vehicle' => !empty($_POST['supports_damaged_vehicle']) ? 1 : 0,
            'supports_subsoil_access' => !empty($_POST['supports_subsoil_access']) ? 1 : 0,
            'requires_manual_confirmation' => !empty($_POST['requires_manual_confirmation']) ? 1 : 0,
        ]);
        Logger::log(Logger::LEVEL_INFO, 'AdminServiceCatalogController', 'capacidadeVeicularSalvar', 'catalogo_servicos',
            "Capacidade veicular salva (prestador #{$providerId}, serviço #{$serviceTypeId}, {$categoria}) por admin #{$user['id']}",
            ['provider_id' => $providerId, 'service_type_id' => $serviceTypeId, 'vehicle_category' => $categoria, 'admin_id' => $user['id']]);
        $this->setFlashMessage('Capacidade veicular salva.', 'success');
        $this->redirect('/admin/catalogo-servicos/compatibilidade?service_type_id=' . $serviceTypeId);
    }
}
