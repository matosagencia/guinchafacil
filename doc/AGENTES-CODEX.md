# Agentes Codex do GuinchaFacil

Este projeto usa tres agentes logicos no Codex. Eles nao sao personas diferentes nem executam deploy automaticamente: cada um possui escopo, arquivos e evidencias proprios.

## Leitura obrigatoria para todos

Antes de agir, cada agente deve:

1. Ler a memoria relevante pelo `MEMORY.md` e pelos resumos indicados.
2. Ler `doc/ARQUITETURA.md`, `doc/CONTRATOS.md`, `doc/DECISOES.md`, `doc/lanes.json` e a lane propria.
3. Ler a skill aplicavel antes de alterar codigo. Para o funil, usar `gf-funil-checkout-qa`.
4. Confirmar ambiente, banco e repositorio antes de qualquer afirmacao remota.
5. Preservar alteracoes existentes e nunca adicionar arquivos em massa.
6. Registrar evidencias, limites e bloqueios em Markdown.

## Agente A - Motor e Integracoes

Responsavel por regras de negocio, cobertura, expansao de raio, catalogo, precificacao, pedidos, pagamentos, webhooks e migrations necessarias ao contrato.

Arquivos principais: `src/Services/**`, `src/Models/**`, `src/Controllers/*ApiController.php`, `src/Controllers/AuthController.php` quando o fluxo for de negocio e `install/migration_*.sql`.

Entrega obrigatoria: contrato de entrada/saida, teste ou matriz para a Faixa C, lint e identificacao explicita de migration pendente. Nao altera views para esconder falha de motor.

## Agente B - Interface e Jornada

Responsavel por views, CSS, JavaScript de interacao, autocomplete, mapa, pins, acessibilidade, estados vazios e continuidade da jornada cliente/oficina/guincho.

Arquivos principais: `src/Views/**`, `public/assets/js/**`, `public/assets/css/**` e pequenos ajustes de controller apenas quando forem necessarios para renderizacao.

Entrega obrigatoria: teste manual ou automatizado da jornada, evidencia de estados de sucesso/erro/vazio e confirmacao de que o frontend nao inventa preco nem cobertura.

## Agente C - QA, Homologacao e Release

Responsavel por testes severos, regressao, contratos, lint, smoke local/remoto, verificacao de migrations, comparacao de SHA, logs e checklist de deploy.

Arquivos principais: `tests/**`, `tools/agents/**`, `doc/evidencias/**` e relatorios Markdown. C nao corrige silenciosamente o codigo testado; abre bloqueio para A ou B com reproducao e evidencia.

Gate minimo antes de release:

- `tools/agents/gf-funil-checkout-qa.ps1` aprovado;
- PHP lint dos arquivos alterados;
- teste do cenario com oficina/guincho;
- teste sem oficina e sem guincho com fallback WhatsApp;
- teste de destino com autocomplete, geocode, segundo pin e rota OSRM;
- verificacao remota de JSON puro, sem warnings PHP;
- migration do banco remoto conferida separadamente do deploy de codigo.

## Protocolo de passagem

Cada agente entrega: objetivo, arquivos alterados, comandos executados, resultado, evidencias, riscos e proximo agente. Conflito de arquivo bloqueia a alteracao e deve ser registrado; nao usar reset destrutivo para resolver conflito.

Deploy continua sendo uma operacao humana no cPanel: `Update from Remote` e depois `Deploy HEAD Commit`, sempre confirmando o SHA publicado.
