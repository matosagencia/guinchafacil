[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Repositorio,

    [string]$Banco = '',

    [string]$Usuario = 'root',
    [string]$Xampp = 'C:\xampp',
    [string]$PatchPath,
    [ValidateSet('deploy', 'rollback')]
    [string]$Acao = 'deploy'
)

$ErrorActionPreference = 'Stop'
$php = Join-Path $Xampp 'php\php.exe'
$mysql = Join-Path $Xampp 'mysql\bin\mysql.exe'
$mysqldump = Join-Path $Xampp 'mysql\bin\mysqldump.exe'
$migration = Join-Path $Repositorio 'install\migration_comissao_v1.sql'
$rollback = Join-Path $Repositorio 'install\rollback_comissao_v1.sql'

function Assert-File([string]$Path, [string]$Phase) {
    if (-not (Test-Path -LiteralPath $Path)) {
        throw "[$Phase] arquivo não encontrado: $Path"
    }
}

function Get-ConfiguredDatabaseName([string]$Root) {
    $envFiles = @('.env.local', '.env')
    foreach ($name in $envFiles) {
        $candidate = Join-Path $Root $name
        if (-not (Test-Path -LiteralPath $candidate)) { continue }

        foreach ($line in Get-Content -LiteralPath $candidate) {
            if ($line -match '^\s*DB_NAME\s*=\s*["'']?([^"''#\s]+)') {
                Write-Host "[CONFIG-DISCOVERY] DB_NAME encontrado em $name"
                return $Matches[1]
            }
        }
    }

    $configCandidates = @(
        (Join-Path $Root 'config.php'),
        (Join-Path $Root 'config\config.php')
    )
    foreach ($candidate in $configCandidates) {
        if (-not (Test-Path -LiteralPath $candidate)) { continue }
        $content = Get-Content -LiteralPath $candidate -Raw
        if ($content -match 'define\s*\(\s*[''\"]DB_NAME[''\"]\s*,\s*[''\"]([^''\"]+)[''\"]\s*\)') {
            Write-Host "[CONFIG-DISCOVERY] DB_NAME encontrado em $candidate"
            return $Matches[1]
        }
    }

    return $null
}

function Invoke-MySqlFile([string]$SqlFile, [string]$Phase) {
    Write-Host "[$Phase] executando $SqlFile"
    Get-Content -LiteralPath $SqlFile -Raw | & $mysql "-u$Usuario" $Banco
    if ($LASTEXITCODE -ne 0) {
        throw "[$Phase] mysql retornou código $LASTEXITCODE"
    }
}

function Invoke-MySqlQuery([string]$Sql, [string]$Phase) {
    $Sql | & $mysql "-u$Usuario" $Banco --batch --skip-column-names
    if ($LASTEXITCODE -ne 0) {
        throw "[$Phase] consulta MySQL retornou código $LASTEXITCODE"
    }
}

