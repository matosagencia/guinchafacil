<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/Triage/TriageRuleEngine.php
// ROADMAP socorro automotivo — Fundamento 3, §4.3: "A triagem deve ser
// baseada em regras versionadas. Não use IA como única fonte de decisão em
// um serviço emergencial."
//
// Motor puro (sem PDO/IO), determinístico e versionado — cada versão de
// regra é um método próprio (avaliarV1) para que uma sessão antiga nunca
// mude de interpretação retroativamente. TriageService sempre chama a
// versão mais recente (RULE_VERSION) para sessões novas.

require_once __DIR__ . '/../../DTO/Triage/TriageRequest.php';
require_once __DIR__ . '/../../DTO/Triage/TriageResult.php';

class TriageRuleEngine
{
    public const RULE_VERSION = 'v1';

    /** Símbolos de sintoma aceitos na Pergunta 1 (§4.2 do roadmap). */
    public const SYMPTOMS = [
        'NAO_LIGA',
        'PNEU',
        'PAROU_TRAJETO',
        'CHAVE',
        'SEM_COMBUSTIVEL',
        'COLISAO',
        'PRECISA_TRANSPORTAR',
        'NAO_SEI',
    ];

    public function avaliar(TriageRequest $request): TriageResult
    {
        return match (self::RULE_VERSION) {
            'v1' => $this->avaliarV1($request),
            default => $this->avaliarV1($request),
        };
    }

    private function avaliarV1(TriageRequest $request): TriageResult
    {
        $r = $request->respostas;
        $sim = static fn(string $chave): bool => strtolower((string)($r[$chave] ?? '')) === 'sim';

        return match ($request->symptomCode) {
            // Colisão: nunca tenta resolver no local — reboque + risco de segurança.
            'COLISAO' => new TriageResult(
                TriageResult::SAFETY_RISK,
                'TOW_CAR',
                [],
                true,
                'Após uma colisão, não recomendamos tentar conduzir o veículo. Aguarde em local seguro — vamos enviar reboque.',
                self::RULE_VERSION
            ),

            'CHAVE' => new TriageResult(
                TriageResult::RECOMMENDED_SERVICE,
                'AUTOMOTIVE_LOCKSMITH',
                [],
                false,
                'Chaveiro automotivo a caminho.',
                self::RULE_VERSION
            ),

            'SEM_COMBUSTIVEL' => new TriageResult(
                TriageResult::RECOMMENDED_SERVICE,
                'FUEL_DELIVERY',
                [],
                false,
                'Vamos levar combustível suficiente para você chegar ao posto mais próximo.',
                self::RULE_VERSION
            ),

            'PRECISA_TRANSPORTAR' => new TriageResult(
                TriageResult::TOWING_REQUIRED,
                'TOW_CAR',
                ['TOW_MOTORCYCLE', 'TOW_UTILITY'],
                false,
                'Vamos providenciar o reboque do veículo.',
                self::RULE_VERSION
            ),

            'NAO_LIGA' => $this->avaliarNaoLigaV1($sim),
            'PNEU' => $this->avaliarPneuV1($r, $sim),
            'PAROU_TRAJETO' => $this->avaliarPaneEletricaV1($sim),

            default => new TriageResult(
                TriageResult::MANUAL_REVIEW_REQUIRED,
                null,
                ['MECHANICAL_ASSISTANCE', 'ELECTRICAL_DIAGNOSIS', 'TOW_CAR'],
                false,
                'Não conseguimos identificar o problema com certeza — um técnico vai avaliar no local.',
                self::RULE_VERSION
            ),
        };
    }

