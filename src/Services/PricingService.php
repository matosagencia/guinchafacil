<?php

require_once __DIR__ . '/GeoService.php';

class PricingService {
    private $config = [];

    public function __construct($pdo = null) {
        $this->carregarConfiguracoes($pdo);
    }

    private function carregarConfiguracoes($pdo) {
        if (!$pdo) {
            global $pdo;
        }
        if ($pdo) {
            $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
            $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $this->config = $configs;
        }
        
        // Valores padrão (fallback) caso o admin não tenha configurado
        $this->config['taxa_saida_assistencia_padrao'] = $this->config['taxa_saida_assistencia_padrao'] ?? 80.00;
        $this->config['valor_base_guincho_padrao'] = $this->config['valor_base_guincho_padrao'] ?? 700.00;
        $this->config['valor_km_extra_guincho'] = $this->config['valor_km_extra_guincho'] ?? 5.00; // NOVO
        $this->config['desconto_recusa_orcamento'] = $this->config['desconto_recusa_orcamento'] ?? 10;
    }

    public function buscarOficinasProximas($latCliente, $lngCliente, $raioMaximo = 10) {
        global $pdo;
        
        // REMOVIDO: taxa_saida (agora vem do admin)
        $sql = "SELECT id, nome, endereco, latitude, longitude, raio_atendimento_km, ativo 
                FROM oficinas 
                WHERE ativo = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL";
                
        $stmt = $pdo->query($sql);
        $oficinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $oficinasComDistancia = [];

        foreach ($oficinas as $oficina) {
            $distancia = GeoService::haversine($latCliente, $lngCliente, $oficina['latitude'], $oficina['longitude']);
            $raioAtendimento = !empty($oficina['raio_atendimento_km']) ? (int)$oficina['raio_atendimento_km'] : $raioMaximo;

            if ($distancia <= $raioAtendimento) {
                $oficina['distancia_km'] = round($distancia, 2);
                $oficinasComDistancia[] = $oficina;
            }
        }

        usort($oficinasComDistancia, function($a, $b) {
            return $a['distancia_km'] <=> $b['distancia_km'];
        });

        return $oficinasComDistancia;
    }

    /**
     * @param float $distanciaDestino Distância total até o destino final em KM (para calcular km extra)
     */
    public function gerarOpcoesCotacao($latCliente, $lngCliente, $tipoProblema, $distanciaDestino = 0) {
        if ($tipoProblema === 'outro' || $tipoProblema === 'orientacao') {
            return ['acao' => 'encaminhar_suporte'];
        }

        $oficinas = $this->buscarOficinasProximas($latCliente, $lngCliente);

        // Valores globais definidos pelo Admin
        $taxaSaidaPadrao = (float)$this->config['taxa_saida_assistencia_padrao'];
        $valorBaseGuincho = (float)$this->config['valor_base_guincho_padrao'];
        $valorKmExtra = (float)$this->config['valor_km_extra_guincho'];
        
        // Cálculo do valor total do guincho (Base + KM Extra)
        $valorTotalGuincho = $valorBaseGuincho + ($distanciaDestino * $valorKmExtra);

        // CENÁRIO SEM OFICINA (Vai direto pro guincho)
        if (empty($oficinas)) {
            return [
                'mostrar_comparativo' => false,
                'opcoes' => [
                    'guincho' => [
                        'titulo' => 'Rebocar',
                        'descricao' => 'Não encontramos mecânicos próximos. Vamos levar seu veículo direto para a oficina.',
                        'valor_base' => $valorBaseGuincho,
                        'valor_km_extra' => $valorKmExtra,
                        'valor_total' => $valorTotalGuincho,
                        'taxa_saida' => 0.00
                    ]
                ]
            ];
        }

        // CENÁRIO COM OFICINA (Mostra comparativo)
        $oficinaMaisProxima = $oficinas[0];

        return [
            'mostrar_comparativo' => true,
            'oficinas_encontradas' => $oficinas,
            'opcoes' => [
                'assistencia_local' => [
                    'titulo' => 'Assistência 24h',
                    'descricao' => 'Um mecânico vai até você. Se não consertar no local, abatemos do reparo.',
                    'valor_taxa' => $taxaSaidaPadrao, // Vem do Admin
                    'prestador_id' => $oficinaMaisProxima['id'],
                    'nome_prestador' => $oficinaMaisProxima['nome'],
                    'distancia_km' => $oficinaMaisProxima['distancia_km']
                ],
                'guincho' => [
                    'titulo' => 'Rebocar',
                    'descricao' => 'Levamos seu carro para a oficina ' . $oficinaMaisProxima['nome'],
                    'valor_base' => $valorBaseGuincho,
                    'valor_km_extra' => $valorKmExtra,
                    'valor_total' => $valorTotalGuincho,
                    'taxa_saida' => 0.00
                ]
            ]
        ];
    }

    public function aplicarDescontoRecusa($valorGuinchoOriginal) {
        $percentualDesconto = (float)$this->config['desconto_recusa_orcamento'];
        $valorComDesconto = $valorGuinchoOriginal - ($valorGuinchoOriginal * ($percentualDesconto / 100));
        
        return [
            'valor_original' => (float)$valorGuinchoOriginal,
            'valor_com_desconto' => round($valorComDesconto, 2),
            'percentual_desconto' => $percentualDesconto,
            'mensagem' => "Desconto de {$percentualDesconto}% aplicado por recusa do orçamento local."
        ];
    }
}