# Plano de execucao — funil de pre-cotacao publica

## Objetivo

Permitir que uma pessoa em situacao de emergencia veja uma cotacao antes de criar a conta, sem criar pedido, reservar guincho ou cobrar nessa etapa.

Fluxo-alvo:

`Landing -> dados minimos -> cotacao valida -> aceite explicito -> cadastro -> revalidacao -> pagamento -> pedido`

O cadastro continua necessario para contato, acompanhamento e responsabilizacao. Ele deixa de ser uma barreira inicial, mas nao e eliminado.

## Persuasao etica aplicada

- **Reciprocidade:** entregar a cotacao e a explicacao do valor antes de pedir cadastro.
- **Compromisso progressivo:** localizacao -> situacao -> conferencia -> aceite -> cadastro.
- **Autoridade:** explicar que o valor vem das regras vigentes da plataforma, cobertura e dados informados; sem selos ou certificacoes inventados.
- **Reducao de ansiedade:** mostrar o proximo passo, o que ainda nao aconteceu e quando ocorre a cobranca.
- **Prova social:** somente dados reais, atuais e auditaveis; nenhum contador, nota, depoimento ou tempo medio ficticio.
- **Escassez:** nao usar contagem regressiva ou falsa disponibilidade. Se houver demanda real, informar a origem e o horario.

“Informacao difusa” nao sera usada para esconder preco, taxas, validade ou condicoes. O elemento persuasivo e revelar valor cedo, com transparencia.

## Contrato de seguranca

1. A pre-cotacao sera um recurso separado de `pedidos`; nao despacha e nao gera pagamento.
2. O servidor recalcula geocodificacao, cobertura, distancia, servico, tarifa e validade. O navegador nunca define o preco.
3. O rascunho anonimo guardara somente dados minimos, token aleatorio em formato hash, versao da tarifa, expiracao e status.
4. O token sera curto, de uso unico, invalidado ao vincular ao cliente e nunca contera endereco ou dados pessoais na URL.
5. Havera limite por IP e sessao, janela de expiracao, limite de chamadas de geocodificacao e CAPTCHA progressivo apenas diante de abuso.
6. Todas as entradas terao limites de tamanho, coordenadas dentro do Brasil, tipos de servico permitidos e rejeicao de valores negativos ou fora da politica.
7. O aceite sera explicito e auditavel. Antes do pagamento, o servidor revalidara preco, cobertura, tarifa e disponibilidade.
8. A criacao do pedido sera transacional e idempotente; falhas nao poderao criar pedido duplicado nem cobrar duas vezes.
9. Nao serao exibidos dados de guincheiros, telefone, endereco pessoal ou localizacao operacional antes da autorizacao apropriada.
10. Logs e metricas nao registrarao endereco exato, token em claro ou coordenadas desnecessarias.

## Etapas ordenadas

### 1. Contrato e inventario — Codex

- Extrair a mesma regra de tarifa usada em `calcularCusto()` e `pedidoCriar()` para um servico compartilhado.
- Definir campos minimos: localizacao, tipo de emergencia, categoria do veiculo e destino somente quando exigido pelo servico.
- Definir estados: `simulacao`, `cotada`, `aceita`, `vinculada`, `expirada`, `consumida`.
- Definir TTL, limites, versao de tarifa e eventos de auditoria.

### 2. Revisao independente — Gemini

- Revisar ameacas, abuso, privacidade e copy.
- Conferir se nenhuma mudanca mistura pre-cotacao com pedido ou pagamento.
- Sugerir casos de teste de replay, troca de usuario, concorrencia e manipulacao de preco.

### 3. Persistencia e token — Codex

- Criar tabela/migracao de pre-cotacoes com token hash, expiracao, status, tarifa e dados minimizados.
- Criar repositorio com consumo atomico e bloqueio transacional.

### 4. API publica de simulacao — Codex, revisao Gemini

- Endpoint GET da tela e POST para gerar cotacao.
- Rate limit, CSRF apropriado para formulario publico, validacao de origem e respostas sem vazamento.
- Calculo exclusivamente no servidor.

### 5. Interface — Gemini propõe, Codex integra

Copy aprovada:

> Veja sua cotacao antes de criar sua conta.

> Informe onde voce esta e o que aconteceu. Mostraremos as condicoes antes de voce decidir.

Apoios: `Sem cobranca nesta etapa`, `Preco explicado antes da confirmacao`, `Voce decide se quer continuar`.

Na cotacao: mostrar servico, origem/destino, distancia, valor, taxas aplicaveis e validade.

### 6. Aceite e cadastro — Codex

- Aceite explicito sem despacho e sem cobranca.
- Redirecionar para cadastro minimo preservando somente o identificador seguro do rascunho.
- Vincular apos cadastro/login e invalidar o token publico.

### 7. Revalidacao, pagamento e pedido — Codex

- Recalcular tudo no servidor.
- Exigir confirmacao final do valor.
- Criar pedido e iniciar pagamento apenas na transacao autorizada, com idempotencia.

### 8. Testes e rollout — Codex supervisiona Gemini

- Testes unitarios, integracao, seguranca e E2E.
- Testar abuso, expiracao, replay, troca de usuario, concorrencia, alteracao de tarifa e falha de pagamento.
- Liberar primeiro por feature flag e observar erros, abandono e conversao sem coletar dados excessivos.

## Criterios de aceite

- Nenhuma cotacao publica cria registro em `pedidos`.
- Nenhuma cotacao publica gera PIX, checkout ou despacho.
- Preco mostrado e preco revalidado usam a mesma regra e versao.
- Token expirado, repetido ou associado a outro usuario e recusado.
- Um mesmo aceite concorrente produz no maximo uma vinculacao/pedido.
- Dados sensiveis nao aparecem em URL, HTML inicial, logs ou mensagens de erro.
- Testes existentes continuam passando e novos testes cobrem cada regra acima.
