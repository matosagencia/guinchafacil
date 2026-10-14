# limpar-comissao-contratos.ps1
# Unifica §5.7 (comissão) e remove tabela tiered de §6.
# Idempotente, com backup, UTF-8 sem BOM.

$ErrorActionPreference = 'Stop'

$raiz    = Split-Path -Parent $MyInvocation.MyCommand.Path
$raiz    = Split-Path -Parent $raiz
$arquivo = Join-Path $raiz 'doc\CONTRATOS.md'

if (-not (Test-Path $arquivo)) { Write-Error "Nao encontrado: $arquivo"; exit 1 }

$stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$arquivo.bak-$stamp"
Copy-Item $arquivo $backup
Write-Host "Backup: $backup"

$texto = [System.IO.File]::ReadAllText($arquivo, [System.Text.Encoding]::UTF8)

# --- 1. Substitui o bloco §5.7 inteiro ---
$novo57 = @'
### §5.7 Comissão

Take rate oficial: **8% flat**, configurável no admin.

**Fonte única:** `configuracoes.comissao_plataforma` (valor decimal, ex.: `0.08`).
**Leitura:** exclusivamente via `Configuracao::get('comissao_plataforma')`. Nenhum valor hardcoded em código.

**Configuração no admin:** a chave é editável em `/admin/configuracoes` como qualquer outra. Mudança vale para o próximo pedido concluído; pedidos já concluídos mantêm o snapshot gravado no `pagamentos.valor_plataforma`.

**Regras:**
- `comissoes_cidade` fica desativada até haver densidade que justifique diferenciação por cidade.
- Tabela tiered por faixa de km (12/10/8/6) **não é oficial**. Foi considerada e descartada. Se voltar, precisa de migração e nova seção.
- Teto por corrida: sem teto. Avaliar após 30 corridas reais.

**Chaves aposentadas:**
- `comissao_assistencia_percentual` — remover de `configuracoes` (não usada).
- `service_price_rules.platform_fee_value` — se preenchido, tem precedência sobre a chave global para aquele `service_type_id` específico (override por serviço, opcional).
'@

$padrao57 = '(?ms)^### §5\.7 Comissão.*?(?=^### §5\.8|^## §6)'
if ($texto -match $padrao57) {
    $texto = [regex]::Replace($texto, $padrao57, ($novo57 + "`r`n`r`n"))
    Write-Host "§5.7 substituido."
} else {
    Write-Warning "§5.7 nao encontrado."
}

# --- 2. Remove a tabela tiered do §6 ---
$antigo6 = @'
**Take rate (D-P2 — 12/10/2026):**
- 0–30 km: 12%
- 31–80 km: 10%
- 81–150 km: 8%
- >150 km: 6% ou R$ 60 (o maior)
- Assistência local: R$ 25 fixos + 5%
'@

$novo6 = @'
**Take rate (consolidado 12/10/2026):**
- **8% flat** sobre o valor líquido pós-gateway. Configurável em `configuracoes.comissao_plataforma`.
- Precedência: `service_price_rules.platform_fee_value` (override por serviço) > `comissoes_cidade.percentual` (desativada) > `configuracoes.comissao_plataforma` (global, 0.08).
- Detalhes em §5.7.
'@

$antigo6 = $antigo6 -replace "`r`n", "`n"
$texto    = $texto    -replace "`r`n", "`n"

if ($texto -match [regex]::Escape($antigo6)) {
    $texto = $texto.Replace($antigo6, ($novo6 -replace "`r`n", "`n"))
    Write-Host "Tabela tiered do §6 removida."
} else {
    Write-Warning "Bloco tiered do §6 nao encontrado (ja pode ter sido alterado)."
}

$texto = $texto -replace "`n", "`r`n"

$utf8SemBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($arquivo, $texto, $utf8SemBom)

Write-Host "OK - CONTRATOS.md atualizado."