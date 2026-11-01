# Resposta contratual A → B — mapa público §23

**Rota solicitada:** `GET /api/pre-cotacao/mapa?lat=-22.90&lng=-43.10` (sem autenticação)

**Responsabilidade A:** serviço de projeção pública de zonas e guinchos elegíveis. Arquivo: `src/Services/PreCotacao/MapaCoberturaService.php`.

**Resposta de sucesso** (o roteador fornece HTTP 200/JSON):

```json
{"zonas":[{"id":1,"name":"Centro","status_expansao":"pedra_viva","polygon_geojson":{"type":"Polygon","coordinates":[[]]}}],"guinchos":[{"id":4,"lat":-22.90,"lng":-43.10,"placa_publica":"ABC1*23"}],"centro":{"lat":-22.90,"lng":-43.10,"zoom_sugerido":12}}
```

O JSON acima é **exemplo estrutural fictício**, não dado real de cobertura.

**Erros:** `{"ok":false,"erro":"Coordenadas inválidas."}` (HTTP 422); erros de infraestrutura (HTTP 503, sem detalhes SQL).

**Cache:** 60 segundos em arquivo temporário do servidor, indexado por coordenada de grade 0,01°; sem gravação no banco. IP rate limiting e registro de rota exigem integração pelo dono no middleware central.

**Privacidade:** posições GPS são deliberadamente arredondadas a 0,01° (~1 km em latitude); placas parcialmente mascaradas; máximo 50 marcadores. Não mostrar marcadores como rastreamento em tempo real nem disponibilizar CPF/telefone/nome do prestador.

**Elegibilidade:** `aprovado=1`, `disponivel=1`, `reboque_aprovado=1`, usuário ativo, ausência de pedido em andamento, bloqueio financeiro `BloqueioFinanceiroService::idsBloqueados()`; localização até 50 km da célula consultada. A composição dessa lista representa candidatos para **reboque**, não necessariamente especialistas ON_SITE.

**Limitação:** a consulta pública não substitui `GuinchoDisponibilidade::candidatosPorAnel()` para matching, pois não exige proximidade dentro do raio operacional individual do guincho; confirmar a diferença antes de rotular visualmente “disponível para seu chamado”.

**Integração pelo dono** (`index.php` compartilhado):

```php
// A rota GET /api/pre-cotacao/mapa deve ser registrada no roteador já existente.
// Após passar pelo middleware de limite por IP:
require_once __DIR__ . '/src/Services/PreCotacao/MapaCoberturaService.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
try {
    if (!isset($_GET['lat'], $_GET['lng']) ||
        !is_scalar($_GET['lat']) || !is_scalar($_GET['lng']) ||
        !is_numeric($_GET['lat']) || !is_numeric($_GET['lng'])) {
        throw new InvalidArgumentException('Coordenadas inválidas.');
    }
    echo json_encode(MapaCoberturaService::consultar(getPDO(), (float)$_GET['lat'], (float)$_GET['lng']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'erro'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('[MAPA-23] ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok'=>false,'erro'=>'Mapa de cobertura temporariamente indisponível.']);
}
exit;
```

**Faixa B:** consumir o endpoint e mostrar os overlays. Corrigir `hidden` / `invalidateSize()` só na view/JS da B.
**Faixa C:** testar autorização pública, rate limit, filtro de expansão, mascaramento/quantização, cache TTL, sem gravação, resposta vazia, SQL falho.
