# atualizar-contratos.ps1
# Reescreve Â§5 e Â§7 do doc/CONTRATOS.md em UTF-8 sem BOM.
# Idempotente, com backup.

$ErrorActionPreference = 'Stop'

$toolsDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$raiz     = Split-Path -Parent $toolsDir
$arquivo  = Join-Path $raiz 'doc\CONTRATOS.md'

if (-not (Test-Path $arquivo)) {
    Write-Error "Arquivo nÃ£o encontrado: $arquivo"
    exit 1
}

$stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$arquivo.bak-$stamp"
Copy-Item $arquivo $backup
Write-Host "Backup: $backup"

$texto = [System.IO.File]::ReadAllText($arquivo, [System.Text.Encoding]::UTF8)

$novo5 = @'
## Â§5. PrecificaÃ§Ã£o

**Fonte Ãºnica:** `service_price_rules` (tabela versionada, com vigÃªncia e `version`).

**ReferÃªncia de mercado:** Ache Guincho 2026 â€” linear, taxa de saÃ­da R$ 120â€“250 + R$ 5â€“8/km para carro de passeio.

**Tabelas aposentadas:** `service_pricing_rules` (vazia, congelar) e as chaves `tarifa_*` em `configuracoes` (migrar e congelar).

### Â§5.1 FÃ³rmula base

PreÃ§o_cliente = taxa_saida
              + [(km_transporte Ã— tarifa_km) Ã— fator_veiculo Ã— fator_periodo]
              + deadhead_do_anel
              + fator_terreno_fixo
              + pedagio_repassado

### Â§5.2 Base de km â€” decisÃ£o obrigatÃ³ria

`service_price_rules.base_km_contagem` ENUM('transporte','percurso_completo','percurso_efetivo').

- `transporte`: apenas coleta â†’ destino. Deadhead absorvido na taxa de saÃ­da.
- `percurso_completo`: deadhead + transporte + retorno.
- `percurso_efetivo`: deadhead + transporte, sem retorno.

Sem esta coluna preenchida, a cotaÃ§Ã£o nÃ£o pode ser gerada. O erro de Campinas nasceu da ausÃªncia dela.

### Â§5.3 Colunas novas em service_price_rules

| Coluna | Tipo | FunÃ§Ã£o |
|---|---|---|
| `base_km_contagem` | ENUM | Qual km estÃ¡ sendo cobrado |
| `fator_veiculo` | DECIMAL(4,2) | Moto, carro, SUV |
| `fator_periodo_noturno` | DECIMAL(4,2) | 1,20â€“1,50 |
| `fator_periodo_fds` | DECIMAL(4,2) | 1,20â€“1,50 |
| `fator_periodo_feriado` | DECIMAL(4,2) | 1,20â€“1,50 |
| `taxa_terreno_leve` | DECIMAL(10,2) | R$ 40â€“80 |
| `taxa_terreno_acentuado` | DECIMAL(10,2) | R$ 80â€“150 |
| `taxa_manobra_ladeira` | DECIMAL(10,2) | R$ 50â€“120 |

### Â§5.4 Fatores

- `fator_veiculo`: multiplica `(km Ã— tarifa)`.
- `fator_periodo`: multiplica `(km Ã— tarifa)`.
- Terreno: **taxa fixa**, nÃ£o multiplica.
  - leve (grade â‰¥ 6% por â‰¥ 1 km): R$ 40â€“80
  - acentuado (grade â‰¥ 8%): R$ 80â€“150
  - manobra em ladeira no hook-up: R$ 50â€“120
- **Altitude nÃ£o cobra.** Fica como dado operacional/informativo no pedido.

### Â§5.5 Deadhead por anel

O deadhead Ã© do anel, nÃ£o do guincho individual. Ver Â§7.

### Â§5.6 PedÃ¡gio

Repassado integralmente, com comprovante. Guincho plataforma 2 eixos. VeÃ­culo em cima da plataforma nÃ£o gera cobranÃ§a autÃ´noma (Free Flow).

### Â§5.7 ComissÃ£o

