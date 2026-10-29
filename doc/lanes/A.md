CONTRATO_RESPOSTA:
  de: A
  para: B

  oficina_id:
    existe: sim
    tabela: pedidos
    tipo: INT(11) NULL
    origem: DecisaoAtendimentoService::oficina_mais_proxima.provider_id
    exposição: Pedido::buscarPorId() já retorna p.*; disponível no array.

  oficina_nome:
    existe: não
    definição: VARCHAR(255) NULL
    origem: snapshot de DecisaoAtendimentoService::oficina_mais_proxima.nome
    exposição: p.* após migration.

  triagem:
    existe: não
    definição: VARCHAR(50) NULL
    origem: decisão operacional do motor (acao ou recomendacao)
    exposição: p.* após migration.

  custo_assistencia:
    existe: não
    definição: DECIMAL(10,2) NULL
    origem: decisao.opcao_assistencia.custo_saida
    exposição: p.* após migration.

  custo_reboque:
    existe: não
    definição: DECIMAL(10,2) NULL
    origem: decisao.opcao_reboque.custo_total
    exposição: p.* após migration.

  recomendacao:
    existe: não
    definição: ENUM('assistencia','reboque') NULL
    origem: decisao.recomendacao
    exposição: p.* após migration.

  origem_funil:
    existe: não
    definição: ENUM('oficina','especialista') NULL
    origem: fluxo que iniciou/criou o pedido
    legado: NULL; a UI deve exibir “—”.
    exposição: p.* em Pedido::buscarPorId() e Pedido::listarPorStatus() após migration.

  worklist_admin:
    confirmação: Pedido::listarPorStatus() seleciona p.*; origem_funil ficará disponível
    sem JOIN adicional após a migration.
ESTADO_FAIXA_A:
  atualizado_em: 2026-10-03
  pagamento_admin_v1:
    status: entregue_para_integracao
    implementacao:
      classe: Pagamento
      metodo: aplicarDestinoPagamento
      assinatura: "aplicarDestinoPagamento(int $pedidoId, string $destino, ?string $provedor = null, ?int $adminId = null): array"
      destinos: [pago_agora, pago_na_chegada, online, sem_cobranca]
      gateway_online: PaymentProviderFactory::gatewayAtivoRaw
      idempotencia: "mesma entrada nao duplica pagamento nem evento; troca de destino e bloqueada"
      link: "somente online: /pagamento/checkout/{pedidoId}"
      eventos:
        pago_agora: pagamento_baixa_manual_registrada
        pago_na_chegada: pagamento_na_chegada_definido
        online: pagamento_online_definido
        sem_cobranca: pagamento_isento
    arquivos:
      alterar: [src/Models/Pagamento.php]
      criar:
        - install/migration_admin_payment_destination_v1.sql
        - install/rollback_admin_payment_destination_v1.sql
    pendencias:
      - "Aplicar a migration no banco antes da integracao da faixa B."
      - "B consome somente o retorno do metodo; A nao altera AdminController, views, CSS ou rotas."

---

# ATUALIZAÇÃO DA FAIXA A — 15/10/2026

> **Âmbito:** exclusivamente ambiente local XAMPP (`C:\xampp\htdocs\guinchafacil`), PHP CLI `C:\xampp\php\php.exe` (8.0.30), MariaDB `guinchafacil_dev`. Esta seção é um registro de execução/observação; não substitui `doc/CONTRATOS.md`, `doc/DECISOES.md` ou a governança `lanes.json`. Nenhum commit ou deploy foi realizado nesta atividade.

## Resumo executivo e estado

