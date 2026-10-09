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
    curl.exe -s -o NUL -w "%{http_code}" "http://localhost:8080/admin/pedidos"
Saida: 302 (redirect para login, comportamento correto de rota protegida sem sessao).

**Licao:**
A URL base local e http://localhost:8080/ (raiz), nao http://localhost:8080/guinchafacil/.

---
## BLOCKER B-002 - Tipo de triagem nao documentado no Contrato Pedido v1

**Data:** 2026-10-01
**Faixa:** B
**Severidade:** media
**Status:** **Ativo** - aguardando Faixa A

**Sintoma:**
src/Views/admin/partials/_pedido_oficina_detalhe.php renderiza triagem com
htmlspecialchars(()). doc/CONTRATOS.md secao 2 marca o tipo de
triagem como "nao verificado". Se for array/JSON, o admin ve "Array" ou JSON cru.

**CONTRATO_PEDIDO emitido (aguardando faixa A):**
    CONTRATO_PEDIDO:
      de: B
      para: A
      o_que_preciso: tipo real e forma de serializacao do campo triagem
                     em Pedido (array? string JSON? string humana?)
      por_que: partial do admin usa htmlspecialchars()
      criterio_de_aceite: doc/CONTRATOS.md secao 2 documenta o tipo de triagem
                          + exemplo para origem_funil=oficina

---
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

---
## BLOCKER B-005 - Alerta de novo pedido nao dispara no dashboard do guincho

**Data:** 2026-10-04
**Faixa:** B
**Severidade:** media
**Status:** **RESOLVIDO em 2026-10-04** - causa raiz confirmada: pedido expirado, nao bug de codigo.

**Sintoma:**
Pedido criado com status aguardando_guincho nao gerava alerta visual no dashboard do guincho logado.

**Causa raiz (confirmada):**
Nao era bug de codigo. O front-end (fix-B-11 v2) estava correto. O backend
(GuinchoController::pedidosDisponiveis() + montarOfertasDisponiveis())
estava correto. Os 4 gates do metodo passavam para o pedido #173:
  - attendance_mode = TOWING (gate A OK)
  - reboque_aprovado do guincho = 1 (gate A OK)
  - service_type_id NULL -> gate C nem entra
  - distancia_km = 2.553 << raio_cobertura_km = 50 (gate de raio OK)

O pedido simplesmente **expirou**. Pedido::listarAguardandoGuincho()
(L387-425) filtra por AND p.expiracao_aceite > NOW(). O pedido #173 foi
criado as 13:24:25 com expiracao_aceite = 13:54:25 (30 min de janela).
As observacoes do HAR foram feitas entre 15:52Z e 16:26Z UTC - ou seja,
~2h depois da janela de aceite ter fechado. Fila legitimamente vazia.

**Evidencia visual do fix-B-11 v2:**
Pedido #183 apareceu como NOVA SOLICITACAO no /guincho/dashboard com
countdown 29:28; atendimento carregado com rota, cliente e valor.
fix-B-11 v2 (toast+beep) funcionou.

**Efeitos colaterais identificados (nao bloqueiam o alerta):**

1. status do pedido permanece aguardando_guincho mesmo apos a janela
   expirar. /admin/pedido/173 mostra "Aguardando Guincho" quando ja nao
   esta. Ninguem move o status para expirado. UX enganosa.
   -> CONTRATO_PEDIDO emitido para Faixa A (recomendacoes R1/R2).

2. Pedido::listarAguardandoGuincho() nao tem fallback para
   expiracao_aceite IS NULL (o > com NULL retorna NULL = falso).
   Qualquer pedido com a coluna NULL fica invisivel pra sempre.
   -> mesma CONTRATO_PEDIDO.

3. tipo_problema do pedido #173 veio **vazio** (string ""), o que e
   anomalo - deveria vir da pre-cotacao ou do funil admin. O
   listarAguardandoGuincho() nao filtra por isso, entao nao foi a
   causa do alerta nao tocar. Mas e dado sujo.
   -> investigacao paralela Faixa B, nao bloqueia B-005.

