<?php

class Configuracao {
    public static function get($chave, $default = null) {
        $sql = "SELECT valor FROM configuracoes WHERE chave = :chave";
        $stmt = getPDO()->prepare($sql);
        $stmt->execute([':chave' => $chave]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['valor'] : $default;
    }
    
    public static function set($chave, $valor, $descricao = '') {
        $sql = "INSERT INTO configuracoes (chave, valor, descricao, criado_em, atualizado_em) \n                VALUES (:chave, :valor, :descricao, NOW(), NOW())\n                ON DUPLICATE KEY UPDATE \n                valor = VALUES(valor), \n                atualizado_em = NOW()";
        
        $stmt = getPDO()->prepare($sql);
        return $stmt->execute([
            ':chave' => $chave,
            ':valor' => $valor,
            ':descricao' => $descricao
        ]);
    }
    
    public static function getAll() {
        $sql = "SELECT chave, valor FROM configuracoes";
        $stmt = getPDO()->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $configs = [];
        foreach ($results as $row) {
            $configs[$row['chave']] = $row['valor'];
        }
        
        return $configs;
    }
    
    public static function getMultiplas($chaves) {
        if (empty($chaves)) return [];
        
        $placeholders = str_repeat('?,', count($chaves) - 1) . '?';
        $sql = "SELECT chave, valor FROM configuracoes WHERE chave IN ($placeholders)";
        
        $stmt = getPDO()->prepare($sql);
        $stmt->execute($chaves);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $configs = [];
        foreach ($results as $row) {
            $configs[$row['chave']] = $row['valor'];
        }
        
        return $configs;
    }
}
