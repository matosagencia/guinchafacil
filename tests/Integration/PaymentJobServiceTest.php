<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/PaymentJobService.php';

final class PaymentJobServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['payment_job_attempts', 'payment_jobs', 'pagamentos', 'pedidos', 'guinchos', 'veiculos', 'usuarios', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");

        // Fixtures mínimas exigidas pelas FKs de pedidos/guinchos (Pacote L1.3).
        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.pj@example.com', 'hash', '11988880001', '10101010101', 'cliente'),
            (2, 'Guincho Teste', 'guincho.pj@example.com', 'hash', '11988880002', '20202020202', 'guincho')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'PJT1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')
        ");
    }

    public function testEnqueuePixPayoutIsIdempotentByPedido(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino) VALUES (1, 'concluido', 100, 1, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')");
        $pdo->exec("INSERT INTO pagamentos (id, pedido_id, metodo, status, valor_total, valor_guincho, valor_plataforma) VALUES (11, 1, 'pix', 'aprovado', 100, 85, 15)");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel, chave_pix, chave_pix_tipo) VALUES (21, 2, 1, 1, 'pix@test.com', 'email')");
        $pdo->exec("UPDATE pedidos SET guincho_id = 21 WHERE id = 1");

        $first = PaymentJobService::enqueuePixPayout(1, 85, 15);
        $second = PaymentJobService::enqueuePixPayout(1, 85, 15);

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertTrue($first['queued']);
        $this->assertFalse($second['queued']);
        $this->assertSame($first['job_id'], $second['job_id']);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM payment_jobs")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testProcessJobRefusesCompletedJob(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO payment_jobs (id, pedido_id, pagamento_id, job_type, idempotency_key, status, attempt_count, max_attempts, available_at, payload_json, created_at, updated_at) VALUES (31, 1, 11, 'pix_payout', 'pix_payout:1', 'completed', 1, 5, '2026-07-12 00:00:00', '{}', '2026-07-12 00:00:00', '2026-07-12 00:00:00')");

        $result = PaymentJobService::processJob(31, 'test_worker');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['processed']);
        $this->assertSame('completed', $result['status']);
    }

    public function testProcessJobMarksRetryWhenPedidoNotConcluido(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, status, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino) VALUES (41, 'a_caminho', 1, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')");
        $pdo->exec("INSERT INTO pagamentos (id, pedido_id, metodo, status, valor_total) VALUES (42, 41, 'pix', 'aprovado', 100)");
        $pdo->exec("INSERT INTO payment_jobs (id, pedido_id, pagamento_id, job_type, idempotency_key, status, attempt_count, max_attempts, available_at, payload_json, created_at, updated_at) VALUES (43, 41, 42, 'pix_payout', 'pix_payout:41', 'running', 0, 5, '2026-07-12 00:00:00', '{\"pedido_id\":41,\"pagamento_id\":42,\"valor_guincho\":85}', '2026-07-12 00:00:00', '2026-07-12 00:00:00')");

        $result = PaymentJobService::processJob(43, 'test_worker');

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['processed']);

        $job = $pdo->query("SELECT status, attempt_count, last_error FROM payment_jobs WHERE id = 43")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('failed', $job['status']);
        $this->assertSame('1', (string)$job['attempt_count']);
        $this->assertStringContainsString('não está concluído', (string)$job['last_error']);
    }

    public function testForceRetryRequeuesFailedJob(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO payment_jobs (id, pedido_id, pagamento_id, job_type, idempotency_key, status, attempt_count, max_attempts, available_at, last_error, worker_id, locked_at, finished_at, payload_json, created_at, updated_at) VALUES (51, 1, 11, 'pix_payout', 'pix_payout:51', 'failed', 5, 5, '2026-07-12 00:00:00', 'Erro antigo', 'worker_x', '2026-07-12 00:00:00', '2026-07-12 00:00:00', '{}', '2026-07-12 00:00:00', '2026-07-12 00:00:00')");

        $result = PaymentJobService::forceRetry(51, 99);

        $this->assertTrue($result['ok']);
        $job = $pdo->query("SELECT status, last_error, worker_id, locked_at, finished_at FROM payment_jobs WHERE id = 51")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('queued', $job['status']);
        $this->assertNull($job['worker_id']);
        $this->assertNull($job['locked_at']);
        $this->assertNull($job['finished_at']);
        $this->assertNull($job['last_error']);
    }
}
