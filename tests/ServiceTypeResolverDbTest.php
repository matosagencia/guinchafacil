<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Services/Catalog/ServiceTypeResolver.php';
use App\Services\Catalog\ServiceTypeResolver;
try {
    $idEletrica = ServiceTypeResolver::porSlug('eletrica');
    if ($idEletrica === null || $idEletrica <= 0) { throw new RuntimeException('servico eletrica ativo nao encontrado'); }
    echo "[PASS][ServiceTypeResolverDbTest][eletrica] id=$idEletrica\n";
    if (ServiceTypeResolver::porSlug('inexistente') !== null) { throw new RuntimeException('slug desconhecido resolveu'); }
    echo "[PASS][ServiceTypeResolverDbTest][inexistente] null\n";
    echo "[DONE][ServiceTypeResolverDbTest] 2 verificacoes concluidas\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL][ServiceTypeResolverDbTest] ' . $e->getMessage() . "\n");
    exit(1);
}