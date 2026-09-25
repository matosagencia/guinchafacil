<?php

declare(strict_types=1);

require_once __DIR__ . '/PedidoQuoteRequest.php';

final class PedidoCreateRequest
{
    public PedidoQuoteRequest $quote;
    public string $descricaoProblema;
    public ?int $actorId;
    public string $actorType;
    public ?int $providerId;
    public ?int $serviceTypeId;
    public ?string $attendanceMode;
    public array $context;

    public function __construct(array $data)
    {
        $this->quote = new PedidoQuoteRequest($data);
        $this->descricaoProblema = trim((string)($data['descricao_problema'] ?? $data['descricao'] ?? ''));
        $this->actorId = isset($data['actor_id']) ? (int)$data['actor_id'] : null;
        $this->actorType = trim((string)($data['actor_type'] ?? 'cliente'));
        $this->providerId = isset($data['provider_id']) && $data['provider_id'] !== '' ? (int)$data['provider_id'] : null;
        $this->serviceTypeId = isset($data['service_type_id']) && $data['service_type_id'] !== '' ? (int)$data['service_type_id'] : null;
        $this->attendanceMode = isset($data['attendance_mode']) ? strtoupper(trim((string)$data['attendance_mode'])) : null;
        $this->context = is_array($data['context'] ?? null) ? $data['context'] : [];
    }
}
