# `docs/HANDOFF-B-to-A-validacao-precotacao.md`

Salve como:

```
C:\xampp\htdocs\guinchafacil\docs\HANDOFF-B-to-A-validacao-precotacao.md
```

Comando PowerShell pronto (grava com UTF-8 sem BOM, sem tocar em outros arquivos):

```powershell
$path = 'C:\xampp\htdocs\guinchafacil\docs\HANDOFF-B-to-A-validacao-precotacao.md'
if (Test-Path -LiteralPath $path) {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    Copy-Item -LiteralPath $path -Destination "$path.bak-$stamp" -Force
    Write-Host "[backup] $path.bak-$stamp"
}
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$content = @'
<<COLE O CONTEUDO DO BLOCO ABAIXO>>
'@
[System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
Write-Host "[write] $path"
```

Ou simplesmente crie o arquivo `.md`, abra em UTF-8 (VS Code/Notepad++ → UTF-8 sem BOM) e cole o bloco abaixo.

---

## Conteúdo completo

```markdown
# Handoff B → A · Validação client-side em `/pre-cotacao` (déficit)

**De:** DeepSeek (Faixa B)
**Para:** ChatGPT (Faixa A)
**Data:** 2026-10-03
**Escopo:** `/pre-cotacao` público + componentes JS compartilhados
**Motivo:** o usuário sinalizou que o estilo de validação recém-implementado no modal admin **deve ser levado para o funil público**, que hoje está deficitário.

**Estado:** ABERTO — aguardando (i) decisão do humano sobre dono do funil público, (ii) decisão sobre módulo compartilhado `br-validators.js`, (iii) confirmação de escopo de A.

---

## 1. Referência: o que existe hoje na Faixa B

Implementado em `src/Views/admin/pedidonovo_etapa1.php` (Faixa B), sessão 2 de 2026-10-03:

```js
function validarEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(v || '').trim());
}
function validarCPF(v) {
    var cpf = String(v || '').replace(/\D/g, '');
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    for (var t = 9; t < 11; t++) {
        var soma = 0;
        for (var i = 0; i < t; i++) soma += parseInt(cpf.charAt(i), 10) * ((t + 1) - i);
        var dig = ((10 * soma) % 11) % 10;
        if (parseInt(cpf.charAt(t), 10) !== dig) return false;
    }
    return true;
}
function validarTelefone(v) {
    var d = String(v || '').replace(/\D/g, '');
    if (d.length < 10 || d.length > 11) return false;
    var ddd = parseInt(d.substring(0, 2), 10);
    if (ddd < 11 || ddd > 99) return false;
    if (d.length === 11 && d.charAt(2) !== '9') return false;
    return true;
}
function fmtCPF(v) { /* máscara 000.000.000-00 */ }
function fmtTel(v) { /* máscara (21) 99999-9999 */ }
```

Equivalente **server-side** em `AdminController::validarCpfBr()` e `AdminController::validarTelefoneBr()` (Faixa B).

### 1.1 — Extensão já aplicada por B (Faixa B, 2026-10-03)

`AuthController::validarDadosCliente()` foi endurecido com o mesmo `validarTelefoneBr()` (patch `fix-B-auth-telefone.ps1`). Isso cobre `/registro/cliente`, `/registro/guincho` e `/registro/especialista`, que já são Faixa B.

---

## 2. Diagnóstico — o que `/pre-cotacao` captura (e o que não captura)

Auditoria dos três arquivos relevantes:

### 2.1 — `src/Views/public/pre-cotacao.php` (legado, `$funilV2On=false`)

Inputs do form:
- `localizacao` (texto livre, maxlength 220)
- `numero_origem` (texto, inputmode numérico, maxlength 20)
- `tipo_problema` (hidden, preenchido por cards)
- `veiculo_pode_mover` (hidden, preenchido por cards)
- `categoria` (hidden, cards de veículo)
- `destino` (texto livre, maxlength 220)
- `numero_destino` (texto, inputmode numérico, maxlength 20)

**Nenhum campo de e-mail, telefone ou CPF.** Nada de contato.

### 2.2 — `src/Views/public/partials/_precotacao_funil.php` (v2, `$funilV2On=true`)

Estágios: `endereco → modo → veiculo → sintoma → opcoes → destino → cotacao`.

O estágio final (`#stage-cotacao`) tem **apenas um botão de submit**. Os hidden fields são:
- `lat_origem`, `lng_origem`, `localizacao`, `numero_origem`
- `lat_destino`, `lng_destino`, `destino`, `numero_destino`
- `categoria`, `tipo_problema`, `decisao_atendimento`

