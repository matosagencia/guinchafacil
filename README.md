# Entrega A — documento e continuidade de triagem

Copie os arquivos mantendo os caminhos. O `index.php` do pacote contém as quatro rotas acrescentadas; aplique apenas os blocos dessas rotas se o arquivo local tiver alterações de outras faixas.

## Migration CPF/CNPJ

Execute duas vezes no XAMPP para homologar o contrato:

```powershell
C:\xampp\php\php.exe tools\test_documento_cnpj_migration.php
```

O resultado esperado contém `RUN-1][OK`, `RUN-2][OK` e `ASSERT][OK`. A coluna `usuarios.cpf` será `VARCHAR(18)` e continuará sendo a coluna canônica de documento.

## Fluxo de triagem

1. `POST /api/pre-cotacao/finalizar` grava a chave `triage_session_id` e o payload privado da cotação.
2. O cadastro preserva a sessão e entrega o cliente em `/checkout/veiculo?triage_session_id=<id>`.
3. O checkout restaura a cotação, valida o veículo do cliente e cria o pedido uma única vez antes de encaminhar ao pagamento.

Os logs de cada fase estão documentados em `doc/CONTRATOS.md`.
