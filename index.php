<?php

// File: guinchafacil/index.php

// Router (front-controller) â€” roda em public_html (raiz) ou em subpasta automaticamente.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Â§OUTPUT-BUFFER-01: qualquer warning/notice do PHP (ex.: fsockopen do
// PHPMailer falhando em DNS/SMTP durante o envio de notificaÃ§Ã£o â€” visto em
// produÃ§Ã£o contaminando a resposta JSON do checkout transparente com
// "Resposta nÃ£o-JSON (HTTP 200)") Ã© impresso direto no corpo da resposta se
// display_errors estiver ligado, ANTES do json_encode() do controller.
// Bufferizar a saÃ­da inteira e deixar os endpoints JSON (responderJson() no
// PagamentoController, por exemplo) descartarem esse lixo com ob_clean()
// antes de emitir o corpo real garante que a resposta HTTP nunca fica
// corrompida por efeitos colaterais de cÃ³digo que nÃ£o deveriam gerar saÃ­da.
ob_start();

$requestId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '') {
    $requestId = 'req_' . bin2hex(random_bytes(8));
}
if (!defined('REQUEST_ID')) {
    define('REQUEST_ID', $requestId);
}

$cspScriptNonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
if (!defined('CSP_SCRIPT_NONCE')) {
    define('CSP_SCRIPT_NONCE', $cspScriptNonce);
}
if (!function_exists('csp_script_nonce_attr')) {
    function csp_script_nonce_attr(): string
    {
        return defined('CSP_SCRIPT_NONCE')
            ? ' nonce="' . htmlspecialchars(CSP_SCRIPT_NONCE, ENT_QUOTES, 'UTF-8') . '"'
            : '';
    }
}

// Â§PAY-CSP-01: form-action sÃ³ com 'self' bloqueava o prÃ³prio checkout â€”
// PagamentoController::iniciarMercadoPago()/iniciarPagSeguro() terminam com um
// redirect 302 do POST do formulÃ¡rio direto pro checkout do gateway (domÃ­nio
// externo), e o Chrome aplica form-action tambÃ©m no destino final do redirect
// de um form, nÃ£o sÃ³ na origem. Sem os domÃ­nios dos gateways aqui, NENHUM
// pagamento real (produÃ§Ã£o ou sandbox) chegava a abrir o checkout â€” achado
// testando o sandbox MercadoPago (erro no console: "violates ... form-action
// 'self'"). Inclui os domÃ­nios de checkout do MercadoPago (produÃ§Ã£o e
// sandbox) e do PagSeguro (produÃ§Ã£o e sandbox). Mantido mesmo apÃ³s o
// checkout transparente porque o fluxo antigo (iniciarMercadoPago/
// iniciarPagSeguro) continua no cÃ³digo como fallback.
//
// Â§PAY-CSP-02 (checkout transparente): Payment Brick do MercadoPago carrega
// o SDK de sdk.mercadopago.com, usa iframes de secure-fields pra tokenizar
// cartÃ£o sem o nÃºmero passar pelo nosso JS (mercadolibre.com/mercadopago.com),
// e busca assets em http2.mlstatic.com. PagSeguroDirectPayment.js vem de
// stc(.sandbox).pagseguro.uol.com.br. Sem essas origens em script-src/
// frame-src/connect-src, o Brick nÃ£o carrega e a tokenizaÃ§Ã£o de cartÃ£o do
// PagSeguro falha silenciosamente.
// Â§PAY-CSP-03: o Brick injeta seus prÃ³prios <script>/<iframe> filhos em
// runtime (device fingerprint /tracks, secure-fields) sem usar o nosso
// nonce â€” impossÃ­vel prever hash/nonce desses scripts com antecedÃªncia.
// A soluÃ§Ã£o documentada pra SDKs de terceiros assim Ã© 'strict-dynamic':
// com ele, um script jÃ¡ confiÃ¡vel (o sdk.mercadopago.com carregado com
// nosso nonce) pode inserir outros scripts em runtime e o browser confia
// neles automaticamente, sem precisar listar cada host. Browsers que
// suportam strict-dynamic ignoram a allowlist de hosts em script-src (por
// isso o https://unpkg.com/https://cdn.jsdelivr.net continuam como
// fallback pra navegadores mais antigos que nÃ£o suportam strict-dynamic).
// connect-src tambÃ©m precisou de api.mercadolibre.com (telemetria do
// Brick) e http2.mlstatic.com (assets/i18n) alÃ©m dos domÃ­nios de API.
// Â§PAY-CSP-04: o Brick carrega o mÃ³dulo antifraude "device fingerprint"
// (armor) do prÃ³prio Mercado Livre â€” mercadolibre.com/mercadolivre.com,
// nÃ£o sÃ³ mercadopago.com/mlstatic.com. Ele faz XHR (connect-src), carrega
// imagens de tracking (img-src) e abre um iframe de sessÃ£o (frame-src)
// nesses domÃ­nios. Sem eles o Brick renderiza os mÃ©todos de pagamento mas
// trava ao selecionar "CartÃ£o de crÃ©dito" (Ã© esse mÃ³dulo que monta os
// campos de nÃºmero/validade/CVV).
$cspPolicy = "default-src 'self' data: blob:; base-uri 'self'; object-src 'none'; form-action 'self' https://www.mercadopago.com.br https://www.mercadopago.com https://sandbox.mercadopago.com.br https://sandbox.mercadopago.com https://pagseguro.uol.com.br https://sandbox.pagseguro.uol.com.br; frame-ancestors 'self'; frame-src 'self' https://www.mercadopago.com.br https://www.mercadopago.com https://sandbox.mercadopago.com.br https://sandbox.mercadopago.com https://http2.mlstatic.com https://www.mercadolibre.com https://www.mercadolivre.com https://api-static.mercadopago.com https://secure-fields.mercadopago.com; script-src 'self' 'nonce-" . CSP_SCRIPT_NONCE . "' 'strict-dynamic' https://unpkg.com https://cdn.jsdelivr.net https://sdk.mercadopago.com https://http2.mlstatic.com https://api-static.mercadopago.com https://secure-fields.mercadopago.com https://stc.pagseguro.uol.com.br https://stc.sandbox.pagseguro.uol.com.br https://www.googletagmanager.com https://connect.facebook.net; script-src-attr 'none'; style-src 'self' 'unsafe-inline' https://unpkg.com; font-src 'self' data:; img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://http2.mlstatic.com https://www.mercadolibre.com https://www.mercadolivre.com https://www.mercadopago.com https://www.mercadopago.com.br https://sandbox.mercadopago.com https://sandbox.mercadopago.com.br https://api-static.mercadopago.com https://secure-fields.mercadopago.com https://www.facebook.com https://www.googletagmanager.com https://www.google.com https://www.google.com.br; connect-src 'self' https://viacep.com.br https://nominatim.openstreetmap.org https://router.project-osrm.org https://api.mercadopago.com https://sdk.mercadopago.com https://events.mercadopago.com https://api.mercadolibre.com https://www.mercadolibre.com https://www.mercadolivre.com https://api-static.mercadopago.com https://secure-fields.mercadopago.com https://http2.mlstatic.com https://ws.pagseguro.uol.com.br https://ws.sandbox.pagseguro.uol.com.br https://stc.pagseguro.uol.com.br https://stc.sandbox.pagseguro.uol.com.br https://www.google-analytics.com https://region1.google-analytics.com https://www.facebook.com https://www.google.com https://analytics.google.com https://ad.doubleclick.net;";
if (!headers_sent()) {
    header('Content-Security-Policy: ' . $cspPolicy);
    header('X-Request-ID: ' . REQUEST_ID);
}

// Core (alguns controllers fazem extends BaseController)
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Services/AuthService.php';
require_once __DIR__ . '/src/Services/Logger.php';
require_once __DIR__ . '/src/Controllers/BaseController.php';

// â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
// Error handling: registra fatal e exceptions com contexto (pra parar de â€œ500 fantasmaâ€)
// â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
// [A-RESOLVER-BOOT-01] Liga o slug publico ao catalogo real de service_types.
// O callback e lazy: getPDO() so abre conexao se porSlug() for chamado.
require_once __DIR__ . '/src/Services/Catalog/ServiceTypeResolver.php';

