<?php
// File: guinchafacil/src/Services/CoberturaService.php

require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/../Models/Catalog/ProviderCapability.php';
require_once __DIR__ . '/../Services/EspecialistaDispatchService.php';

/**
 * §COBERTURA-RAIO-01 (05/08/2026)
 *
 * Antes desta classe, o raio que de fato filtrava a fila de ofertas do
 * guincheiro (GuinchoController::montarOfertasDisponiveis e
 * SseController::pedidosDisponiveis) era só o `raio_maximo_km` GLOBAL de
 * configurações — o raio_cobertura_km de cada guincho (NOT NULL DEFAULT 20
 * no schema, coluna gravada no cadastro) nunca era lido pra filtrar nada,
 * só existia pra exibição no dashboard do próprio guincheiro. Ou seja: um
 * guincheiro que declarou cobrir 15km podia receber ofertas a 50km de
 * distância, e um cliente podia abrir um pedido achando que havia
 * cobertura quando na real nenhum guincho o alcançaria de verdade.
 *
 * Esta classe centraliza a mesma regra de "raio efetivo" nos dois lugares
 * que precisam dela: (1) a fila real de ofertas mostrada ao guincheiro, e
 * (2) o gate de abertura de pedido no ClienteController — pra garantir que
 * "o cliente pode pedir aqui" e "o guincheiro recebe esse pedido" nunca
 * mais divirjam.
 *
 * Regra do raio efetivo: MIN(raio_cobertura_km do próprio guincho,
 * raio_maximo_km global) — o cadastro do guincho só pode RESTRINGIR o
 * alcance dele (respeita o que ele declarou que cobre), nunca ULTRAPASSAR
 * o teto de segurança operacional definido pelo admin.
 */
class CoberturaService
{
    /** Raio efetivo (km) de um guincho: nunca maior que o teto global. */
    public static function raioEfetivoGuincho(array $guincho, ?float $raioMaximoGlobal = null): float
    {
        if ($raioMaximoGlobal === null) {
            require_once __DIR__ . '/../Models/Configuracao.php';
            $cfg = Configuracao::getAll();
            $raioMaximoGlobal = (float)($cfg['raio_maximo_km'] ?? 50);
        }
        $raioProprio = (float)($guincho['raio_cobertura_km'] ?? $raioMaximoGlobal);
        if ($raioProprio <= 0) {
            $raioProprio = $raioMaximoGlobal;
        }
        return min($raioProprio, $raioMaximoGlobal);
    }

    /**
     * Existe pelo menos 1 guincho APROVADO (e com capacidade aprovada pro
     * tipo de serviço, quando aplicável) capaz de alcançar essa coordenada
     * dentro do próprio raio efetivo dele? Não exige `disponivel=1` (online
     * agora) de propósito — um guincho aprovado que está offline no
     * instante da criação do pedido ainda pode aceitar dentro da janela de
     * 30 min (ver cron_cancelar_pedidos_expirados.php); a pergunta aqui é
     * estrutural ("existe alguém que possa ser acionado"), não "está
     * alguém online neste segundo".
     */
    public static function existeGuinchoAlcancavel(float $lat, float $lng, string $attendanceMode, ?int $serviceTypeId): bool
    {
        $pdo = getPDO();
        require_once __DIR__ . '/../Models/Configuracao.php';
        $cfg = Configuracao::getAll();
        $raioMaximoGlobal = (float)($cfg['raio_maximo_km'] ?? 50);

        $sql = "SELECT id, lat_atual, lng_atual, lat_operacao, lng_operacao, raio_cobertura_km, reboque_aprovado
                  FROM guinchos
                 WHERE aprovado = 1";
        $guinchos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($guinchos as $g) {
            if ($attendanceMode === 'TOWING') {
                if ((int)($g['reboque_aprovado'] ?? 1) !== 1) {
                    continue;
                }
            } else {
                if (!$serviceTypeId || !ProviderCapability::possuiCapacidadeAprovada((int)$g['id'], $serviceTypeId)) {
                    continue;
                }
            }

            $latG = is_numeric($g['lat_atual'] ?? null) ? (float)$g['lat_atual'] : (is_numeric($g['lat_operacao'] ?? null) ? (float)$g['lat_operacao'] : null);
            $lngG = is_numeric($g['lng_atual'] ?? null) ? (float)$g['lng_atual'] : (is_numeric($g['lng_operacao'] ?? null) ? (float)$g['lng_operacao'] : null);
            if ($latG === null || $lngG === null || ($latG === 0.0 && $lngG === 0.0)) {
                continue; // guincho sem localização conhecida não conta pra cobertura.
            }

            $distancia = GeoService::haversine($lat, $lng, $latG, $lngG);
            $raioEfetivo = self::raioEfetivoGuincho($g, $raioMaximoGlobal);
            if ($distancia <= $raioEfetivo) {
                return true;
            }
        }

        return false;
    }

