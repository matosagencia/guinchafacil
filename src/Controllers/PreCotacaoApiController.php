<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/TriageSessionService.php';
require_once __DIR__ . '/../Services/GeocodingService.php';
require_once __DIR__ . '/../Services/PreCotacao/PreCotacaoOpcoesService.php';

final class PreCotacaoApiController extends BaseController
{
    public function form(): void
    {
        require __DIR__ . '/../Views/public/pre-cotacao.php';
    }

    public function finalizar(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        try {
            $json = json_decode((string)file_get_contents('php://input'), true);
            $entrada = is_array($json) ? $json : $_POST;
            $cotacao = is_array($entrada['cotacao'] ?? null) ? $entrada['cotacao'] : $entrada;
            // B-011: nenhum custo/distância do cliente é fonte de verdade.
            $oficial = $_SESSION['pre_cotacao'] ?? null;
            if (!is_array($oficial) || ($oficial['origem_tarifa'] ?? '') !== 'precificacao_oficial'
                || (int)($oficial['expira_em'] ?? 0) <= time()
                || empty($oficial['regra_id']) || !isset($oficial['valor'], $oficial['distancia_km'])) {
                http_response_code(409);
                throw new InvalidArgumentException('PRICE-QUOTE-REQUIRED');
            }
            foreach (['tipo_problema','lat_origem','lng_origem','lat_destino','lng_destino',
                      'endereco_origem','endereco_destino','distancia_km'] as $chave) {
                $cotacao[$chave] = $oficial[$chave] ?? null;
            }
            $cotacao['custo_estimado'] = (float)$oficial['valor'];
            $triagem = TriageSessionService::finalizar($cotacao);
            $checkoutUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/checkout/veiculo?triage_session_id=' . rawurlencode($triagem['id']);
            $nextUrl = AuthService::isLoggedIn()
                ? $checkoutUrl
                : (defined('BASE_PATH') ? BASE_PATH : '') . '/registro/cliente?retorno=' . rawurlencode($checkoutUrl);
            echo json_encode([
                'ok' => true,
                'triage_session_id' => $triagem['id'],
                'checkout_url' => $checkoutUrl,
                'next_url' => $nextUrl,
            ], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            Logger::log(Logger::LEVEL_WARN, 'PreCotacaoApiController', 'finalizar', 'triage', '[TRIAGE-VALIDATION-FAIL] ' . $e->getMessage());
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Logger::exception('PreCotacaoApiController', 'finalizar', 'triage', $e, ['phase' => 'TRIAGE-SAVE-FAIL']);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Não foi possível finalizar a cotação.'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function endereco(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $json = json_decode((string) file_get_contents('php://input'), true);
        $entrada = is_array($json) ? $json : $_POST;
        $lat = self::coordenadaValida($entrada['lat'] ?? null, -34.0, 5.0);
        $lng = self::coordenadaValida($entrada['lng'] ?? null, -74.0, -28.0);

        if ($lat === null || $lng === null) {
            Logger::log(Logger::LEVEL_WARN, 'PreCotacaoApiController', 'endereco', 'geocoding', '[REVERSE-GEOCODING-VALIDATION-FAIL] Coordenadas invalidas.');
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Coordenadas inválidas'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $endereco = (new GeocodingService())->reverseGeocode($lat, $lng);
            $label = trim((string) ($endereco['street'] ?? ''));

            if ($label === '') {
                Logger::log(Logger::LEVEL_WARN, 'PreCotacaoApiController', 'endereco', 'geocoding', '[REVERSE-GEOCODING-EMPTY] Nenhum endereco encontrado. lat=' . $lat . ' lng=' . $lng);
                http_response_code(500);
                echo json_encode(['ok' => false, 'erro' => 'Falha no reverse geocoding'], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'ok' => true,
                'label' => $label,
                'numero' => $endereco['numero'] ?? null,
                'cidade' => $endereco['cidade'] ?? null,
                'uf' => $endereco['uf'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Logger::exception('PreCotacaoApiController', 'endereco', 'geocoding', $e, [
                'phase' => 'REVERSE-GEOCODING-FAIL',
                'lat' => $lat,
                'lng' => $lng,
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Falha no reverse geocoding'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/pre-cotacao/endereco/sugestoes?q=X&limit=8
     * Contrato de public/assets/js/components/address-picker.js: data.items.
     */
    public function sugestoes(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $q = trim((string)($_GET['q'] ?? ''));
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 8)));
        if (mb_strlen($q, 'UTF-8') < 3) {
            echo json_encode(['ok' => true, 'data' => ['items' => []]], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $serviceFile = __DIR__ . '/../Services/Address/AddressAutocompleteService.php';
            if (!is_file($serviceFile)) {
                throw new RuntimeException('AddressAutocompleteService.php não encontrado.');
            }
            require_once $serviceFile;
            $items = (new AddressAutocompleteService())->suggest($q, null, null, $limit);
            echo json_encode(['ok' => true, 'data' => ['items' => array_values($items)]], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            Logger::exception('PreCotacaoApiController', 'sugestoes', 'geocoding', $e, [
                'phase' => 'ADDRESS-SUGGEST-FAIL', 'q' => $q,
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Falha ao buscar sugestões'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/pre-cotacao/endereco/reverso?lat=Y&lng=Z
     * Contrato de address-picker.js: data.item.
     */
    public function reverso(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $lat = self::coordenadaValida($_GET['lat'] ?? null, -34.0, 5.0);
        $lng = self::coordenadaValida($_GET['lng'] ?? null, -74.0, -28.0);
        if ($lat === null || $lng === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Coordenadas inválidas'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $end = (new GeocodingService())->reverseGeocode($lat, $lng);
            if (!is_array($end)) {
                $rua = 'Local aproximado';
            }
            $rua = trim((string)($end['street'] ?? ''));
            $cidade = trim((string)($end['cidade'] ?? ''));
            $uf = trim((string)($end['uf'] ?? ''));
            if ($rua === '') $rua = 'Local aproximado';
            $cidadeUf = $cidade;
            if ($cidadeUf !== '' && $uf !== '') $cidadeUf .= '/' . $uf;
            elseif ($cidadeUf === '') $cidadeUf = $uf;

            $item = [
                'principal' => $rua,
                'secundario' => $cidadeUf,
                'lat' => $lat,
                'lng' => $lng,
                'numero' => $end['numero'] ?? null,
                'cidade' => $cidade,
                'uf' => $uf,
                'precisa_numero' => empty($end['numero']),
            ];
            echo json_encode(['ok' => true, 'data' => ['item' => $item]], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            Logger::exception('PreCotacaoApiController', 'reverso', 'geocoding', $e, [
                'phase' => 'REVERSE-GEOCODING-FAIL', 'lat' => $lat, 'lng' => $lng,
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Falha no reverse geocoding'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/pre-cotacao/cobertura?lat=Y&lng=Z
     */
    public function cobertura(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $lat = self::coordenadaValida($_GET['lat'] ?? null, -34.0, 5.0);
        $lng = self::coordenadaValida($_GET['lng'] ?? null, -74.0, -28.0);

        if ($lat === null || $lng === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Coordenadas invalidas'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $temGuincho = false;
        try {
            require_once __DIR__ . '/../Services/GuinchoDisponibilidade.php';
            $temGuincho = (bool)GuinchoDisponibilidade::existeNoRaio($lat, $lng);
        } catch (Throwable $e) {
            Logger::exception('PreCotacaoApiController', 'cobertura', 'disponibilidade', $e, ['phase' => 'COBERTURA-FAIL']);
        }

        echo json_encode([
            'ok' => true,
            'data' => [
                'tem_guincho' => $temGuincho,
                'qtd_oficinas' => 0,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
    /**
     * GET /api/pre-cotacao/triagem-servicos
     */
    public function triagemServicos(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $servicos = [];
        try {
            require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
            foreach (\ServiceType::listarAtivos() as $tipo) {
                $modo = (string)($tipo['attendance_mode'] ?? '');
                $slug = strtolower((string)($tipo['code'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $servicos[] = [
                    'slug' => $slug,
                    'nome' => (string)($tipo['name'] ?? $slug),
                    'descricao' => (string)($tipo['description'] ?? ''),
                    'icone' => $modo === 'TOWING' ? 'fa-truck' : 'fa-wrench',
                    'attendance_mode' => $modo,
                    'service_type_id' => (int)($tipo['id'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            Logger::exception('PreCotacaoApiController', 'triagemServicos', 'catalog', $e, ['phase' => 'TRIAGEM-SERVICOS-FAIL']);
            http_response_code(503);
            echo json_encode(['ok' => false, 'erro' => 'CATALOGO_INDISPONIVEL'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['ok' => true, 'data' => ['servicos' => $servicos]], JSON_UNESCAPED_UNICODE);
    }
    /**
     * GET /api/pre-cotacao/opcoes
     *   ?modo=orientacao|local|reboque
     *   &servico=<slug>
     *   &lat=<float>   &lng=<float>
     *   &lat_destino=<float>  &lng_destino=<float>   (opcional, reboque)
     *
     * Contrato de public-pre-cotacao-flow.js:
     *   { ok: true, data: { modo, disponivel, ... } }  — shape definido em PreCotacaoOpcoesService::montar()
     */
    public function opcoes(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $modo    = trim((string)($_GET['modo']    ?? 'orientacao'));
        $servico = trim((string)($_GET['servico'] ?? ''));

        $lat = self::coordenadaValida($_GET['lat'] ?? null, -34.0, 5.0);
        $lng = self::coordenadaValida($_GET['lng'] ?? null, -74.0, -28.0);

        if ($lat === null || $lng === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Coordenadas de origem inválidas'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Destino é opcional (só reboque usa)
        $latDestino = self::coordenadaValida($_GET['lat_destino'] ?? null, -34.0, 5.0);
        $lngDestino = self::coordenadaValida($_GET['lng_destino'] ?? null, -74.0, -28.0);

        try {
            $payload = PreCotacaoOpcoesService::montar(
                $modo,
                $servico,
                $lat,
                $lng,
                $latDestino,
                $lngDestino
            );

            Logger::log(
                Logger::LEVEL_INFO,
                'PreCotacaoApiController',
                'opcoes',
                'pre-cotacao',
                '[OPCOES-OK] modo=' . $modo . ' servico=' . $servico
                    . ' disponivel=' . ($payload['disponivel'] ?? '?')
                    . ' fallback=' . ($payload['fallback_tipo'] ?? 'none')
            );

            echo json_encode(['ok' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Logger::exception('PreCotacaoApiController', 'opcoes', 'pre-cotacao', $e, [
                'phase'   => 'OPCOES-FAIL',
                'modo'    => $modo,
                'servico' => $servico,
                'lat'     => $lat,
                'lng'     => $lng,
            ]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Falha ao calcular opções.'], JSON_UNESCAPED_UNICODE);
        }
    }

    private static function coordenadaValida($valor, float $minimo, float $maximo): ?float
    {
        if (!is_numeric($valor)) {
            return null;
        }

        $coordenada = (float) $valor;
        if (!is_finite($coordenada) || $coordenada < $minimo || $coordenada > $maximo) {
            return null;
        }

        return $coordenada;
    }
}
