<?php

class Chat {
    /**
     * Pacote L1.9 — $idempotencyKey, quando informado, evita gravar a mesma
     * mensagem duas vezes (duplo-clique, ou retry de rede após timeout do
     * fetch original). Se já existir uma linha com a mesma
     * (pedido_id, usuario_id, idempotency_key), devolve o id existente em vez
     * de inserir de novo — a garantia real é a UNIQUE KEY no banco
     * (uk_chat_pedido_usuario_idempotency, migration_chat_idempotency_v1.sql);
     * este código só evita depender de exceção no caminho feliz.
     */
    public static function enviar($pedido_id, $usuario_id, $mensagem, ?string $idempotencyKey = null) {
        $pdo = getPDO();

        if ($idempotencyKey !== null && $idempotencyKey !== '' && self::hasIdempotencyColumn($pdo)) {
            $existente = $pdo->prepare(
                "SELECT id FROM chat_mensagens WHERE pedido_id = ? AND usuario_id = ? AND idempotency_key = ? LIMIT 1"
            );
            $existente->execute([$pedido_id, $usuario_id, $idempotencyKey]);
            $id = $existente->fetchColumn();
            if ($id !== false) {
                return (int)$id;
            }

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO chat_mensagens (pedido_id, usuario_id, mensagem, idempotency_key, criado_em)
                     VALUES (:pedido_id, :usuario_id, :mensagem, :idempotency_key, NOW())"
                );
                $stmt->execute([
                    ':pedido_id' => $pedido_id,
                    ':usuario_id' => $usuario_id,
                    ':mensagem' => $mensagem,
                    ':idempotency_key' => $idempotencyKey,
                ]);
                return (int)$pdo->lastInsertId();
            } catch (PDOException $e) {
                // Corrida: outra requisição com a mesma key venceu entre o SELECT e o INSERT.
                if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    $existente->execute([$pedido_id, $usuario_id, $idempotencyKey]);
                    $id = $existente->fetchColumn();
                    if ($id !== false) {
                        return (int)$id;
                    }
                }
                throw $e;
            }
        }

        $sql = "INSERT INTO chat_mensagens (pedido_id, usuario_id, mensagem, criado_em) \n                VALUES (:pedido_id, :usuario_id, :mensagem, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':pedido_id' => $pedido_id,
            ':usuario_id' => $usuario_id,
            ':mensagem' => $mensagem
        ]);

        return $pdo->lastInsertId();
    }

    /**
     * Verifica se a migration da coluna idempotency_key já rodou — permite
     * que o código funcione mesmo em bancos que ainda não aplicaram
     * migration_chat_idempotency_v1.sql (degrada para o comportamento antigo).
     */
    private static function hasIdempotencyColumn(PDO $pdo): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_mensagens' AND COLUMN_NAME = 'idempotency_key'"
            );
            $stmt->execute();
            $cache = (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            $cache = false;
        }
        return $cache;
    }
    
    public static function listarPorPedido($pedido_id, $desde_id = 0) {
        $sql = "SELECT c.*, u.nome as usuario_nome, u.nome as remetente_nome,
                       CASE
                           WHEN c.usuario_id = p.cliente_id THEN 'cliente'
                           WHEN g.usuario_id IS NOT NULL AND c.usuario_id = g.usuario_id THEN 'guincho'
                           ELSE COALESCE(u.tipo, 'usuario')
                       END as remetente_tipo
                FROM chat_mensagens c
                JOIN usuarios u ON c.usuario_id = u.id
                JOIN pedidos p ON p.id = c.pedido_id
                LEFT JOIN guinchos g ON g.id = p.guincho_id
                WHERE c.pedido_id = :pedido_id AND c.id > :desde_id
                ORDER BY c.criado_em ASC";
        
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
