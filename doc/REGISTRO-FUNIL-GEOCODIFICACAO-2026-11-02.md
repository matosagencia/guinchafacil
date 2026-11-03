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

O endpoint remoto `/api/pre-cotacao/decisao` apresentou `ORDER_CORE_ERROR` por coluna `public_slug` ausente no banco remoto. Esse drift de schema deve ser tratado pela migration correspondente no cPanel; o frontend agora falha fechado para WhatsApp quando a resposta vier vazia ou com erro e nao deve deixar a etapa 6 sem orientacao.

Commits relacionados: `943b0ef`, `ce729cf`, `677d956`, `70ad9c2`.
