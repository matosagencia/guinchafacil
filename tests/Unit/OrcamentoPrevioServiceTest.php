<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/OrcamentoPrevioService.php';

final class OrcamentoPrevioServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec('DROP TABLE IF EXISTS pedido_orcamentos_previos');
        $pdo->exec("CREATE TABLE pedido_orcamentos_previos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pedido_id INTEGER NOT NULL,
            provider_id INTEGER NOT NULL,
            taxa_diagnostico_local REAL NOT NULL DEFAULT 0,
            estimativa_minima REAL,
            estimativa_maxima REAL,
            valor_mao_obra REAL,
            valor_pecas REAL,
            valor_total REAL,
            taxa_saida_abater REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL,
            descricao_avaria TEXT,
            descricao TEXT,
            abater_diagnostico_na_os INTEGER NOT NULL DEFAULT 0,
            created_at TEXT,
            updated_at TEXT,
            termo_aceite_cliente_at TEXT,
            approved_at TEXT,
            rejected_at TEXT
        )");
    }

    public function testProviderDefineMaoDeObraPecasETotalSemComissao(): void
    {
        $orcamento = OrcamentoPrevioService::criarOrcamentoProvider([
            'pedido_id' => 10,
            'provider_id' => 20,
            'valor_mao_obra' => 500,
            'valor_pecas' => 300,
            'valor_total' => 800,
            'taxa_saida_abater' => 100,
            'descricao' => 'Troca de componente',
        ], 99);

        $this->assertEqualsWithDelta(500.0, (float)$orcamento['valor_mao_obra'], 0.001);
        $this->assertEqualsWithDelta(300.0, (float)$orcamento['valor_pecas'], 0.001);
        $this->assertEqualsWithDelta(800.0, (float)$orcamento['valor_total'], 0.001);
        $this->assertArrayNotHasKey('comissao_plataforma', $orcamento);
    }

    public function testRejeitaTotalDiferenteDaSoma(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OrcamentoPrevioService::criarOrcamentoProvider([
            'pedido_id' => 10,
            'provider_id' => 20,
            'valor_mao_obra' => 500,
            'valor_pecas' => 300,
            'valor_total' => 900,
        ], 99);
    }
}
