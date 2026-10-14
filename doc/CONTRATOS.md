# Contratos de integração

> **Versão 2.5 — 14/10/2026 (v2.4 + decisões do dono de 14/10 sobre as pendências 1, 3, 4, 5 e 8 e sobre o §9).** Base: "v2.0 fusão consolidada" (do dono) + decisões do dono de 12/10 + seções que a v2.0 não tinha (§12–§19, vindas da v2.1).
> **Contrato canônico.** Versões anteriores devem ser arquivadas. Só o dono edita; agentes propõem via CONTRATO_PEDIDO (R2: contrato antes do código).
> Marcas: **[DECIDIDO]** = decisão do dono · **[VERIFICADO]** = conferido por execução/dump · **[ABERTO]** = o dono ainda precisa decidir · *não verificado* = confirmar com o código/banco antes de virar DDL.

## 0. Decisões do dono aplicadas nesta versão

| # | Decisão | Onde |
|---|---|---|
| D-1 | **Comissão: 8% fixos no início, subindo com o tempo** (modelo Uber). A escada 12/10/8/6% da v2.0 fica **fora** | §5.7, §6 |
| D-2 | **§6 permanece** (motor de preço, valores D-P1, precedência de comissão), com a comissão acima | §6 |
| D-3 | **§9 permanece**; parceiro bloqueado **não pode ver ofertas** (resolve a contradição interna da v2.0) | §7.7, §9 |
| D-4 | Altitude absoluta não cobra, mas **ganho de elevação cobra** (subir 750 m é custo mesmo com rua plana no alto). Guincho conta os dois trechos; assistência só o deslocamento do profissional e **só a subida** | §5.4, §10 |
| D-5 | Demais seções da v2.0 (anéis, estorno parcial, CPF só dígitos, B-002/B-004 fechados) adotadas como contrato | §2, §3, §5.5, §5.9, §7 |
| D-7 | **Piso de R$ 650 acima de 80 km removido**; a proteção de preço mínimo fica em `service_price_rules.minimum_customer_price` | §6 |
| D-8 | **Terreno em taxa fixa** (R$); o fator por grau da v1 fica substituído | §5.4, §10.4 |
| D-9 | o parceiro é bloqueado (`bloqueada`) **após 5 dias de carência** do vencimento (D-15), **mas o gate só é ligado depois de provado o pagamento** (webhook PIX aprovando e desbloqueando) | §9 |
| D-10 | "Sozinho em local deserto" = `local_isolado` (só dispatch, sem revisão e sem penalidade) | §16 |
| D-11 | Comissão crescente com **gatilho por saúde da rede** (valores ainda a definir) | §5.7 |
| D-12 | Rota do preço = **mais rápida/recomendada** | §5.2, §21 |
| D-13 | **Fator de moto 0,70 retirado** (sem fonte); moto = 1,00 até haver fonte | §5.4 |
| D-6 | Ordem de trabalho escolhida em 13/10: **T1** motor de preço → **T2** teste real de `aplicarDestinoPagamento` → **T3** extrato decomposto da fatura → **T4** limpeza (blocos T1–T4, para não confundir com as faixas A/B/C) | §20 |
| D-14 | **Comissão permanece 8%** (decisão de 14/10). A comissão crescente por saúde da rede (D-11) fica suspensa até o dono definir indicadores, limiares, degrau, teto e aviso | §5.7 |
| D-15 | **§9:** carência de **5 dias** após o vencimento antes de bloquear; o bloqueado só volta às ofertas ao quitar o débito **com juros**; o painel continua acessível para pagar; pedidos já aceitos seguem até a conclusão | §9 |
| D-16 | **Terreno em R$ mantido** nas faixas do §5.4: a inclinação da via eleva o risco e o custo de mão de obra | §5.4, §10.4 |
| D-17 | **Free Flow presumido em todas as praças** (cenário mais desfavorável); o pedágio entra sempre na cotação | §5.6 |
| D-18 | **Bancos:** dev = o do XAMPP; produção = `_guinchafacil` (nome exato do schema dev a confirmar com A) | §19, §20 |

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

Persistência: `triage_sessions` já existe (6 linhas). `pre_quote_demand_cells` só conta por célula/dia. **Cada cotação individual ainda não é registrada** (ver `cotacoes`, §5.10).

---

## §2. Documento de pessoa

`usuarios.cpf` = `VARCHAR(18) YES UNI NULL`. Gravação: **apenas dígitos** (sem máscara); valores antigos com máscara são tolerados na leitura. Migration `install/migration_expand_usuario_documento.sql`, idempotente via `INFORMATION_SCHEMA`. Formatação de exibição via helper de view.

---

## §3. Contrato Pedido v1 — implementado no banco

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

**Exposição:** `Pedido::buscarPorId()` e `Pedido::listarPorStatus()` retornam `p.*`. **Idempotência:** `Pedido::criarCompleto()` devolve o ID existente se `triage_session_id` já houver.
**Regras:** B consome só estes campos; campo novo = CONTRATO_PEDIDO a A; mudança de nome/tipo/significado = Contrato Pedido v2.
**Estado:** B-002 e B-004 fechados no banco (conforme v2.0). Validar com `SHOW COLUMNS FROM pedidos` antes de arquivar os bloqueios.

**Dívida de schema (não bloqueia):** `pedidos` tem ~80 colunas com duplicatas (`raio_atual_km`/`raio_busca_km`, `score_minimo_atual`/`score_minimo`, `expiracao_aceite`/`expira_em`, `custo_estimado`/`valor_estimado`, `custo_final`/`valor_final`); `lat_destino`/`lng_destino`/`endereco_destino` são `NOT NULL` (atrapalha serviço no local `ON_SITE`); FKs duplicadas `pedidos_ibfk_1/2`; `ON DELETE CASCADE` em `cliente_id`/`veiculo_id` (trocar por `RESTRICT` para preservar histórico financeiro); `app_logs` e `cron_executions` sem retenção.

---

## §4. Idempotência Mercado Pago v1

```
X-Idempotency-Key: ped{pedido_id}-t{tentativa}
```
Tentativa inicia em `1`, mantida em reenvios, avança só após falha. Cobranças complementares preservam chave própria. Webhook: ver §12.

---

## §5. Precificação

**Fonte única:** `service_price_rules` (tabela versionada, com vigência e `version`). Calculador: `TarifaService` (via `DecisaoAtendimentoService`); `PricingService` é só adaptador legado.

**Referência de mercado:** Ache Guincho 2026 — linear, taxa de saída R$ 120-250 + R$ 5-8/km para carro de passeio. Faixas por distância criam degraus de preço; usar linear.

**Valores base** (engate, tarifa por km, multiplicadores noturno/feriado): fonte única = **§6**; este §5 só define a estrutura da fórmula.

**Tabelas aposentadas:** `service_pricing_rules` (vazia, congelar), `provider_quote_rules` (legado, congelar) e as chaves `tarifa_*` em `configuracoes` (migrar e congelar).

### §5.1 Fórmula base

```
preco_cliente = taxa_saida
              + [(km_cobrado x tarifa_km) x fator_veiculo x fator_periodo]
              + componente_elevacao        (§5.4)
              + deadhead_do_anel           (§5.5)
              + taxa_terreno_fixa          (§5.4)
              + pedagio_repassado
```

A fórmula de §6 é a versão simplificada dos valores iniciais; **a oficial é esta**. Ver [ABERTO] em §19 sobre a diferença.

### §5.2 Base de km — decisão obrigatória

`service_price_rules.base_km_contagem` ENUM('transporte','percurso_completo','percurso_efetivo').

- `transporte`: apenas coleta → destino. Deadhead absorvido na taxa de saída.
- `percurso_completo`: deadhead + transporte + retorno.
- `percurso_efetivo`: deadhead + transporte, sem retorno.

Sem esta coluna preenchida, a cotação não pode ser gerada. **[DECIDIDO]** O guincho conta os dois trechos (parceiro → cliente e cliente → destino).

### §5.3 Colunas novas em `service_price_rules`

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
| chaves de elevação | `configuracoes` (§5.4) | `elevacao_franquia_m`, `elevacao_coef_por_m`, `elevacao_teto`, `elevacao_peso_deadhead` (+ `elevacao_coef_descida`, ver §5.4) |

