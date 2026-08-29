<?php
declare(strict_types=1);

// File: guinchafacil/src/Models/Catalog/ServicePricingRule.php
// ROADMAP socorro automotivo — Fundamento 9 (tarifa multisserviço).

class ServicePricingRule
{
    private const TBL = 'service_pricing_rules';

    /**
     * @param int|null $cidadeId null = só as regras globais (cidade_id IS
     *   NULL); passar um id lista as regras específicas daquela cidade (a
     *   view de admin decide se mostra "global" ou "cidade X" via este
     *   parâmetro, igual ao padrão já usado em /admin/planejamento).
     */
    public static function listarComTipos(?int $cidadeId = null): array
    {
        $sql = "SELECT st.id AS service_type_id, st.code, st.name, st.attendance_mode, spr.*
             FROM service_types st
             LEFT JOIN " . self::TBL . " spr ON spr.service_type_id = st.id AND " .
             ($cidadeId !== null ? "spr.cidade_id = " . (int)$cidadeId : "spr.cidade_id IS NULL") . "
             WHERE st.active = 1
             ORDER BY st.name ASC";
        $stmt = getPDO()->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * §PRECO-POR-CIDADE-01: antes, uma regra global só por service_type_id.
     * Agora tenta a regra da cidade específica primeiro (se $cidadeId
     * informado) e cai pra regra global (cidade_id IS NULL) quando não
     * existir override pra aquela cidade — mesma semântica de fallback do
     * ZonePricingService (zona específica > regra global).
     */
    public static function buscarPorServiceType(int $serviceTypeId, ?int $cidadeId = null): ?array
    {
        if ($cidadeId !== null) {
            $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE service_type_id = ? AND cidade_id = ? LIMIT 1");
            $stmt->execute([$serviceTypeId, $cidadeId]);
            $regra = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($regra) {
                return $regra;
            }
        }
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE service_type_id = ? AND cidade_id IS NULL LIMIT 1");
        $stmt->execute([$serviceTypeId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Upsert idempotente por (service_type_id, cidade_id) — select-then-
     * insert/update explícito (não depende só do índice único, já que
     * MySQL trata múltiplos NULL como distintos num UNIQUE KEY; ver
     * migration_service_pricing_v2_cidade.sql). $cidadeId null grava/atualiza
     * a regra GLOBAL de sempre; um id grava um override específico daquela
     * cidade.
     */
    public static function salvar(int $serviceTypeId, array $dados, ?int $cidadeId = null): void
    {
        $pdo = getPDO();
        $valores = [
            (float)($dados['base_fee'] ?? 0),
            (float)($dados['pickup_km_price'] ?? 0),
            isset($dados['tow_km_price']) && $dados['tow_km_price'] !== null && $dados['tow_km_price'] !== '' ? (float)$dados['tow_km_price'] : null,
            (float)($dados['labor_fee'] ?? 0),
            (float)($dados['minimum_price'] ?? 0),
            (float)($dados['night_multiplier'] ?? 1.0),
            (float)($dados['holiday_multiplier'] ?? 1.0),
            !empty($dados['active']) ? 1 : 0,
        ];

        $sqlBusca = $cidadeId !== null
            ? "SELECT id FROM " . self::TBL . " WHERE service_type_id = ? AND cidade_id = ? LIMIT 1"
            : "SELECT id FROM " . self::TBL . " WHERE service_type_id = ? AND cidade_id IS NULL LIMIT 1";
        $paramsBusca = $cidadeId !== null ? [$serviceTypeId, $cidadeId] : [$serviceTypeId];
        $stmtBusca = $pdo->prepare($sqlBusca);
        $stmtBusca->execute($paramsBusca);
        $existenteId = $stmtBusca->fetchColumn();

        if ($existenteId !== false) {
            $pdo->prepare(
                "UPDATE " . self::TBL . "
                 SET base_fee = ?, pickup_km_price = ?, tow_km_price = ?, labor_fee = ?,
                     minimum_price = ?, night_multiplier = ?, holiday_multiplier = ?, active = ?, updated_at = NOW()
                 WHERE id = ?"
            )->execute([...$valores, (int)$existenteId]);
            return;
        }

        $pdo->prepare(
            "INSERT INTO " . self::TBL . "
                (service_type_id, cidade_id, base_fee, pickup_km_price, tow_km_price, labor_fee,
                 minimum_price, night_multiplier, holiday_multiplier, active, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
        )->execute([$serviceTypeId, $cidadeId, ...$valores]);
    }

    /**
     * Remove só o override de uma cidade específica (cidade_id > 0) — nunca
     * a linha global (cidade_id IS NULL), que é a fonte de verdade quando
     * nenhuma cidade tem tarifa própria configurada.
     */
    public static function removerOverrideCidade(int $serviceTypeId, int $cidadeId): bool
    {
        if ($cidadeId <= 0) {
            return false;
        }
        return getPDO()->prepare("DELETE FROM " . self::TBL . " WHERE service_type_id = ? AND cidade_id = ?")
            ->execute([$serviceTypeId, $cidadeId]);
    }

    /**
     * Estima o valor bruto de um serviço (sem considerar deslocamento real —
     * isso é calculado no momento do pedido). Usado como referência exibida
     * ao cliente antes de confirmar. Não substitui o cálculo oficial do
     * TarifaService para reboque, que continua sendo a fonte de verdade
     * financeira do fluxo de reboque já em produção.
     */
    public static function estimarBase(array $regra, bool $noturno = false, bool $feriado = false): float
    {
        $valor = (float)$regra['base_fee'] + (float)$regra['labor_fee'];
        if ($noturno) {
            $valor *= (float)($regra['night_multiplier'] ?? 1.0);
        }
        if ($feriado) {
            $valor *= (float)($regra['holiday_multiplier'] ?? 1.0);
        }
        return max($valor, (float)($regra['minimum_price'] ?? 0));
    }

    /**
     * §DESLOCAMENTO-01 (26/07/2026, correção de lacuna apontada pelo
     * usuário): até esta correção, `pickup_km_price` (taxa de deslocamento
     * por km) e `labor_fee` desta tabela eram configuráveis no admin
     * ("Tarifas por tipo de serviço") mas NUNCA eram efetivamente lidos ao
     * criar um pedido — ClienteController::pedidoCriar/calcularCusto usava
     * incondicionalmente TarifaService (tarifas de REBOQUE, por categoria de
     * veículo), mesmo para serviços ON_SITE (partida auxiliar, diagnóstico
     * elétrico, troca de pneu etc.), cuja estrutura de preço é diferente
     * (deslocamento do prestador até o cliente, não do veículo até um
     * destino). Este método fecha essa lacuna: calcula o valor REAL
     * (deslocamento incluído) que o cliente paga por um serviço ON_SITE.
     *
     * Devolve null quando não existe regra ativa para o tipo de serviço —
     * o chamador deve então cair em algum valor mínimo de segurança (nunca
     * travar a criação do pedido por falta de configuração administrativa).
     */
    public static function calcularTotal(int $serviceTypeId, float $distanciaKm, ?\DateTimeInterface $dataHora = null, ?int $cidadeId = null): ?array
    {
        $regra = self::buscarPorServiceType($serviceTypeId, $cidadeId);
        if (!$regra || empty($regra['active'])) {
            return null;
        }

        require_once __DIR__ . '/../Feriado.php';
        require_once __DIR__ . '/../../../config.php';
        $dataHora = $dataHora ?? new \DateTimeImmutable();
        $noturno = self::isHorarioNoturno($dataHora);
        $feriado = self::isFeriadoHoje($dataHora);

        $distanciaKm = max(0.0, round($distanciaKm, 2));
        $deslocamento = $distanciaKm * (float)($regra['pickup_km_price'] ?? 0);
        $base = (float)($regra['base_fee'] ?? 0) + $deslocamento + (float)($regra['labor_fee'] ?? 0);

        $multiplicador = 1.0;
        if ($noturno) {
            $multiplicador *= (float)($regra['night_multiplier'] ?? 1.0);
        }
        if ($feriado) {
            $multiplicador *= (float)($regra['holiday_multiplier'] ?? 1.0);
        }

        $valor = round($base * $multiplicador, 2);
        $minimo = (float)($regra['minimum_price'] ?? 0);
        if ($minimo > 0 && $valor < $minimo) {
            $valor = $minimo;
        }

        return [
            'valor' => $valor,
            'distancia_km' => $distanciaKm,
            'detalhe' => [
                'base_fee' => (float)($regra['base_fee'] ?? 0),
                'deslocamento_km' => $distanciaKm,
                'deslocamento_preco_km' => (float)($regra['pickup_km_price'] ?? 0),
                'deslocamento_total' => round($deslocamento, 2),
                'labor_fee' => (float)($regra['labor_fee'] ?? 0),
                'noturno' => $noturno,
                'feriado' => $feriado,
                'multiplicador_aplicado' => $multiplicador,
                'minimo' => $minimo,
                'cidade_id_solicitada' => $cidadeId,
                'cidade_id_regra_aplicada' => $regra['cidade_id'] !== null ? (int)$regra['cidade_id'] : null,
            ],
        ];
    }

    private static function isHorarioNoturno(\DateTimeInterface $dataHora): bool
    {
        require_once __DIR__ . '/../Configuracao.php';
        $cfg = Configuracao::getAll();
        $hora = (int)$dataHora->format('H');
        $inicioNoturno = (int)explode(':', (string)($cfg['turno_noturno_inicio'] ?? '20:00'))[0];
        $fimNoturno = (int)explode(':', (string)($cfg['turno_noturno_fim'] ?? '06:00'))[0];
        if ($inicioNoturno === $fimNoturno) {
            return false;
        }
        if ($inicioNoturno < $fimNoturno) {
            return $hora >= $inicioNoturno && $hora < $fimNoturno;
        }
        return $hora >= $inicioNoturno || $hora < $fimNoturno;
    }

    private static function isFeriadoHoje(\DateTimeInterface $dataHora): bool
    {
        $dataAlvo = $dataHora->format('Y-m-d');
        $mesDiaAlvo = $dataHora->format('m-d');
        foreach (\Feriado::listarAtivos() as $feriado) {
            $dataFeriado = substr((string)$feriado['data'], 0, 10);
            if (!empty($feriado['recorrente_anual'])) {
                if (substr($dataFeriado, 5, 5) === $mesDiaAlvo) {
                    return true;
                }
            } elseif ($dataFeriado === $dataAlvo) {
                return true;
            }
        }
        return false;
    }
}
