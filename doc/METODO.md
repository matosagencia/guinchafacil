# Método de trabalho — defeitos em ciclo curto

**Objetivo:** substituir exploração de repositório por lista concreta de defeitos.

## Ciclo por defeito

1. O relatório aponta o defeito com `arquivo:linha`.
2. O agente lê só esse trecho (±30 linhas via `sed -n` ou equivalente).
3. Corrige o mínimo.
4. Roda **apenas o teste do defeito** (`php tests/run_all.php --only=<id>`), nunca a suíte inteira.
5. Commit pequeno com o ID: `fix(B-014): ...`.
6. O relatório é regerado; o defeito sai da lista.

## Regras de economia

- Nenhum agente "explora o repositório". Recebe lista de IDs.
- Cada agente lê no máximo **3 arquivos por defeito**. Se precisar de mais, emite `CONTRATO_PEDIDO` e para.
- Contrato (R2) só é necessário quando muda assinatura ou dado compartilhado. Correção de 404 ou view não precisa.
- Auditoria (C) recebe `git diff --stat` + resultado dos testes, só abre o diff dos arquivos que falharam.
- Cada sessão termina com 5 linhas em `doc/lanes/X.md` (feito / pendente / bloqueio).

## Ferramentas

| Ferramenta | O que faz | Quando rodar |
|---|---|---|
| `tools/route_audit.php` | Acha links órfãos e rotas sem link | Depois de adicionar/remover rotas |
| `tools/smoke_http.ps1` | Testa HTTP de todas as rotas | Antes de cada merge |
| `tests/run_all.php` | Smoke de comportamento | Depois de cada fix |
| `doc/defeitos.json` | Registro único de defeitos | Fonte de verdade para A/B/C |

## Fluxo de descoberta → correção

1. **C** roda `route_audit.php` + `smoke_http.ps1` + `run_all.php`.
2. **C** tria os resultados e escreve em `doc/defeitos.json`.
3. **A/B** pegam defeitos por faixa, corrigem, commitam.
4. **C** reexecuta e remove do JSON o que foi resolvido.