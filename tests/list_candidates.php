<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/config.php';
if (!function_exists('getPDO')) require_once dirname(__DIR__) . '/src/Database.php';

$rows = getPDO()->query(
    "SELECT id, status, criado_em FROM pedidos
      WHERE status IN ('aguardando_pagamento','aguardando_guincho')
      ORDER BY id LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) { echo "Nenhum candidato.\n"; exit; }
foreach ($rows as $r) {
    printf("  #%-4d  %-22s  %s\n", $r['id'], $r['status'], $r['criado_em']);
}