<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/TarifaService.php';
require_once __DIR__ . '/PartnerLocationService.php';

final class DecisaoAtendimentoService
{
    private const TIPOS_SUPORTE = [];

    /** Decisão operacional: SEM preço, SEM split e SEM distância artificial. */
    public function avaliar(int $pedidoId, string $tipoProblema, float $lat, float $lng, array $opcoes = []): array
    {
        // Esta classe não escreve pedido vindo do endpoint público; pedidoId ignorado.
        $tipo = self::normalizarTipo($tipoProblema);
        $oficinas = $this->oficinasNoRaio($lat, $lng, $tipo);
        $online = $this->reboquesOnlineNoRaio($lat, $lng);
        $reboque = $online > 0;
        $assistencia = $this->comparativoHabilitado()
            && $tipo !== 'reboque' && count($oficinas) > 0;
        $necessitaReboque = $tipo === 'reboque' || !$assistencia;
        $opcoesDisp = [];
        if ($assistencia) $opcoesDisp[] = 'assistencia';
        if ($reboque) $opcoesDisp[] = 'reboque';
        if ($necessitaReboque && !$reboque) {
            return [
                'acao' => 'encaminhar_whatsapp', 'opcoes_disponiveis' => [],
                'opcao_assistencia' => ['disponivel' => false],
                'opcao_reboque' => ['disponivel' => false],
                'recomendacao' => null,
                'justificativa' => 'Nenhum guincho disponivel neste momento.',
                'mensagem_suporte' => 'Entre em contato com a central para atendimento.',
                'reboques_online' => 0, 'oficina_mais_proxima' => null,
                'preco_status' => 'NAO_CALCULADO',
            ];
        }
        $melhor = $necessitaReboque ? 'reboque' : 'assistencia';
        return [
            'opcoes_disponiveis' => $opcoesDisp,
            'opcao_assistencia' => [
                'disponivel' => $assistencia,
                'profissionais_proximos' => count($oficinas),
                'mensagem' => 'Disponibilidade verificada. Valor calculado na cotacao oficial.',
            ],
            'opcao_reboque' => [
                'disponivel' => $reboque,
                'mensagem' => 'Reboque disponivel. Valor calculado na cotacao oficial.',
            ],
            'recomendacao' => $melhor,
            'justificativa' => $necessitaReboque
                ? 'A modalidade selecionada necessita de reboque.'
                : 'Ha atendimento local elegivel. A cotacao oficial definira o valor.',
            'oficina_mais_proxima' => $oficinas ? $this->formatarOficina($oficinas[0]) : null,
            'oficinas_encontradas' => array_map(fn($o) => $this->formatarOficina($o), $oficinas),
            'preco_status' => 'NAO_CALCULADO',
        ];
    }

    public function descontoPercentual(): float
    {
        $raw = (float)Configuracao::get('desconto_saida_oficina_percentual', '0.21');
        return $raw > 1 ? $raw / 100 : max(0.0, $raw);
    }

    public function calcularValorComDesconto(float $valor): array
    {
        $percentual = $this->descontoPercentual();
        $desconto = round(max(0.0, $valor) * $percentual, 2);
        return [
            'valor_original' => round(max(0.0, $valor), 2),
            'desconto' => $desconto,
            'valor_final' => round(max(0.0, $valor - $desconto), 2),
            'percentual' => $percentual,
        ];
    }

