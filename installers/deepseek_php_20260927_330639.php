<?php
// install-fix-fluxo.php — corrige 3 arquivos: Service + JS triagem + mojibake da view
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];

function w($root, $rel, $content, &$report) {
    $full = $root . '/' . $rel;
    if (!is_dir(dirname($full))) @mkdir(dirname($full), 0777, true);
    if (file_exists($full)) @copy($full, $full . '.bak-fluxo-' . date('Ymd-His'));
    $bytes = @file_put_contents($full, $content);
    $report[] = ($bytes === false) ? "[ERRO] $rel" : "[OK] $rel ($bytes bytes)";
}

// ═══════════════════════════════════════════════════════════
// 1. DecisaoAtendimentoService.php — remove 'me_orientem' de suporte
//    + fallback para tabela `oficinas` quando `providers` estiver vazia
// ═══════════════════════════════════════════════════════════
$service = <<<'PHPEOF'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/Logger.php';

final class DecisaoAtendimentoService
{
    // Me orientem NAO e suporte: precisa triagem + comparativo.
    private const TIPOS_SUPORTE = ['outro', 'orientacao'];

    public function avaliar(int $pedidoId, string $tipoProblema, float $lat, float $lng, array $opcoes = []): array
    {
        $tipoRaw = strtolower(trim($tipoProblema));
        if (in_array($tipoRaw, self::TIPOS_SUPORTE, true)) {
            return [
                'acao' => 'encaminhar_suporte',
                'opcoes_disponiveis' => [],
                'opcao_assistencia' => ['disponivel' => false],
                'opcao_reboque' => [],
                'recomendacao' => null,
                'justificativa' => 'Vamos te orientar pelo suporte. Nao e necessario cotar agora.',
                'mensagem_suporte' => 'Fale com o suporte apos o login pelo chat do pedido, ou entre em contato com a central.',
                'desconto_fallback_percentual' => $this->descontoPercentual(),
                'oficina_mais_proxima' => null,
            ];
        }

        $tipo = self::normalizarTipo($tipoProblema);
        $categoria = (string)($opcoes['categoria'] ?? 'popular');
        $veiculoPodeMover = (bool)($opcoes['veiculo_pode_mover'] ?? true);

        if (!$this->comparativoHabilitado()) {
            return $this->somenteReboque($pedidoId, $tipo, (float)($opcoes['distancia_km'] ?? 5.0),
                'O comparativo de assistencia esta desabilitado. Seguimos com reboque.');
        }

        $oficinas = $this->oficinasNoRaio($lat, $lng, $tipo);

        if (count($oficinas) === 0) {
            return $this->somenteReboque($pedidoId, $tipo, 5.0,
                'Nao encontramos oficinas no raio para este tipo de servico. Reboque e a unica opcao.');
        }

        if ($tipo === 'colisao' && !$veiculoPodeMover) {
            return $this->somenteReboque($pedidoId, $tipo, (float)$oficinas[0]['distancia_km'],
                'O veiculo nao pode se mover com seguranca. Reboque e obrigatorio.', $oficinas[0]);
        }

        $oficinaMaisProxima = $oficinas[0];
        $distanciaOficina = (float)$oficinaMaisProxima['distancia_km'];
        $custoAssistencia = $this->calcularAssistencia($oficinaMaisProxima);
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
            'oficina_mais_proxima' => $this->formatarOficina($oficinaMaisProxima),
        ];

        Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'decisao_atendimento', 'Comparativo calculado.', [
            'tipo_problema' => $tipo, 'recomendacao' => $recomendacao,
            'custo_assistencia' => $custoAssistencia, 'custo_reboque' => $custoReboque,
        ]);

        return $payload;
    }

    private function somenteReboque(int $pedidoId, string $tipo, float $distancia, string $motivo, ?array $oficina = null): array
    {
        return [
            'opcoes_disponiveis' => ['reboque'],
            'opcao_assistencia' => ['disponivel' => false],
            'opcao_reboque' => [
                'distancia_ate_oficina_km' => round($distancia, 1),
                'custo_total' => $this->calcularReboque($distancia),
                'mensagem' => 'Vamos levar seu veiculo ate a oficina mais proxima.',
            ],
            'recomendacao' => 'reboque',
            'justificativa' => $motivo,
            'desconto_fallback_percentual' => $this->descontoPercentual(),
            'oficina_mais_proxima' => $oficina ? $this->formatarOficina($oficina) : null,
        ];
    }

    private function calcularAssistencia(?array $oficina = null): float
    {
        $taxaOficina = isset($oficina['taxa_resgate_direto']) ? (float)$oficina['taxa_resgate_direto'] : 0.0;
        if ($taxaOficina > 0) return round($taxaOficina, 2);
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
            'mecanica', 'mecanico', 'pane mecanica', 'pane-mecanica' => 'pane_mecanica',
            'reboque' => 'reboque',
            'bateria' => 'bateria',
            'pneu' => 'pneu',
            'chaveiro' => 'chaveiro',
            'colisao' => 'colisao',
            default => 'pane_mecanica',
        };
    }

    private function comparativoHabilitado(): bool
    {
        $valor = strtolower(trim((string)Configuracao::get('habilitar_comparativo_assistencia', '1')));
        return in_array($valor, ['1', 'true', 'sim', 'yes', 'on'], true);
    }

    private function oficinasNoRaio(float $lat, float $lng, string $tipoProblema = ''): array
    {
        $rows = $this->buscarProvidersNoRaio($lat, $lng);
        if (!empty($rows)) return $rows;
        return $this->buscarOficinasNoRaio($lat, $lng, $tipoProblema);
    }

    private function buscarProvidersNoRaio(float $lat, float $lng): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT p.id AS provider_id,
                        COALESCE(NULLIF(TRIM(p.trade_name), ''), p.legal_name) AS nome,
                        ws.latitude, ws.longitude,
                        COALESCE(ws.taxa_resgate_direto, 0) AS taxa_resgate_direto,
                        COALESCE(NULLIF(ws.raio_resgate_direto_km, 0), 30) AS raio_resgate_direto_km
                   FROM providers p
                   JOIN provider_workshop_settings ws ON ws.provider_id = p.id
                  WHERE p.active = 1 AND p.approval_status = 'APPROVED'
                    AND ws.status_parceria = 'ATIVO' AND ws.faz_resgate_direto = 1
                    AND ws.latitude IS NOT NULL AND ws.longitude IS NOT NULL"
            );
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dist = GeoService::haversine($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
                if ($dist <= (float)$row['raio_resgate_direto_km']) {
                    $row['distancia_km'] = round($dist, 2);
                    $rows[] = $row;
                }
            }
            usort($rows, fn($a, $b) => $a['distancia_km'] <=> $b['distancia_km']);
            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function buscarOficinasNoRaio(float $lat, float $lng, string $tipoProblema): array
    {
        $mapaServico = [
            'pneu' => 'borracharia', 'eletrica' => 'eletrica', 'pane_eletrica' => 'eletrica',
            'bateria' => 'bateria', 'mecanica' => 'mecanica', 'pane_mecanica' => 'mecanica',
            'chaveiro' => 'chaveiro',
        ];
        $servicoTipo = $mapaServico[$tipoProblema] ?? null;

        try {
            $sql = "SELECT o.id AS provider_id, o.nome, o.latitude, o.longitude,
                           COALESCE(o.raio_atendimento_km, 10) AS raio_resgate_direto_km,
                           0 AS taxa_resgate_direto
                      FROM oficinas o
                     WHERE o.ativo = 1 AND o.latitude IS NOT NULL AND o.longitude IS NOT NULL";
            if ($servicoTipo !== null) {
                $sql .= " AND EXISTS (SELECT 1 FROM oficina_servicos os
                                       WHERE os.oficina_id = o.id
                                         AND os.tipo = :servico AND os.ativo = 1)";
            }
            $stmt = getPDO()->prepare($sql);
            if ($servicoTipo !== null) $stmt->bindValue(':servico', $servicoTipo);
            $stmt->execute();

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dist = GeoService::haversine($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
                if ($dist <= (float)$row['raio_resgate_direto_km']) {
                    $row['distancia_km'] = round($dist, 2);
                    $rows[] = $row;
                }
            }
            usort($rows, fn($a, $b) => $a['distancia_km'] <=> $b['distancia_km']);

            Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'decisao_atendimento', 'Busca em oficinas locais.', [
                'tipo_problema' => $tipoProblema, 'servico' => $servicoTipo, 'encontradas' => count($rows),
            ]);
            return $rows;
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'decisao_atendimento', $e, ['phase' => 'oficinas_locais']);
            return [];
        }
    }

    private function formatarOficina(array $oficina): array
    {
        return [
            'provider_id' => (int)($oficina['provider_id'] ?? 0),
            'nome' => (string)($oficina['nome'] ?? ''),
            'distancia_km' => round((float)($oficina['distancia_km'] ?? 0), 2),
            'taxa_resgate_direto' => round((float)($oficina['taxa_resgate_direto'] ?? 0), 2),
        ];
    }

    private function descontoPercentualFormatado(): string
    {
        return rtrim(rtrim(number_format($this->descontoPercentual() * 100, 2, ',', '.'), '0'), ',') . '%';
    }
}
PHPEOF;
w($root, 'src/Services/DecisaoAtendimentoService.php', $service, $report);

