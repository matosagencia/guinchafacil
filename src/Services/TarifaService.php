<?php

declare(strict_types=1);

require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Models/Feriado.php';

class TarifaService
{
    /**
     * §PRECO-POR-CIDADE-01: mesma convenção de chave composta já usada em
     * AdminPlanejamentoController::chaveCidade() — `{chave}__cidade_{id}`.
     * Duplicado aqui de propósito (é uma função de 1 linha) pra não criar
     * acoplamento entre TarifaService (motor de cobrança real) e um
     * controller de admin; se algum dia vale a pena unificar, extrair pra
     * um helper compartilhado tipo Configuracao::chaveCidade().
     */
    private static function chaveCidade(string $chave, ?int $cidadeId): string
    {
        return $cidadeId !== null && $cidadeId > 0 ? $chave . '__cidade_' . $cidadeId : $chave;
    }

    /**
     * Lê uma chave de configuração com fallback cidade -> global: se
     * $cidadeId for informado e existir uma chave específica
     * ("{chave}__cidade_{id}") com valor não vazio, usa ela; senão cai na
     * chave global de sempre. Isso preserva 100% do comportamento atual
     * enquanto nenhuma tarifa por cidade for cadastrada no admin.
     */
    private static function cfgPorCidade(array $cfg, string $chave, ?int $cidadeId): ?string
    {
        if ($cidadeId !== null && $cidadeId > 0) {
            $chaveCidade = self::chaveCidade($chave, $cidadeId);
            if (isset($cfg[$chaveCidade]) && $cfg[$chaveCidade] !== '') {
                return (string)$cfg[$chaveCidade];
            }
        }
        return isset($cfg[$chave]) ? (string)$cfg[$chave] : null;
    }

    public static function calcular(float $distanciaKm, ?string $categoriaVeiculo = null, bool $prioridade = false, ?DateTimeInterface $dataHora = null, ?int $cidadeId = null): float
    {
        $detalhe = self::calcularDetalhado($distanciaKm, $categoriaVeiculo, $prioridade, $dataHora, $cidadeId);
        return (float)$detalhe['valor'];
    }

    public static function calcularDetalhado(float $distanciaKm, ?string $categoriaVeiculo = null, bool $prioridade = false, ?DateTimeInterface $dataHora = null, ?int $cidadeId = null): array
    {
        $cfg = Configuracao::getAll();
        $dataHora = $dataHora ?? new DateTimeImmutable();
        $distanciaKm = max(0.0, round($distanciaKm, 2));

        $categoria = self::normalizarCategoria($categoriaVeiculo);
        $isNoturno = self::isHorarioNoturno($cfg, $dataHora);
        $isFeriado = self::isFeriado($dataHora);

        $tarifaKmBase = self::resolverTarifaPorKm($cfg, $categoria, $cidadeId);
        $taxaFixaBase = self::resolverTaxaFixa($cfg, $categoria, $cidadeId);
        $adicionalNoturnoKm = $isNoturno ? (float)(self::cfgPorCidade($cfg, 'tarifa_noturna_km', $cidadeId) ?? 0) : 0.0;
        $adicionalNoturnoFixo = $isNoturno ? (float)(self::cfgPorCidade($cfg, 'tarifa_noturna_fixa', $cidadeId) ?? 0) : 0.0;
        // §A6 — adicional de feriado EMPILHA com o noturno (não substitui),
        // mesmo padrão já usado pelo adicional noturno.
        $adicionalFeriadoKm = $isFeriado ? (float)(self::cfgPorCidade($cfg, 'tarifa_feriado_km', $cidadeId) ?? 0) : 0.0;
        $adicionalFeriadoFixo = $isFeriado ? (float)(self::cfgPorCidade($cfg, 'tarifa_feriado_fixa', $cidadeId) ?? 0) : 0.0;
        $taxaPrioridadeValor = self::cfgPorCidade($cfg, 'taxa_prioridade_valor', $cidadeId) ?? self::cfgPorCidade($cfg, 'taxa_prioridade', $cidadeId);
        $taxaPrioridade = $prioridade ? (float)($taxaPrioridadeValor ?? 0) : 0.0;

        $tarifaKmAplicada = $tarifaKmBase + $adicionalNoturnoKm + $adicionalFeriadoKm;
        $taxaFixaAplicada = $taxaFixaBase + $adicionalNoturnoFixo + $adicionalFeriadoFixo;
        $subtotal = GeoService::calcularCusto($distanciaKm, $tarifaKmAplicada, $taxaFixaAplicada);
        $valor = round($subtotal + $taxaPrioridade, 2);

        return [
            'valor' => $valor,
            'distancia_km' => $distanciaKm,
            'categoria' => $categoria,
            'prioridade' => $prioridade,
            'is_noturno' => $isNoturno,
            'is_feriado' => $isFeriado,
            'cidade_id' => $cidadeId,
            'tarifa_km_base' => round($tarifaKmBase, 2),
            'taxa_fixa_base' => round($taxaFixaBase, 2),
            'adicional_noturno_km' => round($adicionalNoturnoKm, 2),
            'adicional_noturno_fixo' => round($adicionalNoturnoFixo, 2),
            'adicional_feriado_km' => round($adicionalFeriadoKm, 2),
            'adicional_feriado_fixo' => round($adicionalFeriadoFixo, 2),
            'tarifa_km_aplicada' => round($tarifaKmAplicada, 2),
            'taxa_fixa_aplicada' => round($taxaFixaAplicada, 2),
            'taxa_prioridade' => round($taxaPrioridade, 2),
        ];
    }

