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
        'motor ferveu', 'mecanica simples', 'mecânica simples', 'partida',
    ];

    public function resolver(string $tipoProblema, array $context = []): string
    {
        if (!empty($context['modalidade_socorro'])) {
            $forced = strtoupper(trim((string)$context['modalidade_socorro']));
            if (in_array($forced, [self::SOCORRO_LOCAL, self::REBOQUE_PRANCHA, 'REBOQUE_TRADICIONAL'], true)) {
                return $forced === 'REBOQUE_TRADICIONAL' ? self::REBOQUE_PRANCHA : $forced;
            }
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
