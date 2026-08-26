<?php
// Instalador simples do GuinchaFácil
// Acesso: /guinchafacil/install/?key=SEU_TOKEN_AQUI

require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');

$sqlFile = __DIR__ . '/guinchafacil.sql';
$hasSql = file_exists($sqlFile);

?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Instalador - GuinchaFácil</title>
  <style>
    body{font-family:Arial, sans-serif; max-width:900px; margin:40px auto; padding:0 16px;}
    code,pre{background:#f5f5f5; padding:8px; display:block; overflow:auto;}
    .ok{color:green} .bad{color:#b00020}
    button{padding:10px 14px; font-size:14px; cursor:pointer;}
  </style>
</head>
<body>
  <h1>Instalador do Banco — GuinchaFácil</h1>

  <p>Status do arquivo SQL: 
    <?php if ($hasSql): ?>
      <b class="ok">OK</b> (<?= htmlspecialchars($sqlFile) ?>)
    <?php else: ?>
      <b class="bad">NÃO ENCONTRADO</b> — coloque <code>guinchafacil.sql</code> em <code>/guinchafacil/install/</code>
    <?php endif; ?>
  </p>

  <h2>Config detectada</h2>
  <pre><?php
  // Mostra só o mínimo (sem senha)
  echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : '(não definido)') . "\n";
  echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : '(não definido)') . "\n";
  echo "DB_USER: " . (defined('DB_USER') ? DB_USER : '(não definido)') . "\n";
  ?></pre>

  <?php if ($hasSql): ?>
    <form method="post" action="run.php?<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>">
      <p>
        <label>
          <input type="checkbox" name="drop_first" value="1">
          Apagar tabelas existentes (DROP) antes (use só se souber o que está fazendo)
        </label>
      </p>
      <button type="submit">Instalar / Reinstalar Banco</button>
    </form>
  <?php endif; ?>

  <p style="margin-top:30px">
    Depois de instalar, apague a pasta <code>/guinchafacil/install/</code> ou bloqueie geral.
  </p>
</body>
</html>