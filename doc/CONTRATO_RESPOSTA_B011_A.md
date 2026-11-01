# CONTRATO_RESPOSTA — A → B, B-011

**Decisão:** fail-closed. Se não houver regra ativa na zona solicitada ou global explicitamente cadastrada (`pricing_zone_id IS NULL`), `PrecificacaoService::calcular()` devolve `ok=false, erro=PRICE-RULE-MISSING`. Nunca seleciona regra de outra zona, nunca configura preço-base automaticamente sem regra. Serviço desativado devolve `PRICE-SERVICE-INACTIVE`.

**Lookup público:** `ServiceType::buscarPorCodigoAtivo($code)`, retornando `?array` e exigindo `active=1`. Não alterar o método existente sem controle de impactos.

**Assinaturas reais das classes** (sem namespace nos arquivos inspecionados): `RoutingService::distanciaKm(...)`, `ZonePricingService::resolverZonaPorCoordenada(...)`, `PrecificacaoService::calcular([...])`.

**Atenção:** o rascunho B-011-B2/B3 enviado invoca `\\App\\Services\\Routing\\RoutingService`, `\\App\\Services\\Pricing\\ZonePricingService`, `\\App\\Services\\Precificacao\\PrecificacaoService` e `RoutingService::ultimaFonte()`; essas APIs não correspondem ao repositório examinado. Corrigir antes de ativar.

**Status:** patch proposto, testes locais no XAMPP pendentes. Isso libera análise B-011-B2/B3, não libera sua ativação em produção.