`vehicle_category` já existe e está NULL em todas as regras: preencher.

### §5.4 Fatores

- `fator_veiculo` e `fator_periodo` multiplicam `(km × tarifa)`.
- **Terreno [DECIDIDO D-8]: taxa fixa, não multiplica** (substitui o fator por grau da relevo_v1) — leve (grade ≥ 6% por ≥ 1 km): R$ 40-80; acentuado (grade ≥ 8%): R$ 80-150; manobra em ladeira no hook-up: R$ 50-120. **Faixas mantidas pelo dono em 14/10 [D-16]** (a inclinação da via eleva o risco e o custo de mão de obra); o valor inicial exato dentro de cada faixa é carregado por A na regra e fica **[ABERTO]** (menor).
- **Ganho de elevação cobra [DECIDIDO D-4]**: custo de subir (trabalho contra a gravidade), mesmo que a rua no alto seja plana. Guincho: conta os dois trechos; assistência: só o deslocamento do profissional e só a subida.
  **Modelo contínuo [números aguardando confirmação do dono]**, sem degraus:
  ```
  fator_elevacao = 1 + min(elevacao_teto, max(0, ganho_efetivo_m - elevacao_franquia_m) x elevacao_coef_por_m)
  ```
  Chaves em `configuracoes`: `elevacao_franquia_m` = 100, `elevacao_coef_por_m` = 0,0002, `elevacao_teto` = 0,25, `elevacao_peso_deadhead` = 0,5. O fator multiplica o bloco variável `(km x tarifa x fator_veiculo x fator_periodo)`. Exemplos: 450 m → ×1,07; 750 m → ×1,13; ≥ 1.350 m → ×1,25 (teto).
  **Intenção do dono (14/10):** subir para um morro num resgate (ex.: do Centro do Rio a Santa Teresa) custa mais que percorrer a mesma distância em plano. Isso já é o D-4; **os números seguem [ABERTO]**. *Nota de calibração, não verificada:* com franquia de 100 m e coeficiente 0,0002, uma subida de 100–150 m (ordem de grandeza do trecho Centro–Santa Teresa, até onde sei) fica em ×1,00 a ×1,01; a cobrança de subida curta e íngreme depende então da taxa de terreno. O dono decide entre baixar a franquia/subir o coeficiente ou deixar a taxa de terreno cobrir esse caso.
  **Faltam no modelo:** (1) `elevacao_coef_descida` (D-4: a descida do guincho pesa menos que a subida), (2) a semântica exata do `elevacao_peso_deadhead` (ganho do trecho parceiro → cliente pesa 0,5?), confirmar com A. As chaves antigas `ganho_elevacao_faixas` e `ganho_elevacao_mult` (5 faixas, com degraus) ficam **aposentadas**.
  **Estado:** as chaves existem em `configuracoes`, mas nenhum código as lê; `RelevoService` devolve fator neutro (1,00). Enquanto isso a elevação **não está sendo cobrada**.
- **Fator de veículo [DECIDIDO D-13]:** carro = 1,00. **Moto = 1,00 (neutro).** O 0,70 que aparecia na regra de moto (`service_price_rules` id 37) e em `fatorVeiculo` não tem fonte e foi retirado. Os únicos valores de moto no banco (`tarifa_moto_fixa` 150 e `tarifa_moto_km` 6) são **maiores** que os de carro legados (120 e 4,50), então não sustentam desconto. Ação para A: nova versão da regra de moto com `fator_veiculo = 1,00`; só volta a mudar com fonte (cotação real de reboque de moto ou decisão do dono). SUV: sem fonte, 1,00.
- **Altitude absoluta não cobra** (estar a 750 m sem subir). Fica gravada no pedido como dado informativo.

### §5.5 Deadhead por anel

O deadhead é do anel, não do guincho individual. Ver §7.

### §5.6 Pedágio

Repassado integralmente, com comprovante, e **fora da base de comissão** (§5.7). Guincho plataforma 2 eixos. **Decisão do dono (14/10) [D-17]: presumir o cenário mais desfavorável: todas as praças são Free Flow e o pedágio entra sempre na cotação** (tabela por praça, da mesma rota, §21). A pergunta à concessionária (se o veículo sobre a plataforma gera cobrança própria) deixa de bloquear a cotação e fica como conferência: *não verificado*. O valor do pedágio **deve vir da mesma rota usada no preço** (§21); até onde sei, o ORS indica trechos pedagiados mas não devolve o valor, então a fonte do valor (tabela por praça ou entrada manual) precisa ser definida por A.

### §5.7 Comissão **[DECIDIDO D-1]**

- **Início: 8% fixos** sobre a **base de comissão = valor total − pedágio** (inclui elevação, terreno e prioridade), para todos os serviços.
- **Aumento gradual com o tempo** (modelo Uber). O contrato fixa o mecanismo, não os degraus: cada novo percentual entra como **nova versão de `service_price_rules`** (`platform_fee_value`, `effective_from`, `version`), nunca por edição da regra vigente. Os degraus e o aviso prévio ao parceiro são **[ABERTO]**.
- Precedência: `service_price_rules.platform_fee_value` > `comissoes_cidade.percentual` > `configuracoes.comissao_plataforma`. `comissoes_cidade` fica desativada.
- **Ação para A:** hoje `configuracoes.comissao_plataforma` e `comissao_assistencia_percentual` valem **0,21** e as regras de preço 8%. Alinhar para 8% (ou deixar só `service_price_rules` como fonte) e conferir se `ComissaoService`/`FaturaService`/split de pagamento leem a config antiga, para não cobrar 21% do parceiro por engano.
- Teto por corrida: sem teto por enquanto.
- **Pedágio fora da comissão [implementado por A em 13/10; confirmação explícita do dono pendente]:** `base_comissao` exclui o pedágio e `preco_cliente = base_comissao + pedagio`. O gateway incide sobre o total pago. Caso Campinas: base R$ 667,65 → comissão R$ 53,41.

**Reserva de gateway [implementada em `configuracoes`; taxas não conferidas com o contrato do provedor]:** `reserva_gateway_pix` 0,0049, `_debito` 0,0199, `_credito` 0,0498, `_boleto` 0,0199, `_percentual` 0,0498 (legado). A reserva **sai da parte do parceiro**, não aumenta o preço do cliente: na cotação reserva-se o crédito (pior caso); na aprovação, o método real do payload do gateway define a reserva e a diferença volta ao parceiro.
```
base_comissao     = total - pedagio
valor_plataforma = base_comissao x comissao_plataforma
valor_gateway    = total x reserva_gateway(metodo)
valor_parceiro   = total - valor_plataforma - valor_gateway
```
Arredondar plataforma e gateway a centavos e deixar o **resto** com o parceiro, para a soma fechar exatamente.
**Retenção efetiva em corrida sem pedágio = 8% + reserva** (crédito 12,98%, débito 9,99%, boleto 9,99%, PIX 8,49%); com pedágio a comissão incide só sobre a base sem pedágio. O exemplo "Você receberá R$ 794,85 / Retenção R$ 113,55" (total R$ 908,40, retenção 12,5%) **não bate com nenhuma dessas combinações**; com crédito seria retenção R$ 117,91 e parceiro R$ 790,49. Corrigir o exemplo ou o cálculo.
**Aumento gradual [DECIDIDO D-11]:** começa em 8% e sobe com o tempo, com **gatilho por saúde da rede** (não só por data). Cada aumento = nova versão de regra com `effective_from`. **Ainda a definir pelo dono:** quais indicadores (ex.: parceiros ativos por cidade, tempo de aceite, taxa de aceite), os limiares, o tamanho máximo de cada degrau, o teto final e o aviso prévio ao parceiro. Sem esses valores, a comissão fica em 8%. **Decisão do dono em 14/10 [D-14]: a comissão será 8%.** O mecanismo de nova versão de regra permanece, mas nenhum degrau está definido; o D-11 só volta a andar quando o dono fixar indicadores, limiares, degrau, teto e aviso.

### §5.8 Detalhamento obrigatório

