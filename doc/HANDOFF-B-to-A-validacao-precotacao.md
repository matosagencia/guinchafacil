# Handoff B → A · Validação client-side em `/pre-cotacao` + fluxo unificado §23

**De:** DeepSeek (Faixa B)
**Para:** ChatGPT (Faixa A)
**Data original:** 2026-10-03
**Última atualização:** 2026-10-15 (§23 unificação dos funis)
**Escopo:** `/pre-cotacao` público + `/registro/funil` + checkout MP

---

## 1. Referência: validadores da Faixa B

Já implementados em `src/Views/admin/pedidonovo_etapa1.php` (faixa B):

- `validarEmail(v)` — regex simples
- `validarCPF(v)` — 11 dígitos, DV correto, rejeita `11111111111`
- `validarTelefone(v)` — 10 ou 11 dígitos, DDD 11-99, celular começa com 9
- `fmtCPF(v)` / `fmtTel(v)` — máscaras

Server-side equivalentes: `AdminController::validarCpfBr()` e `::validarTelefoneBr()`.

**Estado:** esses validadores foram **replicados inline** em `registro_funil_etapas.php` (view nova, §23). Não há módulo `br-validators.js` compartilhado — decisão registrada em DECISOES.

---

## 2. Diagnóstico de `/pre-cotacao` (atualizado)

**Mudou desde 03/10.** O fluxo real hoje é:
- `/pre-cotacao` **não** captura contato (email/CPF/telefone)
- O contato migrou para **`/registro/funil`** (2 etapas)
- `/checkout/cliente` (B) coleta o pagador
- `/pagamento/checkout/{id}` (A) renderiza o Brick MP com dados pré-injetados

**Conclusão:** os validadores de contato **têm onde ser aplicados** agora — em `/registro/funil`.

---

## 3. ROTA_PEDIDA — `validacao_precotacao_v1` (atualizada)

```markdown
ROTA_PEDIDA: dono
Contexto: fluxo unificado §23 — 3 entradas (Admin/Cliente/Pré-cotação) consomem o mesmo motor.

Rotas novas:
  GET  /registro/funil  → AuthController::registroFunilForm (B)
  POST /registro/funil  → AuthController::registroFunil     (B)

Arquivos:
  - src/Views/public/registro_funil_etapas.php (novo, B)
  - src/Controllers/AuthController.php          (B)
  - index.php                                    (dono)