Take rate inicial: **8%**. Teto por corrida a definir. `comissoes_cidade` fica desativada atÃ© haver densidade que justifique diferenciaÃ§Ã£o.

### Â§5.8 Detalhamento obrigatÃ³rio

Toda cotaÃ§Ã£o grava em `order_price_snapshots`:
- `taxa_saida`, `km_transporte`, `tarifa_km`, `base_km_contagem`
- `fator_veiculo`, `fator_periodo` aplicado
- `deadhead_ref` e anel usado
- `fator_terreno` aplicado
- `pedagio`
- `comissao` (8%)
- `valor_total`

### Â§5.9 Estorno parcial

Quando um guincho de anel interno aceita apÃ³s cotaÃ§Ã£o em anel externo, estorno automÃ¡tico da diferenÃ§a de deadhead.

- Mercado Pago: `POST /v1/payments/{id}/refunds` com `{ "amount": <valor> }`
- PagSeguro: `POST /charges/{id}/cancel` com `{ "amount": <valor> }`
- Trilha em `pedido_eventos` tipo `estorno_diferenca_deadhead`
- Retry com backoff se saldo insuficiente; alerta no admin
'@

$novo7 = @'
## Â§7. Disponibilidade (Bâ†’A)

**Interface antiga:** `GuinchoDisponibilidade::existeNoRaio()` â€” deprecada.

**Interface nova:**

GuinchoDisponibilidade::candidatosPorAnel(
    float $lat, float $lng, int $service_type_id
): array

Retorno por anel: `candidatos[]`, `deadhead_ref`, `elegiveis`.

### Â§7.1 AnÃ©is

ConfigurÃ¡veis no admin. Default: 2, 5, 10, 20, 35 km.

### Â§7.2 Filtro duro

`aprovado = 1 AND disponivel = 1 AND reboque_aprovado = 1
AND NÃƒO bloqueado financeiramente (Â§9)
AND dist_km <= raio_maximo`

### Â§7.3 Deadhead de referÃªncia

Sempre o **centro do anel**. NÃ£o Ã© a mÃ©dia, nÃ£o Ã© o mais distante.

### Â§7.4 Regra de escolha do anel

Menor anel com **â‰¥ 3 elegÃ­veis**. O `deadhead_ref` daquele anel Ã© aplicado a todos os candidatos daquele anel, garantindo igualdade de preÃ§o.

Se nenhum anel atÃ© 35 km tiver â‰¥ 3 elegÃ­veis: fallback WhatsApp.

### Â§7.5 OrdenaÃ§Ã£o interna

1. Menor distÃ¢ncia real
2. Maior reputaÃ§Ã£o
3. Aceites recentes
4. AleatÃ³rio

### Â§7.6 Estorno automÃ¡tico

Se o cliente jÃ¡ pagou deadhead de anel externo e um guincho de anel interno aceita, estorno parcial automÃ¡tico (ver Â§5.9).

### Â§7.7 Bloqueio financeiro (Â§9)

Bloqueado nÃ£o conta como elegÃ­vel. NÃ£o recebe oferta. **NÃ£o recebe notificaÃ§Ã£o atÃ© quitar.**
'@

$padrao5 = '(?ms)^## Â§5\..*?(?=^## Â§)'
$padrao7 = '(?ms)^## Â§7\..*?(?=^## Â§)'

if ($texto -match $padrao5) {
    $texto = [regex]::Replace($texto, $padrao5, ($novo5 + "`r`n`r`n"))
    Write-Host "Â§5 substituÃ­do."
} else {
    Write-Warning "Secao 5 nao encontrada - nada substituido."
}

if ($texto -match $padrao7) {
    $texto = [regex]::Replace($texto, $padrao7, ($novo7 + "`r`n`r`n"))
    Write-Host "Â§7 substituÃ­do."
} else {
    Write-Warning "Secao 7 nao encontrada - nada substituido."
}

$utf8SemBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($arquivo, $texto, $utf8SemBom)

Write-Host "OK - $arquivo atualizado em UTF-8 sem BOM."
