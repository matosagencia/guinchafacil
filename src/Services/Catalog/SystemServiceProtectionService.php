<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Catalog/SystemServiceProtectionService.php
// ROADMAP socorro automotivo - Etapa 16 (servi�o de sistema protegido).
//
// O reboque � estrutural: pode ser configurado, mas nunca removido nem
// desativado. A prote��o N�O � s� UI escondida - � refor�ada aqui, na camada
// de servi�o, com DomainException('SRV-SYS-001'), de forma que qualquer
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

    /** Lan�a se o servi�o n�o puder ser removido. */
    public static function assertRemovable(array $serviceType): void
    {
        if (!self::isRemovable($serviceType)) {
            self::deny($serviceType, 'remover');
        }
    }

    /**
     * Lan�a se a inten��o for DESATIVAR (active -> 0) um servi�o que n�o
     * pode ser desativado. Ativar (ou manter ativo) � sempre permitido.
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
            "Tentativa de {$acao} servi�o de sistema bloqueada (" . self::ERROR_CODE . ")",
            [
                'code' => self::ERROR_CODE,
                'service_type_id' => $serviceType['id'] ?? null,
                'service_code' => $serviceType['code'] ?? null,
                'acao' => $acao,
            ]
        );
        throw new DomainException(
            'Servi�o estrutural do GuinchaF�cil. Pode ser configurado, mas n�o removido nem desativado. (' . self::ERROR_CODE . ')'
        );
    }
}