**Nenhum campo de contato.** Nada para `validarEmail`/`validarCPF`/`validarTelefone` validarem.

### 2.3 — `src/Controllers/AuthController::preCotacao()` (server-side)

Recebe:
- `lat_origem`, `lng_origem`, `numero_origem`, `localizacao`
- `lat_destino`, `lng_destino`, `numero_destino`, `destino`
- `tipo_problema`, `decisao_atendimento`, `categoria`

Valida:
- CSRF (`validateCSRFToken`)
- Rate limit (8s entre chamadas)
- Lat/lng via `filter_var(FILTER_VALIDATE_FLOAT)` + range Brasil
- Geocodificação fallback via `GeocodingService`
- `categoria` contra `$categorias` enum ✓
- `tipo_problema` contra `$serviceMap` enum ✓
- `requires_destination` via `ServiceType::requiresDestination` ✓

**Não valida**:
- `localizacao` — aceita string vazia ou 1 char; só limite de 500 no `substr` final
- `numero_origem` / `numero_destino` — aceita qualquer string, inclusive letras
- `destino` — mesmo problema

### 2.4 — Conclusão

**Os validadores de contato (email/CPF/telefone) que o modal admin usa NÃO TÊM ONDE SER APLICADOS em `/pre-cotacao`** — porque `/pre-cotacao` não captura contato. O déficit real do `/pre-cotacao` é outro: falta validação de **endereço e número**.

O contato só é capturado depois, em:
- `/registro/cliente` (Faixa B) — `AuthController::registroCliente()`
- `/checkout/cliente` (Faixa B) — `CheckoutController::salvarCliente()` (A)

O item 1 do diagnóstico da sessão anterior confirma isto.

---

## 3. ROTA_PEDIDA — `validacao_precotacao_v1`

```markdown
ROTA_PEDIDA: A · validacao_precotacao_v1

Contexto:
    O usuario sinalizou que o estilo de validacao do modal admin
    (client + server, com mascara) deve valer tambem para /pre-cotacao
    publico. O diagnostico da Faixa B (secoes 2.1 a 2.4 deste doc)
    concluiu que /pre-cotacao NAO captura email/telefone/CPF em
    nenhum estagio — portanto os validadores de contato nao tem onde
    ser aplicados ali. O que /pre-cotacao captura e endereco/destino/
    numero/categoria.

Pedido a A (Faixa A):
    1. Auditar src/Views/public/pre-cotacao.php +
       src/Views/public/partials/_precotacao_funil.php +
       public/assets/js/public-pre-cotacao-flow.js.
    2. Aplicar validacao client-side + server-side nos campos de
       TEXTO do funil publico:
        - localizacao: >= 5 chars, <= 220 (limite do form)
        - destino:     >= 5 chars, <= 220 (limite do form)
        - numero_origem / numero_destino: 1-6 chars,
          apenas digitos ou "s/n" (case insensitive)
        - categoria: enum estrito (ja existe server-side via $categorias)
        - tipo_problema: enum estrito (ja existe via $serviceMap)
    3. Se a decisao humana for criar
       public/assets/js/components/br-validators.js (ver secao 4),
       A consome o modulo para os helpers de contato onde fizer
       sentido (ex.: /checkout/cliente se migrar para Faixa A no futuro).

Nao bloqueia B:
    B ja endureceu AuthController::validarDadosCliente() com
    validarTelefoneBr() — mesmo estilo do modal admin. Este patch
    de A e complementar, para os campos de endereco do proprio
    /pre-cotacao.

Arquivos envolvidos (Faixa A, nao tocar por B):
    src/Views/public/pre-cotacao.php
    src/Views/public/partials/_precotacao_funil.php
    public/assets/js/public-pre-cotacao-flow.js

    ATENCAO — CONFUSOES DE DONO:
    - src/Controllers/AuthController.php  contem preCotacaoForm()/
      preCotacao() que servem o funil publico MAS AuthController e
      Faixa B no protocolo. A decidir com o humano se esta parte
      migra para A ou se permanece em B.
    - public/assets/js/components/address-picker.js e consumido
      pelo admin (B) e pelo publico (A). Dono nao declarado em
      docs/lanes.json. Pedir decisao humana.

Decisao pendente do humano:
    Confirmar se o funil /pre-cotacao inteiro (view + controller)
    e Faixa A, ou se so o public-pre-cotacao-flow.js e A enquanto
    AuthController::preCotacao* permanece em B. Enquanto nao
    decidido, B nao edita nada em /pre-cotacao.
```

