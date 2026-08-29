<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Models/Produto.php';
require_once __DIR__ . '/../../src/Models/ProviderProdutoEstoque.php';
require_once __DIR__ . '/../../src/Services/Estoque/EstoqueService.php';

/**
 * ROADMAP socorro automotivo — Etapa 8 (produtos e estoque).
 * Cobre as garantias do livro-razão de estoque: baixa idempotente por pedido,
 * rejeição de saldo insuficiente e estorno. Usa o SQLite in-memory do bootstrap.
 */
final class EstoqueServiceTest extends TestCase
{
    private int $providerId = 1;
    private int $produtoId;

    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec("DELETE FROM estoque_movimentos");
        $pdo->exec("DELETE FROM provider_produtos_estoque");
        $pdo->exec("DELETE FROM produtos");

        $this->produtoId = Produto::criar([
            'sku' => 'BAT-TEST', 'nome' => 'Bateria Teste', 'categoria' => 'bateria',
            'preco_referencia' => 500.00, 'active' => 1,
        ]);
        // Estoque inicial de 5 unidades.
        ProviderProdutoEstoque::definir($this->providerId, $this->produtoId, 5, 480.00);
    }

    public function testSaldoInicial(): void
    {
        $this->assertSame(5, EstoqueService::disponivel($this->providerId, $this->produtoId));
    }

    public function testEntradaSoma(): void
    {
        $this->assertTrue(EstoqueService::entrada($this->providerId, $this->produtoId, 3));
        $this->assertSame(8, EstoqueService::disponivel($this->providerId, $this->produtoId));
    }

    public function testBaixaDecrementa(): void
    {
        $this->assertTrue(EstoqueService::baixarPorPedido($this->providerId, $this->produtoId, 100, 2));
        $this->assertSame(3, EstoqueService::disponivel($this->providerId, $this->produtoId));
    }

    public function testBaixaEIdempotentePorPedido(): void
    {
        // Mesmo pedido/produto/prestador: a segunda chamada NÃO debita de novo.
        $this->assertTrue(EstoqueService::baixarPorPedido($this->providerId, $this->produtoId, 101, 1));
        $this->assertTrue(EstoqueService::baixarPorPedido($this->providerId, $this->produtoId, 101, 1));
        $this->assertSame(4, EstoqueService::disponivel($this->providerId, $this->produtoId));

        // Só um movimento de SAIDA foi registrado para o pedido 101.
        $stmt = getPDO()->prepare("SELECT COUNT(*) FROM estoque_movimentos WHERE pedido_id = 101 AND tipo = 'SAIDA'");
        $stmt->execute();
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    public function testSaldoInsuficienteRejeita(): void
    {
        // Estoque é 5; pedir 6 deve falhar e não deixar saldo negativo.
        $this->assertFalse(EstoqueService::baixarPorPedido($this->providerId, $this->produtoId, 102, 6));
        $this->assertSame(5, EstoqueService::disponivel($this->providerId, $this->produtoId));
    }

    public function testEstornoDevolveSaldo(): void
    {
        EstoqueService::baixarPorPedido($this->providerId, $this->produtoId, 103, 2);
        $this->assertSame(3, EstoqueService::disponivel($this->providerId, $this->produtoId));

        $this->assertTrue(EstoqueService::estornarPorPedido($this->providerId, $this->produtoId, 103, 2));
        $this->assertSame(5, EstoqueService::disponivel($this->providerId, $this->produtoId));

        // Estorno também é idempotente.
        $this->assertTrue(EstoqueService::estornarPorPedido($this->providerId, $this->produtoId, 103, 2));
        $this->assertSame(5, EstoqueService::disponivel($this->providerId, $this->produtoId));
    }
}
