<?php

class Chat {
    public static function enviar($pedido_id, $usuario_id, $mensagem) {
        $sql = "INSERT INTO chat_mensagens (pedido_id, usuario_id, mensagem, criado_em) \n                VALUES (:pedido_id, :usuario_id, :mensagem, NOW())";
        
        $stmt = getPDO()->prepare($sql);
        $stmt->execute([
            ':pedido_id' => $pedido_id,
            ':usuario_id' => $usuario_id,
            ':mensagem' => $mensagem
        ]);
        
        return getPDO()->lastInsertId();
    }
    
    public static function listarPorPedido($pedido_id, $desde_id = 0) {
        $sql = "SELECT c.*, u.nome as usuario_nome \n                FROM chat_mensagens c \n                JOIN usuarios u ON c.usuario_id = u.id \n                WHERE c.pedido_id = :pedido_id AND c.id > :desde_id \n                ORDER BY c.criado_em ASC";
        
        $stmt = getPDO()->prepare($sql);
        $stmt->execute([
            ':pedido_id' => $pedido_id,
            ':desde_id' => $desde_id
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function marcarLidas($pedido_id, $usuario_id) {
        $sql = "UPDATE chat_mensagens SET lida = 1 \n                WHERE pedido_id = :pedido_id AND usuario_id != :usuario_id";
        
        $stmt = getPDO()->prepare($sql);
        return $stmt->execute([
            ':pedido_id' => $pedido_id,
            ':usuario_id' => $usuario_id
        ]);
    }
    
    public static function contarNaoLidas($pedido_id, $usuario_id) {
        $sql = "SELECT COUNT(*) as total \n                FROM chat_mensagens \n                WHERE pedido_id = :pedido_id \n                AND usuario_id != :usuario_id \n                AND lida = 0";
        
        $stmt = getPDO()->prepare($sql);
        $stmt->execute([
            ':pedido_id' => $pedido_id,
            ':usuario_id' => $usuario_id
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }
}
