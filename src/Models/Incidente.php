<?php
declare(strict_types=1);

final class Incidente
{
    public static function criar(array $dados, ?PDO $pdo = null): int
    {
        $pdo = $pdo ?? getPDO();
        $stmt = $pdo->prepare('INSERT INTO incidentes
            (cliente_id, veiculo_id, tipo_problema, descricao_problema,
             lat_origem, lng_origem, endereco_origem, status)
            VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([
            (int)$dados['cliente_id'], (int)$dados['veiculo_id'],
            (string)$dados['tipo_problema'], trim((string)($dados['descricao_problema'] ?? '')),
            (float)$dados['lat_origem'], (float)$dados['lng_origem'],
            trim((string)$dados['endereco_origem']),
            (string)($dados['status'] ?? 'aberto'),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function buscarPorId(int $id, ?PDO $pdo = null): ?array
    {
        $pdo = $pdo ?? getPDO();
        $stmt = $pdo->prepare('SELECT i.*, u.nome AS cliente_nome, u.email AS cliente_email,
                                      v.placa, v.marca, v.modelo
                                 FROM incidentes i
                                 JOIN usuarios u ON u.id=i.cliente_id
                                 JOIN veiculos v ON v.id=i.veiculo_id
                                WHERE i.id=? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function atualizarStatus(int $id, string $status, ?string $resolucao = null, ?PDO $pdo = null): bool
    {
        $pdo = $pdo ?? getPDO();
        $stmt = $pdo->prepare('UPDATE incidentes SET status=?, resolucao_tipo=? WHERE id=?');
        return $stmt->execute([$status, $resolucao, $id]);
    }
}
