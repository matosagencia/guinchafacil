<?php
declare(strict_types=1);

final class PartnerProspectingScoreService
{
    public const WEIGHTS = [
        'demanda_sem_oferta' => 30.0,
        'compatibilidade' => 25.0,
        'capacidade' => 20.0,
        'reputacao' => 15.0,
        'indicacao' => 10.0,
    ];

    public static function avaliar(array $lead, array $regiao): array
    {
        $categoria = mb_strtolower((string)($lead['categoria'] ?? ''), 'UTF-8');
        $alvos = mb_strtolower((string)($regiao['categorias_alvo'] ?? ''), 'UTF-8');
        $demanda = ((int)($regiao['quota_atingida'] ?? 0) < (int)($regiao['quota_alvo'] ?? 0)) ? 30.0 : 0.0;
        $compat = ($categoria !== '' && (str_contains($alvos, $categoria) || str_contains($categoria, 'guincho') || str_contains($categoria, 'oficina'))) ? 25.0 : 10.0;
        $capacidade = (!empty($lead['website']) || (int)($lead['reviews_count'] ?? 0) >= 5) ? 12.0 : 5.0;
        $reputacao = min(15.0, max(0.0, ((float)($lead['rating'] ?? 0) / 5.0) * 15.0));
        $indicacao = (str_contains($categoria, 'posto') || str_contains($categoria, 'estacion') || str_contains($categoria, 'locadora') || str_contains($categoria, 'segur')) ? 10.0 : 3.0;
        $scores = [
            'demanda_sem_oferta' => $demanda,
            'compatibilidade' => $compat,
            'capacidade' => $capacidade,
            'reputacao' => $reputacao,
            'indicacao' => $indicacao,
        ];
        $total = array_sum($scores);
        $fit = sprintf('Demanda sem oferta: %.0f/30; compatibilidade: %.0f/25; capacidade declarada: %.0f/20; reputação verificável: %.0f/15; potencial de indicação: %.0f/10.', $demanda, $compat, $capacidade, $reputacao, $indicacao);
        return ['total' => round($total, 2), 'scores' => $scores, 'motivo_fit' => $fit, 'canal_sugerido' => !empty($lead['telefone']) ? 'whatsapp_manual' : 'pesquisa'];
    }

    public static function mensagem(array $lead, array $regiao): string
    {
        $nome = trim((string)($lead['nome_negocio'] ?? 'sua empresa'));
        $cidade = trim((string)($regiao['cidade'] ?? 'sua região'));
        $categoria = mb_strtolower((string)($lead['categoria'] ?? ''), 'UTF-8');
        if (str_contains($categoria, 'oficin')) {
            return 'Identificamos que sua oficina atende veiculos em ' . $cidade . '. O GuinchaFacil encaminha motoristas que precisam de reparo apos uma pane ou reboque. A oficina recebe oportunidades compativeis e pode enviar orcamento pelo painel. Gostaria de conhecer a operacao?';
        }
        if (str_contains($categoria, 'posto') || str_contains($categoria, 'estacion')) {
            return "Olá, {$nome}. Estamos criando uma rede de assistência para motoristas que enfrentam panes em {$cidade}. Seu estabelecimento poderia indicar o atendimento digital do GuinchaFácil a clientes que precisarem de guincho ou oficina?";
        }
        return "Olá, {$nome}. Identificamos que sua empresa atende veículos em {$cidade}. O GuinchaFácil encaminha motoristas que precisam de reparo após uma pane ou reboque. Gostaria de conhecer a operação?";
    }
}
