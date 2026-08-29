<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/CancelamentoService.php';
require_once __DIR__ . '/../../src/Models/CancellationSnapshot.php';
require_once __DIR__ . '/../../src/Models/Pedido.php';

/**
 * Pacote L1.6 — cobre o fluxo de preview persistido + confirmação por snapshot_id.
 */
final class CancellationSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['cancelamento_snapshots', 'pedido_cancelamentos', 'pedido_idempotency', 'pagamentos', 'pedidos', 'guinchos', 'veiculos', 'usuarios', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('cancelamento_gratis_min', '0')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_cancelamento_percent', '20')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_cancelamento_fixa', '15')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('km_bloqueio_cancelamento', '2')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('cancelamento_preview_ttl_min', '3')");

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (601, 'Cliente Snapshot', 'cliente.snapshot@example.com', 'hash', '11999990001', '60111111111', 'cliente')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (601, 601, 'SNP1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')
        ");
    }

    public function testPreviewPersistsSnapshotAndConfirmationSucceedsWithIt(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (701, 'aguardando_guincho', 100.00, 601, 601, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $pedido = Pedido::buscarPorId(701);
        $preview = CancelamentoService::previewClienteComSnapshot($pedido, 601);

        $this->assertTrue($preview['pode']);
        $this->assertNotNull($preview['snapshot_id']);
        $this->assertNotEmpty($preview['snapshot_hash']);
        $this->assertSame('v2', $preview['formula_version']);

        $snapshot = CancellationSnapshot::buscarPorId((int)$preview['snapshot_id']);
        $this->assertNotNull($snapshot);
        $this->assertSame('pending', $snapshot['status']);
        $this->assertSame(701, (int)$snapshot['pedido_id']);
        $this->assertSame(601, (int)$snapshot['actor_id']);

        $resultado = CancelamentoService::cancelarPorCliente(701, 601, 'Mudei de ideia', (int)$preview['snapshot_id']);
        $this->assertTrue($resultado['ok'], (string)$resultado['erro']);

        $status = $pdo->query("SELECT status FROM pedidos WHERE id = 701")->fetchColumn();
        $this->assertSame('cancelado', $status);

        $snapshotDepois = CancellationSnapshot::buscarPorId((int)$preview['snapshot_id']);
        $this->assertSame('confirmed', $snapshotDepois['status']);
        $this->assertNotNull($snapshotDepois['confirmed_at']);
    }

    public function testConfirmationWithoutSnapshotIdIsRejected(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (702, 'aguardando_guincho', 100.00, 601, 601, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $resultado = CancelamentoService::cancelarPorCliente(702, 601, 'Sem preview');
        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('Preview', (string)$resultado['erro']);

        $status = $pdo->query("SELECT status FROM pedidos WHERE id = 702")->fetchColumn();
        $this->assertSame('aguardando_guincho', $status, 'Pedido não deve mudar de status sem snapshot válido.');
    }

    public function testExpiredSnapshotIsRejectedAndRequiresNewPreview(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (703, 'aguardando_guincho', 100.00, 601, 601, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $pedido = Pedido::buscarPorId(703);
        $preview = CancelamentoService::previewClienteComSnapshot($pedido, 601);

        // Força a expiração do snapshot recém-criado (simula TTL vencido).
        // DATE_SUB/INTERVAL é sintaxe MySQL sem equivalente direto no SQLite
        // de teste; calculado em PHP e passado como parâmetro.
        $expiraNoPassado = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $pdo->prepare("UPDATE cancelamento_snapshots SET expires_at = ? WHERE id = ?")
            ->execute([$expiraNoPassado, $preview['snapshot_id']]);

        $resultado = CancelamentoService::cancelarPorCliente(703, 601, 'Tarde demais', (int)$preview['snapshot_id']);
        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('expirou', (string)$resultado['erro']);

        $status = $pdo->query("SELECT status FROM pedidos WHERE id = 703")->fetchColumn();
        $this->assertSame('aguardando_guincho', $status);
    }

    public function testSnapshotCannotBeReusedAfterConfirmation(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (704, 'aguardando_guincho', 100.00, 601, 601, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino'),
                   (705, 'aguardando_guincho', 100.00, 601, 601, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $pedido = Pedido::buscarPorId(704);
        $preview = CancelamentoService::previewClienteComSnapshot($pedido, 601);

        $primeira = CancelamentoService::cancelarPorCliente(704, 601, 'Primeira confirmação', (int)$preview['snapshot_id']);
        $this->assertTrue($primeira['ok']);

        // Reaproveitar o mesmo snapshot_id para outro pedido (ou o mesmo) deve falhar.
        $segunda = CancelamentoService::cancelarPorCliente(705, 601, 'Reuso indevido', (int)$preview['snapshot_id']);
        $this->assertFalse($segunda['ok']);
    }

    public function testPedidoCancelamentosReceivesAuditRow(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (706, 'aguardando_guincho', 100.00, 601, 601, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $pedido = Pedido::buscarPorId(706);
        $preview = CancelamentoService::previewClienteComSnapshot($pedido, 601);
        $resultado = CancelamentoService::cancelarPorCliente(706, 601, 'Auditoria conectada', (int)$preview['snapshot_id']);
        $this->assertTrue($resultado['ok']);

        $row = $pdo->query("SELECT * FROM pedido_cancelamentos WHERE pedido_id = 706")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row, 'pedido_cancelamentos deve receber uma linha — a tabela não pode mais ficar órfã.');
        $this->assertSame('cliente', $row['ator_tipo']);
        $this->assertSame(601, (int)$row['ator_id']);
    }
}
