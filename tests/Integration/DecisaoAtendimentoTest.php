<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/DecisaoAtendimentoService.php';
require_once __DIR__ . '/../../src/Models/PedidoDecisaoSocorro.php';

final class DecisaoAtendimentoTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pedido_decisoes_socorro', 'provider_workshop_settings', 'providers'] as $table) {
            try { $pdo->exec("DROP TABLE IF EXISTS {$table}"); } catch (Throwable) {}
        }
        try { $pdo->exec('DELETE FROM pedidos'); } catch (Throwable) {}
        try { $pdo->exec('DELETE FROM configuracoes'); } catch (Throwable) {}

        foreach ([
            'orcamento_informado_at TEXT',
            'cliente_decisao_orcamento TEXT',
            'cliente_decisao_reboque TEXT',
            'decisoes_socorro_at TEXT',
        ] as $column) {
            try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN {$column}"); } catch (Throwable) {}
        }

        $pdo->exec("CREATE TABLE providers (
            id INTEGER PRIMARY KEY,
            active INTEGER NOT NULL DEFAULT 1,
            approval_status TEXT NOT NULL DEFAULT 'APPROVED'
        )");
        $pdo->exec("CREATE TABLE provider_workshop_settings (
            provider_id INTEGER PRIMARY KEY,
            status_parceria TEXT NOT NULL DEFAULT 'ATIVO',
            faz_resgate_direto INTEGER NOT NULL DEFAULT 1,
            latitude REAL,
            longitude REAL,
            raio_resgate_direto_km REAL
        )");
        $pdo->exec("CREATE TABLE pedido_decisoes_socorro (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pedido_id INTEGER NOT NULL,
            provider_id INTEGER,
            actor_type TEXT NOT NULL,
            actor_id INTEGER,
            decision_code TEXT NOT NULL,
            metadata_json TEXT,
            idempotency_key TEXT NOT NULL UNIQUE,
            created_at TEXT
        )");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('desconto_saida_oficina_percentual', '0.21')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('custo_saida_profissional_padrao', '120.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_fixa', '80.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_por_km', '20.00')");
        $pdo->exec("INSERT INTO providers (id, active, approval_status) VALUES (10, 1, 'APPROVED')");
        $pdo->exec("INSERT INTO provider_workshop_settings (provider_id, status_parceria, faz_resgate_direto, latitude, longitude, raio_resgate_direto_km)
                    VALUES (10, 'ATIVO', 1, -22.9068, -43.1729, 30)");
        $pdo->exec("INSERT INTO pedidos (id, status, cliente_id, custo_estimado, lat_origem, lng_origem, distancia_km, attendance_mode)
                    VALUES (900, 'diagnostico_concluido', 1, 180.00, -22.9068, -43.1729, 5.0, 'ON_SITE')");
    }

    public function testRecomendaAssistenciaParaBateriaEPneu(): void
    {
        $svc = new DecisaoAtendimentoService();

        $bateria = $svc->avaliar(0, 'bateria', -22.9068, -43.1729, ['custo_total' => 180.00]);
        $pneu = $svc->avaliar(0, 'pneu', -22.9068, -43.1729, ['custo_total' => 180.00]);

        $this->assertSame('assistencia', $bateria['recomendacao']);
        $this->assertSame('assistencia', $pneu['recomendacao']);
        $this->assertStringContainsString('21%', $bateria['justificativa']);
    }

    public function testRecomendaReboqueParaColisao(): void
    {
        $decisao = (new DecisaoAtendimentoService())->avaliar(0, 'colisao', -22.9068, -43.1729, ['custo_total' => 180.00]);

        $this->assertSame('reboque', $decisao['recomendacao']);
    }

    public function testAplicaDescontoConfiguradoAoConverter(): void
    {
        $calc = (new DecisaoAtendimentoService())->calcularValorComDesconto(180.00);

        $this->assertEqualsWithDelta(142.20, $calc['valor_final'], 0.01);
        $this->assertEqualsWithDelta(37.80, $calc['desconto'], 0.01);

        getPDO()->exec("UPDATE configuracoes SET valor = '0.15' WHERE chave = 'desconto_saida_oficina_percentual'");
        $calc15 = (new DecisaoAtendimentoService())->calcularValorComDesconto(180.00);
        $this->assertEqualsWithDelta(153.00, $calc15['valor_final'], 0.01);
    }

    public function testRegistroDeDecisaoEhIdempotente(): void
    {
        PedidoDecisaoSocorro::registrar(900, PedidoDecisaoSocorro::REBOQUE_SOLICITADO, 'cliente', 1);
        PedidoDecisaoSocorro::registrar(900, PedidoDecisaoSocorro::REBOQUE_SOLICITADO, 'cliente', 1);

        $count = (int)getPDO()->query("SELECT COUNT(*) FROM pedido_decisoes_socorro WHERE pedido_id = 900 AND decision_code = 'REBOQUE_SOLICITADO'")->fetchColumn();
        $pedido = getPDO()->query('SELECT cliente_decisao_reboque FROM pedidos WHERE id = 900')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(1, $count);
        $this->assertSame('SIM', $pedido['cliente_decisao_reboque']);
    }
}
