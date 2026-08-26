<?php
// File: guinchafacil/src/Services/EstornoService.php

/**
 * Serviço de estorno de pagamentos (§4.4)
 * Regras:
 * - Cancelamentos em aguardando_pagamento ou aguardando_guincho → estorno automático
 * - Cancelamentos após a_caminho → análise manual do admin (sem estorno automático)
 */
class EstornoService
{
    /**
     * Estorna pagamento aprovado de um pedido via API do gateway.
     *
     * @return array ['sucesso' => bool, 'erro' => string|null]
     */
    public static function estornar(int $pedidoId): array
    {
        $pdo = getPDO();
        $pagamento = $pdo->prepare(
            "SELECT id, metodo, id_externo, status FROM pagamentos WHERE pedido_id = ? AND status = 'aprovado' LIMIT 1"
        );
        $pagamento->execute([$pedidoId]);
        $pag = $pagamento->fetch(PDO::FETCH_ASSOC);

        if (!$pag) {
            // Sem pagamento aprovado — nada a estornar (pedido pode ter sido cancelado antes do pagamento)
            return ['sucesso' => true, 'erro' => null];
        }

        $resultado = match ($pag['metodo']) {
            'mercadopago' => self::estornarMercadoPago($pag['id_externo']),
            'pagseguro'   => self::estornarPagSeguro($pag['id_externo']),
            default       => ['sucesso' => false, 'erro' => "Gateway '{$pag['metodo']}' não suportado para estorno automático."],
        };

        if ($resultado['sucesso']) {
            $pdo->prepare("UPDATE pagamentos SET status = 'estornado' WHERE id = ?")
                ->execute([$pag['id']]);
            error_log("[EstornoService] Estorno pedido {$pedidoId} via {$pag['metodo']} concluído.");
        } else {
            error_log("[EstornoService] Falha estorno pedido {$pedidoId}: " . $resultado['erro']);
        }

        return $resultado;
    }

    private static function estornarMercadoPago(string $idExterno): array
    {
        // Remove prefixo 'mp_' se houver
        $mpId = str_starts_with($idExterno, 'mp_') ? substr($idExterno, 3) : $idExterno;

        $ch = curl_init("https://api.mercadopago.com/v1/payments/{$mpId}/refunds");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . MP_ACCESS_TOKEN,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) return ['sucesso' => false, 'erro' => "cURL: {$curlErr}"];
        if ($httpCode === 201 || $httpCode === 200) return ['sucesso' => true, 'erro' => null];

        $data = json_decode($resp, true);
        $msg  = $data['message'] ?? $data['error'] ?? "HTTP {$httpCode}";
        return ['sucesso' => false, 'erro' => "MercadoPago: {$msg}"];
    }

    private static function estornarPagSeguro(string $idExterno): array
    {
        // Remove prefixo 'ps_' se houver
        $psCode = str_starts_with($idExterno, 'ps_') ? substr($idExterno, 3) : $idExterno;

        $url = 'https://ws.sandbox.pagseguro.uol.com.br/v2/transactions/refunds'
             . '?email=' . urlencode(PS_EMAIL) . '&token=' . PS_TOKEN;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['transactionCode' => $psCode]),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) return ['sucesso' => false, 'erro' => "cURL: {$curlErr}"];
        if ($httpCode === 200) return ['sucesso' => true, 'erro' => null];
        return ['sucesso' => false, 'erro' => "PagSeguro HTTP {$httpCode}: {$resp}"];
    }
}
