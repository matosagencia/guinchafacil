# GuinchaFácil — contexto para IA (atualizar a cada sessão)

## Stack
PHP 8 MVC próprio, MySQL, XAMPP local, cPanel, Mercado Pago.

## Contrato canônico
docs/CONTRATOS.md (versão 2.4)

## Faixas
- A: motor, preço, pagamento, webhook
- B: auth, painéis, admin
- C: QA, testes, deploy

## Estado atual (data: ____)
- T1 motor de preço: [pronto / em andamento / bloqueado]
- T2 aplicarDestinoPagamento: ...
- Pendências do dono: lista dos 9 itens

## Arquivos quentes desta sprint
- src/Services/TarifaService.php
- src/Services/RoutingService.php
- ...

## O que NÃO fazer
- Não inventar colunas/rotas
- Não editar fora da faixa
- R2: contrato antes do código
<!-- GF-FIN1-CONTRATO-20261018 -->
### FIN.1 â€” GestÃ£o financeira e repasses (18/10/2026)

- AdoÃ§Ã£o incremental do layout Lovable de referÃªncia em `/admin/financeiro`, com sidebar `100dvh`, logotipo, ausÃªncia de topbar legado e conservaÃ§Ã£o dos filtros, KPIs, CSV, PIX jobs e aÃ§Ãµes preexistentes.
- Contrato bilateral: pagamento pelo gateway cria crÃ©dito contratual ao parceiro; pagamento diretamente ao parceiro cria obrigaÃ§Ã£o de comissÃ£o Ã  plataforma. Fechamento semanal compensa ambos por parceiro e ciclo.
- `saldo_parceiro = creditos - debitos`; saldo positivo implica repasse, zero compensaÃ§Ã£o, negativo dÃ­vida do parceiro. InadimplÃªncia apenas sobre dÃ­vida lÃ­quida vencida apÃ³s carÃªncia e conciliaÃ§Ã£o.
- Matching anelar progressivo: referÃªncia comum por faixa = maior custo de deslocamento entre elegÃ­veis da faixa. Oficinas sÃ£o responsÃ¡veis por remoÃ§Ã£o incluÃ­da conforme cadastro e oferta.
- Esta fase Ã© APENAS visual/documental; a implementaÃ§Ã£o do razÃ£o bilateral e sua migraÃ§Ã£o exigem auditoria e homologaÃ§Ã£o prÃ³prias (FIN.2+).
- Detalhes e critÃ©rios: `doc/FIN_1_GESTAO_FINANCEIRA_REPASSES.md`.