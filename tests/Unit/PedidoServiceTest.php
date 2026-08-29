<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/PedidoService.php';

final class PedidoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec("DELETE FROM configuracoes");
    }

    public function testProducaoComPagamentoObrigatorioExigeCheckout(): void
    {
        getPDO()->exec("INSERT INTO configuracoes (chave, valor) VALUES ('system_mode', 'production')");
        getPDO()->exec("INSERT INTO configuracoes (chave, valor) VALUES ('payment_required', '1')");

        $service = new PedidoService();

        $this->assertFalse($service->podeIniciarAtendimento());
        $this->assertSame('aguardando_pagamento', $service->statusInicialPedido());
    }

    public function testFreeflowLiberaPedidoSemPagamento(): void
    {
        getPDO()->exec("INSERT INTO configuracoes (chave, valor) VALUES ('system_mode', 'freeflow')");
        getPDO()->exec("INSERT INTO configuracoes (chave, valor) VALUES ('payment_required', '1')");

        $service = new PedidoService();

        $this->assertTrue($service->podeIniciarAtendimento());
        $this->assertSame('aguardando_guincho', $service->statusInicialPedido());
    }

    public function testPaymentRequiredDesabilitadoLiberaPedidoMesmoForaDoFreeflow(): void
    {
        getPDO()->exec("INSERT INTO configuracoes (chave, valor) VALUES ('system_mode', 'sandbox')");
        getPDO()->exec("INSERT INTO configuracoes (chave, valor) VALUES ('payment_required', '0')");

        $service = new PedidoService();

        $this->assertTrue($service->podeIniciarAtendimento());
        $this->assertSame('aguardando_guincho', $service->statusInicialPedido());
    }
}
