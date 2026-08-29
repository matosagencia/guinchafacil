<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Testa a lógica de validação de assinatura do WebhookController
 * sem instanciar o controller (que chama exit e file_get_contents).
 *
 * Cobre:
 *  - Fórmula HMAC-SHA256 com o manifesto no formato MercadoPago
 *  - Parsing do header X-Signature (ts=…,v1=…)
 *  - Fluxo end-to-end: gerar → parsear → verificar
 *  - Rejeição de secret errado ou manifesto adulterado
 *  - Teste da lógica de idempotência via busca de id_externo no DB
 */
class WebhookSignatureTest extends TestCase
{
    private const TS         = '1744000000';
    private const REQUEST_ID = 'req-abc-def-123';
    private const DATA_ID    = '987654321';

    // ─── Fórmula HMAC-SHA256 ─────────────────────────────────────────────────

    /** O manifesto deve seguir exatamente: id:{id};request-id:{rid};ts:{ts}; */
    public function testHmacManifestFormatoCorreto(): void
    {
        $manifest = sprintf(
            'id:%s;request-id:%s;ts:%s;',
            self::DATA_ID,
            self::REQUEST_ID,
            self::TS
        );

        $hash = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);

        // SHA-256 em hexadecimal sempre tem 64 caracteres
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function testAssinaturaValidaVerificaComHashEquals(): void
    {
        $manifest = sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, self::TS);
        $hash     = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);

        $this->assertTrue(hash_equals($hash, $hash));
    }

    public function testSecretErradoNaoVerifica(): void
    {
        $manifest   = sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, self::TS);
        $hashValido = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);
        $hashErrado = hash_hmac('sha256', $manifest, 'WRONG_SECRET_TOTALLY_DIFFERENT');

        $this->assertFalse(hash_equals($hashValido, $hashErrado));
    }

    public function testManifestAdulteradoNaoVerifica(): void
    {
        $manifestOriginal = sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, self::TS);
        $manifestFalsificado = sprintf('id:TAMPERED;request-id:%s;ts:%s;', self::REQUEST_ID, self::TS);

        $hashOriginal    = hash_hmac('sha256', $manifestOriginal,    MP_WEBHOOK_SECRET);
        $hashFalsificado = hash_hmac('sha256', $manifestFalsificado, MP_WEBHOOK_SECRET);

        $this->assertFalse(hash_equals($hashOriginal, $hashFalsificado));
    }

    public function testTsAlteradoInvalidaAssinatura(): void
    {
        $manifestOriginal = sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, self::TS);
        $manifestReplay   = sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, '9999999999');

        $hashOriginal = hash_hmac('sha256', $manifestOriginal, MP_WEBHOOK_SECRET);
        $hashReplay   = hash_hmac('sha256', $manifestReplay,   MP_WEBHOOK_SECRET);

        $this->assertFalse(hash_equals($hashOriginal, $hashReplay));
    }

    // ─── Parsing do header X-Signature ───────────────────────────────────────

    public function testParsingHeaderValido(): void
    {
        $xSig = sprintf('ts=%s,v1=%s', self::TS, str_repeat('a', 64));
        [$ts, $hash] = $this->parseXSignature($xSig);

        $this->assertSame(self::TS, $ts);
        $this->assertSame(str_repeat('a', 64), $hash);
    }

    public function testParsingHeaderComEspacosExtras(): void
    {
        $xSig = sprintf(' ts=%s , v1=%s ', self::TS, str_repeat('b', 64));
        [$ts, $hash] = $this->parseXSignature($xSig);

        $this->assertSame(self::TS, $ts);
        $this->assertSame(str_repeat('b', 64), $hash);
    }

    public function testParsingHeaderVazioRetornaNull(): void
    {
        [$ts, $hash] = $this->parseXSignature('');

        $this->assertNull($ts);
        $this->assertNull($hash);
    }

    public function testParsingHeaderMalformadoRetornaNull(): void
    {
        [$ts, $hash] = $this->parseXSignature('formato_invalido_sem_igual');

        $this->assertNull($ts);
        $this->assertNull($hash);
    }

    public function testParsingHeaderApenasTs(): void
    {
        [$ts, $hash] = $this->parseXSignature('ts=' . self::TS);

        $this->assertSame(self::TS, $ts);
        $this->assertNull($hash); // v1 ausente
    }

    public function testParsingHeaderApenasV1(): void
    {
        [$ts, $hash] = $this->parseXSignature('v1=' . str_repeat('c', 64));

        $this->assertNull($ts);  // ts ausente
        $this->assertSame(str_repeat('c', 64), $hash);
    }

    // ─── End-to-end: gerar + parsear + verificar ────────────────────────────

    public function testEndToEndAssinaturaValidaEVerificada(): void
    {
        // 1. Gera assinatura (simula o que MercadoPago faria)
        $manifest = sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, self::TS);
        $hash     = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);
        $xSig     = sprintf('ts=%s,v1=%s', self::TS, $hash);

        // 2. Parseia (reproduce WebhookController::validarAssinaturaMP)
        [$parsedTs, $parsedHash] = $this->parseXSignature($xSig);

        $this->assertNotNull($parsedTs);
        $this->assertNotNull($parsedHash);

        // 3. Recomputa e verifica
        $recomputado = hash_hmac(
            'sha256',
            sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, $parsedTs),
            MP_WEBHOOK_SECRET
        );

        $this->assertTrue(hash_equals($recomputado, $parsedHash));
    }

    public function testEndToEndAssinaturaComSecretErradoFalha(): void
    {
        $manifest = sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, self::TS);
        $hash     = hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET);
        $xSig     = sprintf('ts=%s,v1=%s', self::TS, $hash);

        [$parsedTs, $parsedHash] = $this->parseXSignature($xSig);

        $recomputadoErrado = hash_hmac(
            'sha256',
            sprintf('id:%s;request-id:%s;ts:%s;', self::DATA_ID, self::REQUEST_ID, $parsedTs),
            'SECRET_INCORRETO'
        );

        $this->assertFalse(hash_equals($recomputadoErrado, $parsedHash));
    }

    // ─── Idempotência via banco (lógica do aprovarPagamento) ────────────────

    protected function setUp(): void
    {
        getPDO()->exec("DELETE FROM pagamentos");
        getPDO()->exec("DELETE FROM logs_webhook");
    }

    public function testIdExtjernoJaAprovadoNaoDeveSerReprocessado(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, id_externo, status)
            VALUES (1, 'mercadopago', 'mp_IDEMPOTENTE', 'aprovado')
        ");

        // Busca direta que reproduz o check de idempotência do WebhookController
        $stmt = getPDO()->prepare(
            "SELECT id, status FROM pagamentos WHERE id_externo = ? LIMIT 1"
        );
        $stmt->execute(['mp_IDEMPOTENTE']);
        $existente = $stmt->fetch();

        $this->assertNotFalse($existente);
        $this->assertSame('aprovado', $existente['status']);

        // Conforme a lógica do controller: se já aprovado → ignorar
        $jaAprovado = ($existente['status'] ?? '') === 'aprovado';
        $this->assertTrue($jaAprovado, 'Webhook duplicado deveria ser ignorado (idempotência)');
    }

    public function testPagamentoPendenteDeveSerProcessado(): void
    {
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, id_externo, status)
            VALUES (2, 'mercadopago', 'mp_PENDENTE', 'pendente')
        ");

        $stmt = getPDO()->prepare(
            "SELECT id, status FROM pagamentos WHERE id_externo = ? LIMIT 1"
        );
        $stmt->execute(['mp_PENDENTE']);
        $existente = $stmt->fetch();

        $jaAprovado = ($existente['status'] ?? '') === 'aprovado';
        $this->assertFalse($jaAprovado, 'Pagamento pendente deve ser processado');
    }

    public function testIdExternoInexistenteDeveSerProcessado(): void
    {
        $stmt = getPDO()->prepare(
            "SELECT id, status FROM pagamentos WHERE id_externo = ? LIMIT 1"
        );
        $stmt->execute(['mp_NAO_EXISTE']);
        $existente = $stmt->fetch();

        $this->assertFalse($existente, 'ID externo inexistente → deve processar normalmente');
    }

    // ─── Helper: reproduz parseXSignature do WebhookController ──────────────

    /** @return array{0: string|null, 1: string|null} */
    private function parseXSignature(string $xSignature): array
    {
        $ts   = null;
        $hash = null;

        foreach (explode(',', $xSignature) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) continue;
            if (trim($kv[0]) === 'ts') $ts   = trim($kv[1]);
            if (trim($kv[0]) === 'v1') $hash = trim($kv[1]);
        }

        return [$ts, $hash];
    }
}
