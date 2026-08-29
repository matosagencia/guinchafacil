<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Models/PedidoEvidencia.php';
require_once __DIR__ . '/../../Models/PedidoLocalizacao.php';
require_once __DIR__ . '/../../Models/Configuracao.php';
require_once __DIR__ . '/../POR/GeofenceService.php';
require_once __DIR__ . '/../POR/PorThresholds.php';

class EvidenceService
{
    public static function issueNonce(array $pedido, string $tipo): array
    {
        $point = PedidoLocalizacao::buscarUltimoValidoPorPedido((int)$pedido['id']);
        if (!$point) {
            throw new RuntimeException('Nenhum ponto válido recente disponível para evidência.');
        }

        $expiresAt = date('c', time() + 300);
        $payload = [
            'pedido_id' => (int)$pedido['id'],
            'tipo' => $tipo,
            'point_id' => (int)$point['id'],
            'expires_at' => $expiresAt,
        ];

        return [
            'token' => self::signPayload($payload),
            'point_id' => (int)$point['id'],
            'expires_at' => $expiresAt,
        ];
    }

    public static function storeUploadedEvidence(array $pedido, int $guinchoId, string $tipo, array $file, string $nonceToken): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Arquivo de evidência ausente.');
        }

        $payload = self::verifyPayload($nonceToken, (int)$pedido['id'], $tipo);
        $point = PedidoLocalizacao::buscarPorId((int)$payload['point_id']);
        if (!$point || (int)$point['pedido_id'] !== (int)$pedido['id']) {
            throw new RuntimeException('Ponto de evidência inválido.');
        }

        $maxAgeSeconds = PorThresholds::photoGpsMaxAgeSeconds();
        $pointTs = !empty($point['device_timestamp']) ? ((int)$point['device_timestamp'] / 1000) : strtotime((string)$point['server_timestamp']);
        if ((time() - (int)$pointTs) > $maxAgeSeconds) {
            throw new RuntimeException('Ponto GPS da evidência expirado.');
        }

        // §A3 — raio único (PorThresholds::arrivalRadiusM()), antes duplicado
        // aqui e em PedidoTransitionService::validatePreconditions() com o
        // mesmo default (150/200) mas cada um lendo Configuracao::getAll()
        // por conta própria.
        // Diagnóstico/orçamento ocorre no local de origem. Essas evidências
        // devem usar a mesma geofence de chegada, e não a do destino.
        $naOrigem = in_array($tipo, ['coleta', 'diagnostico', 'orcamento'], true);
        $radius = $naOrigem
            ? PorThresholds::arrivalRadiusM()
            : PorThresholds::destinationRadiusM();

        $isNear = $naOrigem
            ? GeofenceService::isNearOrigin($pedido, (float)$point['latitude'], (float)$point['longitude'], $radius)
            : GeofenceService::isNearDestination($pedido, (float)$point['latitude'], (float)$point['longitude'], $radius);

        if (!$isNear) {
            throw new RuntimeException('Evidência rejeitada fora da geofence permitida.');
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('Arquivo acima do limite de 5MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file((string)$file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Tipo MIME inválido para evidência.');
        }

        // Pacote L1.5 — evidências NÃO ficam mais em public/uploads (webroot,
        // acessível por URL direta sem autorização). Passam a ser salvas em
        // storage/private/evidencias, fora do diretório servido pelo Apache
        // (bloqueado também via .htaccess na raiz do projeto). O único acesso
        // possível passa a ser a rota autorizada /evidencia/{id}.
        $destDir = self::privateStorageDir();
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0770, true);
        }

        $storedName = sprintf(
            '%s_%d_%d_%s.%s',
            $tipo,
            (int)$pedido['id'],
            $guinchoId,
            bin2hex(random_bytes(8)),
            $allowed[$mime]
        );
        $destPath = $destDir . DIRECTORY_SEPARATOR . $storedName;
        // is_uploaded_file() só é true em requisições HTTP reais; testes
        // automatizados (e o fallback de CLI/simulação já usado em
        // PedidoTransitionService::salvarComprovanteManual()) usam rename().
        $moved = is_uploaded_file((string)$file['tmp_name'])
            ? move_uploaded_file((string)$file['tmp_name'], $destPath)
            : rename((string)$file['tmp_name'], $destPath);
        if (!$moved) {
            throw new RuntimeException('Falha ao mover arquivo de evidência.');
        }

        $sha256 = (string)hash_file('sha256', $destPath);

        // §A2 — a partir daqui o arquivo já foi movido para o storage privado
        // (não é uma operação transacional, não tem como evitar). Se o
        // INSERT falhar por qualquer motivo — incluindo as UNIQUE KEYs de
        // nonce/dedupe (migration_evidencia_nonce_dedupe_v1.sql) — o arquivo
        // não pode ficar órfão em disco: removemos antes de propagar o erro.
        try {
            $evidenciaId = PedidoEvidencia::criar([
                'pedido_id' => (int)$pedido['id'],
                'guincho_id' => $guinchoId,
                'tipo' => $tipo,
                'status' => 'accepted',
                'nonce_token' => $nonceToken,
                'nonce_expires_at' => (string)$payload['expires_at'],
                'point_id' => (int)$point['id'],
                'latitude' => (float)$point['latitude'],
                'longitude' => (float)$point['longitude'],
                'accuracy_m' => $point['accuracy_m'] !== null ? (float)$point['accuracy_m'] : null,
                'device_timestamp' => $point['device_timestamp'],
                'original_name' => (string)($file['name'] ?? $storedName),
                'stored_name' => $storedName,
                'mime_type' => $mime,
                'size_bytes' => (int)$file['size'],
                'sha256' => $sha256,
                'metadata_json' => [
                    'point_sequence' => (int)$point['sequence_number'],
                    'street_name' => $point['street_name'] ?? null,
                ],
            ]);
        } catch (PDOException $e) {
            @unlink($destPath);
            $msg = $e->getMessage();
            // Mensagem varia entre MySQL/MariaDB ("... for key 'uk_nonce_token'")
            // e SQLite ("UNIQUE constraint failed: pedido_evidencias.nonce_token"),
            // por isso o match é pelo nome da coluna (comum aos dois formatos),
            // não pelo nome completo do índice.
            if ((string)$e->getCode() === '23000' && str_contains($msg, 'nonce_token')) {
                throw new RuntimeException('Este nonce de evidência já foi utilizado.');
            }
            if ((string)$e->getCode() === '23000' && str_contains($msg, 'sha256')) {
                throw new RuntimeException('Esta evidência já foi registrada para este pedido/etapa.');
            }
            throw $e;
        } catch (Throwable $e) {
            @unlink($destPath);
            throw $e;
        }

        return [
            'id' => $evidenciaId,
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'sha256' => $sha256,
            'point_id' => (int)$point['id'],
        ];
    }

    /** Diretório privado de storage, fora de public/ (Pacote L1.5). */
    public static function privateStorageDir(): string
    {
        return rtrim(dirname((string)PUBLIC_PATH), '/\\') . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'evidencias';
    }

    private static function signPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $body = rtrim(strtr(base64_encode((string)$json), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $body, (string)ENCRYPTION_KEY);
        return $body . '.' . $sig;
    }

    private static function verifyPayload(string $token, int $pedidoId, string $tipo): array
    {
        [$body, $sig] = array_pad(explode('.', $token, 2), 2, '');
        $expected = hash_hmac('sha256', $body, (string)ENCRYPTION_KEY);
        if ($body === '' || $sig === '' || !hash_equals($expected, $sig)) {
            throw new RuntimeException('Nonce de evidência inválido.');
        }

        $normalized = strtr($body, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode($normalized, true);
        $payload = json_decode((string)$json, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Nonce de evidência corrompido.');
        }
        if ((int)($payload['pedido_id'] ?? 0) !== $pedidoId || (string)($payload['tipo'] ?? '') !== $tipo) {
            throw new RuntimeException('Nonce de evidência incompatível com a operação.');
        }
        if (strtotime((string)($payload['expires_at'] ?? '')) < time()) {
            throw new RuntimeException('Nonce de evidência expirado.');
        }

        return $payload;
    }
}
