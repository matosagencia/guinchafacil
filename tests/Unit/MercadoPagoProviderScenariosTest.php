<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Payment/MercadoPagoProvider.php';

/**
 * Cobertura exaustiva dos cenarios oficiais do Mercado Pago usados pelo
 * fixture local. O teste nao tenta simular a API real; ele so valida o
 * mapeamento do provider para os buckets internos do sistema.
 */
final class MercadoPagoProviderScenariosTest extends TestCase
{
    protected function setUp(): void
    {
        MercadoPagoProviderScenariosTestable::$mockResponse = ['body' => '', 'code' => 200, 'error' => ''];
    }

    /**
     * @return array<string, array{0:string,1:array,2:string,3:bool}>
     */
    public static function mpScenarioProvider(): array
    {
        return [
            'aprovado' => [
                'APRO',
                self::buildPaymentResponse(101, 'approved', 'accredited', 'mastercard'),
                'aprovado',
                true,
            ],
            'recusado_erro_geral' => [
                'OTHE',
                self::buildPaymentResponse(102, 'rejected', 'cc_rejected_other_reason', 'mastercard'),
                'recusado',
                true,
            ],
            'pendente' => [
                'CONT',
                self::buildPaymentResponse(103, 'pending', 'pending_waiting_payment', 'mastercard'),
                'pendente',
                true,
            ],
            'recusado_validacao' => [
                'CALL',
                self::buildPaymentResponse(104, 'rejected', 'cc_rejected_call_for_authorize', 'mastercard'),
                'recusado',
                true,
            ],
            'recusado_fundos' => [
                'FUND',
                self::buildPaymentResponse(105, 'rejected', 'cc_rejected_insufficient_amount', 'mastercard'),
                'recusado',
                true,
            ],
            'recusado_cvv' => [
                'SECU',
                self::buildPaymentResponse(106, 'rejected', 'cc_rejected_bad_filled_security_code', 'mastercard'),
                'recusado',
                true,
            ],
            'recusado_vencimento' => [
                'EXPI',
                self::buildPaymentResponse(107, 'rejected', 'cc_rejected_bad_filled_date', 'mastercard'),
                'recusado',
                true,
            ],
            'recusado_formulario' => [
                'FORM',
                self::buildPaymentResponse(108, 'rejected', 'cc_rejected_form_error', 'mastercard'),
                'recusado',
                true,
            ],
            'rejeitado_sem_numero' => [
                'CARD',
                self::buildPaymentResponse(109, 'rejected', 'cc_rejected_card_number', 'mastercard'),
                'recusado',
                true,
            ],
            'rejeitado_parcelas' => [
                'INST',
                self::buildPaymentResponse(110, 'rejected', 'cc_rejected_bad_filled_installments', 'mastercard'),
                'recusado',
                true,
            ],
            'rejeitado_duplicado' => [
                'DUPL',
                self::buildPaymentResponse(111, 'rejected', 'cc_rejected_duplicate_payment', 'mastercard'),
                'recusado',
                true,
            ],
            'rejeitado_cartao_bloqueado' => [
                'LOCK',
                self::buildPaymentResponse(112, 'rejected', 'cc_rejected_card_disabled', 'mastercard'),
                'recusado',
                true,
            ],
            'rejeitado_tipo_nao_permitido' => [
                'CTNA',
                self::buildPaymentResponse(113, 'rejected', 'cc_rejected_card_type_not_allowed', 'mastercard'),
                'recusado',
                true,
            ],
            'rejeitado_tentativas_pin' => [
                'ATTE',
                self::buildPaymentResponse(114, 'rejected', 'cc_rejected_exceeded_pin_attempts', 'mastercard'),
                'recusado',
                true,
            ],
            'rejeitado_lista_negra' => [
                'BLAC',
                self::buildPaymentResponse(115, 'rejected', 'cc_rejected_blacklist', 'mastercard'),
                'recusado',
                true,
            ],
            'nao_suportado' => [
                'UNSU',
                self::buildPaymentResponse(116, 'rejected', 'cc_rejected_not_supported', 'mastercard'),
                'recusado',
                true,
            ],
            'regra_de_valores' => [
                'TEST',
                self::buildPaymentResponse(117, 'rejected', 'cc_amount_rule_rejected', 'mastercard'),
                'recusado',
                true,
            ],
        ];
    }

