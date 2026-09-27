<?php

require_once __DIR__ . '/../../Services/PricingService.php';
require_once __DIR__ . '/../../Services/GeoService.php';

class PreCotacaoController {
    
    public function triagem() {
        header('Content-Type: application/json');
        
        $dados = json_decode(file_get_contents('php://input'), true);
        $lat = $dados['latitude'] ?? null;
        $lng = $dados['longitude'] ?? null;
        
        if ((!$lat || !$lng) && !empty($dados['endereco'])) {
            $coords = GeoService::geocodificarEndereco($dados['endereco']);
            if ($coords) {
                $lat = $coords['lat'];
                $lng = $coords['lng'];
            }
        }

        $tipoProblema = $dados['tipo_problema'] ?? '';

        if (!$lat || !$lng) {
            echo json_encode(['erro' => 'Não foi possível determinar a localização.']);
            return;
        }

        global $pdo;
        $pricingService = new PricingService($pdo);
        $resultado = $pricingService->gerarOpcoesCotacao($lat, $lng, $tipoProblema);

        echo json_encode($resultado);
    }

    public function recusarAssistencia() {
        header('Content-Type: application/json');
        
        $dados = json_decode(file_get_contents('php://input'), true);
        $valorGuincho = $dados['valor_guincho'] ?? 700.00;

        global $pdo;
        $pricingService = new PricingService($pdo);
        $resultado = $pricingService->aplicarDescontoRecusa($valorGuincho);

        echo json_encode($resultado);
    }
}