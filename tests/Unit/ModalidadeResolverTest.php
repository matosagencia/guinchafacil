<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Pedido/ModalidadeResolver.php';

final class ModalidadeResolverTest extends TestCase
{
    public function testProblemasSimplesViramSocorroLocal(): void
    {
        $resolver = new ModalidadeResolver();

        $this->assertSame(ModalidadeResolver::SOCORRO_LOCAL, $resolver->resolver('pneu furado'));
        $this->assertSame(ModalidadeResolver::SOCORRO_LOCAL, $resolver->resolver('bateria descarregada'));
        $this->assertSame(ModalidadeResolver::SOCORRO_LOCAL, $resolver->resolver('pane seca gasolina'));
    }

    public function testProblemasEstruturaisViramReboque(): void
    {
        $resolver = new ModalidadeResolver();

        $this->assertSame(ModalidadeResolver::REBOQUE_PRANCHA, $resolver->resolver('eixo quebrado'));
        $this->assertSame(ModalidadeResolver::REBOQUE_PRANCHA, $resolver->resolver('roda travada'));
        $this->assertSame(ModalidadeResolver::REBOQUE_PRANCHA, $resolver->resolver('capotamento'));
    }
}