| Entrega / controle | Situação em 15/10/2026 | Evidência e ressalva |
| --- | --- | --- |
| Faixa A T1 — migration local | **APLICADA / PASS** | `tools/faixa_a_t1_controlado.php --apply` confirmou colunas, configurações e histórico no `guinchafacil_dev`; `--check` posterior retornou `SKIP` por aplicação já registrada. |
| Backup anterior à T1 | **REALIZADO** | `C:\backup\guinchafacil_dev_t1_20261014.sql` (11.335.990 bytes), SHA-256 `34D09F2DB173C1E069AEBAE2C47EF04AC89378487BBB473BE1770CA75E2F3E5D`. Arquivo local; não versionar. |
| T1 — vetores | **19/19 PASS** | `C:\xampp\php\php.exe tests\faixa_a_t1_vetores.php`; verificação matemática isolada, **não** comprova integração nem persistência. |
| `PrecificacaoService.php` | **PHP -l PASS** | Revisão de decomposição de custos preparada e sintaxe validada; não comprovado como motor usado nos pedidos reais. |
| A3.1 — Writer | **INSTALADO / PHP -l PASS** | `src/Services/Pricing/OrderPriceSnapshotWriter.php` presente como arquivo novo (`git status`: `??`). |
| A3.1 — Core | **INTEGRADO / PHP -l PASS** | `PedidoCoreService.php` importa Writer na linha 9 e chama `OrderPriceSnapshotWriter::persist($this->pdo, (int)$pedidoId, $quote);` na linha 81. |
| A3.1 — transação | **ESTRUTURALMENTE CONFIRMADO; ATOMICIDADE NÃO COMPROVADA** | `beginTransaction()` linha 56 (se não houver transação externa); `Pedido::criar()` linha 60; `persistirContextoPedido()` linha 79; Writer linha 81; `commit()` linha 96; `rollBack()` no `catch` linhas 100–104 se transação iniciada pelo Core. Confirmar compartilhamento de conexão em `Pedido::criar()` e efeitos externos. |
| A3.1 — persistência de snapshot novo | **NÃO TESTADA** | Nenhum pedido novo foi criado durante a homologação; apenas `SELECT`s históricos. |
| C3 — extrato decomposto §20.5 | **BLOQUEADO** | A Faixa B depende de snapshot real, íntegro, completo e conciliado. |

## 1. T1 — migration, histórico e validação

- Aplicada `install/migration_faixa_a_t1_20261014.sql` ao **banco de desenvolvimento** com backup prévio. A verificação reportou adição das colunas/configurações previstas (11 campos e 2 configurações conforme relatório da execução) e registro de migration; execução repetida foi reconhecida como já aplicada.
- Testes do arquivo `tests/faixa_a_t1_vetores.php`: 19 verificações individuais `PASS`, incluindo os vetores 1–11 e decomposição dos vetores 7–10; mensagem final: `[T1][math][PASS] vetores matematicos concluidos (nao testa integracao)`.
- `git diff --check` não apontou erro de whitespace; apenas avisos de conversão LF para CRLF em arquivos locais, sem necessidade de normalização ampla.
- **Divergência histórica não corrigida automaticamente:** a migration `migration_contract_pedido_idempotencia_v1.sql` possui checksum gravado em desenvolvimento diferente do artefato Git recuperável. Não sobrescrever o registro histórico nem supor equivalência. O dump de produção datado de 10/10 tem contagem de migrations diferente e é apenas referência histórica; sem uso para alterações em produção.

## 2. Contrato recebido da Faixa B — A3.1 necessário para C3

```yaml
CONTRATO_PEDIDO:
  de: B
  para: A
  motivo: 'C3 — extrato decomposto §20.5'
  tabela: order_price_snapshots
  gravacao_em: 'PedidoCoreService::criar, após INSERT em pedidos, na mesma transação'
  campos_json_obrigatorios:
    - taxa_saida
    - km_cobrado
    - tarifa_km
    - base_km_contagem
    - fator_veiculo
    - fator_periodo
    - deadhead_ref
    - fator_terreno
    - ganho_elevacao_m
    - component_elevacao
    - component_terreno
    - component_deadhead
    - pedagio
    - comissao_percentual
    - valor_total
    - pricing_version
  colunas:
    customer_total: 'valor_total'
    provider_total: 'valor_total - comissao - reserva_gateway'
    platform_fee: 'comissao'
    pricing_rule_id: 'NULL permitido para motor legado'
    pricing_version: 'NOT NULL'
    snapshot_hash: 'manter'
  comissao_percentual_v2_5: 0.08
  dados_nao_calculados: 'usar valores neutros (1.00/null) e documentar lacunas, nunca inventar medições'
```