Toda cotação convertida grava em `order_price_snapshots` (já existe; `UNIQUE order_id`; FK para `service_price_rules`; tem `calculation_context_json` e `snapshot_hash`):
- `taxa_saida`, `km_cobrado`, `tarifa_km`, `base_km_contagem`
- `fator_veiculo`, `fator_periodo` aplicado
- `deadhead_ref` e anel usado
- `fator_terreno` aplicado
- `ganho_elevacao_m` (subida, descida, efetivo) e componente de elevação aplicado
- `pedagio`
- `comissao` (% e R$; hoje 8%)
- `valor_total`, regra e `version` usadas

O canhoto do parceiro (mostrado antes do aceite) exibe base, elevação, terreno e prioridade.

### §5.9 Estorno parcial

Quando um guincho de anel interno aceita após cotação em anel externo, estorno automático da diferença de deadhead.

- Mercado Pago: `POST /v1/payments/{id}/refunds` com `{ "amount": <valor> }`
- PagSeguro: `POST /charges/{id}/cancel` com `{ "amount": <valor> }`
- Trilha em `pedido_eventos` tipo `estorno_diferenca_deadhead`
- Retry com backoff se saldo insuficiente; alerta no admin

**Nota técnica:** os dois endpoints vêm da v2.0 e **não foram conferidos** na documentação oficial; A confirma contra o provedor ativo (só um por vez, `PAYMENT_GATEWAY_ACTIVE`) antes de implementar.

### §5.10 Tabelas a criar

Só duas: `cotacoes` (cada cotação, convertida ou não, para medir conversão) e `cotacoes_mercado` (referências de mercado). A faixa de aceitação de preço (±15%) é hipótese: configurável e medida pela conversão do cliente e pela aceitação do parceiro. Reaproveitar `order_price_snapshots`, `order_charge_items`, `payout_ledger_entries`, `pedido_financial_snapshots` (todas vazias hoje).

---

## §6. Motor de preço — fonte única **[DECIDIDO D-2]**

**Schema (dump 12/10/2026):**

- **`service_price_rules`** (tabela canônica): `pricing_zone_id`, `service_type_id`, `vehicle_category`, `base_customer_price`, `minimum_customer_price`, `maximum_customer_price`, `provider_base_amount`, `platform_fee_type`, `platform_fee_value`, `included_distance_km`, `extra_distance_price`, `included_minutes`, `extra_minute_price`, `night_multiplier`, `holiday_multiplier`, `effective_from`, `effective_until`, `active`, `version`.
  `vehicle_category VARCHAR(30) DEFAULT NULL` e `platform_fee_type`/`platform_fee_value` **já existem**. Regra de reboque de carro hoje: R$ 250, 15 km inclusos, R$ 6,50/km extra.
- **`service_pricing_rules`** (0 linhas) — **congelada**.
- **`provider_quote_rules`** — **congelado**.
- **`order_price_snapshots`** — pronta. `UNIQUE order_id`. FK para `service_price_rules`.
- **`comissoes_cidade`** — pronta, vazia, `UNIQUE cidade_id`, desativada.
- **`configuracoes`** — chave/valor; chaves `tarifa_*` a migrar.
- `pricing_zones` mistura zona de preço e metas de expansão (`meta_*`); zona 8 (Centro/RJ) é cópia da zona 1 (Niterói); cidade 1 sem `lat_centro`/`lng_centro`.

**Comissão [DECIDIDO D-1]:** 8% fixos no início, com aumento gradual por nova versão de regra (§5.7). *(A escada D-P2 da v2.0 — 12/10/8/6% e R$ 25 + 5% na assistência — foi retirada.)*

**Precedência:** `service_price_rules.platform_fee_value` > `comissoes_cidade.percentual` > `configuracoes.comissao_plataforma`.

**Valores base (D-P1):**
- `taxa_engate` = R$ 200,00 · `tarifa_km` = R$ 5,80  *(o piso de R$ 650 acima de 80 km foi **removido** [D-7]; proteção por `minimum_customer_price` da regra)*
- `night_multiplier` = 1,20 · `holiday_multiplier` = 1,30

**Conferência (caso Nazaré Paulista → Campinas, rota mais rápida/recomendada [D-12]):** com a rota devolvida pelo ORS em 13/10 (80,63 km), `200 + 80,63 × 5,80 + 27,20 = R$ 694,85`; base de comissão R$ 667,65; comissão 8% = R$ 53,41. Se a rota rápida for a do Google (93 km), o mesmo cálculo dá R$ 766,60. **O pedágio de R$ 27,20 pertence à rota de 93 km:** ao usar outra rota, o pedágio tem de ser o dela (§5.6).

Detalhes em `doc/PRECIFICACAO.md` (versão do commit `87cd06c`, **não revisada** por mim).

---

## §7. Disponibilidade (B→A)

**Estado:** `GuinchoDisponibilidade::existeNoRaio()` (implementado por A em 06/10) **continua em uso até `candidatosPorAnel` estar implementado e aprovado por C**; só então é deprecada (ver §14).

**Interface nova:**

```php
GuinchoDisponibilidade::candidatosPorAnel(
    float $lat, float $lng, int $service_type_id
): array
```

Retorno por anel: `candidatos[]`, `deadhead_ref`, `elegiveis`.

### §7.1 Anéis
Configuráveis no admin. Default: 2, 5, 10, 20, 35 km.

### §7.2 Filtro duro
`aprovado = 1 AND disponivel = 1 AND reboque_aprovado = 1 AND NÃO bloqueado financeiramente (§9) AND dist_km <= raio_maximo`

### §7.3 Deadhead de referência
Sempre o **centro do anel**. Não é a média, não é o mais distante.

### §7.4 Regra de escolha do anel
Menor anel com **≥ 3 elegíveis**. O `deadhead_ref` daquele anel é aplicado a todos os candidatos do anel (igualdade de preço). Se nenhum anel até 35 km tiver ≥ 3 elegíveis: fallback WhatsApp.
*Estado (13/10):* implementado com `guincho_aneis_km = 2,5,10,20,35`, `guincho_anel_min_candidatos = 3`, `raio_maximo_km = 50`; chamada real `candidatosPorAnel($lat, $lng)` + `escolherAnel($aneis)` (a assinatura do contrato, com `$service_type_id`, divergiu: atualizar um dos dois). Teste no Rio (Gamboa): 1 elegível no anel 0–2 km, 0 nos demais, `anel_escolhido = null`, **fallback WhatsApp**.
*Dois pontos para decidir:* (1) com mínimo 3 e rede pequena, todo pedido cai no WhatsApp; começar com `guincho_anel_min_candidatos = 1`. (2) Os anéis são faixas separadas (0–2, 2–5…) e a contagem é por faixa: 2 guinchos a 1 km e 2 a 3 km não formam nenhum anel de 3, embora haja 4 em 5 km. O texto "menor anel com ≥ 3 elegíveis" deve ser lido como contagem **acumulada** (todos até o raio externo do anel); confirmar com A. O raio máximo 50 km passa dos 35 km do último anel: pedidos entre 35 e 50 km sempre vão ao WhatsApp.

### §7.5 Ordenação interna
1. Menor distância real 2. Maior reputação 3. Aceites recentes 4. Aleatório

### §7.6 Estorno automático
Se o cliente já pagou deadhead de anel externo e um guincho de anel interno aceita, estorno parcial automático (ver §5.9).

### §7.7 Bloqueio financeiro [DECIDIDO D-3]
Bloqueado **não conta como elegível, não recebe oferta e não vê ofertas**. Não recebe notificação até quitar. Só `bloqueada` (após os 5 dias de carência) conta como bloqueio; `vencida` dentro da carência, não (§9).

---

## §8. Catálogo Dinâmico (B-009)

**Fonte:** `service_categories` → `service_types` → `service_price_rules`.

- `service_categories`: `code`, `name`, `icon`, `active`, `sort_order`
- `service_types`: `category_id`, `code`, `name`, `attendance_mode ENUM('TOWING','ON_SITE','HYBRID')`, `requires_destination`, `requires_diagnostic`, `requires_before_evidence`, `requires_after_evidence`, `active`, `is_system`, `is_removable`, `can_disable`
- `service_price_rules`: preço por `pricing_zone_id` + `service_type_id` + `vehicle_category`

