<?php
require_once __DIR__ . '/../Services/NotificacaoService.php';
// File: guinchafacil/src/Controllers/AuthController.php

// Refatorado com transações seguras e validações aprimoradas



class AuthController extends BaseController

{

    private const UPLOAD_MAX_SIZE = 5 * 1024 * 1024; // 5MB

    private const ALLOWED_MIME_TYPES = [

        'jpg' => 'image/jpeg',

        'jpeg' => 'image/jpeg',

        'png' => 'image/png',

        'pdf' => 'application/pdf'

    ];



    public function __construct() 

    { 

        parent::__construct(); 

    }



    // ─── LOGIN ────────────────────────────────────────────────────

    public function landing(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirectByProfile();
            return;
        }
        require __DIR__ . '/../Views/public/landing.php';
    }

    /** Página SEO local gerada a partir de cidade + zonas ativas. */
    public function cidadePublica(string $slug): void
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            http_response_code(404);
            echo 'Página não encontrada.';
            return;
        }

        $cidade = Cidade::buscarPorSlug($slug);
        if (!$cidade || (int)($cidade['ativo'] ?? 0) !== 1) {
            http_response_code(404);
            echo 'Página não encontrada.';
            return;
        }

        $zonas = PricingZone::listarPorOrdemExpansao((int)$cidade['id'], true);
        if (!$zonas) {
            http_response_code(404);
            echo 'Atendimento ainda não disponível nesta cidade.';
            return;
        }

        $indexavel = false;
        foreach ($zonas as $zona) {
            if (($zona['status_expansao'] ?? '') === 'pedra_viva') {
                $indexavel = true;
                break;
            }
        }
        if (!$indexavel) {
            header('X-Robots-Tag: noindex, follow');
        }

        require __DIR__ . '/../Views/public/cidade.php';
    }

    /** Sitemap público derivado das cidades que têm cobertura ativa. */
    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $urls = [
            ['loc' => 'https://guinchafacil.com.br/', 'freq' => 'weekly', 'priority' => '1.0'],
            ['loc' => 'https://guinchafacil.com.br/pre-cotacao', 'freq' => 'weekly', 'priority' => '0.9'],
        ];

        foreach (Cidade::listarAtivas() as $cidade) {
            $zonas = PricingZone::listarPorOrdemExpansao((int)$cidade['id'], true);
            $temPedraViva = false;
            foreach ($zonas as $zona) {
                if (($zona['status_expansao'] ?? '') === 'pedra_viva') {
                    $temPedraViva = true;
                    break;
                }
            }
            if ($temPedraViva && !empty($cidade['slug'])) {
                $urls[] = ['loc' => 'https://guinchafacil.com.br/guincho/' . rawurlencode((string)$cidade['slug']), 'freq' => 'weekly', 'priority' => '0.8'];
            }
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            echo '<url><loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc><changefreq>' . $url['freq'] . '</changefreq><priority>' . $url['priority'] . '</priority></url>';
        }
        echo '</urlset>';
    }

    /**
     * Pre-cotacao publica: entrega valor antes do cadastro, sem criar pedido
     * ou pagamento. O pedido autenticado continua sendo o unico caminho de
     * despacho/cobranca.
     */
    public function preCotacaoForm(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirectByProfile();
            return;
        }
        $csrf_token = $this->generateCSRFToken();
        $cotacao = $_SESSION['pre_cotacao'] ?? null;
        $flash = $this->pullFlash();
        require __DIR__ . '/../Views/public/pre-cotacao.php';
    }

    public function preCotacao(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(419);
            $this->setFlashMessage('Sessao expirada. Tente novamente.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }

        $agora = time();
        $ultima = (int)($_SESSION['pre_cotacao_ultima'] ?? 0);
        if ($ultima > 0 && ($agora - $ultima) < 8) {
            $this->setFlashMessage('Aguarde alguns segundos antes de gerar outra cotacao.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }
        $_SESSION['pre_cotacao_ultima'] = $agora;

        $lat = filter_var($_POST['lat_origem'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['lng_origem'] ?? null, FILTER_VALIDATE_FLOAT);
        $localizacao = trim((string)($_POST['localizacao'] ?? ''));
        $latDestino = filter_var($_POST['lat_destino'] ?? null, FILTER_VALIDATE_FLOAT);
        $lngDestino = filter_var($_POST['lng_destino'] ?? null, FILTER_VALIDATE_FLOAT);
        $destino = trim((string)($_POST['destino'] ?? ''));
        $tipo = trim((string)($_POST['tipo_problema'] ?? ''));
        $categoria = trim((string)($_POST['categoria'] ?? 'popular'));
        $categorias = ['moto', 'popular', 'suv', 'caminhonete', 'eletrico'];

        if (($lat === false || $lng === false) && $localizacao !== '') {
            require_once __DIR__ . '/../Services/GeocodingService.php';
            $geocodificado = (new GeocodingService())->geocode($localizacao);
            if ($geocodificado) {
                $lat = filter_var($geocodificado['lat'] ?? null, FILTER_VALIDATE_FLOAT);
                $lng = filter_var($geocodificado['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            }
        }
        if ($lat === false || $lng === false || $lat < -34 || $lat > 5 || $lng < -74 || $lng > -28) {
            $this->setFlashMessage('Informe uma localizacao valida no Brasil.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }
        $serviceMap = ['colisao'=>'TOW_CAR','reboque'=>'TOW_CAR','mecanica'=>'MECHANICAL_ASSISTANCE','bateria'=>'JUMP_START','eletrica'=>'ELECTRICAL_DIAGNOSIS','pneu'=>'TIRE_CHANGE','combustivel'=>'FUEL_DELIVERY','outro'=>'MECHANICAL_ASSISTANCE'];
        if (!isset($serviceMap[$tipo])) $tipo = 'outro';
        require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
        $serviceType = ServiceType::buscarPorCodigo($serviceMap[$tipo]);
        $serviceTypeId = $serviceType ? (int)$serviceType['id'] : 0;
        $requiresDestination = $serviceType ? ServiceType::requiresDestination($serviceType) : in_array($tipo, ['colisao','reboque'], true);
        if (($latDestino === false || $lngDestino === false) && $destino !== '') {
            require_once __DIR__ . '/../Services/GeocodingService.php';
            $destinoGeo = (new GeocodingService())->geocode($destino);
            if ($destinoGeo) { $latDestino = filter_var($destinoGeo['lat'] ?? null, FILTER_VALIDATE_FLOAT); $lngDestino = filter_var($destinoGeo['lng'] ?? null, FILTER_VALIDATE_FLOAT); }
        }
        if ($requiresDestination && ($latDestino === false || $lngDestino === false || $latDestino < -34 || $latDestino > 5 || $lngDestino < -74 || $lngDestino > -28)) {
            $this->setFlashMessage('Para este atendimento, informe o endereco de destino do veiculo.', 'error');
            $this->redirect('/pre-cotacao'); return;
        }
        if ($latDestino !== false && $lngDestino !== false && $latDestino >= -34 && $latDestino <= 5 && $lngDestino >= -74 && $lngDestino <= -28) {
            $distancia = self::distanciaKm((float)$lat, (float)$lng, (float)$latDestino, (float)$lngDestino);
        } else {
            // A pre-cotacao sem destino usa uma distancia minima conservadora;
            // o valor final sempre sera recalculado no fluxo autenticado.
            $distancia = 5.0;
            $latDestino = null;
            $lngDestino = null;
        }
        $distancia = min(500.0, max(0.5, round($distancia, 2)));
        if (!in_array($categoria, $categorias, true)) $categoria = 'popular';

        require_once __DIR__ . '/../Services/CoberturaService.php';
        require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
        $diagnosticoCobertura = $serviceTypeId > 0 ? CoberturaService::diagnosticarAtendimento([
            'attendance_mode' => $serviceType['attendance_mode'] ?? '',
            'lat_origem' => (float)$lat,
            'lng_origem' => (float)$lng,
            'service_type_id' => $serviceTypeId,
            'tipo_problema' => $tipo,
            'categoria' => $categoria,
        ]) : ['status' => 'sem_servico', 'pode_cobrar' => false, 'mensagem' => 'Não conseguimos identificar o tipo de atendimento para validar a cobertura.'];
        if (($diagnosticoCobertura['pode_cobrar'] ?? true) !== true) {
            PreQuoteDemandService::registrar([
                'lat_origem' => (float)$lat,
                'lng_origem' => (float)$lng,
                'tipo_problema' => $tipo,
                'categoria' => $categoria,
            ], 'quote');
            PreQuoteDemandService::registrarSemCobertura([
                'lat_origem' => (float)$lat,
                'lng_origem' => (float)$lng,
                'tipo_problema' => $tipo,
                'categoria' => $categoria,
            ]);
            $this->setFlashMessage((string)($diagnosticoCobertura['mensagem'] ?? 'No momento não há cobertura para essa ocorrência.'), 'error');
            $this->redirect('/pre-cotacao');
            return;
        }

        require_once __DIR__ . '/../Services/TarifaService.php';
        $detalhe = null;
        $origemTarifa = 'reboque';
        if ($serviceTypeId > 0 && (($serviceType['attendance_mode'] ?? '') === 'ON_SITE')) {
            require_once __DIR__ . '/../Services/EspecialistaPricingService.php';
            $precoEspecialista = EspecialistaPricingService::calcular((string)($serviceType['code'] ?? ''), $distancia);
            if ($precoEspecialista !== null) {
                $detalhe = $precoEspecialista['detalhe'];
                $detalhe['valor'] = $precoEspecialista['customer_amount'];
                $origemTarifa = 'especialista_catalogo';
            }
        }
        if ($serviceTypeId > 0) {
            require_once __DIR__ . '/../Services/Pricing/ZonePricingService.php';
            $zona = ZonePricingService::calcularPreco((float)$lat, (float)$lng, $serviceTypeId, $categoria, $distancia);
            if ($detalhe === null && $zona !== null) { $detalhe = $zona['detalhe']; $detalhe['valor'] = $zona['valor']; $origemTarifa = 'zona'; }
            if ($detalhe === null && (($serviceType['attendance_mode'] ?? '') !== 'TOWING')) {
                require_once __DIR__ . '/../Models/Catalog/ServicePricingRule.php';
                $precoServico = ServicePricingRule::calcularTotal($serviceTypeId, $distancia, null, null);
                if ($precoServico !== null) { $detalhe = $precoServico['detalhe']; $detalhe['valor'] = $precoServico['valor']; $origemTarifa = 'servico'; }
            }
        }
        if ($detalhe === null) $detalhe = TarifaService::calcularDetalhado($distancia, $categoria, false);
        PreQuoteDemandService::registrar([
            'lat_origem' => (float)$lat, 'lng_origem' => (float)$lng,
            'tipo_problema' => $tipo, 'categoria' => $categoria,
        ]);
        $token = bin2hex(random_bytes(32));
        $_SESSION['pre_cotacao'] = [
            'token_hash' => hash('sha256', $token),
            'criado_em' => date('c'),
            'expira_em' => $agora + 900,
            'lat_origem' => round((float)$lat, 7),
            'lng_origem' => round((float)$lng, 7),
            'lat_destino' => $latDestino !== null ? round((float)$latDestino, 7) : null,
            'lng_destino' => $lngDestino !== null ? round((float)$lngDestino, 7) : null,
            'tipo_problema' => substr($tipo, 0, 40),
            'categoria' => $categoria,
            'service_type_id' => $serviceTypeId,
            'service_code' => $serviceType['code'] ?? 'TOW_CAR',
            'requires_destination' => $requiresDestination,
            'destino' => substr($destino, 0, 220),
            'distancia_km' => $distancia,
            'valor' => (float)$detalhe['valor'],
            'tarifa' => $detalhe,
            'origem_tarifa' => $origemTarifa,
        ];
        $this->redirect('/pre-cotacao?resultado=1');
    }

    public function aceitarPreCotacao(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(419);
            $this->setFlashMessage('Sessao expirada. Tente novamente.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }
        $cotacao = $_SESSION['pre_cotacao'] ?? null;
        if (!$cotacao || (int)($cotacao['expira_em'] ?? 0) <= time()) {
            unset($_SESSION['pre_cotacao']);
            $this->setFlashMessage('A cotacao expirou. Gere uma nova para continuar.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }
        $_SESSION['pre_cotacao']['aceita_em'] = date('c');
        $_SESSION['pre_cotacao']['status'] = 'aceita';
        require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
        PreQuoteDemandService::registrar($_SESSION['pre_cotacao'], 'accepted');
        $this->redirect('/registro/cliente?retorno=%2Fcliente%2Fpedido%2Fnovo');
    }

    private static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rad = M_PI / 180;
        $a = sin(($lat2 - $lat1) * $rad / 2) ** 2
            + cos($lat1 * $rad) * cos($lat2 * $rad) * sin(($lng2 - $lng1) * $rad / 2) ** 2;
        return 6371.0 * 2 * asin(min(1.0, sqrt($a)));
    }

    public function loginForm(): void

    {

        if ($this->isAuthenticated()) { 

            $this->redirectByProfile(); 

            return; 

        }

        

        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        $retorno = AuthService::sanitizeReturnPath((string)($_GET['retorno'] ?? '/'));

        require __DIR__ . '/../Views/auth/login.php';

    }



    public function login(): void

    {

        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {

            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');

            $this->redirect('/login'); 

            return;

        }



        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        $senha = $_POST['senha'] ?? $_POST['password'] ?? '';



        if (!$email || empty($senha)) {

            $this->setFlashMessage('Email e senha são obrigatórios.', 'error');

            $this->redirect('/login'); 

            return;

        }



        $pdo = getPDO();

        

        try {

            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);



            if (!$user || !password_verify($senha, $user['senha_hash'] ?? '')) {

                $this->setFlashMessage('Email ou senha incorretos.', 'error');

                $this->redirect('/login'); 

                return;

            }



            // Atualiza último login

            $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);



            // Cria sessão com regeneração segura e relógios de expiração.
            AuthService::initializeAuthenticatedSession([
                'id'    => (int)$user['id'],
                'nome'  => $user['nome'],
                'email' => $user['email'],
                'tipo'  => $user['tipo'],
                'ativo' => (bool)$user['ativo'],
            ]);

            $retorno = AuthService::sanitizeReturnPath((string)($_POST['retorno'] ?? '/'));
            if ($retorno !== '/') {
                $this->redirect($retorno);
                return;
            }
            $this->redirectByProfile($user['tipo']);

            

        } catch (Throwable $e) {

            error_log("Erro no login: " . $e->getMessage());

            $this->setFlashMessage('Erro interno. Tente novamente.', 'error');

            $this->redirect('/login');

        }

    }



    // ─── LOGOUT ───────────────────────────────────────────────────

    public function logout(): void

    {

        AuthService::logout();
        $this->redirect('/login');

    }



    public function sessionStatus(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode(AuthService::sessionStatus(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ─── REGISTRO CLIENTE ─────────────────────────────────────────

    public function registroClienteForm(): void

    {

        if ($this->isAuthenticated()) { 

            $this->redirectByProfile(); 

            return; 

        }

        

        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        $retorno = AuthService::sanitizeReturnPath((string)($_GET['retorno'] ?? '/'));

        require __DIR__ . '/../Views/auth/registrocliente.php';

    }



    public function registroCliente(): void

    {

        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {

            $this->setFlashMessage('Token inválido.', 'error');

            $this->redirect('/registro/cliente'); 

            return;

        }



        // Sanitização dos dados

        $dados = $this->sanitizarDadosCliente($_POST);

        $erros = $this->validarDadosCliente($dados);



        if (!empty($erros)) {

            $this->setFlashMessage(implode('<br>', $erros), 'error');

            $this->redirect('/registro/cliente'); 

            return;

        }



        $pdo = getPDO();

        

        try {

            $pdo->beginTransaction();



            // Verifica duplicatas com lock

            $dup = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR cpf = ? FOR UPDATE");

            $dup->execute([$dados['email'], $dados['cpf']]);

            

            if ($dup->fetch()) {

                throw new Exception('Email ou CPF já cadastrado.');

            }



            // Cria usuário

            $userId = $this->criarUsuario($pdo, $dados, 'cliente');
            require_once __DIR__ . '/../Services/MarketingAttributionService.php';
            $atribuicaoCadastro = MarketingAttributionService::current();
            $pdo->prepare('UPDATE usuarios SET utm_source_cadastro=?, utm_medium_cadastro=?, utm_campaign_cadastro=? WHERE id=?')
                ->execute([$atribuicaoCadastro['utm_source'] ?? null, $atribuicaoCadastro['utm_medium'] ?? null, $atribuicaoCadastro['utm_campaign'] ?? null, $userId]);



            // Cria endereço principal

            $this->criarEndereco($pdo, $userId, $dados, true);



            $pdo->commit();

            

            $this->setFlashMessage('Cadastro realizado! Faca login para continuar.', 'success');
            $retorno = AuthService::sanitizeReturnPath((string)($_POST['retorno'] ?? '/'));
            $destinoLogin = '/login' . ($retorno !== '/' ? '?retorno=' . rawurlencode($retorno) : '');
            $this->redirect($destinoLogin);

            

        } catch (Exception $e) {

            $pdo->rollBack();

            error_log("Erro registroCliente: " . $e->getMessage());

            $this->setFlashMessage($e->getMessage() ?: 'Erro interno. Tente novamente.', 'error');

            $this->redirect('/registro/cliente');

        }

    }



    // ─── REGISTRO ESPECIALISTA ─────────────────────────────────────────

    public function registroEspecialistaForm(): void
    {
        if ($this->isAuthenticated() && !(($_GET['admin'] ?? '') === '1' && (($_SESSION['user']['tipo'] ?? '') === 'admin'))) { 
            $this->redirectByProfile(); 
            return; 
        }
        
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        require __DIR__ . '/../Views/auth/registro_especialista.php';
    }

    public function registroEspecialista(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/registro/especialista'); 
            return;
        }

        // Sanitização e validação simplificada para especialista
        $dados = $this->sanitizarDadosCliente($_POST);
        $servicos = is_array($_POST['servicos'] ?? null) ? $_POST['servicos'] : [];
        $mapaServicos = [
            'chaveiro' => 'LOCKOUT',
            'eletrica' => 'ELECTRICAL_DIAG',
            'pneu' => 'TIRE_CHANGE',
            'mecanica' => 'BATTERY_JUMP',
            'combustivel' => 'FUEL_DELIVERY',
        ];
        $servicos = array_map(static fn($codigo) => $mapaServicos[$codigo] ?? $codigo, $servicos);
        $erros = $this->validarDadosCliente($dados);
        if (!$servicos) $erros[] = 'Selecione ao menos um servico.';
        if ($erros) {
            $this->setFlashMessage(implode('<br>', $erros), 'error');
            $this->redirect('/registro/especialista');
            return;
        }

        // ... (lógica de registro similar a registroCliente/Guincho, mas como 'especialista')
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? OR cpf = ? FOR UPDATE');
            $dup->execute([$dados['email'], $dados['cpf']]);
            if ($dup->fetch()) throw new Exception('Email ou CPF ja cadastrado.');
            $userId = $this->criarUsuario($pdo, $dados, 'especialista');
            $this->criarEndereco($pdo, $userId, $dados, true);
            
            require_once __DIR__ . '/../Models/Especialista.php';
            $especialistaId = Especialista::criar([
                'usuario_id' => $userId,
                'nome_profissional' => trim((string)($_POST['nome_profissional'] ?? '')),
                'cpf_cnpj' => $dados['cpf'],
                'documento_tipo' => trim((string)($_POST['documento_tipo'] ?? 'rg')),
                'documento_numero' => trim((string)($_POST['documento_numero'] ?? '')),
                'chave_pix' => trim((string)($_POST['chave_pix'] ?? $dados['cpf'])),
                'chave_pix_tipo' => trim((string)($_POST['chave_pix_tipo'] ?? 'cpf')),
                'bio' => trim((string)($_POST['bio'] ?? '')),
                'raio_atendimento_km' => max(1, min(100, (float)($_POST['raio_atendimento_km'] ?? 10))),
            ], $pdo);
            Especialista::vincularServicos($especialistaId, $servicos, $pdo);
            foreach (['documento_arquivo' => 'documento_identidade', 'selfie_arquivo' => 'selfie'] as $campo => $tipoDocumento) {
                $arquivo = $_FILES[$campo] ?? null;
                if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new DomainException('Envie os dois documentos obrigatórios para homologação.');
                }
                if (($arquivo['size'] ?? 0) <= 0 || ($arquivo['size'] ?? 0) > (defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 5242880)) {
                    throw new DomainException('Cada documento deve ter no máximo 5 MB.');
                }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$arquivo['tmp_name']);
                $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
                if (!$ext) throw new DomainException('Documentos devem ser imagens JPEG, PNG ou WEBP.');
                $base = defined('UPLOAD_PATH_DOCS') ? UPLOAD_PATH_DOCS : (dirname(__DIR__, 2) . '/storage/private/uploads');
                $dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'especialistas';
                if (!is_dir($dir) && !@mkdir($dir, 0770, true)) throw new RuntimeException('Não foi possível preparar o armazenamento.');
                $nome = $tipoDocumento . '_' . $especialistaId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $destino = $dir . DIRECTORY_SEPARATOR . $nome;
                $ok = is_uploaded_file((string)$arquivo['tmp_name']) ? move_uploaded_file((string)$arquivo['tmp_name'], $destino) : rename((string)$arquivo['tmp_name'], $destino);
                if (!$ok) throw new RuntimeException('Falha ao armazenar documento.');
                Especialista::adicionarDocumento($especialistaId, $tipoDocumento, $_POST['documento_numero'] ?? null, $nome, $pdo);
                $coluna = $campo;
                $pdo->prepare("UPDATE especialistas SET {$coluna}=? WHERE id=?")->execute([$nome, $especialistaId]);
            }

            $pdo->commit();
            $this->setFlashMessage('Cadastro de especialista realizado!', 'success');
            $this->redirect((($_POST['admin'] ?? '') === '1' && (($_SESSION['user']['tipo'] ?? '') === 'admin')) ? '/admin/especialistas' : '/login');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Erro registroEspecialista: ' . $e->getMessage());
            $this->setFlashMessage($e->getMessage(), 'error');
            $this->redirect('/registro/especialista');
        }
    }


    public function registroGuinchoForm(): void

    {

        if ($this->isAuthenticated()) { 

            $this->redirectByProfile(); 

            return; 

        }

        

        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        require_once __DIR__ . '/../Models/Cidade.php';
        $cidadesAtivas = Cidade::listarAtivas();

        require __DIR__ . '/../Views/auth/registroguincho.php';

    }



    public function registroGuincho(): void

    {

        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {

            $this->setFlashMessage('Token inválido.', 'error');

            $this->redirect('/registro/guincho'); 

            return;

        }



        // Sanitização dos dados

        $dados = $this->sanitizarDadosGuincho($_POST);

        $erros = $this->validarDadosGuincho($dados, $_FILES);



        if (!empty($erros)) {

            $this->setFlashMessage(implode('<br>', $erros), 'error');

            $this->redirect('/registro/guincho'); 

            return;

        }



        $pdo = getPDO();

        

        try {

            $pdo->beginTransaction();



            // Verifica duplicatas com lock

            $dup = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR cpf = ? FOR UPDATE");

            $dup->execute([$dados['email'], $dados['cpf']]);

            

            if ($dup->fetch()) {

                throw new Exception('Email ou CPF já cadastrado.');

            }



            // Verifica placa duplicada (só quando oferece reboque — especialista
            // sem guincho não informa placa).

            if (!empty($dados['oferece_reboque']) && $dados['placa_guincho'] !== '') {

                $dupPlaca = $pdo->prepare("SELECT id FROM guinchos WHERE placa_guincho = ? FOR UPDATE");

                $dupPlaca->execute([$dados['placa_guincho']]);

                if ($dupPlaca->fetch()) {

                    throw new Exception('Placa já cadastrada no sistema.');

                }

            }



            // Processa uploads

            $uploads = $this->processarUploads($_FILES);



            // Cria usuário

            $userId = $this->criarUsuario($pdo, $dados, 'guincho');

            // Cria endereco principal de operacao do guincho
            $this->criarEndereco($pdo, $userId, $dados, true);


            // Cria registro do guincho

            $guinchoData = [

                'usuario_id' => $userId,

                'cidade_id' => $dados['cidade_id'] ?? 0,

                'cnh_numero' => $dados['cnh_numero'],

                'cnh_validade' => $dados['cnh_validade'],

                'placa_guincho' => $dados['placa_guincho'],

                'cidade_placa' => $dados['cidade_placa'],

                'uf_placa' => $dados['uf_placa'],

                'capacidade_ton' => $dados['capacidade_ton'], // coluna real no banco é capacidade_ton

                'marca_caminhao' => $dados['marca_caminhao'] ?? null,

                'modelo_caminhao' => $dados['modelo_caminhao'] ?? null,

                'tipo_guincho' => $dados['tipo_guincho'] ?? null,

                'categoria_cnh' => $dados['categoria_cnh'] ?? null,

                'ear' => $dados['ear'] ?? null,

                'ano_fabricacao' => $dados['ano_fabricacao'] ?? null,

                'equipamentos_json' => $dados['equipamentos_json'] ?? null,

                'vehicle_brand_id' => $dados['vehicle_brand_id'] ?? null,

                'vehicle_model_id' => $dados['vehicle_model_id'] ?? null,

                'raio_cobertura_km' => $dados['raio_cobertura_km'] ?? 20,

                'chave_pix' => $dados['chave_pix'],

                'chave_pix_tipo' => $dados['chave_pix_tipo'],

                'lat_operacao' => $dados['lat_operacao'],

                'lng_operacao' => $dados['lng_operacao'],

                'foto_veiculo' => $uploads['foto_veiculo'] ?? null,

                'doc_cnh_frente' => $uploads['doc_cnh_frente'] ?? null,

                'doc_cnh_verso' => $uploads['doc_cnh_verso'] ?? null,

                'aprovado' => 0,

                'disponivel' => 0,

                'lat_atual' => null, // coluna real no banco é lat_atual

                'lng_atual' => null  // coluna real no banco é lng_atual

            ];



            $guinchoId = $this->criarGuincho($pdo, $guinchoData);



            if (!$guinchoId) {

                throw new Exception('Falha ao criar registro do guincho');

            }

            // Tipo de prestador: grava oferece_reboque; reboque_aprovado nasce 0
            // (docs de guincho conferidos pelo admin depois). Defensivo: só
            // atualiza se as colunas existirem (migration_prestador_tipo_v1).
            try {
                $pdo->prepare(
                    "UPDATE guinchos SET oferece_reboque = ?, reboque_aprovado = 0 WHERE id = ?"
                )->execute([(int)($dados['oferece_reboque'] ?? 1), (int)$guinchoId]);
            } catch (\Throwable $e) {
                error_log('[registroGuincho] oferece_reboque não gravado (rode migrate?): ' . $e->getMessage());
            }

            // Declara as capacidades de serviço escolhidas (não-reboque), nascem
            // PENDING para o admin aprovar. Reboque é gate por reboque_aprovado,
            // não por capacidade, então não entra aqui.
            $this->declararCapacidadesDoRegistro($pdo, (int)$guinchoId, (array)($dados['servicos'] ?? []));

            $pdo->commit();

            // §CELULAS-NITEROI-01 (05/08/2026): quando a célula da base do
            // prestador está 'pedra_morta', o cadastro é aceito normalmente
            // (não é bloqueado, ver validarDadosGuincho), mas a região ainda
            // não está operacional — sem isso, o parceiro assume que
            // recebeu chamados zero por BUG, não por a região ainda estar
            // em fase de validação. Mensagem só muda quando aplicável;
            // fluxo padrão (nao_ativada bloqueou antes de chegar aqui,
            // pedra_viva ou nenhuma célula mapeada) continua com a
            // mensagem genérica de sempre.
            $mensagemSucesso = 'Cadastro enviado! Aguarde a aprovação do administrador.';
            $latOperacaoCadastro = $dados['lat_operacao'] ?? null;
            $lngOperacaoCadastro = $dados['lng_operacao'] ?? null;
            if ($latOperacaoCadastro !== null && $lngOperacaoCadastro !== null) {
                require_once __DIR__ . '/../Services/Pricing/ZonePricingService.php';
                $zonaDoCadastro = ZonePricingService::resolverZonaPorCoordenada((float)$latOperacaoCadastro, (float)$lngOperacaoCadastro);
                if ($zonaDoCadastro !== null && ($zonaDoCadastro['status_expansao'] ?? '') === 'pedra_morta') {
                    $mensagemSucesso = 'Cadastro enviado! Sua documentação será analisada normalmente, mas atenção: a região "' . $zonaDoCadastro['name'] . '" ainda está em fase de validação e ainda não recebe chamados de clientes — isso não é um erro do seu cadastro, é assim até essa região ser ativada pela nossa equipe.';
                }
            }

            $this->setFlashMessage($mensagemSucesso, 'success');

            $this->redirect('/login');

            

        } catch (Exception $e) {

            $pdo->rollBack();

            $this->limparUploads($uploads ?? []);

            error_log("Erro registroGuincho: " . $e->getMessage());

            $this->setFlashMessage($e->getMessage() ?: 'Erro interno. Tente novamente.', 'error');

            $this->redirect('/registro/guincho');

        }

    }

    /**
     * Declara as capacidades de serviço escolhidas no cadastro (não-reboque),
     * dentro da MESMA transação do registro. Nascem PENDING para o admin
     * aprovar. Reboque não entra aqui — é gate por reboque_aprovado.
     */
    private function declararCapacidadesDoRegistro(\PDO $pdo, int $guinchoId, array $servicos): void
    {
        $mapa = [
            'chaveiro'    => ['AUTOMOTIVE_LOCKSMITH'],
            'eletrica'    => ['JUMP_START', 'BATTERY_TEST', 'BATTERY_REPLACEMENT', 'ELECTRICAL_DIAGNOSIS'],
            'pneu'        => ['TIRE_CHANGE', 'TIRE_INFLATION'],
            'mecanica'    => ['MECHANICAL_ASSISTANCE'],
            'combustivel' => ['FUEL_DELIVERY'],
        ];
        $codes = [];
        foreach ($servicos as $s) {
            if (isset($mapa[$s])) {
                $codes = array_merge($codes, $mapa[$s]);
            }
        }
        if (empty($codes)) {
            return;
        }
        try {
            $in = implode(',', array_fill(0, count($codes), '?'));
            $stmt = $pdo->prepare("SELECT id FROM service_types WHERE code IN ($in) AND active = 1");
            $stmt->execute($codes);
            $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($ids as $stId) {
                $pdo->prepare(
                    "INSERT IGNORE INTO provider_capabilities
                        (provider_id, service_type_id, enabled, approval_status, created_at, updated_at)
                     VALUES (?, ?, 0, 'PENDING', NOW(), NOW())"
                )->execute([$guinchoId, (int)$stId]);
            }
        } catch (\Throwable $e) {
            error_log('[registroGuincho] capacidades do cadastro não declaradas: ' . $e->getMessage());
        }
    }



    // ─── ESQUECEU SENHA ──────────────────────────────────────────

    public function esqueceuSenhaForm(): void

    {

        if ($this->isAuthenticated()) { 

            $this->redirectByProfile(); 

            return; 

        }

        

        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();

        require __DIR__ . '/../Views/auth/esqueceu_senha.php';

    }



    public function esqueceuSenha(): void

    {

        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {

            $this->redirect('/senha/esqueceu'); 

            return;

        }

        

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        $this->setFlashMessage('Se esse email existir, enviaremos um link de redefinição.', 'success');



        if ($email) {

            $pdo = getPDO();

            

            try {

                $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");

                $stmt->execute([$email]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                

                if ($user) {

                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                    

                    $token = bin2hex(random_bytes(32));

                    $expira = date('Y-m-d H:i:s', time() + 3600);

                    

                    $pdo->prepare("INSERT INTO password_resets (email, token, expira_em) VALUES (?,?,?)")

                        ->execute([$email, $token, $expira]);

                    

                    $link = (defined('APP_URL') ? APP_URL : '') . '/senha/redefinir/' . $token;

                    

                    $corpo = "<p>Olá, <b>" . htmlspecialchars($user['nome']) . "</b>!</p>

                             <p>Recebemos uma solicitação para redefinir sua senha.</p>

                             <p><a href='$link'>Clique aqui para redefinir sua senha</a></p>

                             <p>Este link é válido por 1 hora.</p>

                             <p>Se não foi você quem solicitou, ignore este email.</p>";

                    

                    $this->enviarEmail(

                        $email, 

                        'Redefinição de senha — GuinchaFácil', 

                        $corpo

                    );

                }

            } catch (Throwable $e) {

                error_log("Erro esqueceuSenha: " . $e->getMessage());

            }

        }

        

        $this->redirect('/login');

    }



    public function redefinirSenhaForm(string $token): void

    {

        $pdo = getPDO();

        

        try {

            $stmt = $pdo->prepare(

                "SELECT * FROM password_resets 

                 WHERE token = ? AND usado = 0 AND expira_em > NOW() 

                 LIMIT 1"

            );

            $stmt->execute([$token]);

            

            if (!$stmt->fetch()) {

                $this->setFlashMessage('Link inválido ou expirado.', 'error');

                $this->redirect('/senha/esqueceu'); 

                return;

            }

            

            $csrf_token = $this->generateCSRFToken();
            $flash = $this->pullFlash();

            require __DIR__ . '/../Views/auth/redefinir_senha.php';

            

        } catch (Throwable $e) {

            error_log("Erro redefinirSenhaForm: " . $e->getMessage());

            $this->setFlashMessage('Erro interno. Tente novamente.', 'error');

            $this->redirect('/senha/esqueceu');

        }

    }



    public function redefinirSenha(): void

    {

        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {

            $this->redirect('/login'); 

            return;

        }

        

        $token = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');

        $senha = $_POST['senha'] ?? '';

        $confirma = $_POST['confirmacao'] ?? '';



        if (strlen($senha) < 8 || $senha !== $confirma) {

            $this->setFlashMessage('Senha inválida ou as senhas não conferem (mínimo 8 caracteres).', 'error');

            $this->redirect('/senha/redefinir/' . $token); 

            return;

        }



        $pdo = getPDO();

        

        try {

            $stmt = $pdo->prepare(

                "SELECT * FROM password_resets 

                 WHERE token = ? AND usado = 0 AND expira_em > NOW() 

                 LIMIT 1"

            );

            $stmt->execute([$token]);

            $reset = $stmt->fetch(PDO::FETCH_ASSOC);



            if (!$reset) {

                $this->setFlashMessage('Link inválido ou expirado.', 'error');

                $this->redirect('/senha/esqueceu'); 

                return;

            }



            $pdo->beginTransaction();



            // Atualiza senha

            $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE email = ?")

                ->execute([password_hash($senha, PASSWORD_BCRYPT), $reset['email']]);

            

            // Marca token como usado

            $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE token = ?")

                ->execute([$token]);



            $pdo->commit();

            

            $this->setFlashMessage('Senha redefinida com sucesso!', 'success');

            $this->redirect('/login');

            

        } catch (Throwable $e) {

            $pdo->rollBack();

            error_log("Erro redefinirSenha: " . $e->getMessage());

            $this->setFlashMessage('Erro interno. Tente novamente.', 'error');

            $this->redirect('/senha/redefinir/' . $token);

        }

    }



    // ─── MÉTODOS PRIVADOS DE NEGÓCIO ─────────────────────────────



    private function criarUsuario(PDO $pdo, array $dados, string $tipo): int

    {
        require_once __DIR__ . '/../Services/MarketingAttributionService.php';
        $atribuicao = MarketingAttributionService::current();
        $stmt = $pdo->prepare(

            "INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, ativo, criado_em,
             utm_source_cadastro, utm_medium_cadastro, utm_campaign_cadastro)

             VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?)"

        );

        

        $stmt->execute([

            $dados['nome'],

            $dados['email'],

            password_hash($dados['senha'], PASSWORD_BCRYPT),

            $dados['telefone'],

            $dados['cpf'],

            $tipo,
            $atribuicao['utm_source'] ?? null,
            $atribuicao['utm_medium'] ?? null,
            $atribuicao['utm_campaign'] ?? null,

        ]);

        

        return (int)$pdo->lastInsertId();

    }



    private function criarEndereco(PDO $pdo, int $usuarioId, array $dados, bool $principal): int

    {

        $stmt = $pdo->prepare(

            "INSERT INTO enderecos (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, principal)

             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"

        );

        

        $stmt->execute([

            $usuarioId,

            $dados['cep'],

            $dados['logradouro'],

            $dados['numero'],

            $dados['complemento'] ?? null,

            $dados['bairro'],

            $dados['cidade'],

            $dados['estado'],

            $principal ? 1 : 0

        ]);

        

        return (int)$pdo->lastInsertId();

    }



    private function criarGuincho(PDO $pdo, array $dados): ?int

    {

        try {

            $stmt = $pdo->prepare(

                "INSERT INTO guinchos (

                    usuario_id, cidade_id, cnh_numero, cnh_validade, placa_guincho,

                    cidade_placa, uf_placa, capacidade_ton, raio_cobertura_km, chave_pix, chave_pix_tipo,

                    lat_operacao, lng_operacao, foto_veiculo, doc_cnh_frente, doc_cnh_verso,

                    marca_caminhao, modelo_caminhao, vehicle_brand_id, vehicle_model_id,
                    tipo_guincho, categoria_cnh, ear, ano_fabricacao, equipamentos_json,

                    aprovado, disponivel, lat_atual, lng_atual, criado_em

                ) VALUES (

                    :usuario_id, :cidade_id, :cnh_numero, :cnh_validade, :placa_guincho,

                    :cidade_placa, :uf_placa, :capacidade_ton, :raio_cobertura_km, :chave_pix, :chave_pix_tipo,

                    :lat_operacao, :lng_operacao, :foto_veiculo, :doc_cnh_frente, :doc_cnh_verso,

                    :marca_caminhao, :modelo_caminhao, :vehicle_brand_id, :vehicle_model_id,
                    :tipo_guincho, :categoria_cnh, :ear, :ano_fabricacao, :equipamentos_json,

                    :aprovado, :disponivel, :lat_atual, :lng_atual, NOW()

                )"

            );



            $stmt->execute([

                ':usuario_id'       => $dados['usuario_id'],

                ':cidade_id'        => $dados['cidade_id'],

                ':cnh_numero'       => $dados['cnh_numero'],

                ':cnh_validade'     => $dados['cnh_validade'],

                ':placa_guincho'    => $dados['placa_guincho'],

                ':cidade_placa'     => $dados['cidade_placa'],

                ':uf_placa'         => $dados['uf_placa'],

                ':capacidade_ton'   => $dados['capacidade_ton'],

                ':raio_cobertura_km'=> $dados['raio_cobertura_km'],

                ':chave_pix'        => $dados['chave_pix'],

                ':chave_pix_tipo'   => $dados['chave_pix_tipo'],

                ':lat_operacao'     => $dados['lat_operacao'],

                ':lng_operacao'     => $dados['lng_operacao'],

                ':foto_veiculo'     => $dados['foto_veiculo'],

                ':doc_cnh_frente'   => $dados['doc_cnh_frente'],

                ':doc_cnh_verso'    => $dados['doc_cnh_verso'],

                ':marca_caminhao'   => $dados['marca_caminhao'] ?? null,

                ':modelo_caminhao'  => $dados['modelo_caminhao'] ?? null,

                ':vehicle_brand_id' => !empty($dados['vehicle_brand_id']) ? (int)$dados['vehicle_brand_id'] : null,

                ':vehicle_model_id' => !empty($dados['vehicle_model_id']) ? (int)$dados['vehicle_model_id'] : null,

                ':tipo_guincho' => $dados['tipo_guincho'] ?? null,

                ':categoria_cnh' => $dados['categoria_cnh'] ?? null,

                ':ear' => $dados['ear'] ?? null,

                ':ano_fabricacao' => $dados['ano_fabricacao'] ?? null,

                ':equipamentos_json' => $dados['equipamentos_json'] ?? null,

                ':aprovado'         => $dados['aprovado'],

                ':disponivel'       => $dados['disponivel'],

                ':lat_atual'        => $dados['lat_atual'],

                ':lng_atual'        => $dados['lng_atual']

            ]);



            return (int)$pdo->lastInsertId();

            

        } catch (PDOException $e) {

            error_log("Erro PDO ao criar guincho: [" . $e->getCode() . "] " . $e->getMessage());

            throw new Exception('Falha ao salvar dados do guincho no banco: ' . $e->getMessage(), 0, $e);

        }

    }



    private function enviarEmail(string $para, string $assunto, string $corpo): bool

    {

        // ATENÇÃO: mail() nativo raramente funciona em hospedagens modernas sem SMTP configurado.

        // Para produção, substitua por PHPMailer ou SMTP via config.php (defina SMTP_HOST, SMTP_USER, etc.)

        // Exemplo: composer require phpmailer/phpmailer

        $headers = [

            'MIME-Version: 1.0',

            'Content-type: text/html; charset=utf-8',

            'From: GuinchaFácil <noreply@guinchafacil.com.br>',

            'Reply-To: suporte@guinchafacil.com.br',

            'X-Mailer: PHP/' . phpversion()

        ];

        

        return NotificacaoService::enviarEmail($para, $assunto, $corpo);
    }



    // ─── MÉTODOS DE SANITIZAÇÃO ──────────────────────────────────



    private function sanitizarDadosCliente(array $post): array

    {

        return [

            'nome' => trim($post['nome'] ?? ''),

            'email' => strtolower(trim($post['email'] ?? '')),

            'telefone' => preg_replace('/\D/', '', $post['telefone'] ?? ''),

            'cpf' => preg_replace('/\D/', '', $post['cpf'] ?? ''),

            'senha' => $post['senha'] ?? '',

            'confirmar_senha' => $post['confirmar_senha'] ?? '',

            'cep' => preg_replace('/\D/', '', $post['cep'] ?? ''),

            'logradouro' => trim($post['logradouro'] ?? ''),

            'numero' => trim($post['numero'] ?? ''),

            'complemento' => trim($post['complemento'] ?? ''),

            'bairro' => trim($post['bairro'] ?? ''),

            'cidade' => trim($post['cidade'] ?? ''),

            'estado' => strtoupper(trim($post['estado'] ?? ''))

        ];

    }



    private function sanitizarDadosGuincho(array $post): array

    {

        $dados = $this->sanitizarDadosCliente($post);

        

        $dados['cnh_numero'] = preg_replace('/\D/', '', $post['cnh_numero'] ?? '');

        // Vazio vira null (especialista sem reboque não tem CNH; coluna agora nullable).
        $dados['cnh_validade'] = trim($post['cnh_validade'] ?? '') ?: date('Y-m-d', strtotime('+5 years'));

        $dados['placa_guincho'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $post['placa_guincho'] ?? ''));
        $dados['cidade_placa'] = trim((string)($post['cidade_placa'] ?? ''));
        $dados['uf_placa'] = strtoupper(trim((string)($post['uf_placa'] ?? '')));
        // §CATALOGO-VISUAL-01 (02/08/2026): marca/modelo do caminhão, mesmo
        // padrão de autocomplete do veículo do cliente — texto livre com
        // vínculo opcional ao catálogo (vehicle_brand_id/vehicle_model_id).
        $dados['marca_caminhao'] = trim((string)($post['marca_caminhao'] ?? '')) ?: null;
        $dados['modelo_caminhao'] = trim((string)($post['modelo_caminhao'] ?? '')) ?: null;
        $dados['vehicle_brand_id'] = !empty($post['vehicle_brand_id']) ? (int)$post['vehicle_brand_id'] : null;
        $dados['vehicle_model_id'] = !empty($post['vehicle_model_id']) ? (int)$post['vehicle_model_id'] : null;
        $tiposGuincho = ['plataforma', 'asa_delta', 'deck', 'lanca', 'outro'];
        $dados['tipo_guincho'] = in_array((string)($post['tipo_guincho'] ?? ''), $tiposGuincho, true)
            ? (string)$post['tipo_guincho'] : null;
        $categoriasCnh = ['B', 'C', 'D', 'E'];
        $dados['categoria_cnh'] = in_array((string)($post['categoria_cnh'] ?? ''), $categoriasCnh, true)
            ? (string)$post['categoria_cnh'] : null;
        $dados['ear'] = ($post['ear'] ?? '') === '1' ? 1 : (($post['ear'] ?? '') === '0' ? 0 : null);
        $anoFabricacao = (int)($post['ano_fabricacao'] ?? 0);
        $dados['ano_fabricacao'] = ($anoFabricacao >= 1950 && $anoFabricacao <= ((int)date('Y') + 1)) ? $anoFabricacao : null;
        $equipamentosValidos = ['plataforma', 'asa_delta', 'deck', 'lanca', 'bateria', 'pneu', 'sinalizacao'];
        $equipamentos = array_values(array_intersect($equipamentosValidos, (array)($post['equipamentos'] ?? [])));
        $dados['equipamentos_json'] = $equipamentos ? json_encode($equipamentos, JSON_UNESCAPED_UNICODE) : null;
        // Cidade-alvo de atuação (tabela `cidades`) — obrigatória pra todo
        // prestador, diferente de `cidade_placa` (só o registro da placa do
        // veículo). O cliente nunca tem esse vínculo.
        $dados['cidade_id'] = (int)($post['cidade_id'] ?? 0);

        $dados['capacidade_ton'] = (float)($post['capacidade_ton'] ?? 0);

        $dados['raio_cobertura_km'] = (int)($post['raio_cobertura_km'] ?? 20);

        $dados['chave_pix'] = trim($post['chave_pix'] ?? '');

        $dados['chave_pix_tipo'] = $this->validarTipoPix($post['chave_pix_tipo'] ?? '');
        $dados['lat_operacao'] = isset($post['lat_operacao']) ? (float)$post['lat_operacao'] : null;
        $dados['lng_operacao'] = isset($post['lng_operacao']) ? (float)$post['lng_operacao'] : null;

        // Tipo de prestador: o que ele oferece. 'reboque' entre os serviços
        // marca oferece_reboque (exige placa/CNH/capacidade e aprovação de docs).
        $servicosValidos = ['reboque', 'chaveiro', 'eletrica', 'pneu', 'mecanica', 'combustivel'];
        $servicos = array_values(array_intersect($servicosValidos, (array)($post['servicos'] ?? [])));
        // Compat: cadastro antigo (sem seleção) = guincho tradicional.
        if (empty($servicos)) {
            $servicos = ['reboque'];
        }
        $dados['servicos'] = $servicos;
        $dados['oferece_reboque'] = in_array('reboque', $servicos, true) ? 1 : 0;

        return $dados;

    }



    private function validarTipoPix(string $tipo): string

    {

        $tiposPermitidos = ['cpf', 'email', 'telefone', 'aleatoria'];

        return in_array($tipo, $tiposPermitidos, true) ? $tipo : 'cpf';

    }



    // ─── MÉTODOS DE VALIDAÇÃO ────────────────────────────────────



    private function validarDadosCliente(array $dados): array

    {

        $erros = [];



        if (mb_strlen($dados['nome']) < 3) {

            $erros[] = 'Nome deve ter pelo menos 3 caracteres.';

        }



        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {

            $erros[] = 'Email inválido.';

        }



        if (strlen($dados['telefone']) < 10 || strlen($dados['telefone']) > 11) {

            $erros[] = 'Telefone inválido (DDD + número).';

        }



        if (!$this->validarCPF($dados['cpf'])) {

            $erros[] = 'CPF inválido.';

        }



        if (strlen($dados['senha']) < 8) {
            $erros[] = 'Senha deve ter pelo menos 8 caracteres.';
        }



        if ($dados['senha'] !== $dados['confirmar_senha']) {

            $erros[] = 'As senhas não conferem.';

        }



        if (strlen($dados['cep']) !== 8) {

            $erros[] = 'CEP inválido (deve conter 8 dígitos).';

        }



        if (empty($dados['logradouro']) || empty($dados['numero']) || 

            empty($dados['bairro']) || empty($dados['cidade']) || empty($dados['estado'])) {

            $erros[] = 'Preencha todos os campos de endereço.';

        }



        if (strlen($dados['estado']) !== 2) {

            $erros[] = 'UF deve ter 2 caracteres.';

        }



        return $erros;

    }



    private function validarDadosGuincho(array $dados, array $files): array

    {

        $erros = $this->validarDadosCliente($dados);

        // Cidade-alvo de atuação é obrigatória pra todo prestador (guincho
        // ou especialista) — precisa ser uma cidade cadastrada e ativa em
        // /admin/cidades. Cliente não tem essa exigência.
        require_once __DIR__ . '/../Models/Cidade.php';
        $cidadeId = (int)($dados['cidade_id'] ?? 0);
        if ($cidadeId <= 0 || !Cidade::buscarPorId($cidadeId) || empty(Cidade::buscarPorId($cidadeId)['ativo'])) {
            $erros[] = 'Selecione uma cidade-alvo de atuação válida.';
        }

        // §CELULAS-NITEROI-01 (05/08/2026): bloqueio de cadastro de
        // guincho/especialista fora de uma célula territorial HABILITADA —
        // reaproveita o mesmo point-in-polygon já usado pra resolver preço
        // (ZonePricingService::resolverZonaPorCoordenada). Só entra em ação
        // quando (a) o formulário enviou a coordenada de base
        // (lat_operacao/lng_operacao) e (b) já existe pelo menos 1 célula
        // com polígono desenhado em algum lugar do sistema — comportamento
        // aditivo: enquanto nenhuma célula tiver polígono, o cadastro
        // continua exatamente como sempre foi (só exige cidade-alvo ativa),
        // mesma regra já documentada em ZonePricingService.
        //
        // Semântica de status_expansao pra CADASTRO (definida pelo usuário
        // em 05/08/2026, corrige uma leitura errada minha anterior):
        //   nao_ativada -> BLOQUEIA cadastro (região ainda fechada).
        //   pedra_morta -> PERMITE cadastro (só não fica automaticamente
        //                  "ativa"/operacional — isso é aprovação normal do
        //                  admin, já governada por guinchos.aprovado, sem
        //                  relação nenhuma com este gate de região).
        //   pedra_viva  -> PERMITE cadastro (mesma regra de pedra_morta
        //                  aqui; a diferença entre as duas é só o sinal
        //                  visual/estratégico da célula, não a validação).
        $latOperacao = $dados['lat_operacao'] ?? null;
        $lngOperacao = $dados['lng_operacao'] ?? null;
        if ($latOperacao !== null && $lngOperacao !== null) {
            require_once __DIR__ . '/../Models/Pricing/PricingZone.php';
            require_once __DIR__ . '/../Services/Pricing/ZonePricingService.php';
            $existeCelulaComPoligono = (bool)array_filter(
                PricingZone::listarAtivas(),
                static fn(array $z): bool => !empty($z['polygon_geojson'])
            );
            if ($existeCelulaComPoligono) {
                $zonaResolvida = ZonePricingService::resolverZonaPorCoordenada((float)$latOperacao, (float)$lngOperacao);
                if ($zonaResolvida === null) {
                    $erros[] = 'Sua localização de base fica fora de todas as regiões mapeadas pelo GuinchaFácil até o momento — ainda não é possível cadastrar prestadores fora dessas áreas.';
                } elseif (($zonaResolvida['status_expansao'] ?? 'nao_ativada') === 'nao_ativada') {
                    $erros[] = 'A região "' . $zonaResolvida['name'] . '" ainda não está ativada para cadastro de novos parceiros no momento.';
                }
            }
        }

        // Especialista (não oferece reboque): não exige placa/CNH/capacidade.
        // Ele envia os documentos de guincho depois, pelo painel, se quiser
        // virar guincho também.
        if (empty($dados['oferece_reboque'])) {
            return $erros;
        }

        if (false && empty($dados['cnh_numero'])) {

            $erros[] = 'Número da CNH é obrigatório.';

        } elseif (false && strlen($dados['cnh_numero']) < 9) {

            $erros[] = 'Número da CNH inválido.';

        }



        if (false && empty($dados['cnh_validade'])) {

            $erros[] = 'Data de validade da CNH é obrigatória.';

        } else {

            $validade = DateTime::createFromFormat('Y-m-d', $dados['cnh_validade']);

            $hoje = new DateTime();

            $hoje->setTime(0, 0, 0);

            

            if (!$validade) {

                $erros[] = 'Formato de data da CNH inválido. Use AAAA-MM-DD.';

            } elseif ($validade <= $hoje) {

                $erros[] = 'CNH deve ter validade futura.';

            }

        }



        if ($dados['placa_guincho'] !== '' && strlen($dados['placa_guincho']) < 7) {

            $erros[] = 'Placa do guincho inválida (mínimo 7 caracteres).';

        } elseif ($dados['placa_guincho'] !== '' && !preg_match('/^[A-Z0-9]{7}$/', $dados['placa_guincho'])) {

            $erros[] = 'Placa deve conter 7 caracteres alfanuméricos.';

        }

        if ($dados['cidade_placa'] !== '' && mb_strlen($dados['cidade_placa']) < 2) {

            $erros[] = 'Cidade do emplacamento inválida.';

        }

        if ($dados['uf_placa'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf_placa'])) {

            $erros[] = 'UF da placa inválida.';

        }



        if ($dados['capacidade_ton'] < 0) {

            $erros[] = 'Capacidade em toneladas deve ser maior que zero.';

        } elseif ($dados['capacidade_ton'] > 100) {

            $erros[] = 'Capacidade máxima é 100 toneladas.';

        }



        if (false && $dados['chave_pix'] !== '') {

            $erros[] = 'Chave PIX é obrigatória.';

        } elseif ($dados['chave_pix'] !== '' && !$this->validarChavePix($dados['chave_pix'], $dados['chave_pix_tipo'])) {

            $erros[] = 'Chave PIX inválida para o tipo selecionado.';

        }



        // Validações de arquivos

        $camposObrigatorios = ['doc_cnh_frente', 'doc_cnh_verso'];

        foreach ($camposObrigatorios as $campo) {

            if (false && (!isset($files[$campo]) || $files[$campo]['error'] === UPLOAD_ERR_NO_FILE)) {

                $erros[] = 'Documento da CNH (frente e verso) é obrigatório.';

                break;

            }

        }



        return $erros;

    }



    private function validarChavePix(string $chave, string $tipo): bool

    {

        return match($tipo) {

            'cpf' => $this->validarCPF(preg_replace('/\D/', '', $chave)),

            'email' => filter_var($chave, FILTER_VALIDATE_EMAIL) !== false,

            'telefone' => strlen(preg_replace('/\D/', '', $chave)) >= 10,

            'aleatoria' => strlen($chave) >= 8 && strlen($chave) <= 32,

            default => false

        };

    }



    private function validarCPF(string $cpf): bool

    {

        // Remove caracteres não numéricos

        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        

        if (strlen($cpf) !== 11) {

            return false;

        }



        // Verifica se todos os dígitos são iguais

        if (preg_match('/(\d)\1{10}/', $cpf)) {

            return false;

        }



        // Validação dos dígitos verificadores

        for ($t = 9; $t < 11; $t++) {

            $d = 0;

            for ($c = 0; $c < $t; $c++) {

                $d += $cpf[$c] * (($t + 1) - $c);

            }

            $d = ((10 * $d) % 11) % 10;

            if ($cpf[$c] != $d) {

                return false;

            }

        }

        

        return true;

    }



    // ─── MÉTODOS DE UPLOAD ───────────────────────────────────────



    private function processarUploads(array $files): array

    {

        $uploads = [];

        $campos = ['foto_veiculo', 'doc_cnh_frente', 'doc_cnh_verso'];



        foreach ($campos as $campo) {

            if (isset($files[$campo]) && $files[$campo]['error'] === UPLOAD_ERR_OK) {

                try {

                    $uploads[$campo] = $this->processarUpload($campo);

                } catch (Exception $e) {

                    $this->limparUploads($uploads);

                    throw $e;

                }

            }

        }



        return $uploads;

    }



    private function processarUpload(string $campo): string

    {

        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {

            throw new Exception("Arquivo não enviado corretamente");

        }



        $file = $_FILES[$campo];



        // Valida tamanho

        if ($file['size'] > self::UPLOAD_MAX_SIZE) {

            throw new Exception("Arquivo muito grande (máximo 5MB)");

        }



        // Valida tipo MIME real

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mimeType = finfo_file($finfo, $file['tmp_name']);

        finfo_close($finfo);



        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        

        if (!isset(self::ALLOWED_MIME_TYPES[$ext])) {

            throw new Exception("Extensão de arquivo não permitida");

        }

        

        if (self::ALLOWED_MIME_TYPES[$ext] !== $mimeType) {

            throw new Exception("Tipo de arquivo inválido");

        }



        // Gera nome seguro

        $destDir = $this->getUploadDir();

        $fileName = sprintf(

            '%s_%s_%s.%s',

            $campo,

            date('YmdHis'),

            bin2hex(random_bytes(4)),

            $ext

        );

        

        $destPath = $destDir . '/' . $fileName;



        if (!move_uploaded_file($file['tmp_name'], $destPath)) {

            throw new Exception("Falha ao salvar arquivo");

        }



        // Define permissões seguras

        chmod($destPath, 0644);



        return $fileName;

    }



    private function getUploadDir(): string

    {

        // §SEC-UPL-02: doc_cnh_frente/doc_cnh_verso/foto_veiculo são
        // documento de identidade — salvos fora do webroot (UPLOAD_PATH_DOCS),
        // só acessíveis via ArquivoController::servir() autenticado.
        $destDir = defined('UPLOAD_PATH_DOCS') ? UPLOAD_PATH_DOCS : (defined('UPLOAD_PATH') ? UPLOAD_PATH : (dirname(__DIR__, 3) . '/uploads'));

        

        if (!is_dir($destDir)) {

            if (!mkdir($destDir, 0755, true)) {

                throw new Exception("Não foi possível criar diretório de uploads");

            }

        }

        

        if (!is_writable($destDir)) {

            throw new Exception("Diretório de uploads sem permissão de escrita");

        }

        

        return $destDir;

    }



    private function limparUploads(array $uploads): void

    {

        $destDir = $this->getUploadDir();

        

        foreach ($uploads as $arquivo) {

            if ($arquivo) {

                $caminho = $destDir . '/' . $arquivo;

                if (file_exists($caminho)) {

                    @unlink($caminho);

                }

            }

        }

    }



    // ─── HELPERS ─────────────────────────────────────────────────

    

    private function redirectByProfile(?string $tipo = null): void

    {

        $tipo = $tipo ?? ($_SESSION['user']['tipo'] ?? null);

        

        $dest = match($tipo) {

            'admin'       => '/admin/dashboard',

            'guincho'     => '/guincho/dashboard',

            'cliente'     => '/cliente/dashboard',

            'funcionario' => '/funcionario/dashboard',

            'gerente'     => '/gerente/dashboard',

            'especialista'=> '/especialista/dashboard',

            // Nunca aponte o default de volta pra '/login': se o usuário já
            // está autenticado (isAuthenticated()==true) e cai aqui, o
            // loginForm() redireciona de novo pra cá — loop infinito
            // (ERR_TOO_MANY_REDIRECTS). Isso pegou tipos novos (funcionario/
            // gerente) que ainda não existiam neste match. Melhor destino
            // seguro pra um tipo desconhecido é logout, não login.
            default       => '/logout',

        };

        

        $this->redirect($dest);

    }



    protected function isAuthenticated(): bool

    {

        return isset($_SESSION['user']) && isset($_SESSION['auth_at']) && 

               (time() - $_SESSION['auth_at']) < 3600; // 1 hora de sessão

    }



    protected function generateCSRFToken(): string

    {

        if (empty($_SESSION['_csrf_token'])) {

            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        }

        return $_SESSION['_csrf_token'];

    }



    protected function validateCSRFToken(string $token): bool

    {

        if (empty($_SESSION['_csrf_token']) || empty($token)) {

            return false;

        }

        return hash_equals($_SESSION['_csrf_token'], $token);

    }



    protected function setFlashMessage(string $message, string $type = 'info'): void

    {

        if (!isset($_SESSION['_flash'])) {

            $_SESSION['_flash'] = [];

        }

        $_SESSION['_flash'][] = [

            'message' => $message,

            'type' => $type,

            'time' => time()

        ];

    }

    /**
     * L1.10 (achado durante a triagem do Playwright): loginForm(),
     * registroClienteForm(), registroGuinchoForm() e as duas telas de
     * senha (esqueceu/redefinir) faziam `require` direto na view sem
     * nunca ler `$_SESSION['_flash']` de volta — ou seja, toda vez que
     * o POST rejeitava (senha errada, CPF/e-mail duplicado, CSRF etc.)
     * e redirecionava com setFlashMessage(), a mensagem ficava presa na
     * sessão e a página só mostrava o formulário em branco, sem
     * nenhuma explicação — silenciando erros reais tanto pra usuários
     * quanto pros testes E2E. As views esperam `$flash['type']` /
     * `$flash['message']` (item único), então este helper extrai a
     * última mensagem da lista armazenada por setFlashMessage() acima
     * e a consome (unset), em vez de usar BaseController::render()
     * (que popula `$flash` num formato incompatível e além disso
     * embrulharia essas páginas — que são HTML completo e independente,
     * sem header/footer — num layout que elas não usam).
     */
    private function pullFlash(): ?array
    {
        if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            return null;
        }
        $flash = array_pop($_SESSION['_flash']);
        if (empty($_SESSION['_flash'])) {
            unset($_SESSION['_flash']);
        }
        return $flash;
    }



    protected function redirect(string $url): void

    {
        if (!preg_match('#^https?://#i', $url)) {
            if ($url === '' || $url[0] !== '/') {
                $url = '/' . $url;
            }
            $basePath = defined('BASE_PATH') ? (string)BASE_PATH : '';
            $url = $basePath . $url;
        }
        header('Location: ' . $url);

        exit;

    }

}
