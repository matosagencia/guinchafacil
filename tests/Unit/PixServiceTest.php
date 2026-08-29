<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Testa a lógica pura de PixService (sem HTTP real):
 *  - Mapeamento de tipo de chave Pix (private via Reflection)
 *  - Guarda de chave vazia (dispara antes de qualquer chamada cURL)
 *  - Reprocessamento quando não há pagamento com status_pix=falha
 */
class PixServiceTest extends TestCase
{
    // ─── Fixture ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // Garante tabelas limpas antes de cada teste
        $pdo = getPDO();
        $pdo->exec("DELETE FROM pagamentos");
        $pdo->exec("DELETE FROM pedidos");
    }

    // ─── mapearTipoChave (private static) ────────────────────────────────────

    private function mapear(string $tipo): string
    {
        $m = new ReflectionMethod(PixService::class, 'mapearTipoChave');
        $m->setAccessible(true);
        return $m->invoke(null, $tipo);
    }

    public function testMapeaCpfParaMaiusculo(): void
    {
        $this->assertSame('CPF', $this->mapear('cpf'));
    }

    public function testMapeaEmailParaMaiusculo(): void
    {
        $this->assertSame('EMAIL', $this->mapear('email'));
    }

    /** telefone → PHONE (padrão MercadoPago) */
    public function testMapeaTelefoneParaPhone(): void
    {
        $this->assertSame('PHONE', $this->mapear('telefone'));
    }

    /** aleatoria → EVP (chave aleatória no padrão Pix) */
    public function testMapeaAleatoriaParaEvp(): void
    {
        $this->assertSame('EVP', $this->mapear('aleatoria'));
    }

    /** Valores desconhecidos fazem passthrough em UPPERCASE */
    public function testMapeaDesconhecidoParaUppercase(): void
    {
        $this->assertSame('CNPJ', $this->mapear('cnpj'));
        $this->assertSame('CUSTOM', $this->mapear('custom'));
    }

    /** Valores já mapeados (EVP, CPF) não devem ser alterados */
    public function testMapeaValorJaMapeadoRetornaMesmo(): void
    {
        $this->assertSame('EVP',   $this->mapear('EVP'));
        $this->assertSame('CPF',   $this->mapear('CPF'));
        $this->assertSame('PHONE', $this->mapear('PHONE'));
    }

    /** Espaços ao redor são removidos antes do mapeamento */
    public function testMapeaIgnoraEspacos(): void
    {
        $this->assertSame('CPF',   $this->mapear(' cpf '));
        $this->assertSame('EVP',   $this->mapear(' aleatoria '));
        $this->assertSame('PHONE', $this->mapear("\ttelefone\t"));
    }

    // ─── transferir() — guarda de chave vazia ────────────────────────────────

    public function testTransferirChaveVaziaRetornaFalso(): void
    {
        $result = PixService::transferir(42, 150.00, '', 'cpf');

        $this->assertFalse($result['sucesso']);
    }

    public function testTransferirChaveVaziaRetornaMensagemComIdPedido(): void
    {
        $result = PixService::transferir(99, 50.00, '', 'email');

        $this->assertStringContainsString('99', $result['erro']);
    }

    public function testTransferirChaveVaziaRetornaMensagemChavePix(): void
    {
        $result = PixService::transferir(1, 10.00, '', 'aleatoria');

        $this->assertStringContainsString('chave Pix', $result['erro']);
    }

    public function testTransferirChaveVaziaRetornaIdTransacaoNulo(): void
    {
        $result = PixService::transferir(1, 10.00, '', 'cpf');

        $this->assertNull($result['id_transacao']);
    }

    public function testTransferirRetornaArrayComTresCampos(): void
    {
        $result = PixService::transferir(1, 50.00, '', 'cpf');

        $this->assertArrayHasKey('sucesso',      $result);
        $this->assertArrayHasKey('id_transacao', $result);
        $this->assertArrayHasKey('erro',         $result);
    }

    // ─── reprocessar() — sem pagamento com falha ─────────────────────────────

    public function testReprocessarSemPagamentoComFalhaRetornaErro(): void
    {
        // Sem nenhum registro de pagamento no DB
        $result = PixService::reprocessar(42);

        $this->assertFalse($result['sucesso']);
    }

    public function testReprocessarSemFalhaRetornaMensagemExplicativa(): void
    {
        $result = PixService::reprocessar(42);

        $this->assertArrayHasKey('erro', $result);
        $this->assertNotEmpty($result['erro']);
        $this->assertStringContainsString('status_pix=falha', $result['erro']);
    }

    public function testReprocessarPagamentoPendenteNaoEProcessado(): void
    {
        // Pagamento existe mas status_pix = 'pendente', não 'falha'
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, status, status_pix, valor_guincho)
            VALUES (10, 'mercadopago', 'aprovado', 'pendente', 80.00)
        ");

        $result = PixService::reprocessar(10);

        // Deve falhar pois não há registro com status_pix='falha'
        $this->assertFalse($result['sucesso']);
    }

    // ─── transferir() com mock HTTP ──────────────────────────────────────────

    /** [TC-PIX-09] Erro de rede (cURL error) → falso com mensagem 'cURL' */
    public function testTransferirCurlErroRetornaFalso(): void
    {
        PixServiceTestable::$mockResponse = ['body' => '', 'code' => 0, 'error' => 'Connection refused'];

        $result = PixServiceTestable::transferir(5, 100.00, 'chave@test.com', 'email');

        $this->assertFalse($result['sucesso']);
        $this->assertStringContainsString('cURL', $result['erro']);
        $this->assertNull($result['id_transacao']);
    }

    /** [TC-PIX-10] API retorna 201 com campo 'id' → sucesso */
    public function testTransferirApi201ComIdRetornaSucesso(): void
    {
        PixServiceTestable::$mockResponse = ['body' => '{"id":987654}', 'code' => 201, 'error' => ''];

        $result = PixServiceTestable::transferir(6, 200.00, 'chave@test.com', 'email');

        $this->assertTrue($result['sucesso']);
        $this->assertSame('987654', $result['id_transacao']);
        $this->assertNull($result['erro']);
    }

    /** [TC-PIX-11] API retorna 201 sem campo 'id' → falso */
    public function testTransferirApi201SemIdRetornaFalso(): void
    {
        PixServiceTestable::$mockResponse = ['body' => '{"status":"created"}', 'code' => 201, 'error' => ''];

        $result = PixServiceTestable::transferir(7, 50.00, 'chave@test.com', 'email');

        $this->assertFalse($result['sucesso']);
    }

    /** [TC-PIX-12] API retorna 400 com 'message' → erro contém mensagem da API */
    public function testTransferirApi400RetornaMensagemDaApi(): void
    {
        PixServiceTestable::$mockResponse = [
            'body'  => '{"message":"chave pix nao encontrada","status":400}',
            'code'  => 400,
            'error' => '',
        ];

        $result = PixServiceTestable::transferir(8, 75.00, 'chave@test.com', 'email');

        $this->assertFalse($result['sucesso']);
        $this->assertStringContainsString('chave pix nao encontrada', $result['erro']);
    }

    /** [TC-PIX-13] API retorna 500 → erro contém 'HTTP 500' */
    public function testTransferirApi500RetornaErroComCodigo(): void
    {
        PixServiceTestable::$mockResponse = ['body' => '', 'code' => 500, 'error' => ''];

        $result = PixServiceTestable::transferir(9, 60.00, 'chave@test.com', 'email');

        $this->assertFalse($result['sucesso']);
        $this->assertStringContainsString('500', $result['erro']);
    }

    /** [TC-PIX-14] X-Idempotency-Key enviado deve conter o pedidoId */
    public function testTransferirIdempotencyKeyContemPedidoId(): void
    {
        PixServiceTestable::$mockResponse  = ['body' => '{"id":1}', 'code' => 201, 'error' => ''];
        PixServiceTestable::$lastHeaders   = [];

        PixServiceTestable::transferir(42, 100.00, 'chave@test.com', 'email');

        $headerStr = implode(' ', PixServiceTestable::$lastHeaders);
        $this->assertStringContainsString('42', $headerStr);
        $this->assertStringContainsString('Idempotency-Key', $headerStr);
    }

    // ─── reprocessar() com mock HTTP ─────────────────────────────────────────

    /** [TC-PIX-17] Retentativa com API 201 → status_pix='concluido', pago_guincho=1 */
    public function testReprocessarComSucessoDaApiAtualizaBanco(): void
    {
        $pdo = getPDO();

        // Setup: usuário, guincho, pedido, pagamento com status_pix='falha'
        $pdo->exec("INSERT INTO usuarios (id, nome, email) VALUES (1, 'Guincheiro', 'g@test.com')");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, chave_pix, chave_pix_tipo) VALUES (1, 1, 'chave@test.com', 'email')");
        $pdo->exec("INSERT INTO pedidos  (id, guincho_id, status) VALUES (20, 1, 'concluido')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, status, status_pix, valor_guincho, pix_tentativas)
                    VALUES (20, 'aprovado', 'falha', 150.00, 1)");

        PixServiceTestable::$mockResponse = ['body' => '{"id":"TX_REPROCESS_OK"}', 'code' => 201, 'error' => ''];

        $result = PixServiceTestable::reprocessar(20);

        $this->assertTrue($result['sucesso']);

        $pag = $pdo->query("SELECT status_pix, pago_guincho, id_transacao_pix FROM pagamentos WHERE pedido_id = 20")->fetch();
        $this->assertSame('concluido', $pag['status_pix']);
        $this->assertSame(1, (int)$pag['pago_guincho']);
        $this->assertSame('TX_REPROCESS_OK', $pag['id_transacao_pix']);
    }

    /** [TC-PIX-18] Retentativa com API 400 → status_pix='falha' restaurado */
    public function testReprocessarComFalhaDaApiMantemStatusFalha(): void
    {
        $pdo = getPDO();

        $pdo->exec("INSERT INTO usuarios (id, nome, email) VALUES (2, 'Guincheiro2', 'g2@test.com')");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, chave_pix, chave_pix_tipo) VALUES (2, 2, 'chave2@test.com', 'email')");
        $pdo->exec("INSERT INTO pedidos  (id, guincho_id, status) VALUES (21, 2, 'concluido')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, status, status_pix, valor_guincho, pix_tentativas)
                    VALUES (21, 'aprovado', 'falha', 80.00, 0)");

        PixServiceTestable::$mockResponse = [
            'body'  => '{"message":"key not found"}',
            'code'  => 400,
            'error' => '',
        ];

        $result = PixServiceTestable::reprocessar(21);

        $this->assertFalse($result['sucesso']);

        $pag = $pdo->query("SELECT status_pix, pago_guincho FROM pagamentos WHERE pedido_id = 21")->fetch();
        $this->assertSame('falha', $pag['status_pix']);
        $this->assertSame(0, (int)$pag['pago_guincho']);
    }

    /** [TC-PIX-19] Race condition: status já 'processando' quando UPDATE é executado */
    public function testReprocessarStatusProcessandoRetornaErro(): void
    {
        $pdo = getPDO();

        // Simula estado intermediário: outro processo já marcou como 'processando'
        $pdo->exec("INSERT INTO pagamentos (pedido_id, status, status_pix, valor_guincho)
                    VALUES (22, 'aprovado', 'processando', 100.00)");

        // SELECT WHERE status_pix='falha' não retorna nada → erro "nenhum pagamento"
        $result = PixService::reprocessar(22);

        $this->assertFalse($result['sucesso']);
        $this->assertStringContainsString('status_pix=falha', $result['erro']);
    }
}

// ─── Subclasse testável: substitui httpPost() por mock ───────────────────────
class PixServiceTestable extends PixService
{
    public static array  $mockResponse = ['body' => '', 'code' => 200, 'error' => ''];
    public static string $lastUrl      = '';
    public static array  $lastHeaders  = [];

    protected static function deveValidarGuardFinanceiro(): bool
    {
        return false;
    }

    protected static function httpPost(string $url, array $headers, string $body): array
    {
        self::$lastUrl     = $url;
        self::$lastHeaders = $headers;
        return self::$mockResponse;
    }
}
