<?php

declare(strict_types=1);

final class PedidoQuoteRequest
{
    public ?int $clienteId;
    public ?int $veiculoId;
    public string $tipoProblema;
    public ?float $localResgateLat;
    public ?float $localResgateLng;
    public string $enderecoOrigem;
    public ?float $destinoLat;
    public ?float $destinoLng;
    public string $enderecoDestino;
    public ?string $modalidadeSocorro;
    public ?int $forcarPrestadorId;
    public ?string $forcarCanal;
    public float $distanciaKm;
    public ?string $categoriaVeiculo;
    public bool $prioridade;
    public ?int $cidadeId;
    public bool $continuidade;

    public function __construct(array $data)
    {
        $this->clienteId = isset($data['cliente_id']) ? (int)$data['cliente_id'] : (isset($data['clienteId']) ? (int)$data['clienteId'] : null);
        $this->veiculoId = isset($data['veiculo_id']) ? (int)$data['veiculo_id'] : (isset($data['veiculoId']) ? (int)$data['veiculoId'] : null);
        $this->tipoProblema = trim((string)($data['tipo_problema'] ?? $data['tipoProblema'] ?? 'outro'));
        $this->localResgateLat = self::floatOrNull($data['local_resgate_lat'] ?? $data['localResgateLat'] ?? $data['lat_origem'] ?? null);
        $this->localResgateLng = self::floatOrNull($data['local_resgate_lng'] ?? $data['localResgateLng'] ?? $data['lng_origem'] ?? null);
        $this->enderecoOrigem = trim((string)($data['endereco_origem'] ?? $data['enderecoOrigem'] ?? ''));
        $this->destinoLat = self::floatOrNull($data['destino_lat'] ?? $data['destinoLat'] ?? $data['lat_destino'] ?? null);
        $this->destinoLng = self::floatOrNull($data['destino_lng'] ?? $data['destinoLng'] ?? $data['lng_destino'] ?? null);
        $this->enderecoDestino = trim((string)($data['endereco_destino'] ?? $data['enderecoDestino'] ?? ''));
        $this->modalidadeSocorro = isset($data['modalidade_socorro']) ? strtoupper(trim((string)$data['modalidade_socorro'])) : null;
        $this->forcarPrestadorId = isset($data['forcar_prestador_id']) ? (int)$data['forcar_prestador_id'] : null;
        $this->forcarCanal = isset($data['forcar_canal']) ? trim((string)$data['forcar_canal']) : null;
        $this->distanciaKm = max(0.0, (float)($data['distancia_km'] ?? $data['distanciaKm'] ?? 0));
        $this->categoriaVeiculo = isset($data['categoria_veiculo']) ? trim((string)$data['categoria_veiculo']) : null;
        $this->prioridade = !empty($data['prioridade']);
        $this->cidadeId = isset($data['cidade_id']) && $data['cidade_id'] !== '' ? (int)$data['cidade_id'] : null;
        $this->continuidade = !empty($data['continuidade']);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float)$value : null;
    }
}
