<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/TriageSessionService.php';
require_once __DIR__ . '/../Services/GeocodingService.php';

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
