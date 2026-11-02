<?php
// File: guinchafacil/src/Controllers/SseController.php

/**
 * Server-Sent Events - substitui polling HTTP (ticket: [ESCALA] SSE)
 *
 * Endpoints:
 *   GET /sse/pedido/{id}      - status + chat + localiza��o do guincho (cliente, guincho e admin)
 *   GET /sse/pedidos          - pedidos dispon�veis para o guincho (dashboard)
 *   GET /sse/admin/pedidos    - mudan�as de status/localiza��o para pedidos vis�veis no admin
 *
 * Sess�o � fechada ap�s autentica��o para n�o bloquear outras requisi��es do mesmo usu�rio.
 */
class SseController
{
    private const MAX_DURATION   = 290;  // segundos antes de fechar (cliente deve reconectar)
    private const LOOP_SLEEP_MS  = 2000; // intervalo do loop em ms
    private const HEARTBEAT_SECS = 20;   // segundos entre heartbeats

    // ??? Helpers SSE ?????????????????????????????????????????????

    private static function sendHeaders(): void
    {
        // Achado real de 30/07/2026: mensagem de chat confirmada como
        // persistida (chat_mensagens) E emitida pelo servidor (Logger
        // mostrou "nova_mensagem emitida" no hor�rio exato, request_id
        // correspondente � conex�o do cliente) - mas nunca chegou ao
        // navegador dentro da janela do teste. As duas conex�es (cliente e
        // guincho) ficaram abertas os 290s inteiros sem erro/reconex�o, ou
        // seja, n�o foi queda de rede nem bug de autentica��o: foi a
        // RESPOSTA HTTP n�o sendo entregue em tempo real.
        //
        // php.ini deste ambiente tem output_buffering=4096 (n�o "Off") -
        // PHP cria um buffer de sa�da IMPL�CITO de at� 4096 bytes por
        // request, e esse buffer NEM SEMPRE aparece em ob_get_level()
        // dependendo da vers�o/SAPI, ent�o o `if (ob_get_level() > 0)`
        // antigo podia nunca disparar e deixar esse buffer impl�cito vivo
        // durante o loop inteiro - cada evento SSE (bem menor que 4096
        // bytes) ficava represado at� o buffer encher ou a conex�o fechar
        // (exatamente o padr�o observado: tudo "chega" s� no stream_close/
        // timeout). mod_deflate est� desligado neste Apache, ent�o n�o �
        // compress�o - � buffering de verdade.
        //
        // Fechar TODOS os n�veis de buffer em loop (n�o s� um `if`) � o
        // jeito robusto de garantir que nenhum buffer impl�cito sobreviva,
        // independente de como o ambiente/vers�o do PHP contabiliza
        // ob_get_level() para o buffer criado via output_buffering do ini.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        ob_implicit_flush(true);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');  // nginx
        header('Connection: keep-alive');

        // For�a os headers a sa�rem imediatamente, antes do primeiro evento
        // - sem isso o pr�prio envio dos headers podia ficar represado
        // junto com o resto at� o primeiro flush() de conte�do.
        flush();
    }

    /** Emite um evento SSE. $data deve ser serializ�vel como JSON. */
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

    // ??? Autentica��o para SSE ????????????????????????????????????

    /** Retorna usu�rio autenticado ou envia evento SSE terminal. */
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
                'mensagem' => 'Sua sess�o expirou. Entre novamente para continuar.',
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

    // ??? /sse/pedido/{id} ?????????????????????????????????????????

