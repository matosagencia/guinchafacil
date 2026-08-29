<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Payment/MercadoPagoProvider.php';

/**
 * §A5 (auditoria 21/07): MercadoPagoProvider::criarPagamento() — que gera o
 * QR code do checkout Pix transparente — tinha zero cobertura de teste
 * porque fazia curl_init() inline, sem nenhum ponto de injeção mockável
 * (diferente de PixService::httpPost(), já testável). Havia cobertura
 * sólida pro repasse Pix (payout) e pro webhook/estorno, mas nunca pra
 * criação da cobrança em si. Este teste cobre exatamente esse buraco,
 * usando o mesmo padrão de subclasse testável já usado em PixServiceTest.
 */
final class MercadoPagoProviderPixTest extends TestCase
{
    protected function setUp(): void
    {
        MercadoPagoProviderTestable::$mockResponse = ['body' => '', 'code' => 200, 'error' => ''];
    }

    private function dadosPixBase(): array
    {
        return [
            'pedidoId' => 123,
            'valor' => 150.0,
            'descricao' => 'Guincho pedido #123',
            'payerEmail' => 'cliente@test.com',
            'paymentMethodId' => 'pix',
            'docTipo' => 'CPF',
            'docNumero' => '12345678900',
            'idempotencyKey' => 'idem-123',
        ];
    }

    public function testPixAprovadoExtraiQrCodeDaResposta(): void
    {
        MercadoPagoProviderTestable::$mockResponse = [
            'body' => json_encode([
                'id' => 999888777,
                'status' => 'pending', // Pix fica "pending" até o cliente pagar o QR
                'status_detail' => 'pending_waiting_transfer',
                'payment_method_id' => 'pix',
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '00020126...copia-e-cola...6304ABCD',
                        'qr_code_base64' => 'iVBORw0KGgoAAAANS...',
                        'ticket_url' => 'https://www.mercadopago.com.br/payments/999888777/ticket',
                    ],
                ],
            ]),
            'code' => 201,
            'error' => '',
        ];

        $provider = new MercadoPagoProviderTestable();
        $resultado = $provider->criarPagamento($this->dadosPixBase());

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('pendente', $resultado['status']);
        $this->assertSame('mp_999888777', $resultado['idExterno']);
        $this->assertSame('00020126...copia-e-cola...6304ABCD', $resultado['detalhe']['qr_code']);
        $this->assertSame('iVBORw0KGgoAAAANS...', $resultado['detalhe']['qr_code_base64']);
        $this->assertSame('https://www.mercadopago.com.br/payments/999888777/ticket', $resultado['detalhe']['ticket_url']);
        $this->assertNull($resultado['erro']);
    }

    public function testPagamentoComCartaoNaoTentaExtrairQrCode(): void
    {
        MercadoPagoProviderTestable::$mockResponse = [
            'body' => json_encode([
                'id' => 111222333,
                'status' => 'approved',
                'status_detail' => 'accredited',
                'payment_method_id' => 'master',
            ]),
            'code' => 201,
            'error' => '',
        ];

        $dados = $this->dadosPixBase();
        $dados['paymentMethodId'] = 'master';
        $dados['token'] = 'card-token-abc';

        $provider = new MercadoPagoProviderTestable();
        $resultado = $provider->criarPagamento($dados);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('aprovado', $resultado['status']);
        $this->assertArrayNotHasKey('qr_code', $resultado['detalhe']);
        $this->assertArrayNotHasKey('qr_code_base64', $resultado['detalhe']);
    }

    public function testHttpErroRetornaRecusadoComCausa(): void
    {
        MercadoPagoProviderTestable::$mockResponse = [
            'body' => json_encode([
                'message' => 'invalid payment_method_id',
                'status_detail' => 'cc_rejected_bad_filled_card_number',
                'cause' => [['code' => 'E301', 'description' => 'invalid parameter']],
            ]),
            'code' => 400,
            'error' => '',
        ];

        $provider = new MercadoPagoProviderTestable();
        $resultado = $provider->criarPagamento($this->dadosPixBase());

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('recusado', $resultado['status']);
        $this->assertNull($resultado['idExterno']);
        $this->assertSame('cc_rejected_bad_filled_card_number', $resultado['detalhe']['status_detail']);
        $this->assertSame('invalid payment_method_id', $resultado['erro']);
    }

    public function testErroDeRedeRetornaErroDeComunicacao(): void
    {
        MercadoPagoProviderTestable::$mockResponse = [
            'body' => '',
            'code' => 0,
            'error' => 'Connection timed out after 30000 milliseconds',
        ];

        $provider = new MercadoPagoProviderTestable();
        $resultado = $provider->criarPagamento($this->dadosPixBase());

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('erro', $resultado['status']);
        $this->assertSame('Erro de comunicação com o MercadoPago.', $resultado['erro']);
    }

    public function testRespostaNaoJsonRetornaErro(): void
    {
        MercadoPagoProviderTestable::$mockResponse = [
            'body' => '<html>502 Bad Gateway</html>',
            'code' => 502,
            'error' => '',
        ];

        $provider = new MercadoPagoProviderTestable();
        $resultado = $provider->criarPagamento($this->dadosPixBase());

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('erro', $resultado['status']);
        $this->assertSame('Resposta inválida do MercadoPago.', $resultado['erro']);
    }
}

class MercadoPagoProviderTestable extends MercadoPagoProvider
{
    public static array $mockResponse = ['body' => '', 'code' => 200, 'error' => ''];

    protected static function httpPost(string $url, array $headers, string $body): array
    {
        return self::$mockResponse;
    }
}
