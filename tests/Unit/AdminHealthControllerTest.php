<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminHealthControllerTest extends TestCase
{
    public function testControllerEspecializadoExponeAccaoHealth(): void
    {
        $controllerFile = dirname(__DIR__, 2) . '/src/Controllers/AdminHealthController.php';

        $this->assertFileExists($controllerFile);
        require_once $controllerFile;

        $reflection = new ReflectionClass('AdminHealthController');
        $this->assertTrue($reflection->hasMethod('health'));
        $this->assertTrue($reflection->getMethod('health')->isPublic());
    }
}