---
## BLOCKER B-008 - Fallback [B-STID-01] nao resolve eletrica

**Data:** 2026-10-08
**Faixa:** B (paliativo por A)
**Severidade:** alta (bloqueava criacao de pedidos de assistencia via funil admin)
**Status:** **RESOLVIDO em 2026-10-08** - paliativo validado em teste funcional.

**Sintoma:**
Pedido com tipo_problema = "eletrica" no funil admin falhava com
"Destino e obrigatorio para reboque", mesmo com o fallback [B-STID-01]
aplicado em cb654f7. Pedido com tipo_problema = "pneu" funcionava.

**Causa raiz (confirmada):**
O bloco [B-STID-01] em AdminController.php:1624-1640 buscava no catalogo
com a query:

    WHERE active=1 AND (code = ? OR slug = ? OR LOWER(name) = LOWER(?))

Tres problemas:
1. service_types **nao tem coluna slug** (o slug mora em servicos_catalogo).
2. O name real e Eletrica (com acento) - LOWER('Eletrica') != eletrica.
3. O code real e ELECTRICAL_DIAGNOSIS, nao eletrica.

Resultado: pneu batia (por acaso), eletrica nao. serviceTypeId ficava
NULL, PedidoCoreService assumia TOWING, exigia destino -> erro.

**Paliativo aplicado (validado):**
1. fix-B-stid-v5c.ps1 - substituiu o bloco [B-STID-01] antigo por chamada
   ao \App\Services\Catalog\ServiceTypeResolver::porSlug() (Faixa A).
2. fix-B-stid-v6c.ps1 - AdminController consulta service_types.attendance_mode
   via ServiceType::isTowing() e passa modalidade_socorro explicito no
   PedidoCreateRequest. Fecha o gap entre catalogo e ModalidadeResolver.
3. _precotacao_funil_admin.php (Solucao B) - o script proprio sincroniza
   tipo_problema, categoria, valor_cotado e decisao_atendimento via
   evento gf:flow-stage.
4. fix-B-funil-skip-veiculo.ps1 - funil admin pula o Passo 2 (veiculo)
   quando ja veio do Passo 1 (pedidoNovoV2), via window.__gfFlowOptions.

**Evidencia (log do PHP):**
    [B-STID-01] service_type_id resolvido via ServiceTypeResolver: 8 (tipo_problema=eletrica)
    [B-STID-02] service_type_id=8 attendance_mode=ON_SITE -> modalidade_socorro=SOCORRO_LOCAL

**Evidencia (banco):**
    pedidos.service_type_id = 8, attendance_mode = ON_SITE, lat_destino = NULL

**Evidencia (console):**
    [admin-funil] hidden sincronizados: {tipo_problema: 'eletrica', ...}

**Limite do paliativo:**
O ServiceTypeResolver tem mapa hardcoded de 5 slugs (pneu, eletrica,
bateria, mecanica, chaveiro). Servico novo cadastrado no Backoffice
**nao e coberto** -> fail closed -> pedido nao cria. Solucao definitiva:
**B-009 - Catalogo dinamico no funil**.

**Sucessor:** B-009 (catalogo dinamico com service_type_id vindo de select
real, attendance_mode do catalogo, public_slug administravel).

---
## BLOCKER B-010 - Bloqueio financeiro nao tem efeito operacional

**Data:** 2026-10-08
**Faixa:** B (CONTRATO_PEDIDO para A)
**Severidade:** media (a fatura bloqueada nao impede o guincho de receber/aceitar pedidos)
**Status:** **Ativo** - CONTRATO_PEDIDO emitido para Faixa A

**Sintoma:**
A tabela `faturas_parceiro.status = 'bloqueada'` (via `FaturaService::bloquearPorVencimento()`)
nao tem efeito operacional. O guincho com fatura vencida continua:
- Recebendo ofertas em `/guincho/dashboard`
- Aparecendo como candidato no matching (`GuinchoController::montarOfertasDisponiveis`)
- Podendo aceitar pedidos (`PedidoTransitionService::acceptByGuincho`)

