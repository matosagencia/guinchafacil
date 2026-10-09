# refatorar-docs-sessao4.ps1 (v2)
# Refatora os 3 arquivos de documentacao da sessao 4.
# SEM validacao de mojibake (os caracteres do validador quebravam o parser).
# Grava em UTF-8 sem BOM. Backup com timestamp antes de sobrescrever.
#
# Data: 2026-10-08

param(
    [string]$Repositorio = 'C:\xampp\htdocs\guinchafacil'
)

$ErrorActionPreference = 'Stop'

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function Write-Doc {
    param(
        [string]$Path,
        [string]$Content
    )

    $full = Join-Path $Repositorio $Path
    $dir  = Split-Path -Parent $full

    if (-not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }

    if (Test-Path -LiteralPath $full) {
        $bak = "$full.bak-$stamp"
        Copy-Item -LiteralPath $full -Destination $bak -Force
        Write-Host "[BACKUP] $bak" -ForegroundColor DarkGray
    }

    [System.IO.File]::WriteAllText($full, $Content, $utf8NoBom)
    Write-Host "[WRITE]  $full" -ForegroundColor Green
}

# ============================================================
# 1. doc/BLOCKERS.md
# ============================================================
$blockers = @'
# BLOCKERS - GuinchaFacil

> Registro de bloqueios ativos. Formato: sintoma / o que foi tentado / hipotese / por que parei.
> Quando resolvido, mover a entrada para o fim com "RESOLVIDO em AAAA-MM-DD".

---
## BLOCKER B-001 - 404 nas rotas da faixa B em localhost (XAMPP :8080)

**Data:** 2026-10-01
**Faixa:** B
**Severidade:** alta (bloqueava validacao local dos criterios de aceite da B)
**Status:** **RESOLVIDO em 2026-10-01** - causa era URL com prefixo /guinchafacil desnecessario.

**Sintoma:**
GET em tres rotas da faixa B retornava 404 no XAMPP local, com a porta correta (:8080):
- http://localhost:8080/guinchafacil/admin/pedidos
- http://localhost:8080/guinchafacil/admin/pedido/1
- http://localhost:8080/guinchafacil/oficina/dashboard

**Causa raiz (confirmada):**
C:\xampp\apache\conf\extra\httpd-vhosts.conf (linha 48) define:
    DocumentRoot "C:/xampp/htdocs/guinchafacil"
O docroot JA E a raiz do projeto. A URL correta e http://localhost:8080/admin/pedidos
(sem o prefixo /guinchafacil). O prefixo extra fazia o Apache procurar
htdocs/guinchafacil/guinchafacil/admin/pedidos -> 404.

**Confirmacao:**
    curl.exe -s -o NUL -w "%{http_code}" "http://localhost:8080/admin/pedidos"
Saida: 302 (redirect para login, comportamento correto de rota protegida sem sessao).

**Licao:**
A URL base local e http://localhost:8080/ (raiz), nao http://localhost:8080/guinchafacil/.

---
## BLOCKER B-002 - Tipo de triagem nao documentado no Contrato Pedido v1

**Data:** 2026-10-01
**Faixa:** B
**Severidade:** media
**Status:** **Ativo** - aguardando Faixa A

**Sintoma:**
src/Views/admin/partials/_pedido_oficina_detalhe.php renderiza triagem com
htmlspecialchars(()). doc/CONTRATOS.md secao 2 marca o tipo de
triagem como "nao verificado". Se for array/JSON, o admin ve "Array" ou JSON cru.

**Hipoteses:**
- H1: string humana -> partial OK.
- H2: array/JSON -> partial imprime lixo.

**CONTRATO_PEDIDO emitido (aguardando faixa A):**
    CONTRATO_PEDIDO:
      de: B
      para: A
      o_que_preciso: tipo real e forma de serializacao do campo triagem
                     em Pedido (array? string JSON? string humana?)
      por_que: partial do admin usa htmlspecialchars()
      criterio_de_aceite: doc/CONTRATOS.md secao 2 documenta o tipo de triagem
                          + exemplo para origem_funil=oficina

---
## BLOCKER B-004 - Migration + gravacao dos 7 campos do Contrato Pedido v1

**Data:** 2026-10-01
**Faixa:** B (dependencia da A)
**Severidade:** alta (a UI da B mostra "-" em 6 dos 7 campos)
**Status:** **Ativo** - aguardando Faixa A

