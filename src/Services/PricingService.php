<?php

declare(strict_types=1);

require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/DecisaoAtendimentoService.php';

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
        $decisao = (new DecisaoAtendimentoService())->avaliar(
            0,
            (string)$tipoProblema,
            (float)$latCliente,
            (float)$lngCliente,
            ['distancia_km' => (float)$distanciaDestino]
        );
        $oficina = $decisao['oficina_mais_proxima'] ?? null;
        $assistencia = $decisao['opcao_assistencia'] ?? [];
        $reboque = $decisao['opcao_reboque'] ?? [];
        $opcoes = [
            'guincho' => [
                'titulo' => 'Rebocar',
                'descricao' => (string)($reboque['mensagem'] ?? 'Vamos levar seu veículo até a oficina.'),
                'valor_total' => (float)($reboque['custo_total'] ?? 0.0),
                'taxa_saida' => 0.0,
            ],
        ];

        if (!empty($assistencia['disponivel'])) {
            $opcoes = [
                'assistencia_local' => [
                    'titulo' => 'Assistência 24h',
                    'descricao' => (string)($assistencia['mensagem'] ?? ''),
                    'valor_taxa' => (float)($assistencia['custo_saida'] ?? 0.0),
                    'prestador_id' => (int)($oficina['provider_id'] ?? 0),
                    'nome_prestador' => (string)($oficina['nome'] ?? ''),
                    'distancia_km' => (float)($oficina['distancia_km'] ?? 0.0),
                ],
            ] + $opcoes;
        }

        return [
            'mostrar_comparativo' => count($decisao['opcoes_disponiveis'] ?? []) > 1,
            'oficinas_encontradas' => $decisao['oficinas_encontradas'] ?? [],
            'opcoes' => $opcoes,
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
