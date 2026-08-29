<?php
// File: guinchafacil/src/Controllers/SseController.php

/**
 * Server-Sent Events — substitui polling HTTP (ticket: [ESCALA] SSE)
 *
 * Endpoints:
 *   GET /sse/pedido/{id}      — status + chat + localização do guincho (cliente, guincho e admin)
 *   GET /sse/pedidos          — pedidos disponíveis para o guincho (dashboard)
 *   GET /sse/admin/pedidos    — mudanças de status/localização para pedidos visíveis no admin
 *
 * Sessão é fechada após autenticação para não bloquear outras requisições do mesmo usuário.
 */
class SseController
{
    private const MAX_DURATION   = 290;  // segundos antes de fechar (cliente deve reconectar)
    private const LOOP_SLEEP_MS  = 2000; // intervalo do loop em ms
    private const HEARTBEAT_SECS = 20;   // segundos entre heartbeats

    // ─── Helpers SSE ─────────────────────────────────────────────

    private static function sendHeaders(): void
    {
        // Achado real de 30/07/2026: mensagem de chat confirmada como
        // persistida (chat_mensagens) E emitida pelo servidor (Logger
        // mostrou "nova_mensagem emitida" no horário exato, request_id
        // correspondente à conexão do cliente) — mas nunca chegou ao
        // navegador dentro da janela do teste. As duas conexões (cliente e
        // guincho) ficaram abertas os 290s inteiros sem erro/reconexão, ou
        // seja, não foi queda de rede nem bug de autenticação: foi a
        // RESPOSTA HTTP não sendo entregue em tempo real.
        //
        // php.ini deste ambiente tem output_buffering=4096 (não "Off") —
        // PHP cria um buffer de saída IMPLÍCITO de até 4096 bytes por
        // request, e esse buffer NEM SEMPRE aparece em ob_get_level()
        // dependendo da versão/SAPI, então o `if (ob_get_level() > 0)`
        // antigo podia nunca disparar e deixar esse buffer implícito vivo
        // durante o loop inteiro — cada evento SSE (bem menor que 4096
        // bytes) ficava represado até o buffer encher ou a conexão fechar
        // (exatamente o padrão observado: tudo "chega" só no stream_close/
        // timeout). mod_deflate está desligado neste Apache, então não é
        // compressão — é buffering de verdade.
        //
        // Fechar TODOS os níveis de buffer em loop (não só um `if`) é o
        // jeito robusto de garantir que nenhum buffer implícito sobreviva,
        // independente de como o ambiente/versão do PHP contabiliza
        // ob_get_level() para o buffer criado via output_buffering do ini.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        ob_implicit_flush(true);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');  // nginx
        header('Connection: keep-alive');

        // Força os headers a saírem imediatamente, antes do primeiro evento
        // — sem isso o próprio envio dos headers podia ficar represado
        // junto com o resto até o primeiro flush() de conteúdo.
        flush();
    }

