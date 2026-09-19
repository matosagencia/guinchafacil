<?php

namespace App\Services\Prospeccao;

use PDO;
// TODO: ajustar para o namespace real do Logger do projeto, ex:
// use App\Services\Logger;

class ProspeccaoParceirosService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SerpApiMapsClient $serpApi,
        private readonly RegiaoQuotaService $quotaService,
        private readonly MensagemPersuasaoService $mensagemService,
    ) {
    }

    /**
     * Busca leads novos para uma região em todas as categorias
     * configuradas, deduplica e grava com score calculado.
     * Consome 1 crédito SerpApi por categoria (mais se paginar).
     */
    public function buscarLeadsParaRegiao(int $regiaoId, int $paginasPorCategoria = 1): int
    {
        $regiao = $this->buscarRegiao($regiaoId);
        if (!$regiao) {
            throw new \RuntimeException("Região {$regiaoId} não encontrada");
        }

        $categorias = array_filter(array_map('trim', explode(',', $regiao['categorias_alvo'])));
        $inseridos = 0;

        foreach ($categorias as $categoria) {
            $resultados = $this->serpApi->buscar($categoria, (float) $regiao['lat'], (float) $regiao['lng'], $paginasPorCategoria);

            foreach ($resultados as $r) {
                if ($this->inserirLead($regiaoId, $r, $regiao)) {
                    $inseridos++;
                }
            }
        }

        Logger::event([
            'level' => 'info',
            'system' => 'PROSPECCAO',
            'class' => self::class,
            'function' => __FUNCTION__,
            'file' => __FILE__,
            'phase' => 'busca_serpapi',
            'code' => 'PROSPECCAO_LEADS_COLETADOS',
            'message' => "{$inseridos} leads novos coletados",
            'context' => ['regiao_id' => $regiaoId, 'categorias' => $categorias],
        ]);

        return $inseridos;
    }

    private function inserirLead(int $regiaoId, array $dado, array $regiao): bool
    {
        $telefoneNormalizado = preg_replace('/\D/', '', (string) ($dado['telefone'] ?? ''));
        if ($telefoneNormalizado === '') {
            return false; // sem telefone não dá pra contatar, descarta
        }

        $score = $this->quotaService->calcularScore($dado, $regiao);

        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO prospeccao_leads
                (regiao_id, place_id, nome_negocio, categoria, telefone, telefone_normalizado,
                 endereco, website, rating, reviews_count, score_go, status)
             VALUES
                (:regiao_id, :place_id, :nome_negocio, :categoria, :telefone, :telefone_normalizado,
                 :endereco, :website, :rating, :reviews_count, :score_go, 'novo')"
        );

        $stmt->execute([
            'regiao_id' => $regiaoId,
            'place_id' => $dado['place_id'] ?? null,
            'nome_negocio' => $dado['nome_negocio'],
            'categoria' => $dado['categoria'],
            'telefone' => $dado['telefone'] ?? null,
            'telefone_normalizado' => $telefoneNormalizado,
            'endereco' => $dado['endereco'] ?? null,
            'website' => $dado['website'] ?? null,
            'rating' => $dado['rating'] ?? null,
            'reviews_count' => $dado['reviews_count'] ?? null,
            'score_go' => $score,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Monta a fila do dia: top-N leads por score, respeitando a
     * ordem de prioridade das regiões (fuseki). Não envia nada —
     * só prepara o rascunho pra revisão/clique manual (Fase 0).
     *
     * @return array<int, array<string, mixed>>
     */
    public function gerarFilaDoDia(int $limite = 20): array
    {
        $regioes = $this->quotaService->listarRegioesAtivas();
        if (empty($regioes)) {
            return [];
        }

        $regioesPorId = array_column($regioes, null, 'id');
        $idsRegioes = array_map('strval', array_keys($regioesPorId));

        $placeholders = implode(',', array_fill(0, count($idsRegioes), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM prospeccao_leads
             WHERE status = 'novo' AND regiao_id IN ({$placeholders})
             ORDER BY score_go DESC
             LIMIT " . (int) $limite
        );
        $stmt->execute($idsRegioes);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fila = [];
        foreach ($leads as $lead) {
            $regiao = $regioesPorId[$lead['regiao_id']];
            $convite = $this->mensagemService->gerarConvite($lead, $regiao);

            $fila[] = [
                'lead' => $lead,
                'regiao' => $regiao,
                'convite' => $convite,
            ];
        }

        return $fila;
    }

    public function marcarComoEnviado(int $leadId, string $mensagemTexto, ?string $waLink, int $usuarioId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE prospeccao_leads SET status = 'enviado' WHERE id = ? AND status = 'novo'")
                ->execute([$leadId]);

            $this->pdo->prepare(
                "INSERT INTO prospeccao_convites (lead_id, canal, mensagem_texto, wa_link, enviado_por_usuario_id, enviado_em)
                 VALUES (?, 'whatsapp_manual', ?, ?, ?, NOW())"
            )->execute([$leadId, $mensagemTexto, $waLink, $usuarioId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Confirma que o lead virou parceiro cadastrado de fato.
     * É este método — não o envio da mensagem — que fecha a
     * quota da região (ver RegiaoQuotaService::registrarCadastroConfirmado).
     */
    public function confirmarCadastro(int $leadId): void
    {
        $stmt = $this->pdo->prepare('SELECT regiao_id FROM prospeccao_leads WHERE id = ?');
        $stmt->execute([$leadId]);
        $regiaoId = $stmt->fetchColumn();

        if ($regiaoId === false) {
            throw new \RuntimeException("Lead {$leadId} não encontrado");
        }

        $this->pdo->prepare("UPDATE prospeccao_leads SET status = 'cadastrado' WHERE id = ?")
            ->execute([$leadId]);

        $this->quotaService->registrarCadastroConfirmado((int) $regiaoId);
    }

    private function buscarRegiao(int $regiaoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM prospeccao_regioes WHERE id = ?');
        $stmt->execute([$regiaoId]);
        $regiao = $stmt->fetch(PDO::FETCH_ASSOC);

        return $regiao ?: null;
    }
}
