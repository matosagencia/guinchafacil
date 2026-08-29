<?php
// File: guinchafacil/src/Services/PixService.php

/**
 * Serviço de transferência Pix via MercadoPago (§4.3)
 * Regras: repasse só após status=concluido, registrar id_transacao_pix,
 * em falha notificar admin e entrar em fila de reprocessamento.
 */
class PixService
{
    private const MP_PAYMENTS_URL = 'https://api.mercadopago.com/v1/payments';

    /**
     * Mapeia o tipo de chave Pix armazenado no banco para o valor esperado pela API do MercadoPago.
     * DB: cpf | email | telefone | aleatoria
     * API: CPF | EMAIL | PHONE   | EVP
     */
    private static function mapearTipoChave(string $tipo): string
    {
        return match (strtolower(trim($tipo))) {
            'cpf'       => 'CPF',
            'email'     => 'EMAIL',
            'telefone'  => 'PHONE',
            'aleatoria' => 'EVP',
            default     => strtoupper($tipo), // passthrough para valores já mapeados (CPF, PHONE, EVP…)
        };
    }

    /**
     * Transfere valor ao guincheiro via Pix.
     *
     * @return array ['sucesso' => bool, 'id_transacao' => string|null, 'erro' => string|null]
     */
    public static function transferir(int $pedidoId, float $valor, string $chavePix, string $chaveTipo): array
    {
        if (empty($chavePix)) {
            $msg = "Pedido {$pedidoId}: chave Pix do guincheiro não cadastrada.";
            error_log("[PixService] {$msg}");
            return ['sucesso' => false, 'id_transacao' => null, 'erro' => $msg];
        }

        if (static::deveValidarGuardFinanceiro()) {
            $guard = self::validarGuardFinanceiroTransferencia($pedidoId);
            if (!$guard['ok']) {
                error_log("[PixService] {$guard['erro']}");
                return ['sucesso' => false, 'id_transacao' => null, 'erro' => $guard['erro']];
            }
        }

        $body = json_encode([
            'transaction_amount' => round($valor, 2),
            'payment_method_id'  => 'pix',
            'operation_type'     => 'money_transfer',
            'description'        => "Repasse pedido #{$pedidoId}",
            'external_reference' => (string)$pedidoId, // reconciliação financeira por pedido
            'payer' => [
                'email' => defined('ADMIN_EMAIL') ? ADMIN_EMAIL : (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : ''),
            ],
            'receiver' => [
                'key'      => $chavePix,
                'key_type' => self::mapearTipoChave($chaveTipo),
            ],
        ]);

        $result = static::httpPost(self::MP_PAYMENTS_URL, [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'Content-Type: application/json',
            'X-Idempotency-Key: pix-pedido-' . $pedidoId,
        ], $body);
        $resp     = $result['body'] ?? '';
        $httpCode = (int)($result['code'] ?? 0);
        $curlErr  = (string)($result['error'] ?? '');

        if ($curlErr) {
            $msg = "Pedido {$pedidoId}: erro cURL — {$curlErr}";
            error_log("[PixService] {$msg}");
            return ['sucesso' => false, 'id_transacao' => null, 'erro' => $msg];
        }

        $data = json_decode($resp, true);

        if ($httpCode === 201 && !empty($data['id'])) {
            error_log("[PixService] Pedido {$pedidoId}: Pix aprovado. id_transacao={$data['id']}");
            return ['sucesso' => true, 'id_transacao' => (string)$data['id'], 'erro' => null];
        }

        $erro = $data['message'] ?? $data['error'] ?? "HTTP {$httpCode}";
        $msg  = "Pedido {$pedidoId}: Pix recusado — {$erro}";
        error_log("[PixService] {$msg} | payload=" . $resp);
        return ['sucesso' => false, 'id_transacao' => null, 'erro' => $msg];
    }

