<?php
declare(strict_types=1);
ob_start();

$raiz = realpath(__DIR__ . '/../..');
if ($raiz === false) { fwrite(STDERR, "Nao achei a raiz.\n"); exit(99); }

require_once $raiz . '/config.php';
require_once $raiz . '/src/Database.php';
require_once $raiz . '/src/Services/Financial/ChargePolicyService.php';
require_once $raiz . '/src/Services/Routing/RoutingService.php';
define('GF_ROUTING_QA_FIXTURE', true);

if (!function_exists('getPDO')) { fwrite(STDERR, "getPDO() ausente.\n"); exit(99); }

spl_autoload_register(function (string $classe) use ($raiz): void {
    $rel = str_replace('\\', '/', $classe);
    $t = [
        $raiz . '/src/' . preg_replace('#^App/#', '', $rel) . '.php',
        $raiz . '/src/' . $rel . '.php',
        $raiz . '/src/Services/'    . basename($rel) . '.php',
        $raiz . '/src/Models/'      . basename($rel) . '.php',
        $raiz . '/src/Controllers/' . basename($rel) . '.php',
        $raiz . '/src/DTO/'         . basename($rel) . '.php',
    ];
    foreach ($t as $p) { if (is_file($p)) { require_once $p; return; } }
});

$faltando = [];
foreach (['PedidoCoreService','PedidoPricingService','ChargePolicyService','PedidoTransitionService','PedidoCreateRequest'] as $c) {
    if (!class_exists($c)) $faltando[] = $c;
}
if ($faltando) { fwrite(STDERR, "FALTAM: " . implode(', ', $faltando) . "\n"); exit(99); }

try { $pdo = getPDO(); }
catch (Throwable $e) { fwrite(STDERR, "ERRO conectar: " . $e->getMessage() . "\n"); exit(97); }

$pedidoId = null; $exit = 0;
$pdo->beginTransaction();
try {
    $core = new PedidoCoreService($pdo, new PedidoPricingService(), new ChargePolicyService(), new PedidoTransitionService());
    $pedidoId = $core->criar(new PedidoCreateRequest([
        'cliente_id'      => 3,
        'veiculo_id'      => 2,
        'tipo_problema'   => 'reboque',
        'descricao'       => '[E2E_] A3.1 homologacao',
        'lat_origem'      => -22.895468,
        'lng_origem'      => -43.193492,
        'endereco_origem' => 'Rua do Propósito 36, Gamboa, Rio de Janeiro - RJ',
        'lat_destino'     => -22.897437,
        'lng_destino'     => -43.196741,
        'endereco_destino'=> 'Rua da Gamboa 247, Gamboa, Rio de Janeiro - RJ',
        'service_type_id' => 1,
        'actor_type'      => 'test',
        'actor_id'        => 0,
    ]));
    echo "=== A3.1 Homologacao ===\n";
    echo "pedido_id: {$pedidoId}\n";

    $s = $pdo->prepare("SELECT * FROM order_price_snapshots WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $s->execute([$pedidoId]);
    $snap = $s->fetch(PDO::FETCH_ASSOC);

    if (!$snap) { fwrite(STDERR, "FALHA: snapshot nao gravado\n"); $exit = 1; }
    else {
        $ctx = json_decode((string)($snap['calculation_context_json'] ?? '{}'), true) ?: [];
        $esp = ['taxa_saida','km_cobrado','tarifa_km','base_km_contagem','fator_veiculo','fator_periodo','deadhead_ref','fator_terreno','ganho_elevacao_m','component_elevacao','component_terreno','component_deadhead','pedagio','comissao_percentual','valor_total'];
        $falta = array_values(array_diff($esp, array_keys($ctx)));
        $total=(float)$snap['customer_total']; $com=(float)$snap['platform_fee']; $par=(float)$snap['provider_total'];
        printf("customer_total: R$ %.2f\n", $total);
        printf("platform_fee:   R$ %.2f\n", $com);
        printf("provider_total: R$ %.2f\n", $par);
        printf("hash:           %s\n", empty($snap['snapshot_hash']) ? 'AUSENTE' : 'ok');
        printf("campos 5.8:     %d/%d%s\n", count($esp)-count($falta), count($esp),
            empty($falta) ? ' OK' : ' FALTA: ' . implode(',', $falta));
        $pct=(float)($ctx['comissao_percentual']??0); $res=(float)($ctx['reserva_gateway']??0);
        printf("comissao:       %.4f\n", $pct);
        printf("reserva:        R$ %.2f\n", $res);
        $soma=round($par+$com+$res,2);
        printf("split:          %.2f + %.2f + %.2f = %.2f vs total %.2f\n", $par,$com,$res,$soma,$total);
        $prob=[];
        if($falta)$prob[]='5.8 incompleto';
        if(abs($pct-0.08)>0.0001)$prob[]='comissao!=8%';
        if(abs($soma-$total)>=0.01)$prob[]='split nao fecha';
        if(empty($snap['snapshot_hash']))$prob[]='hash ausente';
        if($prob){echo "\nRESULTADO: PARCIAL - ".implode('; ',$prob)."\n";$exit=2;}
        else{echo "\nRESULTADO: OK\n";}
    }
} catch (Throwable $e) {
    fwrite(STDERR, "EXCECAO: " . get_class($e) . ": " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getFile() . ":" . $e->getLine() . "\n");
    $exit = 3;
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); echo "\nRollback executado.\n"; }
}

if ($pedidoId !== null) {
    foreach (['pedidos'=>'id','order_price_snapshots'=>'order_id'] as $t=>$c) {
        $chk=getPDO()->prepare("SELECT COUNT(*) FROM `{$t}` WHERE `{$c}` = ?");
        $chk->execute([$pedidoId]);
        if((int)$chk->fetchColumn()>0){fwrite(STDERR,"RESIDUO em {$t}\n");$exit=$exit?:4;}
    }
    if($exit===0||$exit===2) echo "Sem residuos.\n";
}
exit($exit);
