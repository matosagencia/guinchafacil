<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/POR/ProofOfRoadService.php';
require_once __DIR__ . '/../../src/Models/PedidoLocalizacao.php';
require_once __DIR__ . '/../../src/Models/PedidoPercursoResumo.php';

/**
 * Cobre as 2 correções do Pacote L1.4 (Proof-of-Road):
 * 1. Duplicidade de ponto GPS (retry de rede) deve retornar resposta
 *    idempotente, não estourar um 500.
 * 2. Ponto + resumo da trilha são gravados na mesma transação.
 */
final class ProofOfRoadServiceL14Test extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pedido_localizacoes', 'pedido_percurso_resumos', 'pedidos', 'guinchos', 'veiculos', 'usuarios', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.por@example.com', 'hash', '11988880010', '60606060606', 'cliente'),
            (2, 'Guincho Teste', 'guincho.por@example.com', 'hash', '11988880011', '70707070707', 'guincho')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'POR1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')
        ");
        $pdo->exec("
            INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (1, 2, 1, 1)
        ");
        $pdo->exec("
            INSERT INTO pedidos
                (id, status, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES
                (601, 'a_caminho', 1, 1, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
    }

    private function payload(string $clientPointId, int $sequence): array
    {
        return [
            'client_point_id' => $clientPointId,
            'sequence' => $sequence,
            'latitude' => -23.551,
            'longitude' => -46.631,
            'accuracy_m' => 10,
            'device_timestamp' => (string)(int)(microtime(true) * 1000),
        ];
    }

    /** Item L1.4 #2 — ponto e resumo são gravados atomicamente. */
    public function testIngestPointCreatesPointAndSummaryTogether(): void
    {
        $result = ProofOfRoadService::ingestPoint(601, 1, 2, $this->payload('point-a', 1));

        $this->assertTrue($result['ok'], (string)($result['erro'] ?? ''));
        $this->assertGreaterThan(0, $result['point_id']);

        $pdo = getPDO();
        $pointCount = (int)$pdo->query("SELECT COUNT(*) FROM pedido_localizacoes WHERE pedido_id = 601")->fetchColumn();
        $summaryCount = (int)$pdo->query("SELECT COUNT(*) FROM pedido_percurso_resumos WHERE pedido_id = 601")->fetchColumn();

        $this->assertSame(1, $pointCount, 'O ponto deveria ter sido gravado.');
        $this->assertSame(1, $summaryCount, 'O resumo da trilha deveria ter sido gravado junto, na mesma operação.');
    }

    /** Item L1.4 #1 — reenvio do mesmo client_point_id (retry de rede) é idempotente, não erro. */
    public function testDuplicateClientPointIdIsIdempotent(): void
    {
        $first = ProofOfRoadService::ingestPoint(601, 1, 2, $this->payload('point-retry', 1));
        $this->assertTrue($first['ok'], (string)($first['erro'] ?? ''));

        // Reenvio do MESMO client_point_id, simulando o app do guincheiro
        // retransmitindo depois de uma queda de rede.
        $second = ProofOfRoadService::ingestPoint(601, 1, 2, $this->payload('point-retry', 1));

        $this->assertTrue($second['ok'], 'Reenvio do mesmo ponto não deveria falhar.');
        $this->assertSame($first['point_id'], $second['point_id'], 'Deveria retornar o mesmo ponto já gravado, não criar um novo.');

        $pdo = getPDO();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM pedido_localizacoes WHERE pedido_id = 601 AND client_point_id = 'point-retry'")->fetchColumn();
        $this->assertSame(1, $count, 'Não deveria existir mais de uma linha para o mesmo client_point_id.');
    }

    /**
     * Item L1.4 #1 (corrida real) — simula duas requisições concorrentes que
     * já passaram do check-then-act (buscarPorClientPointId) antes de qualquer
     * insert, forçando as duas a tentar inserir a mesma sequence_number.
     * A segunda deve cair no catch de PDOException 23000 e retornar idempotente,
     * não estourar um erro genérico.
     */
    public function testConcurrentSameSequenceNumberIsHandledGracefully(): void
    {
        $pdo = getPDO();

        // Insere manualmente o "vencedor" da corrida, como se outra requisição
        // já tivesse gravado esse client_point_id um instante antes.
        $pdo->exec("
            INSERT INTO pedido_localizacoes
                (pedido_id, guincho_id, usuario_id, fase, sequence_number, client_point_id, latitude, longitude,
                 distance_raw_m, distance_validated_m, distance_accumulated_m, is_valid)
            VALUES
                (601, 1, 2, 'origem', 1, 'point-race', -23.551, -46.631, 0, 0, 0, 1)
        ");

        // ingestPoint com o MESMO client_point_id deve encontrar o registro via
        // buscarPorClientPointId (check-then-act) e retornar idempotente, sem
        // sequer tentar o insert.
        $result = ProofOfRoadService::ingestPoint(601, 1, 2, $this->payload('point-race', 1));

        $this->assertTrue($result['ok']);
        $this->assertIsInt($result['point_id']);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM pedido_localizacoes WHERE pedido_id = 601 AND client_point_id = 'point-race'")->fetchColumn();
        $this->assertSame(1, $count);
    }
}