**Regra de negócio:**
1. O funil lista `service_types WHERE active = 1`, agrupado por `service_categories.sort_order`.
2. Cliente escolhe um `service_type_id`.
3. `attendance_mode` define o fluxo: `TOWING` exige destino e calcula reboque; `ON_SITE` não exige destino, calcula assistência local e filtra oficinas; `HYBRID` oferece ambos.
4. `PedidoCoreService::criar()` deriva a modalidade do catálogo — **não do `tipo_problema` VARCHAR**.

**Regra proibida:** o funil **não** usa `service_type_id` fixo por slug; consulta o catálogo ativo. **Consumidores:** views públicas, admin, cliente (3 funis). Detalhes de interface e critérios em §15.

---

## §9. Bloqueio Financeiro Operacional (B-010) **[DECIDIDO D-3, D-9, D-15]**

**Contrato aceito pela Faixa A** na versão de bloqueio imediato. A carência de 5 dias, a regra por `bloqueada` e a quitação com juros (v2.5) mudam o contrato: A confirma via CONTRATO_RESPOSTA.

```php
BloqueioFinanceiroService::guinchoEstaBloqueado(int $guinchoId): bool
```

**Fonte:** `faturas_parceiro` (hoje **0 linhas**):
- `parceiro_tipo ENUM('guincho','oficina','especialista')`
- `parceiro_id BIGINT`
- `status ENUM('aberta','paga','vencida','bloqueada','cancelada')`
- `vencimento_em DATETIME`
- `saldo DECIMAL(12,2)`

### §9.1 Linha do tempo [DECIDIDO D-15]
1. A fatura fica `aberta` até `vencimento_em`.
2. Passado o vencimento, vira `vencida`. Começa a **carência de 5 dias** (120 h, `bloqueio_carencia_horas = 120`), contada do vencimento. Na carência o parceiro **continua vendo e aceitando ofertas** e recebe aviso de débito.
3. Terminada a carência sem quitação, o cron `FaturaService::bloquearPorVencimento()` move `vencida → bloqueada`.
4. `bloqueada` só termina por quitação do débito atualizado com juros (§9.4) ou por `desbloquear()` excepcional (§9.5).

### §9.2 Regra
`guinchoEstaBloqueado()` retorna `true` se existe ao menos 1 fatura com `parceiro_tipo = 'guincho'`, `parceiro_id = :id` e `status = 'bloqueada'`. *(Mudança da v2.4: `vencida` deixou de bloquear por si só, porque agora existe a carência.)*
**[A confirma]** Se o cron falhar, a consulta pode tratar também como bloqueada a fatura `vencida` cujo `vencimento_em` + carência já passou, para o bloqueio não depender só do cron.

### §9.3 Efeito do bloqueio
1. O parceiro bloqueado **não vê ofertas** (some do matching, de `/admin/pedidos-disponiveis` e do dashboard) e não recebe notificação de oferta.
2. O **aceite** é consultado de novo no servidor antes de gravar (evita corrida) e rejeitado com: *"Não é possível aceitar novos pedidos enquanto houver fatura financeira vencida pendente de regularização."*
3. O **painel continua acessível**: badge e modal de **pagamento pendente** com o valor atualizado (§9.4) e o meio de pagar (não é oferta). Faixa B exibe; o bloqueio real é do motor (A).
4. **Pedidos já aceitos antes do bloqueio seguem até a conclusão**; o bloqueio vale para novas ofertas. [D-15]
5. Logs `FIN-BLOCK-MATCH` e `FIN-BLOCK-ACCEPT`. Nunca expor ao cliente dados financeiros, valores ou IDs de fatura.

### §9.4 Quitação com juros [DECIDIDO o princípio D-15; números **[ABERTO]**]
- Para voltar às ofertas, o parceiro paga o **débito atualizado**: saldo + multa + juros.
- **[ABERTO] do dono:** percentual de multa, percentual de juros e de quando contam (do vencimento ou do bloqueio). Referência, não verificada e não é parecer jurídico: 2% de multa + 1% ao mês é o padrão mais comum no Brasil. O que vale é o que estiver nos termos que o parceiro aceitou **antes**.
- **[A decide]** onde o valor atualizado vive: calculado na hora ou em colunas (`multa`, `juros`, `valor_atualizado_em`). O extrato (§20.5) mostra a decomposição.
- O PIX é gerado com o **valor atualizado** e validade curta. Um PIX antigo, de valor menor, é pagamento **parcial**: é registrado, mas **não desbloqueia**.
- `marcarPaga()` só quita e desbloqueia quando o saldo atualizado zera, e só quando **não restar nenhuma** fatura `vencida` ou `bloqueada` do parceiro.

### §9.5 Reversão excepcional
`FaturaService::desbloquear($faturaId)` (`bloqueada → aberta`) é liberação excepcional, auditada com administrador, motivo e data; **não equivale a pagamento**. Se a fatura seguir vencida, o cron bloqueia de novo depois da carência. **[ABERTO]** se o admin pode perdoar multa e juros nessa liberação.

### §9.6 Ativação do gate [DECIDIDO D-9]
`bloqueio_financeiro_ativo = 0` em `configuracoes` até estarem provados, nesta ordem: (a) as faturas reais são geradas pelo cron semanal (hoje 0 linhas); (b) a rota `POST /webhook/mercadopago/pix` (`FaturaWebhookController::mercadoPagoPix`) está registrada pelo dono e aprova o pagamento; (c) pagamento aprovado **desbloqueia sozinho** (`marcarPaga()`); (d) o parceiro é avisado antes do vencimento **e durante a carência (no mínimo no último dia)**. Sem (a)–(d), nenhum parceiro é bloqueado. A carência de 5 dias não altera essa ordem.

**Consumidor:** `OficinaController` e `GuinchoController` (Faixa B) exibem o badge e o modal.

**Critério de aceite (C):** guincho `bloqueada` não aparece como candidato nem recebe oferta; aceite rejeitado no servidor; outro guincho elegível continua recebendo e aceitando o mesmo pedido; `vencida` dentro da carência continua recebendo ofertas; após 120 h sem quitar, passa a `bloqueada`; pagamento parcial não desbloqueia; pagamento do valor atualizado desbloqueia sozinho; pedido já aceito segue até a conclusão; painel e tela de pagamento continuam acessíveis.

---

## §10. Relevo: elevação e inclinação (D19/D20) **[DECIDIDO D-4]**

### §10.1 Regras
| Conceito | Custa? | Vale para |
|---|---|---|
| **Altitude absoluta** (estar a 750 m, rua plana) | Não; só informativo | — |
| **Ganho de elevação** (subida acumulada no trajeto) | **Sim** | guincho **e** assistência |
| **Inclinação da via** (grau onde o veículo está / trecho carregado) | Sim | **só guincho** |

- **Guincho:** conta os dois trechos (parceiro → cliente; cliente → destino, com carro rebocado). Descida conta com **coeficiente menor que 1**, configurável no admin: `desnivel_efetivo_m = subida_m + coef_descida × descida_m`.
- **Assistência:** só o deslocamento do profissional até o cliente e **só a subida** (`desnivel_efetivo_m = subida_m`); descida não entra.
- Ponto de partida do trecho 1 antes de existir parceiro: média dos parceiros próximos no raio (definição final em `RelevoService`).
- Sem dado de relevo: fator 1.00, `origem_fator = "fallback_plano"`, sem erro e sem taxa fantasma. A cotação nunca trava esperando o serviço de altitude.

### §10.2 Interface `RelevoService` (Faixa A)

Atual (origem apenas, vigente no código):
```php
RelevoService::fatorPorOrigem(float $lat, float $lng): array
// ['fator_relevo' => float, 'inclinacao_graus' => ?float,
//  'segmento_encontrado' => bool, 'origem_fator' => string, 'via_segmento_id' => ?int]
```
> **[PENDENTE]** O texto da interface estendida do `RelevoService` (trechos, subida e descida) chegou truncado na v2.4 (aparecia só um marcador `\1`). A reenvia a assinatura; até lá vale a interface "Atual" acima.

### §10.3 Como a elevação vira preço **[modelo contínuo; números aguardando confirmação]**

Decidido: **contínuo, sem faixas** (as 5 faixas com degraus foram descartadas). Fórmula, chaves e exemplos em §5.4. Faltam `elevacao_coef_descida` e a semântica de `elevacao_peso_deadhead`. O fator multiplica só o bloco variável `(km x tarifa x fator_veiculo x fator_periodo)`; engate, terreno, deadhead e pedágio não são multiplicados.

