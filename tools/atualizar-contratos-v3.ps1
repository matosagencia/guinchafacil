# atualizar-contratos-v3.ps1
# Atualiza §5.4 (ganho de elevacao) e §5.7 (comissao sobre bruto).
# Idempotente, backup, UTF-8 sem BOM.

$ErrorActionPreference = 'Stop'

$raiz    = Split-Path -Parent $MyInvocation.MyCommand.Path
$raiz    = Split-Path -Parent $raiz
$arquivo = Join-Path $raiz 'doc\CONTRATOS.md'
if (-not (Test-Path $arquivo)) { Write-Error "Nao encontrado: $arquivo"; exit 1 }

$stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
Copy-Item $arquivo "$arquivo.bak-v3-$stamp"
Write-Host "Backup: $arquivo.bak-v3-$stamp"

$texto = [System.IO.File]::ReadAllText($arquivo, [System.Text.Encoding]::UTF8)

$novo54 = @'
### §5.4 Fatores

- `fator_veiculo`: multiplica `(km × tarifa)`.
- `fator_periodo`: multiplica `(km × tarifa)`.
- Terreno — taxas fixas, não multiplicam:
  - leve (grade ≥ 6% por ≥ 1 km): R$ 40–80
  - acentuado (grade ≥ 8%): R$ 80–150
  - manobra em ladeira no hook-up: R$ 50–120
- **Ganho de elevação total** — multiplicador sobre o bloco variável
  `(km × tarifa × fator_veiculo × fator_periodo)`:
  - 0–100 m: 1,00
  - 100–300 m: 1,05
  - 300–600 m: 1,10
  - 600–1000 m: 1,18
  - >1000 m: 1,25
  Configurável em `configuracoes.ganho_elevacao_faixas` (metros) e
  `configuracoes.ganho_elevacao_mult` (multiplicadores).
- **Altitude absoluta não cobra.** Fica como dado operacional/informativo
  no pedido. Distinto de ganho de elevação: estar a 750 m de altitude
  numa rua plana não custa; subir 750 m contra a gravidade custa.
'@

$padrao54 = '(?ms)^### §5\.4 Fatores.*?(?=^### §5\.5|^### §5\.6|^---)'
if ($texto -match $padrao54) {
    $texto = [regex]::Replace($texto, $padrao54, ($novo54 + "`r`n`r`n"))
    Write-Host "§5.4 substituido."
} else {
    Write-Warning "§5.4 nao encontrado."
}

$novo57 = @'
### §5.7 Comissão

Take rate: **8% flat sobre o valor BRUTO do pedido**.

**Fonte única:** `configuracoes.comissao_plataforma` (decimal, ex.: `0.08`).
**Leitura:** exclusivamente via `Configuracao::get('comissao_plataforma')`.
Nenhum valor hardcoded em código.

**Gateway — reserva dinâmica por método de pagamento:**

O valor real do gateway depende do método escolhido pelo cliente. A reserva
é feita em duas etapas:

1. **Na cotação** (método ainda desconhecido): usar `reserva_gateway_credito`
   (crédito à vista, pior caso comum). O cliente vê o preço final com a
   reserva máxima.
2. **No webhook de aprovação**: o método real é detectado pelo payload do
   gateway. Se foi PIX (`reserva_gateway_pix`, 0,49%), a diferença entre
   a reserva máxima e a real cai automaticamente no repasse do guincho.

**Chaves de reserva (configuráveis):**
- `reserva_gateway_pix` = 0,49%
- `reserva_gateway_debito` = 1,99%
- `reserva_gateway_credito` = 4,98% (default na cotação)
- `reserva_gateway_boleto` = 1,99%
- `reserva_gateway_percentual` = 4,98% (fallback legado)

**Fórmula do split (§5.7):**

```
valor_plataforma = total × comissao_plataforma
valor_gateway = total × reserva_gateway (dinâmica por método)
valor_guincho = total − valor_plataforma − valor_gateway
```

Os três somam exatamente o total. Sem estouro.

**Exibição ao guincho:**

```
Você receberá: R$ 794,85
Retenção GuinchaFácil: R$ 113,55
```

Comissão + gateway consolidados em um único número. O detalhamento
interno fica no `pedido_eventos` e no painel financeiro do backoffice.

**Regras:**
- `comissoes_cidade` desativada até haver densidade para diferenciação.
- Tabela tiered por faixa de km (12/10/8/6) **não é oficial**. Descartada.
- Teto por corrida: sem teto por enquanto.

**Chaves aposentadas:**
- `comissao_assistencia_percentual` — removida.
- `service_price_rules.platform_fee_value` — override por serviço, opcional.
'@

$padrao57 = '(?ms)^### §5\.7 Comissão.*?(?=^### §5\.8|^## §6)'
if ($texto -match $padrao57) {
    $texto = [regex]::Replace($texto, $padrao57, ($novo57 + "`r`n`r`n"))
    Write-Host "§5.7 substituido."
} else {
    Write-Warning "§5.7 nao encontrado."
}

$utf8SemBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($arquivo, $texto, $utf8SemBom)
Write-Host "OK - CONTRATOS.md atualizado."