\App\Services\Catalog\ServiceTypeResolver::definirLookupPorCodigo(
    static function (string $code): ?int {
        try {
            $stmt = \getPDO()->prepare(
                'SELECT id FROM service_types WHERE code = ? AND active = 1 LIMIT 1'
            );
            $stmt->execute([$code]);
            $id = $stmt->fetchColumn();

            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            try {
                \Logger::event([
                    'level' => \Logger::LEVEL_ERROR,
                    'class' => 'ServiceTypeResolver',
                    'function' => 'lookupPorCodigo',
                    'system' => 'Catalog',
                    'code' => 'STR-LOOKUP-FAIL',
                    'message' => 'Falha ao resolver service_type code=' . $code,
                    'context' => ['code' => $code, 'exception' => $e->getMessage()],
                ]);
            } catch (\Throwable $logError) {
                error_log('[STR-LOOKUP-FAIL] code=' . $code . ' error=' . $e->getMessage());
            }

            return null;
        }
    }
);
set_exception_handler(function (Throwable $e): void {
    Logger::exception('Router', 'exception_handler', 'PHP', $e, [
        'uri'  => $_SERVER['REQUEST_URI'] ?? null,
        'code' => 'RTR-500',
    ]);

    http_response_code(500);
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<h1>Erro interno do servidor</h1>';
        echo '<pre>' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Erro interno do servidor. Tente novamente mais tarde.';
    }
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if (!$err) return;

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'] ?? 0, $fatal, true)) return;

    Logger::event([
        'level' => Logger::LEVEL_ERROR,
        'class' => 'Router',
        'function' => 'shutdown',
        'system' => 'PHP',
        'file' => (string)($err['file'] ?? ''),
        'phase' => 'shutdown',
        'code' => 'RTR-FATAL',
        'message' => (string)($err['message'] ?? 'Fatal error'),
        'context' => [
            'type' => $err['type'] ?? null,
            'file' => $err['file'] ?? null,
            'line' => $err['line'] ?? null,
            'uri'  => $_SERVER['REQUEST_URI'] ?? null,
        ],
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        if (defined("APP_DEBUG") && APP_DEBUG) {
            echo "<h1>Erro fatal no servidor</h1>";
            echo "<pre>" . htmlspecialchars((string)($err["message"] ?? "Fatal error") . "\n" . (string)($err["file"] ?? "") . ":" . (string)($err["line"] ?? ""), ENT_QUOTES, "UTF-8") . "</pre>";
        } else {
            echo "Erro interno do servidor. Tente novamente mais tarde.";
        }
    }
});

// â€”â€” SessÃ£o segura â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();
require_once __DIR__ . '/src/Services/MarketingAttributionService.php';
MarketingAttributionService::capture();