    /**
     * Reprocessa transferência Pix com status_pix = 'falha' para um pedido.
     *
     * @return array ['sucesso' => bool, 'erro' => string|null]
     */
    public static function reprocessar(int $pedidoId): array
    {
        $pdo = getPDO();

        // Busca pagamento com falha
        $stmt = $pdo->prepare(
            "SELECT p.*, pd.guincho_id
             FROM pagamentos p
             JOIN pedidos pd ON pd.id = p.pedido_id
             WHERE p.pedido_id = ? AND p.status_pix = 'falha'
             LIMIT 1"
        );
        $stmt->execute([$pedidoId]);
        $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pagamento) {
            $msg = "Pedido {$pedidoId}: nenhum pagamento com status_pix=falha encontrado.";
            error_log("[PixService::reprocessar] {$msg}");
            return ['sucesso' => false, 'erro' => $msg];
        }

        // Busca chave Pix do guincheiro — usa Guincho::buscarPorId() para obter valor descriptografado (§5.4)
        require_once __DIR__ . '/../Models/Guincho.php';
        $guincho = Guincho::buscarPorId((int)$pagamento['guincho_id']);

        if (!$guincho || empty($guincho['chave_pix'])) {
            $msg = "Pedido {$pedidoId}: chave Pix do guincheiro não encontrada (guincho_id={$pagamento['guincho_id']}).";
            error_log("[PixService::reprocessar] {$msg}");
            return ['sucesso' => false, 'erro' => $msg];
        }

        // Marca como processando para evitar duplo disparo
        $pdo->prepare("UPDATE pagamentos SET status_pix = 'processando' WHERE pedido_id = ? AND status_pix = 'falha'")
            ->execute([$pedidoId]);

        $resultado = static::transferir(
            $pedidoId,
            (float)$pagamento['valor_guincho'],
            $guincho['chave_pix'],       // já descriptografado pelo Guincho model
            $guincho['chave_pix_tipo'] ?? 'aleatoria'
        );

        if ($resultado['sucesso']) {
            Pagamento::confirmarRepassePix((int)$pagamento['id'], (string)$resultado['id_transacao']);
        } else {
            $pdo->prepare("UPDATE pagamentos SET status_pix = 'falha' WHERE pedido_id = ?")
                ->execute([$pedidoId]);
            error_log("[PixService::reprocessar] Pedido {$pedidoId}: retentativa falhou — " . $resultado['erro']);
        }

        return $resultado;
    }

    protected static function deveValidarGuardFinanceiro(): bool
    {
        return true;
    }

    private static function validarGuardFinanceiroTransferencia(int $pedidoId): array
    {
        if (!class_exists('Pagamento')) {
            require_once __DIR__ . '/../Models/Pagamento.php';
        }

        $qtdAprovados = Pagamento::contarAprovadosPorPedido($pedidoId);
        if ($qtdAprovados !== 1) {
            return [
                'ok' => false,
                'erro' => "PIX-GUARD-01: transferencia do pedido {$pedidoId} exige exatamente 1 pagamento aprovado; encontrados {$qtdAprovados}.",
            ];
        }

        $pagamento = Pagamento::buscarAprovadoPorPedido($pedidoId);
        if (!$pagamento) {
            return [
                'ok' => false,
                'erro' => "PIX-GUARD-01: pagamento aprovado do pedido {$pedidoId} nao encontrado.",
            ];
        }

        if ((int)($pagamento['pago_guincho'] ?? 0) !== 0) {
            return [
                'ok' => false,
                'erro' => "PIX-GUARD-02: transferencia do pedido {$pedidoId} bloqueada; guincho ja foi pago.",
            ];
        }

        if (($pagamento['status_pix'] ?? '') !== 'processando') {
            return [
                'ok' => false,
                'erro' => "PIX-GUARD-03: transferencia do pedido {$pedidoId} exige status_pix=processando.",
            ];
        }

        return ['ok' => true, 'erro' => null];
    }

    protected static function httpPost(string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($ca = ca_bundle_path()) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        return ['body' => (string)$resp, 'code' => (int)$httpCode, 'error' => (string)$curlErr];
    }
}
