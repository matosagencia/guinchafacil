# Estratégia de aquisição GuinchaFácil

Este documento define a aquisição como operação mensurável. Nenhum canal deve ser considerado eficaz apenas por gerar visitas; a fonte de verdade é a conversão registrada no funil e, quando aplicável, no pedido.

## 1. Clientes

- SEO local: páginas por cidade e serviço, com conteúdo útil e cobertura real.
- Google Business Profile: informações locais atualizadas e link rastreável para o funil.
- Conteúdo de emergência veicular: guias para pane, bateria, pneu, destino e pagamento.
- QR codes em parceiros: cada material recebe campanha, parceiro e origem próprios.
- Programa de indicação: código ou link rastreável, com conversão vinculada ao pedido.
- Frotas: páginas e materiais específicos para demanda recorrente.
- WhatsApp: recuperação de abandono e fallback quando a cobertura não existe; não substitui o fluxo de cotação.

Eventos mínimos: `landing_view`, `service_view`, `quote_started`, `quote_completed`, `quote_unavailable`, `whatsapp_fallback`, `order_created`.

## 2. Parceiros operacionais

Perfis: guincho e oficina.

- Abordagem local priorizada por cidade e demanda sem oferta.
- Centros automotivos, eventos e associações de reparadores como fontes de relacionamento.
- Indicação entre oficinas e guinchos como canal de expansão da rede.
- Benefício de entrada por região somente quando aprovado pelo administrador.
- Campanha “primeiros parceiros da cidade” com período, cidade e critérios explícitos.

O prospecto entra na fila auditável. A mensagem é revisada por uma pessoa antes do contato.

## 3. Parceiros de distribuição

Segmento único de distribuição, com subsegmentos rastreáveis:

- estacionamentos e postos;
- frotas e locadoras;
- corretores e seguradoras;
- empresas de rastreamento;
- concessionárias.

Formatos: convênio, link rastreável, QR code e, para parceiros maiores, hipótese de white-label. White-label não deve ser vendido como disponível antes de existir contrato, autenticação, atribuição e suporte definidos.

## 4. Contrato de rastreamento

Toda campanha deve registrar:

- `utm_source`, `utm_medium`, `utm_campaign`, `utm_content` e `utm_term`;
- cidade e serviço de destino;
- parceiro distribuidor, quando houver;
- código ou link de indicação;
- consentimento quando houver comunicação posterior.

Os eventos devem ser deduplicáveis e não podem armazenar dados de pagamento sensíveis. A conversão final precisa apontar para o pedido ou para o lead anonimizado correspondente.

## 5. KPIs

- Cliente: cotação concluída / sessão iniciada; pedido criado / cotação concluída; recuperação por WhatsApp / abandono elegível.
- Operacional: contatos profissionais válidos; resposta positiva; homologações; primeiro pedido por parceiro.
- Distribuição: cliques rastreados; leads; pedidos convertidos; receita atribuída; taxa de indicação por parceiro.

Período, cidade, segmento e fonte devem acompanhar cada relatório. Não usar visita ou mensagem enviada como sinônimo de aquisição.

## 6. Guardrails

- Não enviar mensagens automaticamente sem revisão.
- Não criar contatos ou fontes públicas inventadas.
- Não criar páginas locais sem conteúdo ou cobertura verificável.
- Não prometer prazo, preço mínimo ou disponibilidade que o sistema não consiga provar.
- Não fazer deploy de campanhas ou alterações financeiras sem teste local, migration explícita e rollback documentado.
