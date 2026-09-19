<?php

namespace App\Services\Prospeccao;

use PDO;

/**
 * "Tabuleiro" de prospecção: cada região é uma área com quota de
 * parceiros. A ordem de trabalho segue uma lógica de abertura (Go):
 *
 *  - prioridade_fuseki baixa  => território mais aberto/valioso,
 *    prospectado primeiro (equivalente a jogar cantos/lados antes
 *    do centro disputado).
 *  - Dentro da mesma prioridade, região menos preenchida
 *    (quota_atingida/quota_alvo) entra primeiro — evita
 *    concentrar tudo numa única cidade.
 *  - Região com quota_atingida >= quota_alvo é marcada 'concluida'
 *    e sai da fila sozinha (grupo "vivo", território fechado).
 */
class RegiaoQuotaService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listarRegioesAtivas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM prospeccao_regioes
             WHERE status = 'ativa'
             ORDER BY prioridade_fuseki ASC,
                      (quota_atingida / GREATEST(quota_alvo, 1)) ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO prospeccao_regioes
                (nome, cidade, uf, lat, lng, raio_km, categorias_alvo, quota_alvo, prioridade_fuseki)
             VALUES (:nome, :cidade, :uf, :lat, :lng, :raio_km, :categorias_alvo, :quota_alvo, :prioridade_fuseki)"
        );
        $stmt->execute([
            'nome' => $dados['nome'],
            'cidade' => $dados['cidade'],
            'uf' => $dados['uf'],
            'lat' => $dados['lat'],
            'lng' => $dados['lng'],
            'raio_km' => $dados['raio_km'] ?? 15,
            'categorias_alvo' => $dados['categorias_alvo'],
            'quota_alvo' => $dados['quota_alvo'] ?? 5,
            'prioridade_fuseki' => $dados['prioridade_fuseki'] ?? 100,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Chamar quando um lead vira parceiro CADASTRADO de verdade
     * (não quando a mensagem é apenas enviada). É esse evento que
     * fecha o "território" e pausa a região automaticamente.
     */
    public function registrarCadastroConfirmado(int $regiaoId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT quota_alvo, quota_atingida FROM prospeccao_regioes WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$regiaoId]);
            $regiao = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$regiao) {
                throw new \RuntimeException("Região {$regiaoId} não encontrada");
            }

            $novaQuota = (int) $regiao['quota_atingida'] + 1;
            $status = $novaQuota >= (int) $regiao['quota_alvo'] ? 'concluida' : 'ativa';

            $update = $this->pdo->prepare(
                'UPDATE prospeccao_regioes SET quota_atingida = ?, status = ? WHERE id = ?'
            );
            $update->execute([$novaQuota, $status, $regiaoId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Score de priorização de um lead dentro da fila do dia.
     * Combina "sinal de vida" (avaliações reais — equivalente a
     * checar se um grupo tem espaço suficiente pra estar vivo no
     * tabuleiro) com quanto a região ainda está aberta.
     */
    public function calcularScore(array $lead, array $regiao): float
    {
        $temSinalDeVida = ($lead['reviews_count'] ?? 0) >= 1 || !empty($lead['website']);
        if (!$temSinalDeVida) {
            return 0.0; // lead "morto": sem info suficiente pra valer contato
        }

        $reputacao = min((float) ($lead['rating'] ?? 3.0), 5.0) * 4;      // 0–20
        $atividade = min((int) ($lead['reviews_count'] ?? 0), 50) * 0.4; // 0–20
        $abertura = (1 - ($regiao['quota_atingida'] / max((int) $regiao['quota_alvo'], 1))) * 40; // 0–40
        $prioridadeRegiao = max(0, 20 - $regiao['prioridade_fuseki']); // fuseki baixo pontua mais

        return round($reputacao + $atividade + $abertura + $prioridadeRegiao, 2);
    }
}
