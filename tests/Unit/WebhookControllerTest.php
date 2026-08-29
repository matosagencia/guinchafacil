<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Testa WebhookController: fluxo MP, fluxo PagSeguro, idempotência, split financeiro.
 * Usa WebhookControllerTestable para interceptar lerInput() e httpGet().
 *
 * Cobre TC-WH-07 a TC-WH-20 (TC-WH-01 a TC-WH-06 já cobertos em WebhookSignatureTest).
 */
class WebhookControllerTest extends TestCase
{
    private WebhookControllerTestable $ctrl;

    // ─── Fixture ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec("DELETE FROM pagamentos");
        $pdo->exec("DELETE FROM pedidos");
        $pdo->exec("DELETE FROM logs_webhook");
        $pdo->exec("DELETE FROM configuracoes");
        // Cliente usado por todos os pedidos de teste — PagamentoAprovacaoService
        // faz JOIN usuarios ON u.id = p.cliente_id, então precisa existir.
        $pdo->exec("INSERT OR IGNORE INTO usuarios (id, nome, email, tipo, ativo)
                    VALUES (901, 'Cliente Teste', 'c@test.com', 'cliente', 1)");
        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");
        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");

        $this->ctrl = new WebhookControllerTestable();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Gera X-Signature válido no formato MercadoPago. */
    private function assinar(string $dataId, string $requestId, string $ts): string
    {
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);
    }

