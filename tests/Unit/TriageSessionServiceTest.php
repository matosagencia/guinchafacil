<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/TriageSessionService.php';

final class TriageSessionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        getPDO()->exec("INSERT INTO service_types (category_id, code, public_slug, name, attendance_mode, requires_destination, active) VALUES (1, 'QA_PNEU', 'pneu', 'QA Pneu', 'TOWING', 1, 1) ON DUPLICATE KEY UPDATE active = 1");
    }

    private function cotacaoValida(): array
    {
        return [
            'tipo_problema' => 'pneu',
            'lat_origem' => -23.5505,
            'lng_origem' => -46.6333,
            'endereco_origem' => 'Av. Paulista, SÃ£o Paulo - SP',
            'lat_destino' => -23.5614,
            'lng_destino' => -46.6559,
            'endereco_destino' => 'Rua da ConsolaÃ§Ã£o, SÃ£o Paulo - SP',
            'distancia_km' => 4.2,
            'custo_estimado' => 89.90,
        ];
    }

    public function testFinalizarPersisteIdEContextoNaMesmaSessao(): void
    {
        $triagem = TriageSessionService::finalizar($this->cotacaoValida());

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $triagem['id']);
        self::assertSame($triagem['id'], $_SESSION['triage_session_id']);
        self::assertSame('pneu', $_SESSION['triage_sessions'][$triagem['id']]['dados']['tipo_problema']);
    }

    public function testReconstruirRejeitaIdDeOutraSessao(): void
    {
        TriageSessionService::finalizar($this->cotacaoValida());
        self::assertNull(TriageSessionService::reconstruir(str_repeat('a', 32)));
    }

    public function testReconstruirRecuperaDadosDaCotacao(): void
    {
        $triagem = TriageSessionService::finalizar($this->cotacaoValida());
        $restaurada = TriageSessionService::reconstruir($triagem['id']);

        self::assertNotNull($restaurada);
        self::assertSame(89.90, $restaurada['dados']['custo_estimado']);
        self::assertSame($triagem['id'], $_SESSION['checkout_triagem']['id']);
    }

    public function testCotacaoExpiradaNaoPodeSerReconstruida(): void
    {
        $triagem = TriageSessionService::finalizar($this->cotacaoValida());
        $_SESSION['triage_sessions'][$triagem['id']]['expira_em'] = time() - 1;

        self::assertNull(TriageSessionService::reconstruir($triagem['id']));
        self::assertArrayNotHasKey('triage_session_id', $_SESSION);
    }

    public function testServicoOnSitePodeFinalizarSemDestino(): void
    {
        getPDO()->exec("INSERT INTO service_types (category_id, code, public_slug, name, attendance_mode, requires_destination, active) VALUES (1, 'QA_ELETRICA', 'eletrica', 'QA Eletrica', 'ON_SITE', 0, 1) ON DUPLICATE KEY UPDATE active = 1");
        $dados = $this->cotacaoValida();
        $dados['tipo_problema'] = 'eletrica';
        $dados['lat_destino'] = null;
        $dados['lng_destino'] = null;
        $dados['endereco_destino'] = '';
        $triagem = TriageSessionService::finalizar($dados);
        self::assertSame('eletrica', $triagem['dados']['tipo_problema']);
        self::assertNull($triagem['dados']['lat_destino']);
    }
}
