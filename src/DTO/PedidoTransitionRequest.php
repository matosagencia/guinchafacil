<?php
declare(strict_types=1);

final class PedidoTransitionRequest
{
    public string $actorType;
    public int $actorId;
    public int $pedidoId;
    public ?int $guinchoId;
    public string $targetStatus;
    public array $context;

    public function __construct(
        string $actorType,
        int $actorId,
        int $pedidoId,
        string $targetStatus,
        ?int $guinchoId = null,
        array $context = []
    ) {
        $this->actorType = $actorType;
        $this->actorId = $actorId;
        $this->pedidoId = $pedidoId;
        $this->targetStatus = $targetStatus;
        $this->guinchoId = $guinchoId;
        $this->context = $context;
    }
}
