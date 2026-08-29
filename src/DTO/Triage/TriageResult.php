<?php
declare(strict_types=1);

// File: guinchafacil/src/DTO/Triage/TriageResult.php
// ROADMAP socorro automotivo — Fundamento 3 (triagem do cliente), §4.3.

final class TriageResult
{
    public const RECOMMENDED_SERVICE      = 'RECOMMENDED_SERVICE';
    public const ALTERNATIVE_SERVICES     = 'ALTERNATIVE_SERVICES';
    public const SAFETY_RISK              = 'SAFETY_RISK';
    public const TOWING_REQUIRED          = 'TOWING_REQUIRED';
    public const MANUAL_REVIEW_REQUIRED   = 'MANUAL_REVIEW_REQUIRED';

    public string $resultado;
    public ?string $recommendedServiceCode;
    /** @var string[] */
    public array $alternativeServiceCodes;
    public bool $safetyRisk;
    public string $explicacao;
    public string $ruleVersion;

    public function __construct(
        string $resultado,
        ?string $recommendedServiceCode,
        array $alternativeServiceCodes,
        bool $safetyRisk,
        string $explicacao,
        string $ruleVersion
    ) {
        $this->resultado = $resultado;
        $this->recommendedServiceCode = $recommendedServiceCode;
        $this->alternativeServiceCodes = $alternativeServiceCodes;
        $this->safetyRisk = $safetyRisk;
        $this->explicacao = $explicacao;
        $this->ruleVersion = $ruleVersion;
    }

    public function toArray(): array
    {
        return [
            'resultado' => $this->resultado,
            'recommended_service_code' => $this->recommendedServiceCode,
            'alternative_service_codes' => $this->alternativeServiceCodes,
            'safety_risk' => $this->safetyRisk,
            'explicacao' => $this->explicacao,
            'rule_version' => $this->ruleVersion,
        ];
    }
}