**Sintoma:**
A tabela pedidos so tem oficina_id. Faltam oficina_nome, triagem,
custo_assistencia, custo_reboque, recomendacao, origem_funil.
A UI da faixa B (_pedido_oficina_detalhe.php + badge em admin/pedidos.php)
ja exibe os 7 campos, mas mostra "-" nos que nao existem.

**CONTRATO_PEDIDO emitido (aguardando faixa A):**
    CONTRATO_PEDIDO:
      de: B
      para: A
      o_que_preciso: migration idempotente (INFORMATION_SCHEMA) que adiciona as
                     colunas oficina_nome, triagem, custo_assistencia,
                     custo_reboque, recomendacao, origem_funil em pedidos;
                     e PedidoCoreService/DecisaoAtendimentoService gravando
                     esses campos ao criar pedido do funil oficina.
      por_que: a UI da B ja consome os 7 campos; todos aparecem como "-"
      criterio_de_aceite: pedido origem_funil=oficina grava os 6 campos;
                          admin/pedido/{id} exibe sem "-" (exceto nulos legitimos)

---
## BLOCKER B-005 - Alerta de novo pedido nao dispara no dashboard do guincho

**Data:** 2026-10-04
**Faixa:** B
**Severidade:** media
**Status:** **RESOLVIDO em 2026-10-04** - causa raiz confirmada: pedido expirado, nao bug de codigo.

**Sintoma:**
Pedido criado com status aguardando_guincho nao gerava alerta visual no dashboard do guincho logado.

**Causa raiz (confirmada):**
Nao era bug de codigo. O front-end (fix-B-11 v2) estava correto. O backend
(`GuinchoController::pedidosDisponiveis()` + `montarOfertasDisponiveis()`)
estava correto. Os 4 gates do metodo passavam para o pedido #173:
  - attendance_mode = TOWING (gate A OK)
  - reboque_aprovado do guincho = 1 (gate A OK)
  - service_type_id NULL -> gate C nem entra
  - distancia_km = 2.553 << raio_cobertura_km = 50 (gate de raio OK)

O pedido simplesmente **expirou**. `Pedido::listarAguardandoGuincho()`
(L387-425) filtra por `AND p.expiracao_aceite > NOW()`. O pedido #173 foi
criado as 13:24:25 com `expiracao_aceite = 13:54:25` (30 min de janela).
As observacoes do HAR foram feitas entre 15:52Z e 16:26Z UTC - ou seja,
~2h depois da janela de aceite ter fechado. Fila legitimamente vazia.

**Evidencia visual do fix-B-11 v2:**
Pedido #183 apareceu como NOVA SOLICITACAO no /guincho/dashboard com
countdown 29:28; atendimento carregado com rota, cliente e valor.
`fix-B-11 v2` (toast+beep) funcionou.

**Efeitos colaterais identificados (nao bloqueiam o alerta):**

1. `status` do pedido permanece `aguardando_guincho` mesmo apos a janela
   expirar. `/admin/pedido/173` mostra "Aguardando Guincho" quando ja nao
   esta. Ninguem move o status para `expirado`. UX enganosa.
   -> CONTRATO_PEDIDO emitido para Faixa A (recomendacoes R1/R2).

2. `Pedido::listarAguardandoGuincho()` nao tem fallback para
   `expiracao_aceite IS NULL` (o `>` com NULL retorna NULL = falso).
   Qualquer pedido com a coluna NULL fica invisivel pra sempre.
   -> mesma CONTRATO_PEDIDO.

3. `tipo_problema` do pedido #173 veio **vazio** (string ""), o que e
   anomalo - deveria vir da pre-cotacao ou do funil admin. O
   `listarAguardandoGuincho()` nao filtra por isso, entao nao foi a
   causa do alerta nao tocar. Mas e dado sujo.
   -> investigacao paralela Faixa B, nao bloqueia B-005.

**Observacao (nao bloqueia):**
404 em `public/assets/vendor/leaflet-routing-machine/routing-icon.png`
referenciado por `leaflet-routing-machine.css`. Icone de manobra fica
vazio. Dono decide: adicionar ao bundle ou incluir na fila D10.

---
## BLOCKER B-008 - Fallback [B-STID-01] nao resolve `eletrica`