**Incompatibilidade de tipos documentada:** `PedidoPricingService::cotar()` expõe identificador textual `pedido-pricing-v1`, enquanto `order_price_snapshots.pricing_version` é `INT(11)`. A integração proposta usa versão numérica `1` na coluna e preserva o identificador textual no JSON; verificar ambos no teste real antes da liberação. A coluna `pricing_version` é hoje anulável no schema, mas o **contrato funcional para registros novos** exige valor não nulo.

A existência dos 16 campos não basta: é preciso verificar a origem dos valores, consistência de centavos, pedágio fora da base de comissão, reserva de gateway, hash, idempotência e imutabilidade.

## 3. Auditoria dos motores de preço — antes e depois

**Encontrado no código local:**

- `src/Services/Pricing/PedidoPricingService.php::cotar()` é o motor consumido pelo `PedidoCoreService`; delega a `TarifaService::calcularDetalhado()`, resolve modalidade e política de intermediação, calcula `total = frete + intermediacao_final` e devolve `pricing_version = 'pedido-pricing-v1'`.
- `src/Services/Precificacao/PrecificacaoService.php` contém motor separado e gravação própria de snapshot quando recebe `pedido_id`; busca nos PHP de `src` não encontrou chamadas diretas `PrecificacaoService::calcular()`. **Não substituir os motores indiscriminadamente nem afirmar que a fórmula revisada já é usada pelo Core.**
- Há cálculo por zonas em `ConversionService`; `TarifaService` também é usado por `AuthController`, `ClienteController` e outros serviços. O risco de divergência entre caminhos permanece.
- A revisão de `PrecificacaoService` preparou componentes `component_elevacao`, `component_terreno` e `component_deadhead`, base de km, ajuste piso/teto e conciliação; é conservadora quanto à fórmula legada de terreno. **Não constitui homologação de terreno por taxa fixa, relevo efetivo ou pedágio real.**

**Lacunas a medir:** `RelevoService` (subida/descida/efetivo), `GuinchoDisponibilidade` (anel/deadhead), `RoutingService` (distância rodoviária e pedágio), além dos efeitos de prioridade, piso/teto e taxas. A versão examinada do Core calcula distância por Haversine; reconciliar com a exigência de distância rodoviária oficial. Não registrar dados estimados como medições reais.

## 4. Integração A3.1 instalada no Core

**Arquivos envolvidos**

- `src/Services/PedidoCoreService.php` — alteração instalada em XAMPP pelo script de correção, com backup local `src/Services/PedidoCoreService.php.before-a31-c3-20261014-162713.bak`.
- `src/Services/Pricing/OrderPriceSnapshotWriter.php` — arquivo novo, sintaxe `PASS`; `git status --short` mostrou `??` (não versionado).
- `src/Services/Pricing/PedidoPricingService.php` — fonte da cotação que chega ao Writer; não substituída neste patch.
- `src/Services/Precificacao/PrecificacaoService.php` — revisão matemática em trilha paralela.
- `tools/aplicar-a31-c3-core-xampp.ps1` — instalador executado localmente com `powershell.exe -NoProfile -ExecutionPolicy Bypass -File ...` após bloqueio de assinatura pelo PowerShell. O uso de Bypass ocorreu em processo isolado, sem alterar permanentemente a política; revisar scripts antes de novas execuções.

**Alteração efetivamente demonstrada por `git diff`:**

```php
require_once __DIR__ . '/Pricing/OrderPriceSnapshotWriter.php';
// ... dentro de criar(), após Pedido::criar() e persistirContextoPedido():
$this->persistirContextoPedido($pedidoId, $quote, $request);
OrderPriceSnapshotWriter::persist($this->pdo, (int)$pedidoId, $quote);
```

**Saída do instalador:** `[A31][PASS] Integracao inserida em PedidoCoreService`; `php -l` do Core e do Writer: `No syntax errors detected`.

