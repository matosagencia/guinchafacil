<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$_SERVER['REQUEST_URI'] = '/cli';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'cli';
$_SERVER['SCRIPT_NAME'] = '/cli.php';
if (!defined('REQUEST_ID')) define('REQUEST_ID', 'cli_tx');
if (!defined('CSP_SCRIPT_NONCE')) define('CSP_SCRIPT_NONCE', 'cli');

$raiz = dirname(__DIR__);
require_once $raiz . '/config.php';
if (!function_exists('getPDO')) require_once $raiz . '/src/Database.php';

$a = getPDO();
$b = getPDO();
echo "getPDO() mesma instancia? " . ($a === $b ? 'SIM' : 'NAO') . "\n";
echo "obj_id a=" . spl_object_id($a) . "  b=" . spl_object_id($b) . "\n\n";

echo "antes begin:      " . ($a->inTransaction() ? 'true' : 'false') . "\n";
echo "autocommit antes: " . var_export($a->getAttribute(PDO::ATTR_AUTOCOMMIT), true) . "\n";

$a->beginTransaction();

echo "depois begin:     " . ($a->inTransaction() ? 'true' : 'false') . "\n";
echo "autocommit depois: " . var_export($a->getAttribute(PDO::ATTR_AUTOCOMMIT), true) . "\n";

// Consome o cursor completamente — usar fetchAll(), nao so exec()
$a->query("SELECT 1")->fetchAll(PDO::FETCH_COLUMN);

echo "depois select:    " . ($a->inTransaction() ? 'true' : 'false') . "\n";

$a->commit();

echo "depois commit:    " . ($a->inTransaction() ? 'true' : 'false') . "\n";

echo "\nOK — transacao funciona sem o fix do Database.php.\n";