<?php

declare(strict_types=1);

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/PixService.php';
require_once __DIR__ . '/ChatService.php';
require_once __DIR__ . '/AvaliacaoService.php';
require_once __DIR__ . '/AuditTrailService.php';
require_once __DIR__ . '/../Models/SimulationRun.php';
require_once __DIR__ . '/../Models/SimulationStep.php';
require_once __DIR__ . '/../Models/Pagamento.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Guincho.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../DTO/PedidoTransitionRequest.php';
require_once __DIR__ . '/Pedido/PedidoTransitionService.php';

/**
 * §LIVE-SIM-01 — Exceção para encerrar simulação em falha crítica de fase.
 */
class SimulationStopException extends RuntimeException {}

/**
 * §LIVE-SIM-01 — Serviço que executa o fluxo completo do sistema ponta-a-ponta.
 *
 * Cada execução recebe um run_id único, persiste fases em simulation_steps
 * e o resultado em simulation_runs, e loga tudo via Logger.
 */
class SimulationService
{
    private string $runId;
    private bool   $dryRun;
    private array  $steps  = [];
    private int    $errors = 0;
    /**
     * L1.10: relógio simulado (epoch ms) para os pontos POR ingeridos pelo
     * simulador. Não pode ser "agora" em todas as chamadas: origem→destino
     * ingeridos com poucos milissegundos de diferença gerariam uma
     * velocidade absurda e seriam rejeitados por POR-VAL-006 (excesso de
     * velocidade / teleporte), e um gap grande demais cairia em
     * POR-VAL-007 (por_max_gap_seconds, padrão 180s). Avançamos o relógio
     * simulado em incrementos realistas (120s) entre pontos.
     */
    private ?int $porClockMs = null;

    public function __construct(bool $dryRun = true)
    {
        $this->dryRun = $dryRun;
        $this->runId  = bin2hex(random_bytes(16)); // 32 hex chars — compatível com router
    }

    public function getRunId(): string { return $this->runId; }

    /** Executa o fluxo completo e retorna o relatório. */
    public function run(): array
    {
        $startMs  = (int)(microtime(true) * 1000);
        $pedidoId = null;

        SimulationRun::criar($this->runId, $this->dryRun);

        try {
            $cliente      = $this->fase1Cliente();
            $veiculo      = $this->fase2Veiculo($cliente);
            $fase3        = $this->fase3CriarPedido($cliente, $veiculo);
            $pedidoId     = $fase3['pedido_id'];
            $this->fase4AprovarPagamento($pedidoId, $fase3['custo']);
            $guincho      = $this->fase5Guincho();
            $this->fase6AceitarPedido($pedidoId, $guincho);
            $this->fase7AvancarStatus($pedidoId, $guincho);
            $this->fase8Pix($pedidoId, $guincho);
            $this->fase9Chat($pedidoId, (int)$cliente['id'], (int)$guincho['usuario_id']);
            $this->fase10Avaliacao($pedidoId, (int)$cliente['id'], (int)$guincho['id']);
            $this->fase11WebhookIdempotencia();
        } catch (SimulationStopException) {
            // Fase crítica falhou — já registrado via step/failStep
        } catch (Throwable $e) {
            Logger::log(Logger::LEVEL_ERROR, 'SimulationService', 'run', 'simulacao',
                "Exceção inesperada: " . $e->getMessage(),
                ['run_id' => $this->runId, 'type' => get_class($e), 'line' => $e->getLine()]
            );
            $this->steps[] = ['fase' => 'excecao', 'ok' => false, 'msg' => $e->getMessage()];
            $this->errors++;
        }

        $duracaoMs = (int)(microtime(true) * 1000) - $startMs;
        $ok        = $this->errors === 0;

        SimulationRun::finalizar(
            $this->runId, $ok, $pedidoId,
            count($this->steps), $this->errors, $duracaoMs
        );

        return [
            'ok'         => $ok,
            'run_id'     => $this->runId,
            'pedido_id'  => $pedidoId,
            'relatorio'  => $this->steps,
            'duracao_ms' => $duracaoMs,
            'dry_run'    => $this->dryRun,
        ];
    }

    // ── Fases ──────────────────────────────────────────────────────────────────

