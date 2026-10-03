# Faixa C — QA + Console de testes + Deploy

**Dono:** Gemini · **Quem testa:** DeepSeek (deploy 2x + teste de mutação) · **Aprova e faz merge:** o dono
**Branch de trabalho:** `main` (checkpoint: `ok-20261003-C`) · **Antes de commitar:** `tools/lane_guard.ps1 -Lane C`, depois `tools/smoke.ps1`
Última atualização: 2026-10-03

## 1. Estado real da Faixa C (Funil Admin & Probes Ajax)
- **Motor de Execução (`TestesController.php`):** Suporte completo a probes do tipo `ajax`, avaliando `expect_json_ok` e `expect_flash_regex`.
- **Console SVG (`index.php`):** Reestruturado com os nós exatos da Seção 5.3 do plano B→C (`auth`, `admin_ctx`, `modal_cli`, `modal_vei`, `title_swap`, `ped_criar`, `link_gerado`, `criar_idem`).
- **Cenários JSON (`tests/scenarios/*.json`):** 7 cenários criados.

## 2. Contratos e Pendências
- **Contratos:** Endpoints de teste e ingestão operacionais.
- **Pendências:** Nenhuma. Pronto para a bateria final de testes de mutação M1–M6.