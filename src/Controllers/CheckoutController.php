<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Services/TriageSessionService.php';
require_once __DIR__ . '/../Models/Veiculo.php';
require_once __DIR__ . '/../Models/Pedido.php';

final class CheckoutController extends BaseController
{
    public function veiculo(): void
    {
        $usuario = AuthService::requireAuth('cliente');
        $id = (string)($_GET['triage_session_id'] ?? $_SESSION[TriageSessionService::SESSION_ID_KEY] ?? '');
        $triagem = TriageSessionService::reconstruir($id);
        if ($triagem === null) {
            $this->setFlashMessage('Sua cotação expirou ou não pertence a esta sessão. Refaça a cotação.', 'warning');
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

        $pedidoId = (int)($triagem['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            $pedidoId = Pedido::criar(array_merge($triagem['dados'], [
                'cliente_id' => (int)$usuario['id'],
                'veiculo_id' => $veiculoId,
            ]));
            if ($pedidoId === false) {
                Logger::log(Logger::LEVEL_ERROR, 'CheckoutController', 'pagar', 'checkout', '[CHECKOUT-TRIAGE-ORDER-FAIL] Pedido não criado.', ['triage_session_id' => $id]);
                http_response_code(500); echo 'Não foi possível reconstruir seu pedido.'; exit;
            }
            TriageSessionService::registrarPedido($id, (int)$usuario['id'], $pedidoId);
        }
        Logger::log(Logger::LEVEL_INFO, 'CheckoutController', 'pagar', 'checkout', '[CHECKOUT-TRIAGE-ORDER-OK] Pedido reconstruído para pagamento.', ['triage_session_id' => $id, 'pedido_id' => $pedidoId]);
        $this->redirect('/pagamento/checkout/' . $pedidoId);
    }
}