    private function setPedidoEPagamento(int $pedidoId, float $custo = 100.00): void
    {
        $pdo = getPDO();
        // PagamentoAprovacaoService::aprovar() faz JOIN usuarios ON u.id = p.cliente_id;
        // sem uma linha de cliente em usuarios o JOIN não casa e a aprovação
        // sai por engano como "pedido não está aguardando pagamento".
        $pdo->exec("INSERT OR IGNORE INTO usuarios (id, nome, email, tipo, ativo)
                    VALUES (901, 'Cliente Teste', 'c@test.com', 'cliente', 1)");
        $pdo->exec("INSERT INTO pedidos (id, cliente_id, status, custo_estimado, cliente_nome, cliente_email)
                    VALUES ({$pedidoId}, 901, 'aguardando_pagamento', {$custo}, 'Cliente Teste', 'c@test.com')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total)
                    VALUES ({$pedidoId}, 'mercadopago', 'pendente', {$custo})");
    }

    private function buildMpInput(string $dataId, string $action = 'payment.updated'): string
    {
        return json_encode(['action' => $action, 'data' => ['id' => $dataId]]);
    }

    private function setMpSignatureHeaders(string $dataId, string $requestId = 'req-test', string $ts = '1744000000'): void
    {
        $_SERVER['HTTP_X_SIGNATURE']  = $this->assinar($dataId, $requestId, $ts);
        $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;
        $_GET['data.id']              = $dataId;
    }

    // ─── TC-WH-07: Assinatura inválida → 401, nada no banco ──────────────────

    public function testAssinaturaInvalidaRetorna401(): void
    {
        $_SERVER['HTTP_X_SIGNATURE']  = 'ts=123,v1=invalido_hash_errado_aqui_000000000000000000000000000000';
        $_SERVER['HTTP_X_REQUEST_ID'] = 'req-test';
        $_GET['data.id']              = '111';

        $this->ctrl->fakeInput = $this->buildMpInput('111');

        $ex = null;
        try {
            $this->ctrl->mercadoPago();
        } catch (HttpResponseException $e) {
            $ex = $e;
        }

        $this->assertNotNull($ex);
        $this->assertSame(401, $ex->code);

        $count = (int) getPDO()->query("SELECT COUNT(*) FROM pagamentos")->fetchColumn();
        $this->assertSame(0, $count);
    }

    // ─── TC-WH-08: Assinatura válida, action != 'payment.updated' → 200, sem processar ─

    public function testActionNaoPaymentUpdatedRetorna200SemProcessar(): void
    {
        $this->setPedidoEPagamento(1);
        $this->setMpSignatureHeaders('MP-ID-08');

        $this->ctrl->fakeInput = $this->buildMpInput('MP-ID-08', 'payment.created');

        $ex = null;
        try {
            $this->ctrl->mercadoPago();
        } catch (HttpResponseException $e) {
            $ex = $e;
        }

        $this->assertNotNull($ex);
        $this->assertSame(200, $ex->code);
        $this->assertSame('OK', $ex->body);

        // Pedido não deve ter mudado de status
        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = 1")->fetch();
        $this->assertSame('aguardando_pagamento', $pedido['status']);
    }

    // ─── TC-WH-09: Fluxo completo aprovado ───────────────────────────────────

    public function testFluxoCompletoAprovaMp(): void
    {
        $this->setPedidoEPagamento(2, 100.00);
        $this->setMpSignatureHeaders('MP-PAY-09');

        $this->ctrl->fakeInput  = $this->buildMpInput('MP-PAY-09');
        $this->ctrl->mockHttpGet = [
            'body'  => json_encode(['status' => 'approved', 'external_reference' => '2', 'id' => 'MP-PAY-09']),
            'code'  => 200,
            'error' => '',
        ];

        $ex = null;
        try {
            $this->ctrl->mercadoPago();
        } catch (HttpResponseException $e) {
            $ex = $e;
        }

        $this->assertNotNull($ex);
        $this->assertSame(200, $ex->code);

        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = 2")->fetch();
        $this->assertSame('aguardando_guincho', $pedido['status']);

        $pag = getPDO()->query("SELECT status FROM pagamentos WHERE pedido_id = 2")->fetch();
        $this->assertSame('aprovado', $pag['status']);

        $log = (int) getPDO()->query("SELECT COUNT(*) FROM logs_webhook WHERE fonte='mercadopago'")->fetchColumn();
        $this->assertGreaterThanOrEqual(1, $log);
    }

    // ─── TC-WH-10: Idempotência por id_externo ───────────────────────────────

    public function testIdempotenciaPorIdExternoIgnoraDuplicata(): void
    {
        $this->setPedidoEPagamento(3);
        // Pagamento já aprovado com este id_externo
        getPDO()->exec("UPDATE pagamentos SET status = 'aprovado', id_externo = 'mp_DUP-10' WHERE pedido_id = 3");

        $this->setMpSignatureHeaders('DUP-10');
        $this->ctrl->fakeInput  = $this->buildMpInput('DUP-10');
        $this->ctrl->mockHttpGet = [
            'body'  => json_encode(['status' => 'approved', 'external_reference' => '3', 'id' => 'DUP-10']),
            'code'  => 200,
            'error' => '',
        ];

        try {
            $this->ctrl->mercadoPago();
        } catch (HttpResponseException) { /* esperado */ }

        // Pedido permanece em 'aguardando_pagamento' — já estava aprovado pelo id_externo
        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = 3")->fetch();
        $this->assertSame('aguardando_pagamento', $pedido['status']);

        $log = getPDO()->query("SELECT evento FROM logs_webhook WHERE evento = 'duplicate_ignored'")->fetch();
        $this->assertNotFalse($log);
    }

    // ─── TC-WH-11: Idempotência por status do pagamento ──────────────────────

    public function testIdempotenciaPorStatusPagamentoJaAprovado(): void
    {
        $this->setPedidoEPagamento(4);
        // Pagamento já aprovado (id_externo diferente do que chega)
        getPDO()->exec("UPDATE pagamentos SET status = 'aprovado', id_externo = 'mp_OUTRO' WHERE pedido_id = 4");

        $this->setMpSignatureHeaders('NEW-ID-11');
        $this->ctrl->fakeInput  = $this->buildMpInput('NEW-ID-11');
        $this->ctrl->mockHttpGet = [
            'body'  => json_encode(['status' => 'approved', 'external_reference' => '4', 'id' => 'NEW-ID-11']),
            'code'  => 200,
            'error' => '',
        ];

        try {
            $this->ctrl->mercadoPago();
        } catch (HttpResponseException) { /* esperado */ }

        // Pedido deve permanecer 'aguardando_pagamento' — segundo check de idempotência
        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = 4")->fetch();
        $this->assertSame('aguardando_pagamento', $pedido['status']);
    }

    // ─── TC-WH-12: Pedido não está em 'aguardando_pagamento' ─────────────────

    public function testPedidoNaoAguardandoPagamentoNaoEProcessado(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado) VALUES (5, 'aguardando_guincho', 100)");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status) VALUES (5, 'mercadopago', 'pendente')");

