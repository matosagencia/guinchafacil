<?php
require_once 'config.php';
require_once 'src/Database.php';
$stmt = getPDO()->prepare("SELECT * FROM order_price_snapshots WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([3]);
var_dump($stmt->fetch(PDO::FETCH_ASSOC));
