<?php

declare(strict_types=1);



// File: guinchafacil/src/Services/OrderFlow/OnSiteFlowDefinition.php

// ROADMAP socorro automotivo — Fundamento 4, §5.2/§5.4 (socorro no local).



require_once __DIR__ . '/FlowDefinitionInterface.php';



class OnSiteFlowDefinition implements FlowDefinitionInterface

{

    /**

     * Novos estados (�5.2/5.4 do roadmap, nomes em pt-br para consist�ncia

     * com o resto do schema): diagnostico_iniciado, diagnostico_concluido,

     * autorizacao_servico_pendente (Etapa 5: or�amento complementar),

     * em_execucao_servico, teste_final, conversao_reboque_pendente,

     * conversao_aprovada_cliente, preparacao_veiculo.

     *

     * Depois de `conversao_aprovada_cliente`, o pedido reentra no pipeline

     * de reboque a partir de `aguardando_guincho` (nova disputa) ou pula

     * direto para `preparacao_veiculo` quando o mesmo prestador � h�brido

     * (ver HybridFlowDefinition/Etapa 7) - as duas op��es ficam abertas

     * aqui porque a decis�o de qual delas usar � do servi�o de convers�o,

     * n�o da m�quina de estados.

     */

    protected const NEXT = [

        'aguardando_pagamento' => ['aguardando_guincho', 'cancelado'],

        'aguardando_guincho' => ['a_caminho', 'cancelado'],

        'a_caminho' => ['no_local', 'cancelado'],

        'no_local' => ['diagnostico_iniciado', 'cancelado'],

        'diagnostico_iniciado' => ['diagnostico_concluido', 'cancelado'],

        'diagnostico_concluido' => ['autorizacao_servico_pendente', 'em_execucao_servico', 'conversao_reboque_pendente', 'cancelado'],

        'autorizacao_servico_pendente' => ['decisao_reboque_pendente', 'saida_oficina_pendente', 'aguardando_pagamento_orcamento', 'em_execucao_servico', 'conversao_reboque_pendente', 'recusado_solicitando_reboque', 'cancelado'],

        'decisao_reboque_pendente' => ['aguardando_pagamento_orcamento', 'conversao_reboque_pendente', 'recusado_solicitando_reboque', 'cancelado'],

        'saida_oficina_pendente' => ['autorizacao_servico_pendente', 'conversao_reboque_pendente', 'recusado_solicitando_reboque', 'cancelado'],

        'recusado_solicitando_reboque' => ['aguardando_guincho', 'aguardando_pagamento', 'cancelado'],

        'aguardando_pagamento_orcamento' => ['em_execucao_servico', 'cancelado'],

        'em_execucao_servico' => ['teste_final', 'cancelado'],

        'teste_final' => ['concluido', 'conversao_reboque_pendente', 'cancelado'],

        'conversao_reboque_pendente' => ['conversao_aprovada_cliente', 'cancelado'],

        // 'aguardando_pagamento' � o caminho N�O-h�brido real (cobran�a

        // complementar do reboque - ver ConversionService::decidirConversao):

        // reaproveita o checkout/Payment Brick/webhook j� existentes, que j�

        // avan�am aguardando_pagamento -> aguardando_guincho de forma

        // gen�rica e agn�stica de attendance_mode. 'aguardando_guincho'

        // continua na lista s� por compatibilidade com chamadas antigas/

        // testes que ainda pulam a cobran�a diretamente - n�o � mais o

        // caminho usado por ConversionService no fluxo n�o-h�brido.

        // §HIBRIDO-COMPLEMENTAR-01 (27/07/2026): 'aguardando_pagamento_reboque_hibrido'

        // � o caminho H�BRIDO cobrando o complementar de reboque (mesmo

        // prestador continua vinculado - ver ConversionService::decidirConversao)

        // - antes desta corre��o o h�brido pulava direto pra preparacao_veiculo

        // sem cobrar nada. 'aguardando_guincho' aqui � o destino de DOWNGRADE:

        // se o prestador perder capacidade/aprova��o entre a decis�o de

        // convers�o e o pagamento do complementar ser aprovado, o pedido cai

        // pra fila comum de matching em vez de travar ou seguir com prestador

        // inv�lido (ver PedidoTransitionService::approvePayment).

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

