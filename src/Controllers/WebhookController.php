<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/WebhookController.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Models/Pagamento.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Services/RankingService.php';
require_once __DIR__ . '/../Services/NotificacaoService.php';
require_once __DIR__ . '/../Services/Logger.php';
require_once __DIR__ . '/../Services/AuditTrailService.php';

/** Recebe e processa webhooks de pagamento */
class WebhookController
{
    // ─── Hooks protegidos para testabilidade ─────────────────────────────────

    /** Lê o corpo bruto da requisição HTTP. */
    protected function lerInput(): string
    {
        return (string)file_get_contents('php://input');
    }

    /**
     * Executa um GET via cURL — substituível em testes via subclasse.
     * @return array{body:string, code:int, error:string}
     */
    protected function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['body' => (string)$resp, 'code' => $code, 'error' => $err];
    }

    /** Emite código HTTP e encerra — substituível em testes via subclasse. */
    protected function respond(int $code, string $body = ''): never
    {
        http_response_code($code);
        if ($body !== '') echo $body;
        exit;
    }

    // ─── MercadoPago ─────────────────────────────────────────────────────────

    public function mercadoPago(): void
    {
        $body   = $this->lerInput();
        $xSig   = $_SERVER['HTTP_X_SIGNATURE']  ?? '';
        $reqId  = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        $dataId = $_GET['data.id'] ?? '';

        // Parse X-Signature: ts=...,v1=...
        $ts   = null;
        $hash = null;
        foreach (explode(',', $xSig) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                if (trim($kv[0]) === 'ts') $ts   = trim($kv[1]);
                if (trim($kv[0]) === 'v1') $hash = trim($kv[1]);
            }
        }

        // §MP-SIG-01: valida HMAC-SHA256 com manifesto id:{id};request-id:{rid};ts:{ts};
        $manifest = "id:{$dataId};request-id:{$reqId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, (string)MP_WEBHOOK_SECRET);
        if (!$hash || !hash_equals($expected, $hash)) {
            $this->log('mercadopago', 'signature_invalid', $body, 401);
            Logger::log(Logger::LEVEL_WARN, 'WebhookController', 'mercadoPago', 'webhook',
                'Assinatura inválida no webhook MercadoPago.', ['sig_recv' => substr($xSig, 0, 16)]);
            $this->respond(401);
        }

        $data = json_decode($body, true);
        $this->log('mercadopago', (string)($data['action'] ?? 'notification'), $body, 200);

        Logger::log(Logger::LEVEL_INFO, 'WebhookController', 'mercadoPago', 'webhook',
            "Webhook MP recebido: action=" . ($data['action'] ?? ''),
            ['action' => $data['action'] ?? null, 'data_id' => $data['data']['id'] ?? null]
        );

        if (($data['action'] ?? '') === 'payment.updated' && isset($data['data']['id'])) {
            $this->buscarEProcessarMp((string)$data['data']['id'], $body);
        }
        $this->respond(200, 'OK');
    }

    private function buscarEProcessarMp(string $mpId, string $body = ''): void
    {
        // §IDEMPOTENCIA-01: bloqueia reprocessamento de pagamento já aprovado
        if (Pagamento::buscarPorIdExterno('mp_' . $mpId)) {
            $this->log('mercadopago', 'duplicate_ignored', $body, 200);
            Logger::log(Logger::LEVEL_INFO, 'WebhookController', 'buscarEProcessarMp', 'webhook',
                "Webhook MP ignorado — mp_{$mpId} já processado (idempotência).",
                ['mp_id' => $mpId]
            );
            return;
        }

        $result = $this->httpGet(
            "https://api.mercadopago.com/v1/payments/{$mpId}",
            ['Authorization: Bearer ' . MP_ACCESS_TOKEN]
        );

        if ($result['error'] !== '') {
            Logger::log(Logger::LEVEL_ERROR, 'WebhookController', 'buscarEProcessarMp', 'webhook',
                "Erro cURL ao consultar API MP: {$result['error']}", ['mp_id' => $mpId]);
            return;
        }

        $payment = json_decode($result['body'], true);
        if (!is_array($payment)) {
            Logger::log(Logger::LEVEL_ERROR, 'WebhookController', 'buscarEProcessarMp', 'webhook',
                'Resposta inválida da API MP (não é JSON).', ['mp_id' => $mpId]);
            return;
        }

        if (($payment['status'] ?? '') === 'approved') {
            $pedidoId = (int)($payment['external_reference'] ?? 0);
            if ($pedidoId) {
                $this->aprovarPagamento($pedidoId, 'mp_' . $mpId, (string)json_encode($payment));
            }
        }
    }

    // ─── PagSeguro ────────────────────────────────────────────────────────────

    public function pagSeguro(): void
    {
        $body = $this->lerInput();
        parse_str($body, $post);
        $this->log('pagseguro', (string)($post['notificationType'] ?? 'notification'), $body, 200);

        Logger::log(Logger::LEVEL_INFO, 'WebhookController', 'pagSeguro', 'webhook',
            "Webhook PagSeguro recebido: type=" . ($post['notificationType'] ?? ''),
            ['type' => $post['notificationType'] ?? null]
        );

        if (($post['notificationType'] ?? '') === 'transaction' && isset($post['notificationCode'])) {
            $this->buscarEProcessarPs((string)$post['notificationCode']);
        }
        $this->respond(200, 'OK');
    }

    private function buscarEProcessarPs(string $code): void
    {
        $url = PS_BASE_URL . '/v3/transactions/notifications/'
             . $code . '?email=' . urlencode((string)PS_EMAIL) . '&token=' . PS_TOKEN;

        $result = $this->httpGet($url);

        if ($result['error'] !== '') {
            Logger::log(Logger::LEVEL_ERROR, 'WebhookController', 'buscarEProcessarPs', 'webhook',
                "Erro cURL ao consultar API PS: {$result['error']}", ['code' => $code]);
            return;
        }

        // §PS-XML-01: parse com SimpleXML — nunca regex em XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($result['body']);
        if ($xml === false) {
            Logger::log(Logger::LEVEL_ERROR, 'WebhookController', 'buscarEProcessarPs', 'webhook',
                'Resposta PagSeguro não é XML válido.', ['code' => $code, 'body_excerpt' => substr($result['body'], 0, 200)]);
            return;
        }

        $psStatus = (string)($xml->status ?? '');
        if ($psStatus === '3') { // Status 3 = pago no PagSeguro
            $pedidoId  = (int)($xml->reference ?? 0);
            $txCode    = (string)($xml->code ?? '');
            $idExterno = 'ps_' . $txCode;

            // §IDEMPOTENCIA-01: usa o código de transação (não o de notificação) como chave
            if (Pagamento::buscarPorIdExterno($idExterno)) {
                Logger::log(Logger::LEVEL_INFO, 'WebhookController', 'buscarEProcessarPs', 'webhook',
                    "Webhook PS ignorado — {$idExterno} já processado (idempotência).",
                    ['notification_code' => $code, 'tx_code' => $txCode]
                );
                return;
            }

            if ($pedidoId) {
                $this->aprovarPagamento($pedidoId, $idExterno, $result['body']);
            }
        }
    }

    // ─── Aprovação ────────────────────────────────────────────────────────────

    private function aprovarPagamento(int $pedidoId, string $idExterno, string $payload): void
    {
        // Busca campos mínimos direto da tabela (evita JOINs que diferem entre MySQL e SQLite)
        try {
            $stmt = getPDO()->prepare(
                "SELECT status, custo_estimado, cliente_nome, cliente_email FROM pedidos WHERE id = ?"
            );
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            Logger::log(Logger::LEVEL_ERROR, 'WebhookController', 'aprovarPagamento', 'webhook',
                "Erro ao buscar pedido {$pedidoId}: " . $e->getMessage(),
                ['pedido_id' => $pedidoId]
            );
            return;
        }

        if (!$pedido || $pedido['status'] !== 'aguardando_pagamento') {
            Logger::log(Logger::LEVEL_INFO, 'WebhookController', 'aprovarPagamento', 'webhook',
                "Pedido {$pedidoId} não está aguardando pagamento — ignorado.",
                ['pedido_id' => $pedidoId, 'status' => $pedido['status'] ?? 'não encontrado']
            );
            return;
        }

        $pag = Pagamento::buscarPorPedido($pedidoId);
        if (!$pag) {
            Logger::log(Logger::LEVEL_ERROR, 'WebhookController', 'aprovarPagamento', 'webhook',
                "Pagamento não encontrado para pedido {$pedidoId}.",
                ['pedido_id' => $pedidoId, 'id_externo' => $idExterno]
            );
            return;
        }

        // §IDEMPOTENCIA-02: pagamento do pedido já aprovado por outro gateway/evento
        if (($pag['status'] ?? '') === 'aprovado') {
            Logger::log(Logger::LEVEL_INFO, 'WebhookController', 'aprovarPagamento', 'webhook',
                "Pedido {$pedidoId}: pagamento já aprovado (id_externo={$pag['id_externo']}) — ignorando {$idExterno}.",
                ['pedido_id' => $pedidoId, 'id_externo_existente' => $pag['id_externo'] ?? null]
            );
            return;
        }

        $cfg      = Configuracao::getAll();
        $raw      = isset($cfg['comissao_plataforma']) ? (float)$cfg['comissao_plataforma'] : 0.15;
        $comissao = $raw > 1 ? $raw / 100 : $raw;
        $total    = (float)$pedido['custo_estimado'];
        $plat     = round($total * $comissao, 2);
        $guincho  = round($total - $plat, 2);

        Pagamento::aprovar((int)$pag['id'], $idExterno, $payload);
        Pagamento::atualizarSplit((int)$pag['id'], $guincho, $plat);
        Pedido::atualizarStatus($pedidoId, 'aguardando_guincho');

        $expMin      = (int)($cfg['tempo_expiracao_min'] ?? 5);
        $expiracao   = date('Y-m-d H:i:s', strtotime("+{$expMin} minutes"));
        $raioInicial = (int)($cfg['raio_inicial_km'] ?? 10);
        Pedido::definirExpiracao($pedidoId, $expiracao, $raioInicial);

        AuditTrailService::evento('pagamento_aprovado', 'WebhookController', 'aprovarPagamento', [
            'pedido_id'  => $pedidoId,
            'id_externo' => $idExterno,
            'valor'      => $total,
        ]);

        Logger::log(Logger::LEVEL_INFO, 'WebhookController', 'aprovarPagamento', 'webhook',
            "Pagamento {$idExterno} aprovado para pedido {$pedidoId}. Valor: R${$total}.",
            ['pedido_id' => $pedidoId, 'id_externo' => $idExterno]
        );

        try {
            $cliente = ['nome' => $pedido['cliente_nome'], 'email' => $pedido['cliente_email']];
            NotificacaoService::pedidoConfirmado($pedido, $cliente);
        } catch (Throwable $eNotif) {
            Logger::log(Logger::LEVEL_ERROR, 'WebhookController', 'aprovarPagamento', 'webhook',
                "Falha ao notificar cliente: " . $eNotif->getMessage(),
                ['pedido_id' => $pedidoId]
            );
        }
    }

    // ─── Log webhook ─────────────────────────────────────────────────────────

    private function log(string $fonte, string $evento, string $payload, int $status): void
    {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO logs_webhook (fonte, evento, payload, status_http, criado_em)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$fonte, $evento, $payload, $status]);
        } catch (Throwable $e) {
            Logger::log(Logger::LEVEL_ERROR, 'WebhookController', 'log', 'webhook',
                'Falha ao inserir log_webhook: ' . $e->getMessage());
        }
    }
}
