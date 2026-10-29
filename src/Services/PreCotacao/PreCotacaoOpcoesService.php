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

        // §23: disponibilidade somente; nenhum dinheiro calculado aqui.

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
            return [
                'modo' => 'reboque',
                'disponivel' => true,
                'preco_status' => 'NAO_CALCULADO',
                'fallback_tipo' => null,
            ];
        }

        // Só os modos de assistência consultam oficinas/especialistas.
        $temGuincho = GuinchoDisponibilidade::existeNoRaio($latOrigem, $lngOrigem);
        $oficinas = self::oficinasParaServico($latOrigem, $lngOrigem, $slugServico);

        // ─── Modo LOCAL (resolver no local) ───
        if ($modo === 'local') {
            if (empty($oficinas)) {
                return [
                    'modo' => 'local',
                    'disponivel' => false,
                    'fallback_tipo' => $temGuincho ? 'guincho' : 'suporte',
                    'servico' => $slugServico,
                    'mensagem' => 'Nenhuma oficina para ' . $slugServico . ' na sua região.',
                ];
            }
            return [
                'modo' => 'local',
                'disponivel' => true,
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
                'servico' => $slugServico,
                'mensagem' => 'Nenhuma oficina para ' . $slugServico . ' na sua região.',
            ];
        }

        return [
            'modo' => 'orientacao',
            'disponivel' => true,
            'oficinas_encontradas' => count($oficinas),
            'servico' => $slugServico,
            'fallback_tipo' => null,
        ];
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