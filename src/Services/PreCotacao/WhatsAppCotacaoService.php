<?php
declare(strict_types=1);

final class WhatsAppCotacaoService
{
    public static function gerarLink(array $ctx): string
    {
        $numero = self::numeroWhatsApp();
        if ($numero === '') return '';
        $msg = self::mensagem($ctx);
        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($msg);
    }

    private static function numeroWhatsApp(): string
    {
        $raw = '';
        if (defined('COMPANY_WHATSAPP') && COMPANY_WHATSAPP !== '') {
            $raw = (string)COMPANY_WHATSAPP;
        } elseif (getenv('WHATSAPP_DEFAULT_TO')) {
            $raw = (string)getenv('WHATSAPP_DEFAULT_TO');
        } elseif (getenv('WHATSAPP_FALLBACK_TO')) {
            $raw = (string)getenv('WHATSAPP_FALLBACK_TO');
        }
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') return '';
        if (strlen($digits) <= 11) $digits = '55' . $digits;
        return $digits;
    }

    private static function mensagem(array $ctx): string
    {
        $linhas = [];
        $linhas[] = 'Ola! Preciso de ajuda com meu carro.';
        $linhas[] = '';

        if (!empty($ctx['modo'])) {
            $mapa = ['orientacao' => 'Me orientem', 'local' => 'Resolver no local', 'levar_carro' => 'Levar o carro'];
            $linhas[] = '*Modo:* ' . ($mapa[$ctx['modo']] ?? $ctx['modo']);
        }
        if (!empty($ctx['servico'])) {
            $linhas[] = '*Servico:* ' . $ctx['servico'];
        }
        if (!empty($ctx['veiculo'])) {
            $linhas[] = '*Veiculo:* ' . $ctx['veiculo'];
        }
        if (!empty($ctx['origem_texto'])) {
            $linhas[] = '*Origem:* ' . $ctx['origem_texto'];
        }
        if (!empty($ctx['origem_lat']) && !empty($ctx['origem_lng'])) {
            $linhas[] = 'Mapa: https://www.google.com/maps?q=' . $ctx['origem_lat'] . ',' . $ctx['origem_lng'];
        }
        if (!empty($ctx['destino_texto'])) {
            $linhas[] = '*Destino:* ' . $ctx['destino_texto'];
        }
        return implode("\n", $linhas);
    }
}
