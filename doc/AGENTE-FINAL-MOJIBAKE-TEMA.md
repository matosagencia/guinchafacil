# Agente final de mojibake e tema

O projeto deve executar o agente abaixo antes de qualquer commit ou deploy:

```powershell
.\tools\agents\gf-layout-encoding-agent.ps1
```

O agente verifica:

- UTF-8 valido nos arquivos PHP, JS, CSS e HTML alterados;
- mojibake no texto dos arquivos alterados e no HTML renderizado;
- `php -l` nos PHP alterados;
- exatamente um `.bo-header` e um sidebar no backoffice;
- ausencia de navbar legada;
- ausencia de fundo preto no conteudo;
- `/admin/central` como referencia visual para `/admin/pedido/novo`, `/admin/pedido/novo/funil` e `/admin/configuracoes`.

Para uma varredura completa do repositorio:

```powershell
.\tools\agents\gf-layout-encoding-agent.ps1 -AllTracked
```

O retorno `1` bloqueia o commit/deploy. Nao mascarar mojibake com CSS: corrigir a origem da string e preservar o shell estrutural existente.
