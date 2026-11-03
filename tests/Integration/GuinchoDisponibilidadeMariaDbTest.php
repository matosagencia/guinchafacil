<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/GuinchoDisponibilidade.php';
require_once __DIR__ . '/../../src/Services/Finance/BloqueioFinanceiroService.php';

final class GuinchoDisponibilidadeMariaDbTest extends TestCase
{
    private const LAT = -10.1234;
    private const LNG = -50.1234;
    private PDO $pdo;
    private array $guinchoIds = [];
    private int $fixtureSequence = 0;

    protected function setUp(): void
    {
        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requer MariaDB QA.');
        }
        $this->pdo = getPDO();
        $this->pdo->beginTransaction();
        $this->setConfig('guincho_anel_min_candidatos', '3');
        $this->setConfig('guincho_aneis_km', '2,5,10,20,35');
        $this->setConfig('raio_maximo_km', '50');
        $this->setConfig('bloqueio_financeiro_ativo', '0');
        BloqueioFinanceiroService::limparCache();
    }

    protected function tearDown(): void
    {
        BloqueioFinanceiroService::limparCache();
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testExpandeAneisEEscolheSomenteQuandoAtingeOMinimo(): void
    {
        $this->novoGuincho(0.008, 0.0); // aproximadamente 0,9 km: anel 0
        $this->novoGuincho(0.031, 0.0); // aproximadamente 3,4 km: anel 1
        $this->novoGuincho(0.032, 0.0); // aproximadamente 3,5 km: anel 1
        $this->novoGuincho(0.072, 0.0); // aproximadamente 8,0 km: anel 2
        $this->novoGuincho(0.073, 0.0); // aproximadamente 8,1 km: anel 2
        $this->novoGuincho(0.074, 0.0); // aproximadamente 8,2 km: anel 2

        $decisao = GuinchoDisponibilidade::decidir(self::LAT, self::LNG);

        $this->assertTrue($decisao['disponivel']);
        $this->assertSame(2, $decisao['anel_escolhido']['idx']);
        $this->assertSame(3, $decisao['anel_escolhido']['elegiveis']);
        $this->assertEqualsWithDelta(7.5, $decisao['deadhead_ref'], 0.01);
    }

    public function testRaioIndividualExcluiGuinchoMesmoDentroDoTetoGlobal(): void
    {
        $this->novoGuincho(0.09, 0.0, 2); // cerca de 10 km, raio próprio de 2 km

        $decisao = GuinchoDisponibilidade::decidir(self::LAT, self::LNG);

        $this->assertFalse($decisao['disponivel']);
        $this->assertSame(0, $decisao['candidatos_elegiveis']);
        $this->assertSame('SEM_GUINCHO_ELEGIVEL', $decisao['motivo']);
    }

    public function testUmCandidatoMantemDisponibilidadeMesmoAbaixoDoMinimoDoAnel(): void
    {
        $id = $this->novoGuincho(0.008, 0.0);

        $decisao = GuinchoDisponibilidade::decidir(self::LAT, self::LNG);

        $this->assertTrue($decisao['disponivel']);
        $this->assertSame(1, $decisao['candidatos_elegiveis']);
        $this->assertSame($id, (int)$decisao['anel_escolhido']['candidatos'][0]['id']);
    }

    public function testFaturaBloqueadaRemoveParceiroDosAneis(): void
    {
        $id = $this->novoGuincho(0.008, 0.0);
        $this->setConfig('bloqueio_financeiro_ativo', '1');
        $stmt = $this->pdo->prepare("INSERT INTO faturas_parceiro
            (parceiro_tipo, parceiro_id, ciclo_inicio, ciclo_fim, total_faturado,
             total_repasse, saldo, status, vencimento_em, created_at, updated_at)
            VALUES ('guincho', ?, NOW(), NOW(), 100, 80, 20, 'bloqueada', NOW(), NOW(), NOW())");
        $stmt->execute([$id]);
        BloqueioFinanceiroService::limparCache();

        $decisao = GuinchoDisponibilidade::decidir(self::LAT, self::LNG);

        $this->assertFalse($decisao['disponivel']);
        $this->assertSame(0, $decisao['candidatos_elegiveis']);
    }

    public function testGuinchoOcupadoNaoPodeSerSelecionado(): void
    {
        $id = $this->novoGuincho(0.008, 0.0);
        $clienteId = $this->novoUsuario();
        $vehicle = $this->pdo->prepare("INSERT INTO veiculos
            (usuario_id, placa, marca, modelo, ano, cor, tipo, ativo)
            VALUES (?, 'QAA0001', 'QA', 'Teste', 2020, 'Branco', 'carro', 1)");
        $vehicle->execute([$clienteId]);
        $vehicleId = (int)$this->pdo->lastInsertId();
        $pedido = $this->pdo->prepare("INSERT INTO pedidos
            (cliente_id, veiculo_id, guincho_id, status, attendance_mode,
             lat_origem, lng_origem, criado_em, atualizado_em)
            VALUES (?, ?, ?, 'a_caminho', 'TOWING', -22.9068, -43.1729, NOW(), NOW())");
        $pedido->execute([$clienteId, $vehicleId, $id]);

        $decisao = GuinchoDisponibilidade::decidir(self::LAT, self::LNG);

        $this->assertFalse($decisao['disponivel']);
        $this->assertSame(0, $decisao['candidatos_elegiveis']);
    }

    private function novoGuincho(float $latOffset, float $lngOffset, int $raio = 50): int
    {
        $usuarioId = $this->novoUsuario();
        $stmt = $this->pdo->prepare("INSERT INTO guinchos
            (usuario_id, lat_atual, lng_atual, raio_cobertura_km, disponivel,
             aprovado, oferece_reboque, reboque_aprovado, reputacao, total_avaliacoes)
            VALUES (?, ? + ?, ? + ?, ?, 1, 1, 1, 1, 5.00, 0)");
        $stmt->execute([$usuarioId, self::LAT, $latOffset, self::LNG, $lngOffset, $raio]);
        $id = (int)$this->pdo->lastInsertId();
        $this->guinchoIds[] = $id;
        return $id;
    }

    private function novoUsuario(): int
    {
        $this->fixtureSequence++;
        $email = 'qa-guincho-' . bin2hex(random_bytes(6)) . '-' . $this->fixtureSequence . '@example.invalid';
        $stmt = $this->pdo->prepare("INSERT INTO usuarios (nome, email, tipo, perfil_status, ativo)
            VALUES (?, ?, 'guincho', 'COMPLETO', 1)");
        $stmt->execute(['QA Guincho ' . $this->fixtureSequence, $email]);
        return (int)$this->pdo->lastInsertId();
    }

    private function setConfig(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO configuracoes (chave, valor)
            VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->execute([$key, $value]);
    }
}
