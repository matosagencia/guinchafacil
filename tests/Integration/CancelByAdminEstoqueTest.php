<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../src/Models/Produto.php';
require_once __DIR__ . '/../../src/Models/ProviderProdutoEstoque.php';
require_once __DIR__ . '/../../src/Services/Estoque/EstoqueService.php';

/**
 * §COBERTURA-RAIO-01 (06/08/2026) — gap encontrado ao auditar o pedido do
 * usuário pra verificar o caminho administrativo: PedidoTransitionService::
 * cancelByAdmin() cancela um pedido em QUALQUER status não-terminal
 * (só bloqueia 'cancelado'/'concluido'), incluindo em_execucao_servico com
 * um orçamento já APROVADO e estoque já baixado — usado por
 * AdminController::cancelarPedido() (ação manual do admin) e por
 * DemandaService (resolução de reclamação/reembolso). Antes desta correção,
 * nada revertia esse estoque. Mesmo fix de
 * CancelamentoService::cancelarPorCliente(), agora centralizado em
 * EstoqueService::estornarEstoqueDeOrcamentoAprovado().
 */
final class CancelByAdminEstoqueTest extends TestCase
{
    private int $produtoId;
    private const PROVIDER_ID = 501;
    private const CLIENTE_ID = 1;

    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['estoque_movimentos', 'provider_produtos_estoque', 'produtos', 'pedido_orcamentos', 'pedido_diagnosticos', 'pagamentos', 'pedidos', 'guinchos', 'veiculos', 'usuarios'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }
        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.admin@example.com', 'hash', '11999999999', '11111111111', 'cliente'),
            (2, 'Guincho Teste', 'guincho.admin@example.com', 'hash', '11999999998', '22222222222', 'guincho')
        ");
        $pdo->exec("INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES (1, 1, 'TST1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (" . self::PROVIDER_ID . ", 2, 1, 0)");

        $this->produtoId = Produto::criar([
            'sku' => 'BAT-QA-ADMIN', 'nome' => 'Bateria QA Admin', 'categoria' => 'bateria',
            'preco_referencia' => 400.00, 'active' => 1,
        ]);
        ProviderProdutoEstoque::definir(self::PROVIDER_ID, $this->produtoId, 3, 380.00);
    }

    public function testCancelamentoAdminComOrcamentoAprovadoEstornaEstoque(): void
    {
        $pedidoId = 601;
        $pdo = getPDO();
        $pdo->prepare(
            "INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id,
                lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino,
                attendance_mode, criado_em)
             VALUES (?, 'em_execucao_servico', 400.00, ?, 1, ?, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino', 'ON_SITE', datetime('now'))"
        )->execute([$pedidoId, self::CLIENTE_ID, self::PROVIDER_ID]);

        $pdo->prepare("INSERT INTO pedido_diagnosticos (pedido_id, guincho_id, resultado, descricao) VALUES (?, ?, 'REQUER_ORCAMENTO', 'Diagnóstico QA admin')")
            ->execute([$pedidoId, self::PROVIDER_ID]);
        $diagnosticoId = (int)$pdo->lastInsertId();

        // Simula a baixa que já teria acontecido em decidirOrcamento(aprovado=true).
        EstoqueService::baixarPorPedido(self::PROVIDER_ID, $this->produtoId, $pedidoId, 1, 'Setup do teste');
        $this->assertSame(2, EstoqueService::disponivel(self::PROVIDER_ID, $this->produtoId));

        $itens = [['descricao' => 'Bateria 60Ah', 'valor' => 400.00, 'produto_id' => $this->produtoId, 'quantidade' => 1]];
        $pdo->prepare("INSERT INTO pedido_orcamentos (pedido_id, diagnostico_id, itens_json, valor_total, status) VALUES (?, ?, ?, 400.00, 'APROVADO')")
            ->execute([$pedidoId, $diagnosticoId, json_encode($itens, JSON_UNESCAPED_UNICODE)]);

        $result = PedidoTransitionService::cancelByAdmin($pedidoId, 999, 'Cancelamento administrativo QA');

        $this->assertTrue($result->ok, (string)$result->error);
        $pedido = $pdo->query("SELECT status FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('cancelado', $pedido['status']);

        // O ponto central: estoque voltou ao que era antes da baixa.
        $this->assertSame(3, EstoqueService::disponivel(self::PROVIDER_ID, $this->produtoId));

        $movimento = $pdo->query(
            "SELECT tipo, quantidade FROM estoque_movimentos WHERE pedido_id = {$pedidoId} AND produto_id = {$this->produtoId} AND tipo = 'ESTORNO'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($movimento, 'esperava um movimento ESTORNO gravado');
        $this->assertSame(1, (int)$movimento['quantidade']);
    }

    public function testCancelamentoAdminSemOrcamentoNaoTocaEstoque(): void
    {
        // Caminho do cron de timeout (ExpiracaoPedidosService): pedido em
        // aguardando_guincho, nunca passou por diagnóstico/orçamento —
        // confirma que o fix não introduz nenhum efeito colateral aqui.
        $pedidoId = 602;
        $pdo = getPDO();
        $pdo->prepare(
            "INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id,
                lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino, criado_em)
             VALUES (?, 'aguardando_guincho', 149.90, ?, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino', datetime('now'))"
        )->execute([$pedidoId, self::CLIENTE_ID]);

        $result = PedidoTransitionService::cancelByAdmin($pedidoId, 999, 'Expiração automática do aceite do guincho.');

        $this->assertTrue($result->ok, (string)$result->error);
        $this->assertSame(3, EstoqueService::disponivel(self::PROVIDER_ID, $this->produtoId), 'saldo do produto não relacionado não pode mudar');

        $count = (int)$pdo->query("SELECT COUNT(*) FROM estoque_movimentos WHERE pedido_id = {$pedidoId}")->fetchColumn();
        $this->assertSame(0, $count);
    }
}
