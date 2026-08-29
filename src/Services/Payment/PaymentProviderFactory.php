<?php

declare(strict_types=1);

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once __DIR__ . '/MercadoPagoProvider.php';
require_once __DIR__ . '/PagSeguroProvider.php';

/**
 * Pacote L1.7 (pendência #33): ponto único de resolução de provider por
 * gateway, substituindo o `match ($metodo) { 'mercadopago' => ..., ... }`
 * que antes vivia hardcoded dentro de EstornoService::estornar().
 *
 * Também expõe `ativo()`/`gatewayAtivoRaw()`, que leem a chave
 * administrativa única PAYMENT_GATEWAY_ACTIVE (definida em config.php a
 * partir do .env — ver constituição do projeto, seção 16: "o cliente
 * nunca escolhe o gateway, a administração escolhe um único gateway de
 * cobrança ativo por vez").
 *
 * §GATEWAY-CENTRAL-01 (22/07): `gatewayAtivoRaw()` é agora o ÚNICO ponto
 * que lê a constante PAYMENT_GATEWAY_ACTIVE no projeto inteiro.
 * `GatewayRotationService::gatewayConfigurado()` e
 * `PagamentoController::gatewayAtivo()` foram migrados para delegar aqui
 * em vez de reimplementar `strtolower(trim(defined(...) ? ... : 'mercadopago'))`
 * cada um por conta própria — antes havia 3 leituras independentes da
 * mesma constante (funcionalmente idênticas, mas sem fonte única, o que
 * cria risco de divergência silenciosa se um dia o fallback padrão ou a
 * normalização mudar em só um dos lugares).
 */
final class PaymentProviderFactory
{
    /** @var array<string, PaymentProviderInterface> */
    private static array $instances = [];

    public static function forGateway(string $metodo): ?PaymentProviderInterface
    {
        $metodo = strtolower(trim($metodo));

        if (!isset(self::$instances[$metodo])) {
            self::$instances[$metodo] = match ($metodo) {
                'mercadopago' => new MercadoPagoProvider(),
                'pagseguro' => new PagSeguroProvider(),
                default => null,
            };
        }

        return self::$instances[$metodo];
    }

    /**
     * Valor cru (normalizado: minúsculo + trim) da chave administrativa
     * PAYMENT_GATEWAY_ACTIVE, sem considerar rotação por limite diário.
     * Fonte única — ver §GATEWAY-CENTRAL-01 acima.
     */
    public static function gatewayAtivoRaw(): string
    {
        return strtolower(trim(defined('PAYMENT_GATEWAY_ACTIVE') ? (string)PAYMENT_GATEWAY_ACTIVE : 'mercadopago'));
    }

    /** Provider correspondente à chave administrativa PAYMENT_GATEWAY_ACTIVE. */
    public static function ativo(): ?PaymentProviderInterface
    {
        return self::forGateway(self::gatewayAtivoRaw());
    }
}
