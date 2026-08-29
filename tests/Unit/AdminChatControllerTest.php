<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminChatControllerTest extends TestCase
{
    public function testControllerEspecializadoExponeAcaoIndex(): void
    {
        $controllerFile = dirname(__DIR__, 2) . '/src/Controllers/AdminChatController.php';

        $this->assertFileExists($controllerFile);
        require_once $controllerFile;

        $reflection = new ReflectionClass('AdminChatController');
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }
}
