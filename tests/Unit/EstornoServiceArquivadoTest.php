<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * §ESTORNO-ARQUIVADO-01 (27/07/2026, achado em revisão de código) — depois
 * que ConversionService arquiva o pagamento aprovado original (socorro no
 * local) em `pagamentos_arquivados`, esse pagamento ficava inalcançável por
 * EstornoService::estornar(): nem o cancelamento automático nem o reembolso
 * MANUAL via DemandaService conseguiam localizá-lo (a busca só olhava a
 * tabela viva). Este teste cobre o novo parâmetro $incluirArquivado.
 */
class EstornoServiceArquivadoTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pagamentos_arquivados', 'pagamentos'] as $tabela) {
            try {
                $pdo->exec("DELETE FROM {$tabela}");
            } catch (Throwable) {
            }
        }
        EstornoServiceArquivadoTestable::$mockResponse = ['body' => '', 'code' => 201, 'error' => ''];
    }

    public function testSemIncluirArquivadoNaoEstornaPagamentoArquivado(): void
    {
        // Comportamento padrão (chamadores automáticos) precisa continuar
        // exatamente como antes: nada de novo acontece com pagamento
        // arquivado se o chamador não pedir explicitamente.
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pagamentos_arquivados (pedido_id, fase, metodo, valor_total, status, id_externo, arquivado_em)
                     VALUES (701, 'socorro_local', 'mercadopago', 50.00, 'aprovado', 'mp_original_701', datetime('now'))");

        $resultado = EstornoServiceArquivadoTestable::estornar(701, null, false);

        $this->assertTrue($resultado['sucesso']);
        $arquivado = $pdo->query("SELECT status FROM pagamentos_arquivados WHERE pedido_id = 701")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aprovado', $arquivado['status'], 'Sem incluirArquivado=true, o pagamento arquivado não pode ser tocado.');
    }

    public function testComIncluirArquivadoEstornaPagamentoArquivadoQuandoNaoHaLivro(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pagamentos_arquivados (pedido_id, fase, metodo, valor_total, status, id_externo, arquivado_em)
                     VALUES (702, 'socorro_local', 'mercadopago', 50.00, 'aprovado', 'mp_original_702', datetime('now'))");

        $resultado = EstornoServiceArquivadoTestable::estornar(702, null, true);

        $this->assertTrue($resultado['sucesso'], (string)($resultado['erro'] ?? ''));
        $arquivado = $pdo->query("SELECT status FROM pagamentos_arquivados WHERE pedido_id = 702")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('estornado', $arquivado['status']);
    }

    public function testPagamentoVivoTemPrioridadeSobreArquivado(): void
    {
        // Se existe pagamento aprovado VIVO (a cobrança complementar, por
        // exemplo), ele é o alvo — nunca o arquivado, mesmo com
        // incluirArquivado=true.
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pagamentos_arquivados (pedido_id, fase, metodo, valor_total, status, id_externo, arquivado_em)
                     VALUES (703, 'socorro_local', 'mercadopago', 50.00, 'aprovado', 'mp_original_703', datetime('now'))");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, id_externo, status, valor_total)
                     VALUES (703, 'mercadopago', 'mp_complementar_703', 'aprovado', 80.00)");

        $resultado = EstornoServiceArquivadoTestable::estornar(703, null, true);

        $this->assertTrue($resultado['sucesso'], (string)($resultado['erro'] ?? ''));
        $vivo = $pdo->query("SELECT status FROM pagamentos WHERE pedido_id = 703")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('estornado', $vivo['status'], 'Pagamento vivo (complementar) deveria ter sido o alvo.');
        $arquivado = $pdo->query("SELECT status FROM pagamentos_arquivados WHERE pedido_id = 703")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aprovado', $arquivado['status'], 'Pagamento arquivado não deveria ter sido tocado quando existe um vivo.');
    }
}

class EstornoServiceArquivadoTestable extends EstornoService
{
    public static array $mockResponse = ['body' => '', 'code' => 201, 'error' => ''];

    protected static function httpPostRaw(string $url, array $headers, string $body): array
    {
        return self::$mockResponse;
    }
}
