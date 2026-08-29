<?php
// File: guinchafacil/src/Services/IncidenteService.php

require_once __DIR__ . '/../Models/Incidente.php';

class IncidenteService {
    /**
     * Cria um novo incidente e o associa a um especialista disponível
     */
    public static function criarIncidente(array $dados): int|false
    {
        try {
            $dados['status'] = $dados['status'] ?? 'procurando_especialista';
            return Incidente::criar($dados);
        } catch (Exception $e) {
            error_log("IncidenteService::criarIncidente: " . $e->getMessage());
            return false;
        }
    }
}
