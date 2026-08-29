<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/OrderFlow/OnSiteFlowDefinition.php
// ROADMAP socorro automotivo — Fundamento 4, §5.2/§5.4 (socorro no local).

require_once __DIR__ . '/FlowDefinitionInterface.php';

class OnSiteFlowDefinition implements FlowDefinitionInterface
{
    /**
     * Novos estados (§5.2/5.4 do roadmap, nomes em pt-br para consistência
     * com o resto do schema): diagnostico_iniciado, diagnostico_concluido,
     * autorizacao_servico_pendente (Etapa 5: orçamento complementar),
     * em_execucao_servico, teste_final, conversao_reboque_pendente,
     * conversao_aprovada_cliente, preparacao_veiculo.
     *
     * Depois de `conversao_aprovada_cliente`, o pedido reentra no pipeline
     * de reboque a partir de `aguardando_guincho` (nova disputa) ou pula
     * direto para `preparacao_veiculo` quando o mesmo prestador é híbrido
     * (ver HybridFlowDefinition/Etapa 7) — as duas opções ficam abertas
     * aqui porque a decisão de qual delas usar é do serviço de conversão,
     * não da máquina de estados.
     */
    protected const NEXT = [
        'aguardando_pagamento' => ['aguardando_guincho', 'cancelado'],
        'aguardando_guincho' => ['a_caminho', 'cancelado'],
        'a_caminho' => ['no_local', 'cancelado'],
        'no_local' => ['diagnostico_iniciado', 'cancelado'],
        'diagnostico_iniciado' => ['diagnostico_concluido', 'cancelado'],
        'diagnostico_concluido' => ['autorizacao_servico_pendente', 'em_execucao_servico', 'conversao_reboque_pendente', 'cancelado'],
        'autorizacao_servico_pendente' => ['aguardando_pagamento_orcamento', 'em_execucao_servico', 'conversao_reboque_pendente', 'cancelado'],
        'aguardando_pagamento_orcamento' => ['em_execucao_servico', 'cancelado'],
        'em_execucao_servico' => ['teste_final', 'cancelado'],
        'teste_final' => ['concluido', 'conversao_reboque_pendente', 'cancelado'],
        'conversao_reboque_pendente' => ['conversao_aprovada_cliente', 'cancelado'],
        // 'aguardando_pagamento' é o caminho NÃO-híbrido real (cobrança
        // complementar do reboque — ver ConversionService::decidirConversao):
        // reaproveita o checkout/Payment Brick/webhook já existentes, que já
        // avançam aguardando_pagamento -> aguardando_guincho de forma
        // genérica e agnóstica de attendance_mode. 'aguardando_guincho'
        // continua na lista só por compatibilidade com chamadas antigas/
        // testes que ainda pulam a cobrança diretamente — não é mais o
        // caminho usado por ConversionService no fluxo não-híbrido.
        // §HIBRIDO-COMPLEMENTAR-01 (27/07/2026): 'aguardando_pagamento_reboque_hibrido'
        // é o caminho HÍBRIDO cobrando o complementar de reboque (mesmo
        // prestador continua vinculado — ver ConversionService::decidirConversao)
        // — antes desta correção o híbrido pulava direto pra preparacao_veiculo
        // sem cobrar nada. 'aguardando_guincho' aqui é o destino de DOWNGRADE:
        // se o prestador perder capacidade/aprovação entre a decisão de
        // conversão e o pagamento do complementar ser aprovado, o pedido cai
        // pra fila comum de matching em vez de travar ou seguir com prestador
        // inválido (ver PedidoTransitionService::approvePayment).
        'conversao_aprovada_cliente' => ['aguardando_pagamento', 'aguardando_pagamento_reboque_hibrido', 'aguardando_guincho', 'preparacao_veiculo', 'cancelado'],
        'aguardando_pagamento_reboque_hibrido' => ['preparacao_veiculo', 'aguardando_guincho', 'cancelado'],
        'preparacao_veiculo' => ['em_reboque', 'cancelado'],
        'em_reboque' => ['concluido', 'cancelado'],
        'concluido' => [],
        'cancelado' => [],
    ];

    public function proximosEstados(string $from): array
    {
        return static::NEXT[$from] ?? [];
    }

    public function podeTransitar(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        return in_array($to, static::NEXT[$from] ?? [], true);
    }

    public function proximoStatusPadrao(string $from): ?string
    {
        $opcoes = array_values(array_filter(
            static::NEXT[$from] ?? [],
            static fn(string $status): bool => $status !== 'cancelado'
        ));
        return $opcoes[0] ?? null;
    }
}