function Invoke-NativeFinanceSmokeTest {
    $smokeFile = Join-Path $env:TEMP 'guinchafacil-financeiro-smoke.php'
    $env:GF_REPO_ROOT = $Repositorio
    $smokeSource = @'
<?php

$root = (string) getenv('GF_REPO_ROOT');
if ($root === '' || !is_file($root . '/config.php')) {
    fwrite(STDERR, "[SMOKE][bootstrap][FAIL] config.php nao encontrado\n");
    exit(2);
}

function smoke_log(string $phase, string $result, string $message): void {
    echo "[SMOKE][$phase][$result] $message\n";
}
function smoke_assert(bool $condition, string $phase, string $message): void {
    if (!$condition) {
        throw new RuntimeException("$phase: $message");
    }
    smoke_log($phase, 'PASS', $message);
}

try {
    require_once $root . '/config.php';
    require_once $root . '/src/Services/ComissaoService.php';
    $pdo = getPDO();

    smoke_assert(ComissaoService::schemaDisponivel($pdo), 'ComissaoService::schemaDisponivel', 'colunas de comissao disponiveis');
    $tables = (int) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('comissoes_cidade','faturas_parceiro','faturas_itens','faturas_pagamentos')")->fetchColumn();
    smoke_assert($tables === 4, 'migration_comissao_v1', 'quatro tabelas financeiras presentes');

    $naChegada = ComissaoService::calcular(0, 'pago_na_chegada', 100.00);
    smoke_assert($naChegada['tipo'] === 'faturada' && abs((float)$naChegada['valor_liquido_parceiro'] - 100.00) < 0.001, 'ComissaoService::calcular', 'pago_na_chegada preserva o liquido do parceiro');
    $online = ComissaoService::calcular(0, 'online', 100.00);
    smoke_assert($online['tipo'] === 'descontada' && abs((float)$online['valor_liquido_parceiro'] - (100.00 - (float)$online['valor'])) < 0.001, 'ComissaoService::calcular', 'online desconta apenas a comissao');

    $candidate = $pdo->query('SELECT id FROM pedidos WHERE comissao_valor IS NULL ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$candidate) {
        smoke_log('ComissaoService::persistirParaPedido', 'INFO', 'sem pedido elegivel; calculo e schema validados, persistencia nao exercitada');
        exit(0);
    }

    $pedidoId = (int)$candidate['id'];
    $pdo->beginTransaction();
    $pedido = ['id' => $pedidoId, 'forma_pagamento_escolhida' => 'pago_na_chegada', 'custo_final' => 100.00];
    $first = ComissaoService::persistirParaPedido($pdo, $pedido);
    $second = ComissaoService::persistirParaPedido($pdo, $pedido);
    smoke_assert($first['tipo'] === $second['tipo'] && abs((float)$first['valor'] - (float)$second['valor']) < 0.001, 'ComissaoService::persistirParaPedido', 'segunda chamada reutiliza valor congelado');
    $pdo->rollBack();
    $after = $pdo->prepare('SELECT comissao_valor FROM pedidos WHERE id=?');
    $after->execute([$pedidoId]);
    smoke_assert($after->fetchColumn() === null, 'ComissaoService::persistirParaPedido', 'rollback transacional nao deixou alteracao no pedido');
    smoke_log('financeiro_sem_vendor', 'PASS', 'smoke test concluido sem vendor');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, '[SMOKE][financeiro_sem_vendor][FAIL] classe=ComissaoService funcao=calcular|persistirParaPedido fase=transacao erro=' . $e->getMessage() . "\n");
    exit(1);
}
'@
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($smokeFile, $smokeSource, $utf8NoBom)

    Write-Host "[SMOKE] executando $smokeFile"
    & $php $smokeFile
    if ($LASTEXITCODE -ne 0) { throw "[SMOKE] teste financeiro sem vendor retornou codigo $LASTEXITCODE" }
}

Assert-File $php 'PRECHECK'
Assert-File $mysql 'PRECHECK'
Assert-File $mysqldump 'PRECHECK'
Assert-File $Repositorio 'PRECHECK'
Set-Location -LiteralPath $Repositorio
$bancoConfigurado = Get-ConfiguredDatabaseName $Repositorio
if ([string]::IsNullOrWhiteSpace($bancoConfigurado)) {
    throw '[CONFIG-DISCOVERY] DB_NAME nao encontrado em .env.local, .env ou config.php.'
}
if (-not [string]::IsNullOrWhiteSpace($Banco) -and $Banco -ne $bancoConfigurado) {
    throw "[CONFIG-DISCOVERY] parametro -Banco '$Banco' diverge do DB_NAME configurado '$bancoConfigurado'. Corrija a configuracao, nao force o alvo."
}
$Banco = $bancoConfigurado
Write-Host "[CONFIG-DISCOVERY] banco alvo confirmado: $Banco"

