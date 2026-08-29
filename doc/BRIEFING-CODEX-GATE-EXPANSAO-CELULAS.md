# Briefing para Codex — Gate de Expansão por Célula + Prestador por Território (GuinchaFácil)

**Contexto do projeto:** GuinchaFácil, plataforma de intermediação de socorro/reboque (PHP puro, MySQL, sem framework). Convenções obrigatórias a seguir (já usadas em todo o projeto):

- Migrations em `install/*.sql`, sempre **idempotentes** (guard via `INFORMATION_SCHEMA.COLUMNS`/`TABLE_CONSTRAINTS` antes de `ALTER TABLE`), executadas em ordem alfabética por `install/migrate.php` — nomeie o(s) arquivo(s) para ordenar depois da mais recente hoje (`migration_pricing_zones_v5_metas.sql`).
- Telas admin seguem o padrão "shell-ops" (3 colunas: `ops-topbar` com busca contextual + `ops-summary` com métricas + workspace) ou `shell-ops--no-worklist` para telas de relatório/dashboard sem lista natural de entidade.
- Toda ação administrativa relevante grava auditoria (quem, quando, o quê, estado antes/depois) — ver `Logger::log()` e o padrão já usado em `AdminPricingZoneController::expansaoSalvar()`.
- Toda gravação financeira/idempotente segue chave única / hash de idempotência — nunca duplicar efeito sob retry.
- Regra suprema já vigente: pagamento aprovado ≠ saldo liberado ≠ saque pago ≠ repasse concluído. Qualquer novo relatório deve respeitar essa separação.
- Sem evidência, não está pronto — toda métrica nova precisa ser calculada ao vivo a partir de dado real, nunca estimada/hardcoded.

## Contexto de negócio (leia antes de codar)

O GuinchaFácil está executando uma estratégia de **domínio territorial progressivo de Niterói**: a cidade foi dividida em 5 "células" (zonas geográficas reais, ver `pricing_zones` com `code` = `niteroi-celula-1` a `-5`, polígonos já desenhados a partir de fronteiras de bairro do OpenStreetMap). A ideia é dominar uma célula por vez — só abrir a próxima quando a célula atual atingir um "gate" de indicadores por 4 semanas seguidas.

Já existe hoje (não precisa recriar):
- `pricing_zones`: geometria (`polygon_geojson`), governança de expansão (`ordem_expansao`, `status_expansao` ENUM('nao_ativada','pedra_morta','pedra_viva'), `bairros_referencia`) e, desde `migration_pricing_zones_v5_metas.sql`, metas do piloto de 90 dias (`meta_prestadores_min/max`, `meta_disponibilidade_simultanea`, `meta_atendimentos_mes1/2/3`, `meta_margem_operacional_min_pct`, `meta_margem_pos_marketing_min_pct`, `meta_composicao_prestadores`, `meta_ciclo_inicio`).
- `pedidos.pricing_zone_id`: resolvido automaticamente por point-in-polygon real (`ZonePricingService::resolverZonaPorCoordenada()`) no momento da criação do pedido — todo pedido novo já carrega a célula real de origem.
- `src/Services/TerritorioMetasService.php`: painel ao vivo por célula com pedidos pagos, receita bruta, repasse, comissão, perdas por estorno e margem operacional (tudo via `pricing_zone_id`, sem cache).
- `/admin/dashboard` já renderiza esse painel (cards "Metas & Território" e "As 7 perguntas do marketing").

**O que falta e é o objeto deste briefing** — dois gaps que o painel atual documenta como limitação conhecida, não esconde:

## Gap 1: Gate de expansão (indicadores operacionais por célula)

A meta do piloto define este gate — uma célula só pode abrir a próxima quando atingir, por 4 semanas seguidas:

| Indicador | Meta |
|---|---:|
| Pedidos atendidos | ≥ 15 por semana |
| Taxa de aceite | ≥ 75% |
| Pedido sem prestador | ≤ 10% |
| Mediana de aceite (tempo do pedido até o prestador aceitar) | ≤ 3 minutos |
| Mediana de chegada (tempo do aceite até "no local") | ≤ 25 minutos |
| Cancelamento do prestador | ≤ 8% |
| Conclusão sem disputa | ≥ 92% |
| Margem de contribuição positiva | Sim |
| Cobertura operacional | ≥ 85% do horário anunciado |

Hoje nenhum desses 9 indicadores é calculado. `TerritorioMetasService` só cobre pedidos pagos/receita/repasse/margem — não aceite, chegada, cobertura, nem taxa de aceite/cancelamento.

### Tarefa 0 (fazer primeiro): mapear o que já existe de timestamp por status

Antes de criar tabela nova, investigar:
1. `pedidos` já tem colunas de timestamp por transição de status (ex. `aceito_em`, `no_local_em`) ou isso só existe implicitamente via `PedidoTransitionService`/logs (`Logger::log`)? Ver `src/Services/Pedido/PedidoTransitionService.php` e a tabela usada por `AdminController::pedidoTrilha()` (hoje mistura `RoutingSnapshotService`, `ProofOfRoadService`, `PedidoLocalizacao` — nenhum desses parece ser uma trilha de status genérica).
2. Se não existir uma trilha de status com timestamp por transição, criar (migration idempotente) algo como `pedido_status_eventos` (`pedido_id`, `status_anterior`, `status_novo`, `guincho_id` nullable, `ocorrido_em`), populado dentro de `PedidoTransitionService` toda vez que o status mudar — reaproveitando o ponto único onde toda transição de status já passa, sem duplicar lógica em cada controller.
3. Se já existir alguma coisa parecida (ex. dentro de logs estruturados), preferir ler dali a duplicar.

