<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Models/Configuracao.php';
require_once __DIR__ . '/../../Models/Pagamento.php';
require_once __DIR__ . '/../../Services/Logger.php';
require_once __DIR__ . '/../../Services/AuditTrailService.php';
require_once __DIR__ . '/PaymentProviderFactory.php';

/**
 * §16 da constituição diz que a escolha do gateway é do admin, nunca do
 * cliente — isso continua verdade aqui. O que esta classe adiciona é uma
 * segunda camada SOBRE PAYMENT_GATEWAY_ACTIVE: se o gateway ativo já
 * recebeu hoje mais que o limite diário configurado pelo admin (chave
 * `gateway_rotacao_limite_diario`, padrão R$10.000), e o OUTRO gateway está
 * configurado e utilizável, o sistema passa a usar o outro automaticamente
 * para novos checkouts — sem o admin precisar trocar o .env na mão em
 * pleno horário de pico. PAYMENT_GATEWAY_ACTIVE continua sendo a fonte de
 * verdade "de repouso"; isto é só um desvio temporário, reavaliado a cada
 * checkout novo, nunca persistido como mudança permanente.
 *
 * Pedidos já criados (pagamento já registrado em `pagamentos.metodo`) não
 * são afetados — a rotação só influencia qual painel aparece/qual endpoint
 * é aceito para pedidos que AINDA vão iniciar o pagamento.
 */
final class GatewayRotationService
{
    private const CHAVE_LIMITE = 'gateway_rotacao_limite_diario';
    private const LIMITE_PADRAO = 10000.0;

    public static function limiteDiario(): float
    {
        $valor = Configuracao::get(self::CHAVE_LIMITE, (string)self::LIMITE_PADRAO);
        $limite = (float)str_replace(',', '.', (string)$valor);
        return $limite > 0 ? $limite : self::LIMITE_PADRAO;
    }

    /**
     * Gateway configurado em PAYMENT_GATEWAY_ACTIVE (sem considerar rotação).
     * §GATEWAY-CENTRAL-01: delega em PaymentProviderFactory::gatewayAtivoRaw(),
     * fonte única da leitura desta constante em todo o projeto.
     */
    public static function gatewayConfigurado(): string
    {
        return PaymentProviderFactory::gatewayAtivoRaw();
    }

    /**
     * Gateway efetivo para o PRÓXIMO checkout, já considerando rotação por
     * limite diário. Se PAYMENT_GATEWAY_ACTIVE = 'todos'/'all' (ambos
     * habilitados), a rotação não se aplica — o cliente já vê as duas
     * opções e não há "gateway único" pra rotacionar.
     *
     * @param callable $estaConfigurado function(string $gateway): bool — permite
     *        ao chamador injetar a checagem de credenciais (evita acoplar
     *        esta classe a PagamentoController).
     */
    public static function gatewayEfetivo(callable $estaConfigurado): string
    {
        $ativo = self::gatewayConfigurado();

        if (!in_array($ativo, ['mercadopago', 'pagseguro'], true)) {
            // 'todos'/'all' ou valor desconhecido — sem rotação, comportamento inalterado.
            return $ativo;
        }

        $limite = self::limiteDiario();
        $totalHoje = Pagamento::totalAprovadoHojePorGateway($ativo);

        if ($totalHoje < $limite) {
            return $ativo;
        }

        $alternativo = $ativo === 'mercadopago' ? 'pagseguro' : 'mercadopago';
        if (!$estaConfigurado($alternativo)) {
            // Limite estourado mas não há pra onde rotacionar — mantém o
            // ativo mesmo assim; é melhor aceitar pagamento acima do limite
            // do que recusar cliente por falta de gateway alternativo.
            Logger::log(Logger::LEVEL_WARN, 'GatewayRotationService', 'gatewayEfetivo', 'rotacao',
                "Limite diário ({$limite}) excedido em {$ativo} (recebido hoje: {$totalHoje}), mas {$alternativo} não está configurado — mantendo {$ativo}.",
                ['gateway_ativo' => $ativo, 'total_hoje' => $totalHoje, 'limite' => $limite]
            );
            return $ativo;
        }

        Logger::log(Logger::LEVEL_INFO, 'GatewayRotationService', 'gatewayEfetivo', 'rotacao',
            "Rotação automática: {$ativo} recebeu R$" . number_format($totalHoje, 2, ',', '.')
            . " hoje (limite R$" . number_format($limite, 2, ',', '.') . ") — novos checkouts vão para {$alternativo}.",
            ['gateway_original' => $ativo, 'gateway_efetivo' => $alternativo, 'total_hoje' => $totalHoje, 'limite' => $limite]
        );

        AuditTrailService::evento('gateway_rotacionado_automaticamente', 'GatewayRotationService', 'gatewayEfetivo', [
            'gateway_original' => $ativo,
            'gateway_efetivo'  => $alternativo,
            'total_hoje'       => $totalHoje,
            'limite'           => $limite,
        ]);

        return $alternativo;
    }
}
