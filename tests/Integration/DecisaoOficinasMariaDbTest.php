<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/DecisaoAtendimentoService.php';

final class DecisaoOficinasMariaDbTest extends TestCase
{
    private const LAT = -10.2234;
    private const LNG = -50.2234;
    private PDO $pdo;
    private int $sequence = 0;

    protected function setUp(): void
    {
        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requer MariaDB QA.');
        }
        $this->pdo = getPDO();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testSelecionaOficinaMaisProximaEExpandeParaOutraElegivel(): void
    {
        $maisProxima = $this->novaOficina(0.01, 30);
        $maisDistante = $this->novaOficina(0.06, 30);
        $service = new DecisaoAtendimentoService();

        $decisao = $service->decidirOficinas(self::LAT, self::LNG);

        $this->assertTrue($decisao['disponivel']);
        $this->assertSame($maisProxima, (int)$decisao['escolhido']['provider_id']);
        $this->assertCount(2, $decisao['candidatos']);

        $this->pdo->prepare('UPDATE provider_workshop_settings SET raio_resgate_direto_km = 1 WHERE provider_id = ?')
            ->execute([$maisProxima]);
        $decisaoDistante = $service->decidirOficinas(self::LAT, self::LNG);

        $this->assertTrue($decisaoDistante['disponivel']);
        $this->assertSame($maisDistante, (int)$decisaoDistante['escolhido']['provider_id']);
    }

    public function testRaioDaOficinaImpedeMatchForaDaCobertura(): void
    {
        $this->novaOficina(0.06, 1);

        $decisao = (new DecisaoAtendimentoService())->decidirOficinas(self::LAT, self::LNG);

        $this->assertFalse($decisao['disponivel']);
        $this->assertSame([], $decisao['candidatos']);
        $this->assertSame('SEM_OFICINA_ELEGIVEL', $decisao['motivo']);
    }

    public function testCatalogoAprovadoFiltraOficinaPorCodigoDoServico(): void
    {
        $comCapacidade = $this->novaOficina(0.01, 30);
        $semCapacidade = $this->novaOficina(0.011, 30);
        $service = $this->pdo->query("SELECT id FROM service_types
            WHERE active = 1 AND code = 'ELECTRICAL_DIAG' LIMIT 1")->fetchColumn();
        if ($service === false) {
            $this->pdo->exec("INSERT IGNORE INTO service_categories (id, name, active)
                VALUES (1, 'QA Dispatch Catalog', 1)");
            $this->pdo->exec("INSERT INTO service_types
                (code, name, category_id, attendance_mode, active)
                VALUES ('ELECTRICAL_DIAG', 'Diagnostico eletrico QA', 1, 'ON_SITE', 1)");
            $service = $this->pdo->lastInsertId();
        }

        $capability = $this->pdo->prepare("INSERT INTO provider_workshop_capabilities
            (provider_id, service_type_id, enabled, approval_status, created_at, updated_at)
            VALUES (?, ?, 1, 'APPROVED', NOW(), NOW())");
        $capability->execute([$comCapacidade, (int)$service]);

        $decisao = (new DecisaoAtendimentoService())->decidirOficinas(self::LAT, self::LNG, 'eletrica');

        $ids = array_map(static fn(array $row): int => (int)$row['provider_id'], $decisao['candidatos']);
        $this->assertSame([$comCapacidade], $ids);
        $this->assertNotContains($semCapacidade, $ids);
    }

    private function novaOficina(float $latOffset, float $raio): int
    {
        $this->sequence++;
        $nome = 'QA Oficina ' . $this->sequence . '-' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare("INSERT INTO providers
            (provider_type, legal_name, trade_name, approval_status, active, created_at, updated_at)
            VALUES ('WORKSHOP', ?, ?, 'APPROVED', 1, NOW(), NOW())");
        $stmt->execute([$nome . ' LTDA', $nome]);
        $providerId = (int)$this->pdo->lastInsertId();
        $settings = $this->pdo->prepare("INSERT INTO provider_workshop_settings
            (provider_id, taxa_indicacao_fixa, taxa_resgate_direto,
             permite_resgate_direto, recebe_veiculo_patio, faz_resgate_direto,
             regra_versao, status_parceria, raio_checkin_m,
             raio_resgate_direto_km, latitude, longitude, created_at, updated_at)
            VALUES (?, 30, 20, 1, 1, 1, 'qa', 'ATIVO', 150, ?,
                    ? + ?, ? + 0, NOW(), NOW())");
        $settings->execute([$providerId, $raio, self::LAT, $latOffset, self::LNG]);
        return $providerId;
    }
}
