<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// L1.10: este teste não pode depender de outro arquivo de teste (ex.:
// SessionExpirationTest.php) ter sido carregado antes na mesma execução do
// PHPUnit para expor getPDO()/Configuracao — precisa ser autocontido.
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/src/Models/Configuracao.php';
require_once __DIR__ . '/../../src/Services/HealthService.php';

final class HealthServiceTest extends TestCase
{
    /**
     * §19 da constituição mestra exige 18 domínios mínimos cobertos por
     * HealthService::runAll() (incluindo cliente/guincheiro/pedido,
     * adicionados para fechar o gap do bloqueador P0 "Admin Health
     * navegável de verdade"). Antes este teste só checava 8, depois 16;
     * ficou incompleto e não pegaria uma regressão que removesse um dos
     * outros checks (carteira, saques, env, simulador, notificacoes,
     * schema, gateway, retencao, cliente, guincheiro, pedido).
     */
    public function testRunAllRetornaChecksMinimos(): void
    {
        $checks = HealthService::runAll();
        $dominiosMinimos = [
            'db', 'schema', 'auth', 'csp', 'webhook', 'pix', 'gateway',
            'geocoding', 'cliente', 'guincheiro', 'pedido', 'chat', 'cron',
            'retencao', 'carteira', 'saques', 'env', 'simulador', 'notificacoes',
        ];
        $this->assertCount(
            count($dominiosMinimos),
            $checks,
            'HealthService::runAll() deve retornar exatamente os 19 domínios mínimos, nem a mais nem a menos.'
        );
        foreach ($dominiosMinimos as $key) {
            $this->assertArrayHasKey($key, $checks, "Domínio de health ausente: {$key}");
            $check = $checks[$key];
            foreach (['ok', 'label', 'status', 'info', 'nivel'] as $campo) {
                $this->assertArrayHasKey($campo, $check, "Check '{$key}' não tem o campo obrigatório '{$campo}'.");
            }
            $this->assertIsBool($check['ok'], "Check '{$key}'.ok deve ser bool.");
            $this->assertContains(
                $check['nivel'],
                ['ok', 'aviso', 'erro'],
                "Check '{$key}'.nivel deve ser 'ok', 'aviso' ou 'erro', recebeu '{$check['nivel']}'."
            );
            $this->assertNotSame('', trim((string)$check['label']), "Check '{$key}'.label não pode ser vazio.");
        }
    }

    /**
     * Domínios estruturais que não dependem de credenciais externas
     * (gateway/pix/geocoding/notificacoes podem estar em 'aviso' num
     * ambiente sandbox sem chaves configuradas) precisam estar 'ok' num
     * ambiente de desenvolvimento íntegro, senão o gate de produção não
     * teria valor nenhum.
     */
    public function testDominiosEstruturaisEstaoSaudaveis(): void
    {
        $checks = HealthService::runAll();
        foreach (['db', 'schema', 'auth', 'csp'] as $key) {
            $this->assertSame(
                'ok',
                $checks[$key]['nivel'],
                "Domínio estrutural '{$key}' deveria estar 'ok' num ambiente íntegro. Info: " . ($checks[$key]['info'] ?? '')
            );
        }
    }

    public function testProductionChecklistRetornaItensComEstruturaValida(): void
    {
        $items = HealthService::productionChecklist();
        $this->assertNotEmpty($items, 'productionChecklist() não deve retornar vazio.');
        foreach ($items as $item) {
            foreach (['code', 'label', 'category', 'status', 'nivel', 'detail', 'action'] as $campo) {
                $this->assertArrayHasKey($campo, $item, "Item do checklist sem o campo '{$campo}'.");
            }
            $this->assertContains($item['nivel'], ['ok', 'aviso', 'erro']);
        }
    }
}
