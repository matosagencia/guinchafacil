<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);

$urls = [
    'google'   => 'https://www.google.com/',
    'github'   => 'https://github.com/',
    'heigit'   => 'https://api.heigit.org/',
];

foreach ($urls as $nome => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $r = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    echo str_pad($nome, 10) . " http=$http err=" . ($err ?: '(vazio)') . "\n";
}

echo "\n--- Diagnostic: certificado do heigit ---\n";
$ch = curl_init('https://api.heigit.org/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CERTINFO => true,
    CURLOPT_SSL_VERIFYPEER => false,  // só para ler o cert
]);
curl_exec($ch);
$info = curl_getinfo($ch);
if (!empty($info['certinfo'][0])) {
    $cert = $info['certinfo'][0];
    echo "Emissor: " . ($cert['Issuer'] ?? '?') . "\n";
    echo "Assunto: " . ($cert['Subject'] ?? '?') . "\n";
}
curl_close($ch);