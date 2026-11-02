# Agente de QA do funil e checkout

## Regra de entrega

Este agente deve ser executado antes de qualquer commit ou deploy relacionado ao funil. A entrega só pode ser considerada validada quando o lint, os contratos de pagamento, a varredura de mojibake e os testes autenticados aplicáveis estiverem registrados. Falhas de infraestrutura, gateway ou webhook devem permanecer separadas de falhas de layout e de regra de negócio.

## Higiene de produção

O deploy envia código e assets; não deve sobrescrever o banco de produção com dados locais de teste. Quando houver migração de dados, usar uma lista explícita: manter somente o usuário operacional `soreservas@gmail.com`, configurações de sistema confirmadas e catálogo/serviços necessários à operação. Usuários de teste, pedidos de teste, pagamentos de teste e registros de parceiros locais não devem ser enviados sem exportação aprovada e backup verificável.

Execute `tools/agents/gf-funil-checkout-qa.ps1` antes de commit ou deploy que toque no funil, pagamentos, triagem, checkout ou shells dessas telas.

```powershell
.\tools\agents\gf-funil-checkout-qa.ps1
$env:ADMIN_EMAIL = 'admin@teste.local'
$env:ADMIN_PASS = 'teste123'
.\tools\agents\gf-funil-checkout-qa.ps1 -E2E
```

O agente valida PHP, os contratos de `pago_na_chegada` e `pago_agora`, mojibake e, quando solicitado, o E2E autenticado do admin.

## Invariantes do produto

- O funil público, do cliente e do admin usam a mesma sequência e o mesmo motor de triagem.
- A pré-fase do admin seleciona cliente e veículo; se o veículo já está definido, a etapa de veículo deve ser pulada.
- A Etapa 03 mostra serviços de assistência oferecidos por oficinas elegíveis. Reboque de automóvel, motocicleta e utilitário é decisão de reboque, não problema da Etapa 03.
- Serviços de múltiplas oficinas elegíveis devem ser unidos sem exibir o nome de uma oficina como se fosse a única oferta.
- `pago_na_chegada` significa dinheiro no local, cria pagamento pendente e deixa o pedido aguardando atendimento.
- `pago_agora` significa cliente já pago, registra baixa manual aprovada e não abre checkout.
- `online` exige gateway compatível e Payment Brick no checkout; aprovação real exige resposta do provedor e estado final persistido.
- Cabeçalho, rodapé e shell correto devem permanecer nas telas públicas e nos backoffices; mojibake é regressão.
- Pedra viva/morta é estratégica e não pode bloquear cadastro, cotação ou matching operacional.

## Histórico de falhas já encontradas

- `pre-cotacao/aceitar` com erro 500 e perda de sessão: o contexto deve atravessar registro, veículo e checkout via triage session.
- Fase 6 sem avanço após escolha de pagamento: destino financeiro precisava ser obrigatório e validado no servidor.
- Dinheiro confundido com “já pago”: labels foram reforçados e `pago_agora` passou a exigir confirmação explícita.
- Pagamento online com provedor divergente ou e-mail inválido: validar gateway ativo, payer e snapshot antes de chamar o provedor.
- Checkout fora do shell, barras pretas, header/footer ausentes e mojibake: restaurar o shell compartilhado antes de corrigir aparência local.
- Serviços de oficina incompletos: usar a cesta dinâmica do catálogo e a união das oficinas elegíveis.
- Mapa pode falhar por `curl_init()` ausente no PHP local; isso deve ser tratado como dependência de infraestrutura, não mascarado como falha de criação do pedido.

## Critério de parada

Não declarar o funil corrigido apenas por HTTP 200, lint ou tela renderizada. Registrar a etapa, payload, pedido/evento/pagamento persistido e, no online, a resposta efetiva do gateway. Se o E2E autenticado não puder rodar, reportar o bloqueio e não afirmar validação completa.
