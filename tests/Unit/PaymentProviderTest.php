<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Payment/PaymentProviderFactory.php';

/**
 * Pacote L1.7 (pendência #33): cobre a extração de
 * PaymentProviderInterface + MercadoPagoProvider + PagSeguroProvider +
 * PaymentProviderFactory. Não repete as asserções de chamada HTTP real
 * (curl/mock) já cobertas em tests/Unit/EstornoServiceTest.php — o foco
 * aqui é a resolução por gateway e o contrato da interface em si.
 */
final class PaymentProviderTest extends TestCase
{
    public function testForGatewayMercadoPagoRetornaImplementacaoCorreta(): void
    {
        $provider = PaymentProviderFactory::forGateway('mercadopago');

        $this->assertInstanceOf(MercadoPagoProvider::class, $provider);
        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertSame('mercadopago', $provider->nome());
    }

    public function testForGatewayPagSeguroRetornaImplementacaoCorreta(): void
    {
        $provider = PaymentProviderFactory::forGateway('pagseguro');

        $this->assertInstanceOf(PagSeguroProvider::class, $provider);
        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertSame('pagseguro', $provider->nome());
    }

    public function testForGatewayDesconhecidoRetornaNull(): void
    {
        $this->assertNull(PaymentProviderFactory::forGateway('bitcoin'));
        $this->assertNull(PaymentProviderFactory::forGateway(''));
    }

    public function testForGatewayEhCaseInsensitiveETrim(): void
    {
        $this->assertInstanceOf(MercadoPagoProvider::class, PaymentProviderFactory::forGateway('  MercadoPago  '));
        $this->assertInstanceOf(PagSeguroProvider::class, PaymentProviderFactory::forGateway('PAGSEGURO'));
    }

    public function testAtivoResolvePeloGatewayConfiguradoNoAmbiente(): void
    {
        // PAYMENT_GATEWAY_ACTIVE é definido em config.php a partir do
        // .env; qualquer que seja o valor configurado neste ambiente de
        // teste, ativo() precisa resolver para o mesmo provider que
        // forGateway() resolveria diretamente com esse valor.
        $gatewayConfigurado = defined('PAYMENT_GATEWAY_ACTIVE') ? (string)PAYMENT_GATEWAY_ACTIVE : 'mercadopago';
        $esperado = PaymentProviderFactory::forGateway($gatewayConfigurado);

        $this->assertEquals($esperado, PaymentProviderFactory::ativo());
    }

    public function testMercadoPagoProviderEstornarDelegaParaEstornoServiceComEstruturaValida(): void
    {
        // Igual ao padrão já usado em EstornoServiceTest: o importante é
        // que a delegação acontece e devolve a estrutura esperada, não o
        // resultado da chamada de rede real (que falha em ambiente de
        // teste sem credenciais/API real).
        $provider = new MercadoPagoProvider();
        $resultado = $provider->estornar('mp_123456');

        $this->assertArrayHasKey('sucesso', $resultado);
        $this->assertArrayHasKey('erro', $resultado);
        $this->assertIsBool($resultado['sucesso']);
    }

    public function testPagSeguroProviderEstornarDelegaParaEstornoServiceComEstruturaValida(): void
    {
        $provider = new PagSeguroProvider();
        $resultado = $provider->estornar('ps_ABCDEF');

        $this->assertArrayHasKey('sucesso', $resultado);
        $this->assertArrayHasKey('erro', $resultado);
        $this->assertIsBool($resultado['sucesso']);
    }
}
