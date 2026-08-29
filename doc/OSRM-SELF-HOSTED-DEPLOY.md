# Deploy do OSRM self-hosted — item #37

## Contexto

O código do GuinchaFácil (backend `PorThresholds::roadMatchBaseUrl()` e agora também os 4 mapas do frontend via `PorThresholds::routingFrontendBaseUrl()`) já lê a URL do serviço de roteamento de uma única config: `por_road_match_base_url` (tabela `configuracoes`). Enquanto essa config estiver vazia, o sistema cai no fallback `https://router.project-osrm.org` — o servidor demo público, que tem rate-limit e Termos de Serviço que **proíbem uso em produção**.

Este documento cobre como subir uma instância própria de OSRM e apontar o sistema para ela. É a parte que exige provisionar um servidor — não pode ser feita a partir deste ambiente.

## 1. Subir o OSRM via Docker

Recomendado: extrato do Brasil (ou só Rio de Janeiro/Sudeste, se quiser um arquivo menor e processamento mais rápido) via Geofabrik.

```bash
mkdir -p ~/osrm-data && cd ~/osrm-data

# Extrato menor (Rio de Janeiro) — processa mais rápido, arquivo menor.
# Troque pela região que cobre onde o GuinchaFácil realmente opera.
wget https://download.geofabrik.de/south-america/brazil/rio-de-janeiro-latest.osm.pbf

# Pré-processamento (perfil "car" = carro, compatível com o profile "driving" usado no código)
docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-extract -p /opt/car.lua /data/rio-de-janeiro-latest.osm.pbf
docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-partition /data/rio-de-janeiro-latest.osrm
docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-customize /data/rio-de-janeiro-latest.osrm

# Sobe o servidor de rotas na porta 5000
docker run -d --name osrm-guinchafacil --restart unless-stopped \
  -p 5000:5000 \
  -v "${PWD}:/data" \
  ghcr.io/project-osrm/osrm-backend \
  osrm-routed --algorithm mld /data/rio-de-janeiro-latest.osrm
```

Teste local:

```bash
curl "http://localhost:5000/route/v1/driving/-43.1866,-22.9068;-43.2075,-22.9035?overview=full&geometries=geojson"
```

Deve devolver JSON com `"code":"Ok"` e uma rota — mesmo formato que o código já espera (nada muda no parser).

## 2. Colocar atrás de HTTPS

O demo do problema de item #37 (TLS/Schannel falhando no HTTPS) reforça: exponha o OSRM próprio atrás de um proxy reverso com certificado válido (Nginx + Let's Encrypt, Caddy, ou um load balancer gerenciado), nunca em HTTP puro exposto à internet. Exemplo mínimo com Caddy (gera certificado automaticamente):

```
osrm.seudominio.com.br {
    reverse_proxy localhost:5000
}
```

## 2.1 Atualizar o CSP (passo obrigatório, senão o browser bloqueia)

O `index.php` (linha ~79) define um Content-Security-Policy com `connect-src` explicitamente listando `https://router.project-osrm.org`. Enquanto o novo domínio não estiver nessa lista, o navegador vai **bloquear silenciosamente** o `fetch()` das views (`pedidonovo.php`, `pedido_trilha.php`, `pedidodetalhe.php`, `dashboard.php`) mesmo com a config `por_road_match_base_url` já apontando pro servidor certo — o erro aparece só no console do navegador (`Refused to connect... violates CSP`), não nos logs do PHP.

Ao subir o OSRM próprio, adicionar o novo domínio ao `connect-src` do CSP em `index.php`, ex.:

```
connect-src 'self' ... https://router.project-osrm.org https://osrm.seudominio.com.br ...;
```

(Pode manter o domínio do demo na lista como fallback, ou remover depois que a migração for validada.)

## 3. Apontar o sistema para a instância própria

Depois que `https://osrm.seudominio.com.br` estiver respondendo, defina a config no banco (via painel admin de configuração, quando existir, ou diretamente):

```php
Configuracao::set('por_road_match_base_url', 'https://osrm.seudominio.com.br', 'URL do OSRM self-hosted (item #37)');
```

ou via SQL direto:

```sql
INSERT INTO configuracoes (chave, valor, descricao)
VALUES ('por_road_match_base_url', 'https://osrm.seudominio.com.br', 'URL do OSRM self-hosted (item #37)')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
```

Isso já basta — os 4 mapas do frontend (`pedidonovo.php`, `pedido_trilha.php`, `pedidodetalhe.php`, `dashboard.php`) e o backend (`RoadNetworkMatchService`, quando `por_road_match_enabled=1`) passam a usar a mesma URL automaticamente, sem qualquer alteração de código adicional.

## 4. Atualização periódica do mapa

Dados do OpenStreetMap ficam desatualizados com o tempo (novas ruas, mudanças de sentido). Recomenda-se reprocessar o extrato (`osrm-extract` → `osrm-partition` → `osrm-customize`) a cada 1–3 meses, ou via cron automatizado, substituindo o container em produção sem downtime (subir o novo, trocar o proxy, derrubar o antigo).

## 5. Validação

Depois de configurado, revalidar o item #37 no QA reexecutando os specs que dependem de rota real (`atendimento-colisao-rj`, `atendimento-eletrica-rj`, `stress-*`) e confirmando nos logs (`php_errors.log`) que as chamadas de roteamento agora vão para o domínio próprio, não mais para `router.project-osrm.org`.
