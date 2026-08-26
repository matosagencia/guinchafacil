# GuinchaFácil — Changelog de Correções

## [fix] — Correções aplicadas

### 🔴 CRÍTICO — Cadastro de guincho não salvava na tabela `guinchos`

**Arquivo:** `src/Controllers/AuthController.php`

**Problema:** O método `criarGuincho()` usava nomes de colunas errados no INSERT:
- `capacidade_kg` → correto: `capacidade_ton`
- `latitude_atual` → correto: `lat_atual`
- `longitude_atual` → correto: `lng_atual`

O PDO lançava exceção silenciosa que era capturada internamente e retornava `null`,
causando rollback de toda a transação sem mensagem clara de erro.

**Correção:**
- Nomes de colunas alinhados com o schema SQL real
- `criarGuincho()` agora relança a exceção PDO em vez de engolir o erro
- Removido o método `converterToneladasParaKg()` (não mais necessário; banco armazena toneladas)

---

### 🔴 CRÍTICO — Pedido de socorro criado com status errado

**Arquivo:** `src/Controllers/ClienteController.php`

**Problema:** `pedidoCriar()` inseria o pedido com status `'aguardando_guincho'` diretamente,
pulando todo o fluxo de pagamento (PagamentoController + webhook).

**Correção:**
- Status inicial alterado para `'aguardando_pagamento'`
- Após criar o pedido, redireciona para `/pagamento/checkout/{id}` (fluxo correto)

---

### 🟡 MÉDIO — Dashboard do cliente fazia 3 queries pesadas desnecessárias

**Arquivo:** `src/Controllers/ClienteController.php`

**Problema:** `dashboard()` chamava `Pedido::listarPorCliente($uid, 1, 999)` duas vezes
(buscando até 999 registros só para contar), além dos 5 recentes.

**Correção:** Substituído por uma única query SQL com `COUNT` e `SUM` condicional.

---

### 🟡 MÉDIO — Rota GET duplicada/errada

**Arquivo:** `index.php`

**Problema:** `/cliente/pedido/criar` (GET) apontava para `pedidoNovo` (o formulário),
mas também existia como rota POST. Causava ambiguidade.

**Correção:** Removida a entrada GET duplicada. O formulário deve ser acessado via `/cliente/pedido/novo`.

---

### 🟢 BAIXO — Aviso sobre `mail()` nativo

**Arquivo:** `src/Controllers/AuthController.php`

**Problema:** Reset de senha usava `mail()` nativo, que raramente funciona sem SMTP configurado.

**Correção:** Adicionado comentário de aviso com instrução para usar PHPMailer em produção.

---

### 📦 BANCO DE DADOS — Migration script

**Arquivo:** `install/migration_fix.sql`

Execute este script **uma vez** após instalar o schema original (`guinchafacil.sql`):

```bash
mysql -u root -p guinchafacil < install/migration_fix.sql
```

Ele aplica:
1. Renomeia colunas erradas em `guinchos` (se existirem com nome errado)
2. Insere seeds obrigatórios em `configuracoes` (tarifa_por_km, taxa_fixa, comissao_plataforma)
3. Cria tabela `password_resets` se não existir
