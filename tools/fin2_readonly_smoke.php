<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Services/Finance/CompensacaoBilateralCalculator.php';
require_once __DIR__ . '/../src/Services/Finance/Fin2ReadOnlySource.php';
require_once __DIR__ . '/../src/Services/Finance/Fin2ApuracaoReadOnly.php';

$rows = \App\Services\Finance\Fin2ApuracaoReadOnly::porGuincho(getPDO());
echo json_encode([
    'ok' => true,
    'rows' => count($rows),
    'mutations' => false,
    'sample_keys' => $rows === [] ? [] : array_keys($rows[0]),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
