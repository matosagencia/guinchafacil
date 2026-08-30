<?php
declare(strict_types=1);

final class EnderecoFormatter
{
    public static function compor(string $endereco, ?string $numero = null, ?string $complemento = null): string
    {
        $partes = [];
        $endereco = trim($endereco);
        $numero = $numero !== null ? trim($numero) : '';
        $complemento = $complemento !== null ? trim($complemento) : '';

        if ($endereco !== '') {
            $partes[] = $endereco;
        }
        if ($numero !== '') {
            $partes[] = 'nº ' . $numero;
        }
        if ($complemento !== '') {
            $partes[] = $complemento;
        }

        return trim(implode(', ', $partes));
    }

    public static function comNumeroNoTexto(string $endereco, ?string $numero = null): string
    {
        return self::compor($endereco, $numero, null);
    }
}
