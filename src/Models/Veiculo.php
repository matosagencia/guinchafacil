<?php

class Veiculo {
    /**
     * Cria um novo veículo para o usuário
     */
    public static function criar($usuario_id, $dados) {
        try {
            $sql = "INSERT INTO veiculos (usuario_id, placa, marca, modelo, ano, cor, tipo, criado_em) \n                    VALUES (:usuario_id, :placa, :marca, :modelo, :ano, :cor, :tipo, NOW())";
            
            $stmt = getPDO()->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':placa' => strtoupper(trim($dados['placa'])),
                ':marca' => trim($dados['marca']),
                ':modelo' => trim($dados['modelo']),
                ':ano' => (int)$dados['ano'],
                ':cor' => trim($dados['cor']),
                ':tipo' => trim($dados['tipo'])
            ]);
            
            return getPDO()->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erro ao criar veículo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lista veículos ativos de um usuário
     */
    public static function listarPorUsuario($usuario_id) {
        try {
            $sql = "SELECT * FROM veiculos \n                    WHERE usuario_id = :usuario_id AND ativo = 1 \n                    ORDER BY criado_em DESC";
            
            $stmt = getPDO()->prepare($sql);
            $stmt->execute([':usuario_id' => $usuario_id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao listar veículos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca veículo por ID
     */
    public static function buscarPorId($id) {
        try {
            $sql = "SELECT v.*, u.nome as nome_proprietario \n                    FROM veiculos v \n                    JOIN usuarios u ON v.usuario_id = u.id \n                    WHERE v.id = :id";
            
            $stmt = getPDO()->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar veículo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualiza dados do veículo
     */
    public static function atualizar($id, $dados) {
        try {
            $sql = "UPDATE veiculos \n                    SET placa = :placa, marca = :marca, modelo = :modelo, \n                        ano = :ano, cor = :cor, tipo = :tipo\n                    WHERE id = :id";
            
            $stmt = getPDO()->prepare($sql);
            return $stmt->execute([
                ':id' => $id,
                ':placa' => strtoupper(trim($dados['placa'])),
                ':marca' => trim($dados['marca']),
                ':modelo' => trim($dados['modelo']),
                ':ano' => (int)$dados['ano'],
                ':cor' => trim($dados['cor']),
                ':tipo' => trim($dados['tipo'])
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar veículo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Desativa um veículo (soft delete)
     */
    public static function desativar($id) {
        try {
            $sql = "UPDATE veiculos SET ativo = 0 WHERE id = :id";
            $stmt = getPDO()->prepare($sql);
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("Erro ao desativar veículo: " . $e->getMessage());
            return false;
        }
    }
}
