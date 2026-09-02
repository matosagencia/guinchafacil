<?php
// File: guinchafacil/index.php
// Router (front-controller) — roda em public_html (raiz) ou em subpasta automaticamente.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// §OUTPUT-BUFFER-01: qualquer warning/notice do PHP (ex.: fsockopen do
// PHPMailer falhando em DNS/SMTP durante o envio de notificação — visto em
// produção contaminando a resposta JSON do checkout transparente com
// "Resposta não-JSON (HTTP 200)") é impresso direto no corpo da resposta se
// display_errors estiver ligado, ANTES do json_encode() do controller.
// Bufferizar a saída inteira e deixar os endpoints JSON (responderJson() no
// PagamentoController, por exemplo) descartarem esse lixo com ob_clean()
// antes de emitir o corpo real garante que a resposta HTTP nunca fica
// corrompida por efeitos colaterais de código que não deveriam gerar saída.
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

// §PAY-CSP-01: form-action só com 'self' bloqueava o próprio checkout —
// PagamentoController::iniciarMercadoPago()/iniciarPagSeguro() terminam com um
// redirect 302 do POST do formulário direto pro checkout do gateway (domínio
// externo), e o Chrome aplica form-action também no destino final do redirect
// de um form, não só na origem. Sem os domínios dos gateways aqui, NENHUM
// pagamento real (produção ou sandbox) chegava a abrir o checkout — achado
// testando o sandbox MercadoPago (erro no console: "violates ... form-action
// 'self'"). Inclui os domínios de checkout do MercadoPago (produção e
// sandbox) e do PagSeguro (produção e sandbox). Mantido mesmo após o
// checkout transparente porque o fluxo antigo (iniciarMercadoPago/
// iniciarPagSeguro) continua no código como fallback.
//
// §PAY-CSP-02 (checkout transparente): Payment Brick do MercadoPago carrega
// o SDK de sdk.mercadopago.com, usa iframes de secure-fields pra tokenizar
// cartão sem o número passar pelo nosso JS (mercadolibre.com/mercadopago.com),
// e busca assets em http2.mlstatic.com. PagSeguroDirectPayment.js vem de
// stc(.sandbox).pagseguro.uol.com.br. Sem essas origens em script-src/
// frame-src/connect-src, o Brick não carrega e a tokenização de cartão do
// PagSeguro falha silenciosamente.
// §PAY-CSP-03: o Brick injeta seus próprios <script>/<iframe> filhos em
// runtime (device fingerprint /tracks, secure-fields) sem usar o nosso
// nonce — impossível prever hash/nonce desses scripts com antecedência.
// A solução documentada pra SDKs de terceiros assim é 'strict-dynamic':
// com ele, um script já confiável (o sdk.mercadopago.com carregado com
// nosso nonce) pode inserir outros scripts em runtime e o browser confia
// neles automaticamente, sem precisar listar cada host. Browsers que
// suportam strict-dynamic ignoram a allowlist de hosts em script-src (por
// isso o https://unpkg.com/https://cdn.jsdelivr.net continuam como
// fallback pra navegadores mais antigos que não suportam strict-dynamic).
// connect-src também precisou de api.mercadolibre.com (telemetria do
// Brick) e http2.mlstatic.com (assets/i18n) além dos domínios de API.
// §PAY-CSP-04: o Brick carrega o módulo antifraude "device fingerprint"
// (armor) do próprio Mercado Livre — mercadolibre.com/mercadolivre.com,
// não só mercadopago.com/mlstatic.com. Ele faz XHR (connect-src), carrega
// imagens de tracking (img-src) e abre um iframe de sessão (frame-src)
// nesses domínios. Sem eles o Brick renderiza os métodos de pagamento mas
// trava ao selecionar "Cartão de crédito" (é esse módulo que monta os
// campos de número/validade/CVV).
$cspPolicy = "default-src 'self' data: blob:; base-uri 'self'; object-src 'none'; form-action 'self' https://www.mercadopago.com.br https://www.mercadopago.com https://sandbox.mercadopago.com.br https://sandbox.mercadopago.com https://pagseguro.uol.com.br https://sandbox.pagseguro.uol.com.br; frame-ancestors 'self'; frame-src 'self' https://www.mercadopago.com.br https://www.mercadopago.com https://sandbox.mercadopago.com.br https://sandbox.mercadopago.com https://http2.mlstatic.com https://www.mercadolibre.com https://www.mercadolivre.com https://api-static.mercadopago.com https://secure-fields.mercadopago.com; script-src 'self' 'nonce-" . CSP_SCRIPT_NONCE . "' 'strict-dynamic' https://unpkg.com https://cdn.jsdelivr.net https://sdk.mercadopago.com https://http2.mlstatic.com https://api-static.mercadopago.com https://secure-fields.mercadopago.com https://stc.pagseguro.uol.com.br https://stc.sandbox.pagseguro.uol.com.br https://www.googletagmanager.com https://connect.facebook.net; script-src-attr 'none'; style-src 'self' 'unsafe-inline' https://unpkg.com; font-src 'self' data:; img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://http2.mlstatic.com https://www.mercadolibre.com https://www.mercadolivre.com https://www.facebook.com https://www.googletagmanager.com; connect-src 'self' https://viacep.com.br https://nominatim.openstreetmap.org https://router.project-osrm.org https://api.mercadopago.com https://sdk.mercadopago.com https://events.mercadopago.com https://api.mercadolibre.com https://www.mercadolibre.com https://www.mercadolivre.com https://api-static.mercadopago.com https://secure-fields.mercadopago.com https://http2.mlstatic.com https://ws.pagseguro.uol.com.br https://ws.sandbox.pagseguro.uol.com.br https://stc.pagseguro.uol.com.br https://stc.sandbox.pagseguro.uol.com.br https://www.google-analytics.com https://region1.google-analytics.com https://www.facebook.com https://www.google.com https://ad.doubleclick.net;";
if (!headers_sent()) {
    header('Content-Security-Policy: ' . $cspPolicy);
    header('X-Request-ID: ' . REQUEST_ID);
}

