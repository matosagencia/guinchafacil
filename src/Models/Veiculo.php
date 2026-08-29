<?php

class Veiculo {
    /**
     * Cria um novo veículo para o usuário.
     *
     * Etapa 14 (declaração veicular, MVP — decisão do usuário): cadastro
     * 100% manual, sem API de placa e sem catálogo marca/modelo/versão.
     * `verification_status` nasce DECLARED; sobe para DOCUMENT_SUBMITTED
     * quando o cliente anexa o CRLV-e (opcional) e só chega a VERIFIED
     * por conferência do admin. O veículo pode pedir socorro comum em
     * qualquer um desses estados — nenhum gate foi ligado aqui.
     */
    public static function criar($usuario_id, $dados) {
        try {
            $documentUploaded = !empty($dados['document_uploaded']) ? 1 : 0;
            $verificationStatus = $documentUploaded ? 'DOCUMENT_SUBMITTED' : 'DECLARED';

            $sql = "INSERT INTO veiculos
                        (usuario_id, placa, cidade_placa, uf_placa, marca, modelo, vehicle_brand_id, vehicle_model_id, ano, cor, tipo, categoria_tarifa,
                         vehicle_type, fuel_type, transmission_type, electric_type, operational_category,
                         has_spare_tire, has_locking_bolt, document_uploaded, document_path, verification_status,
                         criado_em)
                    VALUES
                        (:usuario_id, :placa, :cidade_placa, :uf_placa, :marca, :modelo, :vehicle_brand_id, :vehicle_model_id, :ano, :cor, :tipo, :categoria_tarifa,
                         :vehicle_type, :fuel_type, :transmission_type, :electric_type, :operational_category,
                         :has_spare_tire, :has_locking_bolt, :document_uploaded, :document_path, :verification_status,
                         NOW())";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':placa' => strtoupper(trim($dados['placa'])),
                ':cidade_placa' => trim((string)($dados['cidade_placa'] ?? '')),
                ':uf_placa' => strtoupper(trim((string)($dados['uf_placa'] ?? ''))),
                ':marca' => trim($dados['marca']),
                ':modelo' => trim($dados['modelo']),
                ':vehicle_brand_id' => !empty($dados['vehicle_brand_id']) ? (int)$dados['vehicle_brand_id'] : null,
                ':vehicle_model_id' => !empty($dados['vehicle_model_id']) ? (int)$dados['vehicle_model_id'] : null,
                ':ano' => (int)$dados['ano'],
                ':cor' => trim($dados['cor']),
                ':tipo' => trim($dados['tipo']),
                ':categoria_tarifa' => !empty($dados['categoria_tarifa']) ? trim((string)$dados['categoria_tarifa']) : null,
                ':vehicle_type' => $dados['vehicle_type'] ?? null,
                ':fuel_type' => $dados['fuel_type'] ?? null,
                ':transmission_type' => $dados['transmission_type'] ?? null,
                ':electric_type' => $dados['electric_type'] ?? null,
                ':operational_category' => $dados['operational_category'] ?? null,
                ':has_spare_tire' => array_key_exists('has_spare_tire', $dados) ? (int)$dados['has_spare_tire'] : null,
                ':has_locking_bolt' => array_key_exists('has_locking_bolt', $dados) ? (int)$dados['has_locking_bolt'] : null,
                ':document_uploaded' => $documentUploaded,
                ':document_path' => $dados['document_path'] ?? null,
                ':verification_status' => $verificationStatus,
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
            $sql = "UPDATE veiculos
                    SET placa = :placa, cidade_placa = :cidade_placa, uf_placa = :uf_placa, marca = :marca, modelo = :modelo,
                        vehicle_brand_id = :vehicle_brand_id, vehicle_model_id = :vehicle_model_id,
                        ano = :ano, cor = :cor, tipo = :tipo,
                        vehicle_type = :vehicle_type, fuel_type = :fuel_type, transmission_type = :transmission_type,
                        electric_type = :electric_type, operational_category = :operational_category,
                        has_spare_tire = :has_spare_tire, has_locking_bolt = :has_locking_bolt
                    WHERE id = :id";

            $stmt = getPDO()->prepare($sql);
            $ok = $stmt->execute([
                ':id' => $id,
                ':placa' => strtoupper(trim($dados['placa'])),
                ':cidade_placa' => trim((string)($dados['cidade_placa'] ?? '')),
                ':uf_placa' => strtoupper(trim((string)($dados['uf_placa'] ?? ''))),
                ':marca' => trim($dados['marca']),
                ':modelo' => trim($dados['modelo']),
                ':vehicle_brand_id' => !empty($dados['vehicle_brand_id']) ? (int)$dados['vehicle_brand_id'] : null,
                ':vehicle_model_id' => !empty($dados['vehicle_model_id']) ? (int)$dados['vehicle_model_id'] : null,
                ':ano' => (int)$dados['ano'],
                ':cor' => trim($dados['cor']),
                ':tipo' => trim($dados['tipo']),
                ':vehicle_type' => $dados['vehicle_type'] ?? null,
                ':fuel_type' => $dados['fuel_type'] ?? null,
                ':transmission_type' => $dados['transmission_type'] ?? null,
                ':electric_type' => $dados['electric_type'] ?? null,
                ':operational_category' => $dados['operational_category'] ?? null,
                ':has_spare_tire' => array_key_exists('has_spare_tire', $dados) ? (int)$dados['has_spare_tire'] : null,
                ':has_locking_bolt' => array_key_exists('has_locking_bolt', $dados) ? (int)$dados['has_locking_bolt'] : null,
            ]);

            if ($ok && !empty($dados['document_uploaded']) && !empty($dados['document_path'])) {
                self::registrarDocumento($id, $dados['document_path']);
            }

            return $ok;
        } catch (PDOException $e) {
            error_log("Erro ao atualizar veículo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra o envio de documento (CRLV-e) opcional e eleva
     * verification_status para DOCUMENT_SUBMITTED — nunca rebaixa um
     * veículo já VERIFIED pelo admin.
     */
    public static function registrarDocumento($id, string $documentPath) {
        try {
            $sql = "UPDATE veiculos
                    SET document_uploaded = 1, document_path = :document_path,
                        verification_status = IF(verification_status = 'VERIFIED', verification_status, 'DOCUMENT_SUBMITTED')
                    WHERE id = :id";
            $stmt = getPDO()->prepare($sql);
            return $stmt->execute([':id' => $id, ':document_path' => $documentPath]);
        } catch (PDOException $e) {
            error_log("Erro ao registrar documento do veículo: " . $e->getMessage());
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
