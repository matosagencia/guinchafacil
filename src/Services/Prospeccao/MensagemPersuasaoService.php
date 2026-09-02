<?php

declare(strict_types=1);

final class MensagemPersuasaoService
{
    /** @var string */
    private $urlPreCadastro;
    /** @var int|null */
    private $parceirosAtivosRegiao;
    /** @var string */
    private $ofertaReciprocidade;
    /** @var string */
    private $telefoneContato;

    public function __construct(
        string $urlPreCadastro,
        ?int $parceirosAtivosRegiao = null,
        string $ofertaReciprocidade = 'primeiros 30 dias sem taxa de adesao',
        string $telefoneContato = '',
    ) {
        $this->urlPreCadastro = $urlPreCadastro;
        $this->parceirosAtivosRegiao = $parceirosAtivosRegiao;
        $this->ofertaReciprocidade = $ofertaReciprocidade;
        $this->telefoneContato = $telefoneContato;
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $regiao
     * @return array<string, mixed>
     */
    public function gerarConvite(array $lead, array $regiao): array
    {
        $vagasRestantes = max(0, (int)($regiao['quota_alvo'] ?? 0) - (int)($regiao['quota_atingida'] ?? 0));
        $primeiroNome = $this->extrairNomeCurto((string)($lead['nome_negocio'] ?? 'Parceiro'));

        $linhas = [];
        $linhas[] = "Ola! Somos o GuinchaFacil, plataforma de despacho de guincho em {$regiao['cidade']}/{$regiao['uf']}.";
        $linhas[] = "Vimos o {$primeiroNome} no Google e achamos que combina com a nossa rede de {$lead['categoria']} na regiao.";

        if ($this->parceirosAtivosRegiao !== null && $this->parceirosAtivosRegiao > 0) {
            $linhas[] = "Hoje ja temos {$this->parceirosAtivosRegiao} parceiros ativos por aqui recebendo chamados pelo app.";
        }

        $linhas[] = "Estamos abrindo ate {$vagasRestantes} vaga(s) de parceiro nesta regiao, com {$this->ofertaReciprocidade}.";

        if (trim($this->telefoneContato) !== '') {
            $linhas[] = 'Se quiser falar com a equipe, o WhatsApp oficial e ' . $this->formatarTelefone($this->telefoneContato) . '.';
        }

        if (trim($this->urlPreCadastro) !== '') {
            $linhas[] = 'Detalhes do pre-cadastro: ' . $this->urlPreCadastro;
        }

        $linhas[] = 'Quer saber como funciona? Responda SIM que te mando os detalhes, sem compromisso.';
        $linhas[] = 'Se nao for do seu interesse, e so ignorar.';

        $texto = implode("\n\n", $linhas);

        return [
            'texto' => $texto,
            'wa_link' => $this->montarLinkWhatsApp((string)($lead['telefone_normalizado'] ?? $lead['telefone'] ?? ''), $texto),
            'vagas_restantes' => $vagasRestantes,
        ];
    }

    private function extrairNomeCurto(string $nomeNegocio): string
    {
        $partes = explode(' - ', trim($nomeNegocio));
        return trim((string)($partes[0] ?? $nomeNegocio));
    }

    private function formatarTelefone(string $telefone): string
    {
        $digitos = preg_replace('/\D/', '', $telefone);
        if ($digitos === '') {
            return trim($telefone);
        }

        if (strlen($digitos) === 11) {
            return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7));
        }

        if (strlen($digitos) === 10) {
            return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6));
        }

        return $telefone;
    }

    private function montarLinkWhatsApp(string $telefone, string $texto): ?string
    {
        $digitos = preg_replace('/\D/', '', $telefone);
        if ($digitos === '') {
            return null;
        }
        if (strlen($digitos) <= 11) {
            $digitos = '55' . $digitos;
        }

        return 'https://wa.me/' . $digitos . '?text=' . rawurlencode($texto);
    }
}