### §10.4 Inclinação [DECIDIDO D-8]
- **Terreno em taxa fixa** (§5.4): leve R$ 40-80, acentuado R$ 80-150, manobra em ladeira R$ 50-120 (valores da v2.0, **sem fonte; o dono aprova os números**). Vale só para guincho.
- O fator por grau da relevo_v1 (5 faixas, teto 1.30) fica **substituído**; o código só deve usá-lo até a taxa fixa estar em produção.
- Inclinação guardada **com sinal** em `via_segmentos` (descida negativa). `via_segmentos` e `migration_via_segmentos_v1.sql` seguem pendentes (D21).

### §10.5 Snapshot em `pedidos` (migration `install/migration_pedido_relevo_prioridade_v2.sql`, A)
Mantém os 5 campos da v1 (`fator_relevo_aplicado`, `inclinacao_graus_snapshot`, `via_segmento_id`, `origem_fator`, `segmento_encontrado`) e adiciona: `altitude_origem_m`, `altitude_destino_m`, `desnivel_subida_m`, `desnivel_descida_m` (`DECIMAL(7,2) NULL`), `fator_elevacao_aplicado DECIMAL(4,2) NOT NULL DEFAULT 1.00`, `fonte_altitude VARCHAR(30) NULL` (`tabela|opentopodata|zona`). O detalhamento também vai em `order_price_snapshots` (§5.8).

### §10.6 Fonte de altitude
Interface `ElevationProvider` (A) com OpenTopoData autohospedado (D21). Ordem: cache no banco → VPS (HTTPS, chave, timeout curto) → tabela de zonas, com log. O plano de VPS de R$ 49 e a RAM necessária não foram verificados. Dados do Google não entram em `via_segmentos` (ToS). Dados abertos (OSM/ODbL, Data.Rio, Niterói): ler licença e atribuição por dataset.

### §10.7 Aceite (C)
Elevação cobra em guincho e assistência; inclinação só no guincho; guincho soma os dois trechos; descida pesa menos com `coef_descida` do admin; **assistência não conta descida**; rua plana no alto de um morro ainda paga a subida; sem dado: 1.00 sem erro; teto respeitado; VPS fora do ar → fallback + log; snapshot completo; desconto de fallback aplicado depois dos fatores. Casos de referência: Alto da Boa Vista, Vista Chinesa, Santa Teresa, e **Nazaré → Campinas (rota quase plana) não pode ser encarecida**.

**Migrations pendentes (A):** `install/migration_via_segmentos_v1.sql`, `install/migration_pedido_relevo_prioridade_v2.sql`, `install/migration_auditoria_prioridade_v1.sql` (esta só se a prioridade ganhar tabela própria; hoje a trilha é `pedido_eventos`, §13).
**Fórmula e réguas:** `doc/PRECIFICACAO.md` v1.1 §5 e `doc/DECISOES.md` D19/D20 (D15 revogado no ponto "altitude é só diagnóstico").

**Prioridade:** ver §16.

---

## §11. Interface A↔B (`CONTRATO_PEDIDO` / `CONTRATO_RESPOSTA`)

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

Formato curto aceito pelo protocolo (`doc/CONTRATO_RESPOSTA.md`): `ROTA_PEDIDA` (faixa, método, caminho, controller, motivo) para rotas novas; `CONTRATO_PEDIDO` curto (`de`, `para`, `o_que_preciso`, `por_que`, `criterio_de_aceite`) para mudanças pequenas. O agente emite o bloco e **para essa parte**; o dono decide e atualiza este arquivo.

**CONTRATO_PEDIDO registrado — páginas de parceiros (B → A):** rotas `GET /parceiros` (`ParceirosController::index`), `/parceiros/guinchos`, `/parceiros/oficinas`; views `parceiros/index.php`, `parceiros/guinchos.php` (novas) e `parceiros-oficinas.php` (revisar: hero, benefícios, FAQ, JSON-LD); trocar âncoras `#parceiros` por `/parceiros` em `landing.php:72`, `cidades.php:11`, `pre-cotacao.php:168`; SEO por view (title único, meta description, H1 único, JSON-LD LocalBusiness + Service + FAQPage).

---

## §12. Pagamento: webhook e contrato financeiro do admin

### §12.1 Webhook Mercado Pago v1
`WebhookController::mercadoPago()` exige `MP_WEBHOOK_SECRET` e valida o HMAC SHA-256 do manifesto `id:{data.id};request-id:{x-request-id};ts:{ts};` **antes** de qualquer consulta ou aprovação. O primeiro evento é registrado em `logs_webhook` (`provedor='mercadopago'`, `event_id=data.id`); o índice único `(provedor, event_id)` é a trava atômica. Repetido → `200 OK` sem reprocessar. Falha ao registrar a trava → `500` (o provedor reentrega). Um pedido por `triage_session_id`.

### §12.2 Destino de pagamento (admin) — proposto por B em 03/10, A implementa
```text
destino_pagamento: pago_agora | pago_na_chegada | online | sem_cobranca
provedor_online:   mercadopago | pagseguro | null   # null quando destino != online
```
Regras: `online` só com provedor habilitado; a UI **não mostra** provedor desabilitado; `pago_agora` = baixa manual auditável; `pago_na_chegada` = pendente, sem checkout; `online` = cobrança pendente + link; `sem_cobranca` = isenção auditável sem checkout; toda transição gera evento e é idempotente; ao concluir, B redireciona para `/admin/pedido/{id}?criado=1` com flash contextual (decisão do dono, 03/10).
**Um provedor por vez** (`PAYMENT_GATEWAY_ACTIVE`; a UI mostra o que `PaymentProviderFactory::gatewayAtivoRaw()` lê).
Saída de A: operação única `Pagamento::aplicarDestinoPagamento` (link só para `online`, eventos/snapshot coerentes, erro explícito se provedor desabilitado). Critério (B): `AdminController::pedidoCriar` recebe os dois campos, trata os 4 retornos, redireciona e não oferece provedor desabilitado.
**[VERIFICADO 12/10]** Nos 57 pedidos `forma_pagamento_escolhida` e `pagamento_destino_evento` são `NULL`: `aplicarDestinoPagamento()` nunca rodou nesses dados.

---

## §13. Trilha de eventos do pedido (`pedido_eventos`) — implementado em 12/10

**Dono:** A. **Consumidores:** B (admin), C (testes). Schema atual **[VERIFICADO]**: `id, pedido_id, evento, context_json, created_at`; ator, status e motivo ficam em `context_json`.

```php
PedidoEventoService::registrar(PDO $pdo, int $pedidoId, string $evento,
    ?string $statusAnterior = null, ?string $statusNovo = null,
    ?string $atorTipo = null, ?int $atorId = null, ?string $motivo = null,
    array $contextoExtra = []): void
```
`context_json` sempre leva `ator_tipo`, `ator_id`, `status_anterior`, `status_novo`, `motivo`, `ip`. Único ponto de escrita; chamado **dentro da transação** do UPDATE, antes do `commit()`.

Lista nominal (19 pontos de chamada, 22 nomes de evento). Estado: só `pedido_cancelado_admin` foi exercitado em teste real; os demais estão no código e **não provados**.