// Core (alguns controllers fazem extends BaseController)
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Services/AuthService.php';
require_once __DIR__ . '/src/Services/Logger.php';
require_once __DIR__ . '/src/Controllers/BaseController.php';

// ─────────────────────────────────────────────────────────────────────────────
// Error handling: registra fatal e exceptions com contexto (pra parar de “500 fantasma”)
// ─────────────────────────────────────────────────────────────────────────────
set_exception_handler(function (Throwable $e): void {
    Logger::exception('Router', 'exception_handler', 'PHP', $e, [
        'uri'  => $_SERVER['REQUEST_URI'] ?? null,
        'code' => 'RTR-500',
    ]);

    http_response_code(500);
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<h1>Erro interno do servidor</h1>';
        echo '<pre>' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "
" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>';
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

// ── Sessão segura ──────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();
require_once __DIR__ . '/src/Services/MarketingAttributionService.php';
MarketingAttributionService::capture();

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

    // Fallback recursivo: domínios organizados em subpastas (Models/Catalog,
    // Models/Dispatch, Models/Vehicle, Services/Dispatch, Services/Pedido,
    // Financial etc.) não são achados pelos caminhos planos acima. Varre src/
    // uma única vez, monta um mapa {ClasseSemExtensao => caminho} e cacheia.
    // Nomes de classe são únicos no projeto, então não há ambiguidade.
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
// Em public_html (raiz) será '' — nenhuma configuração necessária.
// Para subpasta, force: define('FORCE_BASEPATH','/minha-subpasta') em config.php
$basePath = '';
if (defined('FORCE_BASEPATH') && trim((string)FORCE_BASEPATH) !== '') {
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
        '/'                 => ['AuthController', 'landing', null],
        '/sitemap.xml'      => ['AuthController', 'sitemap', null],
        '/login'            => ['AuthController', 'loginForm', null],
        '/pre-cotacao'      => ['AuthController', 'preCotacaoForm', null],
        '/logout'           => ['AuthController', 'logout', null],
        '/auth/session-status' => ['AuthController', 'sessionStatus', null],
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
        '/admin/pedidos/json'        => ['AdminController', 'pedidosJson', 'admin'],
        '/admin/pedido/novo'         => ['AdminController', 'pedidoCriarForm', 'admin'],
        '/admin/pedido/criar'        => ['AdminController', 'pedidoCriarForm', 'admin'],
        '/admin/pedido/custo'        => ['AdminController', 'pedidoCalcularCusto', 'admin'],
        '/admin/veiculos/ajax'       => ['AdminController', 'veiculosAjax', 'admin'],
        '/admin/clientes/ajax'       => ['AdminController', 'clientesAjax', 'admin'],
        '/admin/oficinas/ajax'       => ['AdminController', 'oficinasAjax', 'admin'],
        '/admin/financeiro'          => ['AdminController', 'financeiro', 'admin'],
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
        '/admin/env'                 => ['AdminController', 'envGovernanca', 'admin'],
        '/admin/env/auditoria'       => ['AdminEnvAuditController', 'index', 'admin'],
        '/admin/servicos'            => ['AdminController', 'servicos', 'admin'],
        '/admin/servico/novo'        => ['AdminController', 'servicoForm', 'admin'],
        '/admin/feriados'            => ['AdminController', 'feriados', 'admin'],
        '/admin/cidades'             => ['AdminController', 'cidades', 'admin'],

        // ROADMAP socorro automotivo — Etapa 1: catálogo estruturado (service_types),
        // distinto de /admin/servicos (atalhos rápidos do painel do cliente).
        '/admin/catalogo-servicos/tipos'        => ['AdminServiceCatalogController', 'tipos', 'admin'],
        '/admin/catalogo-servicos/tipo/novo'    => ['AdminServiceCatalogController', 'tipoForm', 'admin'],
        '/admin/catalogo-servicos/capacidades'  => ['AdminServiceCatalogController', 'capacidades', 'admin'],
        '/admin/catalogo-servicos/tarifas'      => ['AdminServiceCatalogController', 'tarifas', 'admin'],
        '/admin/catalogo-servicos/compatibilidade' => ['AdminServiceCatalogController', 'compatibilidade', 'admin'],

        '/admin/catalogo-veiculos'             => ['AdminVehicleCatalogController', 'marcas', 'admin'],
        '/admin/catalogo-veiculos/marca/novo'   => ['AdminVehicleCatalogController', 'marcaForm', 'admin'],
        '/admin/catalogo-veiculos/modelo/novo'  => ['AdminVehicleCatalogController', 'modeloForm', 'admin'],
        '/admin/catalogo-veiculos/versao/novo'  => ['AdminVehicleCatalogController', 'versaoForm', 'admin'],

        // §CATALOGO-VISUAL-01: públicas de propósito (sem perfil) — o
        // cadastro de caminhão do guincheiro acontece ANTES do login
        // existir (registro público), e reaproveita o MESMO catálogo do
        // cliente. Só leitura, sem dado sensível.
        '/veiculo-catalogo/marcas'  => ['VehicleCatalogController', 'marcas', null],
        '/veiculo-catalogo/modelos' => ['VehicleCatalogController', 'modelos', null],
        // ROADMAP socorro automotivo — Etapa 13: precificação por zona/cidade
        // (pricing_zones/service_price_rules) — schema existia desde
        // migration_pricing_zones_v1.sql, mas sem tela admin nenhuma até
        // 26/07/2026.
        '/admin/precificacao/zonas' => ['AdminPricingZoneController', 'zonas', 'admin'],
        '/admin/demanda-territorial' => ['AdminPricingZoneController', 'demandaTerritorial', 'admin'],
        '/admin/produtos'            => ['AdminProdutoController', 'index', 'admin'],
        '/admin/produto/novo'        => ['AdminProdutoController', 'form', 'admin'],
        '/admin/checklists-incompletos' => ['AdminProofOfServiceController', 'checklistsIncompletos', 'admin'],
        '/admin/planejamento'        => ['AdminPlanejamentoController', 'index', 'admin'],
        '/guincho/estoque'           => ['GuinchoController', 'estoque', 'guincho'],

        '/pagamento/sucesso'  => ['PagamentoController', 'sucesso', 'cliente'],
        '/pagamento/falha'    => ['PagamentoController', 'falha', 'cliente'],
        '/pagamento/pendente' => ['PagamentoController', 'pendente', 'cliente'],
    ],
    'POST' => [
        '/pre-cotacao'      => ['AuthController', 'preCotacao', null],
        '/pre-cotacao/aceitar' => ['AuthController', 'aceitarPreCotacao', null],
        '/login'                 => ['AuthController', 'login', null],
        '/registro/cliente'       => ['AuthController', 'registroCliente', null],
        '/registro/guincho'       => ['AuthController', 'registroGuincho', null],
        '/registro/especialista'  => ['AuthController', 'registroEspecialista', null],
        '/especialista/disponibilidade' => ['EspecialistaController', 'disponibilidade', 'especialista'],
        '/especialista/atendimento/aceitar/' => ['EspecialistaController', 'aceitar', 'especialista'],
        '/especialista/atendimento/status/' => ['EspecialistaController', 'transicionar', 'especialista'],
        '/admin/especialista/aprovar' => ['AdminController', 'especialistaAprovar', 'admin'],
        '/admin/especialista/suspender' => ['AdminController', 'especialistaSuspender', 'admin'],
        '/admin/marketing/campanha/salvar' => ['AdminFinanceAttributionController', 'campanhaSalvar', 'admin'],
        '/admin/marketing/prospeccao/buscar' => ['AdminFinanceAttributionController', 'prospeccaoBuscar', 'admin'],
        '/admin/marketing/prospeccao/regioes/salvar' => ['AdminFinanceAttributionController', 'prospeccaoRegiaoSalvar', 'admin'],
        '/admin/marketing/prospeccao/sincronizar-zonas' => ['AdminFinanceAttributionController', 'prospeccaoSincronizarZonas', 'admin'],
        '/admin/prospeccao/sincronizar-zonas' => ['AdminFinanceAttributionController', 'prospeccaoSincronizarZonas', 'admin'],
        '/admin/financeiro/marketing/gasto' => ['AdminFinanceAttributionController', 'gastoSalvar', 'admin'],
        '/admin/financeiro/marketing/importar' => ['AdminFinanceAttributionController', 'gastoImportar', 'admin'],
        '/admin/financeiro/marketing/excluir' => ['AdminFinanceAttributionController', 'gastoExcluir', 'admin'],
        '/admin/prospeccao/buscar' => ['AdminFinanceAttributionController', 'prospeccaoBuscar', 'admin'],
        '/admin/prospeccao/regioes/salvar' => ['AdminFinanceAttributionController', 'prospeccaoRegiaoSalvar', 'admin'],
        '/senha/esqueceu'         => ['AuthController', 'esqueceuSenha',     null],
        '/senha/redefinir'        => ['AuthController', 'redefinirSenha',    null],


        '/cliente/veiculo/salvar'  => ['ClienteController', 'veiculoSalvar', 'cliente'],
        '/cliente/veiculo/deletar' => ['ClienteController', 'veiculoDeletar', 'cliente'],
        '/cliente/oficina/salvar'  => ['ClienteController', 'oficinaSalvar', 'cliente'],
        '/cliente/oficina/deletar' => ['ClienteController', 'oficinaDeletar', 'cliente'],
        '/cliente/pedido/criar'    => ['ClienteController', 'pedidoCriar', 'cliente'],
        '/cliente/triagem/responder' => ['ClienteController', 'triagemResponder', 'cliente'],
        '/cliente/triagem/avaliar' => ['ClienteController', 'triagemAvaliarJson', 'cliente'],
        '/cliente/perfil/salvar'   => ['ClienteController', 'perfilSalvar', 'cliente'],
        '/cliente/pedido/rascunho' => ['ClienteController', 'pedidoRascunho', 'cliente'],
        '/cliente/chat/enviar'     => ['ClienteController', 'chatEnviar', 'cliente'],

        '/guincho/localizacao'      => ['GuinchoController', 'atualizarLocalizacao', 'guincho'],
        '/guincho/disponibilidade'  => ['GuinchoController', 'toggleDisponibilidade', 'guincho'],
        '/guincho/perfil/salvar'    => ['GuinchoController', 'perfilSalvar',          'guincho'],
        '/guincho/operacao/salvar'  => ['GuinchoController', 'perfilOperacaoSalvar',  'guincho'],
        '/guincho/bancario/salvar'  => ['GuinchoController', 'perfilBancarioSalvar',  'guincho'],
        '/guincho/capacidades/salvar' => ['GuinchoController', 'capacidadesSalvar', 'guincho'],
        '/guincho/tornar-se-guincho/salvar' => ['GuinchoController', 'tornarSeGuinchoSalvar', 'guincho'],
        '/guincho/chat/enviar'      => ['GuinchoController', 'chatEnviar', 'guincho'],
        '/especialista/perfil/salvar' => ['EspecialistaController', 'perfilSalvar', 'especialista'],

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
        '/admin/env/salvar'          => ['AdminController', 'envSalvar', 'admin'],
        '/admin/simulador'           => ['AdminController', 'simularExecutar', 'admin'],
        '/admin/qa/run'             => ['AdminController', 'qaExecutar', 'admin'],
        '/admin/comunicado/salvar'   => ['AdminComunicadoController', 'save', 'admin'],
        '/admin/servico/salvar'      => ['AdminController', 'servicoSalvar', 'admin'],
        '/admin/servico/alternar'    => ['AdminController', 'servicoAlternar', 'admin'],
        '/admin/servico/remover'     => ['AdminController', 'servicoRemover', 'admin'],
        '/admin/catalogo-servicos/tipo/salvar'       => ['AdminServiceCatalogController', 'tipoSalvar', 'admin'],
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

        '/pagamento/mercadopago' => ['PagamentoController', 'iniciarMercadoPago', 'cliente'],
        '/pagamento/pagseguro'   => ['PagamentoController', 'iniciarPagSeguro', 'cliente'],

        // Checkout transparente (§CTP-01): cliente nunca sai de /pagamento/checkout/{id}.
        '/pagamento/mercadopago/pagar' => ['PagamentoController', 'mercadoPagoTransparente', 'cliente'],
        '/pagamento/pagseguro/pagar'   => ['PagamentoController', 'pagSeguroTransparente', 'cliente'],
        '/pagamento/complementar/mercadopago/pagar' => ['PagamentoController', 'complementarMercadoPago', 'cliente'],

        '/webhook/mercadopago'   => ['WebhookController', 'mercadoPago', null],
        '/webhook/pagseguro'     => ['WebhookController', 'pagSeguro', null],

        // Modo de debug global: espelho de erros JS pro log do servidor
        // (ver DebugController::jslog(), public/assets/js/debug.js). Rota
        // pública pois roda em qualquer tela autenticada ou não; noop quando
        // debug_mode_ativo está desligado.
        '/debug/jslog'           => ['DebugController', 'jslog', null],

        // Funcionário só CRIA demandas (nunca executa nada sensível
        // diretamente) — gerente é quem decide, em DemandaService::decidir().
        '/funcionario/demanda/criar' => ['FuncionarioController', 'demandaCriar', 'funcionario'],
        '/gerente/demanda/decidir'   => ['GerenteController', 'demandaDecidir', 'gerente'],
    ],
];

// Rotas dinâmicas (prefixo + id numérico no final)
// Formato: [metodo, prefixo, Controller, action, perfil]
$rotasDinamicas = [
        ['GET',  '/senha/redefinir/',       'AuthController', 'redefinirSenhaForm', null],
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
    ['GET', '/especialista/notificacoes', 'EspecialistaController', 'notificacoes', 'especialista'],
    ['GET', '/especialista/notificacoes/', 'EspecialistaController', 'notificacoes', 'especialista'],
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

// ── Resolve rota ─────────────────────────────────────────────────────────-
$controller = null; $action = null; $perfil = null; $id = null;

if (isset($rotas[$metodo][$uri])) {
    [$controller, $action, $perfil] = $rotas[$metodo][$uri];
} else {
    // Página local SEO: somente o slug de cidade é dinâmico; dashboards têm
    // rotas explícitas e continuam sendo resolvidos antes desta regra.
    if ($controller === null && $metodo === 'GET'
        && preg_match('~^/guincho/([a-z0-9]+(?:-[a-z0-9]+)*)$~', $uri, $m)) {
        $controller = 'AuthController';
        $action = 'cidadePublica';
        $perfil = null;
        $id = $m[1];
    }

    // API operacional de pedidos: possui sub-recursos depois do ID, por isso
    // precisa ser resolvida antes das rotas dinâmicas numéricas legadas.
    if ($controller === null && $metodo === 'GET'
        && preg_match('~^/api/routing/osrm/route/v1/driving/(.+)$~', $uri, $m)) {
        $controller = 'RoutingApiController';
        $action = 'route';
        $perfil = null;
        $id = $m[1];
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
    // Compatibilidade com links antigos do catálogo: /modelo/{id}/versoes.
    // A rota canônica agora é /modelo/{id}; redirecionar evita 404 em cache,
    // favoritos e páginas antigas ainda abertas.
    if ($controller === null && $metodo === 'GET'
        && preg_match('~^/admin/catalogo-veiculos/modelo/(\d+)/versoes$~', $uri, $m)) {
        $controller = 'AdminVehicleCatalogController';
        $action = 'versoesLegado';
        $perfil = 'admin';
        $id = (int)$m[1];
    }    foreach ($rotasDinamicas as $r) {
        if ($controller !== null) break;
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
    Logger::event([
        'level' => Logger::LEVEL_WARN,
        'class' => 'Router',
        'function' => 'resolve',
        'system' => 'ROUTER',
        'phase' => 'route_lookup',
        'code' => 'RTR-001',
        'message' => 'Rota não encontrada.',
        'context' => [
            'metodo' => $metodo,
            'uri' => $uri,
            'basePath' => $basePath,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        ],
    ]);

    http_response_code(404);
    echo '<h1>404 — Página não encontrada</h1>';
    exit;
}

// ── Rate limiting (rotas sensíveis) ───────────────────────────────────────
$rotasSensiveis = ['/login', '/registro/cliente', '/registro/guincho', '/registro/especialista'];
if (in_array($uri, $rotasSensiveis, true) && $metodo === 'POST') {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $rotaRateLimit = ltrim($uri, '/');
    if (!AuthService::verificarRateLimit($ip, $rotaRateLimit)) {
        http_response_code(429);
        echo '<h1>429 — Muitas tentativas. Aguarde alguns minutos.</h1>';
        exit;
    }
}

// ── Autenticação ─────────────────────────────────────────────────────────-
if ($perfil !== null) {
    // O router também precisa usar AuthService: assim AJAX recebe 401 JSON em vez
    // de seguir um redirect e tentar interpretar a tela HTML de login como JSON.
    $isPassivePolling = preg_match('~/(status|pedidos-disponiveis|pedidos|chat)(/|$)~', $uri) === 1
        && $metodo === 'GET';
    AuthService::requireAuth($perfil, !$isPassivePolling);
}

// ── Executa controller ───────────────────────────────────────────────────-
$controllerFile = __DIR__ . '/src/Controllers/' . $controller . '.php';
if (!is_file($controllerFile) && $controller === 'OrdersApiController') {
    $controllerFile = __DIR__ . '/src/Api/Admin/OrdersApiController.php';
}
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
