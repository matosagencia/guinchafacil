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
require_once __DIR__ . '/../Services/PedidoService.php';
require_once __DIR__ . '/../Services/RateLimiter.php';
require_once __DIR__ . '/../Services/Payment/MercadoPagoProvider.php';
require_once __DIR__ . '/../Services/Payment/PagSeguroProvider.php';
require_once __DIR__ . '/../Services/Payment/GatewayRotationService.php';
require_once __DIR__ . '/../Services/Payment/PagamentoAprovacaoService.php';
require_once __DIR__ . '/../Services/Payment/PaymentProviderFactory.php';
require_once __DIR__ . '/../Services/Financial/SupplementalChargeService.php';

/** Controller de pagamentos (MercadoPago + PagSeguro) */
class PagamentoController extends BaseController
{
    private const FAILURE_SESSION_KEY = '_pagamento_falha';

    public function __construct(){ parent::__construct(); }


    /**
     * Gateway "de repouso" — só o valor de PAYMENT_GATEWAY_ACTIVE, sem
     * considerar rotação por limite diário. Usado onde o comportamento
     * antigo (redirect Checkout Pro) precisa continuar exatamente igual.
     * §GATEWAY-CENTRAL-01: delega em PaymentProviderFactory::gatewayAtivoRaw(),
     * fonte única da leitura desta constante em todo o projeto.
     */
    private function gatewayAtivo(): string
    {
        return PaymentProviderFactory::gatewayAtivoRaw();
    }

    /**
     * Gateway efetivo pro PRÓXIMO checkout, já com rotação automática por
     * limite diário aplicada (§16 constituição + rotação nova pedida pelo
     * usuário). É este método — não gatewayAtivo() — que decide qual
     * painel de checkout transparente aparece e qual endpoint aceita a
     * cobrança.
     */
    private function gatewayEfetivo(): string
    {
        return GatewayRotationService::gatewayEfetivo(function (string $gw): bool {
            if ($gw === 'mercadopago') {
                return $this->validarConfigMercadoPago() === null;
            }
            if ($gw === 'pagseguro') {
                return $this->pagSeguroConfigurado();
            }
            return false;
        });
    }

    private function appUrlBase(): string
    {
        return rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/');
    }

    private function urlApp(string $path): string
    {
        return $this->appUrlBase() . '/' . ltrim($path, '/');
    }

    /**
     * §REDIRECT-RELATIVO-01: para redirecionar o NAVEGADOR do cliente
     * (campo "redirect" da resposta JSON do checkout transparente), usar
     * urlApp()/APP_URL é errado — APP_URL é o domínio canônico de produção,
     * fixo no .env, e não tem relação com o host/porta de onde a requisição
     * atual realmente veio. Em ambiente local (XAMPP, 127.0.0.1:8080) isso
     * jogava o navegador pro site real em produção após aprovar o
     * pagamento, onde obviamente não há sessão — visto na prática:
     * https://guinchafacil.com.br/login?motivo=nao_autenticado&retorno=...
     * URL relativa (respeitando BASE_PATH) resolve sempre contra o host
     * atual, local ou produção, sem esse risco. urlApp()/APP_URL continua
     * correto pros outros usos (webhook do MP, validação HTTPS pública).
     */
    private function urlRelativa(string $path): string
    {
        $base = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : '';
        return $base . '/' . ltrim($path, '/');
    }

