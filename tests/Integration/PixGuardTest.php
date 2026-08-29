<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PixGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pagamentos', 'pedidos', 'veiculos', 'usuarios'] as $t) { try { $pdo->exec("DELETE FROM {$t}"); } catch (Throwable) {} }

        // Fixtures mínimas exigidas pela FK fk_pedidos_cliente/fk_pedidos_veiculo (Pacote L1.3).
        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.pixguard@example.com', 'hash', '11988880003', '30303030303', 'cliente')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'PXG1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')
        ");
    }

    /** Cria um pedido mínimo com o id informado, satisfazendo as FKs de pagamentos. */
    private function novoPedido(int $id): void
    {
        getPDO()->exec("
            INSERT INTO pedidos
                (id, status, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES
                ({$id}, 'concluido', 1, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
    }

    public function testContarAprovadosPorPedidoSemPagamentoRetornaZero(): void
    {
        $this->assertSame(0, Pagamento::contarAprovadosPorPedido(999));
    }

    public function testPrepararRepassePixBloqueiaPedidoSemPagamentoAprovado(): void
    {
        $guard = Pagamento::prepararRepassePix(123, 80.00, 20.00);

        $this->assertFalse($guard['ok']);
        $this->assertStringContainsString('PIX-GUARD-01', $guard['erro']);
    }

    /**
     * Pacote L1.3 — este cenário mudou de natureza. A tabela `pagamentos` tem
     * uma constraint UNIQUE em `pedido_id` (agora documentada formalmente em
     * install/migrate.php via addUniqueIndex 'uk_pagamentos_pedido_id'), então
     * hoje é fisicamente impossível existirem 2 linhas de pagamento para o
     * mesmo pedido — a garantia passou de "checagem em código" (PIX-GUARD-01
     * contando linhas com status='aprovado') para "garantia de banco".
     *
     * O teste original inseria 2 linhas para forçar o guard de aplicação a
     * bloquear; isso não é mais possível de simular dessa forma porque o
     * próprio INSERT já falha. Este teste agora confirma essa garantia mais
     * forte diretamente: a segunda inserção deve ser rejeitada pelo banco.
     */
    public function testPrepararRepassePixBloqueiaPagamentoDuplicado(): void
    {
        $this->novoPedido(7);
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total, valor_guincho, valor_plataforma) VALUES (7, 'pix', 'aprovado', 100, 0, 0)");

        // "Duplicate entry" é o texto do MySQL para violação de UNIQUE; o SQLite
        // usado nos testes de integração relata "UNIQUE constraint failed" — a
        // regra em si (uk_pagamentos_pedido_id, ver install/migrate.php) é a
        // mesma nos dois bancos, então aceitamos qualquer uma das duas frases.
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/(Duplicate entry|UNIQUE constraint failed)/i');
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total, valor_guincho, valor_plataforma) VALUES (7, 'pix', 'aprovado', 100, 0, 0)");
    }

    public function testPrepararEConfirmarRepassePixExigePagamentoAprovadoUnico(): void
    {
        $this->novoPedido(9);
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pagamentos (id, pedido_id, metodo, status, status_pix, valor_total, valor_guincho, valor_plataforma, pago_guincho) VALUES (55, 9, 'pix', 'aprovado', 'pendente', 100, 0, 0, 0)");

        $guard = Pagamento::prepararRepassePix(9, 85.00, 15.00);

        $this->assertTrue($guard['ok'], (string)($guard['erro'] ?? ''));
        $this->assertSame(55, (int)$guard['pagamento']['id']);

        $pag = $pdo->query("SELECT status_pix, valor_guincho, valor_plataforma, pago_guincho FROM pagamentos WHERE id = 55")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('processando', $pag['status_pix']);
        $this->assertSame(85.0, (float)$pag['valor_guincho']);
        $this->assertSame(15.0, (float)$pag['valor_plataforma']);
        $this->assertSame(0, (int)$pag['pago_guincho']);

        $this->assertTrue(Pagamento::confirmarRepassePix(55, 'TX-GUARD-OK'));

        $pag = $pdo->query("SELECT status_pix, id_transacao_pix, pago_guincho FROM pagamentos WHERE id = 55")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('concluido', $pag['status_pix']);
        $this->assertSame('TX-GUARD-OK', $pag['id_transacao_pix']);
        $this->assertSame(1, (int)$pag['pago_guincho']);
    }

    public function testTransferirBloqueiaPagamentoNaoPreparado(): void
    {
        $this->novoPedido(10);
        getPDO()->exec("INSERT INTO pagamentos (pedido_id, metodo, status, status_pix, valor_total, valor_guincho, valor_plataforma, pago_guincho) VALUES (10, 'pix', 'aprovado', 'pendente', 100, 80, 20, 0)");

        $resultado = PixService::transferir(10, 80.00, 'pix@test.com', 'email');

        $this->assertFalse($resultado['sucesso']);
        $this->assertStringContainsString('PIX-GUARD-03', $resultado['erro']);
    }

    public function testTransferirPermitePagamentoAprovadoPreparado(): void
    {
        $this->novoPedido(11);
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pagamentos (id, pedido_id, metodo, status, status_pix, valor_total, valor_guincho, valor_plataforma, pago_guincho) VALUES (56, 11, 'pix', 'aprovado', 'processando', 100, 80, 20, 0)");

        PixServiceGuardHttpOk::$mockResponse = ['body' => '{"id":"TX-DIRECT-GUARD"}', 'code' => 201, 'error' => ''];

        $resultado = PixServiceGuardHttpOk::transferir(11, 80.00, 'pix@test.com', 'email');

        $this->assertTrue($resultado['sucesso'], (string)($resultado['erro'] ?? ''));
        $this->assertSame('TX-DIRECT-GUARD', $resultado['id_transacao']);
    }
}

final class PixServiceGuardHttpOk extends PixService
{
    public static array $mockResponse = ['body' => '{"id":"TX-DIRECT-GUARD"}', 'code' => 201, 'error' => ''];

    protected static function httpPost(string $url, array $headers, string $body): array
    {
        return self::$mockResponse;
    }
}
