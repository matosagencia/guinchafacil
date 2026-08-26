<?php
// File: guinchafacil/index.php
// Router (front-controller) — roda em public_html (raiz) ou em subpasta automaticamente.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Core (alguns controllers fazem extends BaseController)
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Services/AuthService.php';
require_once __DIR__ . '/src/Services/Logger.php';
require_once __DIR__ . '/src/Controllers/BaseController.php';

// ─────────────────────────────────────────────────────────────────────────────
// Error handling: registra fatal e exceptions com contexto (pra parar de “500 fantasma”)
// ─────────────────────────────────────────────────────────────────────────────
set_exception_handler(function (Throwable $e): void {
    Logger::exception('Router', 'exception_handler', 'php', $e, [
        'uri'  => $_SERVER['REQUEST_URI'] ?? null,
    ]);

    http_response_code(500);
    echo 'Erro interno do servidor. Tente novamente mais tarde.';
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
        echo 'Erro interno do servidor. Tente novamente mais tarde.';
    }
});

// ── Sessão segura ──────────────────────────────────────────────────────────
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

// ── Autoloader simples (sem namespace) ─────────────────────────────────────
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
if (defined('FORCE_BASEPATH')) {
    $basePath = rtrim((string)FORCE_BASEPATH, '/');
} else {
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($basePath === '.' || $basePath === '/') $basePath = '';
}
if (!defined('BASE_PATH')) define('BASE_PATH', $basePath);

// ── URL atual normalizada ──────────────────────────────────────────────────
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
        '/'                 => ['AuthController', 'loginForm', null],
        '/login'            => ['AuthController', 'loginForm', null],
        '/logout'           => ['AuthController', 'logout', null],
        '/senha/esqueceu'         => ['AuthController', 'esqueceuSenhaForm', null],

        '/registro/cliente' => ['AuthController', 'registroClienteForm', null],
        '/registro/guincho' => ['AuthController', 'registroGuinchoForm', null],

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

        '/guincho/dashboard'  => ['GuinchoController', 'dashboard', 'guincho'],
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

        '/pagamento/sucesso'  => ['PagamentoController', 'sucesso', 'cliente'],
        '/pagamento/falha'    => ['PagamentoController', 'falha', 'cliente'],
        '/pagamento/pendente' => ['PagamentoController', 'pendente', 'cliente'],
    ],
    'POST' => [
        '/login'                 => ['AuthController', 'login', null],
        '/registro/cliente'       => ['AuthController', 'registroCliente', null],
        '/registro/guincho'       => ['AuthController', 'registroGuincho', null],
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

        '/pagamento/mercadopago' => ['PagamentoController', 'iniciarMercadoPago', 'cliente'],
        '/pagamento/pagseguro'   => ['PagamentoController', 'iniciarPagSeguro', 'cliente'],

        '/webhook/mercadopago'   => ['WebhookController', 'mercadoPago', null],
        '/webhook/pagseguro'     => ['WebhookController', 'pagSeguro', null],
    ],
];

