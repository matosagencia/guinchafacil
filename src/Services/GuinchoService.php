<?php
// File: guinchafacil/src/Services/GuinchoService.php

class GuinchoService
{
    public function create(array $dados): int
    {
        // dados esperados: usuario_id, cnh_numero, cnh_validade, placa_guincho, 
        //                  capacidade_ton, raio_cobertura_km, chave_pix, chave_pix_tipo,
        //                  foto_veiculo, doc_cnh_frente, doc_cnh_verso
        $userId = $dados['user_id'] ?? ($dados['usuario_id'] ?? 0);
        return (int)Guincho::criarDeRegistro((int)$userId, $dados);
    }

    public function cpfExists(string $cpf): bool
    {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = ? LIMIT 1");
        $stmt->execute([$cpf]);
        return (bool)$stmt->fetch();
    }

    public function placaExists(string $placa): bool
    {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT id FROM guinchos WHERE placa_guincho = ? LIMIT 1");
        $stmt->execute([strtoupper(trim($placa))]);
        return (bool)$stmt->fetch();
    }
}
