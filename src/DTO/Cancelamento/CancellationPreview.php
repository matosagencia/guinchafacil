<?php

declare(strict_types=1);

/**
 * src/DTO/Cancelamento/CancellationPreview.php
 * Pacote L1.6 — resultado do cálculo de preview de cancelamento, já persistido
 * como cancelamento_snapshots. O ator só confirma referenciando snapshot_id.
 */
class CancellationPreview
{
    public bool $pode;
    public ?string $motivoBloqueio;
    public float $taxa;
    public float $estornoPrevisto;
    public ?int $snapshotId;
    public ?string $snapshotHash;
    public ?string $expiresAt;
    public string $formulaVersion;
    public array $factors;
    public string $porQuality;

    public function __construct(
        bool $pode,
        ?string $motivoBloqueio,
        float $taxa,
        float $estornoPrevisto,
        ?int $snapshotId,
        ?string $snapshotHash,
        ?string $expiresAt,
        string $formulaVersion,
        array $factors = [],
        string $porQuality = 'unknown'
    ) {
        $this->pode = $pode;
        $this->motivoBloqueio = $motivoBloqueio;
        $this->taxa = $taxa;
        $this->estornoPrevisto = $estornoPrevisto;
        $this->snapshotId = $snapshotId;
        $this->snapshotHash = $snapshotHash;
        $this->expiresAt = $expiresAt;
        $this->formulaVersion = $formulaVersion;
        $this->factors = $factors;
        $this->porQuality = $porQuality;
    }

    public function toArray(): array
    {
        return [
            'pode' => $this->pode,
            'motivo_bloqueio' => $this->motivoBloqueio,
            'taxa' => $this->taxa,
            'estorno_previsto' => $this->estornoPrevisto,
            'snapshot_id' => $this->snapshotId,
            'snapshot_hash' => $this->snapshotHash,
            'expires_at' => $this->expiresAt,
            'formula_version' => $this->formulaVersion,
            'factors' => $this->factors,
            'por_quality' => $this->porQuality,
        ];
    }
}
