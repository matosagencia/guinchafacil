# Contrato único de shell dos backoffices

Este contrato vale somente para as áreas autenticadas `/admin`, `/guincho`, `/oficina` e `/cliente`. O frontend público não usa estes parâmetros.

| Parâmetro | Admin | Guincho | Oficina | Cliente |
|---|---|---|---|---|
| `profile` | `admin` | `guincho` | `oficina` | `cliente` |
| `theme` | `executive` | `driver` | `workshop` | `client` |
| `shell` | `backoffice_shell.php` | `backoffice_shell.php` | `backoffice_shell.php` | `backoffice_shell.php` |
| `sidebar` | `bo-sidebar` | `bo-sidebar` | `bo-sidebar` | `bo-sidebar` |
| `topbar` | um `bo-header` | um `bo-header` | um `bo-header` | um `bo-header` |
| `content` | `bo-content` | `bo-content` | `bo-content` | `bo-content` |
| `footer` | `bo-footer` | `bo-footer` | `bo-footer` | `bo-footer` |
| `accent` | verde operacional | verde vivo | laranja mecânico | verde marca |
| `mobile` | sidebar recolhível | sidebar recolhível | sidebar recolhível | sidebar recolhível |

## Regra de implementação

Uma view de rota fornece apenas `$titulo_pagina`, `$breadcrumb`, `$conteudo_pagina` e os dados do fluxo. Ela não deve repetir sidebar, topbar, footer, classe de perfil, tokens do shell ou CSS estrutural.

## Critérios de aceite por rota

1. exatamente um `.bo-header`, `.bo-sidebar`, `.bo-content` e `.bo-footer`;
2. nenhum `.gf-legacy-bo-header` renderizado;
3. nenhum mojibake no HTML visível;
4. sem overflow horizontal em 1440px e 390px;
5. formulários, `name`, `id`, `data-*`, CSRF e endpoints preservados;
6. console do navegador sem erro estrutural.

## Fonte de verdade

- `src/Views/layouts/backoffice_shell.php`
- `src/Views/layouts/backoffice_helpers.php`
- `public/assets/css/gf-bo-lovable.css`
- arquivos HTML de referência em `lovable/`

`src/Views/layouts/header.php` permanece somente como camada de compatibilidade durante a migração e não deve receber novos layouts.
