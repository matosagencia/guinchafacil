# Refatoração — FaturaWebhookController e FaturaService

Arquivos refatorados somente para legibilidade e UTF-8, sem mudança
intencional nas regras de assinatura, idempotência, consulta de fatura ou
respostas HTTP.

## Instalação

Faça backup dos dois arquivos atuais e substitua:

- `FaturaWebhookController.php` em `src/Controllers/`
- `FaturaService.php` em `src/Services/`

## Validação no XAMPP

```powershell
.\validar-fatura-webhook.ps1 -Repositorio 'C:\xampp\htdocs\guinchafacil'
```

Resultado esperado:

```text
[PASS][FaturaWebhook][syntax] ...FaturaWebhookController.php
[PASS][FaturaWebhook][syntax] ...FaturaService.php
[DONE][FaturaWebhook] Sintaxe PHP validada.
```

Não foi fornecido teste automatizado do webhook. Antes de produção, execute o
cenário já existente de assinatura inválida, fatura não localizada, pagamento
válido e repetição da mesma transação.
