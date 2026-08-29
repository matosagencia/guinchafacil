<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminLogsControllerTest extends TestCase
{
    public function testControllerEspecializadoExponeAcaoIndex(): void
    {
        $controllerFile = dirname(__DIR__, 2) . '/src/Controllers/AdminLogsController.php';

        $this->assertFileExists($controllerFile);
        require_once $controllerFile;

        $reflection = new ReflectionClass('AdminLogsController');
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }
}
