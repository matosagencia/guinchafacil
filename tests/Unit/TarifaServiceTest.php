<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/TarifaService.php';
require_once __DIR__ . '/../../src/Models/Feriado.php';

/**
 * §A6 (auditoria 21/07): cobre os dois gaps reais encontrados —
 * (1) não havia adicional de feriado (tabela `feriados` nem existia);
 * (2) TarifaService::categoriaDeVeiculo() já lia `categoria_tarifa`, mas
 * sem a coluna no banco a categoria sempre caía no fallback do ENUM
 * `tipo`, tornando 'suv'/'eletrico' inalcançáveis na prática.
 */
final class TarifaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec("DELETE FROM feriados");
        $pdo->exec("DELETE FROM configuracoes");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_por_km', '3.50')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_fixa', '10.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_noturna_km', '5.50')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_noturna_fixa', '15.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('turno_noturno_inicio', '20:00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('turno_noturno_fim', '06:00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_feriado_km', '5.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_feriado_fixa', '12.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_suv_km', '4.20')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_suv_fixa', '12.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_caminhonete_km', '4.80')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_caminhonete_fixa', '14.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_eletrico_km', '3.50')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_eletrico_fixa', '10.00')");
        // §TESTE-TARIFACAO-01 (02/08/2026): moto deixou de ser normalizada
        // pra 'popular' e ganhou tarifa própria — ver normalizarCategoria().
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_moto_km', '2.80')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_moto_fixa', '7.00')");
    }

    // ─── Feriado isolado ────────────────────────────────────────────────

    public function testDiaComumNaoAplicaAdicionalDeFeriado(): void
    {
        $diaComum = new DateTimeImmutable('2026-03-10 10:00:00'); // terça qualquer, de dia
        $detalhe = TarifaService::calcularDetalhado(10.0, 'popular', false, $diaComum);

        $this->assertFalse($detalhe['is_feriado']);
        $this->assertSame(0.0, $detalhe['adicional_feriado_km']);
        $this->assertSame(0.0, $detalhe['adicional_feriado_fixo']);
    }

    public function testFeriadoRecorrenteAplicaAdicional(): void
    {
        Feriado::criar(['data' => '2026-01-01', 'nome' => 'Confraternização Universal', 'recorrente_anual' => true, 'ativo' => true]);

        // Mesmo dia/mês, ano diferente — recorrente_anual deve casar mesmo assim.
        $anoSeguinte = new DateTimeImmutable('2027-01-01 10:00:00');
        $detalhe = TarifaService::calcularDetalhado(10.0, 'popular', false, $anoSeguinte);

        $this->assertTrue($detalhe['is_feriado']);
        $this->assertSame(5.0, $detalhe['adicional_feriado_km']);
        $this->assertSame(12.0, $detalhe['adicional_feriado_fixo']);
        // tarifa_km_base (3.50) + adicional_feriado_km (5.00) = 8.50
        $this->assertSame(8.5, $detalhe['tarifa_km_aplicada']);
    }

    public function testFeriadoNaoRecorrenteSoValeNoAnoCadastrado(): void
    {
        Feriado::criar(['data' => '2026-06-19', 'nome' => 'Ponto facultativo local', 'recorrente_anual' => false, 'ativo' => true]);

        $mesmoAno = new DateTimeImmutable('2026-06-19 10:00:00');
        $outroAno = new DateTimeImmutable('2027-06-19 10:00:00');

        $this->assertTrue(TarifaService::calcularDetalhado(10.0, 'popular', false, $mesmoAno)['is_feriado']);
        $this->assertFalse(TarifaService::calcularDetalhado(10.0, 'popular', false, $outroAno)['is_feriado']);
    }

    public function testFeriadoInativoNaoAplicaAdicional(): void
    {
        $id = Feriado::criar(['data' => '2026-12-25', 'nome' => 'Natal', 'recorrente_anual' => true, 'ativo' => true]);
        Feriado::alternarAtivo($id); // desativa

        $natal = new DateTimeImmutable('2026-12-25 10:00:00');
        $this->assertFalse(TarifaService::calcularDetalhado(10.0, 'popular', false, $natal)['is_feriado']);
    }

    // ─── Feriado + noturno empilhados ───────────────────────────────────

    public function testFeriadoENoturnoEmpilham(): void
    {
        Feriado::criar(['data' => '2026-12-25', 'nome' => 'Natal', 'recorrente_anual' => true, 'ativo' => true]);

        $natalDeNoite = new DateTimeImmutable('2026-12-25 22:00:00'); // dentro do turno noturno (20h-06h)
        $detalhe = TarifaService::calcularDetalhado(10.0, 'popular', false, $natalDeNoite);

        $this->assertTrue($detalhe['is_feriado']);
        $this->assertTrue($detalhe['is_noturno']);
        // base 3.50 + noturno 5.50 + feriado 5.00 = 14.00 (os dois adicionais empilham, nenhum substitui o outro)
        $this->assertSame(14.0, $detalhe['tarifa_km_aplicada']);
    }

    // ─── Categoria de veículo ────────────────────────────────────────────

    public function testCategoriaSuvUsaTarifaPropria(): void
    {
        $diaComum = new DateTimeImmutable('2026-03-10 10:00:00');
        $detalhe = TarifaService::calcularDetalhado(10.0, 'suv', false, $diaComum);

        $this->assertSame('suv', $detalhe['categoria']);
        $this->assertSame(4.2, $detalhe['tarifa_km_base']);
        $this->assertSame(12.0, $detalhe['taxa_fixa_base']);
    }

    public function testCategoriaCaminhoneteUsaTarifaPropria(): void
    {
        $diaComum = new DateTimeImmutable('2026-03-10 10:00:00');
        $detalhe = TarifaService::calcularDetalhado(10.0, 'caminhonete', false, $diaComum);

        $this->assertSame('caminhonete', $detalhe['categoria']);
        $this->assertSame(4.8, $detalhe['tarifa_km_base']);
    }

    public function testCategoriaEletricoUsaTarifaPropria(): void
    {
        $diaComum = new DateTimeImmutable('2026-03-10 10:00:00');
        $detalhe = TarifaService::calcularDetalhado(10.0, 'eletrico', false, $diaComum);

        $this->assertSame('eletrico', $detalhe['categoria']);
        $this->assertSame(3.5, $detalhe['tarifa_km_base']);
    }

    public function testCategoriaMotoUsaTarifaPropria(): void
    {
        // §TESTE-TARIFACAO-01: achado real pelo teste de coerência — moto
        // era normalizada pra 'popular' e cobrava o mesmo valor de um carro.
        $diaComum = new DateTimeImmutable('2026-03-10 10:00:00');
        $detalhe = TarifaService::calcularDetalhado(10.0, 'moto', false, $diaComum);

        $this->assertSame('moto', $detalhe['categoria']);
        $this->assertSame(2.8, $detalhe['tarifa_km_base']);
        $this->assertSame(7.0, $detalhe['taxa_fixa_base']);
    }

    public function testCategoriaMotoNaoEhMaisNormalizadaParaPopular(): void
    {
        // Regressão explícita do bug corrigido: moto NÃO pode mais cair no
        // mesmo bucket de 'popular' (tarifa_por_km/taxa_fixa do setUp são
        // 3.50/10.00 — diferentes dos valores de moto acima).
        $diaComum = new DateTimeImmutable('2026-03-10 10:00:00');
        $popular = TarifaService::calcularDetalhado(10.0, 'popular', false, $diaComum);
        $moto = TarifaService::calcularDetalhado(10.0, 'moto', false, $diaComum);

        $this->assertNotSame($popular['tarifa_km_base'], $moto['tarifa_km_base']);
        $this->assertNotSame($popular['taxa_fixa_base'], $moto['taxa_fixa_base']);
    }

    public function testCategoriaDeVeiculoPrefereCategoriaTarifaExplicita(): void
    {
        // Mesmo com tipo='carro' (mapearia pra 'popular'), categoria_tarifa
        // explícita deve vencer — era exatamente o gap: a coluna existir e
        // ser lida, mas nunca chegar preenchida do banco.
        $veiculo = ['tipo' => 'carro', 'categoria_tarifa' => 'suv'];
        $this->assertSame('suv', TarifaService::categoriaDeVeiculo($veiculo));
    }

    public function testCategoriaDeVeiculoCaiNoFallbackDoTipoQuandoSemCategoriaTarifa(): void
    {
        $veiculo = ['tipo' => 'caminhao', 'categoria_tarifa' => null];
        $this->assertSame('caminhonete', TarifaService::categoriaDeVeiculo($veiculo));
    }
}
