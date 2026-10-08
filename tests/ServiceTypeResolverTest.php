<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Services/Catalog/ServiceTypeResolver.php';

use App\Services\Catalog\ServiceTypeResolver;

function verificar(bool $condicao, string $fase): void
{
    if (!$condicao) {
        fwrite(STDERR, "[FAIL][ServiceTypeResolver][$fase] verificacao falhou\n");
        exit(1);
    }

    echo "[PASS][ServiceTypeResolver][$fase]\n";
}

$idsPorCode = [
    'TIRE_CHANGE' => 11,
    'ELECTRICAL_DIAGNOSIS' => 12,
    'JUMP_START' => 13,
    'MECHANICAL_ASSISTANCE' => 14,
    'AUTOMOTIVE_LOCKSMITH' => 15,
];

ServiceTypeResolver::definirLookupPorCodigo(
    static fn (string $code): ?int => $idsPorCode[$code] ?? null
);

$casos = [
    'pneu' => ['TIRE_CHANGE', 11],
    'eletrica' => ['ELECTRICAL_DIAGNOSIS', 12],
    'bateria' => ['JUMP_START', 13],
    'mecanica' => ['MECHANICAL_ASSISTANCE', 14],
    'chaveiro' => ['AUTOMOTIVE_LOCKSMITH', 15],
];

foreach ($casos as $slug => [$codeEsperado, $idEsperado]) {
    verificar(ServiceTypeResolver::codePorSlug($slug) === $codeEsperado, "mapa:$slug");
    verificar(ServiceTypeResolver::porSlug($slug) === $idEsperado, "lookup:$slug");
}

verificar(ServiceTypeResolver::codePorSlug('desconhecido') === null, 'mapa:desconhecido');
verificar(ServiceTypeResolver::porSlug('desconhecido') === null, 'lookup:desconhecido-fail-closed');

echo "[DONE][ServiceTypeResolver] 12 verificacoes concluidas\n";
