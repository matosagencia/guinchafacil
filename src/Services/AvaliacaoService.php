<?php
// File: guinchafacil/src/Services/AvaliacaoService.php

class AvaliacaoService
{
    private $avaliacaoModel;

    public function __construct()
    {
        $this->avaliacaoModel = new Avaliacao();
    }

    public function create(array $dados): int
    {
        return (int)$this->avaliacaoModel->criar($dados);
    }
}
