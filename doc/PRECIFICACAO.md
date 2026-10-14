## §7. Disponibilidade de Guincho (B→A)

**Contrato publicado pela Faixa A (PR #1).**

**Interface:**
```php
GuinchoDisponibilidade::existeNoRaio(float $lat, float $lng): bool
Fonte de dados: tabela guinchos, colunas:

lat_operacao, lng_operacao — base do parceiro

raio_cobertura_km INT NOT NULL DEFAULT 20

disponivel TINYINT(1) DEFAULT 1

aprovado TINYINT(1) DEFAULT 0

oferece_reboque TINYINT(1) NOT NULL DEFAULT 1

reboque_aprovado TINYINT(1) NOT NULL DEFAULT 0

Regra: retorna true se existe ao menos 1 guincho com aprovado = 1, disponivel = 1 e distancia_haversine(lat, lng, lat_operacao, lng_operacao) ≤ raio_cobertura_km.

Consumidor: PreCotacaoOpcoesService (Faixa B) — se modo = 'levar_carro' e não há guincho no raio, cai em fallback WhatsApp.

§8. Catálogo Dinâmico (B-009)
Fonte: tabelas service_categories → service_types → service_price_rules.

Estrutura:

service_categories: code, name, icon, active, sort_order

service_types: category_id, code, name, attendance_mode ENUM('TOWING','ON_SITE','HYBRID'), requires_destination, requires_diagnostic, requires_before_evidence, requires_after_evidence, active, is_system, is_removable, can_disable

service_price_rules: preço por pricing_zone_id + service_type_id + vehicle_category

Regra de negócio:

O funil lista service_types WHERE active = 1, agrupado por service_categories.sort_order.

Cliente escolhe um service_type_id.

attendance_mode do serviço define o fluxo:

TOWING → exige destino, calcula reboque

ON_SITE → não exige destino, calcula assistência local, filtra oficinas

HYBRID → oferece ambos

PedidoCoreService::criar() deriva a modalidade do catálogo — não do tipo_problema VARCHAR.

Regra proibida: o funil não deve usar service_type_id fixo por slug. Deve consultar o catálogo ativo.

Consumidores: views públicas, admin, cliente (3 funis).

§9. Bloqueio Financeiro Operacional (B-010)
Contrato aceito pela Faixa A.

Interface:

php
BloqueioFinanceiroService::guinchoEstaBloqueado(int $guinchoId): bool
Fonte: tabela faturas_parceiro, colunas:

parceiro_tipo ENUM('guincho','oficina','especialista')

parceiro_id BIGINT

status ENUM('aberta','paga','vencida','bloqueada','cancelada')

vencimento_em DATETIME

saldo DECIMAL(12,2)

Regra: retorna true se existe ao menos 1 fatura com parceiro_tipo = 'guincho', parceiro_id = :id e status IN ('vencida','bloqueada').

Uso: o parceiro continua recebendo ofertas no painel, mas ao aceitar um pedido o sistema verifica antes e, se bloqueado, exibe modal de pagamento pendente.

Consumidor: OficinaController e GuinchoController (Faixa B) exibem o badge; o bloqueio real é do motor (A).

§10. Relevo v2 e Prioridade (D19/D20)
Interface RelevoService (Faixa A):

php
RelevoService::fatorPorOrigem(float $lat, float $lng): array
// Retorno: ['fator_relevo' => float, 'inclinacao_graus' => ?float,
//           'segmento_encontrado' => bool, 'origem_fator' => string,
//           'via_segmento_id' => ?int]
Migrations pendentes (A): install/migration_via_segmentos_v1.sql, install/migration_auditoria_prioridade_v1.sql.

Fórmula e réguas: ver doc/PRECIFICACAO.md v1.1 §5 e doc/DECISOES.md D19/D20.

Prioridade (D16/D20): prioridade_status em pedidos. Estado inicial: nao_solicitada ou pendente_confirmacao. Transições registradas em pedido_eventos com ator, data, status anterior, novo e motivo.