    public static function categoriaDeVeiculo(array $veiculo): string
    {
        $categoriaExplicita = isset($veiculo['categoria_tarifa']) ? (string)$veiculo['categoria_tarifa'] : '';
        if ($categoriaExplicita !== '') {
            return self::normalizarCategoria($categoriaExplicita);
        }

        return self::normalizarCategoria((string)($veiculo['tipo'] ?? 'popular'));
    }

    private static function normalizarCategoria(?string $categoria): string
    {
        $categoria = strtolower(trim((string)$categoria));
        return match ($categoria) {
            'popular', 'carro' => 'popular',
            // §TESTE-TARIFACAO-01 (02/08/2026): moto tinha categoria própria
            // desmontada — normalizava pra 'popular' e cobrava o mesmo valor
            // de um carro. Achado pelo teste de coerência (tools/teste_
            // tarifacao_niteroi.php), corrigido com pesquisa de mercado real
            // (ver tools/instalar_tarifa_moto_mercado.php).
            'moto' => 'moto',
            'suv', 'utilitario_esportivo' => 'suv',
            'eletrico', 'elétrico' => 'eletrico',
            'caminhonete', 'van', 'caminhao', 'caminhão', 'pickup', 'utilitario' => 'caminhonete',
            default => 'popular',
        };
    }

    private static function isHorarioNoturno(array $cfg, DateTimeInterface $dataHora): bool
    {
        $hora = (int)$dataHora->format('H');
        $inicioNoturno = (int)explode(':', (string)($cfg['turno_noturno_inicio'] ?? '20:00'))[0];
        $fimNoturno = (int)explode(':', (string)($cfg['turno_noturno_fim'] ?? '06:00'))[0];

        if ($inicioNoturno === $fimNoturno) {
            return false;
        }

        if ($inicioNoturno < $fimNoturno) {
            return $hora >= $inicioNoturno && $hora < $fimNoturno;
        }

        return $hora >= $inicioNoturno || $hora < $fimNoturno;
    }

    /**
     * §A6 — compara em PHP (não em SQL) pra funcionar igual em MySQL e no
     * SQLite dos testes, sem depender de DATE_FORMAT/strftime específico de
     * cada dialeto. A tabela é pequena (dezenas de linhas), então buscar
     * tudo e filtrar em memória não é um problema de performance real.
     */
    private static function isFeriado(DateTimeInterface $dataHora): bool
    {
        $dataAlvo = $dataHora->format('Y-m-d');
        $mesDiaAlvo = $dataHora->format('m-d');

        foreach (Feriado::listarAtivos() as $feriado) {
            $dataFeriado = substr((string)$feriado['data'], 0, 10);
            if (!empty($feriado['recorrente_anual'])) {
                if (substr($dataFeriado, 5, 5) === $mesDiaAlvo) {
                    return true;
                }
            } elseif ($dataFeriado === $dataAlvo) {
                return true;
            }
        }

        return false;
    }

    private static function resolverTarifaPorKm(array $cfg, string $categoria, ?int $cidadeId = null): float
    {
        $key = 'tarifa_' . $categoria . '_km';
        $valorCategoria = self::cfgPorCidade($cfg, $key, $cidadeId);
        if ($valorCategoria !== null && $valorCategoria !== '') {
            return (float)$valorCategoria;
        }
        $valorPadrao = self::cfgPorCidade($cfg, 'tarifa_por_km', $cidadeId);
        return $valorPadrao !== null && $valorPadrao !== '' ? (float)$valorPadrao : 5.00;
    }

    private static function resolverTaxaFixa(array $cfg, string $categoria, ?int $cidadeId = null): float
    {
        $key = 'tarifa_' . $categoria . '_fixa';
        $valorCategoria = self::cfgPorCidade($cfg, $key, $cidadeId);
        if ($valorCategoria !== null && $valorCategoria !== '') {
            return (float)$valorCategoria;
        }
        $valorPadrao = self::cfgPorCidade($cfg, 'taxa_fixa', $cidadeId);
        return $valorPadrao !== null && $valorPadrao !== '' ? (float)$valorPadrao : 10.00;
    }
}
