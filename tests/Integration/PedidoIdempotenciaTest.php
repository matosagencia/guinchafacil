<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../src/Models/Pedido.php';
final class PedidoIdempotenciaTest extends TestCase {
    public function testMesmoTriageSessionIdNaoCriaSegundoPedido(): void {
        $dados = ['cliente_id'=>1,'veiculo_id'=>1,'tipo_problema'=>'pneu','descricao_problema'=>'teste','lat_origem'=>-23.55,'lng_origem'=>-46.63,'endereco_origem'=>'Origem','lat_destino'=>-23.56,'lng_destino'=>-46.65,'endereco_destino'=>'Destino','distancia_km'=>4.2,'custo_estimado'=>89.90,'triage_session_id'=>str_repeat('a',32)];
        $primeiro = Pedido::criar($dados);
        $segundo = Pedido::criar($dados);
        self::assertIsInt($primeiro);
        self::assertSame($primeiro,$segundo);
    }
}