if ($Acao -eq 'deploy') {
    if ($PatchPath) {
        Assert-File $PatchPath 'PATCH-CHECK'
        & git apply --check $PatchPath
        if ($LASTEXITCODE -ne 0) { throw '[PATCH-CHECK] git apply --check falhou' }
        & git apply --index $PatchPath
        if ($LASTEXITCODE -ne 0) { throw '[PATCH-APPLY] git apply falhou' }
        Write-Host '[PATCH-APPLY] patch aplicado.'
    }
    Assert-File $migration 'PRECHECK'

    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $backup = Join-Path $Repositorio "backup-$Banco-$timestamp.sql"
    Write-Host "[BACKUP] criando $backup (o MySQL pode solicitar senha)"
    & $mysqldump "-u$Usuario" --single-transaction --routines --triggers "--result-file=$backup" $Banco
    if ($LASTEXITCODE -ne 0) { throw "[BACKUP] mysqldump retornou código $LASTEXITCODE" }

    # A segunda execução confirma a idempotência da migration.
    Invoke-MySqlFile $migration 'MIGRATION-1'
    Invoke-MySqlFile $migration 'MIGRATION-2-IDEMPOTENCIA'

    $schemaAssertion = @"
SELECT CONCAT('tabelas=', COUNT(*))
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('comissoes_cidade','faturas_parceiro','faturas_itens','faturas_pagamentos');
SELECT CONCAT('colunas_pedidos=', COUNT(*))
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos'
  AND COLUMN_NAME IN ('comissao_valor','comissao_tipo','valor_liquido_parceiro','fatura_id');
SELECT CONCAT('fk_pedidos_fatura=', COUNT(*))
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND CONSTRAINT_NAME = 'fk_pedidos_fatura';
"@
    $schemaResult = Invoke-MySqlQuery $schemaAssertion 'SCHEMA-ASSERT'
    $expected = @('tabelas=4','colunas_pedidos=4','fk_pedidos_fatura=1')
    foreach ($line in $expected) {
        if ($schemaResult -notcontains $line) { throw "[SCHEMA-ASSERT] esperado '$line'; recebido: $($schemaResult -join ', ')" }
    }
    Write-Host '[SCHEMA-ASSERT] OK: tabelas, colunas e FK presentes.'

    $phpFiles = @(
        'src\Services\ComissaoService.php',
        'src\Services\FaturaService.php',
        'src\Services\PixFaturaService.php',
        'src\Services\PixService.php',
        'src\Controllers\FaturaWebhookController.php',
        'src\Services\Pedido\PedidoTransitionService.php',
        'cron\fechar_ciclos_semanais.php',
        'cron\aplicar_bloqueios_vencidos.php'
    )
    foreach ($relative in $phpFiles) {
        $file = Join-Path $Repositorio $relative
        Assert-File $file 'PHP-LINT'
        $lint = & $php -l $file
        if ($LASTEXITCODE -ne 0) { throw "[PHP-LINT] falha em ${relative}: $lint" }
        Write-Host "[PHP-LINT] OK $relative"
    }

    Invoke-NativeFinanceSmokeTest

    $phpunit = Join-Path $Repositorio 'vendor\bin\phpunit'
    if (Test-Path -LiteralPath $phpunit) {
        & $php $phpunit tests/Integration/PedidoTransitionServiceTest.php tests/Integration/PedidoTransitionServiceL13Test.php tests/Unit/PixServiceTest.php tests/Unit/WebhookSignatureTest.php
        if ($LASTEXITCODE -ne 0) { throw "[PHPUNIT] regressao retornou codigo $LASTEXITCODE" }
    } else {
        Write-Host '[PHPUNIT][SKIPPED] vendor/bin/phpunit ausente; smoke nativo executado.'
    }
    Write-Host "[CONCLUIDO] deploy validado. Backup: $backup"
    Write-Host '[AVISO] Fatura/PIX real deve ser exercitado somente em banco e credenciais sandbox; o cron chama a API Mercado Pago.'
}
else {
    Assert-File $rollback 'ROLLBACK-PRECHECK'
    Invoke-MySqlFile $rollback 'ROLLBACK-1'
    # A segunda execução confirma a idempotência. Se houver dados, ambas apenas SKIPPED.
    Invoke-MySqlFile $rollback 'ROLLBACK-2-IDEMPOTENCIA'
    Write-Host '[CONCLUIDO] rollback executado. Se ele informou SKIPPED, restaure o backup em vez de forçar DROP.'
}
