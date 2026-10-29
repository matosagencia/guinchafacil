<?php

require_once __DIR__ . '/../Services/NotificacaoService.php';
require_once __DIR__ . '/../Services/EnderecoFormatter.php';
require_once __DIR__ . '/../Models/Pricing/PricingZone.php';
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
        $cidadesSeo = $this->cidadesComCoberturaSeo();
        require __DIR__ . '/../Views/public/landing.php';
    }

    /** Cidades públicas que já possuem pelo menos uma zona pedra_viva. */
    private function cidadesComCoberturaSeo(): array
    {
        $resultado = [];
        foreach (Cidade::listarAtivas() as $cidade) {
            foreach (PricingZone::listarPorOrdemExpansao((int)$cidade['id'], true) as $zona) {
                if (($zona['status_expansao'] ?? '') === 'pedra_viva' && !empty($cidade['slug'])) {
                    $resultado[] = $cidade;
                    break;
                }
            }
        }
        return $resultado;
    }

    public function cidadesPublicas(): void
    {
        $cidadesSeo = $this->cidadesComCoberturaSeo();
        require __DIR__ . '/../Views/public/cidades.php';
    }

    /**
     * POST /parceiros/interesse
     * Recebe o formulario das paginas publicas de parceiros
     * (/parceiros/guinchos e /parceiros/oficinas).
     *
     * Barreira de entrada simples: nome + telefone + tipo_parceiro.
     * Persiste em prospeccao_leads (regiao_id pode ser NULL).
     */
    public function parceirosInteresse(): void
    {
        // 1. Tipo (define o redirect de volta)
        $tipo = (string)($_POST['tipo_parceiro'] ?? '');
        if ($tipo !== 'guincho' && $tipo !== 'oficina') {
            $tipo = 'oficina';
        }
        $redirectBase = '/parceiros/' . ($tipo === 'guincho' ? 'guinchos' : 'oficinas');

        // 2. CSRF
        if (!$this->validateCSRFToken((string)($_POST['csrf_token'] ?? ''))) {
            $this->redirect($redirectBase . '?erro=sessao');
            return;
        }

        // 3. Rate limit (por IP + tipo)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!AuthService::verificarRateLimit((string)$ip, 'parceiros_' . $tipo)) {
            $this->redirect($redirectBase . '?erro=limite');
            return;
        }

        // 4. Persiste via service
        try {
            require_once __DIR__ . '/../Services/Prospeccao/SerpApiMapsClient.php';
            require_once __DIR__ . '/../Services/Prospeccao/RegiaoQuotaService.php';
            require_once __DIR__ . '/../Services/Prospeccao/MensagemPersuasaoService.php';
            require_once __DIR__ . '/../Services/Prospeccao/ProspeccaoParceirosService.php';

            $pdo = getPDO();
            $service = new ProspeccaoParceirosService(
                $pdo,
                new SerpApiMapsClient((string)env('SERPAPI_KEY', '')),
                new RegiaoQuotaService($pdo),
                new MensagemPersuasaoService($redirectBase)
            );

            $service->registrarLeadEntrante([
                'nome_negocio'  => $_POST['nome_oficina'] ?? '',
                'telefone'      => $_POST['telefone'] ?? '',
                'cnpj'          => $_POST['cnpj'] ?? '',
                'tipo_parceiro' => $tipo,
                'cidade'        => $_POST['cidade'] ?? '',
                'uf'            => $_POST['uf'] ?? '',
            ]);

            $this->redirect($redirectBase . '?enviado=1');
        } catch (Throwable $e) {
            error_log('[parceiros_interesse] ' . $e->getMessage());
            $this->redirect($redirectBase . '?erro=invalid');
        }
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

        require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
        require_once __DIR__ . '/../Models/Guincho.php';
        $servicosCatalogo = ServiceType::listarAtivos();
        $zonasVivas = array_values(array_filter($zonas, static fn(array $zona): bool => ($zona['status_expansao'] ?? '') === 'pedra_viva'));
        $guinchosAtivosCidade = Guincho::disponibilidadePorZonasCache(
            array_map(static fn(array $zona): int => (int)$zona['id'], $zonasVivas)
        );

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

        foreach ($this->cidadesComCoberturaSeo() as $cidade) {
            $urls[] = ['loc' => 'https://guinchafacil.com.br/guincho/' . rawurlencode((string)$cidade['slug']), 'freq' => 'weekly', 'priority' => '0.8'];
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

        $prefillLat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $prefillLng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        $prefillLat = ($prefillLat !== false && $prefillLat >= -34 && $prefillLat <= 5) ? (float)$prefillLat : null;
        $prefillLng = ($prefillLng !== false && $prefillLng >= -74 && $prefillLng <= -28) ? (float)$prefillLng : null;
        $prefillOrigemSeo = strtolower(trim((string)($_GET['origem_seo'] ?? '')));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $prefillOrigemSeo)) $prefillOrigemSeo = null;
        if ($prefillLat === null || $prefillLng === null) {
            $prefillLat = null;
            $prefillLng = null;
        }
        $cidadesSeo = $this->cidadesComCoberturaSeo();
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

        $numeroOrigem = trim((string)($_POST['numero_origem'] ?? ''));
        $localizacao = EnderecoFormatter::comNumeroNoTexto(
            (string)($_POST['localizacao'] ?? ''),
            $numeroOrigem !== '' ? $numeroOrigem : null
        );
        $latDestino = filter_var($_POST['lat_destino'] ?? null, FILTER_VALIDATE_FLOAT);
        $lngDestino = filter_var($_POST['lng_destino'] ?? null, FILTER_VALIDATE_FLOAT);
        $numeroDestino = trim((string)($_POST['numero_destino'] ?? ''));
        $destino = EnderecoFormatter::comNumeroNoTexto(
            (string)($_POST['destino'] ?? ''),
            $numeroDestino !== '' ? $numeroDestino : null
        );
        $tipo = trim((string)($_POST['tipo_problema'] ?? ''));
        $decisaoAtendimento = strtolower(trim((string)($_POST['decisao_atendimento'] ?? '')));
        if ($decisaoAtendimento === 'reboque') {
            $tipo = 'reboque';
        }
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

        // [B-011-B2] §23 §1 — resolução dinâmica via service_types.public_slug.
        // Nada hardcoded: a tabela é a única fonte de verdade. Fail-closed se o
        // slug não existir ou o serviço estiver inativo.
        require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
        $serviceType = ServiceType::buscarPorSlugPublico($tipo);
        if (!$serviceType) {
            error_log('[B-011-B2][PRICE-SERVICE-INVALID] tipo_problema=' . $tipo);
            $this->setFlashMessage('Tipo de atendimento não reconhecido. Escolha novamente.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }
        $serviceTypeId = (int)$serviceType['id'];
        $requiresDestination = ServiceType::requiresDestination($serviceType);
        $attendanceMode = (string)($serviceType['attendance_mode'] ?? '');

        if (($latDestino === false || $lngDestino === false) && $destino !== '') {
            require_once __DIR__ . '/../Services/GeocodingService.php';
            $destinoGeo = (new GeocodingService())->geocode($destino);
            if ($destinoGeo) {
                $latDestino = filter_var($destinoGeo['lat'] ?? null, FILTER_VALIDATE_FLOAT);
                $lngDestino = filter_var($destinoGeo['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            }
        }

        if ($requiresDestination && ($latDestino === false || $lngDestino === false || $latDestino < -34 || $latDestino > 5 || $lngDestino < -74 || $lngDestino > -28)) {
            $this->setFlashMessage('Para este atendimento, informe o endereco de destino do veiculo.', 'error');
            $this->redirect('/pre-cotacao');
            return;
        }

        // O serviço da A será a única autoridade de rota/zona/preço.
        if (!in_array($categoria, $categorias, true)) $categoria = 'popular';

        require_once __DIR__ . '/../Services/CoberturaService.php';
        require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
        $diagnosticoCobertura = $serviceTypeId > 0 ? CoberturaService::diagnosticarAtendimento([
            'attendance_mode' => $attendanceMode,
            'lat_origem' => (float)$lat,
            'lng_origem' => (float)$lng,
            'service_type_id' => $serviceTypeId,
            'tipo_problema' => $tipo,
            'categoria' => $categoria,
        ]) : ['status' => 'sem_servico', 'pode_cobrar' => false, 'mensagem' => 'Não conseguimos identificar o tipo de atendimento para validar a cobertura.'];
        $_SESSION['pre_cotacao_sem_cobertura'] = null;
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
            $_SESSION['pre_cotacao_sem_cobertura'] = [
                'status' => (string)($diagnosticoCobertura['status'] ?? 'sem_cobertura'),
                'mensagem' => (string)($diagnosticoCobertura['mensagem'] ?? ''),
                'timestamp' => time(),
            ];
        }

        // [B-011-B1] O valor_cotado do navegador NUNCA determina o preço oficial (§23).
        // Aceito apenas para diagnóstico; se presente, gera log.
        $valorCotadoFrontDiag = filter_var($_POST['valor_cotado'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($valorCotadoFrontDiag !== false && $valorCotadoFrontDiag > 0) {
            error_log(sprintf(
                '[B-011-B1][PRICE-FRONT-DIAG] valor_cotado=%.2f lat=%.4f lng=%.4f tipo=%s',
                $valorCotadoFrontDiag, (float)$lat, (float)$lng, (string)$tipo
            ));
        }

        // [B-011] A Faixa B não calcula nem reinterpreta o preço.
        require_once __DIR__ . '/../Services/Pricing/CotacaoUnificadaService.php';
        $resultado = CotacaoUnificadaService::calcular([
            'lat_origem' => (float)$lat,
            'lng_origem' => (float)$lng,
            'lat_destino' => $latDestino !== false ? (float)$latDestino : null,
            'lng_destino' => $lngDestino !== false ? (float)$lngDestino : null,
            'public_slug' => $tipo,
            'vehicle_category' => $categoria,
        ]);
        if (!($resultado['ok'] ?? false)) {
            error_log('[B011][PRICE-FAIL] ' . (string)($resultado['erro'] ?? 'PRICE-INTERNAL'));
            $this->setFlashMessage('Cotação indisponível: ' . (string)($resultado['erro'] ?? 'PRICE-INTERNAL'), 'error');
            $this->redirect('/pre-cotacao');
            return;
        }
        $distancia = (float)$resultado['km_transporte'];
        $fonteKm = (string)$resultado['fonte_km'];
        $zonaId = $resultado['pricing_zone_id'];
        $detalhe = [
            'valor' => (float)$resultado['preco_cliente'],
            'detalhamento' => (array)$resultado['detalhamento'],
            'fonte_km' => $fonteKm,
            'zona_id' => $zonaId,
        ];
        $origemTarifa = 'precificacao_oficial';
        $regraId = (int)$resultado['regra_id'];
        $regraVersion = (string)$resultado['regra_version'];

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
            'service_code' => $resultado['service_code'],
            'requires_destination' => $requiresDestination,
            'endereco_origem'  => substr($localizacao, 0, 500),
            'endereco_destino' => substr($destino, 0, 500),
            'destino' => substr($destino, 0, 220),
            'distancia_km' => $distancia,
            'valor'          => (float)$detalhe['valor'],
            'tarifa'         => $detalhe,
            'origem_tarifa'  => 'precificacao_oficial',
            'regra_id'       => $regraId,
            'regra_version'  => $regraVersion,
            'detalhamento'   => $detalhe['detalhamento'] ?? [],
            'fonte_km'       => $detalhe['fonte_km'] ?? null,
            'zona_id'        => $detalhe['zona_id'] ?? null,
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
        $formaPagamento = strtolower(trim((string)($_POST['forma_pagamento'] ?? 'online')));
        $_SESSION['pre_cotacao']['forma_pagamento'] = in_array($formaPagamento, ['online', 'pago_na_chegada'], true)
            ? $formaPagamento
            : 'online';

        require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
        PreQuoteDemandService::registrar($_SESSION['pre_cotacao'], 'accepted');

        // §23 B-UNIFIC-23: pré-cotação aceita → registro em 2 etapas.
        $this->redirect('/registro/funil');
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

    public function googleRedirect(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirectByProfile();
            return;
        }
        try {
            require_once __DIR__ . '/../Services/GoogleOAuthService.php';
            header('Location: ' . GoogleOAuthService::createAuthorizationUrl((string)($_GET['retorno'] ?? '/')), true, 302);
            exit;
        } catch (Throwable $e) {
            error_log('[GoogleAuth] redirect: ' . $e->getMessage());
            $this->setFlashMessage('O cadastro com Google ainda não está disponível.', 'error');
            $this->redirect('/login');
        }
    }

    public function googleCallback(): void
    {
        require_once __DIR__ . '/../Services/GoogleOAuthService.php';
        $returnPath = GoogleOAuthService::consumeState((string)($_GET['state'] ?? ''));
        if ($returnPath === null || isset($_GET['error'])) {
            $this->setFlashMessage('Não foi possível validar o acesso pelo Google. Tente novamente.', 'error');
            $this->redirect('/login');
            return;
        }
        try {
            $identity = GoogleOAuthService::fetchIdentity((string)($_GET['code'] ?? ''));
            $pdo = getPDO();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE google_subject = ? OR email = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$identity['subject'], $identity['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && (int)$user['ativo'] !== 1) throw new DomainException('Esta conta está desativada.');
            if ($user) {
                $pdo->prepare('UPDATE usuarios SET google_subject = ?, ultimo_login = NOW() WHERE id = ?')
                    ->execute([$identity['subject'], (int)$user['id']]);
                $user['google_subject'] = $identity['subject'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, google_subject, senha_hash, telefone, cpf, tipo, perfil_status, ativo, criado_em) VALUES (?, ?, ?, ?, NULL, NULL, 'cliente', 'PENDENTE', 1, NOW())");
                $stmt->execute([$identity['name'], $identity['email'], $identity['subject'], password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
                $user = ['id' => (int)$pdo->lastInsertId(), 'nome' => $identity['name'], 'email' => $identity['email'], 'tipo' => 'cliente', 'perfil_status' => 'PENDENTE', 'ativo' => 1];
            }
            if (($user['perfil_status'] ?? 'COMPLETO') === 'PENDENTE' || (($user['google_subject'] ?? '') !== '' && empty($user['cpf']))) {
                $pdo->prepare("UPDATE usuarios SET perfil_status = 'PENDENTE', ultimo_login = NOW() WHERE id = ?")->execute([(int)$user['id']]);
                $user['perfil_status'] = 'PENDENTE';
            }
            $pdo->commit();
            AuthService::initializeAuthenticatedSession($user);
            $this->redirect(($user['perfil_status'] ?? '') === 'PENDENTE' ? '/auth/google/profile' : ($returnPath ?: '/cliente/dashboard'));
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[GoogleAuth] callback: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível concluir o cadastro pelo Google.', 'error');
            $this->redirect('/login');
        }
    }

    public function googleProfileForm(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
            return;
        }
        $user = AuthService::getCurrentUser();
        if (!$user) {
            $this->redirect('/login');
            return;
        }
        $stmt = getPDO()->prepare('SELECT nome, email, perfil_status, perfil_tipo_solicitado FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$user['id']]);
        $perfil = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;
        if (($perfil['perfil_status'] ?? 'COMPLETO') === 'COMPLETO') {
            $this->redirectByProfile((string)($user['tipo'] ?? 'cliente'));
            return;
        }
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        require __DIR__ . '/../Views/auth/google_profile.php';
    }

    public function googleProfileSave(): void
    {
        if (!$this->isAuthenticated() || !$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Sua sessão expirou. Entre novamente.', 'error');
            $this->redirect('/login');
            return;
        }
        $user = AuthService::getCurrentUser();
        $role = strtoupper(trim((string)($_POST['perfil'] ?? '')));
        $allowed = ['CLIENTE', 'GUINCHO', 'OFICINA', 'PRESTADOR_MOVEL'];
        if (!$user || !in_array($role, $allowed, true)) {
            $this->setFlashMessage('Escolha uma opção válida para continuar.', 'error');
            $this->redirect('/perfil/completar');
            return;
        }
        $telefone = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? ''));
        $cpf = preg_replace('/\D+/', '', (string)($_POST['cpf'] ?? ''));
        if (strlen($telefone) < 10 || strlen($cpf) !== 11) {
            $this->setFlashMessage('Informe um telefone e CPF válidos para concluir o perfil.', 'error');
            $this->redirect('/perfil/completar');
            return;
        }
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();
            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE cpf = ? AND id <> ? LIMIT 1 FOR UPDATE');
            $dup->execute([$cpf, (int)$user['id']]);
            if ($dup->fetch()) throw new DomainException('Este CPF já está cadastrado em outra conta.');

            $status = $role === 'CLIENTE' ? 'COMPLETO' : 'PENDENTE_APROVACAO';
            $pdo->prepare('UPDATE usuarios SET telefone = ?, cpf = ?, perfil_status = ?, perfil_tipo_solicitado = ?, atualizado_em = NOW() WHERE id = ?')
                ->execute([$telefone, $cpf, $status, $role, (int)$user['id']]);
            $pdo->commit();
            if ($role === 'CLIENTE') {
                $this->setFlashMessage('Perfil concluído. Bem-vindo ao GuinchaFácil!', 'success');
                $this->redirect('/cliente/dashboard');
                return;
            }
            $this->setFlashMessage('Solicitação recebida. Nossa equipe vai revisar seu cadastro antes de liberar o painel profissional.', 'success');
            $this->redirect('/perfil/completar');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[GoogleProfile] ' . $e->getMessage());
            $this->setFlashMessage($e instanceof DomainException ? $e->getMessage() : 'Não foi possível salvar seu perfil agora.', 'error');
            $this->redirect('/perfil/completar');
        }
    }

    public function magicSolicitar(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirectByProfile();
            return;
        }
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'erro' => 'Sessao expirada.']);
            return;
        }

        $identificador = trim((string)($_POST['identificador'] ?? ''));
        $retorno = (string)($_POST['retorno'] ?? '/');

        require_once __DIR__ . '/../Services/Auth/MagicLinkService.php';
        $resultado = MagicLinkService::solicitar($identificador, $retorno);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function magicEnviado(): void
    {
        $canal = (string)($_GET['canal'] ?? '');
        $csrf_token = $this->generateCSRFToken();
        require __DIR__ . '/../Views/auth/magic_enviado.php';
    }

    public function magicConsumir(string $token): void
    {
        require_once __DIR__ . '/../Services/Auth/MagicLinkService.php';
        $user = MagicLinkService::consumir($token);
        if (!$user) {
            $this->setFlashMessage('Este link expirou ou ja foi usado. Solicite um novo.', 'error');
            $this->redirect('/login');
            return;
        }
        $retorno = AuthService::sanitizeReturnPath((string)($_GET['retorno'] ?? '/cliente/dashboard'));

        AuthService::initializeAuthenticatedSession($user);
        $this->redirect($retorno);
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

            $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);

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
            // §GF-CHECKOUT-FLOW: se existe pre-cotacao ativa na sessao, o destino
            // obrigatorio e /checkout/veiculo.
            $checkoutComTriagem = $this->checkoutPathFromActiveTriage();
            if ($checkoutComTriagem !== null) {
                $this->redirect($checkoutComTriagem);
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
        $tipo = 'cliente';
        require __DIR__ . '/../Views/auth/registro_simples.php';
    }

    public function registroCliente(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/registro/cliente');
            return;
        }

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

            $dup = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR cpf = ? FOR UPDATE");
            $dup->execute([$dados['email'], $dados['cpf']]);
            if ($dup->fetch()) {
                throw new Exception('Email ou CPF já cadastrado.');
            }

            $userId = $this->criarUsuario($pdo, $dados, 'cliente');
            require_once __DIR__ . '/../Services/MarketingAttributionService.php';
            $atribuicaoCadastro = MarketingAttributionService::current();
            $pdo->prepare('UPDATE usuarios SET utm_source_cadastro=?, utm_medium_cadastro=?, utm_campaign_cadastro=? WHERE id=?')
                ->execute([$atribuicaoCadastro['utm_source'] ?? null, $atribuicaoCadastro['utm_medium'] ?? null, $atribuicaoCadastro['utm_campaign'] ?? null, $userId]);

            $this->criarEndereco($pdo, $userId, $dados, true);

            $pdo->commit();

            $this->setFlashMessage('Cadastro realizado! Faca login para continuar.', 'success');
            $checkoutComTriagem = $this->checkoutPathFromActiveTriage();
            if ($checkoutComTriagem !== null) {
                $this->redirect('/login?retorno=' . rawurlencode($checkoutComTriagem));
                return;
            }

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

        $servicosEspecialista = $this->listarServicosEspecialistaDisponiveis();
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        require __DIR__ . '/../Views/auth/registro_especialista.php';
    }

    private function listarServicosEspecialistaDisponiveis(): array
    {
        require_once __DIR__ . '/../Models/Catalog/ServiceType.php';

        $modoLabels = [
            'TOWING' => 'Reboque',
            'ON_SITE' => 'No local',
            'HYBRID' => 'Híbrido',
        ];

        $servicos = [];
        foreach (ServiceType::listarAtivos() as $tipo) {
            $modo = (string)($tipo['attendance_mode'] ?? '');
            if ($modo === 'TOWING') {
                continue;
            }

            $servicos[] = [
                'id' => (int)$tipo['id'],
                'code' => (string)$tipo['code'],
                'name' => (string)$tipo['name'],
                'description' => (string)($tipo['description'] ?? ''),
                'category_name' => (string)($tipo['category_name'] ?? ''),
                'attendance_mode' => $modo,
                'attendance_mode_label' => $modoLabels[$modo] ?? $modo,
                'requires_destination' => !empty($tipo['requires_destination']),
                'active' => !empty($tipo['active']),
            ];
        }

        return $servicos;
    }

    public function registroEspecialista(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/registro/especialista');
            return;
        }

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
        $tipo = 'guincho';
        require __DIR__ . '/../Views/auth/registro_simples.php';
    }

    public function registroGuincho(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Token inválido.', 'error');
            $this->redirect('/registro/guincho');
            return;
        }

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

            $dup = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR cpf = ? FOR UPDATE");
            $dup->execute([$dados['email'], $dados['cpf']]);
            if ($dup->fetch()) {
                throw new Exception('Email ou CPF já cadastrado.');
            }

            if (!empty($dados['oferece_reboque']) && $dados['placa_guincho'] !== '') {
                $dupPlaca = $pdo->prepare("SELECT id FROM guinchos WHERE placa_guincho = ? FOR UPDATE");
                $dupPlaca->execute([$dados['placa_guincho']]);
                if ($dupPlaca->fetch()) {
                    throw new Exception('Placa já cadastrada no sistema.');
                }
            }

            $uploads = $this->processarUploads($_FILES);

            $userId = $this->criarUsuario($pdo, $dados, 'guincho');
            $this->criarEndereco($pdo, $userId, $dados, true);

            $guinchoData = [
                'usuario_id' => $userId,
                'cidade_id' => $dados['cidade_id'] ?? 0,
                'cnh_numero' => $dados['cnh_numero'],
                'cnh_validade' => $dados['cnh_validade'],
                'placa_guincho' => $dados['placa_guincho'],
                'cidade_placa' => $dados['cidade_placa'],
                'uf_placa' => $dados['uf_placa'],
                'capacidade_ton' => $dados['capacidade_ton'],
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
                'lat_atual' => null,
                'lng_atual' => null
            ];

            $guinchoId = $this->criarGuincho($pdo, $guinchoData);

            if (!$guinchoId) {
                throw new Exception('Falha ao criar registro do guincho');
            }
            try {
                $pdo->prepare(
                    "UPDATE guinchos SET oferece_reboque = ?, reboque_aprovado = 0 WHERE id = ?"
                )->execute([(int)($dados['oferece_reboque'] ?? 1), (int)$guinchoId]);
            } catch (\Throwable $e) {
                error_log('[registroGuincho] oferece_reboque não gravado (rode migrate?): ' . $e->getMessage());
            }

            $this->declararCapacidadesDoRegistro($pdo, (int)$guinchoId, (array)($dados['servicos'] ?? []));

            $pdo->commit();

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

    // --- WRAPPERS: URL limpa /registro/{tipo} --------------------------

    public function registroClienteSimplesForm(): void { $this->registroSimplesForm('cliente'); }
    public function registroClienteSimplesSave(): void { $this->registroSimples('cliente'); }

    public function registroGuinchoSimplesForm(): void { $this->registroSimplesForm('guincho'); }
    public function registroGuinchoSimplesSave(): void { $this->registroSimples('guincho'); }

    public function registroOficinaForm(): void { $this->registroSimplesForm('oficina'); }
    public function registroOficinaSave(): void { $this->registroSimples('oficina'); }

    public function registroSimplesForm(string $tipo): void
    {
        if ($this->isAuthenticated()) { $this->redirectByProfile(); return; }
        if (!in_array($tipo, ['cliente','guincho','oficina'], true)) {
            $this->setFlashMessage('Tipo de cadastro invalido.', 'error');
            $this->redirect('/registro/cliente');
            return;
        }
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        $retorno = AuthService::sanitizeReturnPath((string)($_GET['retorno'] ?? '/'));
        require __DIR__ . '/../Views/auth/registro_simples.php';
    }

    public function registroSimples(string $tipo): void
    {
        $tipo = in_array($tipo, ['cliente','guincho','oficina'], true) ? $tipo : 'cliente';
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Sessao expirada. Tente novamente.', 'error');
            $this->redirect('/registro/simples/' . $tipo);
            return;
        }
        $nome = trim((string)($_POST['nome'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $telefone = preg_replace('/\D/', '', (string)($_POST['telefone'] ?? ''));

        $erros = [];
        if (mb_strlen($nome) < 3) $erros[] = 'Informe seu nome completo.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail invalido.';
        if (!$this->validarTelefoneBr($telefone)) $erros[] = 'WhatsApp invalido (DDD + numero).';
        if ($erros) {
            $this->setFlashMessage(implode(' ', $erros), 'error');
            $this->redirect('/registro/simples/' . $tipo);
            return;
        }

        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? FOR UPDATE');
            $dup->execute([$email]);
            if ($dup->fetch()) {
                $pdo->rollBack();
                $this->setFlashMessage('Este e-mail ja tem conta. Faca login.', 'error');
                $this->redirect('/login?retorno=' . rawurlencode('/perfil/completar'));
                return;
            }

            $senhaPlaceholder = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, telefone, tipo, perfil_status, ativo, criado_em) VALUES (?, ?, ?, ?, ?, 'PENDENTE', 1, NOW())");
            $stmt->execute([$nome, $email, $senhaPlaceholder, $telefone, $tipo]);
            $userId = (int)$pdo->lastInsertId();

            $pdo->commit();

            $user = ['id' => $userId, 'nome' => $nome, 'email' => $email, 'tipo' => $tipo, 'ativo' => true];
            AuthService::initializeAuthenticatedSession($user);
            $this->setFlashMessage('Conta criada. Complete seus dados para liberar o painel.', 'success');
            $checkoutComTriagem = $tipo === 'cliente' ? $this->checkoutPathFromActiveTriage() : null;
            if ($checkoutComTriagem !== null) {
                $this->redirect($checkoutComTriagem);
                return;
            }
            $this->redirect('/perfil/completar');
            return;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[registroSimples] ' . $e->getMessage());
            $this->setFlashMessage('Nao foi possivel criar a conta agora.', 'error');
            $this->redirect('/registro/simples/' . $tipo);
        }
    }

    public function perfilCompletarForm(): void
    {
        if (!$this->isAuthenticated()) { $this->redirect('/login?retorno=' . rawurlencode('/perfil/completar')); return; }
        $user = AuthService::getCurrentUser();
        if (!$user) { $this->redirect('/login'); return; }

        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id, nome, email, telefone, cpf, tipo, perfil_status FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$user['id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (($u['perfil_status'] ?? '') === 'COMPLETO') { $this->redirectByProfile((string)$u['tipo']); return; }

        $tipo = in_array((string)($u['tipo'] ?? 'cliente'), ['cliente','guincho','oficina','especialista'], true) ? (string)$u['tipo'] : 'cliente';
        $salvo = [
            'nome' => $u['nome'] ?? '',
            'telefone' => $u['telefone'] ?? '',
            'cpf' => $u['cpf'] ?? '',
            'estado' => '',
        ];
        $pendenteAprovacao = (($u['perfil_status'] ?? '') === 'PENDENTE_APROVACAO');
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        require __DIR__ . '/../Views/auth/perfil_completar.php';
    }

    public function perfilCompletarSave(): void
    {
        if (!$this->isAuthenticated() || !$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Sessao expirada. Entre novamente.', 'error');
            $this->redirect('/login?retorno=' . rawurlencode('/perfil/completar'));
            return;
        }
        $user = AuthService::getCurrentUser();
        if (!$user) { $this->redirect('/login'); return; }

        $nome = trim((string)($_POST['nome'] ?? ''));
        $cpf = preg_replace('/\D/', '', (string)($_POST['cpf'] ?? ''));
        $telefone = preg_replace('/\D/', '', (string)($_POST['telefone'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');
        $conf = (string)($_POST['confirmar_senha'] ?? '');
        $cep = preg_replace('/\D/', '', (string)($_POST['cep'] ?? ''));
        $logradouro = trim((string)($_POST['logradouro'] ?? ''));
        $numero = trim((string)($_POST['numero'] ?? ''));
        $bairro = trim((string)($_POST['bairro'] ?? ''));
        $cidade = trim((string)($_POST['cidade'] ?? ''));
        $estado = strtoupper(trim((string)($_POST['estado'] ?? '')));

        $erros = [];
        if (mb_strlen($nome) < 3) $erros[] = 'Nome invalido.';
        if (!$this->validarDocumentoBr($cpf)) $erros[] = 'CPF ou CNPJ invalido.';
        if (!$this->validarTelefoneBr($telefone)) $erros[] = 'Telefone invalido.';
        if (strlen($senha) < 8) $erros[] = 'Senha deve ter 8+ caracteres.';
        if ($senha !== $conf) $erros[] = 'Senhas nao conferem.';
        if (strlen($cep) !== 8) $erros[] = 'CEP invalido.';
        if ($logradouro === '' || $numero === '' || $bairro === '' || $cidade === '') $erros[] = 'Endereco incompleto.';
        if (!preg_match('/^[A-Z]{2}$/', $estado)) $erros[] = 'UF invalida.';

        if ($erros) {
            $this->setFlashMessage(implode(' ', $erros), 'error');
            $this->redirect('/perfil/completar');
            return;
        }

        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE cpf = ? AND id <> ? LIMIT 1 FOR UPDATE');
            $dup->execute([$cpf, (int)$user['id']]);
            if ($dup->fetch()) {
                $pdo->rollBack();
                $this->setFlashMessage('Este CPF ja esta em outra conta.', 'error');
                $this->redirect('/perfil/completar');
                return;
            }

            $tipoAtual = (string)($_SESSION['user']['tipo'] ?? 'cliente');
            $novoStatus = ($tipoAtual === 'cliente') ? 'COMPLETO' : 'PENDENTE_APROVACAO';

            $pdo->prepare('UPDATE usuarios SET nome = ?, cpf = ?, telefone = ?, senha_hash = ?, perfil_status = ?, atualizado_em = NOW() WHERE id = ?')
                ->execute([$nome, $cpf, $telefone, password_hash($senha, PASSWORD_BCRYPT), $novoStatus, (int)$user['id']]);

            $temEnd = $pdo->prepare('SELECT id FROM enderecos WHERE usuario_id = ? LIMIT 1');
            $temEnd->execute([(int)$user['id']]);
            if ($temEnd->fetch()) {
                $pdo->prepare('UPDATE enderecos SET cep = ?, logradouro = ?, numero = ?, bairro = ?, cidade = ?, estado = ? WHERE usuario_id = ?')
                    ->execute([$cep, $logradouro, $numero, $bairro, $cidade, $estado, (int)$user['id']]);
            } else {
                $pdo->prepare('INSERT INTO enderecos (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, principal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)')
                    ->execute([(int)$user['id'], $cep, $logradouro, $numero, trim((string)($_POST['complemento'] ?? '')) ?: null, $bairro, $cidade, $estado]);
            }

            $_SESSION['user']['nome'] = $nome;
            $_SESSION['user']['perfil_status'] = $novoStatus;

            $pdo->commit();

            if ($novoStatus === 'COMPLETO') {
                $this->setFlashMessage('Perfil completo. Bem-vindo!', 'success');
                $checkoutComTriagem = $tipoAtual === 'cliente' ? $this->checkoutPathFromActiveTriage() : null;
                if ($checkoutComTriagem !== null) {
                    $this->redirect($checkoutComTriagem);
                    return;
                }
                $this->redirectByProfile($tipoAtual);
            } else {
                $this->setFlashMessage('Dados recebidos. Sua documentacao sera analisada pela equipe.', 'success');
                $this->redirect('/perfil/completar');
            }
            return;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[perfilCompletarSave] ' . $e->getMessage());
            $this->setFlashMessage('Nao foi possivel salvar. Tente novamente.', 'error');
            $this->redirect('/perfil/completar');
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
        $emailEnviado = false;

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

                    $emailEnviado = $this->enviarEmail(
                        $email,
                        'Redefinição de senha — GuinchaFácil',
                        $corpo
                    );
                }
            } catch (Throwable $e) {
                error_log("Erro esqueceuSenha: " . $e->getMessage());
            }
        }

        if ($email && isset($user) && !$emailEnviado) {
            $this->setFlashMessage('Não foi possível enviar o email agora. Verifique a configuração SMTP ou tente novamente mais tarde.', 'error');
        } else {
            $this->setFlashMessage('Se esse email existir, enviaremos um link de redefinição.', 'success');
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

        $token = strtolower(trim((string)($_POST['token'] ?? '')));
        $senha = $_POST['senha'] ?? '';
        $confirma = $_POST['confirmacao'] ?? '';

        if (!preg_match('/\A[a-f0-9]{64}\z/', $token) || strlen($senha) < 8 || $senha !== $confirma) {
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

            $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE email = ?")
                ->execute([password_hash($senha, PASSWORD_BCRYPT), $reset['email']]);

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
        $dados['cnh_validade'] = trim($post['cnh_validade'] ?? '') ?: date('Y-m-d', strtotime('+5 years'));
        $dados['placa_guincho'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $post['placa_guincho'] ?? ''));
        $dados['cidade_placa'] = trim((string)($post['cidade_placa'] ?? ''));
        $dados['uf_placa'] = strtoupper(trim((string)($post['uf_placa'] ?? '')));
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
        $dados['cidade_id'] = (int)($post['cidade_id'] ?? 0);
        $dados['capacidade_ton'] = (float)($post['capacidade_ton'] ?? 0);
        $dados['raio_cobertura_km'] = (int)($post['raio_cobertura_km'] ?? 20);
        $dados['chave_pix'] = trim($post['chave_pix'] ?? '');
        $dados['chave_pix_tipo'] = $this->validarTipoPix($post['chave_pix_tipo'] ?? '');
        $dados['lat_operacao'] = isset($post['lat_operacao']) ? (float)$post['lat_operacao'] : null;
        $dados['lng_operacao'] = isset($post['lng_operacao']) ? (float)$post['lng_operacao'] : null;

        $servicosValidos = ['reboque', 'chaveiro', 'eletrica', 'pneu', 'mecanica', 'combustivel'];
        $servicos = array_values(array_intersect($servicosValidos, (array)($post['servicos'] ?? [])));
        if (empty($servicos)) {
            $servicos = ['reboque'];
        }
        $dados['servicos'] = $servicos;
        $dados['oferece_reboque'] = in_array('reboque', $servicos, true) ? 1 : 0;

        return $dados;
    }

    private function validarTipoPix(string $tipo): string
    {
        $tiposPermitidos = ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'];
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
        if (!$this->validarTelefoneBr($dados['telefone'])) {
            $erros[] = 'Telefone inválido (DDD + número).';
        }
        if (!$this->validarDocumentoBr($dados['cpf'])) {
            $erros[] = 'CPF ou CNPJ inválido.';
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

        require_once __DIR__ . '/../Models/Cidade.php';
        $cidadeId = (int)($dados['cidade_id'] ?? 0);
        if ($cidadeId <= 0 || !Cidade::buscarPorId($cidadeId) || empty(Cidade::buscarPorId($cidadeId)['ativo'])) {
            $erros[] = 'Selecione uma cidade-alvo de atuação válida.';
        }

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

        $camposObrigatorios = ['doc_cnh_frente', 'doc_cnh_verso'];
        foreach ($camposObrigatorios as $campo) {
            if (false && (!isset($files[$campo]) || $files[$campo]['error'] === UPLOAD_ERR_NO_FILE)) {
                $erros[] = 'Documento da CNH (frente e verso) é obrigatório.';
                break;
            }
        }

        return $erros;
    }

    /** Valida telefone brasileiro: 10 ou 11 digitos, DDD 11-99, celular comeca com 9. */
    private function validarTelefoneBr(string $tel): bool
    {
        $d = preg_replace('/\D/', '', $tel);
        if (strlen($d) < 10 || strlen($d) > 11) {
            return false;
        }
        $ddd = (int)substr($d, 0, 2);
        if ($ddd < 11 || $ddd > 99) {
            return false;
        }
        if (strlen($d) === 11 && $d[2] !== '9') {
            return false;
        }
        return true;
    }

    private function validarChavePix(string $chave, string $tipo): bool
    {
        return match($tipo) {
            'cpf' => $this->validarCPF(preg_replace('/\D/', '', $chave)),
            'cnpj' => $this->validarCNPJ(preg_replace('/\D/', '', $chave)),
            'email' => filter_var($chave, FILTER_VALIDATE_EMAIL) !== false,
            'telefone' => $this->validarTelefoneBr($chave),
            'aleatoria' => strlen($chave) >= 8 && strlen($chave) <= 32,
            default => false
        };
    }

    private function validarCPF(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

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

    /** Aceita CPF ou CNPJ, sempre após normalização para dígitos. */
    private function validarDocumentoBr(string $documento): bool
    {
        $digitos = preg_replace('/\D/', '', $documento);
        return strlen($digitos) === 11
            ? $this->validarCPF($digitos)
            : (strlen($digitos) === 14 && $this->validarCNPJ($digitos));
    }

    private function validarCNPJ(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        for ($posicao = 12; $posicao <= 13; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int)$cnpj[$i] * $pesos[$i + (13 - $posicao)];
            }
            $digito = ($soma % 11) < 2 ? 0 : 11 - ($soma % 11);
            if ($digito !== (int)$cnpj[$posicao]) {
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

        if ($file['size'] > self::UPLOAD_MAX_SIZE) {
            throw new Exception("Arquivo muito grande (máximo 5MB)");
        }

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

        chmod($destPath, 0644);

        return $fileName;
    }

    private function getUploadDir(): string
    {
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

    private function checkoutPathFromActiveTriage(): ?string
    {
        $cotacao = $_SESSION['pre_cotacao'] ?? null;
        if (is_array($cotacao) && isset($cotacao['expira_em']) && (int)$cotacao['expira_em'] <= time()) {
            return null;
        }

        $triageSessionId = trim((string)(
            $_SESSION['triage_session_id']
            ?? (is_array($cotacao) ? ($cotacao['triage_session_id'] ?? '') : '')
        ));

        if ($triageSessionId === '') {
            return is_array($cotacao) ? '/checkout/veiculo' : null;
        }

        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/', $triageSessionId)) {
            error_log('[AuthController::checkoutPathFromActiveTriage][TRIAGE-ID-INVALID] ID de triagem descartado.');
            return null;
        }

        return '/checkout/veiculo?triage_session_id=' . rawurlencode($triageSessionId);
    }

    private function redirectByProfile(?string $tipo = null): void
    {
        $tipo = $tipo ?? ($_SESSION['user']['tipo'] ?? null);
        if (!empty($_SESSION['user']['id'])) {
            try {
                $pending = getPDO()->prepare('SELECT perfil_status FROM usuarios WHERE id = ? LIMIT 1');
                $pending->execute([(int)$_SESSION['user']['id']]);
                if ((string)$pending->fetchColumn() !== 'COMPLETO') {
                    $this->redirect('/perfil/completar');
                    return;
                }
            } catch (Throwable $e) {
                // Bases anteriores à migration seguem o fluxo legado.
            }
        }

        $dest = match($tipo) {
            'admin'       => '/admin/dashboard',
            'guincho'     => '/guincho/dashboard',
            'cliente'     => '/cliente/dashboard',
            'funcionario' => '/funcionario/dashboard',
            'gerente'     => '/gerente/dashboard',
            'especialista'=> '/especialista/dashboard',
            'oficina'      => '/oficina/dashboard',
            default        => '/logout',
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

    // ─── REGISTRO EM 2 ETAPAS (§23 B-UNIFIC-23) ──────────────────────
    // Fluxo público pré-cotação → registro → checkout.
    // Complementa registroSimples() (3 campos) com CPF + CEP + endereço +
    // categoria de veículo, tudo em uma única transação.
    // Grava $_SESSION['pre_cotacao']['pagador'] para a Faixa A injetar no Brick MP.

    public function registroFunilForm(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirectByProfile();
            return;
        }
        $csrf_token = $this->generateCSRFToken();
        $flash = $this->pullFlash();
        // Draft é lido pela view direto de $_SESSION['pre_cotacao_draft'].
        require __DIR__ . '/../Views/public/registro_funil_etapas.php';
    }

    public function registroFunil(): void
    {
        if (!$this->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/registro/funil');
            return;
        }

        $nome      = trim((string)($_POST['nome'] ?? ''));
        $email     = strtolower(trim((string)($_POST['email'] ?? '')));
        $telefone  = preg_replace('/\D/', '', (string)($_POST['telefone'] ?? ''));
        $cpf       = preg_replace('/\D/', '', (string)($_POST['cpf'] ?? ''));
        $cep       = preg_replace('/\D/', '', (string)($_POST['cep'] ?? ''));
        $endereco  = trim((string)($_POST['endereco'] ?? ''));
        $categoria = (string)($_POST['categoria'] ?? 'popular');

        $categoriasValidas = ['popular', 'moto', 'suv', 'utilitario'];
        if (!in_array($categoria, $categoriasValidas, true)) {
            $categoria = 'popular';
        }

        $erros = [];
        if (mb_strlen($nome) < 3) {
            $erros[] = 'Informe seu nome completo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido.';
        }
        if (!$this->validarTelefoneBr($telefone)) {
            $erros[] = 'WhatsApp inválido (DDD + número).';
        }
        if (!$this->validarCPF($cpf)) {
            $erros[] = 'CPF inválido.';
        }
        if (strlen($cep) !== 8) {
            $erros[] = 'CEP inválido.';
        }
        if (mb_strlen($endereco) < 5) {
            $erros[] = 'Endereço incompleto.';
        }

        $_SESSION['pre_cotacao_draft'] = [
            'nome'      => $nome,
            'email'     => $email,
            'telefone'  => $telefone,
            'cpf'       => $cpf,
            'cep'       => $cep,
            'endereco'  => $endereco,
            'categoria' => $categoria,
        ];

        if ($erros) {
            $this->setFlashMessage(implode(' ', $erros), 'error');
            $this->redirect('/registro/funil');
            return;
        }

        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? OR cpf = ? LIMIT 1 FOR UPDATE');
            $dup->execute([$email, $cpf]);
            if ($dup->fetch()) {
                $pdo->rollBack();
                $this->setFlashMessage('Este e-mail ou CPF já tem conta. Faça login.', 'error');
                $this->redirect('/login?retorno=' . rawurlencode('/checkout/veiculo'));
                return;
            }

            $senhaPlaceholder = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, perfil_status, ativo, criado_em)
                 VALUES (?, ?, ?, ?, ?, 'cliente', 'COMPLETO', 1, NOW())"
            );
            $stmt->execute([$nome, $email, $senhaPlaceholder, $telefone, $cpf]);
            $userId = (int)$pdo->lastInsertId();

            $partes     = array_map('trim', explode(',', $endereco));
            $logradouro = $partes[0] ?? $endereco;
            $bairro     = $partes[1] ?? '';
            $cidade     = $partes[2] ?? '';
            $estado     = strtoupper($partes[3] ?? '');
            if (!preg_match('/^[A-Z]{2}$/', $estado)) {
                $estado = '';
            }

            $pdo->prepare(
                "INSERT INTO enderecos (usuario_id, cep, logradouro, numero, bairro, cidade, estado, principal)
                 VALUES (?, ?, ?, '', ?, ?, ?, 1)"
            )->execute([$userId, $cep, $logradouro, $bairro, $cidade, $estado]);

            if (!is_array($_SESSION['pre_cotacao'] ?? null)) {
                $_SESSION['pre_cotacao'] = [];
            }
            $_SESSION['pre_cotacao']['pagador'] = [
                'nome'      => $nome,
                'email'     => $email,
                'telefone'  => $telefone,
                'cpf'       => $cpf,
                'categoria' => $categoria,
            ];

            $pdo->commit();

            AuthService::initializeAuthenticatedSession([
                'id'    => $userId,
                'nome'  => $nome,
                'email' => $email,
                'tipo'  => 'cliente',
                'ativo' => true,
            ]);

            $checkoutComTriagem = $this->checkoutPathFromActiveTriage();
            if ($checkoutComTriagem !== null) {
                $this->redirect($checkoutComTriagem);
                return;
            }
            $this->redirect('/cliente/dashboard');
            return;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[registroFunil] ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível criar sua conta agora.', 'error');
            $this->redirect('/registro/funil');
        }
    }
}
