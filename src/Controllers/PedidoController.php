<?php

declare(strict_types=1);

require_once __DIR__ . '/../DTO/PedidoQuoteRequest.php';
require_once __DIR__ . '/../DTO/PedidoCreateRequest.php';
require_once __DIR__ . '/../Services/PedidoCoreService.php';
require_once __DIR__ . '/../Services/Pricing/PedidoPricingService.php';
require_once __DIR__ . '/../Services/Financial/ChargePolicyService.php';
require_once __DIR__ . '/../Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Services/DecisaoAtendimentoService.php';
require_once __DIR__ . '/../Models/PedidoDecisaoSocorro.php';

final class PedidoController
{
    public function decisaoPreCotacao(): void
    {
        $this->json(function (): array {
            $payload = $this->payload();
            $draft = is_array($payload['pedido_draft'] ?? null) ? $payload['pedido_draft'] : $payload;
            $lat = filter_var($draft['lat_origem'] ?? $draft['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($draft['lng_origem'] ?? $draft['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($lat === false || $lng === false) {
                throw new InvalidArgumentException('Informe a localizacao de origem para comparar as opcoes.');
            }

            $veiculoPodeMover = filter_var(
                $draft['veiculo_pode_mover'] ?? true,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            $service = new DecisaoAtendimentoService();
            $decisao = $service->avaliar(
                (int)($draft['pedido_id'] ?? 0),
                (string)($draft['tipo_problema'] ?? $draft['tipo'] ?? 'outro'),
                (float)$lat,
                (float)$lng,
                [
                    'categoria' => (string)($draft['categoria'] ?? 'popular'),
                    'distancia_km' => (float)($draft['distancia_km'] ?? 5.0),
                    'custo_total' => (float)($draft['custo_total'] ?? $draft['valor'] ?? 0.0),
                    'veiculo_pode_mover' => $veiculoPodeMover ?? true,
                ]
            );

            $escolha = strtolower(trim((string)($payload['escolha'] ?? '')));
            if (in_array($escolha, ['assistencia', 'reboque'], true)) {
                $_SESSION['pre_cotacao_decisao'] = [
                    'escolha' => $escolha,
                    'payload' => $decisao,
                    'registrado_em' => date('c'),
                ];
                $pedidoId = (int)($draft['pedido_id'] ?? 0);
                if ($pedidoId > 0) {
                    $codigo = $escolha === 'assistencia'
                        ? PedidoDecisaoSocorro::ORCAMENTO_INFORMADO
                        : PedidoDecisaoSocorro::REBOQUE_SOLICITADO;
                    PedidoDecisaoSocorro::registrar($pedidoId, $codigo, 'cliente', (int)($_SESSION['usuario_id'] ?? 0) ?: null, null, [
                        'origem' => 'pre_cotacao',
                        'recomendacao' => $decisao['recomendacao'] ?? null,
                    ]);
                }
            }

            return $decisao + ['escolha' => $escolha !== '' ? $escolha : null];
        });
    }

    public function cotar(): void
    {
        $this->json(function (): array {
            $tipoUsuario = (string)($_SESSION['usuario_tipo'] ?? $_SESSION['tipo'] ?? '');
            $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
            $payload = $this->payload();
            if ($tipoUsuario === 'cliente') {
                $payload['cliente_id'] = $usuarioId;
            }
            return $this->core()->cotar(new PedidoQuoteRequest($payload), [
                'actor_type' => $tipoUsuario !== '' ? $tipoUsuario : 'public',
                'actor_id' => $usuarioId,
                'allow_modalidade_override' => $tipoUsuario === 'admin',
                'modalidade_override' => $payload['modalidade_socorro'] ?? null,
                'allow_priority' => $tipoUsuario === 'admin',
                'prioridade' => !empty($payload['prioridade']),
            ]);
        });
    }

    public function criar(): void
    {
        $this->json(function (): array {
            $payload = $this->payload();
            if (!AuthService::validarCsrfToken((string)($payload['csrf_token'] ?? ''))) {
                http_response_code(403);
                throw new RuntimeException('Sessao expirada. Recarregue a pagina e tente novamente.');
            }

            $tipoUsuario = (string)($_SESSION['usuario_tipo'] ?? $_SESSION['tipo'] ?? '');
            $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
            if ($tipoUsuario === 'cliente') {
                $payload['cliente_id'] = $usuarioId;
                $payload['actor_type'] = 'cliente';
                $payload['actor_id'] = $usuarioId;
                unset($payload['provider_id'], $payload['forcar_prestador_id']);
            } elseif ($tipoUsuario === 'admin') {
                $payload['actor_type'] = 'admin';
                $payload['actor_id'] = $usuarioId;
                if (!empty($payload['forcar_prestador_id']) && empty($payload['provider_id'])) {
                    $payload['provider_id'] = (int)$payload['forcar_prestador_id'];
                }
            } else {
                http_response_code(401);
                throw new RuntimeException('Autenticacao obrigatoria para criar pedido.');
            }

            return ['pedido_id' => $this->core()->criar(new PedidoCreateRequest($payload))];
        }, true);
    }

    private function json(callable $handler, bool $mutable = false): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            echo json_encode(['ok' => true, 'data' => $handler(), 'error' => null], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            if (http_response_code() < 400) {
                http_response_code(422);
            }
            echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code($mutable ? 500 : 422);
            }
            echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'ORDER_CORE_ERROR', 'message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function payload(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        return is_array($json) ? ($json + $_POST) : ($_POST + $_GET);
    }

    private function core(): PedidoCoreService
    {
        return new PedidoCoreService(getPDO(), new PedidoPricingService(), new ChargePolicyService(), new PedidoTransitionService());
    }

    /** GET /api/pre-cotacao/oficinas-proximas?lat=X&lng=Y&tipo=Z */
    public function oficinasProximas(): void
    {
        $this->json(function (): array {
            $lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            $tipo = strtolower(trim((string)($_GET['tipo'] ?? '')));
            if ($lat === false || $lng === false) {
                throw new InvalidArgumentException('Informe lat/lng.');
            }
            $svc = new DecisaoAtendimentoService();
            return [
                'ok' => true,
                'oficinas' => $svc->oficinasProximasPublico((float)$lat, (float)$lng, $tipo),
            ];
        });
    }

    /** GET /api/pre-cotacao/validar-uf-destino?lat_origem=&lng_origem=&lat_destino=&lng_destino= */
    public function validarUfDestino(): void
    {
        $this->json(function (): array {
            $latO = filter_var($_GET['lat_origem']  ?? null, FILTER_VALIDATE_FLOAT);
            $lngO = filter_var($_GET['lng_origem']  ?? null, FILTER_VALIDATE_FLOAT);
            $latD = filter_var($_GET['lat_destino'] ?? null, FILTER_VALIDATE_FLOAT);
            $lngD = filter_var($_GET['lng_destino'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($latO === false || $lngO === false || $latD === false || $lngD === false) {
                throw new InvalidArgumentException('Informe lat/lng de origem e destino.');
            }

            $ufO = $this->ufDeCoordenada((float)$latO, (float)$lngO);
            $ufD = $this->ufDeCoordenada((float)$latD, (float)$lngD);

            // Se não conseguir detectar alguma das UFs, permite (não bloqueia)
            $ok = ($ufO === null || $ufD === null || $ufO === $ufD);

            return [
                'ok' => $ok,
                'uf_origem' => $ufO,
                'uf_destino' => $ufD,
                'mensagem' => $ok
                    ? ''
                    : "O veículo só pode ser levado para uma oficina no mesmo estado. Sua origem está em {$ufO} e o destino em {$ufD}.",
            ];
        });
    }

    /** Reverse geocode local — retorna UF (ex: "RJ") ou null. */
    private function ufDeCoordenada(float $lat, float $lng): ?string
    {
        try {
            // 1) Tenta usar GeocodingService diretamente (SEM HTTP)
            $svcFile = __DIR__ . '/../Services/GeocodingService.php';
            if (is_file($svcFile)) {
                require_once $svcFile;
                if (class_exists('GeocodingService')) {
                    $svc = new \GeocodingService();
                    foreach (['reverse', 'reverseGeocode', 'reverseGeocoding', 'geocodificarReverso'] as $method) {
                        if (method_exists($svc, $method)) {
                            try {
                                $r = $svc->$method($lat, $lng);
                                if (is_array($r)) {
                                    $uf = $this->extrairUfDeResultado($r);
                                    if ($uf) return $uf;
                                }
                            } catch (Throwable $e) {
                                error_log("[ufDeCoordenada] método $method falhou: " . $e->getMessage());
                            }
                        }
                    }
                }
            }

            // 2) Fallback: chama Nominatim DIRETAMENTE (sem passar pelo nosso servidor)
            $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&addressdetails=1&accept-language=pt-BR";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => 'GuinchaFacil/1.0 (contato@guinchafacil.com.br)',
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $caPath = defined('CA_BUNDLE_PATH') ? CA_BUNDLE_PATH : null;
            if ($caPath && is_file($caPath)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caPath);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            }
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $err) {
                error_log('[ufDeCoordenada] cURL Nominatim: ' . $err);
                return null;
            }

            $j = json_decode($raw, true);
            if (!$j) return null;

            return $this->extrairUfDeResultado($j);
        } catch (Throwable $e) {
            error_log('[ufDeCoordenada] ' . $e->getMessage());
            return null;
        }
    }

    /** Extrai UF de um array de resposta (Nominatim ou serviço interno). */
    private function extrairUfDeResultado(array $r): ?string
    {
        // address.state_code (ex: "RJ")
        $uf = $r['address']['state_code'] ?? $r['state_code'] ?? null;
        if ($uf && preg_match('/^[A-Z]{2}$/', strtoupper((string)$uf))) {
            return strtoupper((string)$uf);
        }

        // address.state (nome completo)
        $nome = $r['address']['state'] ?? $r['state'] ?? null;
        if ($nome) {
            $uf = $this->nomeEstadoParaUf((string)$nome);
            if ($uf) return $uf;
        }

        // display_name
        $display = (string)($r['display_name'] ?? '');
        if ($display !== '') {
            foreach (array_map('trim', explode(',', $display)) as $p) {
                $uf = $this->nomeEstadoParaUf($p);
                if ($uf) return $uf;
            }
        }

        return null;
    }

    /** Converte nome do estado em UF. Cobre todos os 27. */
    private function nomeEstadoParaUf(string $nome): ?string
    {
        $nome = trim($nome);
        if ($nome === '') return null;

        if (preg_match('/^[A-Z]{2}$/', strtoupper($nome))) {
            $uf = strtoupper($nome);
            $validas = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
            return in_array($uf, $validas, true) ? $uf : null;
        }

        static $mapa = [
            'acre'=>'AC','alagoas'=>'AL','amapá'=>'AP','amapa'=>'AP','amazonas'=>'AM','bahia'=>'BA',
            'ceará'=>'CE','ceara'=>'CE','distrito federal'=>'DF','espírito santo'=>'ES','espirito santo'=>'ES',
            'goiás'=>'GO','goias'=>'GO','maranhão'=>'MA','maranhao'=>'MA','mato grosso'=>'MT',
            'mato grosso do sul'=>'MS','minas gerais'=>'MG','pará'=>'PA','para'=>'PA','paraíba'=>'PB','paraiba'=>'PB',
            'paraná'=>'PR','parana'=>'PR','pernambuco'=>'PE','piauí'=>'PI','piaui'=>'PI','rio de janeiro'=>'RJ',
            'rio grande do norte'=>'RN','rio grande do sul'=>'RS','rondônia'=>'RO','rondonia'=>'RO','roraima'=>'RR',
            'santa catarina'=>'SC','são paulo'=>'SP','sao paulo'=>'SP','sergipe'=>'SE','tocantins'=>'TO',
        ];
        return $mapa[mb_strtolower($nome, 'UTF-8')] ?? null;
    }

    }
