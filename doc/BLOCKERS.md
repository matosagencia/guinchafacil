# BLOCKERS - GuinchaFacil

> Registro de bloqueios ativos. Formato: sintoma / o que foi tentado / hipotese / por que parei.
> Quando resolvido, mover a entrada para o fim com "RESOLVIDO em AAAA-MM-DD".

---
## BLOCKER B-001 - 404 nas rotas da faixa B em localhost (XAMPP :8080)

**Data:** 2026-10-01
**Faixa:** B
**Severidade:** alta (bloqueava validacao local dos criterios de aceite da B)
**Status:** **RESOLVIDO em 2026-10-01** - causa era URL com prefixo /guinchafacil desnecessario.

**Sintoma:**
GET em tres rotas da faixa B retornava 404 no XAMPP local, com a porta correta (:8080):
- http://localhost:8080/guinchafacil/admin/pedidos
- http://localhost:8080/guinchafacil/admin/pedido/1
- http://localhost:8080/guinchafacil/oficina/dashboard

**Causa raiz (confirmada):**
C:\xampp\apache\conf\extra\httpd-vhosts.conf (linha 48) define:
    DocumentRoot "C:/xampp/htdocs/guinchafacil"
O docroot JA E a raiz do projeto. A URL correta e http://localhost:8080/admin/pedidos
(sem o prefixo /guinchafacil). O prefixo extra fazia o Apache procurar
htdocs/guinchafacil/guinchafacil/admin/pedidos -> 404.

**Confirmacao:**
    curl.exe -s -o NUL -w "%{http_code}
" "http://localhost:8080/admin/pedidos"
Saida: 302 (redirect para login, comportamento correto de rota protegida sem sessao).

**Licao:**
A URL base local e http://localhost:8080/ (raiz), nao http://localhost:8080/guinchafacil/.
## BLOCKER B-002 - Tipo de triagem nao documentado no Contrato Pedido v1

**Data:** 2026-10-01
**Faixa:** B
**Severidade:** media
**Status:** **Ativo** - aguardando Faixa A

**Sintoma:**
src/Views/admin/partials/_pedido_oficina_detalhe.php renderiza triagem com
htmlspecialchars(()). doc/CONTRATOS.md secao 2 marca o tipo de
triagem como "nao verificado". Se for array/JSON, o admin ve "Array" ou JSON cru.

**Hipoteses:**
- H1: string humana -> partial OK.
- H2: array/JSON -> partial imprime lixo.

**CONTRATO_PEDIDO emitido (aguardando faixa A):**
    CONTRATO_PEDIDO:
      de: B
      para: A
      o_que_preciso: tipo real e forma de serializacao do campo triagem
                     em Pedido (array? string JSON? string humana?)
      por_que: partial do admin usa htmlspecialchars()
      criterio_de_aceite: doc/CONTRATOS.md secao 2 documenta o tipo de triagem
                          + exemplo para origem_funil=oficina
## BLOCKER B-004 - Migration + gravacao dos 7 campos do Contrato Pedido v1

**Data:** 2026-10-01
**Faixa:** B (dependencia da A)
**Severidade:** alta (a UI da B mostra "-" em 6 dos 7 campos)
**Status:** **Ativo** - aguardando Faixa A

**Sintoma:**
A tabela pedidos so tem oficina_id. Faltam oficina_nome, triagem,
custo_assistencia, custo_reboque, recomendacao, origem_funil.
A UI da faixa B (_pedido_oficina_detalhe.php + badge em admin/pedidos.php)
ja exibe os 7 campos, mas mostra "-" nos que nao existem.

**CONTRATO_PEDIDO emitido (aguardando faixa A):**
    CONTRATO_PEDIDO:
      de: B
      para: A
      o_que_preciso: migration idempotente (INFORMATION_SCHEMA) que adiciona as
                     colunas oficina_nome, triagem, custo_assistencia,
                     custo_reboque, recomendacao, origem_funil em pedidos;
                     e PedidoCoreService/DecisaoAtendimentoService gravando
                     esses campos ao criar pedido do funil oficina.
      por_que: a UI da B ja consome os 7 campos; todos aparecem como "-"
      criterio_de_aceite: pedido origem_funil=oficina grava os 6 campos;
                          admin/pedido/{id} exibe sem "-" (exceto nulos legitimos)