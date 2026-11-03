<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/GuinchoDisponibilidade.php

require_once __DIR__ . '/CoberturaService.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/Finance/BloqueioFinanceiroService.php';

/**
 * Fonte read-only de disponibilidade para REBOQUE, agora com an�is
 * conc�ntricos de deadhead compartilhado (�5.5 e �7 do CONTRATOS.md).
 *
 * Regra de neg�cio:
 *  - Sem guincho apto agora, o fluxo termina antes da cria��o do pedido.
 *  - Pre�o � cotado com o deadhead do centro do menor anel que tenha
 *    MIN_CANDIDATOS eleg�veis, garantindo igualdade entre os candidatos
 *    daquele anel.
 *  - Parceiro bloqueado financeiramente (�9) n�o entra em nenhum anel.
 *
 * Este servi�o N�O cria pedido, N�O altera estado e N�O grava eventos.
 */
final class GuinchoDisponibilidade
{
    /** Default quando `configuracoes.guincho_aneis_km` n�o est� definido. */
    private const ANEIS_PADRAO = [2, 5, 10, 20, 35];

    /** Default quando `configuracoes.guincho_anel_min_candidatos` n�o existe. */
    private const MIN_CANDIDATOS_PADRAO = 3;

    /**
     * Devolve todos os an�is com seus candidatos eleg�veis ordenados.
     *
     * Estrutura de retorno:
     * [
     *   [
     *     'idx'        => 0,
     *     'min_km'     => 0.0,
     *     'max_km'     => 2.0,
     *     'centro_km'  => 1.0,   // deadhead_ref
     *     'elegiveis'  => 3,
     *     'candidatos' => [
     *       ['id' => 7, 'dist_km' => 0.8, 'reputacao' => 4.9],
     *       ...
     *     ],
     *   ],
     *   ...
     * ]
     *
     * Nunca lan�a: erro de infra devolve [] (fail closed).
     */
    public static function candidatosPorAnel(
        float $lat,
        float $lng,
        ?int $serviceTypeId = null
    ): array {
        if (!self::coordenadasValidas($lat, $lng)) {
            return [];
        }

        try {
            $cfg          = Configuracao::getAll();
            $raioGlobal   = (float)($cfg['raio_maximo_km'] ?? 50);
            if ($raioGlobal <= 0) {
                $raioGlobal = 50.0;
            }

            $aneisKm      = self::aneisConfigurados($cfg);
            $bloqueados   = BloqueioFinanceiroService::idsBloqueados();

            // Query base - todos os candidatos eleg�veis dentro do maior anel.
            // O raio por guincho continua vindo de CoberturaService, aplicado
            // em PHP logo abaixo (mesmo comportamento do existeNoRaio antigo).
            $sql = "SELECT
                        g.id,
                        g.lat_atual,
                        g.lng_atual,
                        g.raio_cobertura_km,
                        COALESCE(g.reputacao, 0) AS reputacao
                      FROM guinchos g
                     WHERE g.aprovado = 1
                       AND g.disponivel = 1
                       AND COALESCE(g.reboque_aprovado, 0) = 1
                       AND g.lat_atual IS NOT NULL
                       AND g.lng_atual IS NOT NULL
                       AND NOT EXISTS (
                           SELECT 1
                             FROM pedidos p
                            WHERE p.guincho_id = g.id
                              AND p.status IN ('a_caminho', 'no_local', 'em_reboque')
                       )";

            if ($bloqueados) {
                // placeholders int seguros (ids v�m do pr�prio banco)
                $ph = implode(',', array_map('intval', $bloqueados));
                $sql .= " AND g.id NOT IN ({$ph})";
            }

            $stmt = getPDO()->query($sql);

            // Distribui candidatos nos an�is.
            $aneis = self::esqueletoAneis($aneisKm);
            $maiorAnelKm = (float)end($aneisKm);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $latG = is_numeric($g['lat_atual'] ?? null) ? (float)$g['lat_atual'] : null;
                $lngG = is_numeric($g['lng_atual'] ?? null) ? (float)$g['lng_atual'] : null;
                if ($latG === null || $lngG === null) {
                    continue;
                }
                if (!self::coordenadasValidas($latG, $lngG)) {
                    continue;
                }

                $raioEfetivo = CoberturaService::raioEfetivoGuincho($g, $raioGlobal);
                if ($raioEfetivo <= 0) {
                    continue;
                }
                $raioEfetivo = min($raioEfetivo, $maiorAnelKm);

                $dist = GeoService::haversine($lat, $lng, $latG, $lngG);
                if ($dist > $raioEfetivo) {
                    continue;
                }

                $idx = self::indiceDoAnel($dist, $aneisKm);
                if ($idx === null) {
                    continue; // al�m do maior anel
                }

                $aneis[$idx]['candidatos'][] = [
                    'id'        => (int)$g['id'],
                    'dist_km'   => round($dist, 3),
                    'reputacao' => (float)$g['reputacao'],
                ];
            }

            // Ordena candidatos dentro de cada anel e preenche contagem.
            foreach ($aneis as $i => $_) {
                $lista = $aneis[$i]['candidatos'];
                usort($lista, static function (array $a, array $b): int {
                    // 1. menor dist�ncia real
                    if ($a['dist_km'] !== $b['dist_km']) {
                        return $a['dist_km'] <=> $b['dist_km'];
                    }
                    // 2. maior reputa��o
                    if ($a['reputacao'] !== $b['reputacao']) {
                        return $b['reputacao'] <=> $a['reputacao'];
                    }
                    // 3. TODO: aceites recentes (30d) - falta coluna/�ndice.
                    // 4. desempate aleat�rio est�vel na sess�o (por id).
                    return $a['id'] <=> $b['id'];
                });
                $aneis[$i]['candidatos'] = $lista;
                $aneis[$i]['elegiveis']  = count($lista);
            }

            return $aneis;
        } catch (Throwable) {
            // Fail closed: erro de infraestrutura nunca autoriza cria��o.
            return [];
        }
    }

    /**
     * Aplica a regra de escolha (§7.4): menor anel com >= $minCandidatos
     * eleg�veis. Devolve o anel escolhido ou null se nenhum atende.
     *
     * N�o cai em WhatsApp aqui - isso � do consumidor
     * (PreCotacaoOpcoesService). Este m�todo s� devolve null.
     */
    public static function escolherAnel(
        array $aneis,
        int $minCandidatos = self::MIN_CANDIDATOS_PADRAO
    ): ?array {
        foreach ($aneis as $anel) {
            if (($anel['elegiveis'] ?? 0) >= $minCandidatos) {
                return $anel;
            }
        }
        return null;
    }

    /**
     * Decisão única de disponibilidade para reboque.
     * A decisão usada pelo funil, pelo gate de criação e pela precificação
     * precisa ser a mesma: candidatos elegíveis, anel e deadhead.
     */
    public static function decidir(float $lat, float $lng, ?int $serviceTypeId = null): array
    {
        $aneis = self::candidatosPorAnel($lat, $lng, $serviceTypeId);
        try {
            $cfg = Configuracao::getAll();
            $minimo = max(1, (int)($cfg['guincho_anel_min_candidatos'] ?? self::MIN_CANDIDATOS_PADRAO));
        } catch (Throwable) {
            return [
                'disponivel' => false,
                'aneis' => $aneis,
                'anel_escolhido' => null,
                'deadhead_ref' => 0.0,
                'motivo' => 'FALHA_CONFIGURACAO',
                'min_candidatos' => self::MIN_CANDIDATOS_PADRAO,
            ];
        }
        $anel = self::escolherAnel($aneis, $minimo);
        if ($anel === null) {
            // Disponibilidade operacional exige apenas um candidato. O mínimo
            // de candidatos define o anel preferido para cotação/dispatch,
            // mas não pode transformar um guincho elegível em indisponibilidade.
            foreach ($aneis as $anelFallback) {
                if (($anelFallback['elegiveis'] ?? 0) > 0) {
                    $anel = $anelFallback;
                    break;
                }
            }
        }
        $totalElegiveis = array_sum(array_map(
            static fn(array $item): int => (int)($item['elegiveis'] ?? 0),
            $aneis
        ));

        return [
            'disponivel' => $anel !== null,
            'aneis' => $aneis,
            'candidatos_elegiveis' => $totalElegiveis,
            'anel_escolhido' => $anel,
            'deadhead_ref' => $anel === null ? 0.0 : (float)($anel['centro_km'] ?? 0.0),
            'motivo' => $anel === null ? 'SEM_GUINCHO_ELEGIVEL' : null,
            'min_candidatos' => $minimo,
        ];
    }

    public static function temCandidatoElegivel(float $lat, float $lng, ?int $serviceTypeId = null): bool
    {
        return (bool)self::decidir($lat, $lng, $serviceTypeId)['disponivel'];
    }

    /**
     * Compatibilidade com chamadores legados. Devolve true se existe
     * ao menos 1 eleg�vel em qualquer anel. N�o distingue densidade.
     *
     * @deprecated Usar candidatosPorAnel() para cota��o com deadhead.
     */
    public static function existeNoRaio(
        float $lat,
        float $lng,
        ?string $categoria = null,
        ?float $raioKm = null
    ): bool {
        // Compatibilidade semântica: todo chamador antigo passa a obedecer
        // exatamente à decisão central (anel mínimo + bloqueios + ocupação).
        return self::temCandidatoElegivel($lat, $lng);
    }

    /**
     * L� `configuracoes.guincho_aneis_km` (CSV, ex.: "2,5,10,20,35").
     * Cai no default se ausente ou inv�lido.
     *
     * @return float[]
     */
    private static function aneisConfigurados(array $cfg): array
    {
        $raw = trim((string)($cfg['guincho_aneis_km'] ?? ''));
        if ($raw === '') {
            return self::ANEIS_PADRAO;
        }
        $partes = array_filter(
            array_map('trim', explode(',', $raw)),
            static fn($v) => $v !== '' && is_numeric($v) && (float)$v > 0
        );
        $valores = array_map('floatval', $partes);
        sort($valores, SORT_NUMERIC);
        return $valores ?: self::ANEIS_PADRAO;
    }

    /**
     * Cada item tem min, max e centro (deadhead_ref = centro do anel).
     * O primeiro anel come�a em 0 km.
     *
     * @param float[] $limitesKm
     */
    private static function esqueletoAneis(array $limitesKm): array
    {
        $aneis = [];
        $min = 0.0;
        foreach ($limitesKm as $idx => $max) {
            $centro = round(($min + $max) / 2, 3);
            $aneis[] = [
                'idx'        => $idx,
                'min_km'     => $min,
                'max_km'     => (float)$max,
                'centro_km'  => $centro,   // deadhead_ref
                'elegiveis'  => 0,
                'candidatos' => [],
            ];
            $min = (float)$max;
        }
        return $aneis;
    }

    /**
     * Devolve o �ndice do anel cujo [min, max) cont�m $dist. O �ltimo anel
     * inclui o limite superior (dist <= max). Null se al�m do maior.
     *
     * @param float[] $limitesKm
     */
    private static function indiceDoAnel(float $dist, array $limitesKm): ?int
    {
        $min = 0.0;
        $ultimo = count($limitesKm) - 1;
        foreach ($limitesKm as $i => $max) {
            $max = (float)$max;
            if ($i === $ultimo) {
                if ($dist <= $max) {
                    return $i;
                }
            } else {
                if ($dist < $max) {
                    return $i;
                }
            }
            $min = $max;
        }
        return null;
    }

    private static function coordenadasValidas(float $lat, float $lng): bool
    {
        if (!is_finite($lat) || !is_finite($lng)) {
            return false;
        }
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return false;
        }
        return !($lat === 0.0 && $lng === 0.0);
    }
}