    /** Emite um evento SSE. $data deve ser serializável como JSON. */
    private static function emit(string $event, mixed $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    /** Encerra o stream com evento final. */
    private static function close(string $motivo): void
    {
        self::emit('stream_close', ['motivo' => $motivo]);
    }

    private static function pedidoStatusPayload(array $pedido): array
    {
        return [
            'id' => (int)($pedido['id'] ?? 0),
            'status' => (string)($pedido['status'] ?? ''),
            'guincho_nome' => $pedido['guincho_operador'] ?? null,
            'guincho_placa' => $pedido['guincho_placa'] ?? null,
            'guincho_tel' => $pedido['guincho_telefone'] ?? null,
            'lat_guincho' => isset($pedido['lat_guincho']) ? (float)$pedido['lat_guincho'] : null,
            'lng_guincho' => isset($pedido['lng_guincho']) ? (float)$pedido['lng_guincho'] : null,
        ];
    }

    // ─── Autenticação para SSE ────────────────────────────────────

    /** Retorna usuário autenticado ou envia evento SSE terminal. */
    private static function auth(?string $perfil = null): array
    {
        $now = time();
        $startedAt = (int)($_SESSION['_auth_started_at'] ?? $_SESSION['auth_at'] ?? 0);
        $lastActivity = (int)($_SESSION['_last_activity'] ?? $startedAt);
        $idleTimeout = defined('SESSION_IDLE_TIMEOUT') ? (int)SESSION_IDLE_TIMEOUT : 3600;
        $absoluteTimeout = defined('SESSION_ABSOLUTE_TIMEOUT') ? (int)SESSION_ABSOLUTE_TIMEOUT : 43200;

        $expired = !AuthService::isLoggedIn()
            || $startedAt <= 0
            || ($now - $lastActivity) > $idleTimeout
            || ($now - $startedAt) > $absoluteTimeout;

        if ($expired) {
            AuthService::logout();
            http_response_code(401);
            self::sendHeaders();
            self::emit('session_expired', [
                'ok' => false,
                'erro' => 'sessao_expirada',
                'mensagem' => 'Sua sessão expirou. Entre novamente para continuar.',
            ]);
            exit;
        }

        $user = AuthService::getCurrentUser();
        $tipo = (string)($user['tipo'] ?? '');
        if (!$user || ($perfil !== null && $tipo !== $perfil && $tipo !== 'admin')) {
            http_response_code(403);
            self::sendHeaders();
            self::emit('erro', ['mensagem' => 'Acesso negado']);
            exit;
        }
        return $user;
    }

    // ─── /sse/pedido/{id} ─────────────────────────────────────────

    /**
     * Transmite atualizações de um pedido em tempo real.
     * Acessível pelo cliente dono do pedido, pelo guincho atribuído e pelo admin.
     */
    public function pedido(int $id): void
    {
        $user = self::auth();

        // Valida acesso ao pedido
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido) {
            http_response_code(404);
            self::sendHeaders();
            self::emit('erro', ['mensagem' => 'Pedido não encontrado']);
            exit;
        }

        $tipo = $user['tipo'];
        $uid  = (int)$user['id'];

        if ($tipo === 'cliente' && (int)$pedido['cliente_id'] !== $uid) {
            http_response_code(403);
            self::sendHeaders();
            self::emit('erro', ['mensagem' => 'Acesso negado']);
            exit;
        }

        if ($tipo === 'guincho') {
            $guincho = Guincho::buscarPorUsuario($uid);
            if (!$guincho || (int)$pedido['guincho_id'] !== (int)$guincho['id']) {
                http_response_code(403);
                self::sendHeaders();
                self::emit('erro', ['mensagem' => 'Acesso negado']);
                exit;
            }
        }

        // Libera sessão para não bloquear outras requisições do usuário
        session_write_close();

        self::sendHeaders();
        set_time_limit(0);
        ignore_user_abort(true);

        $inicio        = time();
        $ultimoMsgId   = (int)($_GET['desde_id'] ?? 0);
        $ultimoStatus  = '';
        $ultimaLat     = null;
        $ultimaLng     = null;
        $ultimoHb      = time();

        $terminais = ['concluido', 'cancelado'];

        // Diagnóstico (30/07/2026): investigação de mensagem de chat
        // confirmada em chat_mensagens (via qa_get_chat_snapshot.php) que não
        // chegava no cliente via SSE. Sem log NENHUM lado servidor deste
        // loop, não dava pra saber se o problema era o servidor nunca
        // enxergar a mensagem nova (Chat::listarPorPedido), nunca emitir
        // (emit()/flush()), ou emitir e o cliente não processar — três
        // causas bem diferentes com o mesmo sintoma. Logs aqui + os
        // [pedidostatus][SSE] já adicionados no cliente permitem comparar os
        // dois lados pelo mesmo pedido_id/msg_id nos logs.
        Logger::log(Logger::LEVEL_INFO, 'SseController', 'pedido', 'sse',
            "Stream aberto — pedido #{$id}, tipo={$tipo}, uid={$uid}, desde_id={$ultimoMsgId}",
            ['pedido_id' => $id, 'tipo' => $tipo, 'uid' => $uid, 'desde_id' => $ultimoMsgId]);

        while (true) {
            if (connection_aborted()) {
                Logger::log(Logger::LEVEL_INFO, 'SseController', 'pedido', 'sse',
                    "Stream encerrado — connection_aborted (pedido #{$id}, tipo={$tipo}, uid={$uid})",
                    ['pedido_id' => $id, 'tipo' => $tipo, 'uid' => $uid]);
                break;
            }
            if ((time() - $inicio) >= self::MAX_DURATION) {
                Logger::log(Logger::LEVEL_INFO, 'SseController', 'pedido', 'sse',
                    'Stream encerrado — timeout de ' . self::MAX_DURATION . "s atingido (pedido #{$id}, tipo={$tipo}, uid={$uid})",
                    ['pedido_id' => $id, 'tipo' => $tipo, 'uid' => $uid]);
                self::close('timeout');
                break;
            }

            try {
                $p = Pedido::buscarPorId($id);
                if (!$p) break;

                // Evento: mudança de status
                if ($p['status'] !== $ultimoStatus) {
                    self::emit('status_update', self::pedidoStatusPayload($p));
                    $ultimoStatus = $p['status'];
                }

                // Evento: localização do guincho atualizada
                $latG = $p['lat_guincho'] !== null ? (float)$p['lat_guincho'] : null;
                $lngG = $p['lng_guincho'] !== null ? (float)$p['lng_guincho'] : null;
                if ($latG !== null && ($latG !== $ultimaLat || $lngG !== $ultimaLng)) {
                    self::emit('localizacao_guincho', ['lat' => $latG, 'lng' => $lngG]);
                    $ultimaLat = $latG;
                    $ultimaLng = $lngG;
                }

                // Evento: novas mensagens de chat
                $novas = Chat::listarPorPedido($id, $ultimoMsgId);
                foreach ($novas as $msg) {
                    self::emit('nova_mensagem', [
                        'id'           => (int)$msg['id'],
                        'mensagem'     => $msg['mensagem'],
                        'usuario_id'   => (int)$msg['usuario_id'],
                        'usuario_nome' => $msg['usuario_nome'],
                        'criado_em'    => $msg['criado_em'],
                    ]);
                    // Log correlacionável com [pedidostatus][SSE] no console do
                    // navegador: se este log existir mas o console do cliente
                    // nunca mostrar "nova_mensagem recebida id=X" pro MESMO id,
                    // o servidor emitiu certinho e o problema é 100% do lado do
                    // cliente (conexão caída/reconectando ou falha ao processar
                    // o evento). Se este log NÃO existir, o servidor nunca viu a
                    // mensagem chegar em Chat::listarPorPedido.
                    Logger::log(Logger::LEVEL_INFO, 'SseController', 'pedido', 'sse',
                        "nova_mensagem emitida — pedido #{$id}, msg_id={$msg['id']}, para tipo={$tipo}/uid={$uid}",
                        ['pedido_id' => $id, 'msg_id' => (int)$msg['id'], 'destinatario_tipo' => $tipo, 'destinatario_uid' => $uid]);
                    $ultimoMsgId = max($ultimoMsgId, (int)$msg['id']);
                }

                // Encerra stream se pedido chegou ao estado final
                if (in_array($p['status'], $terminais, true)) {
                    self::close($p['status']);
                    break;
                }

                // Heartbeat periódico para manter conexão viva
                if ((time() - $ultimoHb) >= self::HEARTBEAT_SECS) {
                    self::emit('heartbeat', ['ts' => time()]);
                    $ultimoHb = time();
                }
            } catch (Throwable $e) {
                error_log('[SseController::pedido] ' . $e->getMessage());
                Logger::exception('SseController', 'pedido', 'sse', $e, ['pedido_id' => $id, 'tipo' => $tipo, 'uid' => $uid]);
            }

            usleep(self::LOOP_SLEEP_MS * 1000);
        }
    }

