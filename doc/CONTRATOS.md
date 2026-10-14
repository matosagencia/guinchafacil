# Contratos de integração

> **Versão 2.0 — 12/10/2026.** Fusão consolidada. Schema verificado contra o dump de `guinchafacil_dev` (120 tabelas).
> **Contrato canônico.** Versões anteriores devem ser arquivadas.

---

## §1. Triagem → registro → checkout

| Item | Contrato |
|---|---|
| Chave principal | `triage_session_id` |
| Formato | 32 hex minúsculos, `bin2hex(random_bytes(16))`, `^[a-f0-9]{32}$` |
| Payload privado | `$_SESSION['triage_sessions'][triage_session_id]` |
| Validade | 30 minutos após finalização |
| Escrita | `PreCotacaoApiController::finalizar()` via `TriageSessionService::finalizar()` |
| Leitura | `CheckoutController::veiculo()` e `pagar()` via `TriageSessionService::reconstruir()` |

**Sequência obrigatória:**
1. `POST /api/pre-cotacao/finalizar` (ou `/pre-cotacao/finalizar`) com origem, destino, distância, custo e tipo.
2. Controlador grava `$_SESSION['triage_session_id']` e payload sob o mesmo ID; retorna ID + URL `/checkout/veiculo?triage_session_id=<id>`.
3. Registro e login preservam a sessão e redirecionam para essa URL. ID nunca é recriado pelo cliente.
4. `/checkout/pagar` cria pedido **uma única vez**; `pedido_id` fica vinculado ao payload.

**Observabilidade:** `TRIAGE-SAVE`, `TRIAGE-RESTORE`, `TRIAGE-REJECT`, `TRIAGE-EXPIRED`, `TRIAGE-ORDER-LINK`, `CHECKOUT-TRIAGE-VEHICLE-FAIL`, `CHECKOUT-TRIAGE-ORDER-FAIL`.

---

## §2. Documento de pessoa

`usuarios.cpf` = `VARCHAR(18) YES UNI NULL`. Gravação: **apenas dígitos** (sem máscara). Migration `install/migration_expand_usuario_documento.sql` — idempotente via `INFORMATION_SCHEMA`. Formatação de exibição via helper de view.

---

## §3. Contrato Pedido v1 — **já implementado no banco**

| Campo do contrato | Coluna real | Tipo |
|---|---|---|
| `oficina_id` | `pedidos.oficina_id` | `INT(11) NULL` |
| `oficina_nome` | `pedidos.oficina_nome` | `VARCHAR(255) NULL` |
| `triagem` | `pedidos.triagem` | `VARCHAR(50) NULL` |
| `custo_assistencia` | `pedidos.custo_assistencia` | `DECIMAL(10,2) NULL` |
| `custo_reboque` | `pedidos.custo_reboque` | `DECIMAL(10,2) NULL` |
| `recomendacao` | `pedidos.recomendacao` | `ENUM('assistencia','reboque') NULL` |
| `origem_funil` | `pedidos.origem_funil` | `ENUM('oficina','especialista') NULL` |
| `triage_session_id` | `pedidos.triage_session_id` | `CHAR(32) NULL` UNIQUE |

**Exposição:** `Pedido::buscarPorId()` e `Pedido::listarPorStatus()` retornam `p.*`. `origem_funil` disponível sem JOIN adicional.

**Idempotência:** `Pedido::criarCompleto()` devolve o ID existente se `triage_session_id` já houver.

**Estado:** **B-002 e B-004 fechados no banco.** Nenhuma migration nova necessária.

---

## §4. Idempotência Mercado Pago v1

```
X-Idempotency-Key: ped{pedido_id}-t{tentativa}
```
Tentativa inicia em `1`, mantida em reenvios, avança só após falha. Cobranças complementares preservam chave própria.

---

## §5. Precificação

**Fonte única:** `service_price_rules` (tabela versionada, com vigência e `version`).

**Referência de mercado:** Ache Guincho 2026 - linear, taxa de saída R$ 120-250 + R$ 5-8/km para carro de passeio.

**Tabelas aposentadas:** `service_pricing_rules` (vazia, congelar) e as chaves `tarifa_*` em `configuracoes` (migrar e congelar).

### §5.1 Fórmula base

Preco_cliente = taxa_saida
              + [(km_transporte x tarifa_km) x fator_veiculo x fator_periodo]
              + deadhead_do_anel
              + fator_terreno_fixo
              + pedagio_repassado

### §5.2 Base de km - decisão obrigatória

`service_price_rules.base_km_contagem` ENUM('transporte','percurso_completo','percurso_efetivo').

