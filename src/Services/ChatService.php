<?php
// File: guinchafacil/src/Services/ChatService.php

require_once __DIR__ . '/../Models/Chat.php';

/**
 * Pacote L1.9 — ponto único de validação/paginação/idempotência do chat.
 * Antes, os controllers chamavam Chat::enviar()/listarPorPedido() direto,
 * duplicando (com pequenas divergências) a validação de tamanho entre
 * ClienteController e GuinchoController. Agora ambos passam por aqui.
 */
class ChatService
{
    public const MENSAGEM_MAX_LEN = 1000;
    public const PAGINA_MAX = 200;

    private $chatModel;

    public function __construct()
    {
        $this->chatModel = new Chat();
    }

    /**
     * @return array{ok:bool, erro:?string, id:?int}
     */
    public function sendMessage(array $dados): array
    {
        $pedidoId = (int)($dados['pedido_id'] ?? 0);
        $usuarioId = (int)($dados['usuario_id'] ?? 0);
        $mensagem = trim((string)($dados['mensagem'] ?? ''));
        $idempotencyKey = isset($dados['idempotency_key']) ? trim((string)$dados['idempotency_key']) : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }
        if ($idempotencyKey !== null) {
            // Nunca confia em qualquer coisa vinda do client sem forma controlada:
            // limita tamanho e caracteres, evita abuso da coluna/índice.
            $idempotencyKey = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $idempotencyKey), 0, 64);
            if ($idempotencyKey === '') {
                $idempotencyKey = null;
            }
        }

        if ($pedidoId <= 0 || $usuarioId <= 0) {
            return ['ok' => false, 'erro' => 'Dados inválidos.', 'id' => null];
        }
        if ($mensagem === '') {
            return ['ok' => false, 'erro' => 'Mensagem vazia.', 'id' => null];
        }
        if (mb_strlen($mensagem) > self::MENSAGEM_MAX_LEN) {
            return ['ok' => false, 'erro' => 'Mensagem excede o tamanho máximo de ' . self::MENSAGEM_MAX_LEN . ' caracteres.', 'id' => null];
        }

        $id = Chat::enviar($pedidoId, $usuarioId, $mensagem, $idempotencyKey);
        if (!$id) {
            return ['ok' => false, 'erro' => 'Não foi possível enviar a mensagem.', 'id' => null];
        }

        return ['ok' => true, 'erro' => null, 'id' => (int)$id];
    }

    /**
     * @return array{mensagens:array, desde_id:int}
     */
    public function getMessagesByPedido(int $pedidoId, int $desdeId = 0, int $limite = self::PAGINA_MAX): array
    {
        $limite = max(1, min(self::PAGINA_MAX, $limite));
        $mensagens = $this->chatModel::listarPorPedido($pedidoId, $desdeId);
        if (count($mensagens) > $limite) {
            $mensagens = array_slice($mensagens, -$limite);
        }
        return $mensagens;
    }

    public function marcarLidas(int $pedidoId, int $usuarioId): void
    {
        Chat::marcarLidas($pedidoId, $usuarioId);
    }
}
