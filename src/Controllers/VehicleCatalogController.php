<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/VehicleCatalogController.php
// §CATALOGO-VISUAL-01 (02/08/2026): endpoints JSON PÚBLICOS (sem
// autenticação) que alimentam o autocomplete de marca/modelo — usado tanto
// no cadastro de veículo do CLIENTE (autenticado, mas reaproveita o mesmo
// endpoint) quanto no cadastro de CAMINHÃO do guincheiro, que acontece
// ANTES do login existir (tela de registro pública). Por isso não pode
// exigir 'cliente'/'guincho' — é o mesmo catálogo pros dois.
// Só leitura, só dados já marcados `active`, sem nenhum dado sensível.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Models/Vehicle/VehicleBrand.php';
require_once __DIR__ . '/../Models/Vehicle/VehicleModel.php';

class VehicleCatalogController
{
    /** GET /veiculo-catalogo/marcas — todas as marcas ativas (id, name, logo_path). */
    public function marcas(): void
    {
        $marcas = VehicleBrand::listarAtivas();
        $payload = array_map(static function ($m) {
            return [
                'id' => (int)$m['id'],
                'name' => (string)$m['name'],
                'logo_path' => $m['logo_path'] ?? null,
            ];
        }, $marcas);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** GET /veiculo-catalogo/modelos?marca_id=X — modelos ativos de UMA marca. */
    public function modelos(): void
    {
        $marcaId = (int)($_GET['marca_id'] ?? 0);
        $modelos = $marcaId > 0 ? VehicleModel::listarPorMarca($marcaId) : [];
        $payload = array_map(static function ($mo) {
            return [
                'id' => (int)$mo['id'],
                'name' => (string)$mo['name'],
                'image_path' => $mo['image_path'] ?? null,
            ];
        }, $modelos);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
