<?php

require_once __DIR__ . '/../Models/Usuario.php';

final class WhatsAppNotificationService
{
    public static function sendFallbackToEspecialista(int $usuarioId, array $contexto = []): bool
    {
        $endpoint = self::endpoint();
        $phone = self::resolvePhone($usuarioId);
        if ($endpoint === '' || $phone === '') {
            return false;
        }

        $usuario = Usuario::buscarPorId($usuarioId) ?: [];
        $payload = [
            'to' => $phone,
            'phone' => $phone,
            'message' => self::buildMessage($usuario, $contexto),
            'context' => [
                'usuario_id' => $usuarioId,
                'atendimento_id' => (int)($contexto['atendimento_id'] ?? 0),
                'incidente_id' => (int)($contexto['incidente_id'] ?? 0),
                'especialista_id' => (int)($contexto['especialista_id'] ?? 0),
                'servico_codigo' => (string)($contexto['servico_codigo'] ?? ''),
                'servico_nome' => (string)($contexto['servico_nome'] ?? ''),
                'endereco_origem' => (string)($contexto['endereco_origem'] ?? ''),
            ],
        ];

        $ok = self::postJson($endpoint, $payload);
        if ($ok) {
            Logger::event([
                'level' => Logger::LEVEL_INFO,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'WHATSAPP',
                'phase' => 'fallback',
                'code' => 'WA-010',
                'message' => 'Fallback WhatsApp enviado ao especialista.',
                'context' => $contexto + [
                    'usuario_id' => $usuarioId,
                    'phone' => $phone,
                    'canal' => 'whatsapp_self_hosted',
                ],
            ]);
        }

        return $ok;
    }

    private static function buildMessage(array $usuario, array $contexto): string
    {
        $nome = trim((string)($usuario['nome'] ?? 'Especialista'));
        $atendimentoId = (int)($contexto['atendimento_id'] ?? 0);
        $incidenteId = (int)($contexto['incidente_id'] ?? 0);
        $servico = trim((string)($contexto['servico_nome'] ?? $contexto['servico_codigo'] ?? 'Chamado'));
        $origem = trim((string)($contexto['endereco_origem'] ?? ''));
        $appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : 'https://guinchafacil.com.br'), '/');

        $linhas = [
            'GuinchoFacil',
            'Ola, ' . $nome . '.',
            'Ha um chamado disponivel no seu raio de atendimento.',
        ];
        if ($atendimentoId > 0) {
            $linhas[] = 'Atendimento: #' . $atendimentoId;
        }
        if ($incidenteId > 0) {
            $linhas[] = 'Incidente: #' . $incidenteId;
        }
        if ($servico !== '') {
            $linhas[] = 'Servico: ' . $servico;
        }
        if ($origem !== '') {
            $linhas[] = 'Origem: ' . $origem;
        }
        $linhas[] = 'Abra o painel: ' . $appUrl . '/especialista/dashboard';
        return implode("\n", $linhas);
    }

    private static function resolvePhone(int $usuarioId): string
    {
        $usuario = Usuario::buscarPorId($usuarioId);
        $phone = preg_replace('/\D+/', '', (string)($usuario['telefone'] ?? ''));
        if ($phone !== '') {
            return $phone;
        }

        $fallback = self::envValue('WHATSAPP_FALLBACK_TO', '');
        if ($fallback !== '') {
            return preg_replace('/\D+/', '', $fallback);
        }

        return self::envValue('WHATSAPP_DEFAULT_TO', '');
    }

    private static function endpoint(): string
    {
        return self::envValue('WHATSAPP_FALLBACK_URL', '');
    }

    private static function postJson(string $url, array $payload): bool
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        $headers = ['Content-Type: application/json'];
        $token = self::envValue('WHATSAPP_FALLBACK_TOKEN', '');
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper(self::envValue('WHATSAPP_FALLBACK_METHOD', 'POST')),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'GuinchaFacil/1.0 (+https://guinchafacil.com.br)',
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            return false;
        }

        if ($status < 200 || $status >= 300) {
            return false;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return true;
        }

        return !isset($decoded['ok']) || (bool)$decoded['ok'];
    }

    private static function envValue(string $key, string $fallback = ''): string
    {
        if (function_exists('env')) {
            return trim((string)env($key, $fallback));
        }

        $value = getenv($key);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }

        return $fallback;
    }
}
