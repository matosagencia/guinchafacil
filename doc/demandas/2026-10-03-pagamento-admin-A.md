CONTRATO_PEDIDO (A)  forma de pagamento ao fechar pedido pelo admin
===================================================================

de:        B (DeepSeek)  via dono
para:      A (ChatGPT)
data:      2026-10-03
prioridade: alta (afeta operacao diaria do admin)

## Contexto

O funil admin (Caminho 3) agora cria pedido em nome de um cliente via
/admin/pedido/novo/v2. O AdminController::pedidoCriar (arquivo sem
faixa, alterado com autorizacao do dono) hoje:

  (a) em system_mode=freeflow ou payment_required=0:
      muda status para aguardando_guincho + define expiracao +
      (opcional) atribui guincho.
  (b) em payment_required=1:
      cria registro em `pagamentos` (mercadopago) e devolve ao admin o
      link /pagamento/checkout/{id} (via flash).

Isso cobre apenas o cenario "gerar link MP". Faltam as outras formas
que a operacao real do admin usa.

## Demanda

Definir uma enum de **forma de pagamento** que o admin escolhe ao
fechar/criar o pedido. Sugestao a confirmar:

  | valor           | significado                                        |
  |-----------------|----------------------------------------------------|
  | local           | pagamento direto ao profissional no local          |
  |                 | (so faz sentido p/ assistencia ON_SITE)            |
  | link_mp         | gera link MP e o admin envia via WhatsApp          |
  | pix_direto      | cliente paga PIX na chave do GuinchaFacil;         |
  |                 | admin da baixa depois                              |
  | baixa_manual    | admin marca "pago" sem transacao digital           |
  |                 | (dinheiro, transferencia, cartao presencial)       |

Pontos que precisam de decisao da A + dono:

  1) Onde persistir a escolha. Sugestao:
     - nova coluna `forma_pagamento_escolhida` em `pedidos` (ENUM)
     - novas colunas `baixado_manualmente_por` (INT NULL) e
       `baixado_manual_em` (DATETIME NULL) para auditoria
     - Alternativa: tabela propria `pedido_formas_pagamento`. Decidir.

  2) Onde o admin escolhe. Sugestao: na tela /admin/pedido/novo/v2
     (Etapa 1, junto com cliente/veiculo), ou apos o funil (Etapa 3,
     antes do redirect final). Decidir e passar pra B implementar.

  3) Efeito em `pedidos.status`:
     - link_mp            -> permanece aguardando_pagamento
     - local/pix_direto/  -> vao direto pra aguardando_guincho? ou
       baixa_manual          status intermediario
                             `aguardando_confirmacao_admin`? Decidir.

  4) Endpoint pra gerar link WhatsApp pre-formatado. Sugestao:
     GET /admin/pedido/{id}/link-pagamento devolve JSON com
     { url, mensagem_whatsapp }  a mensagem ja formatada com nome
     do cliente + valor + URL do checkout, pronta pra wa.me/.
     Deixa o front B so montar o <a href>.

  5) Baixa manual: quem pode dar? So admin master? Precisa de senha +
     justificativa como o pedidoConcluirManual (que ja existe)?
     Decidir.

## Nao e escopo desta demanda

  - Alterar o fluxo do cliente (/cliente/pedido/novo)  continua indo
    direto pra /pagamento/checkout/{id}.
  - Alterar o webhook MP  A.md atual ja cobre.
  - Implementar PIX manual custom  PixService::reprocessar existe mas
    e outra coisa (reprocessamento, nao geracao de QR novo).

## Criterio de aceite (do lado B, quando A responder)

  - AdminController::pedidoCriar recebe o parametro de forma de
    pagamento e persiste conforme a enum decidida por A.
  - Tela de detalhe do pedido (faixa B, admin/pedidodetalhe.php) exibe
    a forma escolhida no card "Financeiro".
  - Se for link_mp, ha um botao "copiar link WhatsApp" que monta a
    mensagem pre-formatada via o endpoint da secao 4.
  - Se for baixa_manual, exige senha + justificativa e registra em
    _flash o resultado (paridade com pedidoConcluirManual).

## Arquivos provavelmente afetados

  Faixa A:
    - src/Controllers/PagamentoController.php
    - src/Models/Pedido.php
    - src/Models/Pagamento.php
    - nova migration (install/migration_forma_pagamento_v1.sql)

  Faixa B (apos A definir):
    - src/Controllers/AdminController.php (pedidoCriar)
    - src/Views/admin/pedidodetalhe.php
    - src/Views/admin/pedidonovo_etapa1.php (se escolha for na Etapa 1)

  Compartilhado:
    - index.php (se rota nova  o endpoint da secao 4)