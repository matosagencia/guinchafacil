# Registro de deploy e migrations - 2026-11-07

## Publicacao

- Release funcional: `c1b9d0e`.
- SHA confirmado no cPanel: `c1b9d0ee8ae40cee4db090fa9e83a22cc852d338`.
- Commit de correcao das migrations: `4b4ceba`.
- Preservacao do checksum historico: `7b9665d`.
- Correcao de pricing: `5ed8fe6`.

## Banco de dados

- `migration_add_guincho_cnh_docs_v1.sql`: aplicada com sucesso.
- `migration_partner_referral_commission_default_v1.sql`: aplicada com sucesso; regra de indicacao de 5% confirmada.
- `migration_pricing_v2.sql`: convertida de Markdown invalido para SQL idempotente e aplicada com sucesso.
- `migration_partner_referral_commissions_v1.sql`: preservada sem alteracao para manter o checksum historico.

## Pendencias conhecidas

Os registros `DRIFT` restantes sao migrations historicas ja aplicadas com checksum diferente do arquivo atual. Eles foram mantidos para auditoria e nao devem ser reaplicados automaticamente nem ter o historico sobrescrito sem comparacao estrutural do banco.

## Evidencia

A atualizacao do banco informou `SUCCESS` para CNH, comissao de indicacao e pricing; as novas migrations foram posteriormente reconhecidas como `SKIPPED` com o mesmo checksum.
