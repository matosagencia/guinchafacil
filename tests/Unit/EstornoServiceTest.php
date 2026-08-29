<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Testa EstornoService com um banco SQLite in-memory:
 *  - Cenário sem pagamento aprovado → retorna sucesso (nada a estornar)
 *  - Gateway desconhecido → retorna erro descritivo sem alterar status
 *  - Prefixo 'mp_' é removido antes de chamar a API do MercadoPago
 *  - Prefixo 'ps_' é removido antes de chamar a API do PagSeguro
 */
class EstornoServiceTest extends TestCase
{
    // ─── Fixture ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        getPDO()->exec("DELETE FROM pagamentos");
    }

    // ─── Sem pagamento aprovado ───────────────────────────────────────────────

    public function testEstornarPedidoSemPagamentoRetornaSucesso(): void
    {
        // Nada no banco → retorna sucesso (cancelamento antes do pagamento é válido)
        $result = EstornoService::estornar(999);

        $this->assertTrue($result['sucesso']);
        $this->assertNull($result['erro']);
    }

    public function testEstornarPedidoComPagamentoPendenteRetornaSucesso(): void
    {
        // Pagamento existe mas status ≠ 'aprovado' → sem estorno necessário
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, id_externo, status)
            VALUES (1, 'mercadopago', 'mp_abc', 'pendente')
        ");

        $result = EstornoService::estornar(1);

        $this->assertTrue($result['sucesso']);
    }

    public function testEstornarNaoAlteraBancoCasoSemPagamento(): void
    {
        $countAntes = (int) getPDO()->query("SELECT COUNT(*) FROM pagamentos")->fetchColumn();

        EstornoService::estornar(9999);

        $countDepois = (int) getPDO()->query("SELECT COUNT(*) FROM pagamentos")->fetchColumn();
        $this->assertSame($countAntes, $countDepois);
    }

    // ─── Gateway desconhecido ─────────────────────────────────────────────────

    public function testGatewayDesconhecidoRetornaFalso(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, id_externo, status)
            VALUES (2, 'bitcoin', 'btc_xyz', 'aprovado')
        ");

        $result = EstornoService::estornar(2);

        $this->assertFalse($result['sucesso']);
    }

    public function testGatewayDesconhecidoMensagemContemNomeGateway(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, id_externo, status)
            VALUES (3, 'paypal', 'pp_123', 'aprovado')
        ");

        $result = EstornoService::estornar(3);

        $this->assertStringContainsString('paypal', $result['erro']);
    }

    public function testGatewayDesconhecidoMensagemContemNaoSuportado(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, id_externo, status)
            VALUES (4, 'stripe', 'str_abc', 'aprovado')
        ");

        $result = EstornoService::estornar(4);

        $this->assertStringContainsString('não suportado', $result['erro']);
    }

    public function testGatewayDesconhecidoNaoAlteraStatusPagamento(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, id_externo, status)
            VALUES (5, 'unknown', 'unk_abc', 'aprovado')
        ");

        EstornoService::estornar(5);

        $pag = getPDO()->query("SELECT status FROM pagamentos WHERE pedido_id = 5")->fetch();
        $this->assertSame('aprovado', $pag['status']);
    }

    // ─── Lógica de prefixo (via Reflection) ──────────────────────────────────

    public function testEstornarMercadoPagoRetornaEstruturaCorreta(): void
    {
        // cURL vai falhar em ambiente de teste — o importante é a estrutura de retorno
        $m = new ReflectionMethod(EstornoService::class, 'estornarMercadoPago');
        $m->setAccessible(true);

        $result = $m->invoke(null, 'mp_123456');

        $this->assertArrayHasKey('sucesso', $result);
        $this->assertArrayHasKey('erro',    $result);
        $this->assertIsBool($result['sucesso']);
    }

    public function testEstornarMercadoPagoPrefixoMpEhRemovidoSemExcecao(): void
    {
        $m = new ReflectionMethod(EstornoService::class, 'estornarMercadoPago');
        $m->setAccessible(true);

        // Não deve lançar exceção — apenas retornar falso com mensagem de erro de cURL/HTTP
        $result = $m->invoke(null, 'mp_XYZ789');

        $this->assertFalse($result['sucesso']);
        $this->assertNotEmpty($result['erro']);
    }

    public function testEstornarMercadoPagoSemPrefixoFuncionaIgual(): void
    {
        $m = new ReflectionMethod(EstornoService::class, 'estornarMercadoPago');
        $m->setAccessible(true);

        // ID sem prefixo 'mp_' também deve ser processado sem exceção
        $result = $m->invoke(null, '123456');

        $this->assertArrayHasKey('sucesso', $result);
    }

    public function testEstornarPagSeguroRetornaEstruturaCorreta(): void
    {
        $m = new ReflectionMethod(EstornoService::class, 'estornarPagSeguro');
        $m->setAccessible(true);

        $result = $m->invoke(null, 'ps_ABCDEF');

        $this->assertArrayHasKey('sucesso', $result);
        $this->assertArrayHasKey('erro',    $result);
        $this->assertIsBool($result['sucesso']);
    }

    // ─── Testes com mock HTTP (EstornoServiceTestable) ────────────────────────

    /** [TC-EST-02] MercadoPago, API 201 → sucesso, status='estornado' no banco */
    public function testEstornarMercadoPagoSucessoAtualizaStatus(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (id, pedido_id, metodo, id_externo, status)
            VALUES (10, 10, 'mercadopago', 'mp_67890', 'aprovado')
        ");

        EstornoServiceTestable::$mockResponse = ['body' => '{}', 'code' => 201, 'error' => ''];

        $result = EstornoServiceTestable::estornar(10);

        $this->assertTrue($result['sucesso']);
        $pag = getPDO()->query("SELECT status FROM pagamentos WHERE id = 10")->fetch();
        $this->assertSame('estornado', $pag['status']);
    }

    /** [TC-EST-03] MercadoPago, API 400 → falso, status NÃO alterado */
    public function testEstornarMercadoPagoFalhaMantemStatus(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (id, pedido_id, metodo, id_externo, status)
            VALUES (11, 11, 'mercadopago', 'mp_FAIL', 'aprovado')
        ");

        EstornoServiceTestable::$mockResponse = [
            'body'  => '{"message":"payment not found"}',
            'code'  => 400,
            'error' => '',
        ];

        $result = EstornoServiceTestable::estornar(11);

        $this->assertFalse($result['sucesso']);
        $pag = getPDO()->query("SELECT status FROM pagamentos WHERE id = 11")->fetch();
        $this->assertSame('aprovado', $pag['status']);
    }

    /** [TC-EST-04] PagSeguro, API 200 → sucesso, status='estornado' */
    public function testEstornarPagSeguroSucessoAtualizaStatus(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (id, pedido_id, metodo, id_externo, status)
            VALUES (12, 12, 'pagseguro', 'ps_TRX001', 'aprovado')
        ");

        EstornoServiceTestable::$mockResponse = ['body' => '', 'code' => 200, 'error' => ''];

        $result = EstornoServiceTestable::estornar(12);

        $this->assertTrue($result['sucesso']);
        $pag = getPDO()->query("SELECT status FROM pagamentos WHERE id = 12")->fetch();
        $this->assertSame('estornado', $pag['status']);
    }

    /** [TC-EST-06] Erro cURL → falso, erro contém 'cURL' */
    public function testEstornarCurlErroRetornaFalso(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (id, pedido_id, metodo, id_externo, status)
            VALUES (13, 13, 'mercadopago', 'mp_CURL', 'aprovado')
        ");

        EstornoServiceTestable::$mockResponse = ['body' => '', 'code' => 0, 'error' => 'Connection refused'];

        $result = EstornoServiceTestable::estornar(13);

        $this->assertFalse($result['sucesso']);
        $this->assertStringContainsString('cURL', $result['erro']);
    }

    /** [TC-EST-07] Prefixo 'mp_' removido: URL deve conter '/payments/67890/refunds' */
    public function testEstornarMercadoPagoRemovePrefixoMpDaUrl(): void
    {
        EstornoServiceTestable::$mockResponse = ['body' => '{}', 'code' => 201, 'error' => ''];
        EstornoServiceTestable::$lastUrl      = '';

        EstornoServiceTestable::publicEstornarMercadoPago('mp_67890');

        $this->assertStringContainsString('/payments/67890/refunds', EstornoServiceTestable::$lastUrl);
        $this->assertStringNotContainsString('mp_67890', EstornoServiceTestable::$lastUrl);
    }

    /** [TC-EST-08] ID sem prefixo: URL também correta; HTTP 200 aceito como sucesso */
    public function testEstornarMercadoPagoSemPrefixoAceita200(): void
    {
        EstornoServiceTestable::$mockResponse = ['body' => '{}', 'code' => 200, 'error' => ''];

        $result = EstornoServiceTestable::publicEstornarMercadoPago('67890');

        $this->assertTrue($result['sucesso']);
        $this->assertStringContainsString('/payments/67890/refunds', EstornoServiceTestable::$lastUrl);
    }

    /** [TC-EST-09] API 422 com message → erro contém mensagem da API */
    public function testEstornarMercadoPago422RetornaMensagem(): void
    {
        EstornoServiceTestable::$mockResponse = [
            'body'  => '{"message":"payment not found"}',
            'code'  => 422,
            'error' => '',
        ];

        $result = EstornoServiceTestable::publicEstornarMercadoPago('mp_99');

        $this->assertFalse($result['sucesso']);
        $this->assertStringContainsString('payment not found', $result['erro']);
    }

    /** [TC-EST-10] Prefixo 'ps_' removido: URL deve conter 'transactionCode=TRX123' */
    public function testEstornarPagSeguroRemovePrefixoPs(): void
    {
        EstornoServiceTestable::$mockResponse = ['body' => '', 'code' => 200, 'error' => ''];
        EstornoServiceTestable::$lastUrl      = '';

        EstornoServiceTestable::publicEstornarPagSeguro('ps_TRX123');

        $this->assertStringContainsString('TRX123', EstornoServiceTestable::$lastUrl);
        $this->assertStringNotContainsString('ps_TRX123', EstornoServiceTestable::$lastUrl);
    }

    /** [TC-EST-11] PagSeguro API 400 → erro contém 'PagSeguro HTTP 400' */
    public function testEstornarPagSeguro400RetornaErro(): void
    {
        EstornoServiceTestable::$mockResponse = ['body' => 'Bad Request', 'code' => 400, 'error' => ''];

        $result = EstornoServiceTestable::publicEstornarPagSeguro('ps_TRX999');

        $this->assertFalse($result['sucesso']);
        $this->assertStringContainsString('PagSeguro HTTP 400', $result['erro']);
    }
}

// ─── Subclasse testável: substitui httpPostRaw() por mock ────────────────────
class EstornoServiceTestable extends EstornoService
{
    public static array  $mockResponse = ['body' => '', 'code' => 200, 'error' => ''];
    public static string $lastUrl      = '';

    protected static function httpPostRaw(string $url, array $headers, string $body): array
    {
        self::$lastUrl = $url;
        return self::$mockResponse;
    }

    /** Expõe estornarMercadoPago() (protected) para testes diretos. */
    public static function publicEstornarMercadoPago(string $id): array
    {
        return static::estornarMercadoPago($id);
    }

    /** Expõe estornarPagSeguro() (protected) para testes diretos. */
    public static function publicEstornarPagSeguro(string $id): array
    {
        return static::estornarPagSeguro($id);
    }
}