// ═══════════════════════════════════════════════════════════
// 2. public-pre-cotacao-sintomas.js — orquestra triagem
// ═══════════════════════════════════════════════════════════
$js = <<<'JSEOF'
(function () {
    'use strict';
    var tipoInput         = document.getElementById('tipo_problema');
    var situacaoStage     = document.getElementById('situacaoStage');
    var sintomaStage      = document.getElementById('sintomaStage');
    var fieldVeiculoMover = document.getElementById('fieldVeiculoPodeMover');
    var veiculoPodeMover  = document.getElementById('veiculo_pode_mover');
    var btnAvancar        = document.getElementById('btnSituacaoAvancar');
    var btnVoltar         = document.getElementById('btnSituacaoVoltar');
    if (!tipoInput || !situacaoStage || !sintomaStage || !btnAvancar) return;

    var MAPA = { pneu:'pneu', eletrica:'eletrica', bateria:'bateria', mecanica:'mecanica', chaveiro:'chaveiro' };

    function hideAll() {
        situacaoStage.hidden = true;
        sintomaStage.hidden = true;
        if (fieldVeiculoMover) fieldVeiculoMover.hidden = true;
    }
    function mostrarSituacao() { hideAll(); situacaoStage.hidden = false; }
    function mostrarSintoma()  { hideAll(); sintomaStage.hidden = false; }
    function mostrarVeiculo()  { hideAll(); if (fieldVeiculoMover) fieldVeiculoMover.hidden = false; }

    function aplicarSintoma(tipo) {
        tipoInput.value = tipo;
        hideAll();
        situacaoStage.hidden = false;
        tipoInput.dispatchEvent(new Event('change', { bubbles: true }));
        setTimeout(function () { btnAvancar.click(); }, 700);
    }

    situacaoStage.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (card) {
        card.addEventListener('click', function () {
            var s = card.getAttribute('data-choice-value');
            situacaoStage.querySelectorAll('[data-choice-group="tipo_problema"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
            tipoInput.value = s;
            if (s === 'me_orientem' || s === 'resolver_local') mostrarSintoma();
            else if (s === 'levar_carro') mostrarVeiculo();
        });
    });

    if (fieldVeiculoMover) {
        fieldVeiculoMover.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (card) {
            card.addEventListener('click', function () {
                var pode = card.getAttribute('data-choice-value') === '1';
                if (veiculoPodeMover) veiculoPodeMover.value = pode ? '1' : '0';
                fieldVeiculoMover.querySelectorAll('[data-choice-group="veiculo_pode_mover"]').forEach(function (c) {
                    c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
                if (pode) mostrarSintoma();
                else aplicarSintoma('reboque');
            });
        });
    }

    sintomaStage.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (card) {
        card.addEventListener('click', function () {
            var s = card.getAttribute('data-choice-value') || '';
            var tipo = MAPA[s] || 'mecanica';
            sintomaStage.querySelectorAll('[data-choice-group="sintoma"]').forEach(function (c) {
                c.classList.remove('is-selected'); c.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('is-selected'); card.setAttribute('aria-pressed', 'true');
            var sInput = document.getElementById('sintoma');
            if (sInput) sInput.value = s;
            aplicarSintoma(tipo);
        });
    });

    if (btnVoltar) {
        btnVoltar.addEventListener('click', function (e) {
            if (!sintomaStage.hidden || (fieldVeiculoMover && !fieldVeiculoMover.hidden)) {
                e.stopImmediatePropagation(); e.preventDefault();
                mostrarSituacao();
            }
        }, true);
    }
})();
JSEOF;
w($root, 'public/assets/js/public-pre-cotacao-sintomas.js', $js, $report);

// ═══════════════════════════════════════════════════════════
// 3. Repara mojibake em pre-cotacao.php
// ═══════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
if (file_exists($viewPath)) {
    $bytes = file_get_contents($viewPath);
    $antes = substr_count($bytes, "\xC3\x83");
    if ($antes > 0) {
        @copy($viewPath, $viewPath . '.bak-mojibake-' . date('Ymd-His'));
        $duplo = ['ÃƒÂ§'=>'ç','ÃƒÂ£'=>'ã','ÃƒÂ¡'=>'á','ÃƒÂ©'=>'é','ÃƒÂ­'=>'í','ÃƒÂ³'=>'ó','ÃƒÂº'=>'ú','ÃƒÂ¢'=>'â','ÃƒÂª'=>'ê','ÃƒÂ´'=>'ô','ÃƒÂµ'=>'õ','ÃƒÂ '=>'à','ÃƒÂ‰'=>'É','ÃƒÂ‡'=>'Ç'];
        $simples = ['Ã§'=>'ç','Ã£'=>'ã','Ã¡'=>'á','Ã©'=>'é','Ã­'=>'í','Ã³'=>'ó','Ãº'=>'ú','Ã¢'=>'â','Ãª'=>'ê','Ã´'=>'ô','Ãµ'=>'õ','Ã '=>'à','Ã‰'=>'É','Ã‡'=>'Ç','Ã‚'=>'','Ã¢â€šÂ¬'=>'€','Ã¢â‚¬â„¢'=>"'",'Ã¢â‚¬Å"'=>'"'];
        $bytes = str_replace(array_keys($duplo), array_values($duplo), $bytes);
        $bytes = str_replace(array_keys($simples), array_values($simples), $bytes);
        $depois = substr_count($bytes, "\xC3\x83");
        file_put_contents($viewPath, $bytes);
        $report[] = "[OK] pre-cotacao.php: mojibake $antes → $depois";
    } else {
        $report[] = "[SKIP] pre-cotacao.php sem mojibake";
    }
}

// ═══════════════════════════════════════════════════════════
// 4. Diagnóstico
// ═══════════════════════════════════════════════════════════
$report[] = "";
$report[] = "── DIAGNOSTICO ──";
try {
    require_once $root . '/config.php';
    $t1 = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1 AND latitude IS NOT NULL")->fetchColumn();
    $t2 = (int)getPDO()->query("SELECT COUNT(*) FROM oficina_servicos WHERE ativo=1")->fetchColumn();
    $report[] = "oficinas ativas + geolocalizadas: $t1";
    $report[] = "oficina_servicos ativos: $t2";
    if ($t2 === 0) $report[] = "[AVISO] oficina_servicos vazio — rode o SQL da Etapa 1";
} catch (Throwable $e) {
    $report[] = "[AVISO] " . $e->getMessage();
}
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix Fluxo</title>
<style>body{font-family:ui-monospace,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6}pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}h1{color:#22c55e;margin-bottom:16px}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:8px}</style>
</head><body>
<h1>Fix — Fluxo de Triagem</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-fix-fluxo.php</p>
</body></html>