<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Services/TriageSessionService.php';
require_once __DIR__ . '/../Models/Veiculo.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Pagamento.php';
require_once __DIR__ . '/../Services/Payment/PaymentProviderFactory.php';
require_once __DIR__ . '/../Services/PromotionResolutionService.php';
require_once __DIR__ . '/../Services/MarketingAttributionService.php';

final class CheckoutController extends BaseController
{
    public function formCliente(): void
    {
        $cotacao = $_SESSION['pre_cotacao'] ?? null;
        if (!is_array($cotacao) || (int)($cotacao['expira_em'] ?? 0) <= time()) {
            $this->setFlashMessage('Sua cotação expirou. Gere uma nova para continuar.', 'warning');
            $this->redirect('/pre-cotacao');
            return;
        }

        $csrf_token = AuthService::gerarCsrfToken();
        $flash = $this->getFlashMessage();
        require __DIR__ . '/../Views/public/checkout-cliente.php';
    }

    public function veiculo(): void
    {
        $usuario = AuthService::requireAuth('cliente');
        $id = (string)($_GET['triage_session_id'] ?? $_SESSION[TriageSessionService::SESSION_ID_KEY] ?? '');
        $triagem = TriageSessionService::reconstruir($id);
        if ($triagem === null) {
            $this->setFlashMessage('Sua cota��o expirou ou não pertence a esta sessão. Refaça a cotação.', 'warning');
            $this->redirect('/pre-cotacao');
        }
        $veiculos = Veiculo::listarPorUsuario((int)$usuario['id']);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/checkout/veiculo.php';
    }