**Data:** 2026-10-08
**Faixa:** B (paliativo por A)
**Severidade:** alta (bloqueava criacao de pedidos de assistencia via funil admin)
**Status:** **RESOLVIDO PALIATIVAMENTE em 2026-10-08** - aguardando confirmacao de teste funcional

**Sintoma:**
Pedido com `tipo_problema = "eletrica"` no funil admin falhava com
"Destino e obrigatorio para reboque", mesmo com o fallback `[B-STID-01]`
aplicado em `cb654f7`. Pedido com `tipo_problema = "pneu"` funcionava.

**Causa raiz (confirmada):**
O bloco `[B-STID-01]` em `AdminController.php:1624-1640` buscava no catalogo
com a query:

    WHERE active=1 AND (code = ? OR slug = ? OR LOWER(name) = LOWER(?))

Tres problemas:
1. `service_types` **nao tem coluna `slug`** (o slug mora em `servicos_catalogo`).
2. O `name` real e `Eletrica` (com acento) - `LOWER('Eletrica')` != `eletrica`.
3. O `code` real e `ELECTRICAL_DIAGNOSIS`, nao `eletrica`.

Resultado: `pneu` batia (por acaso), `eletrica` nao. `$serviceTypeId` ficava
`NULL`, `PedidoCoreService` assumia `TOWING`, exigia destino -> erro.

**Paliativo aplicado:**
1. `fix-B-stid-v5c.ps1` - substituiu o bloco `[B-STID-01]` antigo por chamada
   ao `\App\Services\Catalog\ServiceTypeResolver::porSlug()` (Faixa A).
   Backup: `AdminController.php.bak-fix-B-stid-v5c-20261008-153228`.
2. `_precotacao_funil_admin.php` (Solucao B) - o `<script>` proprio agora
   sincroniza `tipo_problema`, `categoria`, `valor_cotado` e
   `decisao_atendimento` via evento `gf:flow-stage`.

**Evidencia (log do console):**
    [admin-funil] hidden sincronizados: {
      tipo_problema: 'eletrica',
      categoria: 'popular',
      valor_cotado: 96.8,
      decisao_atendimento: 'assistencia'
    }

**Pendencia para fechar:**
Confirmar no `php_errors.log` a linha:
    [B-STID-01] service_type_id resolvido via ServiceTypeResolver: 8 (tipo_problema=eletrica)
E testar `/admin/pedido/novo/funil` com `eletrica`: pedido criado como
`ON_SITE`, `service_type_id = 8`, `lat_destino = NULL`, sem B-GUARD-02/03.

**Limite do paliativo:**
O `ServiceTypeResolver` tem mapa hardcoded de 5 slugs (`pneu`, `eletrica`,
`bateria`, `mecanica`, `chaveiro`). Servico novo cadastrado no Backoffice
**nao e coberto** -> fail closed -> pedido nao cria. Solucao definitiva:
**B-009 - Catalogo dinamico no funil**.

**Sucessor:** B-009 (catalogo dinamico com `service_type_id` vindo de select
real, `attendance_mode` do catalogo, `public_slug` administravel).

**Referencia cruzada:**
- `CONTRATO_RESPOSTA CONSOLIDADO - A -> B` (2026-10-08) em `doc/CONTRATOS.md`.
- `fix-B-stid-v5c.ps1` em `tools/`.
- `_precotacao_funil_admin.php` (Solucao B) em `src/Views/admin/partials/`.
'@

Write-Doc -Path 'doc/BLOCKERS.md' -Content $blockers

# ============================================================
# 2. doc/CONTRATOS.md
# ============================================================
$contratos = @'
# CONTRATOS - GuinchaFacil

> Toda mudanca de entrada/saida **entre faixas** e registrada aqui **antes** do codigo (R2). So o dono edita este arquivo; agentes propoem via `CONTRATO_PEDIDO:`.
> Tipos e nomes de coluna marcados **nao verificado** devem ser confirmados pela faixa dona com o trecho real do codigo antes de virar contrato fechado.

## 1. Como pedir mudanca

### `ROTA_PEDIDA:` (rota nova ou alterada em `index.php`)

    ROTA_PEDIDA:
      faixa: <A|B|C>
      metodo: <GET|POST>
      caminho: </exemplo/{id}>
      controller: <Classe::metodo>
      motivo: <uma linha>

### `CONTRATO_PEDIDO:` (mudanca em arquivo/contrato de outra faixa)

    CONTRATO_PEDIDO:
      de: <faixa solicitante>
      para: <faixa dona>
      o_que_preciso: <campo, metodo ou comportamento>
      por_que: <uma linha>
      criterio_de_aceite: <como saber que ficou pronto>

