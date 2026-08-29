<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/AvaliacaoService.php';

final class AvaliacaoFlowTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['avaliacoes','pedidos','veiculos','guinchos','usuarios'] as $t) { try { $pdo->exec("DELETE FROM {$t}"); } catch (Throwable) {} }
        // "IGNORE" (MySQL) não existe no dialeto do SQLite usado pelo bootstrap de
        // testes (tests/bootstrap.php) — e como as linhas acabaram de ser apagadas
        // pelo DELETE FROM acima, não há risco de duplicidade que justifique o
        // IGNORE aqui mesmo. INSERT simples funciona nos dois bancos.
        $pdo->exec("INSERT INTO usuarios (id,nome,email,senha_hash,telefone,cpf,tipo,ativo) VALUES (1,'Cliente Teste','cliente.avaliacao@teste.com','hash','11988887777','12312312312','cliente',1)");
        $pdo->exec("INSERT INTO usuarios (id,nome,email,senha_hash,telefone,cpf,tipo,ativo) VALUES (2,'Guincho Teste','guincho.avaliacao@teste.com','hash','11988887778','32132132132','guincho',1)");
        $pdo->exec("INSERT INTO veiculos (id,usuario_id,placa,marca,modelo,ano,cor,tipo,ativo,criado_em) VALUES (1,1,'ABC1234','VW','Gol',2020,'Branco','carro',1,NOW())");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (1,2,1,1)");
        $pdo->exec("INSERT INTO pedidos (id, cliente_id, veiculo_id, guincho_id, status, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino, criado_em) VALUES (10,1,1,1,'concluido', -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino', NOW())");
    }

    public function testAvaliacaoServiceCriaEAplicaIdempotencia(): void
    {
        $id = AvaliacaoService::avaliar(10, 1, 1, 5, 'ok');
        $this->assertNotFalse($id);
        $dup = AvaliacaoService::avaliar(10, 1, 1, 4, 'dup');
        $this->assertFalse($dup);
    }
}
