<?php
declare(strict_types=1);

final class PreCotacaoApiController
{
    private function json(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function ok($data): void
    {
        $this->json(['ok' => true, 'data' => $data, 'error' => null]);
    }

    private function fail(string $msg, int $status = 400): void
    {
        $this->json(['ok' => false, 'data' => null, 'error' => $msg], $status);
    }

    public function sugestoes(): void
    {
        try {
            $q = trim((string)($_GET['q'] ?? ''));
            $biasLat = isset($_GET['bias_lat']) ? (float)$_GET['bias_lat'] : null;
            $biasLng = isset($_GET['bias_lng']) ? (float)$_GET['bias_lng'] : null;
            $limit = min(10, max(1, (int)($_GET['limit'] ?? 8)));

            if (mb_strlen($q, 'UTF-8') < 3) {
                $this->ok(['items' => []]);
                return;
            }

            require_once __DIR__ . '/../Services/Address/AddressAutocompleteService.php';
            $svc = new AddressAutocompleteService();
            $items = $svc->suggest($q, $biasLat, $biasLng, $limit);
            $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            error_log('[PreCotacaoApi::sugestoes] ' . $e->getMessage());
            $this->fail('Falha ao buscar sugestoes.', 500);
        }
    }

    public function reverso(): void
    {
        try {
            $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0.0;
            $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0.0;
            if ($lat === 0.0 || $lng === 0.0) {
                $this->fail('Informe lat e lng.');
                return;
            }
            if ($lat < -34 || $lat > 5 || $lng < -74 || $lng > -28) {
                $this->fail('Coordenada fora do Brasil.');
                return;
            }
            require_once __DIR__ . '/../Services/Address/AddressAutocompleteService.php';
            $svc = new AddressAutocompleteService();
            $item = $svc->reverse($lat, $lng);
            $this->ok(['item' => $item]);
        } catch (Throwable $e) {
            error_log('[PreCotacaoApi::reverso] ' . $e->getMessage());
            $this->fail('Falha ao reverter coordenada.', 500);
        }
    }

    public function cobertura(): void
    {
        try {
            $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0.0;
            $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0.0;
            if ($lat === 0.0 || $lng === 0.0) {
                $this->fail('Informe lat e lng.');
                return;
            }

            $qtdOficinas = 0;
            try {
                require_once __DIR__ . '/../Services/DecisaoAtendimentoService.php';
                if (class_exists('DecisaoAtendimentoService')) {
                    $svc = new DecisaoAtendimentoService();
                    if (method_exists($svc, 'oficinasProximasPublico')) {
                        $qtdOficinas = count($svc->oficinasProximasPublico($lat, $lng, ''));
                    }
                }
            } catch (Throwable $e) {
                error_log('[PreCotacaoApi::cobertura::oficinas] ' . $e->getMessage());
            }

            $qtdGuinchos = 0;
            try {
                $stmt = getPDO()->query("
                    SELECT COUNT(*) FROM guinchos
                     WHERE aprovado = 1 AND disponivel = 1
                       AND lat_atual IS NOT NULL AND lng_atual IS NOT NULL
                ");
                $qtdGuinchos = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                error_log('[PreCotacaoApi::cobertura::guincho] ' . $e->getMessage());
            }

            $this->ok([
                'qtd_oficinas' => $qtdOficinas,
                'tem_guincho' => $qtdGuinchos > 0,
                'qtd_guinchos' => $qtdGuinchos,
            ]);
        } catch (Throwable $e) {
            error_log('[PreCotacaoApi::cobertura] ' . $e->getMessage());
            $this->fail('Falha ao verificar cobertura.', 500);
        }
    }

    public function triagemServicos(): void
    {
        try {
            require_once __DIR__ . '/../Models/TriagemServico.php';
            $servicos = TriagemServico::listarAtivos();
            $this->ok(['servicos' => $servicos]);
        } catch (Throwable $e) {
            error_log('[PreCotacaoApi::triagemServicos] ' . $e->getMessage());
            $this->fail('Falha ao listar servicos.', 500);
        }
    }

    public function opcoes(): void
    {
        try {
            $modo = (string)($_GET['modo'] ?? 'orientacao');
            $servico = (string)($_GET['servico'] ?? '');
            $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0.0;
            $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0.0;
            $latDest = isset($_GET['lat_destino']) ? (float)$_GET['lat_destino'] : null;
            $lngDest = isset($_GET['lng_destino']) ? (float)$_GET['lng_destino'] : null;
            if ($lat === 0.0 || $lng === 0.0) {
                $this->fail('Informe lat e lng.');
                return;
            }
            require_once __DIR__ . '/../Services/PreCotacao/PreCotacaoOpcoesService.php';
            $dados = PreCotacaoOpcoesService::montar($modo, $servico, $lat, $lng, $latDest, $lngDest);
            $this->ok($dados);
        } catch (Throwable $e) {
            error_log('[PreCotacaoApi::opcoes] ' . $e->getMessage());
            $this->fail('Falha ao montar opcoes.', 500);
        }
    }

    public function whatsapp(): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                $this->fail('Metodo nao permitido.', 405);
                return;
            }

            $token = (string)($_POST['csrf_token'] ?? '');
            $sessToken = (string)($_SESSION['_csrf_token'] ?? '');
            if ($token === '' || $sessToken === '' || !hash_equals($sessToken, $token)) {
                $this->fail('Sessao expirada.', 419);
                return;
            }

            $ctx = [
                'modo' => (string)($_POST['modo'] ?? ''),
                'servico' => (string)($_POST['servico'] ?? ''),
                'veiculo' => (string)($_POST['veiculo'] ?? ''),
                'origem_texto' => (string)($_POST['origem_texto'] ?? ''),
                'origem_lat' => (string)($_POST['origem_lat'] ?? ''),
                'origem_lng' => (string)($_POST['origem_lng'] ?? ''),
                'destino_texto' => (string)($_POST['destino_texto'] ?? ''),
            ];

            require_once __DIR__ . '/../Services/PreCotacao/WhatsAppCotacaoService.php';
            $link = WhatsAppCotacaoService::gerarLink($ctx);
            if ($link === '') {
                $this->fail('Numero de WhatsApp nao configurado.', 503);
                return;
            }
            $this->ok(['link' => $link]);
        } catch (Throwable $e) {
            error_log('[PreCotacaoApi::whatsapp] ' . $e->getMessage());
            $this->fail('Falha ao gerar link.', 500);
        }
    }
}
