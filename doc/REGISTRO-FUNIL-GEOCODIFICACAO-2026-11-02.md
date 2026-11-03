# Registro do funil: destino, rota e fallback

## Estado validado

- A etapa 5 aceita o destino digitado, selecionado no autocomplete ou escolhido pelo segundo pin.
- O autocomplete retorna coordenadas; quando o provedor de sugestoes nao retorna item, o endpoint usa o geocoder oficial como fallback.
- A rota do mapa usa o proxy OSRM e geometria viaria, sem linha reta artificial.
- O PHP 8.5 nao pode emitir `curl_close()` na resposta, pois o aviso corrompe o JSON consumido pelo navegador.
- Sem oficina e sem guincho ativo, a etapa 6 deve exibir o fallback para WhatsApp.
- O link do WhatsApp deve carregar origem, coordenadas, modo, veiculo, servico e destino.

## Cenario homologado

Origem: Rua Magnolia, Niteroi/RJ.

Modo: levar carro.

Cobertura operacional: nenhuma oficina e nenhum guincho ativo.

Destino: Rua da Gamboa, 247, Rio de Janeiro/RJ.

Resultado esperado: destino geocodificado, rota viaria desenhada, etapa 6 sem cards de preco e botao/link de atendimento pelo WhatsApp.

## Evidencia e pendencia remota

O endpoint remoto `/api/pre-cotacao/decisao` apresentou `ORDER_CORE_ERROR` por coluna `public_slug` ausente no banco remoto. Foi criada a migration idempotente `install/migration_service_types_public_slug_v1.sql`; ela deve ser aplicada no cPanel antes da homologacao definitiva. O frontend agora falha fechado para WhatsApp quando a resposta vier vazia ou com erro e nao deve deixar a etapa 6 sem orientacao.

Commits relacionados: `943b0ef`, `ce729cf`, `677d956`, `70ad9c2`, `1a6f65e`, `5a38fa9`, `f61418a`, `0c22135`, `e549244`, `7d0e23b`, `67718ed`, `ac263dc`.

## Estado atual da homologacao

- A etapa de origem, destino, autocomplete, geocodificacao, segundo pin e rota viaria avancou no remoto.
- A migration `migration_service_types_public_slug_v1.sql` foi aplicada no cPanel.
- O fallback WhatsApp foi validado no cenario sem oficina e sem guincho.
- A cotacao oficial avancou apos o preco passar a usar o proxy interno de rota.
- O proximo bloqueio observado e a etapa financeira `Como prefere pagar?`; ainda nao declarar checkout ou Mercado Pago homologado.
- O proximo ciclo sera fechado em um unico commit de release apos os testes A/B/C locais e a conferencia remota.
- O bloqueio em `Como prefere pagar?` foi localizado em `TriageSessionService::normalizar()`: o loop de obrigatoriedade lancava excecao incondicionalmente. A correcao local foi validada com 7 testes, incluindo `online` e `pago_na_chegada`; ainda falta validar o cadastro/checkout no navegador remoto.
