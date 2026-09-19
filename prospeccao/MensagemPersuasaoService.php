<?php

namespace App\Services\Prospeccao;

/**
 * Gera o rascunho do convite. Cada princípio de persuasão usado
 * aqui só é aplicado se a afirmação correspondente for
 * verificável no próprio banco (sem inflar números, sem prazo
 * fictício):
 *
 *  - Escassez  -> vagas restantes = quota_alvo - quota_atingida (real)
 *  - Prova social -> só entra se houver contagem real de parceiros ativos
 *  - Reciprocidade -> oferta concreta definida em config, não vaga
 *  - Afinidade -> nome do negócio e categoria vêm do próprio lead
 *  - Consistência -> pede um "sim" pequeno, não o cadastro completo
 */
class MensagemPersuasaoService
{
    public function __construct(
        private readonly string $urlPreCadastro,
        private readonly ?int $parceirosAtivosRegiao = null,
        private readonly string $ofertaReciprocidade = 'primeiros 30 dias sem taxa de adesão',
    ) {
    }

    public function gerarConvite(array $lead, array $regiao): array
    {
        $vagasRestantes = max(0, (int) $regiao['quota_alvo'] - (int) $regiao['quota_atingida']);
        $primeiroNome = $this->extrairNomeCurto($lead['nome_negocio']);

        $linhas = [];
        $linhas[] = "Olá! Somos o GuinchaFácil, plataforma de despacho de guincho em {$regiao['cidade']}/{$regiao['uf']}.";
        $linhas[] = "Vimos o {$primeiroNome} no Google e achamos que combina com a nossa rede de {$lead['categoria']} na região.";

        if ($this->parceirosAtivosRegiao !== null && $this->parceirosAtivosRegiao > 0) {
            $linhas[] = "Hoje já temos {$this->parceirosAtivosRegiao} parceiros ativos por aqui recebendo chamados pelo app.";
        }

        $linhas[] = "Estamos abrindo até {$vagasRestantes} vaga(s) de parceiro nesta região, com {$this->ofertaReciprocidade}.";
        $linhas[] = "Quer saber como funciona? Responda SIM que te mando os detalhes, sem compromisso.";
        $linhas[] = "Se não for do seu interesse, é só ignorar — não vamos insistir.";

        $texto = implode("\n\n", $linhas);

        return [
            'texto' => $texto,
            'wa_link' => $this->montarLinkWhatsApp($lead['telefone_normalizado'] ?? $lead['telefone'] ?? '', $texto),
            'vagas_restantes' => $vagasRestantes,
        ];
    }

    private function extrairNomeCurto(string $nomeNegocio): string
    {
        return trim(explode(' - ', $nomeNegocio)[0]);
    }

    private function montarLinkWhatsApp(string $telefone, string $texto): ?string
    {
        $digitos = preg_replace('/\D/', '', $telefone);
        if ($digitos === '') {
            return null;
        }
        if (strlen($digitos) <= 11) {
            $digitos = '55' . $digitos; // DDI Brasil quando não informado
        }

        return 'https://wa.me/' . $digitos . '?text=' . rawurlencode($texto);
    }
}
