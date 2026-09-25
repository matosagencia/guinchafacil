<?php

declare(strict_types=1);

require_once __DIR__ . '/../DTO/PedidoQuoteRequest.php';
require_once __DIR__ . '/../DTO/PedidoCreateRequest.php';
require_once __DIR__ . '/Pedido/ModalidadeResolver.php';
require_once __DIR__ . '/Pricing/PedidoPricingService.php';
require_once __DIR__ . '/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Veiculo.php';

final class PedidoCoreService
{
    public function __construct(
        private PDO $pdo,
        private PedidoPricingService $pricing,
        private ChargePolicyService $chargePolicy,
        private PedidoTransitionService $transition,
        private ?ModalidadeResolver $modalidadeResolver = null
    ) {
        $this->modalidadeResolver ??= new ModalidadeResolver();
    }

    public function cotar(PedidoQuoteRequest $request): array
    {
        $this->validarCoordenadas($request);
        $modalidade = $this->modalidadeResolver->resolver($request->tipoProblema, [
            'modalidade_socorro' => $request->modalidadeSocorro,
            'endereco_destino' => $request->enderecoDestino,
        ]);

        if ($modalidade === ModalidadeResolver::REBOQUE_PRANCHA && $request->enderecoDestino === '' && $request->destinoLat === null) {
            throw new InvalidArgumentException('Destino e obrigatorio para reboque.');
        }

        return $this->pricing->cotar($request->toArray() + ['modalidade_socorro' => $modalidade]);
    }

    public function criar(PedidoCreateRequest $request): int
    {
        $quote = $this->cotar($request->quote);
        $this->validarClienteVeiculo($request);

        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }

