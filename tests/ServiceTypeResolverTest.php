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
    'FUEL_DELIVERY' => 16,
    'MECHANICAL_ASSISTANCE' => 14,
    'AUTOMOTIVE_LOCKSMITH' => 15,
];

$casos = [
    'pneu' => ['TIRE_CHANGE', 11],
    'eletrica' => ['ELECTRICAL_DIAGNOSIS', 12],
    'bateria' => ['JUMP_START', 13],
    'combustivel' => ['FUEL_DELIVERY', 16],
    'pane_seca' => ['FUEL_DELIVERY', 16],
    'mecanica' => ['MECHANICAL_ASSISTANCE', 14],
    'chaveiro' => ['AUTOMOTIVE_LOCKSMITH', 15],
];

foreach ($casos as $slug => [$codeEsperado, $idEsperado]) {
    verificar(ServiceTypeResolver::codePorSlug($slug) === $codeEsperado, "mapa:$slug");

}

verificar(ServiceTypeResolver::codePorSlug('desconhecido') === null, 'mapa:desconhecido');


echo "[DONE][ServiceTypeResolver] 8 verificacoes concluidas\n";
