<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$path = $argv[1] ?? '';
$needle = $argv[2] ?? '';
$radius = isset($argv[3]) && is_numeric($argv[3]) ? max(0, (int)$argv[3]) : 80;

if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Arquivo não encontrado.\n");
    exit(1);
}

$lines = file($path);
if ($lines === false) {
    fwrite(STDERR, "Falha ao ler arquivo.\n");
    exit(1);
}

if ($needle === '') {
    foreach ($lines as $index => $line) {
        echo str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT) . ': ' . $line;
    }
    exit(0);
}

foreach ($lines as $index => $line) {
    if (strpos($line, $needle) === false) {
        continue;
    }

    $start = max(0, $index - $radius);
    $end = min(count($lines) - 1, $index + $radius);
    for ($cursor = $start; $cursor <= $end; $cursor++) {
        echo str_pad((string)($cursor + 1), 4, '0', STR_PAD_LEFT) . ': ' . $lines[$cursor];
    }
    exit(0);
}

fwrite(STDERR, "Trecho não encontrado.\n");
exit(1);
