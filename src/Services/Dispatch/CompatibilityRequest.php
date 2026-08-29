<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Dispatch/CompatibilityRequest.php
// ROADMAP socorro automotivo — Etapa 15 (compatibilidade prestador × veículo).

final class CompatibilityRequest
{
    public const OP_QUEUE_FILTER = 'QUEUE_FILTER';
    public const OP_OFFER_CREATION = 'OFFER_CREATION';
    public const OP_ORDER_ACCEPTANCE = 'ORDER_ACCEPTANCE';
    public const OP_ADMIN_ASSIGNMENT = 'ADMIN_ASSIGNMENT';
    public const OP_CONVERSION_TO_TOWING = 'CONVERSION_TO_TOWING';

    public int $orderId;
    public int $providerId;
    public int $serviceTypeId;
    public string $operation;
    public string $requestId;

    public function __construct(
        int $orderId,
        int $providerId,
        int $serviceTypeId,
        string $operation,
        ?string $requestId = null
    ) {
        $this->orderId = $orderId;
        $this->providerId = $providerId;
        $this->serviceTypeId = $serviceTypeId;
        $this->operation = $operation;
        $this->requestId = $requestId ?? bin2hex(random_bytes(8));
    }
}
