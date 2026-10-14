<?php
declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * Traduz slugs públicos do funil para o catálogo interno service_types.
 *
 * O mapa é fechado deliberadamente. IDs nunca são codificados aqui: o
 * callback registrado no bootstrap consulta somente códigos ativos.
 */
final class ServiceTypeResolver
{
    /** @var null|callable(string): (int|null) */
    private static $idPorCode = null;

    /**
     * Registra o lookup de catálogo durante o bootstrap da aplicação.
     *
     * @param callable(string): (int|null) $lookup
     */
    public static function definirLookupPorCodigo(callable $lookup): void
    {
        self::$idPorCode = $lookup;
    }

    /**
     * Retorna o ID ativo do serviço, ou null em toda condição não resolvida.
     */
    public static function porSlug(string $slug): ?int
    {
        $code = self::codePorSlug($slug);
        if ($code === null || self::$idPorCode === null) {
            return null;
        }

        $id = (self::$idPorCode)($code);

        return is_int($id) && $id > 0 ? $id : null;
    }

    /** Parte pura, validável sem banco. */
    public static function codePorSlug(string $slug): ?string
    {
        $mapa = [
            'pneu' => 'TIRE_CHANGE',
            'eletrica' => 'ELECTRICAL_DIAGNOSIS',
            'bateria' => 'JUMP_START',
            'mecanica' => 'MECHANICAL_ASSISTANCE',
            'chaveiro' => 'AUTOMOTIVE_LOCKSMITH',
        ];

        return $mapa[strtolower(trim($slug))] ?? null;
    }
}
