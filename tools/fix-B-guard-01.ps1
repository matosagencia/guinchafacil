#requires -Version 5.1
<#[
.SYNOPSIS
    Ativa o B-GUARD-01 em AdminController.php de forma segura e idempotente.
.DESCRIPTION
    Substitui o bloco comentado "Aguardando a Faixa A entregar" pela validação
    GuinchoDisponibilidade::existeNoRaio(). Cria backup antes de gravar e salva
    em UTF-8 sem BOM. Pode ser executado novamente sem duplicar o guard.
.EXAMPLE
    .\tools\fix-B-guard-01.ps1
.EXAMPLE
    .\tools\fix-B-guard-01.ps1 -ProjectRoot 'C:\xampp\htdocs\guinchafacil'
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [string]$ProjectRoot = (Get-Location).Path
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$relativePath = Join-Path 'src' (Join-Path 'Controllers' 'AdminController.php')
$filePath = Join-Path $ProjectRoot $relativePath

if (-not (Test-Path -LiteralPath $filePath -PathType Leaf)) {
    throw "Arquivo não encontrado: $filePath. Informe a raiz correta com -ProjectRoot."
}

# Ler como UTF-8, aceitando arquivo com ou sem BOM.
$utf8WithBom = New-Object System.Text.UTF8Encoding($true)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$bytes = [System.IO.File]::ReadAllBytes($filePath)
$startOffset = 0
if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
    $startOffset = 3
}
$texto = [System.Text.Encoding]::UTF8.GetString($bytes, $startOffset, $bytes.Length - $startOffset)

$guardMarker = '[B-GUARD-01]'
$guardPattern = '(?m)^\s*//\s*\[B-GUARD-01\].*$'
if ([regex]::IsMatch($texto, $guardPattern)) {
    Write-Host 'B-GUARD-01 já está presente. Nenhuma alteração necessária.' -ForegroundColor Green
    exit 0
}

# A dependência deve existir exatamente como require_once, sem inserir duplicatas.
$requirePattern = "(?m)^\s*require_once\s+__DIR__\s*\.\s*'/\.\./Services/GuinchoDisponibilidade\.php';\s*$"
if (-not [regex]::IsMatch($texto, $requirePattern)) {
    throw "O require_once de Services/GuinchoDisponibilidade.php não foi encontrado. Nenhuma alteração feita."
}

# Localiza o bloco legado pelo início e pelo separador imediatamente anterior ao B-GUARD-02.
$inicioLiteral = '// Aguardando a Faixa A entregar:'
$inicioCount = ([regex]::Matches($texto, [regex]::Escape($inicioLiteral))).Count
if ($inicioCount -ne 1) {
    throw "Esperava encontrar exatamente 1 âncora '$inicioLiteral'; encontrei $inicioCount. Nenhuma alteração feita."
}

$inicio = $texto.IndexOf($inicioLiteral, [System.StringComparison]::Ordinal)
if ($inicio -lt 0) {
    throw 'Não foi possível localizar o início do bloco legado. Nenhuma alteração feita.'
}

# Incluir os comentários e linhas anteriores do bloco, mas preservar o separador de B-GUARD-02.
$inicioLinha = $texto.LastIndexOf("`n", $inicio)
if ($inicioLinha -lt 0) { $inicioLinha = 0 } else { $inicioLinha++ }
$marcadorFim = '// [B-GUARD-02] Reboque sem cotacao valida -> rejeita'
$fim = $texto.IndexOf($marcadorFim, $inicio, [System.StringComparison]::Ordinal)
if ($fim -lt 0) {
    throw "Não foi encontrado o marcador '$marcadorFim' após o bloco legado. Nenhuma alteração feita."
}
$fimLinha = $texto.LastIndexOf("`n", $fim)
if ($fimLinha -lt $inicioLinha) {
    throw 'Os limites do bloco legado são inválidos. Nenhuma alteração feita.'
}