        $this->setMpSignatureHeaders('ID-12');
        $this->ctrl->fakeInput  = $this->buildMpInput('ID-12');
        $this->ctrl->mockHttpGet = [
            'body'  => json_encode(['status' => 'approved', 'external_reference' => '5', 'id' => 'ID-12']),
            'code'  => 200,
            'error' => '',
        ];

        try {
            $this->ctrl->mercadoPago();
        } catch (HttpResponseException) { /* esperado */ }

        // Status não muda (já estava em aguardando_guincho)
        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = 5")->fetch();
        $this->assertSame('aguardando_guincho', $pedido['status']);
    }

    // ─── TC-WH-14: PagSeguro notificationType != 'transaction' → 200 ─────────

    public function testPagSeguroNotificacaoNaoTransactionRetorna200(): void
    {
        $this->ctrl->fakeInput = 'notificationType=application&notificationCode=ABC';

        $ex = null;
        try {
            $this->ctrl->pagSeguro();
        } catch (HttpResponseException $e) {
            $ex = $e;
        }

        $this->assertNotNull($ex);
        $this->assertSame(200, $ex->code);
        $this->assertSame('OK', $ex->body);
    }

    // ─── TC-WH-15: PagSeguro transaction status=3 → aprovado ─────────────────

    public function testPagSeguroStatus3AprovaPedido(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, cliente_id, status, custo_estimado, cliente_nome, cliente_email)
                    VALUES (6, 901, 'aguardando_pagamento', 100.00, 'C', 'c@c.com')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total)
                    VALUES (6, 'pagseguro', 'pendente', 100.00)");

        $xml = '<?xml version="1.0" encoding="UTF-8"?><transaction><status>3</status><reference>6</reference><code>PSCODE15</code></transaction>';

        $this->ctrl->fakeInput  = 'notificationType=transaction&notificationCode=NOTIF15';
        $this->ctrl->mockHttpGet = ['body' => $xml, 'code' => 200, 'error' => ''];

        try {
            $this->ctrl->pagSeguro();
        } catch (HttpResponseException) { /* esperado */ }

        $pedido = $pdo->query("SELECT status FROM pedidos WHERE id = 6")->fetch();
        $this->assertSame('aguardando_guincho', $pedido['status']);

        $pag = $pdo->query("SELECT status FROM pagamentos WHERE pedido_id = 6")->fetch();
        $this->assertSame('aprovado', $pag['status']);
    }

    // ─── TC-WH-16: PagSeguro status=2 (pendente) → não aprova ───────────────

    public function testPagSeguroStatus2NaoAprovaPedido(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado) VALUES (7, 'aguardando_pagamento', 100.00)");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status) VALUES (7, 'pagseguro', 'pendente')");

        $xml = '<?xml version="1.0" encoding="UTF-8"?><transaction><status>2</status><reference>7</reference><code>PSCODE16</code></transaction>';

        $this->ctrl->fakeInput  = 'notificationType=transaction&notificationCode=NOTIF16';
        $this->ctrl->mockHttpGet = ['body' => $xml, 'code' => 200, 'error' => ''];

        try {
            $this->ctrl->pagSeguro();
        } catch (HttpResponseException) { /* esperado */ }

        $pedido = $pdo->query("SELECT status FROM pedidos WHERE id = 7")->fetch();
        $this->assertSame('aguardando_pagamento', $pedido['status']);
    }

    // ─── TC-WH-17: PagSeguro → id_externo salvo com prefixo 'ps_' ───────────

    public function testPagSeguroIdExternoTemPrefixoPs(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, cliente_id, status, custo_estimado, cliente_nome, cliente_email)
                    VALUES (8, 901, 'aguardando_pagamento', 100.00, 'C', 'c@c.com')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total)
                    VALUES (8, 'pagseguro', 'pendente', 100.00)");

        $xml = '<?xml version="1.0" encoding="UTF-8"?><transaction><status>3</status><reference>8</reference><code>CODEPS17</code></transaction>';

        $this->ctrl->fakeInput  = 'notificationType=transaction&notificationCode=NOTIF17';
        $this->ctrl->mockHttpGet = ['body' => $xml, 'code' => 200, 'error' => ''];

        try {
            $this->ctrl->pagSeguro();
        } catch (HttpResponseException) { /* esperado */ }

        $pag = $pdo->query("SELECT id_externo FROM pagamentos WHERE pedido_id = 8")->fetch();
        $this->assertNotNull($pag['id_externo']);
        $this->assertStringStartsWith('ps_', $pag['id_externo']);
        $this->assertStringContainsString('CODEPS17', $pag['id_externo']);
    }

    // ─── TC-WH-18: Split financeiro correto (comissao_plataforma = 0.20) ─────

    public function testSplitComComissao20Porcento(): void
    {
        getPDO()->exec("UPDATE configuracoes SET valor = '0.20' WHERE chave = 'comissao_plataforma'");
        // §SPLIT-LIQUIDO-01: isola este teste do default de produção de
        // reserva_gateway_percentual (0.045) — este teste verifica só a
        // matemática comissão/repasse sobre o valor cheio, coberto à parte
        // em PedidoTransitionServiceSplitLiquidoTest.
        getPDO()->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('reserva_gateway_percentual', '0')");

        $this->setPedidoEPagamento(9, 100.00);
        $this->setMpSignatureHeaders('ID-18');

        $this->ctrl->fakeInput  = $this->buildMpInput('ID-18');
        $this->ctrl->mockHttpGet = [
            'body'  => json_encode(['status' => 'approved', 'external_reference' => '9', 'id' => 'ID-18']),
            'code'  => 200,
            'error' => '',
        ];

        try {
            $this->ctrl->mercadoPago();
        } catch (HttpResponseException) { /* esperado */ }

        $pag = getPDO()->query("SELECT valor_guincho, valor_plataforma FROM pagamentos WHERE pedido_id = 9")->fetch();
        $this->assertEqualsWithDelta(80.00, (float)$pag['valor_guincho'],    0.01);
        $this->assertEqualsWithDelta(20.00, (float)$pag['valor_plataforma'], 0.01);
    }

    // ─── TC-WH-20: expires_at (expiracao_aceite) preenchido ──────────────────

    public function testExpiracao​AceitePreenchidaAposAprovacao(): void
    {
        $this->setPedidoEPagamento(10, 100.00);
        $this->setMpSignatureHeaders('ID-20');

        $this->ctrl->fakeInput  = $this->buildMpInput('ID-20');
        $this->ctrl->mockHttpGet = [
            'body'  => json_encode(['status' => 'approved', 'external_reference' => '10', 'id' => 'ID-20']),
            'code'  => 200,
            'error' => '',
        ];

        try {
            $this->ctrl->mercadoPago();
        } catch (HttpResponseException) { /* esperado */ }

        $pedido = getPDO()->query("SELECT expiracao_aceite FROM pedidos WHERE id = 10")->fetch();
        $this->assertNotNull($pedido['expiracao_aceite']);
        $this->assertNotEmpty($pedido['expiracao_aceite']);
    }
}

// ─── Subclasse testável: intercepta lerInput(), httpGet() e respond() ────────
class WebhookControllerTestable extends WebhookController
{
    public string $fakeInput   = '';
    public array  $mockHttpGet = ['body' => '', 'code' => 200, 'error' => ''];

    protected function lerInput(): string
    {
        return $this->fakeInput;
    }

    protected function httpGet(string $url, array $headers = []): array
    {
        return $this->mockHttpGet;
    }

    protected function respond(int $code, string $body = ''): never
    {
        throw new HttpResponseException($code, $body);
    }
}
