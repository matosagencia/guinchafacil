<?php

class Usuario {
    public static function criar($dados) {
        $sql = "INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, criado_em) \n                VALUES (:nome, :email, :senha_hash, :telefone, :cpf, :tipo, NOW())";
        
        $stmt = getPDO()->prepare($sql);
        $stmt->execute([
            ':nome' => $dados['nome'],
            ':email' => $dados['email'],
            ':senha_hash' => $dados['senha_hash'],
            ':telefone' => $dados['telefone'],
            ':cpf' => $dados['cpf'],
            ':tipo' => $dados['tipo']
        ]);
        
        return getPDO()->lastInsertId();
    }
    
    public static function buscarPorEmail($email) {
        $sql = "SELECT * FROM usuarios WHERE email = :email";
        $stmt = getPDO()->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public static function buscarPorId($id) {
        $sql = "SELECT * FROM usuarios WHERE id = :id";
        $stmt = getPDO()->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public static function atualizar($id, $dados) {
        $campos = [];
        $valores = [':id' => $id];
        
        foreach ($dados as $campo => $valor) {
            if (in_array($campo, ['nome', 'telefone', 'cpf', 'senha_hash'])) {
                $campos[] = "$campo = :$campo";
                $valores[":$campo"] = $valor;
            }
        }
        
        if (empty($campos)) return false;
        
        $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = getPDO()->prepare($sql);
        return $stmt->execute($valores);
    }
    
    public static function ativar($id) {
        $sql = "UPDATE usuarios SET ativo = 1 WHERE id = :id";
        $stmt = getPDO()->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public static function suspender($id) {
        $sql = "UPDATE usuarios SET ativo = 0 WHERE id = :id";
        $stmt = getPDO()->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public static function listar($filtros = [], $pagina = 1) {
        $offset = ($pagina - 1) * 20;
        $where = [];
        $valores = [];
        
        if (!empty($filtros['tipo'])) {
            $where[] = "tipo = :tipo";
            $valores[':tipo'] = $filtros['tipo'];
        }
        
        if (isset($filtros['ativo'])) {
            $where[] = "ativo = :ativo";
            $valores[':ativo'] = $filtros['ativo'];
        }
        
        if (!empty($filtros['busca'])) {
            $where[] = "(nome LIKE :busca OR email LIKE :busca)";
            $valores[':busca'] = '%' . $filtros['busca'] . '%';
        }
        
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        
        $sql = "SELECT * FROM usuarios $whereClause ORDER BY criado_em DESC LIMIT 20 OFFSET :offset";
        $valores[':offset'] = $offset;
        
        $stmt = getPDO()->prepare($sql);
        $stmt->execute($valores);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function contarTotal($filtros = []) {
        $where = [];
        $valores = [];
        
        if (!empty($filtros['tipo'])) {
            $where[] = "tipo = :tipo";
            $valores[':tipo'] = $filtros['tipo'];
        }
        
        if (isset($filtros['ativo'])) {
            $where[] = "ativo = :ativo";
            $valores[':ativo'] = $filtros['ativo'];
        }
        
        if (!empty($filtros['busca'])) {
            $where[] = "(nome LIKE :busca OR email LIKE :busca)";
            $valores[':busca'] = '%' . $filtros['busca'] . '%';
        }
        
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) as total FROM usuarios $whereClause";
        $stmt = getPDO()->prepare($sql);
        $stmt->execute($valores);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }

    public static function updateLastLogin(int $id): void
    {
        $pdo = getPDO();
        $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$id]);
    }

}
