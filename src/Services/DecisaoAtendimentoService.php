<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/TarifaService.php';
require_once __DIR__ . '/PartnerLocationService.php';
require_once __DIR__ . '/GuinchoDisponibilidade.php';

final class DecisaoAtendimentoService
{
    private const TIPOS_SUPORTE = [];

    /** Decisão operacional: SEM preço, SEM split e SEM distância artificial. */
    public function avaliar(int $pedidoId, string $tipoProblema, float $lat, float $lng, array $opcoes = []): array
    {
        // Esta classe não escreve pedido vindo do endpoint público; pedidoId ignorado.
        $tipo = self::normalizarTipo($tipoProblema);
        $decisaoOficinas = $this->decidirOficinas($lat, $lng, $tipo);
        $oficinas = $decisaoOficinas['candidatos'];
        $decisaoGuincho = GuinchoDisponibilidade::decidir($lat, $lng);
        $online = (int)($decisaoGuincho['candidatos_elegiveis'] ?? 0);
        $reboque = (bool)($decisaoGuincho['disponivel'] ?? false);
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

    /**
     * Fonte única da decisão de oficinas para o funil.
     *
     * A expansão ocorre naturalmente pela lista completa de candidatos: cada
     * oficina é avaliada pelo próprio raio declarado, e a lista é ordenada por
     * distância. Assim, uma oficina mais distante pode ser usada quando não há
     * uma mais próxima, sem ultrapassar o limite operacional dela.
     */
    public function decidirOficinas(float $lat, float $lng, string $tipoProblema = ''): array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat === 0.0 && $lng === 0.0)) {
            return [
                'disponivel' => false,
                'candidatos' => [],
                'escolhido' => null,
                'motivo' => 'COORDENADA_INVALIDA',
            ];
        }

        $candidatos = $this->oficinasNoRaio($lat, $lng, self::normalizarTipo($tipoProblema));
        return [
            'disponivel' => $candidatos !== [],
            'candidatos' => $candidatos,
            'escolhido' => $candidatos[0] ?? null,
            'motivo' => $candidatos === [] ? 'SEM_OFICINA_ELEGIVEL' : null,
        ];
    }

    private function comparativoHabilitado(): bool
    {
        $valor = strtolower(trim((string)Configuracao::get('habilitar_comparativo_assistencia', '1')));
        return in_array($valor, ['1', 'true', 'sim', 'yes', 'on'], true);
    }

    private function oficinasNoRaio(float $lat, float $lng, string $tipoProblema = ''): array
    {
        // O cadastro atual usa providers/provider_workshop_settings, mas ainda
        // existem oficinas legadas ativas sem vínculo explícito. A presença de
        // um provider não pode esconder essas oficinas do funil.
        $rows = array_merge(
            $this->buscarProvidersNoRaio($lat, $lng, $tipoProblema),
            $this->buscarOficinasNoRaio($lat, $lng, $tipoProblema)
        );
        usort($rows, static fn(array $a, array $b): int =>
            ((float)($a['distancia_km'] ?? 0)) <=> ((float)($b['distancia_km'] ?? 0))
        );
        return $rows;
    }

    private function buscarProvidersNoRaio(float $lat, float $lng, string $tipoProblema = ''): array
    {
        $rows = [];
        foreach (PartnerLocationService::listarWorkshopsOperacionais() as $row) {
            if ($row['latitude'] === null || $row['longitude'] === null) {
                error_log('[DecisaoAtendimentoService] Oficina inelegivel sem coordenadas: provider_id=' . (int)$row['provider_id']);
                continue;
            }
            $dist = GeoService::haversine($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
            if ($dist <= (float)$row['raio_resgate_direto_km']) {
                if ($tipoProblema !== '' && $tipoProblema !== 'reboque') {
                    $tipoServico = match ($tipoProblema) {
                        'pane_eletrica' => 'eletrica',
                        'pane_mecanica' => 'mecanica',
                        'pane_seca' => 'combustivel',
                        default => $tipoProblema,
                    };
                    $slugs = array_map(
                        static fn(array $service): string => strtolower(trim((string)($service['slug'] ?? ''))),
                        $this->servicosDaOficina((int)$row['provider_id'], 'provider')
                    );
                    if (!in_array($tipoServico, $slugs, true)) {
                        continue;
                    }
                }
                $row['distancia_km'] = round($dist, 2);
                $row['location_source'] = 'provider';
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
                      LEFT JOIN oficina_provider_links opl ON opl.oficina_id = o.id
                     WHERE o.ativo = 1
                       AND o.disponivel = 1
                       AND opl.provider_id IS NULL
                       AND o.latitude IS NOT NULL
                       AND o.longitude IS NOT NULL";
            if ($servicoTipo !== null) {
                $catalogCodes = match ($servicoTipo) {
                    'bateria' => ['JUMP_START', 'BATTERY_TEST', 'BATTERY_REPLACEMENT'],
                    'pneu', 'borracharia' => ['TIRE_CHANGE', 'TIRE_INFLATION'],
                    'combustivel' => ['FUEL_DELIVERY'],
                    'mecanica' => ['MECHANICAL_ASSISTANCE'],
                    'eletrica' => ['ELECTRICAL_DIAGNOSIS'],
                    'chaveiro' => ['AUTOMOTIVE_LOCKSMITH'],
                    default => [],
                };
                $codePlaceholders = [];
                foreach ($catalogCodes as $index => $code) {
                    $codePlaceholders[] = ':service_code_' . $index;
                }
                $catalogCondition = "EXISTS (SELECT 1
                    FROM oficina_service_types ost
                    INNER JOIN service_types st ON st.id = ost.service_type_id
                    WHERE ost.oficina_id = o.id
                      AND ost.active = 1
                      AND st.active = 1
                      AND (LOWER(COALESCE(st.public_slug, '')) = :service_slug";
                if ($codePlaceholders) {
                    $catalogCondition .= ' OR st.code IN (' . implode(',', $codePlaceholders) . ')';
                }
                $catalogCondition .= "))";
                $legacyCondition = "EXISTS (SELECT 1 FROM oficina_servicos os
                    WHERE os.oficina_id = o.id
                      AND os.tipo = :legacy_service
                      AND os.ativo = 1)";
                $sql .= ' AND (' . $catalogCondition . ' OR ' . $legacyCondition . ')';
            }
            $stmt = getPDO()->prepare($sql);
            if ($servicoTipo !== null) {
                $stmt->bindValue(':service_slug', $servicoTipo);
                $stmt->bindValue(':legacy_service', $servicoTipo);
                foreach ($catalogCodes as $index => $code) {
                    $stmt->bindValue(':service_code_' . $index, $code);
                }
            }
            $stmt->execute();

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dist = GeoService::haversine($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
                if ($dist <= (float)$row['raio_resgate_direto_km']) {
                    $row['distancia_km'] = round($dist, 2);
                    $row['location_source'] = 'legacy';
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

    private function normalizarSlugCatalogo(array $service): string
    {
        $slug = strtolower(trim((string)($service['public_slug'] ?? '')));
        if ($slug !== '') {
            return $slug;
        }
        return match (strtoupper(trim((string)($service['code'] ?? '')))) {
            'BATTERY_TEST', 'BATTERY_REPLACEMENT', 'JUMP_START' => 'bateria',
            'TIRE_INFLATION', 'TIRE_CHANGE' => 'pneu',
            'FUEL_DELIVERY' => 'combustivel',
            'MECHANICAL_ASSISTANCE' => 'mecanica',
            'ELECTRICAL_DIAGNOSIS', 'ELECTRICAL_DIAG' => 'eletrica',
            'AUTOMOTIVE_LOCKSMITH' => 'chaveiro',
            default => strtolower(trim((string)($service['code'] ?? ''))),
        };
    }

    /** Retorna lista pública de oficinas no raio (para a camada de confirmação). */
    public function oficinasProximasPublico(float $lat, float $lng, string $tipoProblema = ''): array
    {
        $oficinas = $this->decidirOficinas($lat, $lng, $tipoProblema)['candidatos'];
        return array_map(fn($o) => [
            'id' => (int)($o['provider_id'] ?? 0),
            'nome' => (string)($o['nome'] ?? ''),
            'distancia_km' => round((float)($o['distancia_km'] ?? 0), 2),
            'servicos' => $this->servicosDaOficina((int)($o['provider_id'] ?? 0), (string)($o['location_source'] ?? '')),
            'taxa_saida' => null, // preço somente pela autoridade oficial
        ], $oficinas);
    }

    /**
     * v22: conta guinchos aprovados, disponíveis e dentro do raio de cobertura.
     * Retorna 1 em caso de erro (não bloqueia o fluxo por falha de schema).
     */
    private function servicosDaOficina(int $providerId, string $source = ''): array
    {
        if ($providerId <= 0) return [];
        $services = [];
        $capabilityTable = $source === 'provider'
            ? 'provider_workshop_capabilities'
            : 'provider_capabilities';
        $stmt = getPDO()->prepare("SELECT st.id, st.code, st.public_slug, st.name, st.description
            FROM {$capabilityTable} pc
            JOIN service_types st ON st.id = pc.service_type_id
            WHERE pc.provider_id = ? AND pc.enabled = 1 AND pc.approval_status = 'APPROVED'
              AND st.active = 1 AND st.attendance_mode = 'ON_SITE'
            ORDER BY st.name ASC");
        $stmt->execute([$providerId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $slug = $this->normalizarSlugCatalogo($s);
            if ($slug === '') continue;
            $services[$slug] = [
                'id' => (int)$s['id'],
                'slug' => $slug,
                'nome' => (string)$s['name'],
                'descricao' => (string)($s['description'] ?? ''),
            ];
        }
        if ($source === 'legacy') {
            $legacy = getPDO()->prepare("SELECT st.id, st.code, st.public_slug, st.name, st.description
                FROM oficina_service_types ost
                JOIN service_types st ON st.id = ost.service_type_id
                WHERE ost.oficina_id = ? AND ost.active = 1 AND st.active = 1
                  AND st.attendance_mode = 'ON_SITE'
                ORDER BY st.name");
            $legacy->execute([$providerId]);
            foreach ($legacy->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $slug = $this->normalizarSlugCatalogo($s);
                if ($slug === '' || isset($services[$slug])) continue;
                $services[$slug] = [
                    'id' => (int)$s['id'],
                    'slug' => $slug,
                    'nome' => (string)$s['name'],
                    'descricao' => (string)($s['description'] ?? ''),
                ];
            }
            // Compatibilidade com os registros antigos ainda existentes.
            $legacyOld = getPDO()->prepare('SELECT tipo FROM oficina_servicos WHERE oficina_id = ? AND ativo = 1 ORDER BY tipo');
            $legacyOld->execute([$providerId]);
            foreach ($legacyOld->fetchAll(PDO::FETCH_COLUMN) as $tipo) {
                $slug = strtolower(trim((string)$tipo));
                if ($slug === '' || isset($services[$slug])) continue;
                $services[$slug] = ['id' => 0, 'slug' => $slug, 'nome' => ucfirst($slug), 'descricao' => ''];
            }
            uasort($services, static fn(array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
            return array_values($services);
        } else {
            $legacy = getPDO()->prepare("SELECT os.tipo FROM oficina_provider_links opl JOIN oficina_servicos os ON os.oficina_id = opl.oficina_id WHERE opl.provider_id = ? AND os.ativo = 1 ORDER BY os.tipo");
            $legacy->execute([$providerId]);
        }
        foreach ($legacy->fetchAll(PDO::FETCH_COLUMN) as $tipo) {
            $slug = strtolower(trim((string)$tipo));
            if ($slug === '' || isset($services[$slug])) continue;
            $services[$slug] = [
                'id' => 0,
                'slug' => $slug,
                'nome' => ucfirst($slug),
                'descricao' => '',
            ];
        }
        uasort($services, static fn(array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        return array_values($services);
    }

}
