<?php
/**
 * Teste de integração leve, executável sem PHPUnit:
 * php tests/Integration/SessionExpirationTest.php
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/src/Services/AuthService.php';

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "[FAIL] {$message}\nEsperado: " . var_export($expected, true) . "\nObtido: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "[OK] {$message}\n";
}

assertSameValue('/guincho/atendimento/64', AuthService::sanitizeReturnPath('/guincho/atendimento/64'), 'aceita caminho interno');
assertSameValue('/', AuthService::sanitizeReturnPath('https://evil.example/phishing'), 'rejeita URL externa');
assertSameValue('/', AuthService::sanitizeReturnPath('//evil.example/phishing'), 'rejeita URL protocol-relative');
assertSameValue('/', AuthService::sanitizeReturnPath('guincho/dashboard'), 'rejeita caminho sem barra inicial');

echo "SessionExpirationTest concluído.\n";
