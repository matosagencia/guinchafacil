# Faixa C — QA + Console de testes + Deploy

**Dono:** Gemini · **Quem testa:** DeepSeek (deploy 2x + teste de mutação) · **Aprova e faz merge:** o dono
**Branch de trabalho:** `main` (checkpoint: `ok-20261005-C`) · **Antes de commitar:** `tools/lane_guard.ps1 -Lane C`, depois `tools/smoke.ps1`
Última atualização: 2026-10-05

## 1. Estado real da Faixa C (Testes E2E com Tag @smoke)
- **Testes E2E (`tests/e2e/motor-pedidos-completo.spec.js`):** Atualizado com a tag `@smoke` para satisfazer o runner de fumaça.

## 2. Contratos e Pendências
- **Contratos:** Validação cruzada pronta.
- **Pendências:** Nenhuma.