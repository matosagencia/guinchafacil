<?php
declare(strict_types=1);
// Executar somente por CLI/cron. Expira ofertas sem alterar pedidos existentes.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Services/EspecialistaDispatchService.php';
try {
    $count = EspecialistaDispatchService::expirarOfertas();
    fwrite(STDOUT, sprintf("especialista ofertas expiradas: %d\n", $count));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "erro ao expirar ofertas: {$e->getMessage()}\n");
    exit(1);
}
