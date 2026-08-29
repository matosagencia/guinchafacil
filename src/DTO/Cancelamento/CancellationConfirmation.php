<?php

declare(strict_types=1);

/**
 * src/DTO/Cancelamento/CancellationConfirmation.php
 * Pacote L1.6 — resultado da confirmação de cancelamento (após validar o snapshot).
 */
class CancellationConfirmation
{
    public bool $ok;
    public ?string $erro;
    public float $taxa;
    public float $estorno;
    public ?int $snapshotId;

    public function __construct(
        bool $ok,
        ?string $erro,
        float $taxa,
        float $estorno,
        ?int $snapshotId
    ) {
        $this->ok = $ok;
        $this->erro = $erro;
        $this->taxa = $taxa;
        $this->estorno = $estorno;
        $this->snapshotId = $snapshotId;
    }

    public static function falha(string $erro): self
    {
        return new self(false, $erro, 0.0, 0.0, null);
    }

    public static function sucesso(float $taxa, float $estorno, int $snapshotId): self
    {
        return new self(true, null, $taxa, $estorno, $snapshotId);
    }

    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'erro' => $this->erro,
            'taxa' => $this->taxa,
            'estorno' => $this->estorno,
            'snapshot_id' => $this->snapshotId,
        ];
    }
}
