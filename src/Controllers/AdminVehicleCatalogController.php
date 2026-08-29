<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/AdminVehicleCatalogController.php
// §CATALOGO-VISUAL-01 (02/08/2026): biblioteca visual de seleção de veículo
// por marca/modelo/versão. Ressuscita o catálogo estruturado criado em
// migration_vehicle_catalog_v1.sql (Etapa 14 do roadmap) — schema já
// existia (VehicleBrand/VehicleModel/VehicleVersion), mas nenhum controller
// nem view nunca o consumiu; este controller é o CRUD admin desse catálogo.
// Controller próprio (não amontoado em AdminServiceCatalogController) porque
// é um domínio diferente — catálogo de VEÍCULO, não de SERVIÇO.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Vehicle/VehicleBrand.php';
require_once __DIR__ . '/../Models/Vehicle/VehicleModel.php';
require_once __DIR__ . '/../Models/Vehicle/VehicleVersion.php';
require_once __DIR__ . '/../Models/Vehicle/VehicleOperationalCategory.php';
require_once __DIR__ . '/../Services/MediaUploadService.php';
require_once __DIR__ . '/../Services/Logger.php';

class AdminVehicleCatalogController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /** Grid de marcas (logo ou badge de inicial) + contagem de modelos. */
    public function marcas(): void
    {
        AuthService::requireAuth('admin');
        $marcas = VehicleBrand::listarTodas();
        $contagemModelos = VehicleModel::contarPorMarca();
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/catalogo_veiculos_marcas.php';
    }

    /** Form de marca — novo (sem ?id) ou edição (?id=X). */
    public function marcaForm(): void
    {
        AuthService::requireAuth('admin');
        $id = (int)($_GET['id'] ?? 0);
        $marca = $id > 0 ? VehicleBrand::buscarPorId($id) : null;
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/catalogo_veiculos_marca_form.php';
    }

    public function marcaSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $active = !empty($_POST['active']);
        $removerLogo = !empty($_POST['remover_logo']);

        if ($name === '') {
            $this->setFlashMessage('Nome da marca é obrigatório.', 'error');
            $this->redirect('/admin/catalogo-veiculos');
        }

        $logoPath = null; // null = preserva o logo atual
        if ($removerLogo) {
            $logoPath = '';
        } elseif (isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $logoPath = MediaUploadService::storeVehicleBrandLogo($_FILES['logo'], 'marca');
            } catch (\RuntimeException $e) {
                $this->setFlashMessage('Falha no upload do logo: ' . $e->getMessage(), 'error');
                $this->redirect('/admin/catalogo-veiculos');
            }
        }

        if ($id > 0) {
            VehicleBrand::atualizar($id, $name, $active, $logoPath);
            Logger::log(Logger::LEVEL_INFO, 'AdminVehicleCatalogController', 'marcaSalvar', 'catalogo_veiculos',
                "Marca #{$id} atualizada por admin #{$user['id']}", ['marca_id' => $id, 'admin_id' => $user['id']]);
        } else {
            $novoId = VehicleBrand::criar($name);
            if ($logoPath) {
                VehicleBrand::atualizar($novoId, $name, $active, $logoPath);
            }
            Logger::log(Logger::LEVEL_INFO, 'AdminVehicleCatalogController', 'marcaSalvar', 'catalogo_veiculos',
                "Marca #{$novoId} ({$name}) criada por admin #{$user['id']}", ['marca_id' => $novoId, 'admin_id' => $user['id']]);
        }

        $this->setFlashMessage('Marca salva com sucesso.', 'success');
        $this->redirect('/admin/catalogo-veiculos');
    }

    /** Grid de modelos (imagem ou placeholder) de UMA marca. */
    public function modelos(int $marcaId): void
    {
        AuthService::requireAuth('admin');
        $marca = VehicleBrand::buscarPorId($marcaId);
        if (!$marca) {
            $this->setFlashMessage('Marca não encontrada.', 'error');
            $this->redirect('/admin/catalogo-veiculos');
        }
        $modelos = VehicleModel::listarPorMarca($marcaId);
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/catalogo_veiculos_modelos.php';
    }

    /** Form de modelo — novo (?marca_id=X) ou edição (?id=X). */
    public function modeloForm(): void
    {
        AuthService::requireAuth('admin');
        $id = (int)($_GET['id'] ?? 0);
        $modelo = $id > 0 ? VehicleModel::buscarPorId($id) : null;
        $marcaId = $modelo ? (int)$modelo['brand_id'] : (int)($_GET['marca_id'] ?? 0);
        $marca = $marcaId > 0 ? VehicleBrand::buscarPorId($marcaId) : null;
        if (!$marca) {
            $this->setFlashMessage('Marca inválida.', 'error');
            $this->redirect('/admin/catalogo-veiculos');
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/catalogo_veiculos_modelo_form.php';
    }

    public function modeloSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $marcaId = (int)($_POST['marca_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $active = !empty($_POST['active']);
        $removerImagem = !empty($_POST['remover_imagem']);

        if ($marcaId <= 0 || $name === '') {
            $this->setFlashMessage('Marca e nome do modelo são obrigatórios.', 'error');
            $this->redirect('/admin/catalogo-veiculos');
        }

        $imagePath = null; // null = preserva a imagem atual
        if ($removerImagem) {
            $imagePath = '';
        } elseif (isset($_FILES['imagem']) && ($_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $imagePath = MediaUploadService::storeVehicleModelImage($_FILES['imagem'], 'modelo');
            } catch (\RuntimeException $e) {
                $this->setFlashMessage('Falha no upload da imagem: ' . $e->getMessage(), 'error');
                $this->redirect('/admin/catalogo-veiculos/marca/' . $marcaId);
            }
        }

        if ($id > 0) {
            VehicleModel::atualizar($id, $name, $active, $imagePath);
            Logger::log(Logger::LEVEL_INFO, 'AdminVehicleCatalogController', 'modeloSalvar', 'catalogo_veiculos',
                "Modelo #{$id} atualizado por admin #{$user['id']}", ['modelo_id' => $id, 'admin_id' => $user['id']]);
        } else {
            $novoId = VehicleModel::criar($marcaId, $name);
            if ($imagePath) {
                VehicleModel::atualizar($novoId, $name, $active, $imagePath);
            }
            Logger::log(Logger::LEVEL_INFO, 'AdminVehicleCatalogController', 'modeloSalvar', 'catalogo_veiculos',
                "Modelo #{$novoId} ({$name}) criado por admin #{$user['id']}", ['modelo_id' => $novoId, 'admin_id' => $user['id']]);
        }

        $this->setFlashMessage('Modelo salvo com sucesso.', 'success');
        $this->redirect('/admin/catalogo-veiculos/marca/' . $marcaId);
    }

    /** Lista de versões (dados técnicos) de UM modelo. */
    public function versoes(int $modeloId): void
    {
        AuthService::requireAuth('admin');
        $modelo = VehicleModel::buscarPorId($modeloId);
        if (!$modelo) {
            $this->setFlashMessage('Modelo não encontrado.', 'error');
            $this->redirect('/admin/catalogo-veiculos');
        }
        $marca = VehicleBrand::buscarPorId((int)$modelo['brand_id']);
        $versoes = VehicleVersion::listarPorModelo($modeloId);
        $categorias = VehicleOperationalCategory::listarAtivas();
        $csrfToken = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/admin/catalogo_veiculos_versoes.php';
    }

    /** Form de versão — novo (?modelo_id=X) ou edição (?id=X). */
    /** Compatibilidade com links antigos que ainda usam o sufixo /versoes. */
    public function versoesLegado(int $modeloId): void
    {
        $this->redirect('/admin/catalogo-veiculos/modelo/' . $modeloId);
    }
    public function versaoForm(): void
    {
        AuthService::requireAuth('admin');
        $id = (int)($_GET['id'] ?? 0);
        $versao = $id > 0 ? VehicleVersion::buscarPorId($id) : null;
        $modeloId = $versao ? (int)$versao['model_id'] : (int)($_GET['modelo_id'] ?? 0);
        $modelo = $modeloId > 0 ? VehicleModel::buscarPorId($modeloId) : null;
        if (!$modelo) {
            $this->setFlashMessage('Modelo inválido.', 'error');
            $this->redirect('/admin/catalogo-veiculos');
        }
        $marca = VehicleBrand::buscarPorId((int)$modelo['brand_id']);
        $categorias = VehicleOperationalCategory::listarAtivas();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/catalogo_veiculos_versao_form.php';
    }

    public function versaoSalvar(): void
    {
        $user = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $modeloId = (int)($_POST['modelo_id'] ?? 0);
        $modelo = $modeloId > 0 ? VehicleModel::buscarPorId($modeloId) : null;
        if (!$modelo || trim((string)($_POST['name'] ?? '')) === '' || (int)($_POST['operational_category_id'] ?? 0) <= 0) {
            $this->setFlashMessage('Modelo, nome da versão e categoria operacional são obrigatórios.', 'error');
            $this->redirect('/admin/catalogo-veiculos');
        }

        $dados = [
            'name' => (string)($_POST['name'] ?? ''),
            'start_year' => (string)($_POST['start_year'] ?? ''),
            'end_year' => (string)($_POST['end_year'] ?? ''),
            'engine' => trim((string)($_POST['engine'] ?? '')),
            'fuel_type' => trim((string)($_POST['fuel_type'] ?? '')),
            'transmission_type' => trim((string)($_POST['transmission_type'] ?? '')),
            'traction_type' => trim((string)($_POST['traction_type'] ?? '')),
            'body_type' => trim((string)($_POST['body_type'] ?? '')),
            'start_stop' => !empty($_POST['start_stop']),
            'electric_type' => trim((string)($_POST['electric_type'] ?? '')),
            'operational_category_id' => (int)($_POST['operational_category_id'] ?? 0),
            'curb_weight_kg' => (string)($_POST['curb_weight_kg'] ?? ''),
            'gross_weight_kg' => (string)($_POST['gross_weight_kg'] ?? ''),
            'length_mm' => (string)($_POST['length_mm'] ?? ''),
            'height_mm' => (string)($_POST['height_mm'] ?? ''),
            'active' => !empty($_POST['active']),
        ];

        if ($id > 0) {
            VehicleVersion::atualizar($id, $dados);
            Logger::log(Logger::LEVEL_INFO, 'AdminVehicleCatalogController', 'versaoSalvar', 'catalogo_veiculos',
                "Versão #{$id} atualizada por admin #{$user['id']}", ['versao_id' => $id, 'admin_id' => $user['id']]);
        } else {
            $novoId = VehicleVersion::criar($modeloId, $dados);
            Logger::log(Logger::LEVEL_INFO, 'AdminVehicleCatalogController', 'versaoSalvar', 'catalogo_veiculos',
                "Versão #{$novoId} criada por admin #{$user['id']}", ['versao_id' => $novoId, 'admin_id' => $user['id']]);
        }

        $this->setFlashMessage('Versão salva com sucesso.', 'success');
        $this->redirect('/admin/catalogo-veiculos/modelo/' . $modeloId);
    }
}