- `transporte`: apenas coleta -> destino. Deadhead absorvido na taxa de saída.
- `percurso_completo`: deadhead + transporte + retorno.
- `percurso_efetivo`: deadhead + transporte, sem retorno.

Sem esta coluna preenchida, a cotação não pode ser gerada. O erro de Campinas nasceu da ausência dela.

### §5.3 Colunas novas em service_price_rules

| Coluna | Tipo | Função |
|---|---|---|
| `base_km_contagem` | ENUM | Qual km está sendo cobrado |
| `fator_veiculo` | DECIMAL(4,2) | Moto, carro, SUV |
| `fator_periodo_noturno` | DECIMAL(4,2) | 1,20-1,50 |
| `fator_periodo_fds` | DECIMAL(4,2) | 1,20-1,50 |
| `fator_periodo_feriado` | DECIMAL(4,2) | 1,20-1,50 |
| `taxa_terreno_leve` | DECIMAL(10,2) | R$ 40-80 |
| `taxa_terreno_acentuado` | DECIMAL(10,2) | R$ 80-150 |
| `taxa_manobra_ladeira` | DECIMAL(10,2) | R$ 50-120 |

### §5.4 Fatores

- `fator_veiculo`: multiplica `(km × tarifa)`.
- `fator_periodo`: multiplica `(km × tarifa)`.
- Terreno — taxas fixas, não multiplicam:
  - leve (grade ≥ 6% por ≥ 1 km): R$ 40–80
  - acentuado (grade ≥ 8%): R$ 80–150
  - manobra em ladeira no hook-up: R$ 50–120
- **Ganho de elevação total** — multiplicador sobre o bloco variável
  `(km × tarifa × fator_veiculo × fator_periodo)`:
  - 0–100 m: 1,00
  - 100–300 m: 1,05
  - 300–600 m: 1,10
  - 600–1000 m: 1,18
  - >1000 m: 1,25
  Configurável em `configuracoes.ganho_elevacao_faixas` (metros) e
  `configuracoes.ganho_elevacao_mult` (multiplicadores).
- **Altitude absoluta não cobra.** Fica como dado operacional/informativo
  no pedido. Distinto de ganho de elevação: estar a 750 m de altitude
  numa rua plana não custa; subir 750 m contra a gravidade custa.

### §5.5 Deadhead por anel

O deadhead é do anel, não do guincho individual. Ver §7.

### §5.6 Pedágio

Repassado integralmente, com comprovante. Guincho plataforma 2 eixos. Veículo em cima da plataforma não gera cobrança autônoma (Free Flow).

### §5.7 Comissão

Take rate: **8% flat sobre o valor BRUTO do pedido**.

**Fonte única:** `configuracoes.comissao_plataforma` (decimal, ex.: `0.08`).
**Leitura:** exclusivamente via `Configuracao::get('comissao_plataforma')`.
Nenhum valor hardcoded em código.

**Gateway — reserva dinâmica por método de pagamento:**

O valor real do gateway depende do método escolhido pelo cliente. A reserva
é feita em duas etapas:

1. **Na cotação** (método ainda desconhecido): usar `reserva_gateway_credito`
   (crédito à vista, pior caso comum). O cliente vê o preço final com a
   reserva máxima.
2. **No webhook de aprovação**: o método real é detectado pelo payload do
   gateway. Se foi PIX (`reserva_gateway_pix`, 0,49%), a diferença entre
   a reserva máxima e a real cai automaticamente no repasse do guincho.

**Chaves de reserva (configuráveis):**
- `reserva_gateway_pix` = 0,49%
- `reserva_gateway_debito` = 1,99%
- `reserva_gateway_credito` = 4,98% (default na cotação)
- `reserva_gateway_boleto` = 1,99%
- `reserva_gateway_percentual` = 4,98% (fallback legado)

**Fórmula do split (§5.7):**

```
valor_plataforma = total × comissao_plataforma
valor_gateway = total × reserva_gateway (dinâmica por método)
valor_guincho = total − valor_plataforma − valor_gateway
```

Os três somam exatamente o total. Sem estouro.

**Exibição ao guincho:**

```
Você receberá: R$ 794,85
Retenção GuinchaFácil: R$ 113,55
```

Comissão + gateway consolidados em um único número. O detalhamento
interno fica no `pedido_eventos` e no painel financeiro do backoffice.

**Regras:**
- `comissoes_cidade` desativada até haver densidade para diferenciação.
- Tabela tiered por faixa de km (12/10/8/6) **não é oficial**. Descartada.
- Teto por corrida: sem teto por enquanto.

