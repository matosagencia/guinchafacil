# Motor de decisão de socorro

## Fluxo móvel

1. O prestador atende no local e registra apenas que o orçamento foi informado.
2. O valor do orçamento permanece fora da plataforma.
3. Se o cliente aprovar, é criada uma cobrança de R$ 25,00 para a oficina móvel.
4. O cliente decide se precisa de reboque:
   - não: o pedido segue para execução/finalização do atendimento;
   - sim: o pedido entra na etapa de cotação de reboque.

O valor de R$ 25,00 é uma cobrança da plataforma à oficina, não um valor cobrado do cliente e não é repasse ao prestador.

## Fluxo de orçamento detalhado

O fluxo legado continua exigindo itens e valores quando o prestador envia um orçamento detalhado, especialmente para peças e mão de obra. Isso mantém a cobrança itemizada e a baixa de estoque existentes.

## Banco

A migration `install/migration_motor_decisao_socorro_v1.sql` cria o diário `pedido_decisoes_socorro`, os campos de decisão em `pedidos` e a configuração da comissão móvel. Execute pelo runner:

```powershell
C:\xampp\php\php.exe install\migrate.php
```

## XAMPP local

Nesta instalação o VirtualHost da porta 8080 aponta diretamente para o projeto. Use:

```text
http://localhost:8080/
```

O `.env.local` deve apontar para `guinchafacil_dev`, usuário `root`, senha vazia e `MP_ENV=sandbox`.

## Playwright

```powershell
$env:PW_HEADLESS='true'
npx playwright test socorro-flow.spec.js --project=chromium
```

Os cenários APRO, OTHE, FUND, SECU e CONT só executam chamadas reais quando `MP_SANDBOX_E2E=1` estiver definido e as chaves Sandbox estiverem disponíveis. Sem isso, o teste estrutural valida a cotação e a abertura do checkout sem movimentar pagamentos.

## Observações

- O teste local confirmou cotação de R$ 135,00 no cenário utilizado.
- A migração local foi aplicada, mas o runner ainda acusa divergências de checksum preexistentes em migrations antigas; elas não foram alteradas.
- O repositório Git local apresenta objetos corrompidos e não deve ser usado para commit/push até ser recuperado a partir de uma cópia íntegra do repositório.
