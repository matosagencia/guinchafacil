# Estratégia territorial: pedra viva e pedra morta

## Decisão de produto

`pedra_viva` e `pedra_morta` são classificações estratégicas para orientar onde concentrar aquisição, prospecção, comunicação e acompanhamento de demanda. Elas não são estados de cobertura operacional.

Qualquer cliente, guincho ou oficina pode se cadastrar em qualquer localização. O cadastro não deve ser rejeitado porque a coordenada está fora de um polígono ou porque a zona está marcada como `nao_ativada`.

## O que decide a operação

O motor operacional deve considerar somente os dados reais da solicitação:

- parceiro aprovado e ativo;
- disponibilidade no momento;
- capacidade/tipo de serviço compatível;
- raio de atendimento configurado pelo parceiro;
- distância calculada entre ocorrência e parceiro;
- regras financeiras e de segurança do pedido.

O status estratégico da zona não pode aparecer como motivo para negar cadastro, ocultar parceiro elegível ou impedir uma cotação.

## Como usar as pedras no marketing

- Pedra viva: prioridade de aquisição e conversão, porque já há sinal operacional ou demanda suficiente para acelerar crescimento.
- Pedra morta: prioridade de prospecção, testes de mensagem e formação de rede; não significa “sem atendimento”.
- Sem classificação: território a pesquisar e qualificar.

As telas administrativas devem usar termos como “prioridade estratégica”, “expansão estratégica” e “sinal de demanda”. Evitar “bloqueada”, “sem chamados” ou “não atende” quando a informação vier apenas de `status_expansao`.

## Painel de decisão

A Central de Marketing deve acompanhar, por território e período:

1. cadastros de clientes, guinchos e oficinas;
2. parceiros aprovados e ativos;
3. pré-cotações e pedidos criados;
4. serviços solicitados e serviços com oferta;
5. conversão por canal e campanha;
6. custo de aquisição, receita e margem, quando houver dados financeiros válidos.

Os indicadores devem ser apresentados como evidência observada. Metas são hipóteses de planejamento e precisam ser explicitamente definidas antes de serem comparadas com resultados.

## Roadmap de aquisição

### Fase 1 — Instrumentação

Confirmar que cada cadastro e pedido pode ser associado à cidade, coordenada, parceiro, serviço e origem de campanha sem alterar a elegibilidade operacional.

### Fase 2 — Oferta

Priorizar prospecção de guinchos e oficinas nas áreas com demanda observada e baixa oferta compatível, sem fechar o cadastro em outras áreas.

### Fase 3 — Demanda

Executar campanhas locais, parcerias e conteúdo por prioridade estratégica. Medir pré-cotação, avanço no funil, pedido e conclusão.

### Fase 4 — Rebalanceamento

Reclassificar prioridades com base nos dados recentes. A mudança de pedra altera o plano de aquisição, nunca o motor de cobertura.

## Regra de manutenção

Antes de cada commit ou deploy, revisar alterações que usem `status_expansao`. Se estiverem em cadastro, matching, cotação ou criação de pedido, a alteração precisa ser rejeitada ou convertida em sinal estratégico. Validar também mojibake e o shell visual conforme `doc/AGENTE-FINAL-MOJIBAKE-TEMA.md`.
