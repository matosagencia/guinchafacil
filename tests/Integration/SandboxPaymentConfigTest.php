<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SandboxPaymentConfigTest extends TestCase
{
    public function testMercadoPagoUsaCredencialSandboxNoEnvLocal(): void
    {
        // Este teste valida a resolução de credencial sandbox feita pelo
        // config.php a partir do .env real. O bootstrap de unidade congela
        // MP_ACCESS_TOKEN com um valor stub ANTES de config.php rodar, então
        // config.php não consegue resolvê-lo — o teste só é significativo
        // rodando contra a configuração real (não sob o stub). Pula nesse caso.
        if (MP_ACCESS_TOKEN === 'TEST_MP_ACCESS_TOKEN') {
            $this->markTestSkipped('Rodando sob o bootstrap stub (MP_ACCESS_TOKEN congelado); teste requer config .env real.');
        }

        // O Mercado Pago descontinuou o prefixo "TEST-" para credenciais de
        // teste: hoje, credenciais de usuário de teste também usam o prefixo
        // "APP_USR-", igual às de produção (a diferença está no App ID/User ID
        // vinculado, não em um prefixo textual). Por isso a validação correta
        // não é mais checar o formato do token, e sim garantir que, com
        // MP_ENV=sandbox, o token efetivo (MP_ACCESS_TOKEN) realmente vem de
        // MP_ACCESS_TOKEN_SANDBOX — e não coincide com a credencial de produção.
        $this->assertSame('sandbox', MP_ENV, 'MP_ENV deveria estar configurado como sandbox no ambiente de teste.');
        $this->assertNotEmpty(MP_ACCESS_TOKEN, 'MP_ACCESS_TOKEN não deveria estar vazio quando MP_ENV=sandbox.');
        $this->assertSame(
            MP_ACCESS_TOKEN_SANDBOX,
            MP_ACCESS_TOKEN,
            'Com MP_ENV=sandbox, MP_ACCESS_TOKEN deveria refletir MP_ACCESS_TOKEN_SANDBOX.'
        );
        $this->assertNotSame(
            MP_ACCESS_TOKEN_PROD,
            MP_ACCESS_TOKEN,
            'MP_ACCESS_TOKEN não deveria coincidir com a credencial de produção enquanto MP_ENV=sandbox — isso indicaria mistura de ambientes.'
        );
    }

    public function testPagSeguroEstaConfiguradoParaSandbox(): void
    {
        $this->assertSame('sandbox', PS_ENV);
        $this->assertStringContainsString('sandbox.pagseguro', PS_CHECKOUT_URL);
        $this->assertStringContainsString('sandbox.pagseguro', PS_BASE_URL);
    }
}