O agente emite o bloco e **para essa parte da tarefa**. O dono decide e atualiza este arquivo.

## 2. Contrato Pedido v1

**Status:** rascunho. A faixa A (ChatGPT) publica a versao final (missao A2) e o dono aprova.
**Dono da escrita:** faixa A (`Pedido` / `PedidoCoreService`). **Consumidores:** faixa B (admin, painel oficina) e faixa C (testes).

| Campo | Significado | Tipo | Observacao |
| --- | --- | --- | --- |
| `oficina_id` | Oficina escolhida pelo motor de decisao | nao verificado | Nulo quando `origem_funil = especialista` |
| `oficina_nome` | Nome da oficina, para exibicao | nao verificado | Exibido em `admin/pedidodetalhe` |
| `triagem` | Resultado da triagem do atendimento | nao verificado | Exibido em `admin/pedidodetalhe` |
| `custo_assistencia` | Custo estimado da assistencia | nao verificado | Exibido em `admin/pedidodetalhe` |
| `custo_reboque` | Custo estimado do reboque | nao verificado | Exibido em `admin/pedidodetalhe` |
| `recomendacao` | Recomendacao do motor | nao verificado | Exibido em `admin/pedidodetalhe` |
| `origem_funil` | Origem do pedido | enum | Valores: `oficina` ou `especialista` |

Regras do contrato:

1. A faixa B **consome apenas** estes campos. Nao consulta tabelas diretamente.
2. Campo que a faixa B precise e nao esteja aqui -> `CONTRATO_PEDIDO` para a faixa A.
3. Pedido com `origem_funil = oficina` deve expor **todos** os campos acima (criterio de aceite da faixa A, testado pela faixa C).
4. Mudanca de nome, tipo ou significado gera **Contrato Pedido v2**, sem alterar a v1 retroativamente.

## 3. Contrato de idempotencia (Pagamento e Webhook)

**Dono:** faixa A. **Testa:** faixa C (cenarios `idempotencia-pagamento` e `idempotencia-webhook`).

| Ponto | Contrato |
| --- | --- |
| Criacao de pagamento | Header `X-Idempotency-Key = "ped{id}-t{tentativa}"` no `MercadoPagoProvider`. Mesmo POST repetido -> 1 cobranca. |
| Webhook: autenticidade | Assinatura HMAC validada antes de qualquer processamento. |
| Webhook: repeticao | Indice unico `(provedor, event_id)` em `logs_webhook`. Evento repetido -> resposta **200** sem reprocessar. |
| Pedido | Um pedido por `triage_session_id`. Duplo clique nao duplica. |

## 4. Contrato de evento de front-end (A -> C)

| Evento | Disparado em | Por quem | Usado por |
| --- | --- | --- | --- |
| `gf:address-confirmed` | `document` | `public-pre-cotacao-flow.js` (faixa A) | Playwright `fluxo-1a` (faixa C) |
| `gf:flow-stage` | `document` | `public-pre-cotacao-flow.js` (faixa A) | Funis admin/cliente (faixa B) |

O payload do evento e **nao verificado**. A faixa A documenta aqui antes de a faixa C depender dele.

## 5. Contrato do console de testes (C <-> todas)

- Cenarios em `tests/scenarios/*.json`; cada passo tem `key`, `node` e `probe`.
- Tabelas `test_runs` e `test_run_steps` (colunas: `run_id`, `step_key`, `node`, `status`, `started_at`, `duration_ms`, `detail`).
- `status` in `pending | running | ok | fail`.
- Eventos: `GET /admin/testes/run/{id}/events?after=N` (polling de 1 s).
- Ingestao do Playwright: `POST /admin/testes/ingest` com token.
- Guardas: so admin, CSRF, bloqueia se `MP_ENV != sandbox`, so dados `E2E_`, nunca exibe segredos.
- Nota: o protocolo v2 manda **estender** `/admin/qa/run/*` e `/admin/simulador` em vez de criar um segundo sistema. Rotas finais dependem da decisao D4.

## 6. Contrato de verificacao de rotas (R7)

Toda checagem de rota usa **GET**:

    curl -s -o /dev/null -w "%{http_code}" https://guinchafacil.com.br/login

