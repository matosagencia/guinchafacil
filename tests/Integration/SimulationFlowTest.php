<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// L1.10: autocontido — não depender de outro teste ter carregado config.php
// antes na mesma execução (mesmo bug corrigido em HealthServiceTest.php).
require_once dirname(__DIR__, 2) . '/config.php';
require_once __DIR__ . '/../../src/Services/SimulationService.php';

final class SimulationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['simulation_steps','simulation_runs','chat_mensagens','avaliacoes','pagamentos','pedidos','veiculos','guinchos','usuarios','logs_webhook','app_logs'] as $t) {
            try { $pdo->exec("DELETE FROM {$t}"); } catch (Throwable) {}
        }
        $pdo->exec("DELETE FROM configuracoes");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma','0.15')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min','5')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('raio_inicial_km','10')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_fixa','15')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_por_km','3.5')");

        // cpf é NOT NULL UNIQUE; deixar em branco preenche '' e colide com qualquer
        // usuário que o SimulationService crie durante o dry-run (Pacote L1.3).
        $pdo->exec("INSERT INTO usuarios (id,nome,email,senha_hash,telefone,cpf,tipo,ativo) VALUES (1,'Cliente Teste','cliente.sim@test.com','hash','11988880004','40404040404','cliente',1)");
        $pdo->exec("INSERT INTO usuarios (id,nome,email,senha_hash,telefone,cpf,tipo,ativo) VALUES (2,'Operador Guincho','guincho.sim@test.com','hash','11988880005','50505050505','guincho',1)");
        $pdo->exec("INSERT INTO veiculos (id,usuario_id,placa,marca,modelo,cor) VALUES (1,1,'ABC1234','VW','Gol','Branco')");
        $pdo->exec("INSERT INTO guinchos (id,usuario_id,chave_pix,chave_pix_tipo,aprovado,disponivel,lat_atual,lng_atual) VALUES (1,2,'pix@test.com','email',1,1,-23.55,-46.63)");
    }

    /**
     * L1.10: testSimulacaoComFalhaCriticaRegistraOkFalso() desativa
     * cliente.sim@test.com para forçar uma falha real — mas como os testes
     * de Integration rodam contra o MySQL real (não uma transação SQLite
     * revertida), esse UPDATE ficava vazando pro banco de fora do PHPUnit
     * sempre que este era o último teste da classe a rodar (foi assim que
     * a conta usada manualmente para QA/Playwright ficou com ativo=0 e o
     * login parou de funcionar). tearDown() garante que a próxima execução
     * — PHPUnit ou manual — sempre encontra o cliente reativado.
     */
    protected function tearDown(): void
    {
        try {
            getPDO()->exec("UPDATE usuarios SET ativo = 1 WHERE email = 'cliente.sim@test.com'");
        } catch (Throwable) {
            // Ambiente sem esta tabela/coluna (ex.: SQLite de outro contexto) — ignora.
        }
    }

    /**
     * Antes este teste só checava "existe pelo menos 1 step" — passaria
     * mesmo se o fluxo abortasse na fase 1 com erro. L1.10 exige que o
     * simulador seja um "simulado oficial ponta a ponta" de verdade, então
     * o teste agora valida que as 11 fases (cliente → veículo → pedido →
     * pagamento → guincho → aceite → status → pix → chat → avaliação →
     * idempotência de webhook) rodam e terminam todas com ok=true.
     */
    public function testSimulacaoDryRunGeraRunESteps(): void
    {
        $service = new SimulationService(true);
        $result = $service->run();

        $this->assertArrayHasKey('run_id', $result);
        $this->assertNotEmpty($result['run_id']);
        $this->assertTrue(
            $result['ok'],
            'Simulação ponta a ponta deveria concluir sem erros. Relatório: ' . json_encode($result['relatorio'], JSON_UNESCAPED_UNICODE)
        );
        $this->assertNotNull($result['pedido_id'], 'Simulação deveria ter criado um pedido real.');

        $fasesEsperadas = [
            'cliente', 'veiculo', 'pedido', 'pagamento', 'guincho',
            'aceite', 'status', 'pix', 'chat', 'avaliacao', 'webhook_idempotencia',
        ];
        $this->assertGreaterThanOrEqual(
            count($fasesEsperadas),
            count($result['relatorio']),
            'Relatório da simulação deveria conter pelo menos uma entrada por fase esperada.'
        );

        foreach ($result['relatorio'] as $step) {
            $this->assertArrayHasKey('fase', $step);
            $this->assertArrayHasKey('ok', $step);
            $this->assertTrue(
                $step['ok'],
                "Fase '{$step['fase']}' da simulação falhou: " . ($step['msg'] ?? '(sem mensagem)')
            );
        }

        $run = SimulationRun::buscarPorRunId($result['run_id']);
        $this->assertNotFalse($run);
        $this->assertSame($result['run_id'], $run['run_id']);
        $this->assertSame('completed', $run['status'], 'simulation_runs.status deveria ser "completed" quando a simulação termina sem erros.');

        $steps = SimulationStep::listarPorRun($result['run_id']);
        $this->assertGreaterThanOrEqual(count($fasesEsperadas), count($steps));
    }

    public function testSimulacaoComFalhaCriticaRegistraOkFalso(): void
    {
        // Remove o cliente ativo para forçar a fase1Cliente() a chamar
        // failStep() (fase 1 exige `SELECT ... WHERE tipo='cliente' AND
        // ativo=1`), provando que o simulador não mascara uma falha real
        // como sucesso — diferente de comissao_plataforma, que tem fallback
        // padrão (0.15) e não serviria pra este teste.
        $pdo = getPDO();
        $pdo->exec("UPDATE usuarios SET ativo = 0 WHERE tipo = 'cliente'");

        $service = new SimulationService(true);
        $result = $service->run();

        $this->assertFalse($result['ok'], 'Simulação sem cliente ativo não deveria reportar sucesso.');
        $this->assertNotEmpty($result['relatorio']);

        $falhas = array_filter($result['relatorio'], fn ($step) => $step['ok'] === false);
        $this->assertNotEmpty($falhas, 'Deveria haver ao menos uma fase marcada como falha.');
    }
}