    public static function normalizarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        return match ($tipo) {
            'combustivel', 'pane seca', 'pane-seca' => 'pane_seca',
            'eletrica', 'pane eletrica', 'pane-eletrica' => 'pane_eletrica',
            'mecanica', 'mecanico', 'pane mecanica', 'pane-mecanica' => 'pane_mecanica',
            'reboque' => 'reboque',
            'bateria' => 'bateria',
            'pneu' => 'pneu',
            'chaveiro' => 'chaveiro',
            'colisao' => 'colisao',
            default => $tipo,
        };
    }

    private function comparativoHabilitado(): bool
    {
        $valor = strtolower(trim((string)Configuracao::get('habilitar_comparativo_assistencia', '1')));
        return in_array($valor, ['1', 'true', 'sim', 'yes', 'on'], true);
    }

    private function oficinasNoRaio(float $lat, float $lng, string $tipoProblema = ''): array
    {
        $rows = $this->buscarProvidersNoRaio($lat, $lng);
        if (!empty($rows)) return $rows;
        return $this->buscarOficinasNoRaio($lat, $lng, $tipoProblema);
    }

    private function buscarProvidersNoRaio(float $lat, float $lng): array
    {
        $rows = [];
        foreach (PartnerLocationService::listarWorkshopsOperacionais() as $row) {
            if ($row['latitude'] === null || $row['longitude'] === null) {
                error_log('[DecisaoAtendimentoService] Oficina inelegivel sem coordenadas: provider_id=' . (int)$row['provider_id']);
                continue;
            }
            $dist = GeoService::haversine($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
            if ($dist <= (float)$row['raio_resgate_direto_km']) {
                $row['distancia_km'] = round($dist, 2);
                $rows[] = $row;
            }
        }
        usort($rows, fn($a, $b) => $a['distancia_km'] <=> $b['distancia_km']);
        return $rows;
    }

    private function buscarOficinasNoRaio(float $lat, float $lng, string $tipoProblema): array
    {
        $mapaServico = [
            'pneu' => 'borracharia', 'eletrica' => 'eletrica', 'pane_eletrica' => 'eletrica',
            'bateria' => 'bateria', 'mecanica' => 'mecanica', 'pane_mecanica' => 'mecanica',
            'chaveiro' => 'chaveiro',
        ];
        $servicoTipo = $mapaServico[$tipoProblema] ?? null;

        // FASE A v2: se o servico NAO e reconhecido, retorna vazio (nao faz match cego).
        if ($tipoProblema !== '' && $servicoTipo === null) {
            Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'decisao_atendimento',
                'Servico nao mapeado — sem match.', ['tipo_problema' => $tipoProblema]);
            return [];
        }

        try {
            $sql = "SELECT o.id AS provider_id, o.nome, o.latitude, o.longitude,
                           COALESCE(o.raio_atendimento_km, 10) AS raio_resgate_direto_km,
                           0 AS taxa_resgate_direto
                      FROM oficinas o
                     WHERE o.ativo = 1
                       AND o.disponivel = 1
                       AND o.latitude IS NOT NULL
                       AND o.longitude IS NOT NULL";
            if ($servicoTipo !== null) {
                $sql .= " AND EXISTS (SELECT 1 FROM oficina_servicos os
                                       WHERE os.oficina_id = o.id
                                         AND os.tipo = :servico AND os.ativo = 1)";
            }
            $stmt = getPDO()->prepare($sql);
            if ($servicoTipo !== null) $stmt->bindValue(':servico', $servicoTipo);
            $stmt->execute();

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dist = GeoService::haversine($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
                if ($dist <= (float)$row['raio_resgate_direto_km']) {
                    $row['distancia_km'] = round($dist, 2);
                    $rows[] = $row;
                }
            }
            usort($rows, fn($a, $b) => $a['distancia_km'] <=> $b['distancia_km']);

            Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'decisao_atendimento', 'Busca em oficinas locais.', [
                'tipo_problema' => $tipoProblema, 'servico' => $servicoTipo, 'encontradas' => count($rows),
            ]);
            return $rows;
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'decisao_atendimento', $e, ['phase' => 'oficinas_locais']);
            return [];
        }
    }

    private function formatarOficina(array $oficina): array
    {
        return [
            'provider_id' => (int)($oficina['provider_id'] ?? 0),
            'nome' => (string)($oficina['nome'] ?? ''),
            'distancia_km' => round((float)($oficina['distancia_km'] ?? 0), 2),
            'taxa_resgate_direto' => round((float)($oficina['taxa_resgate_direto'] ?? 0), 2),
        ];
    }

    private function descontoPercentualFormatado(): string
    {
        return rtrim(rtrim(number_format($this->descontoPercentual() * 100, 2, ',', '.'), '0'), ',') . '%';
    }

    /** Retorna lista pública de oficinas no raio (para a camada de confirmação). */
    public function oficinasProximasPublico(float $lat, float $lng, string $tipoProblema = ''): array
    {
        $oficinas = $this->oficinasNoRaio($lat, $lng, $tipoProblema);
        return array_map(fn($o) => [
            'id' => (int)($o['provider_id'] ?? 0),
            'nome' => (string)($o['nome'] ?? ''),
            'distancia_km' => round((float)($o['distancia_km'] ?? 0), 2),
            'taxa_saida' => null, // preço somente pela autoridade oficial
        ], $oficinas);
    }

    /**
     * v22: conta guinchos aprovados, disponíveis e dentro do raio de cobertura.
     * Retorna 1 em caso de erro (não bloqueia o fluxo por falha de schema).
     */
    private function reboquesOnlineNoRaio(float $lat, float $lng): int
    {
        try {
            $stmt = getPDO()->query(
                "SELECT id, lat_atual, lng_atual, COALESCE(raio_cobertura_km, 50) AS raio
                   FROM guinchos
                  WHERE aprovado = 1
                    AND disponivel = 1
                    AND lat_atual IS NOT NULL
                    AND lng_atual IS NOT NULL"
            );
            $count = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $dist = GeoService::haversine($lat, $lng, (float)$g['lat_atual'], (float)$g['lng_atual']);
                if ($dist <= (float)$g['raio']) $count++;
            }
            return $count;
        } catch (Throwable $e) {
            error_log('[reboquesOnlineNoRaio] ' . $e->getMessage());
            return 1; // erro de schema → não bloqueia
        }
    }
}
