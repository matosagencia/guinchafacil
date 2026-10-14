<?php

declare(strict_types=1);

final class ModalidadeResolver
{
    public const SOCORRO_LOCAL = 'SOCORRO_LOCAL';
    public const REBOQUE_PRANCHA = 'REBOQUE_PRANCHA';

    private const REBOQUE_TERMS = [
        'batida', 'colisao', 'colisão', 'eixo', 'roda travada', 'rodas travadas',
        'travada', 'capotamento', 'capotou', 'dano estrutural', 'imobilizado',
        'nao pode movimentar', 'não pode movimentar', 'sinistro',
    ];

    private const LOCAL_TERMS = [
        'pneu', 'bateria', 'pane seca', 'gasolina', 'combustivel', 'combustível',
        'motor ferveu', 'mecanica', 'mecanica simples', 'mecânica simples', 'partida',
    ];

    public function resolver(string $tipoProblema, array $context = []): string
    {
        $decisaoAtendimento = $this->normalizar((string)($context['decisao_atendimento'] ?? ''));
        if (in_array($decisaoAtendimento, ['local', 'socorro_local'], true)) {
            return self::SOCORRO_LOCAL;
        }
        if (in_array($decisaoAtendimento, ['reboque', 'reboque_prancha', 'reboque_tradicional'], true)) {
            return self::REBOQUE_PRANCHA;
        }

        $texto = $this->normalizar($tipoProblema . ' ' . implode(' ', array_map('strval', $context)));
        foreach (self::REBOQUE_TERMS as $term) {
            if (str_contains($texto, $this->normalizar($term))) {
                return self::REBOQUE_PRANCHA;
            }
        }
        foreach (self::LOCAL_TERMS as $term) {
            if (str_contains($texto, $this->normalizar($term))) {
                return self::SOCORRO_LOCAL;
            }
        }

        return self::REBOQUE_PRANCHA;
    }

    private function normalizar(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $from = ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç'];
        $to = ['a','a','a','a','e','e','i','o','o','o','u','c'];
        return str_replace($from, $to, $value);
    }
}