| Evento | Onde |
|---|---|
| `pedido_criado` | `PedidoService::create` |
| `pagamento_aprovado` | `PedidoTransitionService::approvePayment` |
| `pedido_transicao` | `PedidoTransitionService::transition` |
| `pedido_aceito` | `PedidoTransitionService::assignInternal` |
| `pedido_cancelado_admin` (**provado**, pedido #29) | `PedidoTransitionService::cancelByAdmin` |
| `pedido_cancelado` | `PedidoTransitionService::cancelInternal` |
| `pedido_reenfileirado_guincho` | `PedidoTransitionService::requeueByGuincho` |
| `pedido_concluido_manual` | `PedidoTransitionService::concludeManuallyByAdmin` |
| `pedido_revisao_manual` | `PedidoTransitionService::revisarConclusaoManual` |
| `pedido_reenfileirado_oficina` | `PedidoTransitionService::requeueByOficina` |
| `oficina_aceitou_pedido` | `OficinaController::aceitar` |
| `oficina_atualizou_status` | `OficinaController::atualizarStatus` |
| `orcamento_enviado` | `OficinaController::enviarOrcamento` |
| `pedido_reaberto_fila`, `orcamento_aprovado`, `conversao_reboque_pendente` | `ClienteController` (método a nomear por A) |
| `pedido_criado_admin_aguardando_guincho` | `AdminController::pedidoCriar` |
| `pedido_escalado_para_reboque` | `TowEscalationService` |
| `pagamento_baixa_manual_registrada`, `pagamento_na_chegada_definido`, `pagamento_online_definido`, `pagamento_isento` | `Pagamento::aplicarDestinoPagamento` |
| `migracao_linha_base` | backfill SQL (57 linhas) |

**Defeitos e lacunas (para A):**
1. **Best-effort**: `registrar()` engole `Throwable` e só dá `error_log`; falha de trilha financeira passa em silêncio. Gerar alerta (`app_logs`/admin).
2. `pedido_criado` grava ator fixo `'cliente'`; pedido criado pelo admin sai errado. Passar o ator real.
3. **Caminhos laterais** (`OficinaController`, `ClienteController`, `AdminController`, `TowEscalationService`, `Pagamento`) passaram a gravar evento próprio (lista acima). Falta: (a) A nomear o método de cada chamada do `ClienteController`; (b) decidir se pedido criado pelo admin gera **um** evento só (`pedido_criado_admin_aguardando_guincho`) ou dois (com `pedido_criado`, hoje com ator fixo `cliente`); (c) conferir `Models/Pedido.php`, `PedidoDecisaoSocorro.php` e `ConversionService` se ainda mudam `status` direto.
4. `PedidoCoreService.php:~273` tem INSERT próprio em `pedido_eventos`: manter ou remover para não duplicar.
5. `PedidoTransitionResult` expõe `ok`, `error`, `pedido`, `context`; `pedido` vem inteiro (~4 KB), usar resumo em logs.

**Aceite (C):** cada evento da tabela aparece ao menos uma vez em teste real; pedido novo gera `pedido_criado` com ator correto. Scripts de teste (`tests/smoke_pedido_eventos.php`, `tests/transition_real.php`) passam por revisão de C e só rodam com `MP_ENV=sandbox` e pedido de teste (`transition_real` persiste).

---

## §14. Disponibilidade em vigor e fallbacks (A)

`GuinchoDisponibilidade::existeNoRaio(float $lat, float $lng, ?string $categoria = null, ?float $raioKm = null): bool` — retorna `true` se há ao menos um prestador apto a **TOWING**: `aprovado=1`; `disponivel=1`; `reboque_aprovado=1`; `lat_atual/lng_atual` válidos; sem pedido ativo concorrente em `a_caminho|no_local|em_reboque`; distância ≤ `MIN(raio_cobertura_km, raio_maximo_km global, raioKm)`; e **não bloqueado financeiramente (§9)**. Read-only; erro de banco/config → `false` (fail closed).

`PreCotacaoOpcoesService::montar()`, em `modo=reboque`, consulta **só** `existeNoRaio()` antes de cotar. Sem guincho: `{"modo":"reboque","disponivel":false,"fallback_tipo":"suporte","mensagem":"Nenhum guincho disponivel na sua regiao agora."}`. Em `modo=orientacao|local`, sem oficina/especialista apto: `fallback_tipo="guincho"` se `existeNoRaio` for true, senão `"suporte"` (o front já mostra **Quero rebocar**). A defesa de B antes de `PedidoCoreService::criar()` permanece obrigatória.

---

## §15. `tipo_problema`, `ServiceTypeResolver` e B-009

**`tipo_problema` (A, 07/10):** persistido integralmente em `pedidos.tipo_problema` (`VARCHAR(80)`, não ENUM). Fluxo direto de reboque grava `"reboque"`; fluxo por serviço grava o slug. Nunca grava vazio (`PedidoQuoteRequest` normaliza para `outro`); históricos vazios não são inferidos.

**`ServiceTypeResolver::porSlug(string $slug): ?int` (A, 08/10, paliativo):** mapa fechado pneu→`TIRE_CHANGE`, eletrica→`ELECTRICAL_DIAGNOSIS`, bateria→`JUMP_START`, mecanica→`MECHANICAL_ASSISTANCE`, chaveiro→`AUTOMOTIVE_LOCKSMITH`; **fail closed** (`null` para slug desconhecido, catálogo sem o code ou banco fora; nunca assume TOWING). `definirLookupPorCodigo(callable)` registrado **uma vez** no bootstrap do `index.php`, após `config.php`/`Database.php`, lazy (`getPDO()` dentro do callback), query `SELECT id FROM service_types WHERE code = ? AND active = 1 LIMIT 1`, erro loga `STR-LOOKUP-FAIL`. Aceite: `tests/ServiceTypeResolverTest.php` 12/12 e `ServiceTypeResolverDbTest.php` provando `eletrica` → id real. Limite: serviço novo do Backoffice não é coberto até o B-009.

**B-009 (aceito por B em 08/10) — regra de ouro:** `service_types` é a única fonte; nenhum mapa slug/code/ID no core.
- 3 funis com **um** `form method=post`, campos reais, etapas em `fieldset data-step`; estado de negócio em JS e hidden preenchido por JS **proibidos**.
- `public-pre-cotacao-flow.js` pode: navegar etapas, mostrar/ocultar, habilitar/desabilitar, validar etapa, chamar APIs de cotação, emitir `gf:flow-stage`. Não pode: `window.__gfFlow.STATE`, escrever hidden no submit, resolver `service_type_id`, inventar `tipo_problema`.
- POST: `service_type_id` (obrigatório, select do catálogo ativo); `descricao_problema`, `decisao_atendimento` (intenção, não decide modalidade), `tipo_problema` (legado) opcionais.
- Modalidade por `attendance_mode`: TOWING exige valor cotado (B-GUARD-02) e destino (B-GUARD-03); ON_SITE não. `decisao_atendimento` divergente do catálogo → erro explícito.
- Admin: `POST /admin/pedido/criar` → `AdminController::pedidoCriar`; `/admin/pedido/novo` só Passo 1; erro redireciona 303 para `/admin/pedido/novo/funil` preservando flash e contexto.
- Aceite: criar tipo no Backoffice não exige alterar PHP; mesmo JS nos 3 contextos; etapas ocultas desabilitadas; divergência decisão × catálogo → erro.

**Backfill de `service_type_id` [EXECUTADO em `guinchafacil_dev`, 13/10]:** **[VERIFICADO em `last_run.json`]** pneu (24) → 12; bateria (17) → 5; combustível (2) → 16. Continuam em `service_type_id = 1`: **13 pedidos com `tipo_problema` vazio** e 1 com `outro`; esses 14 não foram classificados, só mantêm o valor padrão antigo (não tratar como "reboque confirmado"). O id 16 para combustível mostra que o mapa foi estendido além dos 5 slugs do resolver: **registrar combustível no `ServiceTypeResolver`/catálogo** ou o fail closed passa a recusar um tipo que já tem pedidos. Fazer o backfill em produção só depois de testar em cópia (A escreve, C valida).

---

## §16. Prioridade e vulnerabilidade (`prioridade_v1`)

- `tipo_vulnerabilidade_humana`: `crianca`, `idoso`, `gestante`, `deficiente`, `nenhuma`. `prioridade_operacional`: `local_isolado`, `normal` (só dispatch, sem revisão de vulnerabilidade). A prioridade por vulnerabilidade humana é **gratuita** (D20).
- **Estados** (`pedidos.prioridade_status`): `nao_solicitada`, `pendente_confirmacao`, `confirmada`, `divergente`, `pendente_revisao`, `cancelada`. Criação: `prioridade_solicitada=false` → `nao_solicitada`; `true` → `pendente_confirmacao`. Toda transição vai a `pedido_eventos` via `PedidoEventoService` (§13).
- **Check-in:** `POST /guincho/pedido/{id}/confirmar-prioridade` → `GuinchoController::confirmarPrioridade` (B autentica, A `PrioridadeService`); corpo `prioridade_confirmada` e `inconsistencia_motivo`; registra `confirmado_por`/`confirmado_em`. `true` → `confirmada`; `false` → `divergente` → `pendente_revisao`.
- **Nenhum débito automático.** Cobrança só pelo admin em `pedidoConcluirManual` (senha + justificativa), após revisão humana.
- **Penalidade (D20), só após revisão humana:** 1ª ocorrência injustificada → aviso, perde a gratuidade naquele atendimento (paga a taxa de prioridade) e registro; reincidência → suspensão temporária com contestação e nova revisão. O cliente vê regra e penalidade **antes** de responder; o uso do botão por parceiro é auditado.
- Aceite (C): estados iniciais corretos; check-in `true`/`false` nas transições certas; nenhum débito automático; todo evento com ator, data, anterior, novo e motivo; `local_isolado` não abre revisão; penalidade só após revisão. **"Sozinho em local deserto" [DECIDIDO D-10]:** tratado como `local_isolado`: afeta só o dispatch, sem revisão de vulnerabilidade e **sem penalidade**. Contra abuso, auditoria agregada no admin (uso muito frequente vai a revisão manual), não punição por pedido.

---

## §17. Front-end, testes e verificação

- **Eventos de front-end (A → C):** `gf:address-confirmed` e `gf:flow-stage` (document, `public-pre-cotacao-flow.js`); payload *não verificado*, A documenta antes de C depender.
- **Console de testes:** cenários em `tests/scenarios/*.json` (cada passo com `key`, `node`, `probe`); tabelas `test_runs`/`test_run_steps`; `status in pending|running|ok|fail`; `GET /admin/testes/run/{id}/events?after=N`; ingestão `POST /admin/testes/ingest` com token; guardas: só admin, CSRF, bloqueia se `MP_ENV != sandbox`, só dados `E2E_`, nunca exibe segredos. O protocolo manda **estender** `/admin/qa/run/*` e `/admin/simulador` (rotas finais dependem de D4).
- **R7:** checagem de rota sempre com **GET** (`curl -s -o /dev/null -w "%{http_code}" https://guinchafacil.com.br/login`); não usar `curl -sI`.
- **Incidente 12/10:** `/login` deu 500 por `csp_script_nonce_attr()` indefinida às 15:40; `index.php` corrigido às 19:47. O git mostra ~810 linhas removidas em `index.php`: **comparar com `_backups\index.20261010_001822.php`** antes de deploy.

---

## §18. Divisão A / B / C

R5: faixa não testa o próprio código. R2: contrato antes do código. Só o dono faz merge.

| Frente | **A** (motor, pagamento, funil API) | **B** (auth, painéis, admin) | **C** (QA, console, deploy) |
|---|---|---|---|
| Triagem/checkout | `TriageSessionService`, controllers de pré-cotação/checkout | login/registro preservam sessão | E2E triagem → pedido, 1 pedido por sessão |
| Pagamento | idempotência MP, webhook, `aplicarDestinoPagamento` | `pedidoCriar` + UI do provedor ativo | `idempotencia-pagamento`, `idempotencia-webhook` |
| Eventos (§13) | `PedidoEventoService`, todos os caminhos de status | linha do tempo no admin | cobrir cada evento, ator correto |
| Preço (§5–6) | `TarifaService` sobre `service_price_rules`, 8% de comissão, snapshot, migrar `tarifa_*` | admin de regras, canhoto do parceiro | teste por caso (Nazaré → Campinas = R$ 739,40 + pedágio), sem degraus |
| Relevo (§10) | `RelevoService`, `ElevationProvider`, `via_segmentos` | exibe fatores; admin edita `coef_descida` e réguas | régua, teto, fallback, VPS fora do ar |
| Disponibilidade (§7, §14) | `existeNoRaio`, depois `candidatosPorAnel` | fallback e badge | com/sem guincho, fallback guincho/suporte |
| Bloqueio (§9) | `BloqueioFinanceiroService`, gates de matching e aceite | badge e modal de pendência | bloqueado não vê oferta nem aceita |
| Catálogo/funis (§8, §15) | resolver, backfill, modalidade pelo catálogo | 3 funis com form único | critérios B-009 |
| Prioridade (§16) | `PrioridadeService`, estados, penalidade | rota de check-in, tela de revisão | máquina de estados, "nenhum débito automático" |
| Deploy | — | — | `tools/lane_guard.ps1`, `tools/smoke.ps1`, `index.php` vs backup, nunca versionar dump/`.env` |

**Ordem sugerida para o motor operar:** (1) §13 completo + backfill §15; (2) B-009 nos 3 funis; (3) preço: `base_km_contagem`, comissão 8% alinhada, snapshot, migração das tarifas; (4) bloqueio e prioridade; (5) anéis. Relevo v2 entra depois do preço base estável.

---

## §19. Pendências

**Resolvido em 14/10:** item 3 (comissão 8%, D-14), item 4 (terreno mantido, D-16), item 5 (Free Flow presumido, D-17), parte "bancos" do item 8 (D-18) e o §9 (carência e juros, D-15, com pontos abertos abaixo).

**[ABERTO] do dono**
1. Números da elevação: franquia, coeficiente, teto, peso do deadhead e `coef_descida` (§5.4, com a nota de calibração). A semântica do `elevacao_peso_deadhead` fica com A.
2. Confirmar explicitamente: pedágio fora da comissão (sem resposta registrada em 14/10; implementado por A).
3. Anéis: mínimo de elegíveis (recomendado 1) e contagem acumulada; assinatura de `candidatosPorAnel`; raio 50 km acima do último anel de 35 km (§7.4).
4. Rota: perfil do ORS (`driving-car` se o guincho for de passeio ou plataforma leve) e `preference`; A compara Nazaré → Campinas em `fastest` e `recommended`, `driving-car` e `driving-hgv`, contra os 93 km do Google (§21).
5. `status_detail` real do PIX no sandbox do Mercado Pago; nome exato do schema dev (XAMPP) e do de produção (`_guinchafacil`); quando ligar o gate (§9.6).
6. Registrar a rota `POST /webhook/mercadopago/pix` (pedida por A em 08/10).
7. §9.4: percentuais de multa e juros e início da contagem; perdão de juros pelo admin; valor atualizado calculado na hora ou em colunas.
8. Valor inicial exato do terreno dentro de cada faixa (A carrega); fonte para SUV e moto, se houver.
9. Fonte do valor do pedágio por praça (tabela no admin, A define); conferência com a concessionária sobre o veículo na plataforma (não bloqueia, D-17).

**Segurança:** o dump traz usuários e endereços reais — não subir ao repositório público; não enviar `.env`, `node_modules` ou `.git` a nenhuma IA; rotacionar segredos do Mercado Pago (D11). Muitos arquivos modificados no git (parte pode ser fim de linha); um agente por vez por arquivo.

## §20. Estado de implementação e evidências (13/10) e ordem T1 → T2 → T3 → T4

### §20.1 O que a evidência prova
Fonte: `tests/last_run.json` (13/10 08:53, pedido #29) e `tests/run_all.php`, banco `guinchafacil_dev`.

| Item | Prova | Limite |
|---|---|---|
| `pedido_eventos` v2 | 10 colunas (`ator_id`, `ator_tipo`, `status_anterior`, `status_novo`, `motivo` incluídas); 58 linhas = 57 `migracao_linha_base` + 1 `pedido_cancelado_admin` | só **1** evento real exercitado; o resto está instrumentado e não provado |
| Gravação e rollback | simulação gravou e desfez | — |
| Comissão 8% | `comissao_plataforma = 0.08`; `comissao_assistencia_percentual` removida | o split real ainda não foi testado (item C) |
| Gateway por método, anéis e elevação | chaves existem em `configuracoes` | os testes só conferem que a chave existe, não o comportamento |
| Bloqueio (§9) | `bloqueio_sem_sujeira` verde | **vazio**: `faturas_parceiro` tem 0 linhas, então o teste passa sempre |
| Anéis | retornam array | no cenário Rio o resultado foi fallback WhatsApp; nenhum anel foi escolhido |

Os "11 checks verdes" são em sua maioria verificações de existência de configuração; apenas gravação, rollback e retorno dos anéis exercitam código. Isso é suficiente para dizer "base pronta", não "motor validado".

### §20.2 Divergências entre o contrato e o que foi dito/feito
1. O arquivo `CONTRATOS.md` enviado em 13/10 ainda tem o §9 antigo (parceiro vê oferta e só no aceite aparece modal), o §7.7 sem a decisão do dono, o §10 só com `fatorPorOrigem` e o §6 ainda listando a escada D-P2 (12/10/8/6%). Há texto contraditório com o §5.7 do mesmo arquivo. Esta versão corrige os quatro pontos.
2. **R2:** `PrecificacaoService`, `RelevoService` e `RoutingService` (OSRM) foram criados antes de o contrato os descrever. OSRM e `por_road_match_base_url` não constam no contrato; entram como §21 se o dono aprovar.
3. A mensagem de estado diz ao mesmo tempo que o motor "não está implementado" e lista `PrecificacaoService` como código novo. Confirmar qual é o fato antes de iniciar A.
4. Contagem de instrumentação: "13 pontos" no quadro e "17 pontos" no resumo. O contrato (§13) só reconhece os 10 do `PedidoTransitionService` + `PedidoService`; os demais precisam de lista nominal.
5. R5: o mesmo agente escreve o código e o teste que o aprova (`run_all.php`). C deve revisar.
6. A afirmação de que `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true` "resolve transações em todo o app" é forte demais: até onde sei, esse atributo já vem ligado por padrão no `pdo_mysql` e trata de consultas pendentes, não de transações. Validar com `tests/test_pdo_tx.php` antes de aceitar.

### §20.3 T1 — vetores de teste do motor de preço (valores calculados à mão, D-P1 + fórmula do §5.1)
Parâmetros: engate R$ 200,00; R$ 5,80/km; `fator_veiculo` 1,00 (carro); base `transporte`; sem deadhead de anel (a validar com o anel escolhido).

| # | Entrada | Esperado |
|---|---|---|
| 1 | 93 km, plano, diurno, pedágio R$ 27,20 | **R$ 766,60** (200 + 539,40 + 27,20) |
| 2 | caso 1 com 450 m de ganho (contínuo: (450−100) × 0,0002 = ×1,07 sobre 539,40) | **R$ 804,36** |
| 3 | caso 1 noturno (×1,20 sobre 539,40; engate não multiplica) | **R$ 874,48** |
| 4 | 10 km, plano, sem pedágio | **R$ 258,00** |
| 5 | 10 km com 650 m de ganho (×1,11 sobre 58,00) | **R$ 264,38** |
| 6 | 80,63 km (ORS, rota rápida/recomendada), pedágio R$ 27,20 | **R$ 694,85**; base de comissão R$ 667,65; comissão R$ 53,41 |
| 7 | split do caso 1 no crédito (8% sobre base sem pedágio 739,40; gateway 4,98% sobre 766,60) | plataforma 59,15; gateway 38,18; parceiro **669,27** |
| 8 | split do caso 1 no PIX (8% + 0,49%) | plataforma 59,15; gateway 3,76; parceiro **703,69** |
| 9 | split do caso 6 no crédito | plataforma 53,41; gateway 34,60; parceiro **606,84** |
| 10 | split do caso 6 no PIX | plataforma 53,41; gateway 3,40; parceiro **638,04** |
| 11 | moto, mesmas 80,63 km, pedágio R$ 27,20 (fator 1,00) | **R$ 694,85** (igual ao carro até haver fonte) |

**Sem degraus:** com o modelo contínuo, 99 m e 101 m de ganho diferem em centavos. Testar a continuidade (mesmo ganho ± 1 m não muda o preço mais que R$ 0,01 × km) e o teto (1.350 m e 2.000 m dão o mesmo fator 1,25).
Também testar: 0 m de ganho = 1,00; elevação sem dado = 1,00 e `origem_fator = fallback_plano`; `RelevoService` neutro **não** pode ser reportado como "elevação cobrada".

### §20.4 T2 — teste real de `aplicarDestinoPagamento`
Roda com `MP_ENV=sandbox`, pedido de teste, e **persiste**: confirmar as quatro saídas (`pago_agora`, `pago_na_chegada`, `online`, `sem_cobranca`), o split pela fórmula do §5.7 (com os números do vetor 7/8), e que cada transição gera evento com ator. Bug conhecido a corrigir antes: split zerado em `pago_agora` (`criar(0,0)`). Valores de split guardados em centavos, soma exata.

### §20.5 T3 — extrato decomposto da fatura (instrução para a faixa B emitida em 14/10)
Cada linha: base, elevação, terreno, deadhead, pedágio, comissão 8%, reserva de gateway (método real), líquido do parceiro. Mostrar ao parceiro o líquido antes do aceite (canhoto) e gravar o mesmo detalhamento em `order_price_snapshots`.

### §20.6 T4 — limpeza
Mojibake em `Database.php` e `AdminController.php`; bloco `php_syntax_all` no `run_all.php` (hoje passa verde com `Pagamento.php` quebrado); `.tmp` órfãos em `tests/`; `.bak-*` na raiz fora do git. Commits temáticos (instrumentação, bloqueio, motor, contratos), cada um revisado por C.

---

## §21. Roteamento e distância (T1) **[DECIDIDO D-12; texto a confirmar por A]**

Implementado por A em `RoutingService` (commits `3eddcf2` e `87cd06c`); descrito aqui para cumprir o R2.
- **Rota do preço: a mais rápida/recomendada**, a mesma que o cliente vê no Google e que o guincho dirige. Confirmar o perfil do ORS (carro × caminhão pesado) e o parâmetro de preferência (rápida × curta); em 13/10 o ORS devolveu 80,63 km contra 93 km do Google, e a diferença vale R$ 71,75 no caso Campinas.
- **Ordem de fontes:** ORS → OSRM próprio → Haversine. O campo **`fonte_km`** (`ors|osrm|haversine`) vai no detalhamento e no `order_price_snapshots` de toda cotação.
- **Haversine (linha reta) subcobra** a corrida: só pode ser usado com fator de estrada configurável ou recusando a cotação; nunca em silêncio.
- **Chave `ORS_API_KEY`:** só em `.env` (nunca no repositório; `.env.example` fica vazio). A ordem de leitura (env × configuração) é documentada por A. Limites de uso do plano do ORS: **não verificados**; manter cache de rotas.
- `tools/check-osrm-deploy.php` bloqueia deploy em produção sem OSRM próprio (commit `3eddcf2`); `por_road_match_base_url` aponta o OSRM.
- **Congelamento:** o preço mostrado ao cliente fica no snapshot; recalcular a rota depois não muda um valor já apresentado.
- **Pedágio:** vem da mesma rota; o ORS não fornece o valor (§5.6).

---

## Registro de alterações

| Data | Mudança |
|---|---|
| 2026-09-30 | Criação inicial (vA) |
| 2026-10-08 | vB adiciona catálogo, bloqueio, financeiro, relevo, prioridade |
| 2026-10-12 | v2.0 — fusão consolidada + schema verificado contra dump. §3, §7, §8, §9 fechados |
| 2026-10-12 | **v2.2** — decisões do dono: comissão 8% inicial crescente, §6 e §9 mantidos (sem ofertas para bloqueado), ganho de elevação cobra; acrescenta §12–§19 (webhook, financeiro admin, trilha de eventos, disponibilidade vigente, resolver/B-009, prioridade, divisão A/B/C) |
| 2026-10-13 | **v2.3** — revisão contra implementação e testes: gateway/comissão (exemplo numérico e pedágio), anéis (acumulado, mínimo 1, assinatura), backfill real, elevação por faixas implementada sem aprovação, §20 com vetores de teste e ordem A→C→B→D |
| 2026-10-14 | **v2.4** — respostas do dono (piso removido, terreno em taxa fixa, gate de bloqueio só após pagamento provado, `local_isolado`, comissão por saúde da rede, rota mais rápida, moto 1,00); elevação contínua; pedágio fora da comissão; lista nominal de 19 pontos de evento; §21 roteamento; Free Flow como pendência |
| 2026-10-14 | **v2.5** — decisões do dono: comissão 8% (D-14), §9 com carência de 5 dias, bloqueio só em `bloqueada`, quitação com juros e painel acessível (D-15), terreno mantido (D-16), Free Flow presumido (D-17), bancos dev/produção (D-18); §19 reorganizado; marcador truncado do §10.2 sinalizado |
