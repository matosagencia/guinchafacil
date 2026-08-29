<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/PaymentJobService.php';

/**
 * Pacote L1.7 — cobre o caminho de "dead-letter": um job que falha de forma
 * permanente (pedido não concluído) precisa parar de ser tentado automaticamente
 * (status='failed'), e só volta a rodar se o admin reabrir explicitamente via
 * PaymentJobService::forceRetry().
 */
final class PaymentJobDeadLetterTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['payout_ledger_entries', 'payment_job_attempts', 'payment_jobs', 'pagamentos', 'pedidos', 'guinchos', 'veiculos', 'usuarios', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (911, 'Cliente DeadLetter', 'cliente.deadletter@example.com', 'hash', '11999992001', '91111111111', 'cliente'),
            (912, 'Guincho DeadLetter', 'guincho.deadletter@example.com', 'hash', '11999992002', '91222222222', 'guincho')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (911, 911, 'DLT1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')
        ");
    }

    public function testJobGoesToDeadLetterWhenPedidoNotConcludedAndCanBeReopenedByAdmin(): void
    {
        $pdo = getPDO();
        // Pedido AINDA NÃO concluído — processJob() marca falha permanente na primeira tentativa.
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (911, 'em_reboque', 100.00, 911, 911, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        $pdo->exec("INSERT INTO pagamentos (id, pedido_id, metodo, status, valor_total, valor_guincho, valor_plataforma) VALUES (911, 911, 'pix', 'aprovado', 100, 85, 15)");
        $pdo->exec("
            INSERT INTO payment_jobs (id, pedido_id, pagamento_id, job_type, idempotency_key, status, attempt_count, max_attempts, available_at, payload_json, created_at, updated_at)
            VALUES (911, 911, 911, 'pix_payout', 'pix_payout:911', 'queued', 0, 5, NOW(), '{\"pedido_id\":911,\"pagamento_id\":911,\"valor_guincho\":85}', NOW(), NOW())
        ");

        $result = PaymentJobService::processJob(911);
        $this->assertFalse($result['ok']);

        $job = $pdo->query("SELECT status, attempt_count, last_error FROM payment_jobs WHERE id = 911")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('failed', $job['status'], 'Falha permanente deve ir direto para dead-letter, sem esperar retry.');
        $this->assertStringContainsString('não está concluído', $job['last_error']);

        // Uma segunda chamada de processamento automático NÃO deve tentar de novo:
        // processJob() recusa jobs com status='failed'.
        $segunda = PaymentJobService::processJob(911);
        $this->assertFalse($segunda['ok']);
        $this->assertStringContainsString('falha permanente', $segunda['erro']);

        $jobDepois = $pdo->query("SELECT status, attempt_count FROM payment_jobs WHERE id = 911")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('failed', $jobDepois['status']);
        $this->assertSame(1, (int)$jobDepois['attempt_count'], 'Job em dead-letter não pode acumular tentativas automáticas.');

        // Admin reabre manualmente o job (ex.: depois de corrigir o problema).
        $reaberto = PaymentJobService::forceRetry(911, 999);
        $this->assertTrue($reaberto['ok']);

        $jobReaberto = $pdo->query("SELECT status, last_error FROM payment_jobs WHERE id = 911")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('queued', $jobReaberto['status']);
        $this->assertNull($jobReaberto['last_error']);
    }

    public function testForceRetryRefusesAlreadyCompletedJob(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (912, 'concluido', 100.00, 911, 911, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        $pdo->exec("INSERT INTO pagamentos (id, pedido_id, metodo, status, valor_total, valor_guincho, valor_plataforma) VALUES (912, 912, 'pix', 'aprovado', 100, 85, 15)");
        $pdo->exec("
            INSERT INTO payment_jobs (id, pedido_id, pagamento_id, job_type, idempotency_key, status, attempt_count, max_attempts, available_at, payload_json, created_at, updated_at)
            VALUES (912, 912, 912, 'pix_payout', 'pix_payout:912', 'completed', 1, 5, NOW(), '{}', NOW(), NOW())
        ");

        $resultado = PaymentJobService::forceRetry(912, 999);
        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('já concluído', $resultado['erro']);
    }
}