    public function pagar(): void
    {
        $usuario = AuthService::requireAuth('cliente');
        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            Logger::log(Logger::LEVEL_WARN, 'CheckoutController', 'pagar', 'checkout', '[CHECKOUT-TRIAGE-CSRF-FAIL] Token inválido.');
            http_response_code(403); exit;
        }
        $id = (string)($_POST['triage_session_id'] ?? '');
        $triagem = TriageSessionService::reconstruir($id);
        if ($triagem === null) $this->redirect('/pre-cotacao');

        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);
        $veiculo = Veiculo::buscarPorId($veiculoId);
        if (!$veiculo || (int)$veiculo['usuario_id'] !== (int)$usuario['id']) {
            Logger::log(Logger::LEVEL_WARN, 'CheckoutController', 'pagar', 'checkout', '[CHECKOUT-TRIAGE-VEHICLE-FAIL] Veículo inválido.', ['triage_session_id' => $id, 'veiculo_id' => $veiculoId]);
            $this->redirect('/checkout/veiculo?triage_session_id=' . rawurlencode($id));
        }

        $cupom = strtoupper(trim((string)($_POST['cupom_codigo'] ?? '')));
        $atribuicao = MarketingAttributionService::forPedido();
        $refCode = (string)($atribuicao['referral_code'] ?? '');
        $context = ['partner_profile' => 'cliente', 'vehicle_make' => $veiculo['marca'] ?? '', 'vehicle_type' => $veiculo['tipo'] ?? '', 'service_type_id' => $triagem['dados']['service_type_id'] ?? ''];
        $promocao = $cupom !== '' ? PromotionResolutionService::resolve($cupom, $context) : ['ok' => false];
        if ($cupom !== '' && !($promocao['ok'] ?? false)) {
            $this->setFlashMessage((string)($promocao['message'] ?? 'Cupom inválido.'), 'warning');
            $promocao = ['ok' => false];
            $cupom = '';
        }
        $gross = (float)($triagem['dados']['custo_estimado'] ?? 0);
        $applied = ($promocao['ok'] ?? false) ? PromotionResolutionService::apply($gross, $promocao) : ['discount' => 0, 'net' => $gross];
        $triagem['dados']['custo_estimado'] = $applied['net'];

        $pedidoId = (int)($triagem['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            $pedidoId = Pedido::criar(array_merge($triagem['dados'], [
                'triage_session_id' => $id,
                'cliente_id' => (int)$usuario['id'],
                'veiculo_id' => $veiculoId,
            ]));
            if ($pedidoId === false) {
                Logger::log(Logger::LEVEL_ERROR, 'CheckoutController', 'pagar', 'checkout', '[CHECKOUT-TRIAGE-ORDER-FAIL] Pedido não criado.', ['triage_session_id' => $id]);
                http_response_code(500); echo 'Não foi possível reconstruir seu pedido.'; exit;
            }
            Pedido::registrarContextoCheckout((int)$pedidoId, $triagem['dados']);
            $pdo = getPDO();
            $pdo->prepare('UPDATE pedidos SET cupom_codigo = ?, desconto_promocional = ?, valor_bruto_checkout = ?, parceiro_indicador_codigo = ? WHERE id = ?')->execute([$cupom ?: null, $applied['discount'], $gross, $refCode ?: null, $pedidoId]);
            if ($applied['discount'] > 0) $pdo->prepare('UPDATE order_price_snapshots SET customer_total = ? WHERE order_id = ?')->execute([$applied['net'], $pedidoId]);
            if (($promocao['ok'] ?? false) && !empty($promocao['rule']['id'])) PromotionResolutionService::consume((int)$promocao['rule']['id']);
            TriageSessionService::registrarPedido($id, (int)$usuario['id'], $pedidoId);

            // A escolha feita na pr�-cota��o atravessa o cadastro e � aplicada
            // somente depois que o pedido autenticado existe.
            $forma = strtolower(trim((string)(
                $_SESSION['pre_cotacao']['forma_pagamento']
                    ?? $triagem['dados']['forma_pagamento']
                    ?? 'online'
            )));
            if ($forma === 'online') {
                $resultado = Pagamento::aplicarDestinoPagamento(
                    (int)$pedidoId,
                    'online',
                    PaymentProviderFactory::gatewayAtivoRaw()
                );
                if (!($resultado['ok'] ?? false)) {
                    Logger::log(Logger::LEVEL_ERROR, 'CheckoutController', 'pagar', 'checkout', '[CHECKOUT-ONLINE-FAIL] Falha ao registrar pagamento online.', ['pedido_id' => $pedidoId]);
                    http_response_code(500); echo 'Não foi possível preparar o pagamento online.'; exit;
                }
            } elseif ($forma === 'pago_na_chegada') {
                $resultado = Pagamento::aplicarDestinoPagamento((int)$pedidoId, 'pago_na_chegada');
                if (!($resultado['ok'] ?? false)) {
                    Logger::log(Logger::LEVEL_ERROR, 'CheckoutController', 'pagar', 'checkout', '[CHECKOUT-CASH-FAIL] Falha ao registrar pagamento na chegada.', ['pedido_id' => $pedidoId]);
                    http_response_code(500); echo 'N�o foi poss�vel registrar a forma de pagamento escolhida.'; exit;
                }
                unset($_SESSION['pre_cotacao']['forma_pagamento']);
                $this->redirect('/cliente/pedido/' . $pedidoId . '?msg=pedido_criado');
            }
        } else {
            // O navegador pode perder a sess�o curta da pr�-cota��o depois do
            // cadastro. A triagem j� persistida continua sendo a fonte segura
            // da escolha feita pelo cliente.
            $forma = strtolower(trim((string)(
                $triagem['dados']['forma_pagamento']
                    ?? $_SESSION['pre_cotacao']['forma_pagamento']
                    ?? 'online'
            )));
            if ($forma === 'online' && empty($triagem['pedido_financeiro_preparado'])) {
                $resultado = Pagamento::aplicarDestinoPagamento(
                    (int)$pedidoId,
                    'online',
                    PaymentProviderFactory::gatewayAtivoRaw()
                );
                if (!($resultado['ok'] ?? false)) {
                    http_response_code(500);
                    echo 'Não foi possível preparar o pagamento online.';
                    exit;
                }
            } elseif ($forma === 'pago_na_chegada') {
                $resultado = Pagamento::aplicarDestinoPagamento((int)$pedidoId, 'pago_na_chegada');
                if (!($resultado['ok'] ?? false)) {
                    http_response_code(500);
                    echo 'N�o foi poss�vel registrar a forma de pagamento escolhida.';
                    exit;
                }
                $this->redirect('/cliente/pedido/' . $pedidoId . '?msg=pedido_criado');
            }
        }
        Logger::log(Logger::LEVEL_INFO, 'CheckoutController', 'pagar', 'checkout', '[CHECKOUT-TRIAGE-ORDER-OK] Pedido reconstruído para pagamento.', ['triage_session_id' => $id, 'pedido_id' => $pedidoId]);
        $this->redirect('/pagamento/checkout/' . $pedidoId);
    }
}
