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
        string $ofertaReciprocidade = 'inscricao gratuita na rede',
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
        $perfil = $this->classificarPerfil((string)($lead['categoria'] ?? ''), (string)($lead['nome_negocio'] ?? ''));
        $textoAbordagem = $this->montarTextoAbordagem($lead, $regiao, $perfil);
        $textoComoFunciona = $this->montarTextoComoFunciona($lead, $regiao, $perfil);

        return [
            'texto' => $textoAbordagem,
            'texto_como_funciona' => $textoComoFunciona,
            'wa_link' => $this->montarLinkWhatsApp((string)($lead['telefone_normalizado'] ?? $lead['telefone'] ?? ''), $textoAbordagem),
            'wa_link_como_funciona' => $this->montarLinkWhatsApp((string)($lead['telefone_normalizado'] ?? $lead['telefone'] ?? ''), $textoComoFunciona),
            'vagas_restantes' => $vagasRestantes,
            'perfil' => $perfil,
        ];
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $regiao
     */
    private function montarTextoAbordagem(array $lead, array $regiao, string $perfil): string
    {
        $nomeNegocio = $this->extrairNomeCurto($this->normalizarTexto((string)($lead['nome_negocio'] ?? 'Parceiro')));
        $cidade = trim($this->normalizarTexto((string)($regiao['cidade'] ?? '')));
        $uf = trim($this->normalizarTexto((string)($regiao['uf'] ?? '')));
        $localidade = trim($cidade . '/' . $uf, '/');
        $categoria = $this->normalizarTexto((string)($lead['categoria'] ?? ''));

        $linhas = [];
        $linhas[] = "Ola, {$nomeNegocio}. Posso te fazer 3 perguntas rapidas para ver se faz sentido para a sua operacao em {$localidade}?";

        if ($perfil === 'guincho') {
            $linhas[] = '1) Hoje voces atendem em ate quantos KM?';
            $linhas[] = '2) Voces conseguem receber aviso no celular e sair quando estiverem disponiveis?';
            $linhas[] = '3) Se aparecer um pedido dentro do seu raio, voces conseguem aceitar na hora?';
        } else {
            $linhas[] = '1) Voces atendem no local ou fazem o atendimento a partir da empresa?';
            $linhas[] = '2) Quais servicos voces cobrem hoje? Ex.: chaveiro, chamados eletricos, partida auxiliar, troca pelo estepe e entrega de combustivel.';
            $linhas[] = '3) Voces conseguem receber aviso no celular e escolher quando aceitar?';
        }

        if ($this->parceirosAtivosRegiao !== null && $this->parceirosAtivosRegiao > 0) {
            $linhas[] = "Ja temos {$this->parceirosAtivosRegiao} parceiros ativos na regiao.";
            $linhas[] = 'Mesmo assim, voces ainda podem entrar agora e ficar entre os primeiros parceiros locais a receber chamados.';
        } else {
            $linhas[] = 'Voces podem ser os primeiros parceiros da regiao a entrar na rede e comecar a receber chamados.';
        }

        $linhas[] = 'O cadastro e gratuito e voces podem comecar a atender assim que estiverem disponiveis.';
        $linhas[] = 'O cliente ve o valor antes de confirmar e, depois que o atendimento termina, o saldo entra no fluxo de liberacao e pode cair em ate 24h, no prazo de repasse.';
        $linhas[] = 'Se fizer sentido, eu te envio o acesso para cadastro agora.';

        if ($perfil === 'guincho') {
            $linhas[] = 'Se a sua duvida for sobre valor, ele varia conforme o tipo de servico, a distancia e a complexidade, sempre informado antes da confirmacao.';
        } else {
            $linhas[] = 'Se a sua duvida for sobre valor, ele varia conforme o tipo de servico, a distancia e a complexidade, sempre informado antes da confirmacao.';
        }

        if (trim($this->telefoneContato) !== '') {
            $linhas[] = 'Nosso WhatsApp oficial e ' . $this->formatarTelefone($this->telefoneContato) . '.';
        }

        if (trim($this->urlPreCadastro) !== '') {
            $linhas[] = 'Pre-cadastro: ' . $this->urlPreCadastro;
        }

        return implode("\n\n", $linhas);
    }

    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $regiao
     */
    private function montarTextoComoFunciona(array $lead, array $regiao, string $perfil): string
    {
        $cidade = trim($this->normalizarTexto((string)($regiao['cidade'] ?? '')));
        $uf = trim($this->normalizarTexto((string)($regiao['uf'] ?? '')));
        $localidade = trim($cidade . '/' . $uf, '/');

        $linhas = [];
        $linhas[] = "Posso te explicar em 3 passos como funciona em {$localidade}?";
        $linhas[] = '1) Voces fazem o cadastro gratuito e ja podem ficar disponiveis para novos pedidos.';
        $linhas[] = '2) Voces ativam os alertas no celular e informam o raio, os bairros e os servicos que atendem.';

        if ($perfil === 'guincho') {
            $linhas[] = '3) Quando surgir um chamado compativel, o sistema avisa no celular e voce decide se aceita.';
        } else {
            $linhas[] = '3) Quando surgir uma demanda compativel, o sistema avisa no celular e voce decide se aceita.';
        }

        $linhas[] = '4) O cliente ve o valor antes da confirmacao, com base no servico, na distancia e na complexidade.';
        $linhas[] = '5) Depois que o atendimento e concluido, o saldo entra no fluxo de liberacao e pode cair em ate 24h, no prazo de repasse.';
        $linhas[] = '6) Se quiser comecar, responda SIM que eu te envio o cadastro agora.';

        return implode("\n\n", $linhas);
    }

    private function classificarPerfil(string $categoria, string $nomeNegocio): string
    {
        $texto = mb_strtolower(trim($categoria . ' ' . $nomeNegocio), 'UTF-8');
        $palavrasGuincho = ['guincho', 'reboque', 'auto socorro', 'autossocorro', 'socorro', 'towing', 'tow truck', 'plataforma'];
        foreach ($palavrasGuincho as $palavra) {
            if (strpos($texto, $palavra) !== false) {
                return 'guincho';
            }
        }

        return 'socorro_local';
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

    private function normalizarTexto(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return $texto;
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }

        $convertido = @iconv('Windows-1252', 'UTF-8//IGNORE', $texto);
        if (is_string($convertido) && $convertido !== '') {
            return $convertido;
        }

        return function_exists('utf8_encode') ? utf8_encode($texto) : $texto;
    }
}
