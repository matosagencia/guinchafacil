<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Models/Configuracao.php';
require_once __DIR__ . '/../GuinchoDisponibilidade.php';

/**
 * Monta o payload de opções da pré-cotação v2.
 *
 * Modos:
 *   - orientacao  → Cards A (assistência) + B (reboque). Fallback: guincho se tem,
 *                   senão WhatsApp suporte.
 *   - local       → Só Card A (sem comparação com reboque).
 *                   Sem assistência: fallback para guincho se houver reboque apto,
 *                   senão WhatsApp suporte.
 *   - reboque     → Retorna valor do reboque direto (usado no fluxo "levar o carro").
 *                   Fallback: SEMPRE WhatsApp suporte.
 */
final class PreCotacaoOpcoesService
{
    public static function montar(
        string $modo,
        string $slugServico,
        float $latOrigem,
        float $lngOrigem,
        ?float $latDestino = null,
        ?float $lngDestino = null
    ): array {
        $modo = in_array($modo, ['orientacao', 'local', 'reboque'], true) ? $modo : 'orientacao';
        $slugServico = trim($slugServico);

        $taxaReboque = self::taxaBaseReboque();

        // ─── Modo REBOQUE (levar o carro) ───
        // Retorno antecipado: este modo NÃO consulta oficinas. A fonte de
        // verdade é a disponibilidade imediata de prestador de reboque.
        if ($modo === 'reboque') {
            $temGuincho = GuinchoDisponibilidade::existeNoRaio($latOrigem, $lngOrigem);
            if (!$temGuincho) {
                return [
                    'modo' => 'reboque',
                    'disponivel' => false,
                    'fallback_tipo' => 'suporte',
                    'mensagem' => 'Nenhum guincho disponível na sua região agora.',
                ];
            }
            $distancia = self::calcularDistancia($latOrigem, $lngOrigem, $latDestino, $lngDestino);
            return [
                'modo' => 'reboque',
                'disponivel' => true,
                'valor_reboque' => self::calcularValorReboque($distancia),
                'taxa_base_reboque' => $taxaReboque,
                'distancia_km' => $distancia,
                'fallback_tipo' => null,
            ];
        }

        // Só os modos de assistência consultam oficinas/especialistas.
        $temGuincho = GuinchoDisponibilidade::existeNoRaio($latOrigem, $lngOrigem);
        $oficinas = self::oficinasParaServico($latOrigem, $lngOrigem, $slugServico);
        $valorDeslocamento = self::valorDeslocamentoServico($slugServico);

        // ─── Modo LOCAL (resolver no local) ───
        if ($modo === 'local') {
            if (empty($oficinas)) {
                return [
                    'modo' => 'local',
                    'disponivel' => false,
                    'fallback_tipo' => $temGuincho ? 'guincho' : 'suporte',
                    'taxa_base_reboque' => $taxaReboque,
                    'servico' => $slugServico,
                    'mensagem' => 'Nenhuma oficina para ' . $slugServico . ' na sua região.',
                ];
            }
            return [
                'modo' => 'local',
                'disponivel' => true,
                'valor_deslocamento' => $valorDeslocamento,
                'abatido_do_reparo' => true,
                'oficinas_encontradas' => count($oficinas),
                'servico' => $slugServico,
                'fallback_tipo' => null,
                // SEM taxa_base_reboque: não se compara com reboque nesse modo
            ];
        }

        // ─── Modo ORIENTACAO (me orientem) ───
        if (empty($oficinas)) {
            return [
                'modo' => 'orientacao',
                'disponivel' => false,
                'fallback_tipo' => $temGuincho ? 'guincho' : 'suporte',
                'taxa_base_reboque' => $taxaReboque,
                'servico' => $slugServico,
                'mensagem' => 'Nenhuma oficina para ' . $slugServico . ' na sua região.',
            ];
        }

        return [
            'modo' => 'orientacao',
            'disponivel' => true,
            'valor_deslocamento' => $valorDeslocamento,
            'taxa_base_reboque' => $taxaReboque,
            'abatido_do_reparo' => true,
            'oficinas_encontradas' => count($oficinas),
            'economia_estimada' => max(0.0, round($taxaReboque - $valorDeslocamento, 2)),
            'servico' => $slugServico,
            'fallback_tipo' => null,
        ];
    }

    private static function valorDeslocamentoServico(string $slug): float
    {
        $base = (float)(Configuracao::get('custo_saida_profissional_padrao', '80.00') ?: 80.0);
        $comissao = (float)(Configuracao::get('comissao_assistencia_percentual', '0.21') ?: 0.21);
        $valor = round($base * (1 + $comissao), 2);

        try {
            $override = Configuracao::get('custo_saida_' . $slug, null);
            if ($override !== null && $override !== '') {
                $valor = (float)$override;
            }
        } catch (Throwable $e) { /* mantém default */ }

        return max(0.0, $valor);
    }

    private static function taxaBaseReboque(): float
    {
        try {
            return (float)(Configuracao::get('taxa_fixa', '150.00') ?: 150.0);
        } catch (Throwable $e) {
            return 150.0;
        }
    }

    private static function calcularValorReboque(float $distanciaKm): float
    {
        if ($distanciaKm <= 0.5) $distanciaKm = 5.0;
        $base = self::taxaBaseReboque();
        try {
            $tarifaKm = (float)(Configuracao::get('tarifa_por_km', '3.50') ?: 3.5);
        } catch (Throwable $e) {
            $tarifaKm = 3.5;
        }
        return round($base + ($tarifaKm * $distanciaKm), 2);
    }

    private static function calcularDistancia(float $lat1, float $lng1, ?float $lat2, ?float $lng2): float
    {
        if ($lat2 === null || $lng2 === null) return 5.0;
        $rad = M_PI / 180;
        $a = sin(($lat2 - $lat1) * $rad / 2) ** 2
            + cos($lat1 * $rad) * cos($lat2 * $rad) * sin(($lng2 - $lng1) * $rad / 2) ** 2;
        $d = 6371.0 * 2 * asin(min(1.0, sqrt($a)));
        return max(0.5, round($d, 2));
    }

    private static function oficinasParaServico(float $lat, float $lng, string $slug): array
    {
        try {
            require_once __DIR__ . '/../DecisaoAtendimentoService.php';
            if (class_exists('DecisaoAtendimentoService') && method_exists('DecisaoAtendimentoService', 'oficinasProximasPublico')) {
                $svc = new DecisaoAtendimentoService();
                return $svc->oficinasProximasPublico($lat, $lng, $slug);
            }
        } catch (Throwable $e) {
            error_log('[PreCotacaoOpcoesService::oficinasParaServico] ' . $e->getMessage());
        }
        return [];
    }
}