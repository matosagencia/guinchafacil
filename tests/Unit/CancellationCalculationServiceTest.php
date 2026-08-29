<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Cancelamento/CancellationCalculationService.php';

/**
 * Pacote L1.6 (pendência #32): teste unitário isolado para
 * CancellationCalculationService — a lacuna era que os testes de
 * integração (CancellationSnapshotTest, CancellationRaceTest,
 * CancellationRefundAtomicityTest) cobrem o fluxo ponta a ponta via
 * CancelamentoService, mas nenhum isola o serviço de cálculo puro nem
 * trava a FORMULA_VERSION contra mudanças silenciosas.
 *
 * Cada snapshot grava a FORMULA_VERSION vigente no momento do cálculo
 * (ver CancellationSnapshot / migration_cancel_snapshot_v2.sql,
 * coluna formula_version). Se este teste quebrar porque a versão mudou,
 * isso é esperado — só exige atualizar a asserção junto com o changelog
 * da fórmula, nunca mudar a versão "de graça" sem revisão.
 */
final class CancellationCalculationServiceTest extends TestCase
{
    private const PEDIDO_ID_FANTASMA = 999999;

    protected function setUp(): void
    {
        // Garante que não sobrou nenhum resumo de percurso associado ao
        // pedido fantasma usado nos testes (senão o cálculo do trecho
        // "a_caminho" deixaria de ser determinístico entre execuções).
        getPDO()->exec("DELETE FROM pedido_percurso_resumos WHERE pedido_id = " . self::PEDIDO_ID_FANTASMA);
    }

    private function cfgPadrao(): array
    {
        return [
            'taxa_cancelamento_fixa' => 15,
            'taxa_cancelamento_percent' => 20,
            'km_bloqueio_cancelamento' => 2,
            'cancelamento_gratis_min' => 5,
        ];
    }

    private function pedidoBase(string $status): array
    {
        return [
            'id' => self::PEDIDO_ID_FANTASMA,
            'status' => $status,
            'custo_final' => 0,
            'custo_estimado' => 100.0,
            'distancia_km' => 10.0,
        ];
    }

    // ─── FORMULA_VERSION ────────────────────────────────────────────────

    public function testFormulaVersionAtualEhV2(): void
    {
        // Trava o valor vigente da constante. Se a fórmula mudar de fato,
        // este teste deve ser atualizado junto — não é permitido incrementar
        // FORMULA_VERSION sem que este teste também mude na mesma revisão.
        $this->assertSame('v2', CancellationCalculationService::FORMULA_VERSION);
    }

    public function testResultadoSempreCarregaAFormulaVersionVigente(): void
    {
        $resultado = CancellationCalculationService::calculate(
            $this->pedidoBase('aguardando_pagamento'),
            $this->cfgPadrao()
        );

        $this->assertSame(CancellationCalculationService::FORMULA_VERSION, $resultado['formula_version']);
    }

    // ─── Status sem penalidade ──────────────────────────────────────────

    public function testAguardandoPagamentoPermiteCancelamentoSemTaxa(): void
    {
        $resultado = CancellationCalculationService::calculate(
            $this->pedidoBase('aguardando_pagamento'),
            $this->cfgPadrao()
        );

        $this->assertTrue($resultado['pode']);
        $this->assertSame(0.0, $resultado['taxa']);
        $this->assertNull($resultado['motivo_bloqueio']);
        $this->assertSame([], $resultado['factors']);
    }

    public function testAguardandoGuinchoPermiteCancelamentoSemTaxa(): void
    {
        $resultado = CancellationCalculationService::calculate(
            $this->pedidoBase('aguardando_guincho'),
            $this->cfgPadrao()
        );

        $this->assertTrue($resultado['pode']);
        $this->assertSame(0.0, $resultado['taxa']);
    }

    // ─── Status irreversíveis ───────────────────────────────────────────

    public function testNoLocalBloqueiaCancelamentoComMotivo(): void
    {
        $resultado = CancellationCalculationService::calculate(
            $this->pedidoBase('no_local'),
            $this->cfgPadrao()
        );

        $this->assertFalse($resultado['pode']);
        $this->assertSame(0.0, $resultado['taxa']);
        $this->assertStringContainsString('irreversível', $resultado['motivo_bloqueio']);
    }

    public function testEmReboqueBloqueiaCancelamentoComMotivo(): void
    {
        $resultado = CancellationCalculationService::calculate(
            $this->pedidoBase('em_reboque'),
            $this->cfgPadrao()
        );

        $this->assertFalse($resultado['pode']);
        $this->assertStringContainsString('irreversível', $resultado['motivo_bloqueio']);
    }

    public function testStatusDesconhecidoBloqueiaCancelamentoComMotivoGenerico(): void
    {
        $resultado = CancellationCalculationService::calculate(
            $this->pedidoBase('concluido'),
            $this->cfgPadrao()
        );

        $this->assertFalse($resultado['pode']);
        $this->assertSame('Pedido não pode ser cancelado nesta fase.', $resultado['motivo_bloqueio']);
    }

