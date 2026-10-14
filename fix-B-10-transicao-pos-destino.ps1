cd C:\xampp\htdocs\guinchafacil
$tmp = Join-Path (Get-Location) '_corrige_antigos.php'
$php = @'
<?php
require __DIR__ . '/config.php';
$pdo = getPDO();
$n = $pdo->exec("UPDATE pedidos
                 SET status = 'aguardando_guincho'
                 WHERE status = 'aguardando_pagamento'
                   AND forma_pagamento_escolhida IN ('pago_na_chegada','pago_agora','sem_cobranca')");
echo "corrigidos: {$n}\n";
$r = $pdo->query('SELECT id, status, forma_pagamento_escolhida FROM pedidos ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $p) {
    echo "#{$p['id']} | {$p['status']} | {$p['forma_pagamento_escolhida']}\n";
}
'@
[System.IO.File]::WriteAllText($tmp, $php, (New-Object System.Text.UTF8Encoding($false)))
& 'C:\xampp\php\php.exe' $tmp
Remove-Item $tmp -Force