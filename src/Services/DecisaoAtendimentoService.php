<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/Logger.php';

final class DecisaoAtendimentoService
{
    private const TIPOS_LOCAL = ['bateria', 'pneu', 'pane_seca'];
    private const TIPOS_REBOQUE = ['pane_mecanica', 'colisao', 'reboque'];

    public function avaliar(int $pedidoId, string $tipoProblema, float $lat, float $lng, array $opcoes = []): array
    {
        $tipo = self::normalizarTipo($tipoProblema);
        $categoria = (string)($opcoes['categoria'] ?? 'popular');
        $veiculoPodeMover = (bool)($opcoes['veiculo_pode_mover'] ?? true);

        $oficinas = $this->oficinasNoRaio($lat, $lng);

        if (count($oficinas) === 0) {
            return $this->somenteReboque($pedidoId, $tipo, 5.0,
                'Nao encontramos oficinas no raio de atendimento. Reboque e a unica opcao.');
        }

        if ($tipo === 'colisao' && !$veiculoPodeMover) {
            return $this->somenteReboque($pedidoId, $tipo, (float)$oficinas[0]['distancia_km'],
                'O veiculo nao pode se mover com seguranca. Reboque e obrigatorio.');
        }

        $distanciaOficina = (float)$oficinas[0]['distancia_km'];
        $custoAssistencia = $this->calcularAssistencia();
        $custoReboque     = $this->calcularReboque($distanciaOficina);
        $recomendacao     = $custoReboque > $custoAssistencia ? 'assistencia' : 'reboque';
        $justificativa    = $recomendacao === 'assistencia'
            ? 'A assistencia no local sai mais barata que o reboque. Se o profissional nao conseguir resolver, voce pode converter para reboque com '
                . $this->descontoPercentualFormatado() . ' de desconto.'
            : 'O reboque ate a oficina mais proxima ficou mais em conta que a saida da assistencia.';

        $payload = [
            'opcoes_disponiveis' => ['assistencia', 'reboque'],
            'opcao_assistencia' => [
                'disponivel' => true,
                'profissionais_proximos' => count($oficinas),
                'distancia_km' => round($distanciaOficina, 1),
                'custo_saida' => $custoAssistencia,
                'mensagem' => 'Um profissional vai ate voce. Voce paga apenas a saida dele.',
            ],
            'opcao_reboque' => [
                'distancia_ate_oficina_km' => round($distanciaOficina, 1),
                'custo_total' => $custoReboque,
                'mensagem' => 'Vamos levar seu veiculo ate a oficina mais proxima.',
            ],
            'recomendacao' => $recomendacao,
            'justificativa' => $justificativa,
            'desconto_fallback_percentual' => $this->descontoPercentual(),
        ];

        Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'decisao_atendimento', 'Comparativo calculado.', [
            'pedido_id' => $pedidoId ?: null,
            'tipo_problema' => $tipo,
            'recomendacao' => $recomendacao,
            'custo_assistencia' => $custoAssistencia,
            'custo_reboque' => $custoReboque,
        ]);

        return $payload;
    }

    private function somenteReboque(int $pedidoId, string $tipo, float $distancia, string $motivo): array
    {
        $custoReboque = $this->calcularReboque($distancia);

        Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'decisao_atendimento', 'Somente reboque.', [
            'pedido_id' => $pedidoId ?: null,
            'tipo_problema' => $tipo,
            'motivo' => $motivo,
            'custo_reboque' => $custoReboque,
        ]);

        return [
            'opcoes_disponiveis' => ['reboque'],
            'opcao_assistencia' => ['disponivel' => false],
            'opcao_reboque' => [
                'distancia_ate_oficina_km' => round($distancia, 1),
                'custo_total' => $custoReboque,
                'mensagem' => 'Vamos levar seu veiculo ate a oficina mais proxima.',
            ],
            'recomendacao' => 'reboque',
            'justificativa' => $motivo,
            'desconto_fallback_percentual' => $this->descontoPercentual(),
        ];
    }

    private function calcularAssistencia(): float
    {
        $base     = (float)Configuracao::get('custo_saida_profissional_padrao', '80.00');
        $comissao = (float)Configuracao::get('comissao_assistencia_percentual', '0.21');
        return round($base * (1 + $comissao), 2);
    }

    private function calcularReboque(float $distanciaKm): float
    {
        $base        = (float)Configuracao::get('taxa_fixa', '150.00');
        $tarifaPorKm = (float)Configuracao::get('tarifa_por_km', '3.50');
        return round($base + ($tarifaPorKm * $distanciaKm), 2);
    }

    public function descontoPercentual(): float
    {
        $raw = (float)Configuracao::get('desconto_saida_oficina_percentual', '0.21');
        return $raw > 1 ? $raw / 100 : max(0.0, $raw);
    }

    public function calcularValorComDesconto(float $valor): array
    {
        $percentual = $this->descontoPercentual();
        $desconto = round(max(0.0, $valor) * $percentual, 2);
        return [
            'valor_original' => round(max(0.0, $valor), 2),
            'desconto' => $desconto,
            'valor_final' => round(max(0.0, $valor - $desconto), 2),
            'percentual' => $percentual,
        ];
    }

    public static function normalizarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        return match ($tipo) {
            'combustivel', 'pane seca', 'pane-seca' => 'pane_seca',
            'eletrica', 'pane eletrica', 'pane-eletrica' => 'pane_eletrica',
            'mecanica', 'mecanico', 'pane mecanica', 'pane-mecanica', 'outro' => 'pane_mecanica',
            'reboque' => 'reboque',
            'bateria' => 'bateria',
            'pneu' => 'pneu',
            'colisao' => 'colisao',
            default => 'pane_mecanica',
        };
    }

    private function oficinasNoRaio(float $lat, float $lng): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT p.id AS provider_id, ws.latitude, ws.longitude,
                        COALESCE(NULLIF(ws.raio_resgate_direto_km, 0), 30) AS raio_resgate_direto_km
                   FROM providers p
                   JOIN provider_workshop_settings ws ON ws.provider_id = p.id
                  WHERE p.active = 1
                    AND p.approval_status = 'APPROVED'
                    AND ws.status_parceria = 'ATIVO'
                    AND ws.faz_resgate_direto = 1
                    AND ws.latitude IS NOT NULL
                    AND ws.longitude IS NOT NULL"
            );
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dist = GeoService::haversine($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
                if ($dist <= (float)$row['raio_resgate_direto_km']) {
                    $row['distancia_km'] = round($dist, 2);
                    $rows[] = $row;
                }
            }
            usort($rows, static fn(array $a, array $b): int => $a['distancia_km'] <=> $b['distancia_km']);
            return $rows;
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'decisao_atendimento', $e, ['phase' => 'oficinas_no_raio']);
            return [];
        }
    }

    private function descontoPercentualFormatado(): string
    {
        return rtrim(rtrim(number_format($this->descontoPercentual() * 100, 2, ',', '.'), '0'), ',') . '%';
    }
}