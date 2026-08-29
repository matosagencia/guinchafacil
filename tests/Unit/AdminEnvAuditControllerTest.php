<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminEnvAuditControllerTest extends TestCase
{
    public function testControllerEspecializadoExponeAcaoIndex(): void
    {
        $controllerFile = dirname(__DIR__, 2) . '/src/Controllers/AdminEnvAuditController.php';

        $this->assertFileExists($controllerFile);
        require_once $controllerFile;

        $reflection = new ReflectionClass('AdminEnvAuditController');
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }
}
