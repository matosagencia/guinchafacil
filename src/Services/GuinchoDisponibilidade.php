<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/GuinchoDisponibilidade.php

require_once __DIR__ . '/CoberturaService.php';
require_once __DIR__ . '/../Models/Configuracao.php';

/**
 * Fonte read-only de disponibilidade imediata para REBOQUE.
 *
 * Regra de negocio (2026-10-06):
 * sem guincho apto agora, o fluxo termina antes da criacao do pedido.
 *
 * Este servico NAO cria pedido, NAO altera estado e NAO grava eventos.
 */
final class GuinchoDisponibilidade
{
    /**
     * Retorna true somente quando existe ao menos um prestador apto a receber
     * um pedido TOWING na coordenada informada.
     *
     * $categoria fica reservada para o gate veicular pre-pedido. Hoje o motor
     * real de compatibilidade depende do snapshot de um pedido ja existente;
     * portanto, antes da criacao, nao inventamos uma elegibilidade que o
     * dominio ainda nao consegue provar. Os gates aplicaveis pre-pedido sao:
     * aprovado, disponivel, reboque_aprovado, GPS, nao ocupado e raio.
     */
    public static function existeNoRaio(
        float $lat,
        float $lng,
        ?string $categoria = null,
        ?float $raioKm = null
    ): bool {
        if (!self::coordenadasValidas($lat, $lng)) {
            return false;
        }

        try {
            $cfg = Configuracao::getAll();
            $raioGlobal = (float)($cfg['raio_maximo_km'] ?? 50);
            if ($raioGlobal <= 0) {
                $raioGlobal = 50.0;
            }

            $raioConsulta = $raioGlobal;
            if ($raioKm !== null && $raioKm > 0) {
                $raioConsulta = min($raioConsulta, $raioKm);
            }

            $stmt = getPDO()->query(
                "SELECT
                    g.id,
                    g.lat_atual,
                    g.lng_atual,
                    g.raio_cobertura_km
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
                   )"
            );

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $guincho) {
                $latGuincho = is_numeric($guincho['lat_atual'] ?? null)
                    ? (float)$guincho['lat_atual']
                    : null;
                $lngGuincho = is_numeric($guincho['lng_atual'] ?? null)
                    ? (float)$guincho['lng_atual']
                    : null;

                if ($latGuincho === null || $lngGuincho === null) {
                    continue;
                }

                if (!self::coordenadasValidas($latGuincho, $lngGuincho)) {
                    continue;
                }

                $raioEfetivo = CoberturaService::raioEfetivoGuincho(
                    $guincho,
                    $raioGlobal
                );
                $raioEfetivo = min($raioEfetivo, $raioConsulta);

                if ($raioEfetivo <= 0) {
                    continue;
                }

                $distancia = GeoService::haversine(
                    $lat,
                    $lng,
                    $latGuincho,
                    $lngGuincho
                );

                if ($distancia <= $raioEfetivo) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            // Fail closed: erro de infraestrutura nunca autoriza criacao.
            return false;
        }
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
