# Integração — menu admin e rotas

Escrito no padrão documentado do projeto (classes `sidebar-title` /
`sidebar-link`, ícones Font Awesome 6, seção "Sistema" da sidebar admin).
**Cole seu `src/Views/layouts/sidebar_admin.php` real e eu ajusto isto
para o encaixe exato** (indentação, variável de "rota ativa" etc.).

## 1. Sidebar admin

Sugestão de posição: nova seção "Aquisição", logo após "Financeiro" e
antes de "Comunicados" (se o módulo de Comunicados já existir) — os
dois são ferramentas de crescimento/alcance, faz sentido ficarem juntas.

```php
<div class="sidebar-title">Aquisição</div>
<a class="sidebar-link<?= str_starts_with($rotaAtual ?? '', '/admin/prospeccao') ? ' active' : '' ?>"
   href="/admin/prospeccao">
    <i class="fas fa-user-plus"></i> Prospecção de Parceiros
</a>
```

Se preferir não criar seção nova, encaixa dentro de "Sistema", junto de
Configurações/Logs/Health.

## 2. Rotas (ajustar à sintaxe real do roteador em `index.php`)

```php
// GET
'/admin/prospeccao'          => [AdminProspeccaoController::class, 'index'],
'/admin/prospeccao/regioes'  => [AdminProspeccaoController::class, 'regioes'],

// POST
'/admin/prospeccao/regioes/salvar'        => [AdminProspeccaoController::class, 'regiaoSalvar'],
'/admin/prospeccao/buscar'                => [AdminProspeccaoController::class, 'buscar'],
'/admin/prospeccao/lead/{id}/enviado'     => [AdminProspeccaoController::class, 'marcarEnviado'],
'/admin/prospeccao/lead/{id}/cadastrado'  => [AdminProspeccaoController::class, 'confirmarCadastro'],
```

## 3. `.env`

```env
SERPAPI_KEY=
```

Se o projeto já tem a tela de governança do `.env` (`/admin/env`),
adicionar `SERPAPI_KEY` ao grupo "Simulado / testes" ou criar grupo
"Prospecção".

## 4. Autoload

Os novos arquivos seguem `namespace App\Services\Prospeccao` e
`App\Controllers`. Confirmar que o autoloader do projeto
(`autoload.php`) já cobre `src/Services/Prospeccao/*` — se ele mapear
por convenção PSR-4 a partir de `src/`, não precisa de nenhuma
alteração.

## 5. Pendências marcadas com TODO no código

- `AdminProspeccaoController::validarCsrf()` — plugar na validação de
  CSRF já usada pelos outros controllers admin.
- `ProspeccaoParceirosService` — trocar o `Logger::event(...)` pelo
  namespace real da classe `Logger` do projeto.
- `MensagemPersuasaoService` — o texto do convite é um rascunho
  inicial; ajustar tom conforme sua marca antes de usar em produção.