Nao usar `curl -sI` (envia HEAD; o router so trata GET/POST).

## 7. Historico

| Versao | Data | Mudanca |
| --- | --- | --- |
| Pedido v1 (rascunho) | 2026-09-30 | Campos extraidos do protocolo; tipos pendentes de verificacao pela faixa A |
| Resolucao service_type_id (A->B) | 2026-10-08 | `ServiceTypeResolver::porSlug()` - mapa slug->code, fail closed |
| Catalogo Dinamico (A->B) - visao B-009 | 2026-10-08 | Aceito por B; implementacao paliativa vigente, arquitetura definitiva agendada |

---

## Contrato Financeiro Admin (destino de pagamento)

**Status:** proposto por B em 2026-10-03; aguarda A implementar.
**Dono da escrita:** A (Pagamento / Payment). **Consumidores:** B (AdminController + views do funil).

### Entrada

    destino_pagamento:
      pago_agora        # baixa manual auditavel
      pago_na_chegada   # permanece pendente, sem checkout
      online            # cria cobranca pendente + link do provedor
      sem_cobranca      # isencao auditavel, sem checkout

    provedor_online:
      mercadopago
      pagseguro
      null              # quando destino != online

### Regras

1. `online` so pode aceitar provedor efetivamente habilitado na configuracao.
2. A interface **nao pode mostrar** provedor desabilitado.
3. `pago_agora` registra baixa manual auditavel.
4. `pago_na_chegada` permanece pendente, sem criar checkout.
5. `online` cria cobranca pendente e link do provedor escolhido.
6. `sem_cobranca` registra isencao auditavel, sem checkout.
7. Todas as transicoes devem gerar evento e ser idempotentes.
8. Ao concluir com sucesso, B redireciona para `/admin/pedido/{id}?criado=1` (com flash contendo link MP ou mensagem de isencao) - decisao do dono em 2026-10-03, contraria o `/admin/pedidos` original proposto por B.

### Saida esperada de A

- Operacao unica para aplicar o destino financeiro ao pedido recem-criado.
- Retorno do link de checkout apenas para destino `online`.
- Eventos/snapshot financeiro coerentes.
- Erro explicito se o provedor escolhido estiver desabilitado.
- Fonte de verdade que lista os provedores habilitados (endpoint ou metodo estatico - A decide o nome).

### Nao verificado

- Nome exato do metodo de A.
- Formato exato do retorno (array com chaves).

### Confirmado pelo dono em 2026-10-03

- **Provedores habilitados:** apenas UM por vez - o valor da config `PAYMENT_GATEWAY_ACTIVE`. Nao coexistem mercadopago + pagseguro. A interface deve mostrar exatamente esse (ou os permitidos pela mesma fonte que `PaymentProviderFactory::gatewayAtivoRaw()` le), nunca os dois.
- **Redirect final:** `/admin/pedido/{id}?criado=1` (com flash contextual exibindo link do provedor quando destino = `online`).

### Criterio de aceite (B)

- `AdminController::pedidoCriar` recebe `destino_pagamento` e `provedor_online` via POST.
- Chama o metodo de A e trata os 4 retornos.
- Redireciona para `/admin/pedido/{id}?criado=1` com flash contextual.
- Interface da Etapa 1 nao permite escolher provedor desabilitado.

---

## Contrato de Disponibilidade de Guincho (B -> A)

**Status:** implementado pela Faixa A em 2026-10-06.
**Dono:** Faixa A (dispatch/cobertura). **Consumidores:** Faixa B (AdminController) e Faixa C (testes).

### Metodo publico

    GuinchoDisponibilidade::existeNoRaio(
        float $lat,
        float $lng,
        ?string $categoria = null,
        ?float $raioKm = null
    ): bool

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

    {
      "modo": "reboque",
      "disponivel": false,
      "fallback_tipo": "suporte",
      "mensagem": "Nenhum guincho disponivel na sua regiao agora."
    }

Com guincho apto, retorna `disponivel:true` e `valor_reboque`.

A defesa da Faixa B antes de `PedidoCoreService::criar()` permanece obrigatoria e nao deve ser removida.

### Semantica de fallback em orientacao/local (2026-10-07)

Quando nao existir oficina/especialista apto para o servico solicitado:

- se `GuinchoDisponibilidade::existeNoRaio(...)` retornar `true`, a API deve retornar `fallback_tipo = "guincho"`;
- se retornar `false`, a API deve retornar `fallback_tipo = "suporte"`.