    /**
     * @dataProvider mpScenarioProvider
     */
    public function testCenariosOficiaisDoMercadoPagoFicamNosBucketsEsperados(
        string $scenarioCode,
        array $responseBody,
        string $expectedStatus,
        bool $expectedSuccess
    ): void {
        MercadoPagoProviderScenariosTestable::$mockResponse = [
            'body' => json_encode($responseBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'code' => 201,
            'error' => '',
        ];

        $provider = new MercadoPagoProviderScenariosTestable();
        $resultado = $provider->criarPagamento($this->baseDadosCartao($scenarioCode));

        $this->assertSame($expectedStatus, $resultado['status'], $scenarioCode);
        $this->assertSame($expectedSuccess, $resultado['sucesso'], $scenarioCode);
        $this->assertSame('mp_' . $responseBody['id'], $resultado['idExterno']);
        $this->assertSame($responseBody['status_detail'], $resultado['detalhe']['status_detail']);
        $this->assertSame($responseBody['payment_method_id'], $resultado['detalhe']['payment_method_id']);

        if ($expectedStatus === 'erro') {
            $this->assertNotNull($resultado['erro']);
        } elseif ($expectedStatus === 'recusado') {
            $this->assertSame($responseBody['status_detail'], $resultado['erro']);
        } else {
            $this->assertNull($resultado['erro']);
        }
    }

    public function testErroDeComunicacaoRetornaErro(): void
    {
        MercadoPagoProviderScenariosTestable::$mockResponse = [
            'body' => '',
            'code' => 0,
            'error' => 'Connection timed out after 30000 milliseconds',
        ];

        $provider = new MercadoPagoProviderScenariosTestable();
        $resultado = $provider->criarPagamento($this->baseDadosPix());

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('erro', $resultado['status']);
        $this->assertSame('Erro de comunicação com o MercadoPago.', $resultado['erro']);
    }

    public function testRespostaNaoJsonRetornaErro(): void
    {
        MercadoPagoProviderScenariosTestable::$mockResponse = [
            'body' => '<html>502 Bad Gateway</html>',
            'code' => 502,
            'error' => '',
        ];

        $provider = new MercadoPagoProviderScenariosTestable();
        $resultado = $provider->criarPagamento($this->baseDadosPix());

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('erro', $resultado['status']);
        $this->assertSame('Resposta inválida do MercadoPago.', $resultado['erro']);
    }

    public function testStatusDesconhecidoRetornaErro(): void
    {
        MercadoPagoProviderScenariosTestable::$mockResponse = [
            'body' => json_encode([
                'id' => 99887766,
                'status' => 'refunded',
                'status_detail' => 'payment_refunded',
                'payment_method_id' => 'mastercard',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'code' => 201,
            'error' => '',
        ];

        $provider = new MercadoPagoProviderScenariosTestable();
        $resultado = $provider->criarPagamento($this->baseDadosCartao('UNKNOWN'));

        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('erro', $resultado['status']);
        $this->assertSame('mp_99887766', $resultado['idExterno']);
        $this->assertNull($resultado['erro']);
    }

    public function testPixSemTokenExtraiQrCodeEPermanecePendente(): void
    {
        MercadoPagoProviderScenariosTestable::$mockResponse = [
            'body' => json_encode([
                'id' => 200200200,
                'status' => 'pending',
                'status_detail' => 'pending_waiting_transfer',
                'payment_method_id' => 'pix',
                'point_of_interaction' => [
                    'transaction_data' => [
                        'qr_code' => '00020126...copia-e-cola...6304ABCD',
                        'qr_code_base64' => 'iVBORw0KGgoAAAANS...',
                        'ticket_url' => 'https://www.mercadopago.com.br/payments/200200200/ticket',
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'code' => 201,
            'error' => '',
        ];

        $provider = new MercadoPagoProviderScenariosTestable();
        $resultado = $provider->criarPagamento($this->baseDadosPix());

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('pendente', $resultado['status']);
        $this->assertSame('mp_200200200', $resultado['idExterno']);
        $this->assertSame('00020126...copia-e-cola...6304ABCD', $resultado['detalhe']['qr_code']);
        $this->assertSame('iVBORw0KGgoAAAANS...', $resultado['detalhe']['qr_code_base64']);
        $this->assertSame('https://www.mercadopago.com.br/payments/200200200/ticket', $resultado['detalhe']['ticket_url']);
    }

    public function testBoletoExtraiUrlDePagamentoEPermanecePendente(): void
    {
        MercadoPagoProviderScenariosTestable::$mockResponse = [
            'body' => json_encode([
                'id' => 300300300,
                'status' => 'pending',
                'status_detail' => 'pending_waiting_payment',
                'payment_method_id' => 'bolbradesco',
                'transaction_details' => [
                    'external_resource_url' => 'https://www.mercadopago.com.br/payments/300300300/boleto',
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'code' => 201,
            'error' => '',
        ];

        $dados = $this->baseDadosBoleto();
        $provider = new MercadoPagoProviderScenariosTestable();
        $resultado = $provider->criarPagamento($dados);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('pendente', $resultado['status']);
        $this->assertSame('mp_300300300', $resultado['idExterno']);
        $this->assertSame('https://www.mercadopago.com.br/payments/300300300/boleto', $resultado['detalhe']['boleto_url']);
        $this->assertSame('bolbradesco', $resultado['detalhe']['payment_method_id']);
    }

    private function baseDadosCartao(string $scenarioCode): array
    {
        return [
            'pedidoId' => 123,
            'valor' => 150.0,
            'descricao' => 'Guincho pedido #' . $scenarioCode,
            'payerEmail' => 'cliente@test.com',
            'paymentMethodId' => 'mastercard',
            'docTipo' => 'CPF',
            'docNumero' => '12345678909',
            'idempotencyKey' => 'idem-' . strtolower($scenarioCode),
            'token' => 'card-token-' . strtolower($scenarioCode),
        ];
    }

    private function baseDadosPix(): array
    {
        return [
            'pedidoId' => 456,
            'valor' => 150.0,
            'descricao' => 'Guincho pedido pix',
            'payerEmail' => 'cliente@test.com',
            'paymentMethodId' => 'pix',
            'docTipo' => 'CPF',
            'docNumero' => '12345678909',
            'idempotencyKey' => 'idem-pix',
        ];
    }

    private function baseDadosBoleto(): array
    {
        return [
            'pedidoId' => 789,
            'valor' => 150.0,
            'descricao' => 'Guincho pedido boleto',
            'payerEmail' => 'cliente@test.com',
            'paymentMethodId' => 'bolbradesco',
            'docTipo' => 'CPF',
            'docNumero' => '12345678909',
            'idempotencyKey' => 'idem-boleto',
        ];
    }

    private static function buildPaymentResponse(int $id, string $status, string $statusDetail, string $paymentMethodId): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'status_detail' => $statusDetail,
            'payment_method_id' => $paymentMethodId,
        ];
    }
}

final class MercadoPagoProviderScenariosTestable extends MercadoPagoProvider
{
    public static array $mockResponse = ['body' => '', 'code' => 200, 'error' => ''];

    protected static function httpPost(string $url, array $headers, string $body): array
    {
        return self::$mockResponse;
    }
}
