<?php

declare(strict_types=1);

final class RegiaoQuotaService
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarRegioesAtivas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM prospeccao_regioes
             WHERE status = 'ativa'
             ORDER BY prioridade_fuseki ASC,
                      (quota_atingida / GREATEST(quota_alvo, 1)) ASC,
                      nome ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO prospeccao_regioes
                (nome, cidade, uf, lat, lng, raio_km, categorias_alvo, quota_alvo, prioridade_fuseki)
             VALUES
                (:nome, :cidade, :uf, :lat, :lng, :raio_km, :categorias_alvo, :quota_alvo, :prioridade_fuseki)"
        );
        $stmt->execute([
            'nome' => (string)($dados['nome'] ?? ''),
            'cidade' => (string)($dados['cidade'] ?? ''),
            'uf' => (string)($dados['uf'] ?? ''),
            'lat' => (float)($dados['lat'] ?? 0),
            'lng' => (float)($dados['lng'] ?? 0),
            'raio_km' => (int)($dados['raio_km'] ?? 15),
            'categorias_alvo' => (string)($dados['categorias_alvo'] ?? ''),
            'quota_alvo' => (int)($dados['quota_alvo'] ?? 5),
            'prioridade_fuseki' => (int)($dados['prioridade_fuseki'] ?? 100),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function buscarPorNomeCidadeUf(string $nome, string $cidade, string $uf): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM prospeccao_regioes
             WHERE nome = ? AND cidade = ? AND uf = ?
             LIMIT 1"
        );
        $stmt->execute([$nome, $cidade, strtoupper($uf)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function salvarOuAtualizar(array $dados): int
    {
        $nome = trim((string)($dados['nome'] ?? ''));
        $cidade = trim((string)($dados['cidade'] ?? ''));
        $uf = strtoupper(trim((string)($dados['uf'] ?? '')));

        if ($nome === '' || $cidade === '' || !preg_match('/^[A-Z]{2}$/', $uf)) {
            throw new RuntimeException('Dados invalidos para salvar a regiao.');
        }

        $existente = $this->buscarPorNomeCidadeUf($nome, $cidade, $uf);
        $payload = [
            'nome' => $nome,
            'cidade' => $cidade,
            'uf' => $uf,
            'lat' => (float)($dados['lat'] ?? 0),
            'lng' => (float)($dados['lng'] ?? 0),
            'raio_km' => max(1, (int)($dados['raio_km'] ?? 15)),
            'categorias_alvo' => (string)($dados['categorias_alvo'] ?? ''),
            'quota_alvo' => max(1, (int)($dados['quota_alvo'] ?? 5)),
            'prioridade_fuseki' => (int)($dados['prioridade_fuseki'] ?? 100),
        ];

        if ($existente) {
            $stmt = $this->pdo->prepare(
                "UPDATE prospeccao_regioes
                    SET lat = :lat,
                        lng = :lng,
                        raio_km = :raio_km,
                        categorias_alvo = :categorias_alvo,
                        quota_alvo = :quota_alvo,
                        prioridade_fuseki = :prioridade_fuseki,
                        status = :status
                  WHERE id = :id"
            );
            $stmt->execute([
                'lat' => $payload['lat'],
                'lng' => $payload['lng'],
                'raio_km' => $payload['raio_km'],
                'categorias_alvo' => $payload['categorias_alvo'],
                'quota_alvo' => $payload['quota_alvo'],
                'prioridade_fuseki' => $payload['prioridade_fuseki'],
                'status' => (string)($dados['status'] ?? 'ativa'),
                'id' => (int)$existente['id'],
            ]);

            return (int)$existente['id'];
        }

        return $this->criar($payload);
    }

    public function registrarCadastroConfirmado(int $regiaoId, bool $gerenciarTransacao = true): void
    {
        if ($gerenciarTransacao) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT quota_alvo, quota_atingida FROM prospeccao_regioes WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$regiaoId]);
            $regiao = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$regiao) {
                throw new RuntimeException("Regiao {$regiaoId} nao encontrada");
            }

            $novaQuota = (int)$regiao['quota_atingida'] + 1;
            $status = $novaQuota >= (int)$regiao['quota_alvo'] ? 'concluida' : 'ativa';

            $update = $this->pdo->prepare(
                'UPDATE prospeccao_regioes SET quota_atingida = ?, status = ? WHERE id = ?'
            );
            $update->execute([$novaQuota, $status, $regiaoId]);

            if ($gerenciarTransacao) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($gerenciarTransacao && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function calcularScore(array $lead, array $regiao): float
    {
        $temSinalDeVida = ($lead['reviews_count'] ?? 0) >= 1 || !empty($lead['website']);
        if (!$temSinalDeVida) {
            return 0.0;
        }

        $reputacao = min((float)($lead['rating'] ?? 3.0), 5.0) * 4;
        $atividade = min((int)($lead['reviews_count'] ?? 0), 50) * 0.4;
        $abertura = (1 - ((int)$regiao['quota_atingida'] / max((int)$regiao['quota_alvo'], 1))) * 40;
        $prioridadeRegiao = max(0, 20 - (int)$regiao['prioridade_fuseki']);

        return round($reputacao + $atividade + $abertura + $prioridadeRegiao, 2);
    }
}