Essa regra vale para `modo=orientacao` e `modo=local`.

O front-end `public-pre-cotacao-flow.js` ja interpreta `fallback_tipo="guincho"` exibindo o botao **Quero rebocar**. A Faixa A deve fornecer apenas a semantica correta no payload.

---

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

---

## Contrato de Resolucao de service_type_id (A -> B)

**Status:** entregue por A em 2026-10-08 (resolver + testes unitarios).
**Dono da escrita:** A (`src/Services/Catalog/ServiceTypeResolver.php`). **Consumidores:** B (`AdminController::pedidoCriar`).

### Metodo entregue

    App\Services\Catalog\ServiceTypeResolver::porSlug(string $slug): ?int

### Semantica

Traduz o slug publico do funil (`pneu`, `eletrica`, `bateria`, `mecanica`, `chaveiro`) para o `id` interno da tabela `service_types`. Mapa propositalmente fechado.

| Slug | Code interno |
| --- | --- |
| `pneu` | `TIRE_CHANGE` |
| `eletrica` | `ELECTRICAL_DIAGNOSIS` |
| `bateria` | `JUMP_START` |
| `mecanica` | `MECHANICAL_ASSISTANCE` |
| `chaveiro` | `AUTOMOTIVE_LOCKSMITH` |

**Fail closed:** retorna `null` para slug desconhecido, catalogo sem o `code`, banco indisponivel ou lookup nao registrado. **Nunca** assume `TOWING` como fallback.

### API de lookup

    App\Services\Catalog\ServiceTypeResolver::definirLookupPorCodigo(
        callable(string): (int|null) $lookup
    ): void

Registrado **uma vez** no bootstrap.

### Bootstrap

| Item | Valor |
| --- | --- |
| Arquivo | `index.php` |
| Quando | apos `require_once` de `config.php` e `Database.php`, antes do dispatcher |
| Chamada | `ServiceTypeResolver::definirLookupPorCodigo(callback)` |
| Callback | lazy - `getPDO()` chamado **dentro** do callback, nao no boot |
| Query | `SELECT id FROM service_types WHERE code = ? AND active = 1 LIMIT 1` |
| Fail closed | `true` - erro de banco loga `STR-LOOKUP-FAIL` e retorna `null` |
| API de conexao | `\getPDO()` - definida em `src/Database.php:25`, singleton lazy |

### Criterio de aceite

- `tests/ServiceTypeResolverTest.php` passa 12/12 (mapa + lookup injetado).
- `tests/ServiceTypeResolverDbTest.php` prova `eletrica -> id real` em ambiente com banco.
- `porSlug('inexistente')` retorna `null`.
- `CONTRATO_RESPOSTA` tem campo `bootstrap:` preenchido.

### Status de implementacao (2026-10-08)

- [x] `ServiceTypeResolver.php` criado com mapa + `definirLookupPorCodigo()`.
- [x] `tests/ServiceTypeResolverTest.php` (12/12 PASS em PHP 8.0.30).
- [x] Namespace `App\Services\Catalog` declarado no resolver.
- [x] Bootstrap registrado no `index.php`.
- [x] `tests/ServiceTypeResolverDbTest.php` (prova real de banco).
- [x] `CONTRATO_RESPOSTA` com `bootstrap:` preenchido.

**Limite (paliativo):** mapa hardcoded de 5 slugs. Servico novo cadastrado no Backoffice nao e coberto -> fail closed. Solucao definitiva: B-009.

---

## Contrato de Catalogo Dinamico (A -> B) - visao B-009

**Status:** aceito por B em 2026-10-08. Implementacao paliativa vigente (`ServiceTypeResolver`); arquitetura definitiva agendada como **B-009**.

**Regra de ouro:** `service_types` e a unica fonte de verdade. Nenhum mapa de slug/code/ID pode permanecer gravado no core.

### Interface canonica dos 3 funis

| Item | Regra |
| --- | --- |
| Formulario | um unico `<form method="post">` por funil |
| Campos | `<input>`/`<select>`/`<radio>` reais |
| Etapas | `<fieldset data-step="...">` (nao forms separados) |
| JS | so apresentacao, validacao e chamadas de cotacao |
| Estado de negocio em JS | **proibido** |
| Hidden preenchido por JS | **proibido** |

### Contrato do `public-pre-cotacao-flow.js`