---

## 4. Proposta: módulo compartilhado `br-validators.js`

**Componente novo sem dono claro em `docs/lanes.json`.**

### 4.1 — Contrato `br-validators_v1` (proposto)

```
Arquivo:    public/assets/js/components/br-validators.js
Global:     window.BrValidators
API:
    validarEmail(string): bool
    validarCPF(string): bool          // aceita com ou sem máscara
    validarTelefone(string): bool     // aceita com ou sem máscara
    fmtCPF(string): string            // 000.000.000-00
    fmtTel(string): string            // (21) 99999-9999

Compatibilidade: IIFE, sem deps, ~60 LOC, Chrome/Firefox/Edge/Safari.
Versionamento:   <script src=".../br-validators.js?v=YYYYMMDD-1">
CSP:             self — sem inline, sem eval.
```

### 4.2 — Regras

- **R1.** CPF válido → 11 dígitos, não todos iguais, DV correto.
- **R2.** Telefone válido → 10 ou 11 dígitos, DDD 11–99, celular 11 dígitos começa com 9.
- **R3.** `fmt*` não altera o conteúdo se entrada inválida (trunca no máximo 11 dígitos).
- **R4.** Nunca lança exceção com entrada `null`/`undefined`/`number`.

### 4.3 — Consumidores previstos

- **B** (Faixa B): modal admin `pedidonovo_etapa1.php` — refactor opcional, não bloqueia.
- **A** (Faixa A): `public-pre-cotacao-flow.js` (+ views que precisem).
- **B** (Faixa B): `/registro/cliente`, `/checkout/cliente` quando B2 entrar.

### 4.4 — Ação requerida do humano

Autorizar criação do arquivo e adicionar em `docs/lanes.json`:

```json
"shared": ["public/assets/js/components/br-validators.js"]
```

**Alternativa (não recomendada):** A duplica inline em `public-pre-cotacao-flow.js`; B duplica nas views de contato. Custo: 3 fontes de verdade para CPF/tel/email.

---

## 5. Tarefas para A

### A.1 — Diagnóstico adicional (obrigatório antes de codar)

- Arquivos: `src/Views/public/pre-cotacao.php`, `src/Views/public/partials/_precotacao_funil.php`, `public/assets/js/public-pre-cotacao-flow.js`.
- Ação: confirmar o inventário da seção 2 e anotar qualquer campo de texto que B possa ter deixado passar.
- Evidência: trecho de código mostrando os inputs.

### A.2 — Aguardar decisão do humano sobre dono de `/pre-cotacao`

- Se `/pre-cotacao` é A → A edita direto.
- Se `/pre-cotacao` é B (view+controller) → B edita, mas precisa `ROTA_PEDIDA` de A para o JS do funil.
- Enquanto não decidido: **ninguém edita**.

### A.3 — Aguardar decisão do humano sobre `br-validators.js`

- Se aprovado → A consome em `public-pre-cotacao-flow.js`.
- Se não aprovado → A duplica inline e marca dívida técnica em `docs/DECISOES.md`.

### A.4 — Aplicar `validacao_precotacao_v1` (seção 3)

Quando decidido o dono, aplicar os filtros de texto no funil público.

### A.5 — Não esquecer o outro handoff