**Chaves aposentadas:**
- `comissao_assistencia_percentual` — removida.
- `service_price_rules.platform_fee_value` — override por serviço, opcional.

### §5.8 Detalhamento obrigatório

Toda cotação grava em `order_price_snapshots`:
- `taxa_saida`, `km_transporte`, `tarifa_km`, `base_km_contagem`
- `fator_veiculo`, `fator_periodo` aplicado
- `deadhead_ref` e anel usado
- `fator_terreno` aplicado
- `pedagio`
- `comissao` (8%)
- `valor_total`

### §5.9 Estorno parcial

Quando um guincho de anel interno aceita após cotação em anel externo, estorno automático da diferença de deadhead.

- Mercado Pago: `POST /v1/payments/{id}/refunds` com `{ "amount": <valor> }`
- PagSeguro: `POST /charges/{id}/cancel` com `{ "amount": <valor> }`
- Trilha em `pedido_eventos` tipo `estorno_diferenca_deadhead`
- Retry com backoff se saldo insuficiente; alerta no admin

---

## §6. Motor de preço — fonte única

**Schema verificado (dump 12/10/2026):**

- **`service_price_rules`** (tabela canônica):
  `pricing_zone_id`, `service_type_id`, `vehicle_category`, `base_customer_price`, `minimum_customer_price`, `maximum_customer_price`, `provider_base_amount`, `platform_fee_type`, `platform_fee_value`, `included_distance_km`, `extra_distance_price`, `included_minutes`, `extra_minute_price`, `night_multiplier`, `holiday_multiplier`, `effective_from`, `effective_until`, `active`, `version`.
  `vehicle_category VARCHAR(30) DEFAULT NULL` **já existe**.
  `platform_fee_type` + `platform_fee_value` **já existem**.

- **`service_pricing_rules`** (0 linhas) — **congelada** (desenho antigo `base_fee`/`pickup_km_price`/`tow_km_price`).
- **`provider_quote_rules`** (legado por provedor) — **congelado**.
- **`order_price_snapshots`** — pronta. `UNIQUE order_id`. FK para `service_price_rules`.
- **`comissoes_cidade`** — pronta, vazia. `UNIQUE cidade_id`.
- **`configuracoes`** — chave/valor. Chaves `tarifa_*` a migrar.

**Take rate (D-P2 — 12/10/2026):**
- 0–30 km: 12%
- 31–80 km: 10%
- 81–150 km: 8%
- >150 km: 6% ou R$ 60 (o maior)
- Assistência local: R$ 25 fixos + 5%

**Precedência:** `service_price_rules.platform_fee_value` > `comissoes_cidade.percentual` > `configuracoes.comissao_plataforma`.

**Valores base (D-P1 — 12/10/2026):**
- `taxa_engate` = R$ 200,00 · `tarifa_km` = R$ 5,80 · piso > 80 km = R$ 650,00
- `night_multiplier` = 1,20 · `holiday_multiplier` = 1,30
- Fórmula: `preço = engate + (km × tarifa) × fator_terreno + pedágio`

Detalhes em `doc/PRECIFICACAO.md` v1.1.

---

## §7. Disponibilidade (B->A)

**Interface antiga:** `GuinchoDisponibilidade::existeNoRaio()` - deprecada.

**Interface nova:**

GuinchoDisponibilidade::candidatosPorAnel(
    float $lat, float $lng, int $service_type_id
): array

Retorno por anel: `candidatos[]`, `deadhead_ref`, `elegiveis`.

### §7.1 Anéis

Configuráveis no admin. Default: 2, 5, 10, 20, 35 km.

### §7.2 Filtro duro

`aprovado = 1 AND disponivel = 1 AND reboque_aprovado = 1
AND NÃO bloqueado financeiramente (§9)
AND dist_km <= raio_maximo`

### §7.3 Deadhead de referência

Sempre o **centro do anel**. Não é a média, não é o mais distante.

### §7.4 Regra de escolha do anel

Menor anel com **>= 3 elegíveis**. O `deadhead_ref` daquele anel é aplicado a todos os candidatos daquele anel, garantindo igualdade de preço.

Se nenhum anel até 35 km tiver >= 3 elegíveis: fallback WhatsApp.

### §7.5 Ordenação interna

1. Menor distância real
2. Maior reputação
3. Aceites recentes
4. Aleatório

### §7.6 Estorno automático

Se o cliente já pagou deadhead de anel externo e um guincho de anel interno aceita, estorno parcial automático (ver §5.9).

### §7.7 Bloqueio financeiro (§9)

Bloqueado não conta como elegível. Não recebe oferta. **Não recebe notificação até quitar.**

---