**Causa raiz:**
O gate de bloqueio nao foi implementado. Existe apenas o estado
`'bloqueada'` na tabela, sem consumo pelo matching/aceite.

**CONTRATO_PEDIDO emitido (aceito por A em 2026-10-08):**
    CONTRATO_PEDIDO:
      de: B
      para: A
      o_que_preciso: BloqueioFinanceiroService::guinchoEstaBloqueado(int $guinchoId): bool
                     com gate no matching e no aceite.
      por_que: faturas_parceiro.status='bloqueada' nao tem efeito operacional.
      criterio_de_aceite:
        - guincho bloqueado nao aparece no dashboard nem no matching
        - aceite rejeitado com mensagem explicita
        - logs FIN-BLOCK-MATCH e FIN-BLOCK-ACCEPT
        - outro guincho elegivel continua recebendo o mesmo pedido

**Resposta de A (2026-10-08):**
- `BloqueioFinanceiroService::guinchoEstaBloqueado(int $guinchoId): bool`
- Condicao: `status = 'bloqueada' AND vencimento_em < NOW()` em `faturas_parceiro`
- Uso: 1) matching (excluir o guincho), 2) aceite (re-checar para evitar race),
  3) auditoria/log (`FIN-BLOCK-MATCH`, `FIN-BLOCK-ACCEPT`).
- Mensagem ao guincho: "Nao e possivel aceitar novos pedidos enquanto houver
  fatura financeira vencida pendente de regularizacao."
- Reversao: `marcarPaga()` (fatura vira 'paga') ou `desbloquear()` (manual,
  auditado com admin+motivo+data; o cron re-bloqueia se continuar vencida).

**Ação B (bloqueada):**
B nao implementa o gate — apenas exibe o badge "bloqueado" em
`/admin/faturas` e `/admin/pedido/{id}` (feito em 2026-10-08).

**Referencia cruzada:**
- `CONTRATO_RESPOSTA` (A → B) em `doc/CONTRATOS.md`.

---
## DEBITO TECNICO - Mojibake no AdminController.php

**Data:** 2026-10-08
**Faixa:** B
**Severidade:** baixa (cosmetico â€” comentarios ilegiveis; codigo funciona)
**Status:** **Registrado** - nao corrigir agora (risco alto vs beneficio baixo)

**Descricao:**
`src/Controllers/AdminController.php` tem mojibake (`AÆ’AE'A...`) em
comentarios e strings literais â€” residuo de sessoes anteriores. O
`php -l` passa; o site funciona. Nao e bug funcional.

**Impacto:**

- Leitura humana dos comentarios prejudicada.
- `Select-String` nao acha palavras com acento (ex.: `eletrica`).

**Acao futura (quando houver janela de manutencao):**

1. `git stash` tudo (garante estado limpo).
2. Restaurar `AdminController.php` do commit `8fdc9ff`.
3. Corrigir o mojibake usando **bytes** (nao `Get-Content -Raw`):

       $bytes = [System.IO.File]::ReadAllBytes($path)
       $texto = [System.Text.Encoding]::UTF8.GetString($bytes)
       # ... edita ...
       [System.IO.File]::WriteAllBytes($path,
           [System.Text.Encoding]::UTF8.GetBytes($texto))

4. Reaplicar os patches da sessao 5:
   - `fix-B-stid-v6c.ps1`
   - `fix-B-admin-faturas-controller.ps1`
   - `fix-B-admin-faturas-menu-e-comissao.ps1`
5. Commit + push.

**Por que nao corrigir agora:**

- O arquivo tem 4.400+ linhas â€” refatoracao de altissimo risco.
- Cada edicao com `Get-Content` + `WriteAllText` PIORA o encoding
  (aconteceu 3 vezes nas sessoes 3, 4 e 5).
- O `php -l` passa; o site funciona.
- R6 do protocolo: diff minimo, sem limpezas fora da tarefa.

**Referencia cruzada:**

- Commit `cdffcfd` (sessao 5) no `origin/main`.
- Mesmo problema pode afetar `index.php` (verificar em sessao dedicada).