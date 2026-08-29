<?php
declare(strict_types=1);

final class PedidoTransitionResult
{
    public bool $ok;
    public ?string $error;
    public ?array $pedido;
    public array $context;

    private function __construct(bool $ok, ?string $error, ?array $pedido, array $context)
    {
        $this->ok = $ok;
        $this->error = $error;
        $this->pedido = $pedido;
        $this->context = $context;
    }

    public static function success(array $pedido, array $context = []): self
    {
        return new self(true, null, $pedido, $context);
    }

    public static function failure(string $error, array $context = []): self
    {
        return new self(false, $error, null, $context);
    }
}
