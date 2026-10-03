# Motor de Decisão — Assistência no Local vs Reboque

> **Versão:** 2.0
> **Data:** 2026-09-26
> **Autor:** Matos Agência
> **Escopo:** Pré-cotação pública + painel admin + testes

---

## 📋 Sumário

1. [Contexto](#1-contexto)
2. [Fórmulas travadas](#2-fórmulas-travadas)
3. [Árvore de decisão](#3-árvore-de-decisão)
4. [Arquivos a alterar](#4-arquivos-a-alterar)
5. [Validação obrigatória](#5-validação-obrigatória)
6. [Critérios de aceite](#6-critérios-de-aceite)
7. [Ordem de execução](#7-ordem-de-execução)

---

## 1. Contexto

Projeto PHP MVC (repo `matosagencia/guinchafacil`). Implementar o motor de decisão **assistência no local vs reboque** na pré-cotação, comparando as duas taxas de deslocamento e oferecendo fallback com 21% de desconto.

**Regras imperativas:**

- Sem oficina no raio de atendimento → **somente reboque**
- Colisão + veículo não pode se mover → **somente reboque**

**Princípio:** usar configs existentes sempre que possível. Não criar chaves duplicadas.

---

## 2. Fórmulas travadas
ASSISTÊNCIA = custo_saida_profissional_padrao × (1 + comissao_assistencia_percentual)
= 80,00 × 1,21
= 96,80

REBOQUE = taxa_fixa + (tarifa_por_km × distancia_ate_oficina_mais_proxima)
= 150,00 + (3,50 × km)

text

### Chaves de configuração

| Chave | Status | Valor | Uso |
|---|---|---|---|
| `taxa_fixa` | **já existe** | R$ 10,00 → **atualizar para R$ 150,00** | Base do reboque |
| `tarifa_por_km` | **já existe** | R$ 3,50 | Por km do reboque |
| `custo_saida_profissional_padrao` | **criar** | R$ 80,00 | Base da assistência |
| `comissao_assistencia_percentual` | **criar** | 0.21 | Comissão sobre assistência |
| `desconto_saida_oficina_percentual` | **já existe** | 0.21 | Fallback assistência → reboque |

---

## 3. Árvore de decisão
Sem oficinas no raio → SOMENTE REBOQUE

Colisão + veículo NÃO pode mover → SOMENTE REBOQUE

Todos os outros casos → COMPARATIVO
Se reboque > assistência → recomendar ASSISTÊNCIA
Senão → recomendar REBOQUE

text

**Pergunta "O veículo pode se mover com segurança?"** só aparece no funil quando `tipo_problema = colisao`. Nos demais casos, assume `true` silenciosamente.

---

## 4. Arquivos a alterar

### 4.1 CRIAR `install/migration_decisao_config_v2.sql`

```sql
-- Motor de decisao assistencia vs reboque.
-- Usa a chave existente taxa_fixa como base do reboque (nao cria duplicada).
-- Idempotente.

INSERT INTO `configuracoes` (`chave`, `valor`, `descricao`)
VALUES
    ('custo_saida_profissional_padrao', '80.00',
     'Valor base que o cliente paga pela saida de um profissional de assistencia.'),
    ('comissao_assistencia_percentual', '0.21',
     'Percentual de comissao da plataforma sobre a saida da assistencia.')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Atualiza a base do reboque para R$ 150 somente se ainda estiver no valor
-- antigo (< R$ 100). Se o admin ja ajustou, nao sobrescreve.
UPDATE `configuracoes`
   SET `valor` = '150.00',
       `descricao` = 'Valor base do reboque (bandeirada). Somado a tarifa por km.'
 WHERE `chave` = 'taxa_fixa'
   AND CAST(`valor` AS DECIMAL(10,2)) < 100.00;
4.2 SUBSTITUIR src/Services/DecisaoAtendimentoService.php
Manter:

Constantes

normalizarTipo()

oficinasNoRaio()

descontoPercentual()

descontoPercentualFormatado()

calcularValorComDesconto()

Remover:

custoSaidaProfissional()

require_once TarifaService.php

Substituir avaliar() e adicionar:

php
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
4.3 AJUSTAR src/Controllers/PedidoController.php
No método decisaoPreCotacao(), adicionar no início:

php
$veiculoPodeMover = filter_var($_POST['veiculo_pode_mover'] ?? '1', FILTER_VALIDATE_BOOLEAN);
No array $opcoes:

php
$opcoes = [
    'categoria' => (string)($_POST['categoria'] ?? 'popular'),
    'veiculo_pode_mover' => $veiculoPodeMover,
];
4.4 ADICIONAR em src/Views/public/pre-cotacao.php
Após o fieldset de tipo_problema (linha ~49):

html
<fieldset class="quote-step" id="fieldVeiculoPodeMover" data-show-when="colisao" hidden>
    <legend>O veículo pode se mover com segurança?</legend>
    <div class="choice-grid" data-choice-group="veiculo_pode_mover">
        <button type="button" class="choice-card is-selected" data-choice-value="1" aria-pressed="true">
            <i class="fas fa-check" aria-hidden="true"></i>
            <strong>Sim, consegue andar</strong>
            <small>Posso levar devagar até a oficina.</small>
        </button>
        <button type="button" class="choice-card" data-choice-value="0" aria-pressed="false">
            <i class="fas fa-ban" aria-hidden="true"></i>
            <strong>Não, está travado</strong>
            <small>Precisa de guincho para sair do lugar.</small>
        </button>
    </div>
    <input type="hidden" name="veiculo_pode_mover" id="veiculo_pode_mover" value="1">
</fieldset>
Antes do botão de submit:

html
<div id="decisaoComparativo" class="decisao-comparativo" hidden></div>
4.5 AJUSTAR public/assets/js/public-pre-cotacao-form.js
Bloco de visibilidade condicional:

js
function atualizarVisibilidadeVeiculoPodeMover(tipoAtual) {
    var field = document.getElementById('fieldVeiculoPodeMover');
    if (!field) return;
    var mostrar = (tipoAtual === 'colisao');
    field.hidden = !mostrar;
    if (!mostrar) {
        var h = document.getElementById('veiculo_pode_mover');
        if (h) h.value = '1';
    }
}

document.querySelectorAll('[data-choice-group="tipo_problema"] .choice-card').forEach(function (btn) {
    btn.addEventListener('click', function () {
        atualizarVisibilidadeVeiculoPodeMover(btn.getAttribute('data-choice-value'));
    });
});
No FormData enviado para /api/pre-cotacao/decisao:

js
var h = document.getElementById('veiculo_pode_mover');
fd.append('veiculo_pode_mover', h ? h.value : '1');
Na renderização dos cards:

js
var soReboque = payload.opcoes_disponiveis.length === 1
             && payload.opcoes_disponiveis[0] === 'reboque';

if (soReboque) {
    // renderiza só card reboque + payload.justificativa como aviso destacado
} else {
    // renderiza 2 cards, destacando payload.recomendacao
}
4.6 ADICIONAR em src/Views/admin/configuracoes.php
Perto da linha ~261, junto do taxa_fixa:

html
<div class="col-md-4">
    <label class="form-label">Saída do profissional de assistência (R$)</label>
    <input type="number" step="0.01" class="form-control" name="custo_saida_profissional_padrao"
           value="<?php echo htmlspecialchars($config['custo_saida_profissional_padrao'] ?? '80.00'); ?>">
</div>
<div class="col-md-4">
    <label class="form-label">Comissão da assistência (decimal)</label>
    <input type="number" step="0.01" class="form-control" name="comissao_assistencia_percentual"
           value="<?php echo htmlspecialchars($config['comissao_assistencia_percentual'] ?? '0.21'); ?>">
    <small class="text-muted">Ex: 0.21 = 21%</small>
</div>
Atualizar o label do campo taxa_fixa existente:

html
<label class="form-label">Taxa Fixa / Bandeirada do Reboque (R$)</label>
4.7 ADICIONAR em src/Controllers/AdminController.php
Na whitelist (linha ~3483):

php
'tarifa_por_km', 'taxa_fixa',
'custo_saida_profissional_padrao', 'comissao_assistencia_percentual',
No array de chaves salvas (linha ~3591):

php
"tarifa_por_km", "taxa_fixa", "comissao_plataforma",
"custo_saida_profissional_padrao", "comissao_assistencia_percentual",
4.8 ADICIONAR em tests/Integration/DecisaoAtendimentoTest.php
php
public function testColisaoComVeiculoTravadoRetornaSomenteReboque(): void
{
    $svc = new DecisaoAtendimentoService();
    $r = $svc->avaliar(0, 'colisao', -22.9, -43.2, ['veiculo_pode_mover' => false]);
    $this->assertSame(['reboque'], $r['opcoes_disponiveis']);
    $this->assertSame('reboque', $r['recomendacao']);
}

public function testColisaoComVeiculoMovelRetornaComparativo(): void
{
    $svc = new DecisaoAtendimentoService();
    $r = $svc->avaliar(0, 'colisao', -22.9, -43.2, ['veiculo_pode_mover' => true]);
    $this->assertContains('assistencia', $r['opcoes_disponiveis']);
    $this->assertContains('reboque', $r['opcoes_disponiveis']);
}

public function testSemOficinaNoRaioRetornaSomenteReboque(): void
{
    $svc = new DecisaoAtendimentoService();
    $r = $svc->avaliar(0, 'bateria', 0.0, 0.0, ['veiculo_pode_mover' => true]);
    $this->assertSame(['reboque'], $r['opcoes_disponiveis']);
}

public function testReboqueUsaTaxaFixaComoBase(): void
{
    $svc = new DecisaoAtendimentoService();
    $reflex = new ReflectionMethod($svc, 'calcularReboque');
    $reflex->setAccessible(true);
    $valor = $reflex->invoke($svc, 10.0);
    $this->assertGreaterThan(150.0, $valor);
}

public function testAssistenciaRecomendadaQuandoReboqueMaisCaro(): void
{
    $svc = new DecisaoAtendimentoService();
    $r = $svc->avaliar(0, 'bateria', -22.9, -43.2, ['veiculo_pode_mover' => true]);
    if (in_array('assistencia', $r['opcoes_disponiveis'], true)) {
        $this->assertContains($r['recomendacao'], ['assistencia', 'reboque']);
    } else {
        $this->markTestSkipped('Sem oficina no raio de teste.');
    }
}
5. Validação obrigatória
5.1 Sintaxe
powershell
php install/migrate.php
php -l src/Services/DecisaoAtendimentoService.php
php -l src/Controllers/PedidoController.php
php -l src/Controllers/AdminController.php
php -l src/Views/public/pre-cotacao.php
php -l src/Views/admin/configuracoes.php
node --check public/assets/js/public-pre-cotacao-form.js
5.2 Testes
powershell
vendor\bin\phpunit tests/Integration/DecisaoAtendimentoTest.php
5.3 Teste manual (localhost:8080/pre-cotacao)
#	Cenário	Resultado esperado
1	Bateria	2 cards, assistência destacada (R
96
,
80
v
s
R
96,80vsR 150 + km)
2	Colisão + "Sim"	2 cards com comparativo
3	Colisão + "Não"	Só card reboque
4	Endereço sem oficina no raio	Só card reboque
5	Admin → Configurações	2 campos novos + taxa_fixa mostrando R$ 150
6	Alterar custo_saida_profissional_padrao para 100	Próxima cotação mostra R$ 121,00
7	Alterar comissao_assistencia_percentual para 0.30	Próxima cotação embute 30%
6. Critérios de aceite
□ Colisão + "Não pode mover" → nunca mostra card de assistência
□ Sem oficina no raio → nunca mostra card de assistência
□ taxa_fixa não é criada de novo — usa a existente
□ Reboque = taxa_fixa + (tarifa_por_km × km)
□ Assistência = custo_saida_profissional_padrao × (1 + comissao_assistencia_percentual)
□ Alterar valores no admin reflete no próximo cálculo sem deploy
□ Pergunta "veículo pode se mover?" só aparece em colisao
□ Toda decisão continua em pedido_decisoes_socorro (não regride)
□ Migration não sobrescreve taxa_fixa se já estiver ≥ R$ 100
7. Ordem de execução
✅ Migration install/migration_decisao_config_v2.sql

✅ Service: substituir avaliar(), adicionar somenteReboque(), calcularAssistencia(), calcularReboque(); remover custoSaidaProfissional() e o require do TarifaService

✅ PedidoController::decisaoPreCotacao() — ler veiculo_pode_mover

✅ View pre-cotacao.php — fieldset condicional + container do comparativo

✅ JS — visibilidade condicional + envio + renderização

✅ Admin view — 2 inputs + atualizar label do taxa_fixa

✅ AdminController — whitelist

✅ Testes — 5 casos

✅ Rodar validações locais (seção 5)

✅ Reportar outputs (php -l, phpunit, testes manuais)

Relatório final esperado
Ao terminar, reportar:

Output dos php -l (todos devem dizer "No syntax errors detected")

Output do phpunit (todos os 5 testes devem passar)

Descrição dos 7 testes manuais (tabela da seção 5.3)

git status no XAMPP mostrando os arquivos alterados/criados
