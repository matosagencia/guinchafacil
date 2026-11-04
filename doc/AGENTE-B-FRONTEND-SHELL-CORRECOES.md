# Agente B — correções obrigatórias do shell parceiro

Registro de 03/11/2026.

O perfil `parceiro` usa `.main-wrapper.gf-unified-shell`, a sidebar própria e o topo contextual `.gf-panel-topbar`. A navbar legada renderizada por `src/Views/layouts/header.php` não pode aparecer nem reservar espaço antes do wrapper.

Manter obrigatoriamente:

- `body.parceiro` e `body[data-role="parceiro"]` com `margin-top: 0` e `padding-top: 0`;
- `nav.navbar` e `.gf-unified-topbar` legados ocultos no perfil parceiro;
- `.main-wrapper.gf-unified-shell` iniciado no topo, sem deslocamento ou transformação;
- `.gf-panel-topbar` visível, com fundo branco e texto azul-petróleo contrastante;
- sidebar, topbar e conteúdo usando o mesmo shell/responsividade dos demais perfis.

Antes de declarar pronto, testar `/parceiro/dashboard` e `/perfil/completar` autenticado, incluindo viewport mobile, e verificar que não existe faixa superior preta.

Padrão público registrado: todas as páginas `/parceiros/*` devem usar o mesmo header visual de `/parceiros`: fundo branco, logo com texto escuro e destaque verde, links escuros com ativo verde e CTA padrão. O tema de distribuição pode alterar hero e conteúdo, mas não pode sobrescrever as cores do header.