    private function appUrlEhHttpsPublica(): bool
    {
        $partes = parse_url($this->appUrlBase());
        $scheme = strtolower((string)($partes['scheme'] ?? ''));
        $host = strtolower((string)($partes['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        return !in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && !str_ends_with($host, '.local');
    }

    private function validarConfigMercadoPago(): ?string
    {
        if (trim((string)(defined('MP_ACCESS_TOKEN') ? MP_ACCESS_TOKEN : '')) === '') {
            return 'MP-CONFIG-01: MP_ACCESS_TOKEN nao configurado.';
        }

        if ($this->appUrlBase() === '') {
            return 'MP-CONFIG-02: APP_URL nao configurado.';
        }

        if (str_starts_with((string)MP_ACCESS_TOKEN, 'APP_USR-') && !$this->appUrlEhHttpsPublica()) {
            return 'MP-CONFIG-03: token MercadoPago de producao exige APP_URL publica com HTTPS; atual=' . $this->appUrlBase();
        }

        return null;
    }

    private function pagSeguroConfigurado(): bool
    {
        $email = trim((string)(defined('PS_EMAIL') ? PS_EMAIL : ''));
        $token = trim((string)(defined('PS_TOKEN') ? PS_TOKEN : ''));

        return $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && $token !== ''
            && $token !== 'A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6';
    }

    private function validarConfigPagSeguro(): ?string
    {
        if (!in_array($this->gatewayAtivo(), ['pagseguro', 'todos', 'all'], true)) {
            return 'PS-CONFIG-00: PagSeguro desativado por PAYMENT_GATEWAY_ACTIVE=' . $this->gatewayAtivo();
        }

        if (!$this->pagSeguroConfigurado()) {
            return 'PS-CONFIG-01: PS_EMAIL/PS_TOKEN ausentes, invalidos ou placeholder.';
        }

        if ($this->appUrlBase() === '') {
            return 'PS-CONFIG-02: APP_URL nao configurado.';
        }

        return null;
    }

    private function registrarFalhaPagamento(
        int $pedidoId,
        string $gateway,
        string $mensagemUsuario,
        string $detalheDev,
        array $contexto = []
    ): void {
        $codigo = strtoupper(substr(hash('sha256', $gateway . '|' . $pedidoId . '|' . microtime(true)), 0, 10));

        $_SESSION[self::FAILURE_SESSION_KEY][$pedidoId] = [
            'gateway' => $gateway,
            'mensagem_usuario' => $mensagemUsuario,
            'detalhe_dev' => $detalheDev,
            'codigo' => $codigo,
            'contexto' => $contexto,
            'criado_em' => time(),
        ];

        error_log('[PagamentoController][' . $codigo . '] Falha em ' . $gateway . ' no pedido ' . $pedidoId
            . ': ' . $detalheDev
            . (!empty($contexto) ? ' | ctx=' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''));
    }

    private function redirecionarFalhaPagamento(
        int $pedidoId,
        string $gateway,
        string $mensagemUsuario,
        string $detalheDev,
        array $contexto = []
    ): void {
        $this->registrarFalhaPagamento($pedidoId, $gateway, $mensagemUsuario, $detalheDev, $contexto);
        $this->redirect("/pagamento/falha/{$pedidoId}");
    }

    private function mensagemUsuarioPorHttpCode(int $httpCode, string $gateway): string
    {
        if ($httpCode === 0) {
            return "Não conseguimos conectar com {$gateway}. Tente novamente em instantes.";
        }

        if (in_array($httpCode, [401, 403], true)) {
            return "A configuração de pagamento de {$gateway} precisa ser revisada. Nossa equipe já tem um código de diagnóstico para investigar.";
        }

        if (in_array($httpCode, [400, 404, 409, 422], true)) {
            return "O provedor recusou os dados do checkout. Revise o pedido e tente novamente.";
        }

        if ($httpCode >= 500) {
            return "{$gateway} está instável no momento. Tente novamente em alguns minutos.";
        }

        return "Não foi possível iniciar o pagamento por {$gateway}. Tente novamente.";
    }

    private function extrairErroMercadoPago(?string $resp, int $httpCode): string
    {
        if ($resp === null || trim($resp) === '') {
            return "MercadoPago retornou HTTP {$httpCode} sem corpo de resposta.";
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            return "MercadoPago retornou HTTP {$httpCode} com JSON inválido: " . json_last_error_msg();
        }

        $partes = [];
        foreach (['message', 'error', 'error_description', 'status'] as $campo) {
            if (!empty($data[$campo]) && is_scalar($data[$campo])) {
                $partes[] = "{$campo}: " . (string)$data[$campo];
            }
        }

        $causas = $data['cause'] ?? $data['causes'] ?? [];
        if (is_array($causas)) {
            foreach ($causas as $causa) {
                if (is_array($causa)) {
                    $codigo = isset($causa['code']) ? '[' . $causa['code'] . '] ' : '';
                    $descricao = $causa['description'] ?? $causa['message'] ?? null;
                    if ($descricao) {
                        $partes[] = 'cause: ' . $codigo . $descricao;
                    }
                } elseif (is_scalar($causa)) {
                    $partes[] = 'cause: ' . (string)$causa;
                }
            }
        }

        return $partes ? implode(' | ', $partes) : "MercadoPago retornou HTTP {$httpCode} sem mensagem de erro estruturada.";
    }

    private function extrairErroPagSeguro(?string $resp, int $httpCode): string
    {
        if ($resp === null || trim($resp) === '') {
            return "PagSeguro retornou HTTP {$httpCode} sem corpo de resposta.";
        }

        libxml_use_internal_errors(true);
        $xmlResp = simplexml_load_string((string)$resp);
        if ($xmlResp === false) {
            $erros = array_map(static fn($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            return "PagSeguro retornou HTTP {$httpCode} com XML inválido: " . implode('; ', array_filter($erros));
        }

        $partes = [];
        foreach ($xmlResp->errors->error ?? [] as $erro) {
            $codigo = trim((string)($erro->code ?? ''));
            $mensagem = trim((string)($erro->message ?? ''));
            if ($codigo !== '' || $mensagem !== '') {
                $partes[] = trim(($codigo !== '' ? "[{$codigo}] " : '') . $mensagem);
            }
        }

        if (!$partes) {
            foreach (['message', 'error'] as $campo) {
                $valor = trim((string)($xmlResp->{$campo} ?? ''));
                if ($valor !== '') {
                    $partes[] = "{$campo}: {$valor}";
                }
            }
        }

        return $partes ? implode(' | ', $partes) : "PagSeguro retornou HTTP {$httpCode} sem mensagem de erro estruturada.";
    }

    private function normalizarFalhaRetornoGateway(): array
    {
        $status = strtolower((string)($_GET['status'] ?? $_GET['collection_status'] ?? ''));
        $paymentId = (string)($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');
        $preferenceId = (string)($_GET['preference_id'] ?? '');

        $mensagem = match ($status) {
            'rejected' => 'O pagamento foi recusado pelo provedor. Confira os dados da forma de pagamento ou escolha outra opção.',
            'cancelled', 'canceled' => 'O pagamento foi cancelado antes da conclusão.',
            'pending', 'in_process' => 'O pagamento ainda não foi confirmado pelo provedor.',
            default => 'O provedor não confirmou o pagamento.',
        };

        $detalhe = 'Retorno do provedor na rota de falha'
            . ($status !== '' ? " | status={$status}" : '')
            . ($paymentId !== '' ? " | payment_id={$paymentId}" : '')
            . ($preferenceId !== '' ? " | preference_id={$preferenceId}" : '');

        return [$mensagem, $detalhe, [
            'status' => $status,
            'payment_id' => $paymentId,
            'preference_id' => $preferenceId,
        ]];
    }

    private function pagamentoFoiIgnorado(): bool
    {
        $pedidoService = new PedidoService();
        return $pedidoService->podeIniciarAtendimento();
    }

    private function pedidoContexto(?int $pedidoId = null): ?array
    {
        if ($pedidoId !== null && $pedidoId > 0) {
            return Pedido::buscarPorId($pedidoId) ?: null;
        }

        $usuario = AuthService::getCurrentUser();
        $usuarioId = (int)($usuario['id'] ?? 0);
        if ($usuarioId <= 0) {
            return null;
        }

        $stmt = getPDO()->prepare(
            "SELECT id
               FROM pedidos
              WHERE cliente_id = ?
              ORDER BY criado_em DESC, id DESC
              LIMIT 1"
        );
        $stmt->execute([$usuarioId]);
        $foundId = (int)$stmt->fetchColumn();

        return $foundId > 0 ? (Pedido::buscarPorId($foundId) ?: null) : null;
    }

    public function checkout(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');

        if ($this->pagamentoFoiIgnorado()) {
            $this->redirect('/cliente/dashboard');
        }

        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || $pedido['status'] !== 'aguardando_pagamento') {
            $this->redirect('/cliente/dashboard');
        }
        $csrfToken = AuthService::gerarCsrfToken();
        $gatewayAtivo = $this->gatewayEfetivo();
        $mostrarMercadoPago = in_array($gatewayAtivo, ['mercadopago', 'todos', 'all'], true)
            && $this->validarConfigMercadoPago() === null;
        $mostrarPagSeguro = in_array($gatewayAtivo, ['pagseguro', 'todos', 'all'], true) && $this->pagSeguroConfigurado();
        $mpPublicKey = defined('MP_PUBLIC_KEY') ? (string)MP_PUBLIC_KEY : '';
        require __DIR__ . '/../Views/pagamento/checkout.php';
    }

    public function complementarCheckout(int $chargeItemId): void
    {
        AuthService::requireAuth('cliente');
        $user = AuthService::getCurrentUser();
        $ctx = SupplementalChargeService::buscarContextoCliente($chargeItemId, (int)$user['id']);
        if (!$ctx) { $this->redirect('/cliente/dashboard'); }
        if (($ctx['status'] ?? '') === 'APPROVED') { $this->redirect('/cliente/pedido/' . (int)$ctx['order_id']); }
        $payment = $ctx;
        $valorPedido = (float)$ctx['amount'];
        $pedidoId = (int)$ctx['order_id'];
        $csrfToken = AuthService::gerarCsrfToken();
        $gatewayAtivo = $this->gatewayEfetivo();
        $mostrarMercadoPago = in_array($gatewayAtivo, ['mercadopago', 'todos', 'all'], true) && $this->validarConfigMercadoPago() === null;
        $mpPublicKey = defined('MP_PUBLIC_KEY') ? (string)MP_PUBLIC_KEY : '';
        require __DIR__ . '/../Views/pagamento/complementar.php';
    }

    public function complementarMercadoPago(): void
    {
        AuthService::requireAuth('cliente');
        $body = $this->lerJsonBody();
        if (!AuthService::validarCsrfToken((string)($body['csrf_token'] ?? ''))) $this->responderJson(['sucesso'=>false,'erro'=>'Sessão expirada.'], 403);
        $chargePaymentId = (int)($body['charge_payment_id'] ?? 0);
        $user = AuthService::getCurrentUser();
        $ctx = SupplementalChargeService::buscarContextoPorPagamento($chargePaymentId);
        if (!$ctx || (int)$ctx['cliente_id'] !== (int)$user['id'] || ($ctx['status'] ?? '') !== 'PENDING') $this->responderJson(['sucesso'=>false,'erro'=>'Cobrança complementar inválida.'], 422);
        $formData = is_array($body['formData'] ?? null) ? $body['formData'] : [];
        $method = (string)($formData['payment_method_id'] ?? '');
        if ($method === '') $this->responderJson(['sucesso'=>false,'erro'=>'Método de pagamento não identificado.'], 422);
        $provider = new MercadoPagoProvider();
        $externalReference = 'charge:' . (int)$ctx['order_id'] . ':' . $chargePaymentId;
        $result = $provider->criarPagamento([
            'pedidoId' => (int)$ctx['order_id'], 'externalReference' => $externalReference,
            'valor' => (float)$ctx['amount'], 'descricao' => 'Itens adicionais GuinchaFácil #' . (int)$ctx['order_id'],
            'payerEmail' => (string)$user['email'], 'paymentMethodId' => $method,
            'token' => (string)($formData['token'] ?? ''), 'parcelas' => (int)($formData['installments'] ?? 1),
            'issuerId' => (string)($formData['issuer_id'] ?? ''),
            'docTipo' => (string)($formData['payer']['identification']['type'] ?? 'CPF'),
            'docNumero' => (string)($formData['payer']['identification']['number'] ?? ''),
            'idempotencyKey' => 'charge-mp-' . $chargePaymentId . '-' . substr(hash('sha256', (string)($formData['token'] ?? '') . $method), 0, 24),
            'notificationUrl' => $this->urlApp('/webhook/mercadopago'),
        ]);
        if (!$result['sucesso']) $this->responderJson(['sucesso'=>false,'status'=>$result['status'],'erro'=>'O pagamento da cotação não foi aprovado.','detalhe'=>$result['detalhe'] ?? null]);
        if ($result['status'] === 'aprovado' && $result['idExterno']) {
            $ok = SupplementalChargeService::aprovarPagamentoGateway((int)$ctx['order_id'], $chargePaymentId, (string)$result['idExterno'], json_encode($result['detalhe']));
            if (!$ok) $this->responderJson(['sucesso'=>false,'erro'=>'Pagamento recebido, mas a execução não foi liberada. Contate o suporte.']);
        }
        $this->responderJson(['sucesso'=>true,'status'=>$result['status'],'detalhe'=>$result['detalhe'],'redirect'=>$result['status']==='aprovado' ? $this->urlRelativa('/cliente/pedido/' . (int)$ctx['order_id']) : null]);
    }

    // ─── Checkout transparente ──────────────────────────────────────────────
    // §CTP-01: o comprador nunca sai de /pagamento/checkout/{id}. Estes
    // endpoints são chamados via fetch() pelo JS da view; toda validação
    // que os endpoints antigos (iniciarMercadoPago/iniciarPagSeguro) fazem
    // por redirect aqui é feita e devolvida como JSON.

    private function responderJson(array $dados, int $codigo = 200): never
    {
        // §OUTPUT-BUFFER-01: descarta qualquer saída acidental (warning de
        // rede do PHPMailer, notice, etc.) que tenha vazado antes daqui —
        // ver ob_start() em index.php. Sem isso, um efeito colateral
        // silencioso (ex.: SMTP indisponível ao notificar o cliente) quebra
        // o JSON do checkout transparente mesmo com o pagamento aprovado.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function lerJsonBody(): array
    {
        $raw = (string)file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Valida que o pedido pertence ao cliente autenticado e ainda está
     * aguardando pagamento — mesma checagem que os fluxos antigos faziam
     * antes de criar a cobrança, só que aqui é compartilhada pelos dois
     * gateways transparentes.
     */
    private function validarPedidoParaCobrancaTransparente(int $pedidoId): array
    {
        $usuario = AuthService::getCurrentUser();
        $usuarioId = (int)($usuario['id'] ?? 0);

        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido) {
            return [null, 'Pedido não encontrado.'];
        }
        if ((int)($pedido['cliente_id'] ?? 0) !== $usuarioId) {
            return [null, 'Este pedido não pertence ao usuário autenticado.'];
        }
        if ($this->pagamentoFoiIgnorado() || ($pedido['status'] ?? '') !== 'aguardando_pagamento') {
            return [null, 'Pedido não está aguardando pagamento.'];
        }
        return [$pedido, null];
    }

    /**
     * POST /pagamento/mercadopago/pagar — recebe o formData que o Payment
     * Brick gerou no browser (token de cartão OU payment_method_id
     * 'pix'/'bolbradesco') e cobra direto via MercadoPagoProvider::criarPagamento().
     * Resposta síncrona já vem com o status final (ou QR/boleto pendente),
     * então aprova na hora via PagamentoAprovacaoService — sem esperar
     * webhook — quando status = aprovado.
     */
    public function mercadoPagoTransparente(): void
    {
        AuthService::requireAuth('cliente');
        $body = $this->lerJsonBody();

        if (!AuthService::validarCsrfToken((string)($body['csrf_token'] ?? ''))) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
        }

        $pedidoId = (int)($body['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Pedido inválido.'], 422);
        }

        $limiter = new RateLimiter();
        $rlKey = 'pagamento_transparente_mp';
        if (!$limiter->checkLimit($rlKey, 8, 300)) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Muitas tentativas de pagamento. Aguarde alguns minutos e tente novamente.'], 429);
        }

        [$pedido, $erroPedido] = $this->validarPedidoParaCobrancaTransparente($pedidoId);
        if ($pedido === null) {
            $limiter->recordAttempt($rlKey, 8, 300);
            $this->responderJson(['sucesso' => false, 'erro' => $erroPedido], 422);
        }

        $erroConfig = $this->validarConfigMercadoPago();
        if ($erroConfig !== null) {
            error_log('[PagamentoController][mercadoPagoTransparente] ' . $erroConfig);
            $this->responderJson(['sucesso' => false, 'erro' => 'MercadoPago não está disponível no momento.'], 503);
        }

        $formData = is_array($body['formData'] ?? null) ? $body['formData'] : [];
        $paymentMethodId = (string)($formData['payment_method_id'] ?? $body['payment_method_id'] ?? '');
        if ($paymentMethodId === '') {
            $limiter->recordAttempt($rlKey, 8, 300);
            $this->responderJson(['sucesso' => false, 'erro' => 'Método de pagamento não identificado.'], 422);
        }

        // §PAY-IDEMP-01: cria/reaproveita o registro pendente ANTES de cobrar
        // (mesmo padrão do fluxo redirect) — evita ficar sem rastro local se
        // o cliente fechar a aba entre a resposta da API e o retorno pra nós.
        $pagamentoId = Pagamento::criar($pedidoId, 'mercadopago', (float)$pedido['custo_estimado'], 0, 0);
        if (!$pagamentoId) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Não foi possível registrar a transação. Tente novamente.'], 500);
        }

        // §PAY-IDEMP-02 (27/07/2026, achado em teste real de Sandbox): a
        // fórmula antiga ('mp-'.pedidoId.'-'.hash(pagamentoId.'|'.pedidoId))
        // é 100% determinística — depende só de pedido_id/pagamento_id, sem
        // nenhum componente por tentativa. Como Pagamento::criar() reaproveita
        // a MESMA linha (mesmo pagamento_id) em cada nova tentativa de
        // cobrança do mesmo pedido (reiniciarTentativa(), usado tanto em
        // retry legítimo do cliente quanto em reset de seed de teste), toda
        // tentativa real mandava pro Mercado Pago o MESMO X-Idempotency-Key,
        // mesmo com um token de cartão novo do Brick a cada vez — confirmado
        // nos logs reais (php_errors.log) com "Cannot infer Payment Method"
        // (causa 2131) se repetindo em sequência pro mesmo pedido_id, cada
        // chamada com token diferente mas a mesma chave de idempotência.
        // Incluir o token do Brick (única por tokenização, de uso único e
        // efêmera) na chave resolve isso: tentativas com token NOVO geram
        // chave nova; um retry de rede genuíno reenviando o MESMO token
        // ainda cai na mesma chave, preservando a proteção original contra
        // cobrança duplicada por reenvio de rede.
        $tokenParaIdempotencia = (string)($formData['token'] ?? '');
        $idempotencyKey = 'mp-' . $pedidoId . '-' . substr(hash('sha256', (string)$pagamentoId . '|' . $pedidoId . '|' . $paymentMethodId . '|' . $tokenParaIdempotencia), 0, 24);

        // §PAY-EMAIL-01: causa real de "payer.email must be a valid email"
        // (HTTP 400 do MP, visto em produção nos logs) — o Brick às vezes
        // manda formData.payer.email como STRING VAZIA (não null/ausente),
        // e '??' só cai no fallback quando a chave não existe ou é null,
        // nunca por causa de string vazia. Resultado: mandávamos "" pro MP
        // mesmo tendo o e-mail real do cliente disponível. Agora valida de
        // verdade (FILTER_VALIDATE_EMAIL) e só usa o valor do Brick se for
        // um e-mail válido — senão cai pro e-mail cadastrado do cliente.
        $payerEmailBrick = trim((string)($formData['payer']['email'] ?? ''));
        $payerEmail = filter_var($payerEmailBrick, FILTER_VALIDATE_EMAIL)
            ? $payerEmailBrick
            : (string)($pedido['cliente_email'] ?? '');

        $provider = new MercadoPagoProvider();
        $resultado = $provider->criarPagamento([
            'pedidoId'        => $pedidoId,
            'valor'           => (float)$pedido['custo_estimado'],
            'descricao'       => 'Serviço de Guincho - GuinchaFácil #' . $pedidoId,
            'payerEmail'      => $payerEmail,
            'paymentMethodId' => $paymentMethodId,
            'token'           => (string)($formData['token'] ?? ''),
            'parcelas'        => (int)($formData['installments'] ?? 1),
            'issuerId'        => (string)($formData['issuer_id'] ?? ''),
            'docTipo'         => (string)($formData['payer']['identification']['type'] ?? 'CPF'),
            'docNumero'       => (string)($formData['payer']['identification']['number'] ?? ''),
            'idempotencyKey'  => $idempotencyKey,
            'notificationUrl' => $this->urlApp('/webhook/mercadopago'),
        ]);

        if (!$resultado['sucesso']) {
            $limiter->recordAttempt($rlKey, 8, 300);
            $this->registrarFalhaPagamento($pedidoId, 'MercadoPago',
                'O pagamento não foi aprovado. Verifique os dados e tente novamente.',
                (string)($resultado['erro'] ?? 'Recusado sem detalhe.'),
                ['status' => $resultado['status'], 'detalhe' => $resultado['detalhe']]
            );
            // §PAY-DEBUG-01: o erro genérico pro usuário final continua o
            // mesmo, mas o corpo da resposta agora carrega 'erro_tecnico' e
            // 'detalhe' (status_detail/cause reais da API do MP, já
            // capturados por MercadoPagoProvider::criarPagamento()) — sem
            // isso, diagnosticar uma recusa exigia ir direto no error_log
            // do PHP a cada teste. O dado não é sensível (não inclui número
            // de cartão nem token bruto), só o motivo da recusa.
            $this->responderJson([
                'sucesso'      => false,
                'status'       => $resultado['status'],
                'erro'         => 'O pagamento não foi aprovado pelo MercadoPago. Verifique os dados e tente novamente.',
                'erro_tecnico' => $resultado['erro'] ?? null,
                'detalhe'      => $resultado['detalhe'] ?? null,
            ], 200);
        }

        if ($resultado['status'] === 'aprovado' && $resultado['idExterno']) {
            $aprovacao = PagamentoAprovacaoService::aprovar(
                $pedidoId, $resultado['idExterno'], json_encode($resultado['detalhe']), 'checkout_transparente'
            );
            if (!$aprovacao['ok']) {
                $this->responderJson(['sucesso' => false, 'erro' => $aprovacao['erro'] ?? 'Pagamento aprovado, mas não foi possível concluir o pedido. Contate o suporte.'], 200);
            }
        }

        $this->responderJson([
            'sucesso'   => true,
            'status'    => $resultado['status'],
            'detalhe'   => $resultado['detalhe'],
            'redirect'  => $resultado['status'] === 'aprovado' ? $this->urlRelativa("/pagamento/sucesso/{$pedidoId}") : null,
        ]);
    }

    /**
     * GET /pagamento/pagseguro/sessao/{id} — passo 1 do checkout
     * transparente PagSeguro: cria a sessão que o PagSeguroDirectPayment.js
     * do browser precisa antes de tokenizar o cartão / gerar o senderHash.
     */
    public function pagSeguroSessao(int $pedidoId): void
    {
        AuthService::requireAuth('cliente');
        [$pedido, $erroPedido] = $this->validarPedidoParaCobrancaTransparente($pedidoId);
        if ($pedido === null) {
            $this->responderJson(['sucesso' => false, 'erro' => $erroPedido], 422);
        }

        $erroConfig = $this->validarConfigPagSeguro();
        if ($erroConfig !== null) {
            error_log('[PagamentoController][pagSeguroSessao] ' . $erroConfig);
            $this->responderJson(['sucesso' => false, 'erro' => 'PagSeguro não está disponível no momento.'], 503);
        }

        $provider = new PagSeguroProvider();
        $sessao = $provider->criarSessao();
        if (!$sessao['sucesso']) {
            $this->responderJson(['sucesso' => false, 'erro' => $sessao['erro']], 502);
        }

        $this->responderJson(['sucesso' => true, 'sessionId' => $sessao['sessionId']]);
    }

    /**
     * POST /pagamento/pagseguro/pagar — cartão (com senderHash + token
     * gerados no browser) ou boleto, direto via PagSeguroProvider::criarPagamento().
     * Ver PagSeguroProvider::criarPagamento() pra escopo (sem Pix nesta rodada).
     */
    public function pagSeguroTransparente(): void
    {
        AuthService::requireAuth('cliente');
        $body = $this->lerJsonBody();

        if (!AuthService::validarCsrfToken((string)($body['csrf_token'] ?? ''))) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
        }

        $pedidoId = (int)($body['pedido_id'] ?? 0);
        if ($pedidoId <= 0) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Pedido inválido.'], 422);
        }

        $limiter = new RateLimiter();
        $rlKey = 'pagamento_transparente_ps';
        if (!$limiter->checkLimit($rlKey, 8, 300)) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Muitas tentativas de pagamento. Aguarde alguns minutos e tente novamente.'], 429);
        }

