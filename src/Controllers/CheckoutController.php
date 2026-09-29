<?php

declare(strict_types=1);

/**
 * Checkout rapido pos-cotacao.
 *
 * Fluxo:
 *   1. /pre-cotacao aceitar  -> /checkout/cliente (se anonimo)
 *   2. /checkout/cliente     -> cria usuario (nome + tel + email), loga
 *   3. /checkout/veiculo     -> cria veiculo + pedido + pagamento pendente
 *   4. /pagamento/checkout/N -> Brick MP existente
 */
class CheckoutController extends BaseController
{
    public function formCliente(): void
    {
        if (AuthService::isLoggedIn()) {
            $this->redirect('/checkout/veiculo');
            return;
        }
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        require __DIR__ . '/../Views/public/checkout-cliente.php';
    }

    public function salvarCliente(): void
    {
        if (AuthService::isLoggedIn()) {
            $this->redirect('/checkout/veiculo');
            return;
        }

        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(419);
            $this->setFlashMessage('Sessao expirada. Tente novamente.', 'error');
            $this->redirect('/checkout/cliente');
            return;
        }

        $nome     = trim((string)($_POST['nome'] ?? ''));
        $telefone = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? ''));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));

        if (mb_strlen($nome) < 3) {
            $this->setFlashMessage('Informe seu nome completo.', 'error');
            $this->redirect('/checkout/cliente');
            return;
        }
        if (strlen((string)$telefone) < 10 || strlen((string)$telefone) > 11) {
            $this->setFlashMessage('Telefone invalido. Use DDD + numero.', 'error');
            $this->redirect('/checkout/cliente');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setFlashMessage('Email invalido.', 'error');
            $this->redirect('/checkout/cliente');
            return;
        }

        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare('SELECT id, nome, email, telefone, tipo, ativo FROM usuarios WHERE LOWER(email) = ? OR telefone = ? LIMIT 1');
            $stmt->execute([$email, $telefone]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente && (int)$existente['ativo'] !== 1) {
                $this->setFlashMessage('Esta conta esta desativada.', 'error');
                $this->redirect('/checkout/cliente');
                return;
            }

            if ($existente) {
                require_once __DIR__ . '/../Services/Auth/MagicLinkService.php';
                $resultado = MagicLinkService::solicitar($telefone !== '' ? (string)$telefone : $email, '/checkout/veiculo');
                if (!empty($resultado['ok'])) {
                    $canal = (string)($resultado['canal'] ?? '');
                    $this->redirect('/auth/magic?canal=' . urlencode($canal));
                } else {
                    $this->setFlashMessage($resultado['erro'] ?? 'Nao foi possivel enviar o link.', 'error');
                    $this->redirect('/checkout/cliente');
                }
                return;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, google_subject, tipo, perfil_status, ativo, criado_em)
                 VALUES (?, ?, NULL, ?, NULL, NULL, 'cliente', 'COMPLETO', 1, NOW())"
            );
            $stmt->execute([$nome, $email, $telefone]);
            $userId = (int)$pdo->lastInsertId();

            $user = ['id' => $userId, 'nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'tipo' => 'cliente', 'ativo' => 1];
            AuthService::initializeAuthenticatedSession($user);

            $this->redirect('/checkout/veiculo');
        } catch (Throwable $e) {
            error_log('[CheckoutController][salvarCliente] ' . $e->getMessage());
            $this->setFlashMessage('Nao foi possivel criar sua conta. Tente novamente.', 'error');
            $this->redirect('/checkout/cliente');
        }
    }

    public function formVeiculo(): void
    {
        AuthService::requireAuth('cliente');

        $cotacao = $_SESSION['pre_cotacao'] ?? null;
        if (!$cotacao || (int)($cotacao['expira_em'] ?? 0) <= time()) {
            unset($_SESSION['pre_cotacao']);
            $this->setFlashMessage('Sua cotacao expirou. Gere uma nova para continuar.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $csrf_token = $this->generateCSRFToken();
        $veiculos = Veiculo::listarPorUsuario($uid);
        $flash = $this->pullFlash();

        require __DIR__ . '/../Views/public/checkout-veiculo.php';
    }

    public function salvarVeiculo(): void
    {
        AuthService::requireAuth('cliente');

        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(419);
            $this->setFlashMessage('Sessao expirada. Tente novamente.', 'error');
            $this->redirect('/checkout/veiculo');
            return;
        }

        $cotacao = $_SESSION['pre_cotacao'] ?? null;
        if (!$cotacao || (int)($cotacao['expira_em'] ?? 0) <= time()) {
            unset($_SESSION['pre_cotacao']);
            $this->setFlashMessage('Sua cotacao expirou. Gere uma nova para continuar.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $veiculoId = (int)($_POST['veiculo_id'] ?? 0);

        if ($veiculoId <= 0) {
            $tipoPost = (string)($_POST['tipo'] ?? 'carro');
            if (!in_array($tipoPost, ['carro','moto','caminhao','van'], true)) {
                $tipoPost = 'carro';
            }
            $tipoParaVehicleType = [
                'carro'    => 'automovel_passeio',
                'moto'     => 'moto',
                'caminhao' => 'caminhao_leve',
                'van'      => 'utilitario',
            ];
            $vehicleType = $tipoParaVehicleType[$tipoPost] ?? 'automovel_passeio';

            $dadosVeiculo = [
                'placa'                => strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($_POST['placa'] ?? '')))),
                'cidade_placa'         => trim((string)($_POST['cidade_placa'] ?? '')),
                'uf_placa'             => strtoupper(trim((string)($_POST['uf_placa'] ?? ''))),
                'marca'                => trim((string)($_POST['marca'] ?? '')),
                'modelo'               => trim((string)($_POST['modelo'] ?? '')),
                'vehicle_brand_id'     => !empty($_POST['vehicle_brand_id']) ? (int)$_POST['vehicle_brand_id'] : null,
                'vehicle_model_id'     => !empty($_POST['vehicle_model_id']) ? (int)$_POST['vehicle_model_id'] : null,
                'ano'                  => (int)($_POST['ano'] ?? 0),
                'cor'                  => trim((string)($_POST['cor'] ?? '')),
                'tipo'                 => $tipoPost,
                'vehicle_type'         => $vehicleType,
                'operational_category' => $vehicleType,
            ];

            if ($dadosVeiculo['marca'] === '' || $dadosVeiculo['modelo'] === '' || $dadosVeiculo['ano'] < 1950) {
                $this->setFlashMessage('Preencha marca, modelo e ano do veiculo.', 'error');
                $this->redirect('/checkout/veiculo');
                return;
            }

            $novoId = Veiculo::criar($uid, $dadosVeiculo);
            if (!$novoId) {
                $this->setFlashMessage('Nao foi possivel cadastrar o veiculo. Tente novamente.', 'error');
                $this->redirect('/checkout/veiculo');
                return;
            }
            $veiculoId = (int)$novoId;
        } else {
            $v = Veiculo::buscarPorId($veiculoId);
            if (!$v || (int)$v['usuario_id'] !== $uid) {
                $this->setFlashMessage('Veiculo invalido.', 'error');
                $this->redirect('/checkout/veiculo');
                return;
            }
        }

        try {
            $pedidoId = Pedido::criarCompleto([
                'cliente_id'            => $uid,
                'veiculo_id'            => $veiculoId,
                'tipo_problema'         => (string)($cotacao['tipo_problema'] ?? 'outro'),
                'descricao_problema'    => '',
                'lat_origem'            => (float)($cotacao['lat_origem'] ?? 0),
                'lng_origem'            => (float)($cotacao['lng_origem'] ?? 0),
                'endereco_origem'       => (string)($cotacao['endereco_origem'] ?? ''),
                'lat_destino'           => $cotacao['lat_destino'] ?? null,
                'lng_destino'           => $cotacao['lng_destino'] ?? null,
                'endereco_destino'      => (string)($cotacao['endereco_destino'] ?? $cotacao['destino'] ?? ''),
                'distancia_km'          => (float)($cotacao['distancia_km'] ?? 5),
                'custo_estimado'        => (float)($cotacao['valor'] ?? 0),
                'status'                => 'aguardando_pagamento',
                'service_type_id'       => (int)($cotacao['service_type_id'] ?? 0),
                'attendance_mode'       => 'TOWING',
                'veiculo_esta_batido'   => 0,
                'rodas_travadas'        => 0,
                'local_dificil_acesso'  => 0,
                'em_garagem_subsolo'    => 0,
                'utm_source'            => $_SESSION['utm']['utm_source'] ?? null,
                'utm_medium'            => $_SESSION['utm']['utm_medium'] ?? null,
                'utm_campaign'          => $_SESSION['utm']['utm_campaign'] ?? null,
                'utm_content'           => $_SESSION['utm']['utm_content'] ?? null,
                'utm_term'              => $_SESSION['utm']['utm_term'] ?? null,
                'canal_aquisicao'       => $_SESSION['utm']['canal_aquisicao'] ?? 'organico',
                'referrer_url'          => $_SESSION['utm']['referrer_url'] ?? null,
                'landing_page'          => $_SESSION['utm']['landing_page'] ?? null,
                'cidade_id'             => null,
                'pricing_zone_id'       => null,
                'modalidade_socorro'    => 'REBOQUE_TRADICIONAL',
                'local_resgate_lat'     => (float)($cotacao['lat_origem'] ?? 0),
                'local_resgate_lng'     => (float)($cotacao['lng_origem'] ?? 0),
            ]);
        } catch (Throwable $e) {
            error_log('[CheckoutController][salvarVeiculo] ' . $e->getMessage());
            $this->setFlashMessage('Nao foi possivel criar o pedido. Tente novamente.', 'error');
            $this->redirect('/checkout/veiculo');
            return;
        }

        if ($pedidoId <= 0) {
            $this->setFlashMessage('Nao foi possivel criar o pedido. Tente novamente.', 'error');
            $this->redirect('/checkout/veiculo');
            return;
        }

        Pagamento::criar($pedidoId, 'mercadopago', (float)($cotacao['valor'] ?? 0), 0, 0);
        unset($_SESSION['pre_cotacao']);

        $this->redirect('/pagamento/checkout/' . $pedidoId);
    }

    public function pagar(): void
    {
        AuthService::requireAuth('cliente');

        $pedidoId = (int)($_GET['pedido'] ?? 0);
        if ($pedidoId <= 0) {
            $this->redirect('/cliente/dashboard');
            return;
        }

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (int)$pedido['cliente_id'] !== $uid) {
            $this->redirect('/cliente/dashboard');
            return;
        }

        $this->redirect('/pagamento/checkout/' . $pedidoId);
    }
}