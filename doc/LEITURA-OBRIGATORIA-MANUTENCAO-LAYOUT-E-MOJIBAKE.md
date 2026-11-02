# Leitura obrigatória — manutenção de layout e codificação

Este documento é obrigatório antes de alterar qualquer tela do GuinchaFácil.

## Regra principal

O layout estrutural existente não deve ser redesenhado, recortado ou substituído durante uma correção funcional. Se uma alteração quebrar o padrão visual, a tela deve ser restaurada imediatamente à estrutura original antes de continuar o trabalho.

## Estrutura que deve ser preservada

Em páginas de backoffice, preservar:

- o shell visual do perfil;
- o `sidebar` padrão do perfil;
- o `<header class="bo-header">` nas telas administrativas;
- o `header`/topbar do painel quando a tela usar `main-wrapper`;
- o footer correspondente ao shell;
- classes, IDs, `data-*`, nomes de campos, ações de formulário e tokens CSRF usados pelo JavaScript;
- responsividade, ordem visual e espaçamento definidos pelos CSS do shell.

Não criar uma segunda barra superior para corrigir uma tela. Primeiro identificar qual shell a página deveria usar e reutilizar o layout existente.

## Procedimento obrigatório antes de editar

1. Identificar controller, view, layout incluído e CSS carregado.
2. Comparar a página com a referência do mesmo perfil (`admin/central` para admin e a página equivalente do cliente, guincho ou oficina).
3. Verificar se a tela já usa `backoffice_shell.php`, `header.php` ou um shell específico.
4. Preservar um backup ou confirmar o estado no Git antes de uma alteração estrutural.
5. Alterar somente o componente necessário.

## Procedimento obrigatório depois de editar

1. Executar lint PHP nos arquivos alterados.
2. Verificar que existe exatamente um header estrutural e um sidebar estrutural.
3. Confirmar que não surgiu uma barra preta, margem superior ou sidebar duplicado.
4. Validar desktop e viewport estreito.
5. Visitar a página alterada e executar uma varredura de mojibake no HTML renderizado.
6. Revisar também console do navegador e respostas HTTP relacionadas ao fluxo.

## Varredura de mojibake

Ao visitar qualquer página para correção, procurar no HTML e no texto visível por padrões como:

```text
ï¿½
�
Ã
Â
â€
ðŸ
```

Também procurar palavras conhecidas corrompidas, como `cotaï¿½ï¿½o`, `serviï¿½os`, `veï¿½culo`, `configuraï¿½ï¿½es` e `Identificaï¿½ï¿½o`.

Não mascarar o problema somente com substituições globais no layout. Corrigir a origem da string, garantir UTF-8 sem BOM e declarar `charset=UTF-8` quando a resposta for HTML ou JSON.

## Critério de aceite

Uma tela só está pronta quando:

- mantém o shell visual do perfil;
- não possui mojibake no conteúdo visitado;
- não perdeu ações, formulários, IDs ou classes usados pelo fluxo;
- passa pelo fluxo real correspondente;
- e o resultado foi verificado com evidência local, sem declarar deploy ou produção sem validação externa.

Qualquer alteração futura que viole estas regras deve ser revertida para a última estrutura conhecida como correta antes de prosseguir.