- `docs/HANDOFF-B-to-A.md` §4 A.4 — `link` preenchido no ramo idempotente do `Pagamento::aplicarDestinoPagamento`. **Este documento não substitui aquele.**

---

## 6. Critérios de aceite

- [ ] `/pre-cotacao` rejeita `localizacao` com menos de 5 caracteres.
- [ ] `/pre-cotacao` rejeita `numero_origem`/`numero_destino` com letras (exceto `s/n`).
- [ ] Server-side (PHP) valida os mesmos campos — não só o JS.
- [ ] Se `br-validators.js` aprovado: existe uma única implementação, consumida por B e A.
- [ ] Se `br-validators.js` rejeitado: dívida técnica registrada em `docs/DECISOES.md` com justificativa.
- [ ] Cobertura de mutação em C documentada em `docs/HANDOFF-B-to-C.md` §10.4.

---

## 7. O que B não pode fazer

B **não edita** `src/Views/public/pre-cotacao.php`, `src/Views/public/partials/_precotacao_funil.php`, `public/assets/js/public-pre-cotacao-flow.js` nem `public/assets/js/components/address-picker.js`. Faixa A / shared.

Se A decidir que um trecho cabe a B, A publica `ROTA_PEDIDA:` neste arquivo e B responde com o patch — **somente após o humano atualizar `docs/lanes.json`**.

`AuthController::preCotacaoForm()` e `preCotacao()` são Faixa B, mas fazem parte do funil `/pre-cotacao` do escopo A — por isso **B não edita sem confirmação explícita**.

---

## 8. Referência cruzada

- Implementação de referência (B): `src/Views/admin/pedidonovo_etapa1.php` — funções `validarEmail`, `validarCPF`, `validarTelefone`, `fmtCPF`, `fmtTel`.
- Helpers server-side (B): `AdminController::validarCpfBr()`, `AdminController::validarTelefoneBr()`, `AuthController::validarTelefoneBr()`.
- Correlatos:
  - `docs/HANDOFF-B-to-A.md` (link de pagamento online).
  - `docs/HANDOFF-B-to-C.md` §10 (cenários extra de validação).
  - `docs/lanes/B.md` §11 e §12 (estado da Faixa B).

---

## 9. Changelog deste documento

| Data | Autor | Mudança |
| --- | --- | --- |
| 2026-10-03 | B | Criação — handoff inicial sobre reuso de validadores em `/pre-cotacao`. |
| 2026-10-03 (sessão 3) | B | Diagnóstico completo (seções 2.1–2.4) + `ROTA_PEDIDA` para A + contrato `br-validators_v1` + `fix-B-auth-telefone.ps1` complementar. |
```

---

## Rollback

```powershell
# Restaurar backup anterior (se existia):
Copy-Item 'docs\HANDOFF-B-to-A-validacao-precotacao.md.bak-<stamp>' `
          'docs\HANDOFF-B-to-A-validacao-precotacao.md' -Force

# Ou simplesmente remover (se não existia antes):
Remove-Item 'docs\HANDOFF-B-to-A-validacao-precotacao.md' -Force
```

---

## Resumo do que foi entregue

Este `.md` consolida em um só arquivo:

1. **§1** — referência dos validadores do modal admin (B) + extensão já aplicada no `AuthController` (`fix-B-auth-telefone.ps1`).
2. **§2** — diagnóstico honesto: `/pre-cotacao` **não captura contato**, o déficit real é em endereço/número.
3. **§3** — `ROTA_PEDIDA: validacao_precotacao_v1` com escopo, arquivos e decisão pendente do humano.
4. **§4** — contrato `br-validators_v1` para o módulo compartilhado (aguardando autorização humana + `docs/lanes.json`).
5. **§5–§7** — tarefas de A, critérios de aceite, o que B não pode fazer.
6. **§8** — referências cruzadas.
7. **§9** — changelog.

Enquanto o humano não decidir dono do `/pre-cotacao` e `br-validators.js`, **ninguém edita o funil público** — está escrito explicitamente no §5 A.2 e §7.

Quando você mandar as views `registrocliente.php` e `checkout-cliente.php`, devolvo o `fix-B-views-validacao.ps1` fechando o B2 (client-side nas telas de contato que são de B).