<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../src/DTO/PedidoTransitionRequest.php';
require_once __DIR__ . '/../../src/Models/Pedido.php';

/**
 * Cobre especificamente as 5 correções do Pacote L1.3 (máquina de estados
 * e concorrência), complementando PedidoTransitionServiceTest.php (que só
 * garante que o comportamento pré-existente não quebrou).
 */
final class PedidoTransitionServiceL13Test extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pedido_evidencias', 'pedido_localizacoes', 'pedido_idempotency', 'pagamentos', 'pedidos', 'guinchos', 'veiculos', 'usuarios', 'configuracoes', 'app_logs'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('por_arrival_radius_m', '150')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('por_destination_radius_m', '200')");

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.l13@example.com', 'hash', '11999999999', '11111111121', 'cliente'),
            (2, 'Guincho Teste', 'guincho.l13@example.com', 'hash', '11999999998', '22222222232', 'guincho')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'TST2B34', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')
        ");
        $pdo->exec("
            INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (1, 2, 1, 1)
        ");
    }

    private function novoPedido(int $id, string $status, ?int $guinchoId = null): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos
                (id, status, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES
                ({$id}, '{$status}', 1, 1, " . ($guinchoId ?? 'NULL') . ", -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
    }

    /** Item L1.3 #3 — noop explícito: pedir a mesma transição não falha nem duplica efeito. */
    public function testTransitionToSameStatusIsExplicitNoop(): void
    {
        $this->novoPedido(501, 'aguardando_guincho');

        $request = new PedidoTransitionRequest('admin', 1, 501, 'aguardando_guincho');
        $result = PedidoTransitionService::transition($request);

        $this->assertTrue($result->ok, (string)$result->error);
        $this->assertTrue($result->context['noop'] ?? false, 'Esperava noop=true no contexto do resultado.');
    }

    /** Item L1.3 #1 — ator 'system' não é mais isento de geofence/evidência. */
    public function testSystemActorIsNoLongerExemptFromPreconditions(): void
    {
        $this->novoPedido(502, 'em_reboque', 1);

        // Sem nenhum ponto de POR registrado, o snapshot não tem last_valid_point,
        // então a checagem de geofence deve bloquear mesmo para actorType='system'.
        $request = new PedidoTransitionRequest('system', 0, 502, 'concluido', 1, [
            'foto_destino' => 'qualquer.jpg',
        ]);
        $result = PedidoTransitionService::transition($request);

        $this->assertFalse($result->ok, 'actorType=system não deveria mais contornar a checagem de geofence/evidência.');
        $this->assertNotNull($result->error);
    }

    /** Item L1.3 #2 — idempotência: reenviar a mesma idempotency_key não falha nem reatribui. */
    public function testAcceptByGuinchoIsIdempotentWithSameKey(): void
    {
        $this->novoPedido(503, 'aguardando_guincho');

        $key = 'test-idem-key-503';
        $first = PedidoTransitionService::acceptByGuincho(503, 1, 1, $key);
        $this->assertTrue($first->ok, (string)$first->error);
        $this->assertSame('a_caminho', $first->context['status_novo']);

        // Reenvio da MESMA requisição (ex: timeout de rede e retry do app).
        $second = PedidoTransitionService::acceptByGuincho(503, 1, 1, $key);
        $this->assertTrue($second->ok, 'Reenvio com a mesma idempotency_key deveria retornar o resultado já registrado, não falhar.');
        $this->assertSame('a_caminho', $second->context['status_novo']);

        $pdo = getPDO();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM pedido_idempotency WHERE idempotency_key = " . $pdo->quote($key))->fetchColumn();
        $this->assertSame(1, $count, 'Deveria existir exatamente um registro de idempotência para esta chave.');
    }

    /** Item L1.3 #5 — evidência validada por evidence_id persistido, não por nome de arquivo solto. */
    public function testEvidenceValidationRequiresAcceptedRecord(): void
    {
        $this->novoPedido(504, 'em_reboque', 1);

        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedido_localizacoes
                (id, pedido_id, guincho_id, usuario_id, fase, sequence_number, client_point_id, latitude, longitude)
            VALUES
                (1, 504, 1, 2, 'em_reboque', 1, 'test-point-1', -23.56, -46.64)
        ");
        // DATE_ADD/INTERVAL e REPEAT() são sintaxe/função MySQL sem equivalente
        // direto no SQLite de teste — calculados em PHP e passados como parâmetros.
        $nonceExpiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $sha256Fake = str_repeat('a', 64);
        $pdo->prepare("
            INSERT INTO pedido_evidencias
                (id, pedido_id, guincho_id, tipo, status, nonce_token, nonce_expires_at, point_id, latitude, longitude,
                 original_name, stored_name, mime_type, size_bytes, sha256)
            VALUES
                (901, 504, 1, 'entrega', 'pending', 'nonce-abc', ?, 1, -23.56, -46.64,
                 'foto.jpg', 'stored_foto.jpg', 'image/jpeg', 12345, ?)
        ")->execute([$nonceExpiresAt, $sha256Fake]);

        $reflection = new ReflectionClass(PedidoTransitionService::class);
        $method = $reflection->getMethod('validateEvidence');
        $method->setAccessible(true);

        $pedido = ['id' => 504];
        $requestPending = new PedidoTransitionRequest('guincho', 1, 504, 'concluido', 1, ['evidence_id' => 901]);
        $errorPending = $method->invoke(null, $pedido, $requestPending, 'entrega');
        $this->assertNotNull($errorPending, 'Evidência com status pending não deveria ser aceita.');

        $pdo->exec("UPDATE pedido_evidencias SET status = 'accepted' WHERE id = 901");
        $requestAccepted = new PedidoTransitionRequest('guincho', 1, 504, 'concluido', 1, ['evidence_id' => 901]);
        $errorAccepted = $method->invoke(null, $pedido, $requestAccepted, 'entrega');
        $this->assertNull($errorAccepted, 'Evidência com status accepted deveria passar na validação.');

        // evidence_id inexistente deve falhar, não silenciar.
        $requestMissing = new PedidoTransitionRequest('guincho', 1, 504, 'concluido', 1, ['evidence_id' => 999999]);
        $errorMissing = $method->invoke(null, $pedido, $requestMissing, 'entrega');
        $this->assertNotNull($errorMissing, 'evidence_id inexistente deveria falhar a validação.');
    }

    /** Item L1.3 #4 — atribuirGuincho() legado emite aviso de depreciação em runtime. */
    public function testAtribuirGuinchoLegadoEmiteAvisoDeDeprecacao(): void
    {
        $this->novoPedido(505, 'aguardando_guincho');

        $capturedLevel = null;
        set_error_handler(function (int $errno) use (&$capturedLevel): bool {
            $capturedLevel = $errno;
            return true; // suprime o warning padrão do PHP durante o teste
        }, E_USER_DEPRECATED);

        Pedido::atribuirGuincho(505, 1);

        restore_error_handler();

        $this->assertSame(E_USER_DEPRECATED, $capturedLevel, 'Pedido::atribuirGuincho() deveria emitir E_USER_DEPRECATED.');
    }
}
