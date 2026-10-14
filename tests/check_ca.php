<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);

$url = 'https://api.heigit.org/';
$bundles = [
    'storage/cacert.pem',
    'C:/xampp/apache/bin/curl-ca-bundle.crt',
    'C:/xampp/php/extras/ssl/cacert.pem',
];

foreach ($bundles as $ca) {
    if (!is_file($ca)) {
        echo str_pad($ca, 50) . " → NÃO EXISTE\n";
        continue;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CAINFO => $ca,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $r = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $status = $http === 200 ? 'OK' : 'FALHA';
    echo str_pad($ca, 50) . " → http=$http err=" . ($err ?: '(vazio)') . " [$status]\n";
}

echo "\n--- Teste sem CA explícito (usa default do sistema) ---\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$r = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "default → http=$http err=" . ($err ?: '(vazio)') . "\n";