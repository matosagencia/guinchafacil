<?php
declare(strict_types=1);

interface AddressProviderInterface
{
    public function suggest(string $q, ?float $biasLat = null, ?float $biasLng = null, ?array $bbox = null, int $limit = 8): array;
    public function reverse(float $lat, float $lng): ?array;
}
