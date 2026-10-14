<?php
// File: guinchafacil/index.php
// Router (front-controller) — roda em public_html (raiz) ou em subpasta automaticamente.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$cspPolicy = "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://www.googletagmanager.com https://www.google-analytics.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://fonts.googleapis.com; font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://unpkg.com https://www.googletagmanager.com https://www.google-analytics.com; connect-src 'self' https://viacep.com.br https://nominatim.openstreetmap.org https://router.project-osrm.org https://api.mercadopago.com https://ws.pagseguro.uol.com.br https://ws.sandbox.pagseguro.uol.com.br https://cdn.jsdelivr.net https://unpkg.com https://www.googletagmanager.com https://www.google-analytics.com;";

if (!headers_sent()) {
    header('Content-Security-Policy: ' . $cspPolicy);
}

// ── Nonce CSP ──
// Precisa ser definido ANTES de qualquer view/controller chamar csp_script_nonce_attr().
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

// Core (alguns controllers fazem extends BaseController)
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Services/AuthService.php';
require_once __DIR__ . '/src/Services/Logger.php';
require_once __DIR__ . '/src/Controllers/BaseController.php';

// ─────────────────────────────────────────────────────────────────────────────
// Error handling: registra fatal e exceptions com contexto (pra parar de "500 fantasma")
// ─────────────────────────────────────────────────────────────────────────────
set_exception_handler(function (Throwable $e): void {
    Logger::exception('Router', 'exception_handler', 'php', $e, [
        'uri'  => $_SERVER['REQUEST_URI'] ?? null,
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

    Logger::log(Logger::LEVEL_ERROR, 'Router', 'shutdown', 'php', (string)($err['message'] ?? 'Fatal error'), [
        'type' => $err['type'] ?? null,
        'file' => $err['file'] ?? null,
        'line' => $err['line'] ?? null,
        'uri'  => $_SERVER['REQUEST_URI'] ?? null,
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

// ── Sessão segura ─────────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();

// Regenera ID da sessão a cada 5 minutos
if (!isset($_SESSION['last_regen'])) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
} elseif (time() - (int)$_SESSION['last_regen'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

// ── Autoloader simples (sem namespace) ────────────────────────────────────────
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
});

// BasePath: detectado automaticamente.
// Em public_html (raiz) será '' — nenhuma configuração necessária.
// Para subpasta, force: define('FORCE_BASEPATH','/minha-subpasta') em config.php
$basePath = '';
$forced = defined('FORCE_BASEPATH') ? (string)FORCE_BASEPATH : '';
if ($forced !== '' && $forced !== '/') {
    $basePath = rtrim($forced, '/');
} else {
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($basePath === '.' || $basePath === '/') $basePath = '';
}
if (!defined('BASE_PATH')) define('BASE_PATH', $basePath);

// ── URL atual normalizada ─────────────────────────────────────────────────────
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = strtok($uri, '?') ?: '/';

// Remove basePath do começo da URL (rodando em subpasta)
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
    if ($uri === '') $uri = '/';
}

// Normaliza acesso direto ao index.php
if ($uri === '/index.php') $uri = '/';

// Normaliza trailing slash
$uri = rtrim($uri, '/') ?: '/';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ─────────────────────────────────────────────────────────────────────────────
// Rotas (sem gambiarra de espaçamento: método e path separados)
// Formato: $rotas[METODO][PATH] = [Controller, action, perfil]
// ─────────────────────────────────────────────────────────────────────────────
$rotas = [
    'GET' => [
        '/'                 => ['AuthController', 'landing', null],
        '/login'            => ['AuthController', 'loginForm', null],
        '/logout'           => ['AuthController', 'logout', null],
        '/senha/esqueceu'         => ['AuthController', 'esqueceuSenhaForm', null],

        '/registro/cliente' => ['AuthController', 'registroClienteForm', null],
        '/registro/guincho' => ['AuthController', 'registroGuinchoForm', null],
        '/pre-cotacao'      => ['PreCotacaoApiController', 'form', null],
        '/parceiros'        => ['SeoPartnerController', 'index', null],
        '/parceiros/guinchos' => ['SeoPartnerController', 'guinchos', null],
        '/parceiros/oficinas' => ['SeoPartnerController', 'oficinas', null],
        '/guincho'          => ['AuthController', 'cidadesPublicas', null],
        '/checkout/veiculo' => ['CheckoutController', 'veiculo', 'cliente'],

        '/cliente/dashboard'    => ['ClienteController', 'dashboard', 'cliente'],
        '/cliente/veiculos'     => ['ClienteController', 'veiculos', 'cliente'],
        '/cliente/veiculo/novo'  => ['ClienteController', 'veiculoForm', 'cliente'],
        '/cliente/veiculos/novo' => ['ClienteController', 'veiculoForm', 'cliente'],
        '/cliente/oficinas'      => ['ClienteController', 'oficinas', 'cliente'],
        '/cliente/oficina/nova'  => ['ClienteController', 'oficinaForm', 'cliente'],
        '/cliente/oficinas/nova' => ['ClienteController', 'oficinaForm', 'cliente'],
        '/cliente/pedido/novo'  => ['ClienteController', 'pedidoNovo', 'cliente'],
        '/cliente/historico'    => ['ClienteController', 'historico', 'cliente'],
        '/cliente/pedido/custo' => ['ClienteController', 'calcularCusto', 'cliente'],
        '/geocode'              => ['GeocodeController', 'search', null],

        '/guincho/dashboard'  => ['GuinchoController', 'dashboard', 'guincho'],
        '/guincho/pedidos-disponiveis' => ['GuinchoController', 'pedidosDisponiveis', 'guincho'],
        '/guincho/pedidos'    => ['GuinchoController', 'pedidosDisponiveis', 'guincho'],
        '/guincho/historico'  => ['GuinchoController', 'historico', 'guincho'],
        '/guincho/financeiro' => ['GuinchoController', 'financeiro', 'guincho'],
        '/guincho/perfil'     => ['GuinchoController', 'perfilForm', 'guincho'],

        '/admin/dashboard'           => ['AdminController', 'dashboard', 'admin'],
        '/admin/usuarios'            => ['AdminController', 'usuarios', 'admin'],
        '/admin/usuario/novo'        => ['AdminController', 'usuarioForm', 'admin'],
        '/admin/usuario/suspender'   => ['AdminController', 'usuariosSuspenderGet', 'admin'],
        '/admin/guinchos'            => ['AdminController', 'guinchos', 'admin'],
        '/admin/guinchospendentes'   => ['AdminController', 'guinchosPendentes', 'admin'],
        '/admin/guincho/novo'        => ['AdminController', 'guinchoNovoForm', 'admin'],
        '/admin/pedidos'             => ['AdminController', 'pedidos', 'admin'],
        '/admin/pedido/novo'         => ['AdminController', 'pedidoCriarForm', 'admin'],
        '/admin/pedido/criar'        => ['AdminController', 'pedidoCriarForm', 'admin'],
        '/admin/veiculos/ajax'       => ['AdminController', 'veiculosAjax', 'admin'],
        '/admin/clientes/ajax'       => ['AdminController', 'clientesAjax', 'admin'],
        '/admin/oficinas/ajax'       => ['AdminController', 'oficinasAjax', 'admin'],
        '/admin/financeiro'          => ['AdminController', 'financeiro', 'admin'],
        '/admin/financeiro/csv'      => ['AdminController', 'exportarCsv', 'admin'],
        '/admin/configuracoes'       => ['AdminController', 'configuracoes', 'admin'],
        '/admin/logs'                => ['AdminController', 'logs', 'admin'],
        '/admin/chat'                => ['AdminController', 'chat', 'admin'],
        '/admin/health'              => ['AdminController', 'health', 'admin'],
        '/admin/simulador'           => ['AdminController', 'simulador', 'admin'],
        '/admin/env'                 => ['AdminController', 'envGovernanca', 'admin'],
        '/admin/env/auditoria'       => ['AdminController', 'envAuditoria', 'admin'],

        '/pagamento/sucesso'  => ['PagamentoController', 'sucesso', 'cliente'],
        '/pagamento/falha'    => ['PagamentoController', 'falha', 'cliente'],
        '/pagamento/pendente' => ['PagamentoController', 'pendente', 'cliente'],
    ],
    'POST' => [
        '/login'                 => ['AuthController', 'login', null],
        '/registro/cliente'       => ['AuthController', 'registroCliente', null],
        '/registro/guincho'       => ['AuthController', 'registroGuincho', null],
        '/parceiros/interesse'     => ['AuthController', 'parceirosInteresse', null],
        '/api/pre-cotacao/endereco'  => ['PreCotacaoApiController', 'endereco', null],
        '/api/pre-cotacao/finalizar' => ['PreCotacaoApiController', 'finalizar', null],
        '/api/pre-cotacao/endereco/sugestoes' => ['PreCotacaoApiController', 'sugestoes', null],
        '/api/pre-cotacao/endereco/reverso'   => ['PreCotacaoApiController', 'reverso',   null],
        '/pre-cotacao/finalizar'     => ['PreCotacaoApiController', 'finalizar', null],
        '/checkout/pagar'            => ['CheckoutController', 'pagar', 'cliente'],
        '/senha/esqueceu'         => ['AuthController', 'esqueceuSenha',     null],
        '/senha/redefinir'        => ['AuthController', 'redefinirSenha',    null],


        '/cliente/veiculo/salvar'  => ['ClienteController', 'veiculoSalvar', 'cliente'],
        '/cliente/veiculo/deletar' => ['ClienteController', 'veiculoDeletar', 'cliente'],
        '/cliente/oficina/salvar'  => ['ClienteController', 'oficinaSalvar', 'cliente'],
        '/cliente/oficina/deletar' => ['ClienteController', 'oficinaDeletar', 'cliente'],
        '/cliente/pedido/criar'    => ['ClienteController', 'pedidoCriar', 'cliente'],
        '/cliente/chat/enviar'     => ['ClienteController', 'chatEnviar', 'cliente'],

        '/guincho/localizacao'      => ['GuinchoController', 'atualizarLocalizacao', 'guincho'],
        '/guincho/disponibilidade'  => ['GuinchoController', 'toggleDisponibilidade', 'guincho'],
        '/guincho/perfil/salvar'    => ['GuinchoController', 'perfilSalvar',          'guincho'],
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
        '/admin/configuracoes'       => ['AdminController', 'configuracoesSalvar', 'admin'],
        '/admin/env/salvar'          => ['AdminController', 'envSalvar', 'admin'],
        '/admin/simulador'           => ['AdminController', 'simularExecutar', 'admin'],

        '/pagamento/mercadopago' => ['PagamentoController', 'iniciarMercadoPago', 'cliente'],
        '/pagamento/pagseguro'   => ['PagamentoController', 'iniciarPagSeguro', 'cliente'],

        '/webhook/mercadopago'   => ['WebhookController', 'mercadoPago', null],
        '/webhook/pagseguro'     => ['WebhookController', 'pagSeguro', null],
    ],
];

// Rotas dinâmicas (prefixo + id numérico no final)
// Formato: [metodo, prefixo, Controller, action, perfil]
$rotasDinamicas = [
    ['GET',  '/senha/redefinir/',       'AuthController', 'redefinirSenhaForm', null, 'token'],
    ['GET',  '/guincho/',               'AuthController', 'cidadePublica',      null, 'slug'],
    ['GET',  '/parceiros/oficinas-',    'SeoPartnerController', 'landing',      null, 'slug'],
    ['POST', '/parceiros/oficinas-',    'SeoPartnerController', 'capturar',     null, 'slug'],
    ['GET',  '/cliente/pedido/',         'ClienteController', 'pedidoStatus',     'cliente'],
    ['GET',  '/cliente/chat/',           'ClienteController', 'chat',             'cliente'],
    ['POST', '/cliente/cancelar/',        'ClienteController', 'cancelarPedido',   'cliente'],
    ['POST', '/cliente/pedido/cancelar/', 'ClienteController', 'cancelarPedido',   'cliente'],
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

    ['GET',  '/guincho/aceitar/',        'GuinchoController', 'aceitarForm',      'guincho'],
    ['POST', '/guincho/aceitar/',        'GuinchoController', 'aceitar',          'guincho'],
    ['POST', '/guincho/recusar/',        'GuinchoController', 'recusar',          'guincho'],
    ['POST', '/guincho/cancelar/',        'GuinchoController', 'cancelarAtendimento', 'guincho'],
    ['POST', '/guincho/pedido/cancelar/', 'GuinchoController', 'cancelarAtendimento', 'guincho'],
    ['POST', '/guincho/status/',         'GuinchoController', 'atualizarStatus',  'guincho'],
    ['POST', '/guincho/pedido/status-atualizar/', 'GuinchoController', 'atualizarStatus', 'guincho'],
    ['GET',  '/guincho/atendimento/',    'GuinchoController', 'atendimento',      'guincho'],

    ['GET',  '/guincho/pedido/status-json/', 'GuinchoController', 'pedidoStatusJson', 'guincho'],
    ['GET',  '/guincho/chat/',           'GuinchoController', 'chatMensagens',    'guincho'],
    ['POST', '/guincho/chat/',           'GuinchoController', 'chatEnviar',       'guincho'],

    ['GET',  '/arquivo/',                'ArquivoController', 'servir',           null],

    ['GET',  '/admin/pedido/',           'AdminController',   'pedidoDetalhe',    'admin'],
    ['GET',  '/admin/usuario/',          'AdminController',   'usuarioDetalhe',   'admin'],
    ['GET',  '/admin/usuario/editar/',   'AdminController',   'usuarioEditar',    'admin'],
    ['GET',  '/admin/guincho/',          'AdminController',   'guinchoDetalhe',   'admin'],
    ['POST', '/admin/pix/reprocessar/',  'AdminController',   'pixReprocessar',   'admin'],
    ['POST', '/admin/usuario/ativar/',   'AdminController',   'usuarioAtivar',    'admin'],
    ['POST', '/admin/usuario/suspender/','AdminController',   'usuarioSuspender', 'admin'],
    ['POST', '/admin/simulador',         'AdminController',   'simularExecutar',  'admin'],
    ['GET',  '/admin/simulador/',        'AdminController',   'simuladorResultado','admin'],

    ['GET',  '/pagamento/checkout/',     'PagamentoController','checkout',        'cliente'],
    ['GET',  '/pagamento/sucesso/',      'PagamentoController','sucesso',         'cliente'],
    ['GET',  '/pagamento/falha/',        'PagamentoController','falha',           'cliente'],
];

// ── Resolve rota ──────────────────────────────────────────────────────────────
$controller = null; $action = null; $perfil = null; $id = null;

if (isset($rotas[$metodo][$uri])) {
    [$controller, $action, $perfil] = $rotas[$metodo][$uri];
} else {
    foreach ($rotasDinamicas as $r) {
        [$m, $prefixo, $c, $a, $p, $paramTipo] = $r + [5 => 'id'];
        if ($m !== $metodo) continue;
        if (strpos($uri, $prefixo) !== 0) continue;

        $param = substr($uri, strlen($prefixo));
        if ($param === '') continue;

        $ehNumerico = ctype_digit($param);
        $ehToken    = ctype_xdigit($param) && strlen($param) >= 32;
        $ehSlug     = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $param) === 1;

        if (($paramTipo === 'slug' && $ehSlug) || ($paramTipo === 'token' && $ehToken) || ($paramTipo === 'id' && $ehNumerico)) {
            $controller = $c;
            $action     = $a;
            $perfil     = $p;
            $id         = $ehNumerico ? (int)$param : $param;
            break;
        }
    }
}

if (!$controller) {
    error_log('[Router][404] ' . json_encode([
        'metodo' => $metodo,
        'uri' => $uri,
        'basePath' => $basePath,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
        'php_self' => $_SERVER['PHP_SELF'] ?? null,
        'known_paths_get' => array_slice(array_keys($rotas['GET'] ?? []), 0, 40),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    http_response_code(404);
    echo '<h1>404 — Página não encontrada</h1>';
    exit;
}

// ── Rate limiting (rotas sensíveis) ───────────────────────────────────────────
$rotasSensiveis = ['/login', '/registro/cliente', '/registro/guincho'];
if (in_array($uri, $rotasSensiveis, true) && $metodo === 'POST') {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $rotaRateLimit = ltrim($uri, '/');
    if (!AuthService::verificarRateLimit($ip, $rotaRateLimit)) {
        http_response_code(429);
        echo '<h1>429 — Muitas tentativas. Aguarde alguns minutos.</h1>';
        exit;
    }
}

// ── Autenticação ──────────────────────────────────────────────────────────────
if ($perfil !== null) {
    if (!AuthService::isLoggedIn()) {
        header('Location: ' . ($basePath ?: '') . '/login');
        exit;
    }

    $usuario = AuthService::getCurrentUser();

    if (!$usuario) {
        session_unset();
        session_destroy();
        header('Location: ' . ($basePath ?: '') . '/login');
        exit;
    }

    $tipoUsuario = $usuario['tipo'] ?? '';
    // Admin pode acessar todas as áreas para preview ("ver como")
    $isAdmin = ($tipoUsuario === 'admin');
    if ($tipoUsuario !== $perfil && !$isAdmin) {
        // Redireciona para o dashboard do perfil real (não 403 genérico)
        $destinos = [
            'admin'   => ($basePath ?: '') . '/admin/dashboard',
            'cliente' => ($basePath ?: '') . '/cliente/dashboard',
            'guincho' => ($basePath ?: '') . '/guincho/dashboard',
        ];
        header('Location: ' . ($destinos[$tipoUsuario] ?? ($basePath ?: '') . '/login'));
        exit;
    }
}

// ── Executa controller ────────────────────────────────────────────────────────
$controllerFile = __DIR__ . '/src/Controllers/' . $controller . '.php';
if (is_file($controllerFile)) {
    require_once $controllerFile;
}

if (!class_exists($controller)) {
    throw new RuntimeException("Controller '$controller' não encontrado. (file=" . basename($controllerFile) . ")");
}

$instancia = new $controller();

if (!method_exists($instancia, $action)) {
    throw new RuntimeException("Método '$action' não existe em '$controller'.");
}

Logger::log(Logger::LEVEL_DEBUG, $controller, $action, "router", "Executando controller", ["method" => $metodo, "uri" => $uri]);
try {
    $id !== null ? $instancia->$action($id) : $instancia->$action();
} catch (Throwable $e) {
    Logger::exception($controller, $action, "controller", $e, ["method" => $metodo, "uri" => $uri, "id" => $id]);
    throw $e;
}