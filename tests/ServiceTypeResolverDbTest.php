<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Services/Catalog/ServiceTypeResolver.php';

use App\Services\Catalog\ServiceTypeResolver;

ServiceTypeResolver::definirLookupPorCodigo(
    static function (string $code): ?int {
        $stmt = \getPDO()->prepare(
            'SELECT id FROM service_types WHERE code = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
);

try {
    $idEletrica = ServiceTypeResolver::porSlug('eletrica');
    if ($idEletrica === null || $idEletrica <= 0) {
        throw new RuntimeException('ELECTRICAL_DIAGNOSIS ativo nao foi encontrado.');
    }
    echo "[PASS][ServiceTypeResolverDbTest][eletrica] id=$idEletrica\n";

    $idInexistente = ServiceTypeResolver::porSlug('inexistente');
    if ($idInexistente !== null) {
        throw new RuntimeException('Slug desconhecido devolveu id=' . $idInexistente . '.');
    }
    echo "[PASS][ServiceTypeResolverDbTest][inexistente] null\n";
    echo "[DONE][ServiceTypeResolverDbTest] 2 verificacoes concluidas\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL][ServiceTypeResolverDbTest] ' . $e->getMessage() . "\n");
    exit(1);
}
