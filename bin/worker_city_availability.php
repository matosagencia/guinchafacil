<?php

declare(strict_types=1);

$lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'guinchafacil-city-availability.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, json_encode(['updated' => false, 'skipped' => 'already_running'], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
}
register_shutdown_function(static function () use ($lockHandle): void {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Models/Guincho.php';

try {
    $counts = Guincho::recalcularDisponibilidadePorZonaCache();
    fwrite(STDOUT, json_encode(['updated' => true, 'zones' => count($counts)], JSON_UNESCAPED_UNICODE) . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, '[city_availability] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
