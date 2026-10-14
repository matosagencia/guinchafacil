<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);

$url = 'https://api.heigit.org/openrouteservice/v2/directions/driving-car';
$key = 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjdiMDQ2NDZkYmJmNDRkNDliMjIwOGVmNTFjZGFiYzhmIiwiaCI6Im11cm11cjY0In0=';  // <-- cola a chave aqui temporariamente

$body = json_encode([
    'coordinates' => [[-46.4372655, -23.1794793], [-47.0683, -22.8975]],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: ' . $key,
    ],
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP: $http\n";
echo "curl_error: " . ($err ?: '(vazio)') . "\n";
echo "Resposta (primeiros 400 chars):\n";
echo substr((string)$resp, 0, 400) . "\n";