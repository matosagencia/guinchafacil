<?php

require_once 'GeoService.php';

class RankingService {
    
    public static function calcularScore($distancia_km, $reputacao) {
        $distancia_ajustada = max(0.1, $distancia_km);
        
        $score_distancia = (1 / $distancia_ajustada) * 0.6;
        $score_reputacao = ($reputacao / 5) * 0.4;
        
        return $score_distancia + $score_reputacao;
    }
    
    public static function buscarGuinchosDisponiveis($lat_cliente, $lng_cliente, $raio_km, $score_minimo) {
        $pdo = getPDO();
        
        $sql = "SELECT u.id, u.nome, u.telefone, g.latitude, g.longitude, g.veiculo_placa, g.veiculo_modelo,\n                       COALESCE(AVG(a.estrelas), 0) as reputacao\n                FROM usuarios u \n                JOIN guinchos g ON u.id = g.usuario_id \n                LEFT JOIN avaliacoes a ON u.id = a.guincho_id\n                WHERE u.tipo = 'guincho' \n                AND u.ativo = 1 \n                AND g.aprovado = 1 \n                AND g.disponivel = 1\n                AND g.latitude IS NOT NULL \n                AND g.longitude IS NOT NULL\n                GROUP BY u.id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $guinchos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $guinchos_ranqueados = [];
        
        foreach ($guinchos as $guincho) {
            $distancia = GeoService::haversine(
                $lat_cliente, 
                $lng_cliente, 
                $guincho['latitude'], 
                $guincho['longitude']
            );
            
            if ($distancia > $raio_km) {
                continue;
            }
            
            $score = self::calcularScore($distancia, $guincho['reputacao']);
            
            if ($score < $score_minimo) {
                continue;
            }
            
            $guincho['distancia'] = round($distancia, 2);
            $guincho['score'] = round($score, 4);
            $guinchos_ranqueados[] = $guincho;
        }
        
        usort($guinchos_ranqueados, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $guinchos_ranqueados;
    }
    
    public static function expandirRaioSePreciso($pedido_id) {
        $pdo = getPDO();
        
        $sql = "SELECT * FROM pedidos WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $pedido_id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido || $pedido['expiracao_aceite'] >= date('Y-m-d H:i:s')) {
            return false;
        }
        
        $novo_raio = $pedido['raio_busca'] + 5;
        
        $sql = "UPDATE pedidos SET raio_busca = :novo_raio WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':novo_raio' => $novo_raio, ':id' => $pedido_id]);
        
        return true;
    }
}
