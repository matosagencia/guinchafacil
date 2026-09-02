<?php

declare(strict_types=1);

final class ProspeccaoParceirosService
{
    /** @var PDO */
    private $pdo;
    /** @var SerpApiMapsClient */
    private $serpApi;
    /** @var RegiaoQuotaService */
    private $quotaService;
    /** @var MensagemPersuasaoService */
    private $mensagemService;

    public function __construct(
        PDO $pdo,
        SerpApiMapsClient $serpApi,
        RegiaoQuotaService $quotaService,
        MensagemPersuasaoService $mensagemService,
    ) {
        $this->pdo = $pdo;
        $this->serpApi = $serpApi;
        $this->quotaService = $quotaService;
        $this->mensagemService = $mensagemService;
    }

    public function serpApiConfigurada(): bool
    {
        return $this->serpApi->isConfigured();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buscarLeadsParaRegiao(int $regiaoId, int $paginasPorCategoria = 1): array
    {
        if (!$this->serpApiConfigurada()) {
            throw new RuntimeException('SERPAPI_KEY nao configurada no .env.');
        }

        $regiao = $this->buscarRegiao($regiaoId);
        if (!$regiao) {
            throw new RuntimeException("Regiao {$regiaoId} nao encontrada");
        }

        $categorias = array_filter(array_map('trim', explode(',', (string)$regiao['categorias_alvo'])));
        $inseridos = 0;
        $totalResultados = [];

        foreach ($categorias as $categoria) {
            $resultados = $this->serpApi->buscar($categoria, (float)$regiao['lat'], (float)$regiao['lng'], $paginasPorCategoria);

            foreach ($resultados as $resultado) {
                if ($this->inserirLead($regiaoId, $resultado, $regiao)) {
                    $inseridos++;
                    $totalResultados[] = $resultado;
                }
            }
        }

        Logger::log(
            Logger::LEVEL_INFO,
            __CLASS__,
            __FUNCTION__,
            'marketing',
            "{$inseridos} leads novos coletados",
            [
                'regiao_id' => $regiaoId,
                'categorias' => $categorias,
                'coletados' => $inseridos,
            ]
        );

        $this->registrarAtividade(
            'operacao',
            'buscar_leads',
            [
                'titulo' => 'Busca de leads na região ' . (string)($regiao['nome'] ?? ''),
                'regiao_id' => $regiaoId,
                'detalhes' => [
                    'categorias' => $categorias,
                    'paginas_por_categoria' => $paginasPorCategoria,
                    'leads_novos' => $inseridos,
                ],
            ]
        );

        return $totalResultados;
    }

    /**
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
             ORDER BY score_go DESC, id DESC
             LIMIT " . (int)$limite
        );
        $stmt->execute($idsRegioes);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fila = [];
        foreach ($leads as $lead) {
            $regiao = $regioesPorId[$lead['regiao_id']] ?? null;
            if (!$regiao) {
                continue;
            }
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
            $this->pdo->prepare("UPDATE prospeccao_leads SET status = 'enviado' WHERE id = ?")
                ->execute([$leadId]);

            $this->pdo->prepare(
                "INSERT INTO prospeccao_convites (lead_id, canal, mensagem_texto, wa_link, enviado_por_usuario_id, enviado_em)
                 VALUES (?, 'whatsapp_manual', ?, ?, ?, NOW())"
            )->execute([$leadId, $mensagemTexto, $waLink, $usuarioId]);

            $this->registrarAtividade('operacao', 'lead_enviado', [
                'lead_id' => $leadId,
                'usuario_id' => $usuarioId,
                'titulo' => 'Lead marcado como enviado',
                'detalhes' => [
                    'wa_link' => $waLink,
                ],
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function confirmarCadastro(int $leadId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT regiao_id, status FROM prospeccao_leads WHERE id = ? FOR UPDATE');
            $stmt->execute([$leadId]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lead) {
                throw new RuntimeException("Lead {$leadId} nao encontrado");
            }

            if (($lead['status'] ?? '') === 'cadastrado') {
                $this->pdo->commit();
                return;
            }

            $this->pdo->prepare("UPDATE prospeccao_leads SET status = 'cadastrado' WHERE id = ?")
                ->execute([$leadId]);

            $this->quotaService->registrarCadastroConfirmado((int)$lead['regiao_id'], false);

            $this->registrarAtividade('operacao', 'lead_cadastrado', [
                'lead_id' => $leadId,
                'regiao_id' => (int)$lead['regiao_id'],
                'titulo' => 'Cadastro confirmado',
                'detalhes' => [
                    'status_anterior' => (string)($lead['status'] ?? ''),
                ],
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function inserirLead(int $regiaoId, array $dado, array $regiao): bool
    {
        $telefoneNormalizado = preg_replace('/\D/', '', (string)($dado['telefone'] ?? ''));
        if ($telefoneNormalizado === '') {
            return false;
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
            'nome_negocio' => $dado['nome_negocio'] ?? '',
            'categoria' => $dado['categoria'] ?? '',
            'telefone' => $dado['telefone'] ?? null,
            'telefone_normalizado' => $telefoneNormalizado,
            'endereco' => $dado['endereco'] ?? null,
            'website' => $dado['website'] ?? null,
            'rating' => $dado['rating'] ?? null,
            'reviews_count' => $dado['reviews_count'] ?? null,
            'score_go' => $score,
        ]);

        if ($stmt->rowCount() > 0) {
            $leadId = (int)$this->pdo->lastInsertId();
            $this->registrarAtividade('contato_obtido', 'lead_coletado', [
                'lead_id' => $leadId > 0 ? $leadId : null,
                'regiao_id' => $regiaoId,
                'titulo' => (string)($dado['nome_negocio'] ?? 'Contato obtido'),
                'detalhes' => [
                    'categoria' => (string)($dado['categoria'] ?? ''),
                    'telefone' => (string)($dado['telefone'] ?? ''),
                    'rating' => $dado['rating'] ?? null,
                    'reviews_count' => $dado['reviews_count'] ?? null,
                ],
            ]);
        }

        return $stmt->rowCount() > 0;
    }

    private function buscarRegiao(int $regiaoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM prospeccao_regioes WHERE id = ?');
        $stmt->execute([$regiaoId]);
        $regiao = $stmt->fetch(PDO::FETCH_ASSOC);

        return $regiao ?: null;
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function registrarAtividade(string $tipo, string $acao, array $dados = []): void
    {
        $titulo = trim((string)($dados['titulo'] ?? ucfirst(str_replace('_', ' ', $acao))));
        $detalhes = $dados['detalhes'] ?? [];
        $stmt = $this->pdo->prepare(
            "INSERT INTO prospeccao_atividades
                (tipo, acao, titulo, detalhes_json, regiao_id, lead_id, usuario_id)
             VALUES
                (:tipo, :acao, :titulo, :detalhes_json, :regiao_id, :lead_id, :usuario_id)"
        );
        $stmt->execute([
            'tipo' => $tipo === 'contato_obtido' ? 'contato_obtido' : 'operacao',
            'acao' => substr($acao, 0, 80),
            'titulo' => $titulo,
            'detalhes_json' => json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'regiao_id' => isset($dados['regiao_id']) ? (int)$dados['regiao_id'] : null,
            'lead_id' => isset($dados['lead_id']) ? (int)$dados['lead_id'] : null,
            'usuario_id' => isset($dados['usuario_id']) ? (int)$dados['usuario_id'] : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarHistorico(int $limite = 25): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, l.nome_negocio, l.telefone, l.categoria, r.nome AS regiao_nome, r.cidade AS regiao_cidade, r.uf AS regiao_uf
               FROM prospeccao_atividades a
          LEFT JOIN prospeccao_leads l ON l.id = a.lead_id
          LEFT JOIN prospeccao_regioes r ON r.id = a.regiao_id
           ORDER BY a.criado_em DESC, a.id DESC
              LIMIT " . max(1, $limite)
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
