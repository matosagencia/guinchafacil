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
    /**
     * Retorna o ID ativo do serviço, ou null em toda condição não resolvida.
     */
    public static function porSlug(string $slug): ?int
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') { return null; }
        if (function_exists('getPDO')) {
            try {
                $stmt = \getPDO()->prepare('SELECT id FROM service_types WHERE active = 1 AND (LOWER(public_slug) = ? OR LOWER(code) = ?) LIMIT 1');
                $stmt->execute([$slug, $slug]);
                $id = $stmt->fetchColumn();
                if ($id !== false && (int)$id > 0) { return (int)$id; }
            } catch (\Throwable $e) { }
        }
        return null;
    }

    /**
     * Parte pura, validável sem banco.
     */
    public static function codePorSlug(string $slug): ?string
    {
        $mapa = [
            'pneu'       => 'TIRE_CHANGE',
            'eletrica'   => 'ELECTRICAL_DIAGNOSIS',
            'bateria'    => 'JUMP_START',
            'combustivel'=> 'FUEL_DELIVERY',
            'pane_seca'  => 'FUEL_DELIVERY',
            'mecanica'   => 'MECHANICAL_ASSISTANCE',
            'chaveiro'   => 'AUTOMOTIVE_LOCKSMITH',
            'tire_change' => 'TIRE_CHANGE',
            'electrical_diagnosis'   => 'ELECTRICAL_DIAGNOSIS',
            'jump_start'             => 'JUMP_START',
            'fuel_delivery'          => 'FUEL_DELIVERY',
            'mechanical_assistance'  => 'MECHANICAL_ASSISTANCE',
            'automotive_locksmith'   => 'AUTOMOTIVE_LOCKSMITH',
            'towing'                 => 'TOWING',
            'battery_replacement'    => 'BATTERY_REPLACEMENT',
            'battery_test'           => 'BATTERY_TEST',
            'tire_inflation'         => 'TIRE_INFLATION',
            'tow_car'                => 'TOW_CAR',
            'tow_motorcycle'         => 'TOW_MOTORCYCLE',
            'tow_utility'            => 'TOW_UTILITY',
        ];

        $slugLower = strtolower($slug);
        return $mapa[$slugLower] ?? null;
    }
}