    /**
     * Transmite atualiza��es de um pedido em tempo real.
     * Acess�vel pelo cliente dono do pedido, pelo guincho atribu�do e pelo admin.
     */
    public function pedido(int $id): void
    {
        $user = self::auth();

        // Valida acesso ao pedido
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido) {
            http_response_code(404);
            self::sendHeaders();
            self::emit('erro', ['mensagem' => 'Pedido n�o encontrado']);
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

        // Libera sess�o para n�o bloquear outras requisi��es do usu�rio
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

        // Diagn�stico (30/07/2026): investiga��o de mensagem de chat
        // confirmada em chat_mensagens (via qa_get_chat_snapshot.php) que n�o
        // chegava no cliente via SSE. Sem log NENHUM lado servidor deste
        // loop, n�o dava pra saber se o problema era o servidor nunca
        // enxergar a mensagem nova (Chat::listarPorPedido), nunca emitir
        // (emit()/flush()), ou emitir e o cliente n�o processar - tr�s
        // causas bem diferentes com o mesmo sintoma. Logs aqui + os
        // [pedidostatus][SSE] j� adicionados no cliente permitem comparar os
        // dois lados pelo mesmo pedido_id/msg_id nos logs.
        Logger::log(Logger::LEVEL_INFO, 'SseController', 'pedido', 'sse',
            "Stream aberto - pedido #{$id}, tipo={$tipo}, uid={$uid}, desde_id={$ultimoMsgId}",
            ['pedido_id' => $id, 'tipo' => $tipo, 'uid' => $uid, 'desde_id' => $ultimoMsgId]);

        while (true) {
            if (connection_aborted()) {
                Logger::log(Logger::LEVEL_INFO, 'SseController', 'pedido', 'sse',
                    "Stream encerrado - connection_aborted (pedido #{$id}, tipo={$tipo}, uid={$uid})",
                    ['pedido_id' => $id, 'tipo' => $tipo, 'uid' => $uid]);
                break;
            }
            if ((time() - $inicio) >= self::MAX_DURATION) {
                Logger::log(Logger::LEVEL_INFO, 'SseController', 'pedido', 'sse',
                    'Stream encerrado - timeout de ' . self::MAX_DURATION . "s atingido (pedido #{$id}, tipo={$tipo}, uid={$uid})",
                    ['pedido_id' => $id, 'tipo' => $tipo, 'uid' => $uid]);
                self::close('timeout');
                break;
            }

            try {
                $p = Pedido::buscarPorId($id);
                if (!$p) break;

                // Evento: mudan�a de status
                if ($p['status'] !== $ultimoStatus) {
                    self::emit('status_update', self::pedidoStatusPayload($p));
                    $ultimoStatus = $p['status'];
                }

                // Evento: localiza��o do guincho atualizada
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
                    // Log correlacion�vel com [pedidostatus][SSE] no console do
                    // navegador: se este log existir mas o console do cliente
                    // nunca mostrar "nova_mensagem recebida id=X" pro MESMO id,
                    // o servidor emitiu certinho e o problema � 100% do lado do
                    // cliente (conex�o ca�da/reconectando ou falha ao processar
                    // o evento). Se este log N�O existir, o servidor nunca viu a
                    // mensagem chegar em Chat::listarPorPedido.
                    Logger::log(Logger::LEVEL_INFO, 'SseController', 'pedido', 'sse',
                        "nova_mensagem emitida - pedido #{$id}, msg_id={$msg['id']}, para tipo={$tipo}/uid={$uid}",
                        ['pedido_id' => $id, 'msg_id' => (int)$msg['id'], 'destinatario_tipo' => $tipo, 'destinatario_uid' => $uid]);
                    $ultimoMsgId = max($ultimoMsgId, (int)$msg['id']);
                }

                // Encerra stream se pedido chegou ao estado final
                if (in_array($p['status'], $terminais, true)) {
                    self::close($p['status']);
                    break;
                }

                // Heartbeat peri�dico para manter conex�o viva
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

    // ??? /sse/pedidos (guincho dashboard) ????????????????????????

    /**
     * Transmite novos pedidos dispon�veis na �rea do guincho.
     */
    public function pedidosDisponiveis(): void
    {
        $user = self::auth('guincho');
        $uid  = (int)$user['id'];

        $guincho = Guincho::buscarPorUsuario($uid);
        if (!$guincho || !$guincho['aprovado']) {
            self::sendHeaders();
            self::emit('erro', ['mensagem' => 'Guincho n�o aprovado']);
            exit;
        }

        session_write_close();
        self::sendHeaders();
        set_time_limit(0);
        ignore_user_abort(true);

        $inicio      = time();
        $vistos      = [];      // IDs de pedidos j� notificados nesta sess�o SSE
        $ultimoHb    = time();
        $guinchoId   = (int)$guincho['id'];

        while (true) {
            if (connection_aborted()) break;
            if ((time() - $inicio) >= self::MAX_DURATION) {
                self::close('timeout');
                break;
            }

            try {
                // Recarrega posi��o atual e disponibilidade
                $g = Guincho::buscarPorId($guinchoId);
                if (!$g || !$g['disponivel']) {
                    usleep(self::LOOP_SLEEP_MS * 1000);
                    continue;
                }

                $lat  = (float)($g['lat_atual'] ?? -23.5505);
                $lng  = (float)($g['lng_atual'] ?? -46.6333);
                // �COBERTURA-RAIO-01 (05/08/2026): mesmo raio efetivo de
                // GuinchoController::montarOfertasDisponiveis - ver
                // CoberturaService.
                require_once __DIR__ . '/../Services/CoberturaService.php';
                $cfg  = Configuracao::getAll();
                $raio = CoberturaService::raioEfetivoGuincho($g, (float)($cfg['raio_maximo_km'] ?? 50));

                $pedidos = Pedido::listarAguardandoGuincho();
                foreach ($pedidos as $p) {
                    if (in_array((int)$p['id'], $vistos, true)) continue;

                    // Etapa 4 (matching por capacidade) - mesmo crit�rio de
                    // GuinchoController::montarOfertasDisponiveis(): reboque
                    // continua vis�vel a todo guincho aprovado; servi�os novos
                    // (ON_SITE/HYBRID) exigem capacidade aprovada.
                    $attendanceMode = (string)($p['attendance_mode'] ?? 'TOWING');
                    if ($attendanceMode === 'TOWING') {
                        // Reboque s� para prestador com reboque aprovado.
                        if ((int)($g['reboque_aprovado'] ?? 1) !== 1) {
                            continue;
                        }
                    } else {
                        $serviceTypeId = (int)($p['service_type_id'] ?? 0);
                        if ($serviceTypeId <= 0 || !ProviderCapability::possuiCapacidadeAprovada($guinchoId, $serviceTypeId)) {
                            continue;
                        }
                    }

                    // Etapa 15 - compatibilidade prestador � ve�culo (mesmo
                    // crit�rio de GuinchoController::montarOfertasDisponiveis):
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