    public static function diagnosticarAtendimento(array $pedido): array
    {
        $attendanceMode = strtoupper(trim((string)($pedido['attendance_mode'] ?? '')));
        if ($attendanceMode !== 'ON_SITE') {
            return [
                'status' => 'ok',
                'pode_cobrar' => true,
                'pode_especialista' => true,
                'pode_reboque' => true,
                'mensagem' => null,
            ];
        }

        $lat = is_numeric($pedido['lat_origem'] ?? null) ? (float)$pedido['lat_origem'] : null;
        $lng = is_numeric($pedido['lng_origem'] ?? null) ? (float)$pedido['lng_origem'] : null;
        if ($lat === null || $lng === null) {
            return [
                'status' => 'sem_coordenadas',
                'pode_cobrar' => false,
                'pode_especialista' => false,
                'pode_reboque' => false,
                'mensagem' => 'Não conseguimos validar a cobertura para esta localização.',
            ];
        }

        $serviceTypeId = (int)($pedido['service_type_id'] ?? 0);
        if ($serviceTypeId <= 0) {
            return [
                'status' => 'sem_servico',
                'pode_cobrar' => false,
                'pode_especialista' => false,
                'pode_reboque' => false,
                'mensagem' => 'Não conseguimos identificar o tipo de atendimento para validar a cobertura.',
            ];
        }

        $serviceCode = self::resolverServiceCode($serviceTypeId);
        $especialistas = $serviceCode !== ''
            ? EspecialistaDispatchService::candidatosPorCoordenada($lat, $lng, $serviceCode)
            : [];

        if (!empty($especialistas)) {
            return [
                'status' => 'ok',
                'pode_cobrar' => true,
                'pode_especialista' => true,
                'pode_reboque' => true,
                'mensagem' => null,
                'especialistas_encontrados' => count($especialistas),
                'service_code' => $serviceCode,
            ];
        }

        $podeReboque = self::existeGuinchoAlcancavel($lat, $lng, 'TOWING', null);
        if ($podeReboque) {
            return [
                'status' => 'somente_reboque',
                'pode_cobrar' => false,
                'pode_especialista' => false,
                'pode_reboque' => true,
                'mensagem' => 'Para esta localização, hoje só há cobertura de reboque. O atendimento local não será cobrado agora.',
                'service_code' => $serviceCode,
            ];
        }

        return [
            'status' => 'sem_cobertura',
            'pode_cobrar' => false,
            'pode_especialista' => false,
            'pode_reboque' => false,
            'mensagem' => 'No momento não há cobertura para esta ocorrência. Estamos expandindo a rede nesta região.',
            'service_code' => $serviceCode,
        ];
    }

    private static function resolverServiceCode(int $serviceTypeId): string
    {
        static $cache = [];
        if (array_key_exists($serviceTypeId, $cache)) {
            return $cache[$serviceTypeId];
        }

        try {
            $stmt = getPDO()->prepare('SELECT code FROM service_types WHERE id = ? LIMIT 1');
            $stmt->execute([$serviceTypeId]);
            $code = strtoupper(trim((string)($stmt->fetchColumn() ?: '')));
            return $cache[$serviceTypeId] = $code;
        } catch (Throwable) {
            return $cache[$serviceTypeId] = '';
        }
    }
}