    private function fase1Cliente(): array
    {
        $stmt = getPDO()->query("SELECT id, nome, email FROM usuarios WHERE tipo='cliente' AND ativo=1 LIMIT 1");
        $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        if (!$row) {
            $this->failStep('1-cliente', 'Nenhum cliente ativo encontrado.');
        }
        $this->step('1-cliente', true, "Cliente: {$row['nome']} (id={$row['id']})");
        return $row;
    }

    private function fase2Veiculo(array $cliente): array
    {
        $stmt = getPDO()->prepare("SELECT id FROM veiculos WHERE usuario_id=? LIMIT 1");
        $stmt->execute([$cliente['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->failStep('2-veiculo', "Cliente {$cliente['id']} sem veículo cadastrado.");
        }
        $this->step('2-veiculo', true, "Veículo id={$row['id']}");
        return $row;
    }

    /** @return array{pedido_id: int, custo: float} */
    private function fase3CriarPedido(array $cliente, array $veiculo): array
    {
        $cfg       = Configuracao::getAll();
        $distancia = 10.0;
        require_once __DIR__ . '/TarifaService.php';
        $custo     = TarifaService::calcular($distancia);

        $pedidoId = Pedido::criar([
            'cliente_id'         => $cliente['id'],
            'veiculo_id'         => $veiculo['id'],
            'tipo_problema'      => 'outro',
            'descricao_problema' => '[SIMULACAO] run_id=' . $this->runId,
            'lat_origem'         => -23.5505,
            'lng_origem'         => -46.6333,
            'endereco_origem'    => 'Av. Paulista, 1000, São Paulo',
            'lat_destino'        => -23.5615,
            'lng_destino'        => -46.6550,
            'endereco_destino'   => 'Rua Augusta, 500, São Paulo',
            'distancia_km'       => $distancia,
            'custo_estimado'     => $custo,
            'raio_atual_km'      => 50,
            'score_minimo_atual' => 0.0,
        ]);

        if (!$pedidoId) {
            $this->failStep('3-criar-pedido', 'Falha ao criar pedido no banco.');
        }
        $this->step('3-criar-pedido', true, "Pedido id={$pedidoId}, custo=R${$custo}");
        return ['pedido_id' => (int)$pedidoId, 'custo' => $custo];
    }

    private function fase4AprovarPagamento(int $pedidoId, float $custo): void
    {
        $cfg        = Configuracao::getAll();
        $comissao   = (float)($cfg['comissao_plataforma'] ?? 0.15);
        $valGuincho = round($custo * (1 - $comissao), 2);
        $valPlat    = round($custo * $comissao, 2);

        $pagId = Pagamento::criar($pedidoId, 'simulacao', $custo, 0, 0);
        if (!$pagId) {
            $this->failStep('4-pagamento', 'Falha ao inserir registro de pagamento no banco.');
        }
        $pag = Pagamento::buscarPorPedido($pedidoId);

        if (!$pag) {
            $this->failStep('4-pagamento', 'Registro de pagamento não encontrado após criação.');
        }

        Pagamento::aprovar((int)$pag['id'], 'sim_' . $this->runId, json_encode(['simulacao' => true, 'run_id' => $this->runId]));
        Pagamento::atualizarSplit((int)$pag['id'], $valGuincho, $valPlat);
        $result = PedidoTransitionService::transition(new PedidoTransitionRequest(
            'system',
            0,
            $pedidoId,
            'aguardando_guincho'
        ));
        if (!$result->ok) {
            $this->failStep('4-pagamento', (string)$result->error);
        }
        Pedido::definirExpiracao($pedidoId, date('Y-m-d H:i:s', strtotime('+5 minutes')), 50);

        $this->step('4-pagamento', true, "Aprovado. split: guincho=R${$valGuincho} plataforma=R${$valPlat}");
    }

    private function fase5Guincho(): array
    {
        $stmt = getPDO()->query(
            "SELECT g.*, u.nome AS op_nome, u.email AS op_email
             FROM guinchos g JOIN usuarios u ON u.id=g.usuario_id
             WHERE g.aprovado=1 AND u.ativo=1 LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        if (!$row) {
            $this->failStep('5-guincho', 'Nenhum guincho aprovado encontrado.');
        }
        $this->step('5-guincho', true, "Guincho: {$row['op_nome']} (id={$row['id']})");
        return $row;
    }

    private function fase6AceitarPedido(int $pedidoId, array $guincho): void
    {
        $result = PedidoTransitionService::acceptByGuincho($pedidoId, (int)$guincho['id'], (int)$guincho['usuario_id']);
        if (!$result->ok) {
            $this->failStep('6-aceitar', (string)$result->error);
        }

        $this->step('6-aceitar', true, "Guincho {$guincho['id']} atribuído. Status → a_caminho");
    }

    /**
     * L1.10: o simulador se anuncia como "ponta a ponta", mas nunca
     * alimentava POR (Proof-of-Road) — desde o Pacote L1.4,
     * PedidoTransitionService exige um ponto GPS válido e dentro da
     * geofence de origem/destino antes de aceitar no_local/em_reboque/
     * concluido (§POR-GEOFENCE). Sem isso, a fase 7 sempre falhava
     * silenciosamente e só não quebrava porque o teste antigo nunca
     * checava ok=true por fase. Agora ingerimos um ponto GPS simulado na
     * origem antes de no_local/em_reboque e no destino antes de concluido.
     */
    private function fase7AvancarStatus(int $pedidoId, array $guincho): void
    {
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido) {
            $this->failStep('7-status-no_local', 'Pedido não encontrado para avançar status.');
        }

        // Um único ponto válido na origem já satisfaz a precondição de
        // no_local E em_reboque (ambas checam GeofenceService::isNearOrigin
        // contra o "último ponto válido" da snapshot POR, não um ponto por
        // fase) — ingerir um segundo ponto idêntico logo em seguida seria
        // rejeitado por POR-VAL-008 (distância ~0 em menos de 15s, tratado
        // como duplicata/replay). Só ingerimos de novo para 'concluido',
        // que exige proximidade do destino (coordenadas bem diferentes).
        $sequence = 1;
        foreach (['no_local', 'em_reboque', 'concluido'] as $novoStatus) {
            $context = [];
            if ($novoStatus === 'no_local') {
                $sequence = $this->ingestSimulationPoint(
                    $pedidoId, $guincho, (float)$pedido['lat_origem'], (float)$pedido['lng_origem'], $sequence, "7-por-{$novoStatus}"
                );
            } elseif ($novoStatus === 'concluido') {
                $sequence = $this->ingestSimulationPoint(
                    $pedidoId, $guincho, (float)$pedido['lat_destino'], (float)$pedido['lng_destino'], $sequence, "7-por-{$novoStatus}"
                );
            }

            if ($novoStatus === 'em_reboque') {
                $context['foto_plataforma'] = 'simulacao-coleta.jpg';
            } elseif ($novoStatus === 'concluido') {
                $context['foto_destino'] = 'simulacao-entrega.jpg';
            }
            $result = PedidoTransitionService::transition(new PedidoTransitionRequest(
                'system',
                0,
                $pedidoId,
                $novoStatus,
                null,
                $context
            ));
            if (!$result->ok) {
                $this->failStep("7-status-{$novoStatus}", (string)$result->error);
            }
            $this->step("7-status-{$novoStatus}", true, "Status → {$novoStatus}");
        }
    }

    private function ingestSimulationPoint(int $pedidoId, array $guincho, float $lat, float $lng, int $sequence, string $fase): int
    {
        require_once __DIR__ . '/POR/ProofOfRoadService.php';
        $result = ProofOfRoadService::ingestPoint($pedidoId, (int)$guincho['id'], (int)$guincho['usuario_id'], [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy_m' => 8.0,
            'sequence' => $sequence,
            'client_point_id' => 'sim_' . $this->runId . '_' . $sequence,
            // LocationValidationService espera epoch em milissegundos
            // (int)$payload['device_timestamp'], não uma string ISO-8601,
            // e avança em incrementos realistas (ver $porClockMs).
            'device_timestamp' => $this->nextPorClockMs(),
        ]);

        if (empty($result['ok']) || empty($result['accepted'])) {
            $this->failStep($fase, 'Falha ao ingerir ponto POR simulado: ' . (string)($result['erro'] ?? 'ponto rejeitado.'));
        }

        $this->step($fase, true, "Ponto POR aceito em ({$lat}, {$lng}), sequence={$sequence}");
        return $sequence + 1;
    }

    private function nextPorClockMs(): int
    {
        if ($this->porClockMs === null) {
            $this->porClockMs = (int)round(microtime(true) * 1000);
        } else {
            $this->porClockMs += 120_000; // +120s por ponto — dentro de por_max_gap_seconds (180s) e abaixo de por_max_speed_kmh para a distância origem→destino simulada.
        }
        return $this->porClockMs;
    }

    private function fase8Pix(int $pedidoId, array $guincho): void
    {
        // §PIX-GUARD-01: único guard para dry-run e real
        $qtdAprovados = Pagamento::contarAprovadosPorPedido($pedidoId);

        if ($qtdAprovados === 0) {
            $this->step('8-pix', false, 'PIX-GUARD-01: sem pagamento aprovado. Repasse bloqueado.');
            return;
        }
        if ($qtdAprovados > 1) {
            $this->step('8-pix', false, "PIX-GUARD-01: {$qtdAprovados} pagamentos aprovados — inconsistência. Repasse bloqueado.");
            return;
        }

        $pagGuard = Pagamento::buscarAprovadoPorPedido($pedidoId);
        if (!$pagGuard) {
            $this->step('8-pix', false, 'Pagamento aprovado não recuperado (erro de BD). Repasse bloqueado.');
            return;
        }

        $pdo = getPDO();

        if ($this->dryRun) {
            $idSintetico = 'dry-' . substr($this->runId, 0, 8) . '-' . time();
            $pdo->prepare(
                "UPDATE pagamentos SET valor_guincho=?, valor_plataforma=?, status_pix='concluido',
                 id_transacao_pix=?, pago_guincho=1, data_pagamento_guincho=NOW() WHERE id=?"
            )->execute([
                $pagGuard['valor_guincho'],
                $pagGuard['valor_plataforma'],
                $idSintetico,
                $pagGuard['id'],
            ]);
            $this->step('8-pix', true, "PIX_DRY_RUN: id_transacao={$idSintetico}");
            return;
        }

        $chavePix  = (string)($guincho['chave_pix']      ?? '');
        $chaveTipo = (string)($guincho['chave_pix_tipo'] ?? 'EVP');

        $pdo->prepare("UPDATE pagamentos SET status_pix='processando' WHERE id=?")->execute([$pagGuard['id']]);

        $pix = PixService::transferir($pedidoId, (float)$pagGuard['valor_guincho'], $chavePix, $chaveTipo);

        if ($pix['sucesso']) {
            $pdo->prepare(
                "UPDATE pagamentos SET status_pix='concluido', id_transacao_pix=?, pago_guincho=1, data_pagamento_guincho=NOW() WHERE id=?"
            )->execute([$pix['id_transacao'], $pagGuard['id']]);
            $this->step('8-pix', true, "Pix aprovado: id_transacao={$pix['id_transacao']}");
        } else {
            $pdo->prepare("UPDATE pagamentos SET status_pix='falha', pago_guincho=0 WHERE id=?")->execute([$pagGuard['id']]);
            $this->step('8-pix', false, "Pix falhou: {$pix['erro']}");
        }
    }

    /**
     * L1.10: o simulador ainda chamava a API antiga do ChatService
     * (ChatService::enviar()/listar() estáticos), que deixou de existir
     * quando o Pacote L1.9 reescreveu ChatService como serviço de
     * instância com sendMessage()/getMessagesByPedido()/marcarLidas() —
     * o simulador nunca foi atualizado junto, e o teste antigo (que só
     * checava "existe >=1 step") nunca teria pegado esse fatal error.
     */
    private function fase9Chat(int $pedidoId, int $clienteId, int $guinchoUsuarioId): void
    {
        $chatService = new ChatService();

        // Envia mensagem como cliente
        $envio = $chatService->sendMessage([
            'pedido_id' => $pedidoId,
            'usuario_id' => $clienteId,
            'mensagem' => '[SIMULACAO] Mensagem de teste do cliente.',
            'idempotency_key' => 'sim_' . $this->runId . '_chat',
        ]);
        if (empty($envio['ok']) || empty($envio['id'])) {
            $this->step('9-chat-envio', false, 'Falha ao enviar mensagem pelo cliente: ' . (string)($envio['erro'] ?? '?'));
            return;
        }
        $msgId = (int)$envio['id'];
        $this->step('9-chat-envio', true, "Mensagem enviada pelo cliente (msg_id={$msgId}).");

        // Lê como guincho e verifica que a mensagem chegou
        $msgs = $chatService->getMessagesByPedido($pedidoId, $msgId - 1);
        $encontrou = false;
        foreach ($msgs as $m) {
            if ((int)$m['id'] === $msgId) { $encontrou = true; break; }
        }
        if (!$encontrou) {
            $this->step('9-chat-leitura', false, "Mensagem id={$msgId} não encontrada na listagem.");
            return;
        }
        $chatService->marcarLidas($pedidoId, $guinchoUsuarioId);
        $this->step('9-chat-leitura', true, "Mensagem lida pelo guincho. Chat funcional.");

        AuditTrailService::evento('chat_simulado', 'SimulationService', 'fase9Chat', [
            'run_id'    => $this->runId,
            'pedido_id' => $pedidoId,
            'msg_id'    => $msgId,
        ]);
    }

    private function fase10Avaliacao(int $pedidoId, int $clienteId, int $guinchoId): void
    {
        $avalId = AvaliacaoService::avaliar($pedidoId, $clienteId, $guinchoId, 5, '[SIMULACAO] Ótimo serviço.');

        if ($avalId === false) {
            $this->step('10-avaliacao', false, 'Falha ao criar avaliação (jaAvaliou ou erro de BD).');
            return;
        }
        $this->step('10-avaliacao', true, "Avaliação criada (id={$avalId}, estrelas=5).");

        // Idempotência: segunda tentativa deve retornar false
        $dup = AvaliacaoService::avaliar($pedidoId, $clienteId, $guinchoId, 3, 'duplicata');
        if ($dup !== false) {
            $this->step('10-avaliacao-idempotencia', false,
                'Idempotência de avaliação FALHOU: segunda avaliação foi aceita.');
        } else {
            $this->step('10-avaliacao-idempotencia', true,
                'Idempotência OK: segunda avaliação corretamente bloqueada.');
        }

        AuditTrailService::evento('avaliacao_simulada', 'SimulationService', 'fase10Avaliacao', [
            'run_id'     => $this->runId,
            'pedido_id'  => $pedidoId,
            'avaliacao'  => $avalId,
        ]);
    }

    private function fase11WebhookIdempotencia(): void
    {
        // Verifica que o mecanismo de idempotência do webhook funciona:
        // o id_externo 'sim_{runId}' já foi gravado na fase 4 → buscarPorIdExterno deve encontrá-lo.
        $idExterno = 'sim_' . $this->runId;
        $existing  = Pagamento::buscarPorIdExterno($idExterno);

        if (!$existing) {
            $this->step('11-webhook-idempotencia', false,
                "Idempotência FALHA: id_externo={$idExterno} não encontrado. Coluna id_externo pode não existir.");
            return;
        }

        if (($existing['status'] ?? '') !== 'aprovado') {
            $this->step('11-webhook-idempotencia', false,
                "Idempotência FALHA: pagamento encontrado mas status=" . ($existing['status'] ?? '?'));
            return;
        }

        $this->step('11-webhook-idempotencia', true,
            "Idempotência OK: id_externo={$idExterno} já aprovado. Re-processamento de webhook seria bloqueado.");
    }

    // ── Helpers internos ───────────────────────────────────────────────────────

    private function step(string $fase, bool $ok, string $msg): void
    {
        $this->steps[] = ['fase' => $fase, 'ok' => $ok, 'msg' => $msg];

        try {
            SimulationStep::registrar($this->runId, $fase, $ok, $msg);
        } catch (Throwable) { /* best effort */ }

        Logger::log(
            $ok ? Logger::LEVEL_INFO : Logger::LEVEL_ERROR,
            'SimulationService', 'run', 'simulacao',
            "[{$this->runId}] {$fase}: {$msg}",
            ['run_id' => $this->runId, 'fase' => $fase, 'ok' => $ok]
        );

        if (!$ok) {
            $this->errors++;
        }
    }

    /** Registra falha e interrompe a simulação. @throws SimulationStopException */
    private function failStep(string $fase, string $msg): never
    {
        $this->step($fase, false, $msg);
        throw new SimulationStopException("{$fase}: {$msg}");
    }
}