# O trecho substituído começa na linha de abertura do comentário grande, encontrada antes da âncora.
$abertura = $texto.LastIndexOf('// =============================================================', $inicio, [System.StringComparison]::Ordinal)
if ($abertura -lt 0) {
    throw 'Não foi encontrada a abertura do bloco comentado. Nenhuma alteração feita.'
}
$aberturaLinha = $texto.LastIndexOf("`n", $abertura)
if ($aberturaLinha -lt 0) { $aberturaLinha = 0 } else { $aberturaLinha++ }

# Garante que estamos substituindo apenas o bloco esperado, não uma região maior do método.
$blocoLegado = $texto.Substring($aberturaLinha, $fimLinha - $aberturaLinha)
if ($blocoLegado -notmatch '(?s)//\s*Aguardando a Faixa A entregar:' -or
    $blocoLegado -notmatch 'GuinchoDisponibilidade::existeNoRaio\(' -or
    $blocoLegado -notmatch 'Enquanto esse metodo nao existir') {
    throw 'O conteúdo encontrado não corresponde ao bloco legado esperado. Nenhuma alteração feita.'
}

$novoBloco = @'
        // =============================================================
        // [B-GUARD-01] Impede criar pedido de reboque sem guincho no raio.
        if (!GuinchoDisponibilidade::existeNoRaio(
            $latOrigem,
            $lngOrigem,
            null,
            null
        )) {
            $_SESSION['_flash'][] = [
                'message' => 'Nenhum guincho disponível no raio de atendimento agora. '
                           . 'Aguarde alguns minutos ou tente outro destino.',
                'type' => 'error',
            ];
            $this->redirect('/admin/pedido/novo/funil');
            return;
        }
        // =============================================================

'@

$novoTexto = $texto.Substring(0, $aberturaLinha) + $novoBloco + $texto.Substring($fimLinha)

# Validar que o guard aparece uma vez e que o B-GUARD-02 continua intacto.
$guardCount = ([regex]::Matches($novoTexto, $guardPattern)).Count
if ($guardCount -ne 1) {
    throw "Validação falhou: esperado 1 B-GUARD-01, encontrado $guardCount. Arquivo original preservado."
}
if (-not $novoTexto.Contains($marcadorFim)) {
    throw 'Validação falhou: B-GUARD-02 não foi preservado. Arquivo original preservado.'
}
if (-not [regex]::IsMatch($novoTexto, $requirePattern)) {
    throw 'Validação falhou: require_once da dependência não foi preservado. Arquivo original preservado.'
}

# Backup único com timestamp; só grava o arquivo depois das validações.
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupPath = "$filePath.bak-B-GUARD-01-$timestamp"
[System.IO.File]::Copy($filePath, $backupPath, $false)

try {
    [System.IO.File]::WriteAllText($filePath, $novoTexto, $utf8NoBom)
} catch {
    # Se a escrita falhar, restaura o original a partir do backup.
    [System.IO.File]::Copy($backupPath, $filePath, $true)
    throw
}

# Releitura para confirmar UTF-8 válido, ausência de BOM e persistência do guard.
$finalBytes = [System.IO.File]::ReadAllBytes($filePath)
if ($finalBytes.Length -ge 3 -and $finalBytes[0] -eq 0xEF -and $finalBytes[1] -eq 0xBB -and $finalBytes[2] -eq 0xBF) {
    [System.IO.File]::Copy($backupPath, $filePath, $true)
    throw 'A validação final detectou BOM UTF-8; o arquivo original foi restaurado.'
}
$finalText = [System.Text.Encoding]::UTF8.GetString($finalBytes)
if (-not [regex]::IsMatch($finalText, $guardPattern)) {
    [System.IO.File]::Copy($backupPath, $filePath, $true)
    throw 'A validação final não encontrou B-GUARD-01; o arquivo original foi restaurado.'
}

Write-Host 'B-GUARD-01 ativado com sucesso.' -ForegroundColor Green
Write-Host "Arquivo: $filePath"
Write-Host "Backup:  $backupPath"
Write-Host 'Codificação: UTF-8 sem BOM'
