# Rodada de agentes do marketing e backoffice

Esta divisão é lógica dentro do Codex: não há subagentes independentes disponíveis nesta sessão. Todo trabalho continua sob validação do agente principal.

## Leituras comuns obrigatórias

- `doc/INDICE-AUTORIDADE-DOCUMENTAL.md` — precedência e protocolo de conflito.

- `doc/CONTRATOS.md`
- `doc/MARKETING-POSICIONAMENTO-2026-11-03.md`
- `doc/guinchafacil_backoffice_ux_lovable.md`
- `doc/AGENTE-FUNIL-CHECKOUT-QA.md`
- `skills/gf-funil-checkout-qa/SKILL.md`
- `src/Views/admin/central_operacional.php`
- `src/Views/admin/dashboard.php`
- `src/Views/components/admin_nav_operacional.php`

## Agente A — arquitetura e dados

Responsável por controllers, services, migrations, contratos, tabelas e integração de dados.

Skills: `gf-funil-checkout-qa`, `analytics`, `revops`.

Obrigatório: verificar compatibilidade de tipos/FK, idempotência, checksum de migration e não alterar produção.

## Agente B — frontend e shell

Responsável por views, layout, navegação, responsividade, acessibilidade e consistência visual.

Skills: `gf-funil-checkout-qa`, `optimize-page`, `seo-audit`, `ai-seo`.

Obrigatório: comparar com `dashboard.php` e `central_operacional.php`; preservar `gf-f18c-root`, sidebar, `bo-body`, `bo-header` e `bo-content`; executar PHP lint e auditoria mobile.

## Agente C — QA e release

Responsável por testes severos, auditoria de regressão, documentação e pré-commit.

Skills: `gf-funil-checkout-qa`, `broken-links`, `analytics`.

Obrigatório: executar o gate do funil, validar todas as rotas de marketing, conferir migrations no QA local e bloquear commit se houver parse error, drift não explicado ou template fora do shell.

## Contrato de passagem

Nenhum agente declara pronto sem evidência. A rodada final só pode gerar um commit consolidado após A, B e C apresentarem lint, testes e diff revisado.
