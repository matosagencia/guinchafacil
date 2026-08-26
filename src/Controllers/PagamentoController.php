<?php
// File: guinchafacil/src/Controllers/PagamentoController.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Pagamento.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Services/RankingService.php';
require_once __DIR__ . '/../Services/NotificacaoService.php';

/** Controller de pagamentos (MercadoPago + PagSeguro) */
class PagamentoController extends BaseController
{
    public function __construct(){ parent::__construct(); }

    public function checkout(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || $pedido['status'] !== 'aguardando_pagamento') {
            $this->redirect('/cliente/dashboard');
        }
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/pagamento/checkout.php';
    }

    public function iniciarMercadoPago(?int $pedidoId = null): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token']??'')) { http_response_code(403); exit; }
        $pedidoId = $pedidoId ?? (int)($_POST['pedido_id'] ?? 0);
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido) { $this->redirect('/cliente/dashboard'); }

        $payload = json_encode([
            'items' => [[
                'title'       => 'Serviço de Guincho - GuinchaFácil',
                'quantity'    => 1,
                'unit_price'  => (float)$pedido['custo_estimado'],
                'currency_id' => 'BRL',
            ]],
            'back_urls' => [
                'success' => APP_URL . "/pagamento/sucesso/{$pedidoId}",
                'failure' => APP_URL . "/pagamento/falha/{$pedidoId}",
                'pending' => APP_URL . "/pagamento/pendente/{$pedidoId}",
            ],
            'auto_return'     => 'approved',
            'external_reference' => (string)$pedidoId,
            'notification_url' => APP_URL . '/webhook/mercadopago',
        ]);

        $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . MP_ACCESS_TOKEN,
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 201) {
            $data = json_decode($resp, true);
            // Cria registro de pagamento pendente
            Pagamento::criar($pedidoId, 'mercadopago', (float)$pedido['custo_estimado'], 0, 0);
            header('Location: ' . $data['init_point']); exit;
        }
        error_log("[PagamentoController] MP erro $code: $resp");
        $this->redirect("/pagamento/falha/{$pedidoId}");
    }

    public function iniciarPagSeguro(?int $pedidoId = null): void
    {
        AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken($_POST['csrf_token']??'')) { http_response_code(403); exit; }
        $pedidoId = $pedidoId ?? (int)($_POST['pedido_id'] ?? 0);
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido) { $this->redirect('/cliente/dashboard'); }

        $usuario = AuthService::getCurrentUser();
        $xml = '<?xml version="1.0" encoding="UTF-8"?><checkout>'
             . '<currency>BRL</currency>'
             . '<redirectURL>' . APP_URL . "/pagamento/sucesso/{$pedidoId}</redirectURL>"
             . '<items><item>'
             . '<id>1</id><description>Serviço de Guincho GuinchaFácil</description>'
             . '<amount>' . number_format((float)$pedido['custo_estimado'],2,'.','') . '</amount>'
             . '<quantity>1</quantity>'
             . '</item></items>'
             . '<sender><name>' . htmlspecialchars($usuario['nome']) . '</name>'
             . '<email>' . htmlspecialchars($usuario['email']) . '</email></sender>'
             . '</checkout>';

        // §PS-ENV-01: respeita variáveis de ambiente — nunca hardcode sandbox
        $psApiUrl      = defined('PS_BASE_URL')     ? (string)PS_BASE_URL     : 'https://ws.sandbox.pagseguro.uol.com.br';
        $psCheckoutUrl = defined('PS_CHECKOUT_URL') ? (string)PS_CHECKOUT_URL : 'https://sandbox.pagseguro.uol.com.br';

        $ch = curl_init($psApiUrl . '/v2/checkout?email=' . urlencode((string)PS_EMAIL) . '&token=' . (string)PS_TOKEN);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/xml; charset=UTF-8'],
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        if ($resp && strpos($resp, '<code>') !== false) {
            // §PS-XML-01: parse com SimpleXML
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string((string)$resp);
            $code = $xmlResp !== false ? (string)($xmlResp->code ?? '') : '';
            Pagamento::criar($pedidoId, 'pagseguro', (float)$pedido['custo_estimado'], 0, 0);
            header('Location: ' . $psCheckoutUrl . '/v2/checkout/payment.html?code=' . urlencode($code));
            exit;
        }
        error_log("[PagamentoController] PS erro: $resp");
        $this->redirect("/pagamento/falha/{$pedidoId}");
    }

    public function sucesso(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        $pedido = Pedido::buscarPorId($pedidoId);
        require __DIR__ . '/../Views/pagamento/sucesso.php';
    }

    public function falha(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        $pedido = Pedido::buscarPorId($pedidoId);
        require __DIR__ . '/../Views/pagamento/falha.php';
    }

    public function pendente(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        $pedido = Pedido::buscarPorId($pedidoId);
        require __DIR__ . '/../Views/pagamento/pendente.php';
    }
}