    // ─── /sse/pedidos (guincho dashboard) ────────────────────────

    /**
     * Transmite novos pedidos disponíveis na área do guincho.
     */
    public function pedidosDisponiveis(): void
    {
        $user = self::auth('guincho');
        $uid  = (int)$user['id'];

        $guincho = Guincho::buscarPorUsuario($uid);
        if (!$guincho || !$guincho['aprovado']) {
            self::sendHeaders();
            self::emit('erro', ['mensagem' => 'Guincho não aprovado']);
            exit;
        }

        session_write_close();
        self::sendHeaders();
        set_time_limit(0);
        ignore_user_abort(true);

        $inicio      = time();
        $vistos      = [];      // IDs de pedidos já notificados nesta sessão SSE
        $ultimoHb    = time();
        $guinchoId   = (int)$guincho['id'];

        while (true) {
            if (connection_aborted()) break;
            if ((time() - $inicio) >= self::MAX_DURATION) {
                self::close('timeout');
                break;
            }

            try {
                // Recarrega posição atual e disponibilidade
                $g = Guincho::buscarPorId($guinchoId);
                if (!$g || !$g['disponivel']) {
                    usleep(self::LOOP_SLEEP_MS * 1000);
                    continue;
                }

                $lat  = (float)($g['lat_atual'] ?? -23.5505);
                $lng  = (float)($g['lng_atual'] ?? -46.6333);
                // §COBERTURA-RAIO-01 (05/08/2026): mesmo raio efetivo de
                // GuinchoController::montarOfertasDisponiveis — ver
                // CoberturaService.
                require_once __DIR__ . '/../Services/CoberturaService.php';
                $cfg  = Configuracao::getAll();
                $raio = CoberturaService::raioEfetivoGuincho($g, (float)($cfg['raio_maximo_km'] ?? 50));

                $pedidos = Pedido::listarAguardandoGuincho();
                foreach ($pedidos as $p) {
                    if (in_array((int)$p['id'], $vistos, true)) continue;

                    // Etapa 4 (matching por capacidade) — mesmo critério de
                    // GuinchoController::montarOfertasDisponiveis(): reboque
                    // continua visível a todo guincho aprovado; serviços novos
                    // (ON_SITE/HYBRID) exigem capacidade aprovada.
                    $attendanceMode = (string)($p['attendance_mode'] ?? 'TOWING');
                    if ($attendanceMode === 'TOWING') {
                        // Reboque só para prestador com reboque aprovado.
                        if ((int)($g['reboque_aprovado'] ?? 1) !== 1) {
                            continue;
                        }
                    } else {
                        $serviceTypeId = (int)($p['service_type_id'] ?? 0);
                        if ($serviceTypeId <= 0 || !ProviderCapability::possuiCapacidadeAprovada($guinchoId, $serviceTypeId)) {
                            continue;
                        }
                    }

                    // Etapa 15 — compatibilidade prestador × veículo (mesmo
                    // critério de GuinchoController::montarOfertasDisponiveis):
                    // esconde INELIGIBLE. Fallback conservador cobre reboque.
                    $serviceTypeIdCmp = (int)($p['service_type_id'] ?? 0);
                    if ($serviceTypeIdCmp > 0) {
                        $compat = ProviderVehicleCompatibilityService::evaluate(new CompatibilityRequest(
                            (int)$p['id'], $guinchoId, $serviceTypeIdCmp, CompatibilityRequest::OP_QUEUE_FILTER
                        ));
                        if (!$compat->allowsOffer()) {
                            continue;
                        }
                    }

                    $dist = calculateDistance($lat, $lng, (float)$p['lat_origem'], (float)$p['lng_origem']);
                    if ($dist > $raio) continue;

                    self::emit('novo_pedido', [
                        'id'              => (int)$p['id'],
                        'tipo_problema'   => $p['tipo_problema'],
                        'endereco_origem' => $p['endereco_origem'],
                        'endereco_destino'=> $p['endereco_destino'],
                        'custo_estimado'  => (float)$p['custo_estimado'],
                        'distancia_km'    => round($dist, 1),
                    ]);
                    $vistos[] = (int)$p['id'];
                }

                if ((time() - $ultimoHb) >= self::HEARTBEAT_SECS) {
                    self::emit('heartbeat', ['ts' => time()]);
                    $ultimoHb = time();
                }
            } catch (Throwable $e) {
                error_log('[SseController::pedidosDisponiveis] ' . $e->getMessage());
            }

            usleep(self::LOOP_SLEEP_MS * 1000);
        }
    }

