<?php
declare(strict_types=1);

// File: guinchafacil/src/DTO/Triage/TriageRequest.php
// ROADMAP socorro automotivo — Fundamento 3 (triagem do cliente).

final class TriageRequest
{
    public string $symptomCode;
    /** @var array<string, string> respostas da pergunta 2 (dinâmica), chave => valor */
    public array $respostas;

    public function __construct(string $symptomCode, array $respostas = [])
    {
        $this->symptomCode = $symptomCode;
        $this->respostas = $respostas;
    }
}