    // ─── Status "a_caminho": cálculo determinístico da taxa ────────────

    public function testACaminhoSemPercursoRegistradoAplicaTaxaFixaComoPiso(): void
    {
        // Sem pedido_percurso_resumos para o pedido fantasma, distance_ratio
        // e time_ratio caem em 0, então constitutional_factor vira o piso
        // mínimo (0.1) — e a taxa proporcional (100 * 0,20 * 0,1 = 2,00)
        // fica abaixo da taxa fixa (15), então o piso fixo prevalece.
        $resultado = CancellationCalculationService::calculate(
            $this->pedidoBase('a_caminho'),
            $this->cfgPadrao()
        );

        $this->assertTrue($resultado['pode']);
        $this->assertSame(15.0, $resultado['taxa']);
        $this->assertNull($resultado['motivo_bloqueio']);

        $this->assertSame(0.0, $resultado['factors']['distance_ratio']);
        $this->assertSame(0.0, $resultado['factors']['time_ratio']);
        $this->assertSame(0.1, $resultado['factors']['constitutional_factor']);
        $this->assertSame(100.0, $resultado['factors']['base']);
        $this->assertSame(15.0, $resultado['factors']['fixed_fee']);
        $this->assertSame(20.0, $resultado['factors']['percent']);
        $this->assertSame(2.0, $resultado['factors']['block_km']);
        $this->assertSame(CancellationCalculationService::FORMULA_VERSION, $resultado['formula_version']);
    }

    public function testACaminhoNuncaCobraTaxaAcimaDoValorBase(): void
    {
        $pedido = $this->pedidoBase('a_caminho');
        $pedido['custo_estimado'] = 5.0; // base bem menor que a taxa fixa (15)

        $resultado = CancellationCalculationService::calculate($pedido, $this->cfgPadrao());

        // A fórmula nunca pode cobrar mais do que o próprio valor do pedido.
        $this->assertSame(5.0, $resultado['taxa']);
    }

    public function testCalculoEhDeterministicoParaOMesmoEstado(): void
    {
        $pedido = $this->pedidoBase('a_caminho');
        $cfg = $this->cfgPadrao();

        $primeiro = CancellationCalculationService::calculate($pedido, $cfg);
        $segundo = CancellationCalculationService::calculate($pedido, $cfg);

        $this->assertSame($primeiro, $segundo);
    }

    // ─── §A1 (v2): distance_ratio usa distancia_guincho_origem_km ─────────

    private function inserirResumoOrigem(float $distanceValidatedM, int $durationSeconds): void
    {
        PedidoPercursoResumo::upsert([
            'pedido_id' => self::PEDIDO_ID_FANTASMA,
            'fase' => 'origem',
            'total_points' => 10,
            'valid_points' => 10,
            'rejected_points' => 0,
            'started_at' => date('Y-m-d H:i:s'),
            'last_point_at' => date('Y-m-d H:i:s'),
            'duration_seconds' => $durationSeconds,
            'distance_raw_m' => $distanceValidatedM,
            'distance_validated_m' => $distanceValidatedM,
            'max_gap_seconds' => 0,
            'max_speed_kmh' => 40,
            'tracking_quality' => 'good',
            'last_street' => null,
            'last_latitude' => null,
            'last_longitude' => null,
        ]);
    }

    public function testDistanceRatioUsaDistanciaGuinchoOrigemQuandoDisponivel(): void
    {
        // Guincho percorreu 4km até a origem (POR real); distancia_km do
        // pedido (origem->destino cotada ao cliente) é 10km — se o bug
        // antigo estivesse presente, o ratio usaria 10km como base e daria
        // 0.4. Com a coluna nova (2km, guincho->origem), o ratio correto é
        // min(1.0, 4/2) = 1.0.
        $this->inserirResumoOrigem(4000.0, 60);

        $pedido = $this->pedidoBase('a_caminho');
        $pedido['distancia_km'] = 10.0; // origem->destino (não deve ser usada)
        $pedido['distancia_guincho_origem_km'] = 2.0; // guincho->origem (deve ser usada)

        $resultado = CancellationCalculationService::calculate($pedido, $this->cfgPadrao());

        $this->assertSame(1.0, $resultado['factors']['distance_ratio']);
        $this->assertSame(2.0, $resultado['factors']['distance_base_km']);
    }

    public function testDistanceRatioCaiNoFallbackDeDistanciaKmQuandoColunaNovaEhNula(): void
    {
        // Pedidos aceitos antes da migration (coluna NULL) continuam usando
        // o comportamento legado, pra não travar cancelamentos em andamento.
        $this->inserirResumoOrigem(4000.0, 60);

        $pedido = $this->pedidoBase('a_caminho');
        $pedido['distancia_km'] = 10.0;
        $pedido['distancia_guincho_origem_km'] = null;

        $resultado = CancellationCalculationService::calculate($pedido, $this->cfgPadrao());

        $this->assertSame(0.4, $resultado['factors']['distance_ratio']);
        $this->assertSame(10.0, $resultado['factors']['distance_base_km']);
    }
}
