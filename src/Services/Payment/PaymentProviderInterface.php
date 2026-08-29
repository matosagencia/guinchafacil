<?php

declare(strict_types=1);

/**
 * Pacote L1.7 (pendência #33): interface comum para os gateways de
 * cobrança suportados pela plataforma.
 *
 * Contexto: hoje a lógica de cada gateway (Mercado Pago / PagSeguro)
 * está espalhada em PagamentoController (criação de cobrança),
 * WebhookController (confirmação) e EstornoService (estorno), sempre
 * acoplada via `match ($metodo) { 'mercadopago' => ..., 'pagseguro' => ... }`.
 * A operação de ESTORNO é a única que hoje já tem uma superfície 100%
 * uniforme entre os dois gateways (mesma assinatura, mesmo formato de
 * retorno), então é o primeiro corte real de extração — sem reescrever
 * checkout/webhook às cegas, que teriam efeitos colaterais muito maiores
 * e não podem ser validados sem rodar o PHPUnit real do usuário.
 *
 * `criarCobranca()`/`processarWebhook()` ficam documentados aqui como
 * próximo passo (ver `PaymentProviderFactory`), mas a extração deles
 * exige mover PagamentoController::iniciarMercadoPago()/iniciarPagSeguro()
 * e WebhookController::mercadoPago()/pagSeguro() — trabalho maior, deixado
 * para uma iteração separada e explicitamente sinalizada.
 */
interface PaymentProviderInterface
{
    /** Identificador do gateway, igual ao valor gravado em pagamentos.metodo. */
    public function nome(): string;

    /**
     * Estorna (total ou parcialmente) um pagamento aprovado neste gateway.
     *
     * @param string     $idExterno   ID do pagamento no gateway (com ou sem
     *                                prefixo mp_/ps_ — cada provider remove
     *                                o próprio prefixo).
     * @param float|null $valorParcial Valor a estornar; null = estorno total.
     * @return array{sucesso: bool, erro: string|null}
     */
    public function estornar(string $idExterno, ?float $valorParcial = null): array;

    /**
     * Checkout transparente (§CTP-01, ver PagamentoController): processa o
     * pagamento DIRETO na API do gateway, sem redirect — o comprador nunca
     * sai da nossa página. `$dados` chega já validado pelo controller
     * (pedido pertence ao cliente logado, status aguardando_pagamento,
     * valor confere com custo_estimado).
     *
     * Campos esperados em $dados (nem todos usados por todo provider/método):
     *   pedidoId (int), valor (float), descricao (string), payerEmail (string),
     *   paymentMethodId (string) — ex: 'visa'/'master'/'pix'/'bolbradesco' (MP)
     *                              ou 'creditCard'/'boleto' (PagSeguro),
     *   token (?string) — token do cartão tokenizado no browser,
     *   parcelas (int), issuerId (?string), docTipo (string), docNumero (string),
     *   idempotencyKey (string), senderHash (?string) — só PagSeguro.
     *
     * @return array{
     *   sucesso: bool,
     *   status: 'aprovado'|'pendente'|'recusado'|'erro',
     *   idExterno: ?string,
     *   detalhe: array<string,mixed>,
     *   erro: ?string
     * }
     */
    public function criarPagamento(array $dados): array;
}
