# Índice de autoridade documental

Este é o ponto de entrada obrigatório para qualquer agente Codex que trabalhe no GuinchaFácil.

## Ordem de precedência

1. `doc/CONTRATOS.md` — contrato canônico de produto, dados, preço, pagamento e estados.
2. `doc/DECISOES.md` — decisões explícitas do proprietário e resolução de ambiguidades.
3. `doc/AGENTES-CODEX.md` — escopos, limites e protocolo de passagem entre A, B e C.
4. `doc/lanes/A.md`, `doc/lanes/B.md`, `doc/lanes/C.md` — estado e pendências por faixa.
5. `doc/AGENTE-FUNIL-CHECKOUT-QA.md` — gate obrigatório do funil e checkout.
6. `doc/CONTEXTO-PRODUTO-MARKETING.md`, `doc/ESTRATEGIA-AQUISICAO.md` e `doc/BACKLOG-TECNICO-MARKETING.md` — marketing e aquisição.
7. Documentos de módulo — somente dentro do escopo do módulo e sem contrariar os itens anteriores.

## Comunicação entre agentes

- O agente que vai editar um arquivo deve declarar o escopo, ler os documentos acima e registrar o contrato de entrada/saída.
- Se o arquivo estiver sendo trabalhado por outro agente, a alteração fica bloqueada até haver passagem registrada.
- Nenhum agente pode alterar contrato, migration, estado, endpoint, preço ou pagamento silenciosamente.
- Divergência deve virar bloqueio documentado, não uma adaptação local.
- Toda passagem deve informar arquivos, testes, resultado, riscos e próximo responsável.
- O agente C valida o diff consolidado; não existe commit por agente.

## Arquivos arquivados

Backups de contratos e rascunhos DeepSeek foram movidos para `_archive/docs-obsoletos-20261103`. Eles não são fonte de instrução e não devem ser lidos como contrato vigente.

## Regra de release

Somente o proprietário aprova o commit final. O deploy exige gate local, migration conferida, SHA do commit e confirmação do comportamento remoto.
