<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Evidence/EvidenceService.php';
require_once __DIR__ . '/../../src/Models/PedidoLocalizacao.php';
require_once __DIR__ . '/../../src/Models/PedidoEvidencia.php';

/**
 * §A2 (auditoria 21/07): EvidenceService não tinha nenhum teste até aqui.
 * Cobre os três gaps reais encontrados: nonce reutilizável, ausência de
 * dedupe por conteúdo, e arquivo órfão em disco quando o INSERT falha.
 */
final class EvidenceServiceTest extends TestCase
{
    private const PEDIDO_ID = 888001;
    private const GUINCHO_ID = 501;
    private const USUARIO_ID = 601;

    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec('DELETE FROM pedido_evidencias WHERE pedido_id = ' . self::PEDIDO_ID);
        $pdo->exec('DELETE FROM pedido_localizacoes WHERE pedido_id = ' . self::PEDIDO_ID);
        $pdo->exec('DELETE FROM pedidos WHERE id = ' . self::PEDIDO_ID);

        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, lat_origem, lng_origem, lat_destino, lng_destino)
            VALUES (" . self::PEDIDO_ID . ", 'a_caminho', 100.0, -23.55, -46.63, -23.56, -46.64)
        ");
    }

    private function pedido(): array
    {
        return [
            'id' => self::PEDIDO_ID,
            'lat_origem' => -23.55,
            'lng_origem' => -46.63,
            'lat_destino' => -23.56,
            'lng_destino' => -46.64,
        ];
    }

    /** Ponto GPS válido, dentro da geofence de origem (mesma lat/lng da origem). */
    private function inserirPontoValido(string $clientPointId): int
    {
        $proximaSequencia = (int)getPDO()
            ->query('SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM pedido_localizacoes WHERE pedido_id = ' . self::PEDIDO_ID)
            ->fetchColumn();

        return PedidoLocalizacao::criar([
            'pedido_id' => self::PEDIDO_ID,
            'guincho_id' => self::GUINCHO_ID,
            'usuario_id' => self::USUARIO_ID,
            'fase' => 'origem',
            'sequence_number' => $proximaSequencia,
            'client_point_id' => $clientPointId,
            'latitude' => -23.55,
            'longitude' => -46.63,
            'accuracy_m' => 10,
            'speed_mps' => null,
            'heading_deg' => null,
            'device_timestamp' => null,
            'previous_point_id' => null,
            'distance_raw_m' => 0,
            'distance_validated_m' => 0,
            'distance_accumulated_m' => 0,
            'elapsed_ms' => null,
            'calculated_speed_kmh' => null,
            'street_name' => null,
            'street_source' => null,
            'match_confidence' => null,
            'is_valid' => 1,
            'rejection_code' => null,
            'hash_previous' => null,
            'hash_current' => 'hash-' . $clientPointId,
            'request_id' => null,
            'run_id' => null,
        ]);
    }

    /** PNG 1x1 válido (menor arquivo que passa a checagem de MIME por finfo). */
    private function criarArquivoFake(?string $sufixoConteudo = null): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        // §A2: sufixo opcional após o EOF do PNG muda o sha256 sem invalidar
        // o arquivo (viewers/finfo ignoram bytes após o IEND), usado pra
        // isolar o teste de nonce único do dedupe por conteúdo (senão os
        // dois testes disparariam a mesma violação de UNIQUE ao mesmo tempo).
        if ($sufixoConteudo !== null) {
            $png .= $sufixoConteudo;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'gf-evidence-test-');
        file_put_contents($tmp, $png);

        return [
            'name' => 'foto.png',
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
    }

    public function testUploadValidoRegistraEvidenciaAceita(): void
    {
        $pedido = $this->pedido();
        $this->inserirPontoValido('pt-' . uniqid());
        $nonce = EvidenceService::issueNonce($pedido, 'coleta');

        $evidencia = EvidenceService::storeUploadedEvidence(
            $pedido, self::GUINCHO_ID, 'coleta', $this->criarArquivoFake(), $nonce['token']
        );

        $this->assertNotEmpty($evidencia['id']);
        $registro = PedidoEvidencia::buscarPorId((int)$evidencia['id']);
        $this->assertSame('accepted', $registro['status']);
    }

    public function testNoncePodeSerUsadoApenasUmaVez(): void
    {
        $pedido = $this->pedido();
        $this->inserirPontoValido('pt-' . uniqid());
        $nonce = EvidenceService::issueNonce($pedido, 'coleta');

        // Primeiro uso: aceito.
        EvidenceService::storeUploadedEvidence(
            $pedido, self::GUINCHO_ID, 'coleta', $this->criarArquivoFake(), $nonce['token']
        );

        $dirAntes = glob(EvidenceService::privateStorageDir() . DIRECTORY_SEPARATOR . '*');

        // Segundo uso do MESMO token (com arquivo de conteúdo DIFERENTE, pra
        // isolar da constraint de dedupe por sha256): deve ser rejeitado por
        // reuso de nonce, não aceito de novo.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nonce de evidência já foi utilizado');
        try {
            EvidenceService::storeUploadedEvidence(
                $pedido, self::GUINCHO_ID, 'coleta', $this->criarArquivoFake('outro-conteudo'), $nonce['token']
            );
        } finally {
            // O arquivo movido na segunda tentativa não pode sobrar em disco
            // (compensação de rollback do §A2) — só o da primeira tentativa permanece.
            $dirDepois = glob(EvidenceService::privateStorageDir() . DIRECTORY_SEPARATOR . '*');
            $this->assertCount(count($dirAntes), $dirDepois);
        }
    }

    public function testDedupePorConteudoRejeitaMesmoArquivoComNoncesDiferentes(): void
    {
        $pedido = $this->pedido();
        $this->inserirPontoValido('pt-' . uniqid());
        $nonce1 = EvidenceService::issueNonce($pedido, 'coleta');

        EvidenceService::storeUploadedEvidence(
            $pedido, self::GUINCHO_ID, 'coleta', $this->criarArquivoFake(), $nonce1['token']
        );

        // Novo ponto válido -> novo nonce válido, mas o CONTEÚDO do arquivo é
        // idêntico (mesmo PNG fixo) para o mesmo pedido/tipo.
        $this->inserirPontoValido('pt-' . uniqid());
        $nonce2 = EvidenceService::issueNonce($pedido, 'coleta');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('já foi registrada');
        EvidenceService::storeUploadedEvidence(
            $pedido, self::GUINCHO_ID, 'coleta', $this->criarArquivoFake(), $nonce2['token']
        );
    }

    public function testNonceExpiradoEhRejeitado(): void
    {
        $pedido = $this->pedido();
        $this->inserirPontoValido('pt-' . uniqid());

        // Monta um token com expires_at no passado diretamente (sem esperar
        // os 5 minutos reais de expiração).
        $ref = new ReflectionClass(EvidenceService::class);
        $sign = $ref->getMethod('signPayload');
        $sign->setAccessible(true);
        $point = PedidoLocalizacao::buscarUltimoValidoPorPedido(self::PEDIDO_ID);
        $tokenExpirado = $sign->invoke(null, [
            'pedido_id' => self::PEDIDO_ID,
            'tipo' => 'coleta',
            'point_id' => (int)$point['id'],
            'expires_at' => date('c', time() - 10),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expirado');
        EvidenceService::storeUploadedEvidence(
            $pedido, self::GUINCHO_ID, 'coleta', $this->criarArquivoFake(), $tokenExpirado
        );
    }
}
