<?php
// File: guinchafacil/src/Services/ChatService.php

class ChatService
{
    private $chatModel;

    public function __construct()
    {
        $this->chatModel = new Chat();
    }

    public function sendMessage(array $dados): int
    {
        return (int)$this->chatModel->enviar($dados);
    }

    public function getMessagesByPedido(int $pedidoId): array
    {
        return $this->chatModel->listarPorPedido($pedidoId);
    }
}