## §8. Catálogo Dinâmico (B-009)

**Fonte:** tabelas `service_categories` → `service_types` → `service_price_rules`.

**Estrutura:**
- `service_categories`: `code`, `name`, `icon`, `active`, `sort_order`
- `service_types`: `category_id`, `code`, `name`, `attendance_mode ENUM('TOWING','ON_SITE','HYBRID')`, `requires_destination`, `requires_diagnostic`, `requires_before_evidence`, `requires_after_evidence`, `active`, `is_system`, `is_removable`, `can_disable`
- `service_price_rules`: preço por `pricing_zone_id` + `service_type_id` + `vehicle_category`

**Regra de negócio:**
1. O funil lista `service_types WHERE active = 1`, agrupado por `service_categories.sort_order`.
2. Cliente escolhe um `service_type_id`.
3. `attendance_mode` do serviço define o fluxo:
   - `TOWING` → exige destino, calcula reboque
   - `ON_SITE` → não exige destino, calcula assistência local, filtra oficinas
   - `HYBRID` → oferece ambos
4. `PedidoCoreService::criar()` deriva a modalidade do catálogo — **não do `tipo_problema` VARCHAR**.

**Regra proibida:** o funil **não** deve usar `service_type_id` fixo por slug. Deve consultar o catálogo ativo.

**Consumidores:** views públicas, admin, cliente (3 funis).

---

## §9. Bloqueio Financeiro Operacional (B-010)

**Contrato aceito pela Faixa A.**

**Interface:**
```php
BloqueioFinanceiroService::guinchoEstaBloqueado(int $guinchoId): bool
```

**Fonte:** tabela `faturas_parceiro`, colunas:
- `parceiro_tipo ENUM('guincho','oficina','especialista')`
- `parceiro_id BIGINT`
- `status ENUM('aberta','paga','vencida','bloqueada','cancelada')`
- `vencimento_em DATETIME`
- `saldo DECIMAL(12,2)`

**Regra:** retorna `true` se existe ao menos 1 fatura com `parceiro_tipo = 'guincho'`, `parceiro_id = :id` e `status IN ('vencida','bloqueada')`.

**Uso:** o parceiro continua recebendo ofertas no painel, mas ao **aceitar** um pedido o sistema verifica antes e, se bloqueado, exibe modal de pagamento pendente.

**Consumidor:** `OficinaController` e `GuinchoController` (Faixa B) exibem o badge; o bloqueio real é do motor (A).

---

## §10. Relevo v2 e Prioridade (D19/D20)

**Interface `RelevoService` (Faixa A):**
```php
RelevoService::fatorPorOrigem(float $lat, float $lng): array
// Retorno: ['fator_relevo' => float, 'inclinacao_graus' => ?float,
//           'segmento_encontrado' => bool, 'origem_fator' => string,
//           'via_segmento_id' => ?int]
```

**Migrations pendentes (A):** `install/migration_via_segmentos_v1.sql`, `install/migration_auditoria_prioridade_v1.sql`.

**Fórmula e réguas:** ver `doc/PRECIFICACAO.md` v1.1 §5 e `doc/DECISOES.md` D19/D20.

**Prioridade (D16/D20):** `prioridade_status` em `pedidos`. Estado inicial: `nao_solicitada` ou `pendente_confirmacao`. Transições registradas em `pedido_eventos` com ator, data, status anterior, novo e motivo.

---

## §11. Interface A↔B (`CONTRATO_PEDIDO` / `CONTRATO_RESPOSTA`)

Formato dos blocos emitidos entre faixas.

```
CONTRATO_PEDIDO:
  de: B
  para: A
  motivo: <por que B precisa>
  contrato_esperado:
    endpoint: <rota>
    body: <JSON>
    response_200: <JSON>
    response_erro: <JSON>
  impacto_se_nao_implementar: <consequência>
  prioridade: <ALTA|MÉDIA|BAIXA>
```

```
CONTRATO_RESPOSTA:
  de: A
  para: B
  <nome_do_campo>:
    existe: sim | não
    tabela: <nome>
    tipo: <tipo SQL>
    definição: <DDL>            # se precisa criar
    origem: <onde o motor gera>
    exposição: <como B acessa>
    legado: <o que fazer com dados antigos>
```

---

## Registro de alterações

| Data | Mudança |
|---|---|
| 2026-09-30 | Criação inicial (vA) |
| 2026-10-08 | vB adiciona catálogo, bloqueio, financeiro, relevo, prioridade |
| 2026-10-12 | **v2.0** — Fusão consolidada + schema verificado contra dump. §3, §7, §8, §9 fechados. |
