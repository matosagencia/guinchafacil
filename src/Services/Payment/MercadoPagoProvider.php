<?php

declare(strict_types=1);

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once __DIR__ . '/../EstornoService.php';

/**
 * Pacote L1.7 (pendência #33). Delega para EstornoService::estornarMercadoPago(),
 * que já concentra a chamada HTTP real à API do Mercado Pago (remoção do
 * prefixo mp_, montagem da URL de refund, tratamento de resposta). Não
 * duplicamos essa lógica aqui — a extração de providers é sobre dar um
 * ponto de entrada único e testável (`PaymentProviderInterface`), não
 * sobre reescrever a integração já validada em produção.
 */
// §A5: não é mais `final` — precisa ser extensível pra existir uma
// subclasse testável que sobrescreve httpPost() (mesmo padrão de
// PixService, que também não é final), ver MercadoPagoProviderPixTest.
class MercadoPagoProvider implements PaymentProviderInterface
{
    public function nome(): string
    {
        return 'mercadopago';
    }

    public function estornar(string $idExterno, ?float $valorParcial = null): array
    {
        return EstornoService::estornarMercadoPago($idExterno, $valorParcial);
    }

    /**
     * Checkout transparente MercadoPago (Payment Brick): chama POST
     * /v1/payments diretamente com o token/dados que o Brick gerou no
     * browser (cartão) ou o payment_method_id (pix/boleto — 'bolbradesco'),
     * sem preferência/redirect. A resposta síncrona desta chamada JÁ É o
     * objeto de pagamento completo (mesmo formato que o webhook busca
     * depois via GET /v1/payments/{id}) — por isso o controller pode
     * aprovar direto quando status vier 'approved', sem esperar webhook.
     *
     * X-Idempotency-Key: exigido pela API do MP para chamadas POST que
     * criam recursos (evita duplo débito se o cliente reenviar por
     * timeout de rede) — vem pronto em $dados['idempotencyKey'].
     */
    public function criarPagamento(array $dados): array
    {
        $payload = [
            'transaction_amount' => round((float)($dados['valor'] ?? 0), 2),
            'description'        => (string)($dados['descricao'] ?? 'Serviço de Guincho - GuinchaFácil'),
            'payment_method_id'  => (string)($dados['paymentMethodId'] ?? ''),
            'installments'       => max(1, (int)($dados['parcelas'] ?? 1)),
            'external_reference' => (string)($dados['externalReference'] ?? $dados['pedidoId'] ?? ''),
            'notification_url'   => (string)($dados['notificationUrl'] ?? ''),
            'payer' => [
                'email' => (string)($dados['payerEmail'] ?? ''),
                'identification' => [
                    'type'   => (string)($dados['docTipo'] ?? 'CPF'),
                    'number' => preg_replace('/\D+/', '', (string)($dados['docNumero'] ?? '')),
                ],
            ],
        ];

        // Token só existe pra cartão (Card Payment Brick/Payment Brick com
        // método cartão) — Pix e boleto pagam com payment_method_id sozinho.
        if (!empty($dados['token'])) {
            $payload['token'] = (string)$dados['token'];
        }
        if (!empty($dados['issuerId'])) {
            $payload['issuer_id'] = (string)$dados['issuerId'];
        }

        $body = json_encode($payload);
        // §A5 — chamada extraída para httpPost() (mesmo padrão de
        // PixService::httpPost()) especificamente para poder ser
        // sobrescrita por uma subclasse testável; antes disso o método
        // inteiro (inclusive geração de QR code Pix) não tinha nenhum
        // ponto de injeção e ficou sem nenhum teste, unitário ou E2E.
        $result = static::httpPost('https://api.mercadopago.com/v1/payments', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            'X-Idempotency-Key: ' . (string)($dados['idempotencyKey'] ?? uniqid('mp-', true)),
        ], $body);
        $resp     = $result['body'] ?? '';
        $httpCode = (int)($result['code'] ?? 0);
        $curlErr  = (string)($result['error'] ?? '');

        if ($curlErr !== '') {
            error_log('[MercadoPagoProvider][criarPagamento] cURL: ' . $curlErr);
            return ['sucesso' => false, 'status' => 'erro', 'idExterno' => null, 'detalhe' => [], 'erro' => 'Erro de comunicação com o MercadoPago.'];
        }

        $data = json_decode((string)$resp, true);
        if (!is_array($data)) {
            error_log('[MercadoPagoProvider][criarPagamento] Resposta inválida (HTTP ' . $httpCode . '): ' . substr((string)$resp, 0, 500));
            return ['sucesso' => false, 'status' => 'erro', 'idExterno' => null, 'detalhe' => [], 'erro' => 'Resposta inválida do MercadoPago.'];
        }

        if ($httpCode >= 400) {
            $msg = (string)($data['message'] ?? $data['error'] ?? "HTTP {$httpCode}");
            $causas = $data['cause'] ?? [];
            error_log('[MercadoPagoProvider][criarPagamento] Recusado HTTP ' . $httpCode . ': ' . $msg . ' | cause=' . json_encode($causas));
            return [
                'sucesso' => false,
                'status'  => 'recusado',
                'idExterno' => null,
                'detalhe' => ['status_detail' => $data['status_detail'] ?? null, 'cause' => $causas],
                'erro'    => $msg,
            ];
        }

        $mpStatus = (string)($data['status'] ?? '');
        $status = match ($mpStatus) {
            'approved' => 'aprovado',
            'pending', 'in_process', 'authorized' => 'pendente',
            'rejected', 'cancelled' => 'recusado',
            default => 'erro',
        };

        $detalhe = [
            'status_detail' => $data['status_detail'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? null,
        ];
        // Pix: QR code pra exibir na hora, sem sair da página.
        if (isset($data['point_of_interaction']['transaction_data'])) {
            $txData = $data['point_of_interaction']['transaction_data'];
            $detalhe['qr_code'] = $txData['qr_code'] ?? null;
            $detalhe['qr_code_base64'] = $txData['qr_code_base64'] ?? null;
            $detalhe['ticket_url'] = $txData['ticket_url'] ?? null;
        }
        // Boleto: link pra imprimir/pagar.
        if (isset($data['transaction_details']['external_resource_url'])) {
            $detalhe['boleto_url'] = $data['transaction_details']['external_resource_url'];
        }

        return [
            'sucesso'   => $status !== 'erro',
            'status'    => $status,
            'idExterno' => isset($data['id']) ? 'mp_' . $data['id'] : null,
            'detalhe'   => $detalhe,
            'erro'      => $status === 'recusado' ? (string)($data['status_detail'] ?? 'Pagamento recusado.') : null,
        ];
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