    public function adminPedidos(): void
    {
        self::auth('admin');
        $ids = array_values(array_filter(array_map(static function (string $raw): int {
            $raw = trim($raw);
            return ctype_digit($raw) ? (int)$raw : 0;
        }, explode(',', (string)($_GET['ids'] ?? ''))), static fn(int $id): bool => $id > 0));
        $ids = array_slice(array_values(array_unique($ids)), 0, 100);

        session_write_close();
        self::sendHeaders();
        set_time_limit(0);
        ignore_user_abort(true);

        $inicio = time();
        $ultimoHb = time();
        $snapshots = [];

        while (true) {
            if (connection_aborted()) {
                break;
            }
            if ((time() - $inicio) >= self::MAX_DURATION) {
                self::close('timeout');
                break;
            }

            try {
                foreach ($ids as $pedidoId) {
                    $pedido = Pedido::buscarPorId($pedidoId);
                    if (!$pedido) {
                        continue;
                    }

                    $payload = self::pedidoStatusPayload($pedido);
                    $snapshot = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (($snapshots[$pedidoId] ?? null) === $snapshot) {
                        continue;
                    }

                    self::emit('pedido_status_update', $payload);
                    $snapshots[$pedidoId] = $snapshot;
                }

                if ((time() - $ultimoHb) >= self::HEARTBEAT_SECS) {
                    self::emit('heartbeat', ['ts' => time()]);
                    $ultimoHb = time();
                }
            } catch (Throwable $e) {
                error_log('[SseController::adminPedidos] ' . $e->getMessage());
            }

            usleep(self::LOOP_SLEEP_MS * 1000);
        }
    }
}