### Tarefa 1: calcular os 9 indicadores por célula, por semana

- Novo método em `TerritorioMetasService` (ou serviço dedicado `CelulaGateService`), usando `pedido_status_eventos` (ou equivalente encontrado na Tarefa 0) cruzado com `pedidos.pricing_zone_id`:
  - Pedidos atendidos por semana (contagem, `aguardando_guincho` → aceite dentro da semana).
  - Taxa de aceite = pedidos aceitos ÷ pedidos ofertados a algum prestador.
  - Pedido sem prestador = pedidos que expiraram em `aguardando_guincho` sem nenhum aceite ÷ total de pedidos da célula.
  - Mediana de aceite = mediana de (`aceito_em` − `criado_em`) por pedido da célula.
  - Mediana de chegada = mediana de (`no_local_em` − `aceito_em`).
  - Cancelamento do prestador = pedidos cancelados pelo guincheiro após aceite ÷ pedidos aceitos.
  - Conclusão sem disputa = pedidos concluídos sem ocorrência/disputa aberta ÷ pedidos concluídos (ver tabela de Ocorrências já existente).
  - Margem de contribuição positiva = já calculável hoje via `TerritorioMetasService::painel()['margem_operacional']` > 0.
  - Cobertura operacional = precisa definir "horário anunciado" (provavelmente vem de disponibilidade dos prestadores da célula — se não houver dado de agenda/plantão hoje, marcar como indicador `indisponivel` explicitamente em vez de inventar número).
- Cada indicador deve ter status visual: atingido / não atingido / dado insuficiente (não confundir "0% porque não tem dado" com "0% porque a operação está ruim").

### Tarefa 2: UI do gate na tela de célula

- Em `/admin/precificacao/zonas` (ou um novo painel `/admin/territorio/gate`), mostrar por célula: os 9 indicadores das últimas 4 semanas completas, com badge verde/vermelho/cinza (atingido/não atingido/sem dado), e um resumo "pronta para abrir a próxima célula: sim/não" com o motivo do bloqueio.
- Reaproveitar `status_expansao` já existente — quando todos os 9 indicadores baterem por 4 semanas seguidas, sugerir (não automatizar sozinho) a mudança para `pedra_viva` e liberar a UI para o admin ativar a próxima célula (`ordem_expansao` seguinte).

## Gap 2: Prestador vinculado a uma célula (hoje é só por cidade)

`TerritorioMetasService::painel()` hoje conta "prestadores homologados" via `guinchos.cidade_id` — aproximação por cidade inteira, não pela célula/bairro real onde o prestador atua. Isso deixa a meta de "10-15 prestadores homologados na célula 1" imprecisa (conta prestadores da cidade toda, não só da célula).

### Tarefa 3: vincular guincheiro a uma célula (organizacional, não afeta matching)

- Migration idempotente: `guinchos.pricing_zone_id INT NULL`, FK para `pricing_zones(id) ON DELETE SET NULL`, índice. **Importante**: este campo é só organizacional/de relatório — não deve alterar em nada a lógica real de despacho/matching de pedido↔guincheiro, que continua sendo por proximidade geográfica real, igual hoje.
- Preencher automaticamente na aprovação do prestador: se o guincheiro tiver lat/lng de base cadastrada, resolver a célula via o mesmo `ZonePricingService::resolverZonaPorCoordenada()` já usado para pedidos. Se não tiver lat/lng, deixar `NULL` e permitir o admin setar manualmente.
- Adicionar campo de seleção de célula na tela de aprovação/edição de guincheiro (admin), com opção "detectar automaticamente" e "definir manualmente".
- Atualizar `TerritorioMetasService::painel()` para contar prestadores por `pricing_zone_id` quando disponível, caindo para a aproximação por cidade só quando nenhum guincheiro da cidade tiver célula definida ainda (não quebrar o painel atual durante a transição).

---

## Critérios de entrega (para todas as tarefas)

1. Migrations idempotentes, testadas contra banco já existente (não pode quebrar se rodar duas vezes).
2. Nenhuma tela nova sem o padrão shell-ops já estabelecido.
3. `pedido_status_eventos` (ou equivalente) não pode adicionar latência perceptível ao fluxo de transição de status — é um INSERT simples, não uma operação pesada.
4. Nenhum indicador pode ser exibido como número quando não há dado suficiente — mostrar "sem dado" explicitamente, nunca 0% ou 100% por padrão.
5. Ao final, rodar balance-check de sintaxe PHP (`php -l` em cada arquivo tocado) e a suíte PHPUnit existente — não pode haver regressão.
6. Entregar um resumo final indicando: migrations novas (na ordem em que devem rodar), rotas novas, e como testar manualmente cada indicador do gate (idealmente com um script CLI de verificação, seguindo o padrão `tools/*.php` com guard `if (PHP_SAPI !== 'cli')`).
