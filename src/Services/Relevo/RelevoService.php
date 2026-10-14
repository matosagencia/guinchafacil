<?php
declare(strict_types=1);

/**
 * §10 — Relevo v2.
 *
 * Calcula ganho de elevação acumulado e inclinação máxima de uma rota.
 * Não usa altitude absoluta — isso é dado operacional, não cobra (§5.4).
 *
 * Fonte de dados (em ordem de preferência):
 *   1. Tabela `via_segmentos` (quando populada)
 *   2. Provider externo (OpenTopoData próprio, conforme D21)
 *   3. Fallback neutro: ganho 0, fator 1.00
 *
 * Configurável:
 *   - configuracoes.ganho_elevacao_faixas  ex.: "[100,300,600,1000]"
 *   - configuracoes.ganho_elevacao_mult    ex.: "[1.00,1.05,1.10,1.18,1.25]"
 */
final class RelevoService
{
    public static function calcularRota(
        float $latOrigem,
        float $lngOrigem,
        ?float $latDestino = null,
        ?float $lngDestino = null
    ): array {
        if (!self::coordsValidas($latOrigem, $lngOrigem)) {
            return self::neutro('coordenadas invalidas');
        }

        try {
            // 1. Tentar via_segmentos (se tabela existir e tiver dado)
            $viaTabela = self::tentarViaSegmentos($latOrigem, $lngOrigem, $latDestino, $lngDestino);
            if ($viaTabela !== null) {
                return self::montarResposta($viaTabela, 'via_segmentos');
            }

            // 2. Provider externo (a implementar quando D21 for decidido)
            //    Por enquanto: neutro.
            return self::neutro('sem provider configurado');
        } catch (Throwable $e) {
            error_log('[RelevoService] falha: ' . $e->getMessage());
            return self::neutro('erro: ' . $e->getMessage());
        }
    }

    /**
     * §5.4 — Fator de elevação contínuo (sem degraus).
     *
     * Franquia de 100 m absorve ruído. Acima: 0,02% por metro (0.0002),
     * com teto de 25% (fator 1.25 em 1.350 m efetivos).
     *
     * Configurável:
     *   elevacao_franquia_m     ex.: 100
     *   elevacao_coeficiente    ex.: 0.0002
     *   elevacao_teto           ex.: 0.25
     *   elevacao_peso_deadhead  ex.: 0.5   (peso do trecho vazio)
     */
    public static function fatorElevacao(float $ganhoMetros, float $ganhoDeadheadMetros = 0.0): float
    {
        $cfg = Configuracao::getAll();
        $franquia = (float)($cfg['elevacao_franquia_m']     ?? 100.0);
        $coef     = (float)($cfg['elevacao_coeficiente']    ?? 0.0002);
        $teto     = (float)($cfg['elevacao_teto']           ?? 0.25);
        $peso     = (float)($cfg['elevacao_peso_deadhead']  ?? 0.5);

        $ganhoEfetivo = $ganhoMetros + ($ganhoDeadheadMetros * $peso);

        if ($ganhoEfetivo <= $franquia) {
            return 1.00;
        }

        $excedente = $ganhoEfetivo - $franquia;
        $adicional = min($teto, $excedente * $coef);

        return round(1.00 + $adicional, 4);
    }

    private static function tentarViaSegmentos(
        float $latO,
        float $lngO,
        ?float $latD,
        ?float $lngD
    ): ?array {
        try {
            $pdo = getPDO();
            // Só procede se a tabela existir
            $tabelaExiste = false;
            try {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'via_segmentos'"
                );
                $stmt->execute();
                $tabelaExiste = (int)$stmt->fetchColumn() > 0;
            } catch (Throwable) {
                return null;
            }
            if (!$tabelaExiste) {
                return null;
            }

            $stmt = $pdo->query("SELECT COUNT(*) FROM via_segmentos");
            if ((int)$stmt->fetchColumn() === 0) {
                return null;
            }

            // TODO: calcular ganho acumulado entre origem e destino varrendo
            //       via_segmentos ao longo da polyline. Depende de:
            //       - polyline da rota (OSRM)
            //       - colunas reais da via_segmentos (a definir quando D21 fechar)
            //       Por enquanto, neutro mesmo com tabela populada.
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function montarResposta(array $dados, string $fonte): array
    {
        $ganho = (float)($dados['ganho_elevacao_m'] ?? 0);
        return [
            'ganho_elevacao_m'     => round($ganho, 1),
            'inclinacao_max_graus' => isset($dados['inclinacao_max_graus'])
                ? (float)$dados['inclinacao_max_graus'] : null,
            'fator_elevacao'       => self::fatorElevacao($ganho),
            'fonte'                => $fonte,
        ];
    }

    private static function neutro(string $motivo): array
    {
        return [
            'ganho_elevacao_m'     => 0.0,
            'inclinacao_max_graus' => null,
            'fator_elevacao'       => 1.00,
            'fonte'                => 'neutro',
            'motivo'               => $motivo,
        ];
    }

    private static function coordsValidas(float $lat, float $lng): bool
    {
        if (!is_finite($lat) || !is_finite($lng)) return false;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return false;
        return !($lat === 0.0 && $lng === 0.0);
    }
}
