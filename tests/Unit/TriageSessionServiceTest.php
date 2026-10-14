<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/TriageSessionService.php';

final class TriageSessionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function cotacaoValida(): array
    {
        return [
            'tipo_problema' => 'pneu',
            'lat_origem' => -23.5505,
            'lng_origem' => -46.6333,
            'endereco_origem' => 'Av. Paulista, São Paulo - SP',
            'lat_destino' => -23.5614,
            'lng_destino' => -46.6559,
            'endereco_destino' => 'Rua da Consolação, São Paulo - SP',
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
}