    /** §4.2: "O painel acende? O motor tenta girar? As luzes estão fracas? O veículo apagou enquanto estava rodando?" */
    private function avaliarNaoLigaV1(callable $sim): TriageResult
    {
        if ($sim('apagou_rodando')) {
            return new TriageResult(
                TriageResult::RECOMMENDED_SERVICE,
                'ELECTRICAL_DIAGNOSIS',
                ['JUMP_START'],
                false,
                'Veículo apagando em movimento indica falha elétrica que precisa de diagnóstico — não é só bateria fraca.',
                self::RULE_VERSION
            );
        }

        if (!$sim('painel_acende') && !$sim('motor_gira')) {
            return new TriageResult(
                TriageResult::RECOMMENDED_SERVICE,
                'JUMP_START',
                ['BATTERY_TEST', 'BATTERY_REPLACEMENT'],
                false,
                'Painel apagado e motor sem girar sugerem bateria descarregada — vamos tentar a partida auxiliar primeiro.',
                self::RULE_VERSION
            );
        }

        if ($sim('luzes_fracas')) {
            return new TriageResult(
                TriageResult::RECOMMENDED_SERVICE,
                'BATTERY_TEST',
                ['JUMP_START', 'BATTERY_REPLACEMENT'],
                false,
                'Luzes fracas indicam bateria fraca ou no fim da vida útil — vamos testar antes de decidir se precisa trocar.',
                self::RULE_VERSION
            );
        }

        return new TriageResult(
            TriageResult::RECOMMENDED_SERVICE,
            'ELECTRICAL_DIAGNOSIS',
            ['MECHANICAL_ASSISTANCE'],
            false,
            'Sintomas não indicam claramente bateria — um diagnóstico elétrico no local vai identificar a causa.',
            self::RULE_VERSION
        );
    }

    /** §4.2: "Existe estepe? Está em boas condições? Parafuso antifurto? Quantos pneus afetados? Roda danificada?" */
    private function avaliarPneuV1(array $r, callable $sim): TriageResult
    {
        $pneusAfetados = (int)($r['pneus_afetados'] ?? 1);

        if ($sim('roda_danificada')) {
            return new TriageResult(
                TriageResult::TOWING_REQUIRED,
                'TOW_CAR',
                [],
                true,
                'Roda danificada não pode ser resolvida com a troca simples do pneu — é necessário reboque.',
                self::RULE_VERSION
            );
        }

        if ($pneusAfetados >= 2) {
            return new TriageResult(
                TriageResult::TOWING_REQUIRED,
                'TOW_CAR',
                [],
                false,
                'Mais de um pneu afetado — o estepe resolve só um. Vamos providenciar o reboque.',
                self::RULE_VERSION
            );
        }

        if (!$sim('estepe_existe') || strtolower((string)($r['estepe_condicao'] ?? '')) === 'ruim') {
            return new TriageResult(
                TriageResult::TOWING_REQUIRED,
                'TOW_CAR',
                [],
                false,
                'Sem estepe em condições de uso, não é possível trocar o pneu no local — vamos rebocar o veículo.',
                self::RULE_VERSION
            );
        }

        $explicacao = 'Vamos trocar o pneu danificado pelo seu estepe.';
        if ($sim('parafuso_antifurto')) {
            $explicacao .= ' Separe a chave do parafuso antifurto antes da chegada do técnico.';
        }

        return new TriageResult(
            TriageResult::RECOMMENDED_SERVICE,
            'TIRE_CHANGE',
            [],
            false,
            $explicacao,
            self::RULE_VERSION
        );
    }

    /** §4.2 (pane elétrica): "Mensagem no painel? Cheiro de queimado/fumaça? Apagou em movimento? Bateria trocada recentemente?" */
    private function avaliarPaneEletricaV1(callable $sim): TriageResult
    {
        if ($sim('cheiro_queimado')) {
            return new TriageResult(
                TriageResult::SAFETY_RISK,
                'TOW_CAR',
                [],
                true,
                'Cheiro de queimado ou fumaça é risco de segurança — não tente ligar o veículo novamente. Vamos enviar reboque.',
                self::RULE_VERSION
            );
        }

        if ($sim('apagou_em_movimento')) {
            return new TriageResult(
                TriageResult::RECOMMENDED_SERVICE,
                'ELECTRICAL_DIAGNOSIS',
                ['TOW_CAR'],
                false,
                'Falha que desliga o veículo em movimento precisa de diagnóstico no local; pode evoluir para reboque.',
                self::RULE_VERSION
            );
        }

        if ($sim('bateria_trocada_recente')) {
            return new TriageResult(
                TriageResult::RECOMMENDED_SERVICE,
                'ELECTRICAL_DIAGNOSIS',
                ['BATTERY_TEST'],
                false,
                'Como a bateria foi trocada recentemente, pode ser um problema de instalação — vamos diagnosticar.',
                self::RULE_VERSION
            );
        }

        return new TriageResult(
            TriageResult::RECOMMENDED_SERVICE,
            'MECHANICAL_ASSISTANCE',
            ['ELECTRICAL_DIAGNOSIS'],
            false,
            'Vamos enviar um técnico para avaliar e resolver no local, se possível.',
            self::RULE_VERSION
        );
    }
}
