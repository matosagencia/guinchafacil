<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Catalog/SystemServiceProtectionService.php
// ROADMAP socorro automotivo — Etapa 16 (serviço de sistema protegido).
//
// O reboque é estrutural: pode ser configurado, mas nunca removido nem
// desativado. A proteção NÃO é só UI escondida — é reforçada aqui, na camada
// de serviço, com DomainException('SRV-SYS-001'), de forma que qualquer
// caminho (POST direto, script, futura API) esbarre na mesma regra.

require_once __DIR__ . '/../Logger.php';

class SystemServiceProtectionService
{
    public const ERROR_CODE = 'SRV-SYS-001';

    public static function isProtected(array $serviceType): bool
    {
        return (int)($serviceType['is_system'] ?? 0) === 1;
    }

    public static function isRemovable(array $serviceType): bool
    {
        return (int)($serviceType['is_removable'] ?? 1) === 1;
    }

    public static function canDisable(array $serviceType): bool
    {
        return (int)($serviceType['can_disable'] ?? 1) === 1;
    }

    /** Lança se o serviço não puder ser removido. */
    public static function assertRemovable(array $serviceType): void
    {
        if (!self::isRemovable($serviceType)) {
            self::deny($serviceType, 'remover');
        }
    }

    /**
     * Lança se a intenção for DESATIVAR (active -> 0) um serviço que não
     * pode ser desativado. Ativar (ou manter ativo) é sempre permitido.
     */
    public static function assertActiveChangeAllowed(array $serviceType, bool $novoAtivo): void
    {
        if (!$novoAtivo && !self::canDisable($serviceType)) {
            self::deny($serviceType, 'desativar');
        }
    }

    private static function deny(array $serviceType, string $acao): void
    {
        Logger::log(Logger::LEVEL_WARN, 'SystemServiceProtectionService', 'deny', 'catalogo_servicos',
            "Tentativa de {$acao} serviço de sistema bloqueada (" . self::ERROR_CODE . ")",
            [
                'code' => self::ERROR_CODE,
                'service_type_id' => $serviceType['id'] ?? null,
                'service_code' => $serviceType['code'] ?? null,
                'acao' => $acao,
            ]
        );
        throw new DomainException(
            'Serviço estrutural do GuinchaFácil. Pode ser configurado, mas não removido nem desativado. (' . self::ERROR_CODE . ')'
        );
    }
}
