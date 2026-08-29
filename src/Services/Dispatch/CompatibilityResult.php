<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Dispatch/CompatibilityResult.php
// ROADMAP socorro automotivo — Etapa 15 (compatibilidade prestador × veículo).
//
// Resultado de três estados (não é booleano): ELIGIBLE / REQUIRES_CONFIRMATION
// / INELIGIBLE. allowsOffer() = pode aparecer na fila (ELIGIBLE ou
// REQUIRES_CONFIRMATION); isEligible() = pode ser aceito direto (só ELIGIBLE).

final class CompatibilityResult
{
    public const ELIGIBLE = 'ELIGIBLE';
    public const REQUIRES_CONFIRMATION = 'REQUIRES_CONFIRMATION';
    public const INELIGIBLE = 'INELIGIBLE';

    private string $status;
    /** @var string[] códigos DSP-CMP-* */
    private array $reasonCodes;
    /** @var string[] mensagens legíveis para o prestador */
    private array $warnings;

    /**
     * @param string[] $reasonCodes
     * @param string[] $warnings
     */
    public function __construct(string $status, array $reasonCodes = [], array $warnings = [])
    {
        $this->status = $status;
        $this->reasonCodes = $reasonCodes;
        $this->warnings = $warnings;
    }

    public static function eligible(array $reasonCodes = ['DSP-CMP-020'], array $warnings = []): self
    {
        return new self(self::ELIGIBLE, $reasonCodes, $warnings);
    }

    public static function requiresConfirmation(array $reasonCodes, array $warnings = []): self
    {
        return new self(self::REQUIRES_CONFIRMATION, $reasonCodes, $warnings);
    }

    public static function ineligible(array $reasonCodes, array $warnings = []): self
    {
        return new self(self::INELIGIBLE, $reasonCodes, $warnings);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @return string[] */
    public function getReasonCodes(): array
    {
        return $this->reasonCodes;
    }

    public function getPrimaryReasonCode(): string
    {
        return $this->reasonCodes[0] ?? 'DSP-CMP-020';
    }

    /** @return string[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function isEligible(): bool
    {
        return $this->status === self::ELIGIBLE;
    }

    public function allowsOffer(): bool
    {
        return in_array($this->status, [self::ELIGIBLE, self::REQUIRES_CONFIRMATION], true);
    }
}
