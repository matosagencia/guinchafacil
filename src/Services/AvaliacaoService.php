<?php
// File: guinchafacil/src/Services/AvaliacaoService.php

require_once __DIR__ . '/../Models/Avaliacao.php';

class AvaliacaoService
{
    private $avaliacaoModel;

    public function __construct()
    {
        $this->avaliacaoModel = new Avaliacao();
    }

    public function create(array $dados): int
    {
        return (int)Avaliacao::criar(
            (int)($dados['pedido_id'] ?? 0),
            (int)($dados['cliente_id'] ?? 0),
            (int)($dados['guincho_id'] ?? 0),
            (int)($dados['estrelas'] ?? 0),
            (string)($dados['comentario'] ?? '')
        );
    }

    public static function avaliar(int $pedidoId, int $clienteId, int $guinchoId, int $estrelas, string $comentario = ''): int|false
    {
        if (Avaliacao::jaAvaliou($pedidoId, $clienteId)) {
            return false;
        }

        return Avaliacao::criar($pedidoId, $clienteId, $guinchoId, $estrelas, $comentario);
    }
}
