<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Services/Finance/CompensacaoBilateralCalculator.php';
require_once __DIR__ . '/../src/Services/Finance/Fin2ReadOnlySource.php';
require_once __DIR__ . '/../src/Services/Finance/Fin2ApuracaoReadOnly.php';

$password = getenv('GF_FIN2_DB_PASSWORD');
if (!is_string($password) || $password === '') {
    throw new RuntimeException('GF_FIN2_DB_PASSWORD ausente');
}
$db = new PDO(
    'mysql:host=localhost;dbname=guinchafacil_dev;charset=utf8mb4',
    'gf_fin2_app',
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$rows = \App\Services\Finance\Fin2ApuracaoReadOnly::porGuincho($db);
echo json_encode(['ok' => true, 'rows' => count($rows), 'mutations' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
