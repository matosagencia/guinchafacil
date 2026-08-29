<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../src/Services/Catalog/SystemServiceProtectionService.php';

/**
 * ROADMAP socorro automotivo — Etapa 16.
 * Proteção do serviço de sistema (reboque): pode ser configurado, mas nunca
 * removido nem desativado. Regra reforçada na camada de serviço (SRV-SYS-001),
 * não só na UI. Teste puro, sem banco.
 */
final class SystemServiceProtectionServiceTest extends TestCase
{
    private function protegido(): array
    {
        return ['id' => 1, 'code' => 'TOW_CAR', 'is_system' => 1, 'is_removable' => 0, 'can_disable' => 0];
    }

    private function comum(): array
    {
        return ['id' => 2, 'code' => 'JUMP_START', 'is_system' => 0, 'is_removable' => 1, 'can_disable' => 1];
    }

    public function testReconheceServicoProtegido(): void
    {
        $this->assertTrue(SystemServiceProtectionService::isProtected($this->protegido()));
        $this->assertFalse(SystemServiceProtectionService::isRemovable($this->protegido()));
        $this->assertFalse(SystemServiceProtectionService::canDisable($this->protegido()));
    }

    public function testServicoComumNaoEProtegido(): void
    {
        $this->assertFalse(SystemServiceProtectionService::isProtected($this->comum()));
        $this->assertTrue(SystemServiceProtectionService::isRemovable($this->comum()));
        $this->assertTrue(SystemServiceProtectionService::canDisable($this->comum()));
    }

    public function testRemoverServicoProtegidoLanca(): void
    {
        $this->expectException(DomainException::class);
        SystemServiceProtectionService::assertRemovable($this->protegido());
    }

    public function testRemoverServicoComumNaoLanca(): void
    {
        SystemServiceProtectionService::assertRemovable($this->comum());
        $this->assertTrue(true); // não lançou
    }

    public function testDesativarServicoProtegidoLanca(): void
    {
        $this->expectException(DomainException::class);
        // novoAtivo = false => tentando desativar
        SystemServiceProtectionService::assertActiveChangeAllowed($this->protegido(), false);
    }

    public function testManterAtivoServicoProtegidoNaoLanca(): void
    {
        // novoAtivo = true => manter ativo é sempre permitido
        SystemServiceProtectionService::assertActiveChangeAllowed($this->protegido(), true);
        $this->assertTrue(true);
    }

    public function testDesativarServicoComumNaoLanca(): void
    {
        SystemServiceProtectionService::assertActiveChangeAllowed($this->comum(), false);
        $this->assertTrue(true);
    }

    public function testCodigoDeErroCanonico(): void
    {
        $this->assertSame('SRV-SYS-001', SystemServiceProtectionService::ERROR_CODE);
    }
}