        try {
            $pedidoId = Pedido::criar([
                'cliente_id' => (int)$request->quote->clienteId,
                'veiculo_id' => (int)$request->quote->veiculoId,
                'tipo_problema' => $request->quote->tipoProblema,
                'descricao_problema' => $request->descricaoProblema,
                'lat_origem' => (float)$request->quote->localResgateLat,
                'lng_origem' => (float)$request->quote->localResgateLng,
                'endereco_origem' => $request->quote->enderecoOrigem,
                'lat_destino' => (float)($request->quote->destinoLat ?? $request->quote->localResgateLat),
                'lng_destino' => (float)($request->quote->destinoLng ?? $request->quote->localResgateLng),
                'endereco_destino' => $request->quote->enderecoDestino,
                'distancia_km' => (float)$quote['km_cobrado'],
                'custo_estimado' => (float)$quote['total'],
                'status' => 'aguardando_pagamento',
            ]);
            if (!$pedidoId) {
                throw new RuntimeException('Falha ao criar pedido.');
            }

            $this->persistirContextoPedido($pedidoId, $quote, $request);
            $this->registrarEvento($pedidoId, 'PEDIDO_CRIADO', [
                'actor_type' => $request->actorType,
                'actor_id' => $request->actorId,
                'quote' => $quote,
            ]);

            if ($request->providerId !== null && $request->providerId > 0) {
                $this->atribuirPrestador($pedidoId, $request->providerId, [
                    'actor_type' => $request->actorType,
                    'actor_id' => $request->actorId ?? 0,
                ]);
            }

            if ($started) {
                $this->pdo->commit();
            }

            return (int)$pedidoId;
        } catch (Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function atribuirPrestador(int $pedidoId, int $providerId, array $context): void
    {
        $actorType = (string)($context['actor_type'] ?? 'admin');
        $actorId = (int)($context['actor_id'] ?? 0);
        $result = $actorType === 'guincho'
            ? PedidoTransitionService::acceptByGuincho($pedidoId, $providerId, $actorId)
            : PedidoTransitionService::assignByAdmin($pedidoId, $providerId, $actorId);

        if (!$result->ok) {
            throw new RuntimeException($result->message);
        }
    }

    public function transicionar(int $pedidoId, string $event, array $context): void
    {
        if ($event === 'ORCAMENTO_RECUSADO') {
            $this->pdo->prepare("UPDATE pedidos SET status = 'recusado_solicitando_reboque', attendance_mode = 'TOWING' WHERE id = ?")
                ->execute([$pedidoId]);
            $this->registrarEvento($pedidoId, $event, $context);
            return;
        }

        $this->registrarEvento($pedidoId, $event, $context);
    }

    private function validarCoordenadas(PedidoQuoteRequest $request): void
    {
        if ($request->localResgateLat === null || $request->localResgateLng === null) {
            throw new InvalidArgumentException('Localizacao de resgate obrigatoria.');
        }
        if (!GeoService::coordenadasValidas($request->localResgateLat, $request->localResgateLng)) {
            throw new InvalidArgumentException('Coordenadas de origem fora do limite aceito.');
        }
        if ($request->destinoLat !== null && $request->destinoLng !== null && !GeoService::coordenadasValidas($request->destinoLat, $request->destinoLng)) {
            throw new InvalidArgumentException('Coordenadas de destino fora do limite aceito.');
        }
    }

    private function validarClienteVeiculo(PedidoCreateRequest $request): void
    {
        if (!$request->quote->clienteId || !$request->quote->veiculoId) {
            throw new InvalidArgumentException('Cliente e veiculo sao obrigatorios.');
        }
        $veiculo = Veiculo::buscarPorId((int)$request->quote->veiculoId);
        if (!$veiculo || (int)($veiculo['usuario_id'] ?? 0) !== (int)$request->quote->clienteId) {
            throw new InvalidArgumentException('Veiculo invalido para o cliente.');
        }
    }

    private function persistirContextoPedido(int $pedidoId, array $quote, PedidoCreateRequest $request): void
    {
        $columns = $this->columns('pedidos');
        $sets = [];
        $params = [];
        foreach ([
            'modalidade_socorro' => $quote['modalidade_socorro'] ?? null,
            'local_resgate_lat' => $request->quote->localResgateLat,
            'local_resgate_lng' => $request->quote->localResgateLng,
            'attendance_mode' => ($quote['modalidade_socorro'] ?? '') === ModalidadeResolver::SOCORRO_LOCAL ? 'ON_SITE' : 'TOWING',
            'service_type_id' => $request->serviceTypeId,
        ] as $column => $value) {
            if (isset($columns[$column])) {
                $sets[] = $column . ' = ?';
                $params[] = $value;
            }
        }
        if ($sets !== []) {
            $params[] = $pedidoId;
            $this->pdo->prepare('UPDATE pedidos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
        }

        if ($this->tableExists('pedido_financial_snapshots')) {
            $this->pdo->prepare('INSERT INTO pedido_financial_snapshots
                (pedido_id, taxa_saida, valor_km, km_cobrado, km_excedente, intermediacao, desconto_continuidade, total, moeda, pricing_version, snapshot_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([
                    $pedidoId,
                    $quote['taxa_saida'],
                    $quote['valor_km'],
                    $quote['km_cobrado'],
                    $quote['km_excedente'],
                    $quote['intermediacao_final'],
                    $quote['desconto_continuidade'],
                    $quote['total'],
                    $quote['moeda'],
                    $quote['pricing_version'],
                    json_encode($quote, JSON_UNESCAPED_UNICODE),
                ]);
        }
    }

    private function registrarEvento(int $pedidoId, string $event, array $context): void
    {
        if ($this->tableExists('pedido_eventos')) {
            $this->pdo->prepare('INSERT INTO pedido_eventos (pedido_id, evento, context_json, created_at) VALUES (?, ?, ?, NOW())')
                ->execute([$pedidoId, $event, json_encode($context, JSON_UNESCAPED_UNICODE)]);
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
                $stmt->execute([$table]);
                return (bool)$stmt->fetchColumn();
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function columns(string $table): array
    {
        $columns = [];
        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                foreach ($this->pdo->query('PRAGMA table_info(' . $table . ')') as $row) {
                    $columns[(string)$row['name']] = true;
                }
                return $columns;
            }
            foreach ($this->pdo->query('SHOW COLUMNS FROM ' . $table) as $row) {
                $columns[(string)$row['Field']] = true;
            }
        } catch (Throwable) {
        }
        return $columns;
    }
}