A chamada está antes do `commit` e dentro de `try/catch`. Quando o Core inicia a transação, o `catch` efetua rollback em caso de exceção. Quando recebe transação externa, cabe ao chamador fazer rollback. **Ainda não foi verificado** se `Pedido::criar()` utiliza rigorosamente o mesmo PDO ou se há efeitos externos não transacionais.

## 5. Mapeamento dos caminhos de criação

| Entrada | Caminho encontrado | Cobertura A3.1 |
| --- | --- | --- |
| `AdminController.php` linhas ~1666–1677 | `new PedidoCoreService` → `->criar(new PedidoCreateRequest(...))` | Incluída no Core |
| `ClienteController.php` linhas ~908–911 | `new PedidoCoreService` → `->criar(...)` | Incluída no Core |
| `PedidoController.php` linhas ~117 e 149 | `core()->criar(...)` | Incluída no Core |
| `OrcamentoPrevioService.php` linha ~122 | Instancia `PedidoCoreService`; confirmar se é somente cotação | Inspecionar |
| `CheckoutController.php` linha ~46 | `Pedido::criar(...)` diretamente | **Fora do Core** |
| `SimulationService.php` linha ~279 | `Pedido::criar(...)` diretamente | **Fora do Core** |
| `PedidoService.php` linha ~103 | `$this->pedidoModel->criar(...)` | **Fora do Core ou a verificar** |
| `ConversionService.php` | `ZonePricingService` / `TarifaService` no recálculo | Contrato de reprecificação a definir |

A cobertura do Core **não equivale a cobertura de todos os fluxos da plataforma**. Não migrar `CheckoutController`, simulador ou serviços de outra faixa sem aprovação de fronteiras e testes de regressão.

## 6. Evidência do banco e ausência legítima de snapshots novos

- `order_price_snapshots` possui índice `uk_order_price_snapshot_order` (`UNIQUE(order_id)`); não foi necessário criar outro índice.
- A tabela continha somente um registro histórico: `id=1`, `order_id=3`, `pricing_version=NULL`, JSON de 3 chaves, `customer_total=27.50`, `provider_total=24.53`, `platform_fee=1.60`; contrato C3 `0` nesse registro. **Não alterar retroativamente o histórico.**
- Consultas `SELECT` sobre pedidos mais recentes localizaram IDs **66 a 70**, todos sem snapshot e criados antes do patch: ID 70 em `2026-10-03 09:32:34`, ID 69 em `2026-09-29 23:07:57`, ID 68 em `2026-09-29 19:34:36`, ID 67 em `2026-08-30 15:39:06` e ID 66 em `2026-07-12 11:51:27`.
- **Nenhum pedido foi criado nesta rodada de diagnóstico**. Foram executadas apenas consultas SQL `SELECT`; resultados sem snapshot nesses pedidos antigos são esperados e **não provam falha do Writer**.
- `Get-Content C:\xampp\apache\logs\error.log -Tail 150 | Select-String ...` não trouxe ocorrências dos padrões `OrderPriceSnapshotWriter`, `PedidoCoreService`, `SQLSTATE`, `PRICE-`; isso não equivale a teste runtime nem exclui erros registrados em outros logs ou períodos.

## 7. Aceite pendente — plano objetivo de homologação segura

