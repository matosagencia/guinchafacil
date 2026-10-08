# CONTRATOS — GuinchaFácil

> Toda mudança de entrada/saída **entre faixas** é registrada aqui **antes** do código (R2). Só o dono edita este arquivo; agentes propõem via `CONTRATO_PEDIDO:`.
> Tipos e nomes de coluna marcados **não verificado** devem ser confirmados pela faixa dona com o trecho real do código antes de virar contrato fechado.

## 1. Como pedir mudança

### `ROTA_PEDIDA:` (rota nova ou alterada em `index.php`)

```
ROTA_PEDIDA:
  faixa: <A|B|C>
  metodo: <GET|POST>
  caminho: </exemplo/{id}>
  controller: <Classe::metodo>
  motivo: <uma linha>
```

### `CONTRATO_PEDIDO:` (mudança em arquivo/contrato de outra faixa)

```
CONTRATO_PEDIDO:
  de: <faixa solicitante>
  para: <faixa dona>
  o_que_preciso: <campo, método ou comportamento>
  por_que: <uma linha>
  criterio_de_aceite: <como saber que ficou pronto>
  ## Contrato de Disponibilidade de Guincho (B → A)

**Status:** entregue por A em 2026-10-06 (PR #1, branch própria, commit `main`).
**Dono da escrita:** A (`src/Services/GuinchoDisponibilidade.php` + `src/Services/PreCotacao/PreCotacaoOpcoesService.php`). **Consumidores:** B (`AdminController::pedidoCriar`), C (cenários E2E).

### Método entregue

```php
GuinchoDisponibilidade::existeNoRaio(
    float  $lat,
    float  $lng,
    ?string $categoria = null,
    ?float  $raioKm    = null
): bool
```

O agente emite o bloco e **para essa parte da tarefa**. O dono decide e atualiza este arquivo.

## 2. Contrato Pedido v1

**Status:** rascunho. A faixa A (ChatGPT) publica a versão final (missão A2) e o dono aprova.
**Dono da escrita:** faixa A (`Pedido` / `PedidoCoreService`). **Consumidores:** faixa B (admin, painel oficina) e faixa C (testes).

| Campo | Significado | Tipo | Observação |
| --- | --- | --- | --- |
| `oficina_id` | Oficina escolhida pelo motor de decisão | não verificado | Nulo quando `origem_funil = especialista` |
| `oficina_nome` | Nome da oficina, para exibição | não verificado | Exibido em `admin/pedidodetalhe` |
| `triagem` | Resultado da triagem do atendimento | não verificado | Exibido em `admin/pedidodetalhe` |
| `custo_assistencia` | Custo estimado da assistência | não verificado | Exibido em `admin/pedidodetalhe` |
| `custo_reboque` | Custo estimado do reboque | não verificado | Exibido em `admin/pedidodetalhe` |
| `recomendacao` | Recomendação do motor | não verificado | Exibido em `admin/pedidodetalhe` |
| `origem_funil` | Origem do pedido | enum | Valores: `oficina` ou `especialista` |

Regras do contrato:

1. A faixa B **consome apenas** estes campos. Não consulta tabelas diretamente.
2. Campo que a faixa B precise e não esteja aqui → `CONTRATO_PEDIDO` para a faixa A.
3. Pedido com `origem_funil = oficina` deve expor **todos** os campos acima (critério de aceite da faixa A, testado pela faixa C).
4. Mudança de nome, tipo ou significado gera **Contrato Pedido v2**, sem alterar a v1 retroativamente.

## 3. Contrato de idempotência (Pagamento e Webhook)

**Dono:** faixa A. **Testa:** faixa C (cenários `idempotencia-pagamento` e `idempotencia-webhook`).

| Ponto | Contrato |
| --- | --- |
| Criação de pagamento | Header `X-Idempotency-Key = "ped{id}-t{tentativa}"` no `MercadoPagoProvider`. Mesmo POST repetido → 1 cobrança. |
| Webhook: autenticidade | Assinatura HMAC validada antes de qualquer processamento. |
| Webhook: repetição | Índice único `(provedor, event_id)` em `logs_webhook`. Evento repetido → resposta **200** sem reprocessar. |
| Pedido | Um pedido por `triage_session_id`. Duplo clique não duplica. |

## 4. Contrato de evento de front-end (A → C)

| Evento | Disparado em | Por quem | Usado por |
| --- | --- | --- | --- |
| `gf:address-confirmed` | `document` | `public-pre-cotacao-flow.js` (faixa A) | Playwright `fluxo-1a` (faixa C) |

O payload do evento é **não verificado**. A faixa A documenta aqui antes de a faixa C depender dele.

## 5. Contrato do console de testes (C ↔ todas)

- Cenários em `tests/scenarios/*.json`; cada passo tem `key`, `node` e `probe`.
- Tabelas `test_runs` e `test_run_steps` (colunas: `run_id`, `step_key`, `node`, `status`, `started_at`, `duration_ms`, `detail`).
- `status` ∈ `pending | running | ok | fail`.
- Eventos: `GET /admin/testes/run/{id}/events?after=N` (polling de 1 s).
- Ingestão do Playwright: `POST /admin/testes/ingest` com token.
- Guardas: só admin, CSRF, bloqueia se `MP_ENV != sandbox`, só dados `E2E_`, nunca exibe segredos.
- Nota: o protocolo v2 manda **estender** `/admin/qa/run/*` e `/admin/simulador` em vez de criar um segundo sistema. Rotas finais dependem da decisão D4.

## 6. Contrato de verificação de rotas (R7)

Toda checagem de rota usa **GET**:

```
curl -s -o /dev/null -w "%{http_code}" https://guinchafacil.com.br/login
```

Não usar `curl -sI` (envia HEAD; o router só trata GET/POST).

## 7. Histórico

| Versão | Data | Mudança |
| --- | --- | --- |
| Pedido v1 (rascunho) | 2026-09-30 | Campos extraídos do protocolo; tipos pendentes de verificação pela faixa A |

## Contrato Financeiro Admin (destino de pagamento)

**Status:** proposto por B em 2026-10-03; aguarda A implementar.
**Dono da escrita:** A (Pagamento / Payment). **Consumidores:** B (AdminController + views do funil).

### Entrada

destino_pagamento:

pago_agora # baixa manual auditavel

pago_na_chegada # permanece pendente, sem checkout

online # cria cobranca pendente + link do provedor

sem_cobranca # isencao auditavel, sem checkout

provedor_online:

mercadopago

pagseguro

null # quando destino != online


### Regras

1. `online` so pode aceitar provedor efetivamente habilitado na configuracao.
2. A interface **nao pode mostrar** provedor desabilitado.
3. `pago_agora` registra baixa manual auditavel.
4. `pago_na_chegada` permanece pendente, sem criar checkout.
5. `online` cria cobranca pendente e link do provedor escolhido.
6. `sem_cobranca` registra isencao auditavel, sem checkout.
7. Todas as transicoes devem gerar evento e ser idempotentes.
8. Ao concluir com sucesso, B redireciona para `/admin/pedido/{id}?criado=1`
   (com flash contendo link MP ou mensagem de isencao)  decisao do dono
   em 2026-10-03, contraria o `/admin/pedidos` original proposto por B.

### Saida esperada de A

- Operacao unica para aplicar o destino financeiro ao pedido recem-criado.
- Retorno do link de checkout apenas para destino `online`.
- Eventos/snapshot financeiro coerentes.
- Erro explicito se o provedor escolhido estiver desabilitado.
- Fonte de verdade que lista os provedores habilitados (endpoint ou
  metodo estatico  A decide o nome).

### Nao verificado

- Nome exato do metodo de A.
- Formato exato do retorno (array com chaves).

### Confirmado pelo dono em 2026-10-03

- **Provedores habilitados:** apenas UM por vez  o valor da config
  `PAYMENT_GATEWAY_ACTIVE`. Nao coexistem mercadopago + pagseguro.
  A interface deve mostrar exatamente esse (ou os permitidos pela
  mesma fonte que `PaymentProviderFactory::gatewayAtivoRaw()` le),
  nunca os dois.
- **Redirect final:** `/admin/pedido/{id}?criado=1` (com flash
  contextual exibindo link do provedor quando destino = `online`).

### Criterio de aceite (B)

- `AdminController::pedidoCriar` recebe `destino_pagamento` e
  `provedor_online` via POST.
- Chama o metodo de A e trata os 4 retornos.
- Redireciona para `/admin/pedido/{id}?criado=1` com flash contextual.
- Interface da Etapa 1 nao permite escolher provedor desabilitado.


## Contrato de Disponibilidade de Guincho (B -> A)

**Status:** implementado pela Faixa A em 2026-10-06.  
**Dono:** Faixa A (dispatch/cobertura). **Consumidores:** Faixa B (AdminController) e Faixa C (testes).

### Metodo publico

```php
GuinchoDisponibilidade::existeNoRaio(
    float $lat,
    float $lng,
    ?string $categoria = null,
    ?float $raioKm = null
): bool
```

### Semantica

Retorna `true` somente quando existe pelo menos um prestador apto a receber um atendimento de **reboque/TOWING** nas coordenadas informadas.

O gate pre-pedido exige:

1. `aprovado = 1`;
2. `disponivel = 1`;
3. `reboque_aprovado = 1`;
4. `lat_atual/lng_atual` validos;
5. nenhum pedido ativo concorrente do mesmo guincho em `a_caminho|no_local|em_reboque`;
6. distancia dentro de `MIN(raio_cobertura_km, raio_maximo_km global, raioKm opcional)`.

O metodo e **read-only**: nao cria pedido, nao altera estado e nao grava evento/log de pedido. Em erro de banco/configuracao, retorna `false` (fail closed).

### Limites do contrato pre-pedido

- `expiracao_aceite` pertence ao pedido e so existe depois da criacao; portanto nao e aplicavel a esta consulta pre-pedido.
- `categoria` esta reservada para compatibilidade veicular pre-pedido. O motor atual de compatibilidade usa snapshot ligado a `pedido_id`; a Faixa A nao inventa um gate diferente antes de o pedido existir.

### Integracao de pre-cotacao

`PreCotacaoOpcoesService::montar()`, quando `modo=reboque`, consulta **somente** `GuinchoDisponibilidade::existeNoRaio()` antes de calcular cotacao. Nao consulta oficinas neste modo.

Sem guincho apto, a resposta e:

```json
{
  "modo": "reboque",
  "disponivel": false,
  "fallback_tipo": "suporte",
  "mensagem": "Nenhum guincho disponível na sua região agora."
}
```

Com guincho apto, retorna `disponivel:true` e `valor_reboque`.

A defesa da Faixa B antes de `PedidoCoreService::criar()` permanece obrigatoria e nao deve ser removida.


### Semantica de fallback em orientacao/local (2026-10-07)

Quando nao existir oficina/especialista apto para o servico solicitado:

- se `GuinchoDisponibilidade::existeNoRaio(...)` retornar `true`, a API deve retornar `fallback_tipo = "guincho"`;
- se retornar `false`, a API deve retornar `fallback_tipo = "suporte"`.

Essa regra vale para `modo=orientacao` e `modo=local`.

O front-end `public-pre-cotacao-flow.js` ja interpreta `fallback_tipo="guincho"` exibindo o botao **Quero rebocar**. A Faixa A deve fornecer apenas a semantica correta no payload.


## Contrato de Persistencia de tipo_problema (B -> A)

**Status:** implementado pela Faixa A em 2026-10-07.  
**Dono:** Faixa A (`PedidoCoreService` / `Pedido`). **Consumidores:** Faixa B (AdminController) e Faixa C (testes).

### Regra

Quando a criacao receber `tipo_problema` nao vazio, o valor deve ser persistido integralmente em `pedidos.tipo_problema`.

Exemplos:

- fluxo direto de reboque: `tipo_problema = "reboque"`;
- fluxo por servico: `tipo_problema = <slug do servico>`.

A coluna `pedidos.tipo_problema` e `VARCHAR(80)`, e nao ENUM, porque o catalogo de servicos e extensivel. Isso impede que MySQL/MariaDB em modo permissivo converta silenciosamente slugs novos para string vazia.

Como defesa adicional:

- `PedidoQuoteRequest` normaliza valor vazio para `outro`;
- `Pedido::criar()` e `Pedido::criarCompleto()` nunca gravam string vazia;
- valores historicos vazios nao sao inferidos/backfillados automaticamente.

### Criterio

`tipo_problema="reboque"` deve ser lido como `reboque` apos o INSERT. Nenhum pedido novo criado pelos fluxos atuais deve persistir `tipo_problema = ''`.
).


CONTRATO_PEDIDO:
  de: B
  para: A
  o_que_preciso:
    1. Tabela nova `comissoes_cidade` (idempotente, INFORMATION_SCHEMA):
         id              BIGINT UNSIGNED PK AUTO_INCREMENT
         cidade_id       BIGINT UNSIGNED NOT NULL (UNIQUE)
         percentual      DECIMAL(5,4) NOT NULL DEFAULT 0.0000
         ativo           TINYINT(1) NOT NULL DEFAULT 1
         created_at, updated_at

    2. Colunas novas em `pedidos` (idempotentes):
         comissao_valor            DECIMAL(10,2) NULL
         comissao_tipo             ENUM('faturada','descontada') NULL
         valor_liquido_parceiro    DECIMAL(10,2) NULL
         fatura_id                 BIGINT UNSIGNED NULL (FK)

    3. Metodo estatico publico na Faixa A:
         ComissaoService::calcular(
             int $pedidoId,
             string $formaPagamento,   // 'pago_na_chegada' | 'pago_agora' | 'online' | 'sem_cobranca'
             float $valorTotal,
             ?int $cidadeId = null
         ): array
       Retorna:
         [
           'percentual'             => float,   // ex: 0.1500
           'valor'                  => float,   // ex: 28.50
           'tipo'                   => string,  // 'faturada' | 'descontada'
           'valor_liquido_parceiro' => float,   // ex: 189.39 (faturada) ou 160.89 (descontada)
         ]
       Regra:
         percentual_efetivo = config('comissao_plataforma') + comissao_cidade.percentual (se cidade tiver override)
         Se forma = 'pago_na_chegada':
             tipo = 'faturada'
             valor = total * percentual
             valor_liquido_parceiro = total   // recebe cheio
         Senão (pago_agora/online/sem_cobranca/outros):
             tipo = 'descontada'
             valor = total * percentual
             valor_liquido_parceiro = total - valor

    4. Quando o pedido for concluido (PedidoTransitionService::concludeByX),
       gravar os 3 campos via ComissaoService::calcular() + UPDATE pedidos.
       Isso pode ser feito no mesmo `approvePayment()` (se pagamento for
       o gatilho) ou no `concludeByX` (se conclusao for o gatilho).
       Faixa A decide o ponto canonico e documenta aqui.

  por_que:
    A UI da Faixa B vai ler esses 3 campos em `pedidos` para montar
    o extrato do parceiro. Sem eles, o front precisaria recalcular
    a comissao a cada render, o que e caro e sujeito a divergencia.

  criterio_de_aceite:
    - Migration `install/migration_comissao_v1.sql` idempotente.
    - Colunas existem em `pedidos` apos rodar 2x.
    - `ComissaoService::calcular()` retorna os 4 campos.
    - Pedido concluido com `forma_pagamento_escolhida = 'pago_na_chegada'`
      grava `comissao_valor`, `comissao_tipo='faturada'`,
      `valor_liquido_parceiro = valor_total`.
    - Pedido concluido com `forma_pagamento_escolhida = 'pago_agora'`
      grava `comissao_tipo='descontada'`,
      `valor_liquido_parceiro = valor_total - comissao_valor`.
      
CONTRATO_PEDIDO:
  de: B
  para: A
  o_que_preciso:
    1. Tabelas novas (idempotentes, INFORMATION_SCHEMA):
         faturas_parceiro (schema definido em docs/CONTRATOS.md §Financeiro)
         faturas_itens
         faturas_pagamentos

    2. Servico estatico na Faixa A:
         FaturaService::fecharCiclo(\DateTimeImmutable $cicloInicio): int
       Comportamento:
         - Para cada parceiro com pedidos concluidos no ciclo
           (domingo 00:00 ate sabado 23:59)
         - Cria faturas_parceiro com status='aberta'
         - Cria faturas_itens para cada pedido
         - Calcula saldo
         - Se saldo > 0: gera PIX via PixService::gerar(...) para GF pagar
         - Se saldo < 0: gera PIX via PixService::gerar(...) para parceiro pagar
         - Retorna contagem de faturas criadas

         FaturaService::marcarPaga(int $faturaId, string $transacaoId, string $metodo): bool
         FaturaService::bloquearPorVencimento(): int  // cron job
         FaturaService::desbloquear(int $faturaId): bool

    3. Endpoint novo (rota registrada pelo dono):
         POST /webhook/mercadopago/pix
       Comportamento:
         - Valida assinatura HMAC
         - Localiza fatura por pix_copia_cola ou transacao_id
         - Chama FaturaService::marcarPaga(...)
         - Retorna 200 sem reprocessar (idempotencia por transacao_id)

    4. Jobs cron:
         - fechar_ciclos_semanais (domingo 00:01)
         - aplicar_bloqueios_vencidos (sabado 00:01)

  por_que:
    A Faixa B precisa de um servico estavel para consultar fatura,
    bloquear/desbloquear parceiro, e gerar PIX. Sem contrato escrito,
    B fica bloqueada.

  criterio_de_aceite:
    - Migration idempotente, rodar 2x nao altera nada.
    - Rodar fecharCiclo() em um ciclo com 3 pedidos de teste cria 1 fatura.
    - Com saldo > 0: fatura tem pix_qrcode preenchido.
    - Com saldo < 0: fatura tem pix_qrcode + pix_copia_cola.
    - Webhook com evento repetido retorna 200 e nao reprocessa.
    - Fatura com saldo > 0 e paga em D+7: status='paga'.
    - Fatura nao paga em D+7 bloqueia parceiro (bloquearPorVencimento).