// Rotas dinâmicas (prefixo + id numérico no final)
// Formato: [metodo, prefixo, Controller, action, perfil]
$rotasDinamicas = [
        ['GET',  '/senha/redefinir/',       'AuthController', 'redefinirSenhaForm', null],
    ['GET',  '/cliente/pedido/',         'ClienteController', 'pedidoStatus',     'cliente'],
    ['GET',  '/cliente/chat/',           'ClienteController', 'chat',             'cliente'],
    ['POST', '/cliente/cancelar/',        'ClienteController', 'cancelarPedido',   'cliente'],
    ['POST', '/cliente/chat/',           'ClienteController', 'chatEnviar',       'cliente'],
    ['GET',  '/cliente/chat/mensagens/', 'ClienteController', 'chatMensagens',    'cliente'],
    ['GET',  '/cliente/avaliar/',        'ClienteController', 'avaliar',          'cliente'],
    ['GET',  '/cliente/veiculo/editar/', 'ClienteController', 'veiculoEditar',    'cliente'],
    ['GET',  '/cliente/veiculos/editar/','ClienteController', 'veiculoEditar',    'cliente'],
    ['GET',  '/cliente/oficina/editar/', 'ClienteController', 'oficinaEditar',    'cliente'],
    ['GET',  '/cliente/oficinas/editar/','ClienteController', 'oficinaEditar',    'cliente'],
    ['POST', '/cliente/avaliar/',        'ClienteController', 'avaliarSalvar',    'cliente'],
    ['GET',  '/cliente/pedido/status/',  'ClienteController', 'pedidoStatusAjax', 'cliente'],

    ['GET',  '/guincho/aceitar/',        'GuinchoController', 'aceitarForm',      'guincho'],
    ['POST', '/guincho/aceitar/',        'GuinchoController', 'aceitar',          'guincho'],
    ['POST', '/guincho/recusar/',        'GuinchoController', 'recusar',          'guincho'],
    ['POST', '/guincho/status/',         'GuinchoController', 'atualizarStatus',  'guincho'],
    ['GET',  '/guincho/atendimento/',    'GuinchoController', 'atendimento',      'guincho'],
    ['GET',  '/guincho/chat/',           'GuinchoController', 'chatMensagens',    'guincho'],
    ['POST', '/guincho/chat/',           'GuinchoController', 'chatEnviar',       'guincho'],

    ['GET',  '/admin/pedido/',           'AdminController',   'pedidoDetalhe',    'admin'],
    ['GET',  '/admin/usuario/',          'AdminController',   'usuarioDetalhe',   'admin'],
    ['GET',  '/admin/usuario/editar/',   'AdminController',   'usuarioEditar',    'admin'],
    ['GET',  '/admin/guincho/',          'AdminController',   'guinchoDetalhe',   'admin'],
    ['POST', '/admin/pix/reprocessar/',  'AdminController',   'pixReprocessar',   'admin'],
    ['POST', '/admin/usuario/ativar/',   'AdminController',   'usuarioAtivar',    'admin'],
    ['POST', '/admin/usuario/suspender/','AdminController',   'usuarioSuspender', 'admin'],

    ['GET',  '/pagamento/checkout/',     'PagamentoController','checkout',        'cliente'],
    ['GET',  '/pagamento/sucesso/',      'PagamentoController','sucesso',         'cliente'],
    ['GET',  '/pagamento/falha/',        'PagamentoController','falha',           'cliente'],
];

// ── Resolve rota ─────────────────────────────────────────────────────────-
$controller = null; $action = null; $perfil = null; $id = null;

if (isset($rotas[$metodo][$uri])) {
    [$controller, $action, $perfil] = $rotas[$metodo][$uri];
} else {
    foreach ($rotasDinamicas as $r) {
        [$m, $prefixo, $c, $a, $p] = $r;
        if ($m !== $metodo) continue;
        if (strpos($uri, $prefixo) !== 0) continue;

        $param = substr($uri, strlen($prefixo));
        if ($param === '') continue;

        // Rotas de token (ex: /senha/redefinir/{hex64}) aceitam hex alfanumérico
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

// ── Rate limiting (rotas sensíveis) ───────────────────────────────────────
$rotasSensiveis = ['/login', '/registro/cliente', '/registro/guincho'];
if (in_array($uri, $rotasSensiveis, true) && $metodo === 'POST') {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (!AuthService::verificarRateLimit($ip, $uri)) {
        http_response_code(429);
        echo '<h1>429 — Muitas tentativas. Aguarde alguns minutos.</h1>';
        exit;
    }
}

// ── Autenticação ─────────────────────────────────────────────────────────-
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

// ── Executa controller ───────────────────────────────────────────────────-
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

$id !== null ? $instancia->$action($id) : $instancia->$action();
