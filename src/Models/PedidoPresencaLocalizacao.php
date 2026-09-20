<?php

declare(strict_types=1);

final class PedidoPresencaLocalizacao
{
    public static function criar(array $dados, ?PDO $pdo = null): int
    {
        $accuracy = (float)($dados['precisao_metros'] ?? -1);
        if ($accuracy < 0 || !isset($dados['latitude'], $dados['longitude'])) {
            throw new InvalidArgumentException('Amostra de presença inválida.');
        }
        $pdo ??= getPDO();
        $stmt = $pdo->prepare('INSERT INTO pedido_presenca_localizacoes
            (pedido_id, usuario_id, latitude, longitude, precisao_metros, origem_ponto, captured_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            (int)$dados['pedido_id'], (int)$dados['usuario_id'], (float)$dados['latitude'],
            (float)$dados['longitude'], $accuracy, (string)($dados['origem_ponto'] ?? 'APP_CLIENTE'),
            (string)($dados['captured_at'] ?? date('Y-m-d H:i:s')),
        ]);
        return (int)$pdo->lastInsertId();
    }
}