        [$pedido, $erroPedido] = $this->validarPedidoParaCobrancaTransparente($pedidoId);
        if ($pedido === null) {
            $limiter->recordAttempt($rlKey, 8, 300);
            $this->responderJson(['sucesso' => false, 'erro' => $erroPedido], 422);
        }

        $erroConfig = $this->validarConfigPagSeguro();
        if ($erroConfig !== null) {
            error_log('[PagamentoController][pagSeguroTransparente] ' . $erroConfig);
            $this->responderJson(['sucesso' => false, 'erro' => 'PagSeguro não está disponível no momento.'], 503);
        }

        $metodo = (string)($body['metodo'] ?? 'creditCard'); // 'creditCard' | 'boleto'
        if (!in_array($metodo, ['creditCard', 'boleto'], true)) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Método de pagamento inválido.'], 422);
        }
        if ($metodo === 'creditCard' && empty($body['senderHash'])) {
            $limiter->recordAttempt($rlKey, 8, 300);
            $this->responderJson(['sucesso' => false, 'erro' => 'Sessão de pagamento não iniciada corretamente. Atualize a página e tente novamente.'], 422);
        }

        $usuario = AuthService::getCurrentUser();

        $pagamentoId = Pagamento::criar($pedidoId, 'pagseguro', (float)$pedido['custo_estimado'], 0, 0);
        if (!$pagamentoId) {
            $this->responderJson(['sucesso' => false, 'erro' => 'Não foi possível registrar a transação. Tente novamente.'], 500);
        }

        $provider = new PagSeguroProvider();
        $resultado = $provider->criarPagamento([
            'pedidoId'             => $pedidoId,
            'valor'                => (float)$pedido['custo_estimado'],
            'descricao'            => 'Serviço de Guincho - GuinchaFácil #' . $pedidoId,
            'paymentMethodId'      => $metodo,
            'nome'                 => (string)($usuario['nome'] ?? ''),
            'payerEmail'           => (string)($usuario['email'] ?? $pedido['cliente_email'] ?? ''),
            'docNumero'            => (string)($body['docNumero'] ?? ''),
            'telefoneDdd'          => (string)($body['telefoneDdd'] ?? ''),
            'telefoneNumero'       => (string)($body['telefoneNumero'] ?? ''),
            'senderHash'           => (string)($body['senderHash'] ?? ''),
            'token'                => (string)($body['token'] ?? ''),
            'parcelas'             => (int)($body['parcelas'] ?? 1),
            'nascimento'           => (string)($body['nascimento'] ?? ''),
            'enderecoRua'          => (string)($body['enderecoRua'] ?? ''),
            'enderecoNumero'       => (string)($body['enderecoNumero'] ?? ''),
            'enderecoComplemento'  => (string)($body['enderecoComplemento'] ?? ''),
            'enderecoBairro'       => (string)($body['enderecoBairro'] ?? ''),
            'enderecoCep'          => (string)($body['enderecoCep'] ?? ''),
            'enderecoCidade'       => (string)($body['enderecoCidade'] ?? ''),
            'enderecoUf'           => (string)($body['enderecoUf'] ?? ''),
            'notificationUrl'      => $this->urlApp('/webhook/pagseguro'),
        ]);

        if (!$resultado['sucesso']) {
            $limiter->recordAttempt($rlKey, 8, 300);
            $this->registrarFalhaPagamento($pedidoId, 'PagSeguro',
                'O pagamento não foi aprovado. Verifique os dados e tente novamente.',
                (string)($resultado['erro'] ?? 'Recusado sem detalhe.'),
                ['status' => $resultado['status'], 'detalhe' => $resultado['detalhe']]
            );
            $this->responderJson([
                'sucesso'      => false,
                'status'       => $resultado['status'],
                'erro'         => 'O pagamento não foi aprovado pelo PagSeguro. Verifique os dados e tente novamente.',
                'erro_tecnico' => $resultado['erro'] ?? null,
                'detalhe'      => $resultado['detalhe'] ?? null,
            ], 200);
        }

        // PagSeguro normalmente confirma via IPN (webhook) mesmo quando a
        // resposta síncrona já vem "paga" (status 3/4) — mas se vier,
        // aproveitamos igual ao MP em vez de esperar à toa.
        if ($resultado['status'] === 'aprovado' && $resultado['idExterno']) {
            PagamentoAprovacaoService::aprovar(
                $pedidoId, $resultado['idExterno'], json_encode($resultado['detalhe']), 'checkout_transparente'
            );
        }

        $this->responderJson([
            'sucesso'  => true,
            'status'   => $resultado['status'],
            'detalhe'  => $resultado['detalhe'],
            'redirect' => $resultado['status'] === 'aprovado' ? $this->urlRelativa("/pagamento/sucesso/{$pedidoId}") : null,
        ]);
    }

    public function iniciarMercadoPago(?int $pedidoId = null): void
    {
        AuthService::requireAuth('cliente');
        $pedidoId = $pedidoId ?? (int)($_POST['pedido_id'] ?? 0);
        if (!AuthService::validarCsrfToken($_POST['csrf_token']??'')) {
            if ($pedidoId > 0) {
                $this->redirecionarFalhaPagamento(
                    $pedidoId,
                    'MercadoPago',
                    'Sua sessão expirou antes de iniciar o pagamento. Atualize a página e tente novamente.',
                    'CSRF token inválido ao iniciar checkout MercadoPago.'
                );
            }
            http_response_code(403);
            exit;
        }
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido) {
            $this->setFlashMessage('Pedido não encontrado para iniciar pagamento.', 'error');
            $this->redirect('/cliente/dashboard');
        }
        if ($this->pagamentoFoiIgnorado() || ($pedido['status'] ?? '') !== 'aguardando_pagamento') {
            $this->redirect('/cliente/dashboard');
        }

        $erroConfig = $this->validarConfigMercadoPago();
        if ($erroConfig !== null) {
            $this->redirecionarFalhaPagamento(
                $pedidoId,
                'MercadoPago',
                'A configuração do MercadoPago precisa ser revisada antes de iniciar o checkout.',
                $erroConfig,
                ['app_url' => $this->appUrlBase()]
            );
        }

        $payload = json_encode([
            'items' => [[
                'title'       => 'Serviço de Guincho - GuinchaFácil',
                'quantity'    => 1,
                'unit_price'  => (float)$pedido['custo_estimado'],
                'currency_id' => 'BRL',
            ]],
            'back_urls' => [
                'success' => $this->urlApp("/pagamento/sucesso/{$pedidoId}"),
                'failure' => $this->urlApp("/pagamento/falha/{$pedidoId}"),
                'pending' => $this->urlApp("/pagamento/pendente/{$pedidoId}"),
            ],
            'auto_return'     => 'approved',
            'external_reference' => (string)$pedidoId,
            'notification_url' => $this->urlApp('/webhook/mercadopago'),
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
        if ($ca = ca_bundle_path()) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $this->redirecionarFalhaPagamento(
                $pedidoId,
                'MercadoPago',
                $this->mensagemUsuarioPorHttpCode(0, 'MercadoPago'),
                'Erro cURL ao criar preferência: ' . $curlErr,
                ['curl_error' => $curlErr]
            );
        }

        if ($code === 201) {
            $data = json_decode((string)$resp, true);
            if (!is_array($data)) {
                $this->redirecionarFalhaPagamento(
                    $pedidoId,
                    'MercadoPago',
                    'Recebemos uma resposta inesperada do MercadoPago. Tente novamente em instantes.',
                    'Resposta de sucesso com JSON inválido: ' . json_last_error_msg(),
                    ['http_code' => $code, 'body_preview' => substr((string)$resp, 0, 500)]
                );
            }
            $pagamentoId = Pagamento::criar($pedidoId, 'mercadopago', (float)$pedido['custo_estimado'], 0, 0);
            if (!$pagamentoId) {
                $this->redirecionarFalhaPagamento(
                    $pedidoId,
                    'MercadoPago',
                    'O pagamento foi preparado, mas não conseguimos registrar a transação. Tente novamente.',
                    'Pagamento::criar retornou false após preferência MercadoPago criada.',
                    ['http_code' => $code, 'preference_id' => $data['id'] ?? null]
                );
            }
            // §PAY-SANDBOX-01: o Mercado Pago descontinuou o prefixo "TEST-" nas
            // credenciais de teste (hoje usam "APP_USR-" igual produção — mesmo
            // formato validado em SandboxPaymentConfigTest), então checar o prefixo
            // do token pra decidir a URL de checkout sempre caía no ramo de produção
            // mesmo com MP_ENV=sandbox. A fonte de verdade correta é MP_ENV.
            $checkoutUrl = (MP_ENV === 'sandbox')
                ? ($data['sandbox_init_point'] ?? $data['init_point'] ?? '')
                : ($data['init_point'] ?? '');
            if ($checkoutUrl === '') {
                $this->redirecionarFalhaPagamento(
                    $pedidoId,
                    'MercadoPago',
                    'O MercadoPago não retornou o link de checkout. Tente novamente em instantes.',
                    'Preferência criada sem init_point/sandbox_init_point.',
                    ['http_code' => $code, 'preference_id' => $data['id'] ?? null]
                );
            }
            header('Location: ' . $checkoutUrl); exit;
        }

        $detalhe = $this->extrairErroMercadoPago((string)$resp, $code);
        $this->redirecionarFalhaPagamento(
            $pedidoId,
            'MercadoPago',
            $this->mensagemUsuarioPorHttpCode($code, 'MercadoPago'),
            $detalhe,
            ['http_code' => $code, 'body_preview' => substr((string)$resp, 0, 500)]
        );
    }

    public function iniciarPagSeguro(?int $pedidoId = null): void
    {
        AuthService::requireAuth('cliente');
        $pedidoId = $pedidoId ?? (int)($_POST['pedido_id'] ?? 0);
        if (!AuthService::validarCsrfToken($_POST['csrf_token']??'')) {
            if ($pedidoId > 0) {
                $this->redirecionarFalhaPagamento(
                    $pedidoId,
                    'PagSeguro',
                    'Sua sessão expirou antes de iniciar o pagamento. Atualize a página e tente novamente.',
                    'CSRF token inválido ao iniciar checkout PagSeguro.'
                );
            }
            http_response_code(403);
            exit;
        }
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido) {
            $this->setFlashMessage('Pedido não encontrado para iniciar pagamento.', 'error');
            $this->redirect('/cliente/dashboard');
        }
        if ($this->pagamentoFoiIgnorado() || ($pedido['status'] ?? '') !== 'aguardando_pagamento') {
            $this->redirect('/cliente/dashboard');
        }

        $erroConfig = $this->validarConfigPagSeguro();
        if ($erroConfig !== null) {
            $this->redirecionarFalhaPagamento(
                $pedidoId,
                'PagSeguro',
                'PagSeguro não está disponível para este checkout no momento. Use a opção ativa de pagamento.',
                $erroConfig
            );
        }

        $usuario = AuthService::getCurrentUser();
        $xml = '<?xml version="1.0" encoding="UTF-8"?><checkout>'
             . '<currency>BRL</currency>'
             . '<redirectURL>' . $this->urlApp("/pagamento/sucesso/{$pedidoId}") . '</redirectURL>'
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
        if ($ca = ca_bundle_path()) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }
        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $this->redirecionarFalhaPagamento(
                $pedidoId,
                'PagSeguro',
                $this->mensagemUsuarioPorHttpCode(0, 'PagSeguro'),
                'Erro cURL ao criar checkout: ' . $curlErr,
                ['curl_error' => $curlErr]
            );
        }

        $xmlResp = false;
        $code = '';
        if ($resp) {
            // §PS-XML-01: parse com SimpleXML
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string((string)$resp);
            $code = $xmlResp !== false ? trim((string)($xmlResp->code ?? '')) : '';
        }

        if ($xmlResp !== false && $code !== '') {
            $pagamentoId = Pagamento::criar($pedidoId, 'pagseguro', (float)$pedido['custo_estimado'], 0, 0);
            if (!$pagamentoId) {
                $this->redirecionarFalhaPagamento(
                    $pedidoId,
                    'PagSeguro',
                    'O pagamento foi preparado, mas não conseguimos registrar a transação. Tente novamente.',
                    'Pagamento::criar retornou false após checkout PagSeguro criado.',
                    ['http_code' => $httpCode, 'checkout_code' => $code]
                );
            }
            header('Location: ' . $psCheckoutUrl . '/v2/checkout/payment.html?code=' . urlencode($code));
            exit;
        }

        $detalhe = $this->extrairErroPagSeguro((string)$resp, $httpCode);
        $this->redirecionarFalhaPagamento(
            $pedidoId,
            'PagSeguro',
            $this->mensagemUsuarioPorHttpCode($httpCode, 'PagSeguro'),
            $detalhe,
            ['http_code' => $httpCode, 'body_preview' => substr((string)$resp, 0, 500)]
        );
    }

    public function sucesso(?int $pedidoId = null): void
    {
        AuthService::requireAuth('cliente');
        $pedido = $this->pedidoContexto($pedidoId);
        require __DIR__ . '/../Views/pagamento/sucesso.php';
    }

    public function falha(?int $pedidoId = null): void
    {
        AuthService::requireAuth('cliente');
        $pedido = $this->pedidoContexto($pedidoId);
        $resolvedPedidoId = (int)($pedido['id'] ?? $pedidoId ?? 0);
        $falha = $resolvedPedidoId > 0 ? ($_SESSION[self::FAILURE_SESSION_KEY][$resolvedPedidoId] ?? null) : null;
        if ($falha && $resolvedPedidoId > 0) {
            unset($_SESSION[self::FAILURE_SESSION_KEY][$resolvedPedidoId]);
        } else {
            if ($resolvedPedidoId > 0) {
                [$mensagemUsuario, $detalheDev, $contexto] = $this->normalizarFalhaRetornoGateway();
                $this->registrarFalhaPagamento($resolvedPedidoId, 'Gateway', $mensagemUsuario, $detalheDev, $contexto);
                $falha = $_SESSION[self::FAILURE_SESSION_KEY][$resolvedPedidoId] ?? null;
                unset($_SESSION[self::FAILURE_SESSION_KEY][$resolvedPedidoId]);
            } else {
                $falha = [
                    'mensagem_usuario' => 'Não foi possível concluir o pagamento agora.',
                    'detalhe_dev' => null,
                    'codigo' => null,
                    'gateway' => 'Gateway',
                ];
            }
        }

        $motivoPagamento = $falha['mensagem_usuario'] ?? 'Não foi possível concluir o pagamento agora.';
        $detalhePagamento = $falha['detalhe_dev'] ?? null;
        $codigoDiagnostico = $falha['codigo'] ?? null;
        $gatewayPagamento = $falha['gateway'] ?? null;
        $mostrarDetalheDev = defined('APP_ENV') && APP_ENV !== 'production';
        require __DIR__ . '/../Views/pagamento/falha.php';
    }

    public function pendente(?int $pedidoId = null): void
    {
        AuthService::requireAuth('cliente');
        $pedido = $this->pedidoContexto($pedidoId);
        require __DIR__ . '/../Views/pagamento/pendente.php';
    }
}