1. **Teste de persistência:** usar pedido fictício novo pelo funil cliente/admin no XAMPP **ou**, preferencialmente, um harness automatizado com transação/rollback comprovados. Não processar PIX real, não notificar parceiros, não chamar gateways externos; não executar no ambiente de produção.
2. **Atomicidade:** confirmar `Pedido::criar()` com mesmo PDO e sem commit/autoconexão independente; se transação pré-existente, validar tratamento de falha pelo chamador.
3. **Contrato JSON:** verificar presença e semântica das 16 propriedades do §20.5, inclusive `ganho_elevacao_m` objeto `{subida,descida,efetivo}` ou `null`, `base_km_contagem` enum e `comissao_percentual=0.08` quando aplicável.
4. **Conciliação:** `customer_total=valor_total=preço do pedido`; `platform_fee=comissao`; `provider_total=customer_total-comissao-reserva_gateway` com precisão de centavos e pedágio excluído da base de comissão. Testar desconto de continuidade e modalidade ON_SITE, além de TOWING.
5. **Idempotência/segurança:** hash estável, tentativa de duplicação por `order_id` não altera registro, erro propaga exceção, rollback consistente e sem resposta de sucesso falso.
6. **Cobertura:** inventariar e tratar os caminhos diretos de criação do pedido, sem quebrar fronteiras de faixas. Documentar fatores neutros sem simular valores reais.
7. **Entregar evidências à Faixa B:** consulta de snapshot novo, versão válida, JSON completo, totais conciliados, logs/resultado da homologação; apenas então considerar liberação do C3.

**Decisão em 15/10/2026:** `A3.1 = integrado estruturalmente, homologação funcional PENDENTE`; `C3 = BLOQUEADO`. Não criar snapshots manuais para fazer o teste passar; não fazer commit/deploy até a liberação expressa do dono.

## 8. Próximos comandos de leitura (sem alterações)

```powershell
Set-Location 'C:\xampp\htdocs\guinchafacil'
& 'C:\xampp\php\php.exe' -l 'src\Services\PedidoCoreService.php'
& 'C:\xampp\php\php.exe' -l 'src\Services\Pricing\OrderPriceSnapshotWriter.php'
git diff --check
git status --short -- 'src/Services/PedidoCoreService.php' 'src/Services/Pricing/OrderPriceSnapshotWriter.php'
```

```sql
SELECT p.id, p.criado_em, p.custo_estimado,
       s.id AS snapshot_id, s.pricing_version,
       JSON_LENGTH(s.calculation_context_json) AS campos,
       s.customer_total, s.provider_total, s.platform_fee
FROM pedidos p
LEFT JOIN order_price_snapshots s ON s.order_id = p.id
ORDER BY p.id DESC LIMIT 5;
```

**Nota de governança:** somente a cópia desta documentação foi preparada aqui. A edição do arquivo real em `C:\xampp\htdocs\guinchafacil\doc\lanes\A.md` exige aplicação local explícita; nenhuma modificação remota ou no Windows foi realizada nesta sessão.

---

## T2 — aplicarDestinoPagamento (validado 2026-10-15)

**Evidência informada pelo responsável:** `tests/t2_destino_pagamento.php` (4 saídas + idempotência + conflito + split §5.7).  
**Status:** **PASSOU — 0 falhas (exit 0)**, conforme resultado de homologação comunicado em 15/10/2026. O log bruto do teste não foi anexado a esta atualização documental.

### Resumo por destino

| Destino | Evento gravado | `pagamentos` | Link | Split §5.7 |
|---|---|---|---|---|
| `pago_agora` | `pagamento_baixa_manual_registrada` | 1 linha (`metodo=dinheiro`) | `null` | Reserva redistribuída ao guincho (R$ 707,45) |
| `pago_na_chegada` | `pagamento_na_chegada_definido` | Nenhuma | `null` | — |
| `online` | `pagamento_online_definido` | 1 linha (`metodo=mercadopago`) | `/pagamento/checkout/{id}` | 669,27 + 59,15 + 38,18 = 766,60 |
| `sem_cobranca` | `pagamento_isento` | Nenhuma | `null` | — |

### Validado nos quatro destinos

- `ok=true`, `erro=null` na chamada inicial.
- Evento com `ator_tipo=admin` e `ator_id=1`.
- Segunda chamada com o mesmo destino: `idempotente=true`; evento não é duplicado.
- Terceira chamada com destino diferente: `ok=false` e erro preenchido (conflito protegido).

### Validado no `online` (§5.7 / §20.3, vetor 7)

