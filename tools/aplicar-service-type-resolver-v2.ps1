[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Repositorio,
    [string]$PhpExe = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Log([string]$Fase, [string]$Mensagem) {
    Write-Host "[$Fase] $Mensagem"
}

function Falhar([string]$Mensagem) {
    throw "[FAIL][ServiceTypeResolverV2] $Mensagem"
}

function Escrever-Utf8SemBom([string]$Caminho, [string]$Conteudo) {
    [System.IO.File]::WriteAllText(
        $Caminho,
        $Conteudo,
        [System.Text.UTF8Encoding]::new($false)
    )
}

$repo = (Resolve-Path -LiteralPath $Repositorio).Path
$resolver = Join-Path $repo 'src\Services\Catalog\ServiceTypeResolver.php'
$index = Join-Path $repo 'index.php'
$testPuro = Join-Path $repo 'tests\ServiceTypeResolverTest.php'
$testDb = Join-Path $repo 'tests\ServiceTypeResolverDbTest.php'
$contratos = Join-Path $repo 'doc\CONTRATOS.md'
$origem = Split-Path -Parent $PSScriptRoot

foreach ($arquivo in @($resolver, $index, $testPuro, $contratos)) {
    if (-not (Test-Path -LiteralPath $arquivo)) {
        Falhar "Arquivo obrigatorio nao encontrado: $arquivo"
    }
}
if (-not (Test-Path -LiteralPath $PhpExe)) {
    Falhar "PHP CLI nao encontrado: $PhpExe"
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $repo ('.backup-service-type-resolver-v2-' + $stamp)
New-Item -ItemType Directory -Path $backup | Out-Null
Copy-Item -LiteralPath $resolver, $index, $testPuro, $contratos -Destination $backup
if (Test-Path -LiteralPath $testDb) { Copy-Item -LiteralPath $testDb -Destination $backup }
Log 'BACKUP' $backup

$conteudoResolver = Get-Content -LiteralPath $resolver -Raw
if ($conteudoResolver -notmatch 'namespace\s+App\\Services\\Catalog\s*;') {
    if ($conteudoResolver -notmatch 'declare\(strict_types=1\);') {
        Falhar 'ServiceTypeResolver.php nao possui declare(strict_types=1); ancora de patch ausente.'
    }
    $conteudoResolver = $conteudoResolver -replace '(declare\(strict_types=1\);\r?\n)', "`$1`r`nnamespace App\Services\Catalog;`r`n"
    Escrever-Utf8SemBom $resolver $conteudoResolver
    Log 'NAMESPACE' 'App\Services\Catalog declarado.'
} else {
    Log 'NAMESPACE' 'Ja declarado; nenhuma alteracao.'
}

$conteudoIndex = Get-Content -LiteralPath $index -Raw
$marcador = '[A-RESOLVER-BOOT-01]'
if ($conteudoIndex -notmatch [regex]::Escape($marcador)) {
    $ancora = 'set_exception_handler'
    $posicao = $conteudoIndex.IndexOf($ancora, [System.StringComparison]::Ordinal)
    if ($posicao -lt 0) {
        Falhar 'Ancora set_exception_handler nao encontrada no index.php; bootstrap nao foi inserido.'
    }
    $bootstrap = @'
// [A-RESOLVER-BOOT-01] Liga o slug publico ao catalogo real de service_types.
// O callback e lazy: getPDO() so abre conexao se porSlug() for chamado.
require_once __DIR__ . '/src/Services/Catalog/ServiceTypeResolver.php';

\App\Services\Catalog\ServiceTypeResolver::definirLookupPorCodigo(
    static function (string $code): ?int {
        try {
            $stmt = \getPDO()->prepare(
                'SELECT id FROM service_types WHERE code = ? AND active = 1 LIMIT 1'
            );
            $stmt->execute([$code]);
            $id = $stmt->fetchColumn();

            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            try {
                \Logger::event([
                    'level' => \Logger::LEVEL_ERROR,
                    'class' => 'ServiceTypeResolver',
                    'function' => 'lookupPorCodigo',
                    'system' => 'Catalog',
                    'code' => 'STR-LOOKUP-FAIL',
                    'message' => 'Falha ao resolver service_type code=' . $code,
                    'context' => ['code' => $code, 'exception' => $e->getMessage()],
                ]);
            } catch (\Throwable $logError) {
                error_log('[STR-LOOKUP-FAIL] code=' . $code . ' error=' . $e->getMessage());
            }

            return null;
        }
    }
);

'@
    $conteudoIndex = $conteudoIndex.Insert($posicao, $bootstrap)
    Escrever-Utf8SemBom $index $conteudoIndex
    Log 'BOOTSTRAP' 'Inserido antes de set_exception_handler.'
} else {
    Log 'BOOTSTRAP' 'Marcador ja existe; nenhuma alteracao.'
}

$fonteTestePuro = Join-Path $origem 'tests\ServiceTypeResolverTest.php'
$fonteTesteDb = Join-Path $origem 'tests\ServiceTypeResolverDbTest.php'
if ([System.IO.Path]::GetFullPath($fonteTestePuro) -ne [System.IO.Path]::GetFullPath($testPuro)) {
    Copy-Item -LiteralPath $fonteTestePuro -Destination $testPuro -Force
}
if ([System.IO.Path]::GetFullPath($fonteTesteDb) -ne [System.IO.Path]::GetFullPath($testDb)) {
    Copy-Item -LiteralPath $fonteTesteDb -Destination $testDb -Force
}
Log 'TESTS' 'Runners confirmados; copia propria ignorada quando o pacote esta na raiz do projeto.'

$contratoTexto = Get-Content -LiteralPath $contratos -Raw
if ($contratoTexto -notmatch [regex]::Escape($marcador)) {
    $doc = Get-Content -LiteralPath (Join-Path $origem 'doc\CONTRATO_RESPOSTA.md') -Raw
    Escrever-Utf8SemBom $contratos ($contratoTexto.TrimEnd() + "`r`n`r`n<!-- $marcador -->`r`n" + $doc + "`r`n")
    Log 'CONTRATO' 'Campo bootstrap publicado em doc\CONTRATOS.md.'
} else {
    Log 'CONTRATO' 'Marcador ja existe; nenhuma alteracao.'
}

foreach ($arquivo in @($resolver, $index, $testPuro, $testDb)) {
    & $PhpExe -l $arquivo
    if ($LASTEXITCODE -ne 0) { Falhar "php -l falhou: $arquivo" }
}

& $PhpExe $testPuro
if ($LASTEXITCODE -ne 0) { Falhar 'Teste puro falhou.' }
& $PhpExe $testDb
if ($LASTEXITCODE -ne 0) { Falhar 'Teste de banco falhou.' }

Log 'DONE' 'Resolver plugado ao catalogo e validado.'