// Regenera ID da sessÃ£o a cada 5 minutos
if (!isset($_SESSION['last_regen'])) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
} elseif (time() - (int)$_SESSION['last_regen'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

// â€”â€” Autoloader simples (sem namespace) â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
spl_autoload_register(function ($classe): void {
    $classe = (string)$classe;
    $paths = [
        __DIR__ . '/src/Controllers/' . $classe . '.php',
        __DIR__ . '/src/Models/'      . $classe . '.php',
        __DIR__ . '/src/Services/'    . $classe . '.php',
    ];
    foreach ($paths as $p) {
        if (is_file($p)) { require_once $p; return; }
    }

    // Fallback recursivo: domÃ­nios organizados em subpastas (Models/Catalog,
    // Models/Dispatch, Models/Vehicle, Services/Dispatch, Services/Pedido,
    // Financial etc.) nÃ£o sÃ£o achados pelos caminhos planos acima. Varre src/
    // uma Ãºnica vez, monta um mapa {ClasseSemExtensao => caminho} e cacheia.
    // Nomes de classe sÃ£o Ãºnicos no projeto, entÃ£o nÃ£o hÃ¡ ambiguidade.
    static $mapa = null;
    if ($mapa === null) {
        $mapa = [];
        $base = __DIR__ . '/src';
        if (is_dir($base)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $arquivo) {
                if ($arquivo->isFile() && $arquivo->getExtension() === 'php') {
                    $nome = $arquivo->getBasename('.php');
                    if (!isset($mapa[$nome])) {
                        $mapa[$nome] = $arquivo->getPathname();
                    }
                }
            }
        }
    }
    if (isset($mapa[$classe]) && is_file($mapa[$classe])) {
        require_once $mapa[$classe];
    }
});

// BasePath: detectado automaticamente.
// Em public_html (raiz) serÃ¡ '' â€” nenhuma configuraÃ§Ã£o necessÃ¡ria.
// Para subpasta, force: define('FORCE_BASEPATH','/minha-subpasta') em config.php
$basePath = '';
if (defined('FORCE_BASEPATH') && trim((string)FORCE_BASEPATH) !== '') {
    $basePath = rtrim((string)FORCE_BASEPATH, '/');
} else {
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($basePath === '.' || $basePath === '/') $basePath = '';
}
if (!defined('BASE_PATH')) define('BASE_PATH', $basePath);
if (isset($_GET['__route_debug'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '') . PHP_EOL;
    echo 'SCRIPT_NAME=' . ($_SERVER['SCRIPT_NAME'] ?? '') . PHP_EOL;
    echo 'PHP_SELF=' . ($_SERVER['PHP_SELF'] ?? '') . PHP_EOL;
    echo 'BASE_PATH=' . $basePath . PHP_EOL;
    exit;
}

// â€”â€” URL atual normalizada â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = strtok($uri, '?') ?: '/';

// Remove basePath do comeÃ§o da URL (rodando em subpasta)
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
    if ($uri === '') $uri = '/';
}

// Normaliza acesso direto ao index.php
if ($uri === '/index.php') $uri = '/';

// Normaliza trailing slash
$uri = rtrim($uri, '/') ?: '/';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
// Rotas (sem gambiarra de espaÃ§amento: mÃ©todo e path separados)
// Formato: $rotas[METODO][PATH] = [Controller, action, perfil]
// â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
$rotas = [

    'GET' => [
        // === ROUTES OFICINA GET ===
        '/oficina/pedido/{id}/status'    => ['OficinaController', 'statusJson',          'oficina'],
        '/oficina/pedido/{id}'           => ['OficinaController', 'atendimento',         'oficina'],
        '/oficina/arquivo/{nome}'        => ['OficinaController', 'servirFoto',          'oficina'],
        '/oficina/dashboard'             => ['OficinaController', 'dashboard',           'oficina'],
        '/oficina/pedidos'               => ['OficinaController', 'pedidosPage',         'oficina'],
        '/oficina/financeiro'            => ['OficinaController', 'financeiro',          'oficina'],
        '/api/pre-cotacao/oficinas-proximas' => ['PedidoController', 'oficinasProximas', null],
        '/api/pre-cotacao/validar-uf-destino' => ['PedidoController', 'validarUfDestino', null],
        '/api/pre-cotacao/endereco/sugestoes' => ['PreCotacaoApiController', 'sugestoes', null],
        '/api/pre-cotacao/endereco/reverso'   => ['PreCotacaoApiController', 'reverso', null],
        '/api/pre-cotacao/cobertura'          => ['PreCotacaoApiController', 'cobertura', null],
        '/api/pre-cotacao/triagem-servicos'   => ['PreCotacaoApiController', 'triagemServicos', null],
        '/api/pre-cotacao/opcoes'              => ['PreCotacaoApiController', 'opcoes', null],
        '/oficina/historico'             => ['OficinaController', 'historico',           'oficina'],
        '/oficina/perfil'                => ['OficinaController', 'perfilForm',          'oficina'],

        '/'                 => ['AuthController', 'landing', null],
        '/parceiros/interesse' => ['AuthController', 'parceirosInteresse', null],
        '/sitemap.xml'      => ['AuthController', 'sitemap', null],
        '/login'            => ['AuthController', 'loginForm', null],

        '/pre-cotacao'      => ['AuthController', 'preCotacaoForm', null],
        '/guincho'          => ['AuthController', 'cidadesPublicas', null],
        '/logout'           => ['AuthController', 'logout', null],

        '/auth/session-status' => ['AuthController', 'sessionStatus', null],
        '/auth/google' => ['AuthController', 'googleRedirect', null],
        '/auth/google/callback' => ['AuthController', 'googleCallback', null],
        '/auth/google/profile' => ['AuthController', 'googleProfileForm', null],
        '/auth/magic'          => ['AuthController', 'magicEnviado', null],
        '/senha/esqueceu'         => ['AuthController', 'esqueceuSenhaForm', null],

        '/registro/cliente' => ['AuthController', 'registroClienteForm', null],
        '/registro/guincho' => ['AuthController', 'registroGuinchoForm', null],
        '/registro/especialista' => ['AuthController', 'registroEspecialistaForm', null],

        '/cliente/dashboard'    => ['ClienteController', 'dashboard', 'cliente'],
        '/cliente/incidente/orcamento/aprovar/{id}' => ['IncidenteController', 'aprovarOrcamento', 'cliente'],
        '/cliente/incidente/orcamento/recusar/{id}' => ['IncidenteController', 'recusarOrcamento', 'cliente'],
        '/cliente/incidente/reboque/{id}' => ['IncidenteController', 'solicitarReboque', 'cliente'],
        '/cliente/veiculos'     => ['ClienteController', 'veiculos', 'cliente'],
        '/cliente/veiculo/novo'  => ['ClienteController', 'veiculoForm', 'cliente'],
        '/cliente/veiculos/novo' => ['ClienteController', 'veiculoForm', 'cliente'],
        '/cliente/oficinas'      => ['ClienteController', 'oficinas', 'cliente'],
        '/cliente/oficina/nova'  => ['ClienteController', 'oficinaForm', 'cliente'],
        '/cliente/oficinas/nova' => ['ClienteController', 'oficinaForm', 'cliente'],
        '/cliente/pedido/novo'  => ['ClienteController', 'pedidoNovo', 'cliente'],
        '/cliente/oficinas-parceiras' => ['OficinaParceriaController', 'clienteOficinas', 'cliente'],
        '/cliente/triagem'          => ['ClienteController', 'triagem', 'cliente'],
        '/cliente/triagem/resultado' => ['ClienteController', 'triagemResultado', 'cliente'],
        '/cliente/pedido/rascunho' => ['ClienteController', 'pedidoRascunho', 'cliente'],
        '/cliente/historico'    => ['ClienteController', 'historico', 'cliente'],
        '/cliente/financeiro'   => ['ClienteController', 'financeiro', 'cliente'],
        '/cliente/perfil'       => ['ClienteController', 'perfilForm', 'cliente'],
        '/cliente/pedido/custo' => ['ClienteController', 'calcularCusto', 'cliente'],
        '/geocode'              => ['GeocodeController', 'search', null],
        '/geocode/public'       => ['GeocodeController', 'searchPublic', null],
        '/geocode/reverse'      => ['GeocodeController', 'reverse', null],
        '/comunicados/carousel' => ['ComunicadoController', 'carousel', null],
        '/sse/pedidos'          => ['SseController', 'pedidosDisponiveis', null],
        '/sse/admin/pedidos'    => ['SseController', 'adminPedidos', 'admin'],

        '/funcionario/dashboard'  => ['FuncionarioController', 'dashboard', 'funcionario'],
        '/funcionario/pedidos'    => ['FuncionarioController', 'pedidos', 'funcionario'],
        '/funcionario/financeiro' => ['FuncionarioController', 'financeiro', 'funcionario'],
        '/funcionario/demandas'   => ['FuncionarioController', 'demandas', 'funcionario'],
        '/funcionario/demandas/nova' => ['FuncionarioController', 'demandaNovaForm', 'funcionario'],

        '/gerente/dashboard'  => ['GerenteController', 'dashboard', 'gerente'],
        '/gerente/demandas'   => ['GerenteController', 'demandas', 'gerente'],

        '/guincho/dashboard'  => ['GuinchoController', 'dashboard', 'guincho'],
        '/guincho/pedidos-disponiveis' => ['GuinchoController', 'pedidosDisponiveis', 'guincho'],
        '/oficina/pedidos-disponiveis' => ['OficinaController', 'pedidosDisponiveis', 'oficina'],

        '/guincho/pedidos'    => ['GuinchoController', 'pedidosDisponiveis', 'guincho'],
        '/guincho/historico'  => ['GuinchoController', 'historico', 'guincho'],
        '/guincho/financeiro' => ['GuinchoController', 'financeiro', 'guincho'],
        '/guincho/perfil'     => ['GuinchoController', 'perfilForm', 'guincho'],
        '/guincho/operacao'   => ['GuinchoController', 'perfilOperacaoForm', 'guincho'],
        '/guincho/bancario'   => ['GuinchoController', 'perfilBancarioForm', 'guincho'],
        '/guincho/capacidades' => ['GuinchoController', 'capacidades', 'guincho'],
        '/especialista/dashboard' => ['EspecialistaController', 'dashboard', 'especialista'],
        '/especialista/perfil' => ['EspecialistaController', 'perfilForm', 'especialista'],
        '/especialista/servicos' => ['EspecialistaController', 'servicos', 'especialista'],
        '/especialista/notificacoes' => ['EspecialistaController', 'notificacoes', 'especialista'],
        '/especialista/disponibilidade' => ['EspecialistaController', 'disponibilidade', 'especialista'],
        '/especialista/perfil/salvar' => ['EspecialistaController', 'perfilSalvar', 'especialista'],
        '/especialista/atendimento/{id}/aceitar' => ['EspecialistaController', 'aceitar', 'especialista'],
        '/especialista/atendimento/{id}/status' => ['EspecialistaController', 'transicionar', 'especialista'],
        '/guincho/tornar-se-guincho' => ['GuinchoController', 'tornarSeGuincho', 'guincho'],
        
        '/admin/central'             => ['AdminController', 'centralOperacional', 'admin'],
        '/admin/alertas'             => ['AdminController', 'alertasOperacionais', 'admin'],
        '/admin/despacho'            => ['AdminController', 'despacho', 'admin'],
        '/admin/ocorrencias'         => ['AdminController', 'ocorrencias', 'admin'],
        '/admin/avaliacoes'          => ['AdminController', 'avaliacoes', 'admin'],
        '/admin/documentos'          => ['AdminController', 'documentos', 'admin'],
        '/admin/proof-of-road'       => ['AdminController', 'proofOfRoad', 'admin'],
        '/admin/carteiras'           => ['AdminController', 'carteiras', 'admin'],
        '/admin/saques'              => ['AdminController', 'saques', 'admin'],
        '/admin/ocorrencia/criar'    => ['AdminController', 'ocorrenciaCriar', 'admin'],
        '/admin/ocorrencia/resolver' => ['AdminController', 'ocorrenciaResolver', 'admin'],
        '/admin/dashboard'           => ['AdminController', 'dashboard', 'admin'],
        '/admin/dashboard/json'      => ['AdminController', 'dashboardJson', 'admin'],
        '/admin/dashboard/mapa-json' => ['AdminController', 'dashboardMapaJson', 'admin'],
        '/api/admin/orders'          => ['OrdersApiController', 'index', 'admin'],
        '/admin/especialistas' => ['AdminController', 'especialistas', 'admin'],
        '/admin/especialistas/cadastrar' => ['AdminController', 'especialistaCadastroForm', 'admin'],
        '/admin/especialista-fragmento/{id}' => ['AdminController', 'especialistaDetalheFragmento', 'admin'],
        '/admin/especialista/documento-status' => ['AdminController', 'especialistaDocumentoStatus', 'admin'],
        '/admin/usuarios' => ['AdminController', 'usuarios', 'admin'],
        '/admin/usuario/novo'        => ['AdminController', 'usuarioForm', 'admin'],
        '/admin/usuario/suspender'   => ['AdminController', 'usuariosSuspenderGet', 'admin'],
        '/admin/prestadores'         => ['AdminController', 'prestadores', 'admin'],
        '/admin/guinchos'            => ['AdminController', 'guinchos', 'admin'],
        '/admin/guinchospendentes'   => ['AdminController', 'guinchosPendentes', 'admin'],
        '/admin/guincho/novo'        => ['AdminController', 'guinchoNovoForm', 'admin'],
        '/admin/pedidos'             => ['AdminController', 'pedidos', 'admin'],
        '/admin/faturas'                 => ['AdminController', 'faturas',           'admin'],
        '/admin/fatura/{id}'             => ['AdminController', 'faturaDetalhe',     'admin'],
        '/admin/fatura/{id}/marcar-paga' => ['AdminController', 'faturaMarcarPaga',  'admin'],
        '/admin/fatura/{id}/desbloquear' => ['AdminController', 'faturaDesbloquear', 'admin'],
        '/admin/pedidos/json'        => ['AdminController', 'pedidosJson', 'admin'],
        '/admin/pedido/novo'         => ['AdminController', 'pedidoNovoV2', 'admin'],
        '/admin/pedido/novo/v2'       => ['AdminController', 'pedidoNovoV2',    'admin'],
        '/admin/pedido/novo/funil'    => ['AdminController', 'pedidoNovoFunil', 'admin'],
        '/admin/pedido/criar'        => ['AdminController', 'pedidoCriar',     'admin'],
        '/admin/pedido/custo'        => ['AdminController', 'pedidoCalcularCusto', 'admin'],
        '/admin/veiculos/ajax'       => ['AdminController', 'veiculosAjax', 'admin'],
        '/admin/clientes/ajax'       => ['AdminController', 'clientesAjax', 'admin'],
        '/admin/oficinas/ajax'       => ['AdminController', 'oficinasAjax', 'admin'],
        '/admin/financeiro'          => ['AdminController', 'financeiro', 'admin'],
        '/admin/oficinas-parceiras'  => ['OficinaParceriaController', 'admin', 'admin'],
        '/admin/prestadores-moveis' => ['AdminController', 'prestadoresMoveis', 'admin'],
        '/admin/financeiro/bypass-alertas' => ['OficinaParceriaController', 'bypassCasos', 'admin'],
        '/admin/financeiro/bypass-alertas/decidir' => ['OficinaParceriaController', 'bypassDecidir', 'admin'],
        '/admin/financeiro/csv'      => ['AdminController', 'exportarCsv', 'admin'],
        '/admin/financeiro/visao-unificada' => ['AdminFinanceAttributionController', 'visaoUnificada', 'admin'],
        '/admin/marketing' => ['AdminFinanceAttributionController', 'marketingCentral', 'admin'],
        '/admin/marketing/campanha/salvar' => ['AdminFinanceAttributionController', 'campanhaSalvar', 'admin'],
        '/admin/marketing/prospeccao/buscar' => ['AdminFinanceAttributionController', 'prospeccaoBuscar', 'admin'],
        '/admin/marketing/prospeccao/regioes/salvar' => ['AdminFinanceAttributionController', 'prospeccaoRegiaoSalvar', 'admin'],
        '/admin/marketing/prospeccao/sincronizar-zonas' => ['AdminFinanceAttributionController', 'prospeccaoSincronizarZonas', 'admin'],
        '/admin/prospeccao/sincronizar-zonas' => ['AdminFinanceAttributionController', 'prospeccaoSincronizarZonas', 'admin'],
        '/admin/financeiro/marketing/gasto' => ['AdminFinanceAttributionController', 'gastoSalvar', 'admin'],
        '/admin/financeiro/marketing/importar' => ['AdminFinanceAttributionController', 'gastoImportar', 'admin'],
        '/admin/financeiro/marketing/excluir' => ['AdminFinanceAttributionController', 'gastoExcluir', 'admin'],
        '/admin/financeiro/visao-unificada/csv' => ['AdminFinanceAttributionController', 'exportarCsv', 'admin'],
        '/admin/prospeccao' => ['AdminFinanceAttributionController', 'marketingCentral', 'admin'],
        '/admin/prospeccao/regioes' => ['AdminFinanceAttributionController', 'marketingCentral', 'admin'],
        '/admin/prospeccao/buscar' => ['AdminFinanceAttributionController', 'prospeccaoBuscar', 'admin'],
        '/admin/prospeccao/regioes/salvar' => ['AdminFinanceAttributionController', 'prospeccaoRegiaoSalvar', 'admin'],
        '/admin/configuracoes'       => ['AdminController', 'configuracoes', 'admin'],
        '/admin/logs'                => ['AdminLogsController', 'index', 'admin'],
        '/admin/logs/export'         => ['AdminController', 'logsExport', 'admin'],
        '/admin/comunicados'         => ['AdminComunicadoController', 'index', 'admin'],
        '/admin/comunicado/novo'     => ['AdminComunicadoController', 'form', 'admin'],
        '/admin/chat'                => ['AdminChatController', 'index', 'admin'],
        '/admin/health'              => ['AdminHealthController', 'health', 'admin'],
        '/admin/simulador'           => ['AdminController', 'simulador', 'admin'],
        '/admin/env'                 => ['AdminController', 'configuracoes', 'admin'],
        '/admin/env/auditoria'       => ['AdminEnvAuditController', 'index', 'admin'],
        '/admin/servicos'            => ['AdminController', 'servicos', 'admin'],
        '/admin/servico/novo'        => ['AdminController', 'servicoForm', 'admin'],
        '/admin/feriados'            => ['AdminController', 'feriados', 'admin'],
        '/admin/cidades'             => ['AdminController', 'cidades', 'admin'],
        '/admin/cidade/excluir'      => ['AdminController', 'cidadeExcluir', 'admin'],

        // ROADMAP socorro automotivo â€” Etapa 1: catÃ¡logo estruturado (service_types),
        // distinto de /admin/servicos (atalhos rÃ¡pidos do painel do cliente).
        '/admin/catalogo-servicos/tipos'        => ['AdminServiceCatalogController', 'tipos', 'admin'],
        '/admin/catalogo-servicos/tipo/novo'    => ['AdminServiceCatalogController', 'tipoForm', 'admin'],
        '/admin/catalogo-servicos/capacidades'  => ['AdminServiceCatalogController', 'capacidades', 'admin'],
        '/admin/catalogo-servicos/tarifas'      => ['AdminServiceCatalogController', 'tarifas', 'admin'],
        '/admin/catalogo-servicos/compatibilidade' => ['AdminServiceCatalogController', 'compatibilidade', 'admin'],

        '/admin/catalogo-veiculos'             => ['AdminVehicleCatalogController', 'marcas', 'admin'],
        '/admin/catalogo-veiculos/marca/novo'   => ['AdminVehicleCatalogController', 'marcaForm', 'admin'],
        '/admin/catalogo-veiculos/modelo/novo'  => ['AdminVehicleCatalogController', 'modeloForm', 'admin'],
        '/admin/catalogo-veiculos/versao/novo'  => ['AdminVehicleCatalogController', 'versaoForm', 'admin'],

        // Â§CATALOGO-VISUAL-01: pÃºblicas de propÃ³sito (sem perfil) â€” o
        // cadastro de caminhÃ£o do guincheiro acontece ANTES do login
        // existir (registro pÃºblico), e reaproveita o MESMO catÃ¡logo do
        // cliente. SÃ³ leitura, sem dado sensÃ­vel.
        '/veiculo-catalogo/marcas'  => ['VehicleCatalogController', 'marcas', null],
        '/veiculo-catalogo/modelos' => ['VehicleCatalogController', 'modelos', null],

        // ROADMAP socorro automotivo â€” Etapa 13: precificaÃ§Ã£o por zona/cidade
        // (pricing_zones/service_price_rules) â€” schema existia desde
        // migration_pricing_zones_v1.sql, mas sem tela admin nenhuma atÃ©
        // 26/07/2026.
        '/admin/precificacao/zonas' => ['AdminPricingZoneController', 'zonas', 'admin'],
        '/admin/demanda-territorial' => ['AdminPricingZoneController', 'demandaTerritorial', 'admin'],
        '/admin/produtos'            => ['AdminProdutoController', 'index', 'admin'],
        '/admin/produto/novo'        => ['AdminProdutoController', 'form', 'admin'],
        '/admin/checklists-incompletos' => ['AdminProofOfServiceController', 'checklistsIncompletos', 'admin'],
        '/admin/planejamento'        => ['AdminPlanejamentoController', 'index', 'admin'],
        '/guincho/estoque'           => ['GuinchoController', 'estoque', 'guincho'],

        '/checkout/cliente' => ['CheckoutController', 'formCliente', null],


        '/checkout/veiculo' => ['CheckoutController', 'formVeiculo', 'cliente'],


        '/checkout/pagar'   => ['CheckoutController', 'pagar', 'cliente'],


        '/pagamento/sucesso'  => ['PagamentoController', 'sucesso', 'cliente'],
        '/pagamento/falha'    => ['PagamentoController', 'falha', 'cliente'],
        '/pagamento/pendente' => ['PagamentoController', 'pendente', 'cliente'],
    ],

    'POST' => [
    
    '/oficina/pedido/{id}/cancelar' => ['OficinaController', 'cancelarAtendimento', 'oficina'],
        '/admin/pedido/novo/contexto' => ['AdminController', 'pedidoNovoContexto', 'admin'],
                '/admin/pedido/novo/api/cliente' => ['AdminController', 'pedidoNovoApiClienteCriar', 'admin'],
        '/admin/pedido/novo/api/veiculo' => ['AdminController', 'pedidoNovoApiVeiculoCriar', 'admin'],
        // === ROUTES OFICINA POST ===
        '/oficina/perfil/salvar'          => ['OficinaController', 'perfilSalvar',        'oficina'],
        '/oficina/servicos/salvar'        => ['OficinaController', 'salvarServicos',      'oficina'],
        '/oficina/disponibilidade'        => ['OficinaController', 'disponibilidade',     'oficina'],
        '/oficina/localizacao'            => ['OficinaController', 'atualizarLocalizacao','oficina'],
        '/oficina/pedido/{id}/atualizar'  => ['OficinaController', 'atualizarStatus',     'oficina'],
        '/oficina/recusar/{id}'           => ['OficinaController', 'recusar',             'oficina'],
        '/oficina/pedido/{id}/evidencia'  => ['OficinaController', 'registrarEvidencia',  'oficina'],
        '/oficina/pedido/{id}/orcamento'  => ['OficinaController', 'enviarOrcamento',     'oficina'],
        '/oficina/pedido/{id}/aceitar'    => ['OficinaController', 'aceitar',             'oficina'],
        '/cliente/pedido/{id}/orcamento-oficina/responder' => ['ClienteController', 'responderOrcamentoOficina', 'cliente'],

        '/pre-cotacao'      => ['AuthController', 'preCotacao', null],
        '/pre-cotacao/aceitar' => ['AuthController', 'aceitarPreCotacao', null],
        '/api/pre-cotacao/decisao' => ['PedidoController', 'decisaoPreCotacao', null],
        '/api/pre-cotacao/whatsapp' => ['PreCotacaoApiController', 'whatsapp', null],
        '/pedido/cotar' => ['PedidoController', 'cotar', null],
        '/pedido/criar' => ['PedidoController', 'criar', null],
        '/login'                 => ['AuthController', 'login', null],
        '/auth/google/profile'   => ['AuthController', 'googleProfileSave', null],
        '/auth/magic/solicitar' => ['AuthController', 'magicSolicitar', null],
        '/push/subscribe'         => ['PushSubscriptionController', 'subscribe', null],
        '/push/unsubscribe'       => ['PushSubscriptionController', 'unsubscribe', null],
        '/registro/cliente'       => ['AuthController', 'registroCliente', null],
        '/registro/guincho'       => ['AuthController', 'registroGuincho', null],
        
        
        '/registro/especialista'  => ['AuthController', 'registroEspecialista', null],
        '/especialista/atendimento/aceitar/' => ['EspecialistaController', 'aceitar', 'especialista'],
        '/especialista/atendimento/status/' => ['EspecialistaController', 'transicionar', 'especialista'],
        '/admin/especialista/aprovar' => ['AdminController', 'especialistaAprovar', 'admin'],
        '/admin/especialista/suspender' => ['AdminController', 'especialistaSuspender', 'admin'],
        '/senha/esqueceu'         => ['AuthController', 'esqueceuSenha',     null],
        '/senha/redefinir'        => ['AuthController', 'redefinirSenha',    null],

        '/cliente/veiculo/salvar'  => ['ClienteController', 'veiculoSalvar', 'cliente'],
        '/cliente/veiculo/deletar' => ['ClienteController', 'veiculoDeletar', 'cliente'],
        '/cliente/oficina/salvar'  => ['ClienteController', 'oficinaSalvar', 'cliente'],
        '/cliente/oficina/deletar' => ['ClienteController', 'oficinaDeletar', 'cliente'],
        '/cliente/pedido/criar'    => ['ClienteController', 'pedidoCriar', 'cliente'],
        '/cliente/pedido/orcamento-previo/aprovar' => ['ClienteController', 'orcamentoPrevioAprovar', 'cliente'],
        '/cliente/pedido/oficina-parceira/selecionar' => ['OficinaParceriaController', 'selecionar', 'cliente'],
        '/guincho/pedido/checkin-oficina' => ['OficinaParceriaController', 'checkin', 'guincho'],
        '/admin/oficina/cadastrar' => ['OficinaParceriaController', 'cadastrar', 'admin'],
        '/admin/oficina/aprovar' => ['OficinaParceriaController', 'aprovar', 'admin'],
        '/admin/oficina/suspender' => ['OficinaParceriaController', 'suspender', 'admin'],
        '/admin/oficina/reativar' => ['OficinaParceriaController', 'reativar', 'admin'],
        '/admin/oficina/atualizar' => ['OficinaParceriaController', 'atualizar', 'admin'],
        '/admin/prestador-movel/regra-orcamento' => ['AdminController', 'salvarRegraOrcamentoPrestador', 'admin'],
        '/admin/oficina/indicacao/revisar' => ['OficinaParceriaController', 'revisar', 'admin'],
        '/admin/oficina/indicacao/estornar' => ['OficinaParceriaController', 'estornar', 'admin'],

        '/cliente/triagem/responder' => ['ClienteController', 'triagemResponder', 'cliente'],
        '/cliente/triagem/avaliar' => ['ClienteController', 'triagemAvaliarJson', 'cliente'],
        '/cliente/perfil/salvar'   => ['ClienteController', 'perfilSalvar', 'cliente'],
        '/cliente/chat/enviar'     => ['ClienteController', 'chatEnviar', 'cliente'],

        '/guincho/localizacao'      => ['GuinchoController', 'atualizarLocalizacao', 'guincho'],
        '/guincho/disponibilidade'  => ['GuinchoController', 'toggleDisponibilidade', 'guincho'],
        '/guincho/perfil/salvar'    => ['GuinchoController', 'perfilSalvar',          'guincho'],
        '/guincho/operacao/salvar'  => ['GuinchoController', 'perfilOperacaoSalvar',  'guincho'],
        '/guincho/bancario/salvar'  => ['GuinchoController', 'perfilBancarioSalvar',  'guincho'],
        '/guincho/capacidades/salvar' => ['GuinchoController', 'capacidadesSalvar', 'guincho'],
        '/guincho/tornar-se-guincho/salvar' => ['GuinchoController', 'tornarSeGuinchoSalvar', 'guincho'],
        '/guincho/chat/enviar'      => ['GuinchoController', 'chatEnviar', 'guincho'],

        '/admin/usuario/ativar'      => ['AdminController', 'usuarioAtivar', 'admin'],
        '/admin/usuario/suspender'   => ['AdminController', 'usuarioSuspender', 'admin'],
        '/admin/usuario/salvar'      => ['AdminController', 'usuarioSalvar', 'admin'],
        '/admin/usuario/atualizar'   => ['AdminController', 'usuarioAtualizar', 'admin'],
        '/admin/usuario/senha'       => ['AdminController', 'usuarioSenha', 'admin'],
        '/admin/guincho/aprovar'     => ['AdminController', 'guinchoAprovar', 'admin'],
        '/admin/guincho/rejeitar'    => ['AdminController', 'guinchoRejeitar', 'admin'],
        '/admin/guincho/criar'       => ['AdminController', 'guinhoCriar', 'admin'],
        '/admin/guincho/atualizar'   => ['AdminController', 'guinchoAtualizar', 'admin'],
        '/admin/pedido/criar'        => ['AdminController', 'pedidoCriar', 'admin'],
        '/admin/pedido/status'       => ['AdminController', 'pedidoAlterarStatus', 'admin'],
        '/admin/pedido/cancelar'     => ['AdminController', 'pedidoCancelar', 'admin'],
        '/admin/pedido/atribuir'     => ['AdminController', 'pedidoAtribuir', 'admin'],
        '/admin/pedido/concluir-manual' => ['AdminController', 'pedidoConcluirManual', 'admin'],
        '/admin/pedido/revisar-manual'  => ['AdminController', 'pedidoRevisarManual', 'admin'],

        '/admin/configuracoes'       => ['AdminController', 'configuracoesSalvar', 'admin'],
        '/admin/configuracoes/atualizar-banco' => ['AdminController', 'atualizarBancoPeloPainel', 'admin'],
        '/admin/env/salvar'          => ['AdminController', 'envSalvar', 'admin'],
        '/admin/simulador'           => ['AdminController', 'simularExecutar', 'admin'],
        '/admin/qa/run'              => ['AdminController', 'qaExecutar', 'admin'],
        '/admin/comunicado/salvar'   => ['AdminComunicadoController', 'save', 'admin'],
        '/admin/servico/salvar'      => ['AdminController', 'servicoSalvar', 'admin'],
        '/admin/servico/alternar'    => ['AdminController', 'servicoAlternar', 'admin'],
        '/admin/servico/remover'     => ['AdminController', 'servicoRemover', 'admin'],

        '/admin/catalogo-servicos/tipo/salvar'        => ['AdminServiceCatalogController', 'tipoSalvar', 'admin'],
        '/admin/catalogo-servicos/capacidade/decidir' => ['AdminServiceCatalogController', 'capacidadeDecidir', 'admin'],
        '/admin/catalogo-servicos/tarifa/salvar'      => ['AdminServiceCatalogController', 'tarifaSalvar', 'admin'],
        '/admin/catalogo-servicos/tarifa/remover-override' => ['AdminServiceCatalogController', 'tarifaRemoverOverride', 'admin'],
        '/admin/catalogo-servicos/requisito/salvar'   => ['AdminServiceCatalogController', 'requisitoSalvar', 'admin'],
        '/admin/catalogo-servicos/capacidade-veicular/salvar' => ['AdminServiceCatalogController', 'capacidadeVeicularSalvar', 'admin'],

        '/admin/catalogo-veiculos/marca/salvar'  => ['AdminVehicleCatalogController', 'marcaSalvar', 'admin'],
        '/admin/catalogo-veiculos/modelo/salvar' => ['AdminVehicleCatalogController', 'modeloSalvar', 'admin'],
        '/admin/catalogo-veiculos/versao/salvar' => ['AdminVehicleCatalogController', 'versaoSalvar', 'admin'],

        '/admin/precificacao/zona/salvar' => ['AdminPricingZoneController', 'zonaSalvar', 'admin'],
        '/admin/precificacao/regra/salvar' => ['AdminPricingZoneController', 'regraSalvar', 'admin'],
        '/admin/precificacao/regra/desativar' => ['AdminPricingZoneController', 'regraDesativar', 'admin'],
        '/admin/precificacao/zona/expansao' => ['AdminPricingZoneController', 'expansaoSalvar', 'admin'],

        '/admin/produto/salvar'      => ['AdminProdutoController', 'salvar', 'admin'],
        '/admin/planejamento/salvar' => ['AdminPlanejamentoController', 'salvar', 'admin'],
        '/guincho/estoque/salvar'    => ['GuinchoController', 'estoqueSalvar', 'guincho'],

        '/admin/feriado/salvar'      => ['AdminController', 'feriadoSalvar', 'admin'],
        '/admin/feriado/alternar'    => ['AdminController', 'feriadoAlternar', 'admin'],
        '/admin/feriado/remover'     => ['AdminController', 'feriadoRemover', 'admin'],
        '/admin/cidade/salvar'       => ['AdminController', 'cidadeSalvar', 'admin'],
        '/admin/cidade/alternar'     => ['AdminController', 'cidadeAlternar', 'admin'],
        '/admin/cidade/geo/salvar'   => ['AdminController', 'cidadeGeoSalvar', 'admin'],

        '/checkout/cliente' => ['CheckoutController', 'salvarCliente', null],


        '/checkout/veiculo' => ['CheckoutController', 'salvarVeiculo', 'cliente'],


        '/pagamento/mercadopago' => ['PagamentoController', 'iniciarMercadoPago', 'cliente'],
        '/pagamento/pagseguro'   => ['PagamentoController', 'iniciarPagSeguro', 'cliente'],

        // Checkout transparente (Â§CTP-01): cliente nunca sai de /pagamento/checkout/{id}.
        '/pagamento/mercadopago/pagar' => ['PagamentoController', 'mercadoPagoTransparente', 'cliente'],
        '/pagamento/pagseguro/pagar'   => ['PagamentoController', 'pagSeguroTransparente', 'cliente'],
        '/pagamento/complementar/mercadopago/pagar' => ['PagamentoController', 'complementarMercadoPago', 'cliente'],

        '/webhook/mercadopago'   => ['WebhookController', 'mercadoPago', null],
        // Baixa faturas semanais de parceiros apÃ³s PIX confirmado pelo Mercado Pago.
        // A assinatura HMAC e a idempotÃªncia sÃ£o validadas no controller.
        '/webhook/mercadopago/pix' => ['FaturaWebhookController', 'mercadoPagoPix', null],
        '/webhook/pagseguro'     => ['WebhookController', 'pagSeguro', null],

        // Modo de debug global: espelho de erros JS pro log do servidor
        // (ver DebugController::jslog(), public/assets/js/debug.js). Rota
        // pÃºblica pois roda em qualquer tela autenticada ou nÃ£o; noop quando
        // debug_mode_ativo estÃ¡ desligado.
        '/debug/jslog'           => ['DebugController', 'jslog', null],

        // FuncionÃ¡rio sÃ³ CRIA demandas (nunca executa nada sensÃ­vel
        // diretamente) â€” gerente Ã© quem decide, em DemandaService::decidir().
        '/funcionario/demanda/criar' => ['FuncionarioController', 'demandaCriar', 'funcionario'],
        '/gerente/demanda/decidir'   => ['GerenteController', 'demandaDecidir', 'gerente'],
    ],

];

// Rotas dinÃ¢micas (prefixo + id numÃ©rico no final)
// Formato: [metodo, prefixo, Controller, action, perfil]
$rotasDinamicas = [

    ['GET',  '/senha/redefinir/',       'AuthController', 'redefinirSenhaForm', null],
    ['GET',  '/auth/magic/',            'AuthController', 'magicConsumir', null],

    ['GET',  '/cliente/pedido/',         'ClienteController', 'pedidoStatus',     'cliente'],
    ['GET',  '/cliente/chat/',           'ClienteController', 'chat',             'cliente'],
    ['POST', '/cliente/cancelar/',        'ClienteController', 'cancelarPedido',   'cliente'],
    ['POST', '/cliente/pedido/cancelar/', 'ClienteController', 'cancelarPedido',   'cliente'],
    ['GET',  '/cliente/cancelamento-preview/', 'ClienteController', 'cancelamentoPreview', 'cliente'],
    ['POST', '/cliente/chat/',           'ClienteController', 'chatEnviar',       'cliente'],
    ['GET',  '/cliente/chat/mensagens/', 'ClienteController', 'chatMensagens',    'cliente'],
    ['GET',  '/cliente/avaliar/',        'ClienteController', 'avaliar',          'cliente'],
    ['GET',  '/cliente/veiculo/editar/', 'ClienteController', 'veiculoEditar',    'cliente'],
    ['GET',  '/cliente/veiculos/editar/','ClienteController', 'veiculoEditar',    'cliente'],
    ['GET',  '/cliente/oficina/editar/', 'ClienteController', 'oficinaEditar',    'cliente'],
    ['GET',  '/cliente/oficinas/editar/','ClienteController', 'oficinaEditar',    'cliente'],
    ['POST', '/cliente/avaliar/',        'ClienteController', 'avaliarSalvar',    'cliente'],
    ['GET',  '/cliente/pedido/status/',  'ClienteController', 'pedidoStatusAjax', 'cliente'],
    ['GET',  '/cliente/pedido/status-json/', 'ClienteController', 'pedidoStatusJson', 'cliente'],
    ['POST', '/cliente/orcamento/decidir/', 'ClienteController', 'orcamentoDecidir', 'cliente'],
    ['POST', '/cliente/conversao/decidir/', 'ClienteController', 'conversaoDecidir', 'cliente'],
    ['GET',  '/sse/pedido/',             'SseController', 'pedido', null],

    ['GET',  '/gerente/demanda/',        'GerenteController', 'demandaDetalhe', 'gerente'],
    ['GET',  '/admin/especialista-fragmento/', 'AdminController', 'especialistaDetalheFragmento', 'admin'],
    ['POST', '/admin/marketing/prospeccao/sincronizar-zonas', 'AdminFinanceAttributionController', 'prospeccaoSincronizarZonas', 'admin'],
    ['POST', '/admin/prospeccao/sincronizar-zonas', 'AdminFinanceAttributionController', 'prospeccaoSincronizarZonas', 'admin'],
    ['POST', '/admin/marketing/prospeccao/lead/enviado/', 'AdminFinanceAttributionController', 'prospeccaoMarcarEnviado', 'admin'],
    ['POST', '/admin/marketing/prospeccao/lead/cadastrado/', 'AdminFinanceAttributionController', 'prospeccaoConfirmarCadastro', 'admin'],
    ['POST', '/admin/prospeccao/lead/enviado/', 'AdminFinanceAttributionController', 'prospeccaoMarcarEnviado', 'admin'],
    ['POST', '/admin/prospeccao/lead/cadastrado/', 'AdminFinanceAttributionController', 'prospeccaoConfirmarCadastro', 'admin'],

    ['GET',  '/guincho/aceitar/',        'GuinchoController', 'aceitarForm',      'guincho'],
    ['POST', '/guincho/aceitar/',        'GuinchoController', 'aceitar',          'guincho'],
    ['POST', '/guincho/recusar/',        'GuinchoController', 'recusar',          'guincho'],
    ['POST', '/guincho/cancelar/',        'GuinchoController', 'cancelarAtendimento', 'guincho'],
    ['POST', '/guincho/pedido/cancelar/', 'GuinchoController', 'cancelarAtendimento', 'guincho'],
    ['POST', '/guincho/status/',         'GuinchoController', 'atualizarStatus',  'guincho'],
    ['POST', '/guincho/pedido/status-atualizar/', 'GuinchoController', 'atualizarStatus', 'guincho'],
    ['GET',  '/guincho/pedido/checkin-oficina/nonce/', 'OficinaParceriaController', 'nonce', 'guincho'],
    ['GET',  '/guincho/atendimento/',    'GuinchoController', 'atendimento',      'guincho'],
    ['POST', '/guincho/diagnostico/iniciar/',  'GuinchoController', 'diagnosticoIniciar', 'guincho'],
    ['POST', '/guincho/diagnostico/concluir/', 'GuinchoController', 'diagnosticoConcluir', 'guincho'],
    ['POST', '/guincho/execucao/concluir/',    'GuinchoController', 'execucaoConcluir', 'guincho'],
    ['POST', '/guincho/teste-final/concluir/', 'GuinchoController', 'testeFinalConcluir', 'guincho'],
    ['POST', '/guincho/preparacao/concluir/',  'GuinchoController', 'preparacaoConcluir', 'guincho'],
    ['GET',  '/guincho/pedido/recibo/',  'GuinchoController', 'recibo',           'guincho'],
    ['GET',  '/guincho/evidencia-nonce/', 'GuinchoController', 'evidenciaNonce',  'guincho'],

    ['GET',  '/guincho/pedido/status-json/', 'GuinchoController', 'pedidoStatusJson', 'guincho'],
    ['GET',  '/guincho/chat/',           'GuinchoController', 'chatMensagens',    'guincho'],
    ['POST', '/guincho/chat/',           'GuinchoController', 'chatEnviar',       'guincho'],
    ['POST', '/push/subscribe',         'PushSubscriptionController', 'subscribe', null],
    ['POST', '/push/unsubscribe',       'PushSubscriptionController', 'unsubscribe', null],
    ['GET',  '/push/acao/aceitar/',     'PushActionController', 'aceitar', null],
    ['GET',  '/push/acao/recusar/',     'PushActionController', 'recusar', null],
    ['POST', '/push/acao/aceitar/',     'PushActionController', 'aceitar', null],
    ['POST', '/push/acao/recusar/',     'PushActionController', 'recusar', null],

    ['GET',  '/arquivo/',                'ArquivoController', 'servir',           null],
    ['GET',  '/evidencia/',              'ArquivoController', 'servirEvidencia',  null],
    ['GET',  '/evidencia-especialista/', 'ArquivoController', 'servirEvidenciaEspecialista', null],
    ['GET',  '/documento-especialista/', 'ArquivoController', 'servirDocumentoEspecialista', 'admin'],

    ['GET',  '/admin/pedido/',           'AdminController',   'pedidoDetalhe',    'admin'],
    ['GET',  '/admin/pedido/status-json/','AdminController',  'pedidoStatusJson', 'admin'],
    ['GET',  '/admin/pedido/trilha/',    'AdminController',   'pedidoTrilha',     'admin'],
    ['GET',  '/admin/comunicado/',       'AdminComunicadoController', 'form', 'admin'],
    ['GET',  '/admin/comunicado/preview/', 'AdminComunicadoController', 'preview', 'admin'],
    ['GET',  '/admin/comunicado/metricas/', 'AdminComunicadoController', 'metrics', 'admin'],
    ['POST', '/admin/comunicado/publicar/', 'AdminComunicadoController', 'publish', 'admin'],
    ['POST', '/admin/comunicado/pausar/', 'AdminComunicadoController', 'pause', 'admin'],
    ['POST', '/admin/comunicado/arquivar/', 'AdminComunicadoController', 'archive', 'admin'],
    ['GET',  '/admin/usuario/',          'AdminController',   'usuarioDetalhe',   'admin'],
    ['GET',  '/admin/usuario-fragmento/', 'AdminController',  'usuarioDetalheFragmento', 'admin'],
    ['GET',  '/admin/usuario/editar/',   'AdminController',   'usuarioEditar',    'admin'],
    ['GET',  '/admin/guincho/',          'AdminController',   'guinchoDetalhe',   'admin'],
    ['GET',  '/admin/guincho-fragmento/', 'AdminController',  'guinchoDetalheFragmento', 'admin'],
    ['GET',  '/admin/especialista-fragmento/', 'AdminController', 'especialistaDetalheFragmento', 'admin'],
    ['GET',  '/admin/carteira/',         'AdminController',   'carteiraDetalhe',  'admin'],
    ['GET',  '/admin/carteira-json/',    'AdminController',   'carteiraDetalheJson', 'admin'],
    ['POST', '/admin/pix/reprocessar/',  'AdminController',   'pixReprocessar',   'admin'],
    ['POST', '/especialista/atendimento/aceitar/', 'EspecialistaController', 'aceitar', 'especialista'],
    ['POST', '/especialista/atendimento/status/', 'EspecialistaController', 'transicionar', 'especialista'],
    ['POST', '/especialista/atendimento/diagnostico/', 'EspecialistaController', 'diagnostico', 'especialista'],
    ['POST', '/especialista/atendimento/chegada/', 'EspecialistaController', 'chegada', 'especialista'],
    ['POST', '/especialista/atendimento/localizacao/', 'EspecialistaController', 'localizacao', 'especialista'],
    ['GET',  '/especialista/notificacoes', 'EspecialistaController', 'notificacoes', 'especialista'],
    ['GET',  '/especialista/notificacoes/', 'EspecialistaController', 'notificacoes', 'especialista'],
    ['POST', '/cliente/incidente/orcamento/aprovar/', 'IncidenteController', 'aprovarOrcamento', 'cliente'],
    ['POST', '/cliente/incidente/orcamento/recusar/', 'IncidenteController', 'recusarOrcamento', 'cliente'],
    ['POST', '/cliente/incidente/reboque/', 'IncidenteController', 'solicitarReboque', 'cliente'],
    ['POST', '/admin/payment-job/retry/','AdminController',   'paymentJobRetry',  'admin'],

    ['POST', '/admin/usuario/ativar/',   'AdminController',   'usuarioAtivar',    'admin'],
    ['POST', '/admin/usuario/suspender/','AdminController',   'usuarioSuspender', 'admin'],
    ['POST', '/admin/simulador',         'AdminController',   'simularExecutar',  'admin'],
    ['GET',  '/admin/simulador/',        'AdminController',   'simuladorResultado','admin'],
    ['GET',  '/admin/qa/run/',           'AdminController',   'simuladorResultado','admin'],
    ['GET',  '/admin/qa/run/status/',    'AdminController',   'qaStatus',         'admin'],
    ['POST', '/admin/qa/run/cancel/',    'AdminController',   'qaCancelar',       'admin'],
    ['GET',  '/admin/qa/run/artifact/',  'AdminController',   'qaArtifact',       'admin'],

    ['GET',  '/pagamento/checkout/',     'PagamentoController','checkout',        'cliente'],
    ['GET',  '/pagamento/complementar/', 'PagamentoController','complementarCheckout', 'cliente'],
    ['GET',  '/pagamento/sucesso/',      'PagamentoController','sucesso',         'cliente'],
    ['GET',  '/pagamento/falha/',        'PagamentoController','falha',           'cliente'],
    ['GET',  '/pagamento/pagseguro/sessao/', 'PagamentoController', 'pagSeguroSessao', 'cliente'],
    ['GET',  '/admin/precificacao/zona/', 'AdminPricingZoneController', 'zonaRegras', 'admin'],
    ['GET',  '/admin/catalogo-veiculos/marca/', 'AdminVehicleCatalogController', 'modelos', 'admin'],
    ['GET',  '/admin/catalogo-veiculos/modelo/', 'AdminVehicleCatalogController', 'versoes', 'admin'],

];

// â€”â€” Resolve rota â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
$controller = null; $action = null; $perfil = null; $id = null;

if (isset($rotas[$metodo][$uri])) {
    [$controller, $action, $perfil] = $rotas[$metodo][$uri];
} else {
    // Match por template: {id} -> digitos, {nome} -> [\w.-]+
    // Resolve rotas dinamicas registradas em $rotas[METODO].
    // So roda se $controller ainda for null; fallbacks abaixo intactos.
    foreach ($rotas[$metodo] as $template => $target) {
        if (strpos($template, '{') === false) continue;
        // Placeholders ANTES do preg_quote; named groups para distinguir id/nome
        $regex = str_replace(['{id}', '{nome}'], ["\x01", "\x02"], $template);
        $regex = preg_quote($regex, '~');
        $regex = str_replace(
            ["\x01", "\x02"],
            ['(?P<id>\d+)', '(?P<nome>[\w.-]+)'],
            $regex
        );
        if (preg_match('~^' . $regex . '$~', $uri, $m)) {
            [$controller, $action, $perfil] = $target;
            if (isset($m['id']) && $m['id'] !== '') {
                $id = (int)$m['id'];
            } elseif (isset($m['nome']) && $m['nome'] !== '') {
                $id = $m['nome'];
            } else {
                $id = null;
            }
            break;
        }
    }
    // PÃ¡gina local SEO: somente o slug de cidade Ã© dinÃ¢mico; dashboards tÃªm
    // rotas explÃ­citas e continuam sendo resolvidos antes desta regra.
    if ($controller === null && in_array($metodo, ['GET', 'POST'], true)
        && preg_match('~^/parceiros/oficinas-([a-z0-9]+(?:-[a-z0-9]+)*)$~', $uri, $m)) {
        $controller = 'SeoPartnerController';
        $action = $metodo === 'POST' ? 'capturar' : 'landing';
        $perfil = null;
        $id = $m[1];
    }

    if ($controller === null && $metodo === 'GET'
        && preg_match('~^/guincho/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $uri, $m)) {
        $controller = 'AuthController';
        $action = 'cidadePublica';
        $perfil = null;
        $id = $m[1];
    }

    // API operacional de pedidos: possui sub-recursos depois do ID, por isso
    // precisa ser resolvida antes das rotas dinÃ¢micas numÃ©ricas legadas.
    if ($controller === null && $metodo === 'GET'
        && preg_match('~^/api/routing/osrm/route/v1/driving/(.+)$~', $uri, $m)) {
        $controller = 'RoutingApiController';
        $action = 'route';
        $perfil = null;
        $id = $m[1];
    }
    if ($controller === null && $metodo === 'POST'
        && preg_match('~^/api/pedido/(\d+)/converter-reboque$~', $uri, $m)) {
        $controller = 'ClienteController';
        $action = 'converterReboqueComDesconto';
        $perfil = 'cliente';
        $id = (int)$m[1];
    }
    if (preg_match('~^/api/admin/orders/(\d+)(?:/(tracking|timeline|messages))?$~', $uri, $m)) {
        $controller = 'OrdersApiController';
        $id = (int)$m[1];
        $subresource = $m[2] ?? '';
        if ($subresource === 'tracking') $action = 'tracking';
        elseif ($subresource === 'timeline') $action = 'timeline';
        elseif ($subresource === 'messages') $action = $metodo === 'POST' ? 'sendMessage' : 'messages';
        else $action = 'show';
        $perfil = 'admin';
    }

    // Compatibilidade com links antigos do catÃ¡logo: /modelo/{id}/versoes.
    // A rota canÃ´nica agora Ã© /modelo/{id}; redirecionar evita 404 em cache,
    // favoritos e pÃ¡ginas antigas ainda abertas.
    if ($controller === null && $metodo === 'GET'
        && preg_match('~^/admin/catalogo-veiculos/modelo/(\d+)/versoes$~', $uri, $m)) {
        $controller = 'AdminVehicleCatalogController';
        $action = 'versoesLegado';
        $perfil = 'admin';
        $id = (int)$m[1];
    }

    foreach ($rotasDinamicas as $r) {
        if ($controller !== null) break;
        [$m, $prefixo, $c, $a, $p] = $r;
        if ($m !== $metodo) continue;
        if (strpos($uri, $prefixo) !== 0) continue;

        $param = substr($uri, strlen($prefixo));
        if ($param === '') continue;

        // Rotas de token (ex: /senha/redefinir/{hex64}) aceitam hex alfanumÃ©rico
        $ehNumerico = ctype_digit($param);
        $ehToken    = ctype_xdigit($param) && strlen($param) >= 32;

        if ($ehNumerico || $ehToken) {
            $controller = $c;
            $action     = $a;
            $perfil     = $p;
            $id         = $ehNumerico ? (int)$param : $param; // string para tokens
            break;
        }
    }
}

if (!$controller) {
    Logger::event([
        'level' => Logger::LEVEL_WARN,
        'class' => 'Router',
        'function' => 'resolve',
        'system' => 'ROUTER',
        'phase' => 'route_lookup',
        'code' => 'RTR-001',
        'message' => 'Rota nÃ£o encontrada.',
        'context' => [
            'metodo' => $metodo,
            'uri' => $uri,
            'basePath' => $basePath,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        ],
    ]);

    http_response_code(404);
    echo '<h1>404 â€” PÃ¡gina nÃ£o encontrada</h1>';
    exit;
}

// â€”â€” Rate limiting (rotas sensÃ­veis) â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
$rotasSensiveis = ['/login', '/registro/cliente', '/registro/guincho', '/registro/especialista'];
if (in_array($uri, $rotasSensiveis, true) && $metodo === 'POST') {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $rotaRateLimit = ltrim($uri, '/');
    if (!AuthService::verificarRateLimit($ip, $rotaRateLimit)) {
        http_response_code(429);
        echo '<h1>429 â€” Muitas tentativas. Aguarde alguns minutos.</h1>';
        exit;
    }
}

// â€”â€” AutenticaÃ§Ã£o â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
if ($perfil !== null) {
    // O router tambÃ©m precisa usar AuthService: assim AJAX recebe 401 JSON em vez
    // de seguir um redirect e tentar interpretar a tela HTML de login como JSON.
    $isPassivePolling = preg_match('~/(status|pedidos-disponiveis|pedidos|chat)(/|$)~', $uri) === 1
        && $metodo === 'GET';
    AuthService::requireAuth($perfil, !$isPassivePolling);
}

// â€”â€” Executa controller â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”â€”
$controllerFile = __DIR__ . '/src/Controllers/' . $controller . '.php';
if (!is_file($controllerFile) && $controller === 'OrdersApiController') {
    $controllerFile = __DIR__ . '/src/Api/Admin/OrdersApiController.php';
}
if (is_file($controllerFile)) {
    require_once $controllerFile;
}

if (!class_exists($controller)) {
    throw new RuntimeException("Controller '$controller' nÃ£o encontrado. (file=" . basename($controllerFile) . ")");
}

$instancia = new $controller();

if (!method_exists($instancia, $action)) {
    throw new RuntimeException("MÃ©todo '$action' nÃ£o existe em '$controller'.");
}

Logger::event([
    'level' => Logger::LEVEL_DEBUG,
    'class' => $controller,
    'function' => $action,
    'system' => 'ROUTER',
    'phase' => 'dispatch',
    'code' => 'RTR-200',
    'message' => 'Executando controller.',
    'context' => ['method' => $metodo, 'uri' => $uri],
]);

try {
    $id !== null ? $instancia->$action($id) : $instancia->$action();
} catch (Throwable $e) {
    Logger::exception($controller, $action, 'CONTROLLER', $e, ['method' => $metodo, 'uri' => $uri, 'id' => $id, 'code' => 'RTR-002']);
    throw $e;
}