- `guincho == provider_total` do snapshot: **R$ 669,27**.
- `plataforma == platform_fee` do snapshot: **R$ 59,15**.
- Conciliação: **669,27 + 59,15 + 38,18 = 766,60** (parceiro + plataforma + reserva de gateway = total).
- Comissão: `plataforma == base_comissao × comissao_plataforma`; **739,40 × 0,08 = 59,15** após arredondamento.

### Validado no `pago_agora`

- `pagamentos.metodo=dinheiro`.
- Reserva de crédito redistribuída ao guincho na liquidação manual, sem mutação do snapshot: **669,27 + 38,18 = 707,45**.

### Observações para T4 — não bloqueiam T2

1. **`pagamentos.comissao_plataforma` permanece `NULL`:** somente `valor_plataforma` é preenchido. As colunas possuem propósitos potencialmente diferentes; em T4, decidir entre documentar a distinção, retirar a coluna não utilizada ou preencher ambas conforme contrato e impacto de compatibilidade.
2. **`pagamentos.id_externo` permanece `NULL` em `online`:** esperado na criação; o identificador do Mercado Pago é recebido posteriormente pelo webhook. Homologação ponta a ponta do webhook, se exigida, será **T2b**, com teste específico.
3. **`logs_webhook` não possui `UNIQUE (origem, evento)`:** drift da migration `migration_contract_pedido_idempotencia_v1.sql`, aplicada em 10/10/2026 e com checksum histórico divergente. Sem a restrição, a idempotência atômica de webhook não está garantida no desenvolvimento. Corrigir em T4 com migration evolutiva e saneamento de duplicidades, **sem reescrever o checksum histórico**.
4. **`order_price_snapshots.provider_total` permanece imutável:** em `pago_agora`, a redistribuição acontece em `pagamentos`; não sobrescrever o snapshot original, conforme §5.8.

### Separação de marcos e governança

- **T2 — `aplicarDestinoPagamento`:** PASSOU de acordo com a evidência de execução comunicada; 4 destinos, idempotência, conflito e split §5.7 cobertos.
- **A3.1/C3 — snapshot na criação real do pedido:** **continua pendente de homologação integrada**. O teste T2 utiliza snapshots financeiros para conciliação, mas **não comprova** que `PedidoCoreService::criar()` gravou automaticamente um novo `order_price_snapshots` após a instalação do Writer.
- **T4:** concentrar as três pendências de esquema/semântica registradas acima e avaliar testes do webhook. Não alterar produção; não fazer commit/deploy sem liberação do dono.


<!-- GF-FIN1-CONTRATO-20261018 -->
### FIN.1 â€” GestÃ£o financeira e repasses (18/10/2026)

- AdoÃ§Ã£o incremental do layout Lovable de referÃªncia em `/admin/financeiro`, com sidebar `100dvh`, logotipo, ausÃªncia de topbar legado e conservaÃ§Ã£o dos filtros, KPIs, CSV, PIX jobs e aÃ§Ãµes preexistentes.
- Contrato bilateral: pagamento pelo gateway cria crÃ©dito contratual ao parceiro; pagamento diretamente ao parceiro cria obrigaÃ§Ã£o de comissÃ£o Ã  plataforma. Fechamento semanal compensa ambos por parceiro e ciclo.
- `saldo_parceiro = creditos - debitos`; saldo positivo implica repasse, zero compensaÃ§Ã£o, negativo dÃ­vida do parceiro. InadimplÃªncia apenas sobre dÃ­vida lÃ­quida vencida apÃ³s carÃªncia e conciliaÃ§Ã£o.
- Matching anelar progressivo: referÃªncia comum por faixa = maior custo de deslocamento entre elegÃ­veis da faixa. Oficinas sÃ£o responsÃ¡veis por remoÃ§Ã£o incluÃ­da conforme cadastro e oferta.
- Esta fase Ã© APENAS visual/documental; a implementaÃ§Ã£o do razÃ£o bilateral e sua migraÃ§Ã£o exigem auditoria e homologaÃ§Ã£o prÃ³prias (FIN.2+).
- Detalhes e critÃ©rios: `doc/FIN_1_GESTAO_FINANCEIRA_REPASSES.md`.