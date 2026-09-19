<?php

require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/PushActionTokenService.php';

final class TelegramNotificationService
{
    public static function sendFallbackToEspecialista(int $usuarioId, array $contexto = []): bool
    {
        $botToken = self::botToken();
        $chatId = self::resolveChatId($usuarioId);
        if ($botToken === '' || $chatId === '') {
            return false;
        }

        $usuario = Usuario::buscarPorId($usuarioId) ?: [];
        $texto = self::buildMessage($usuario, $contexto);
        $replyMarkup = self::buildReplyMarkup($usuarioId, $contexto);

        $payload = [
            'chat_id' => $chatId,
            'text' => $texto,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($replyMarkup !== []) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $ok = self::postJson('https://api.telegram.org/bot' . $botToken . '/sendMessage', $payload);
        if ($ok) {
            Logger::event([
                'level' => Logger::LEVEL_INFO,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'TELEGRAM',
                'phase' => 'fallback',
                'code' => 'TG-010',
                'message' => 'Fallback Telegram enviado ao especialista.',
                'context' => $contexto + [
                    'usuario_id' => $usuarioId,
                    'chat_id' => $chatId,
                    'canal' => 'telegram',
                ],
            ]);
        }

        return $ok;
    }

    private static function buildMessage(array $usuario, array $contexto): string
    {
        $nome = htmlspecialchars((string)($usuario['nome'] ?? 'Especialista'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $atendimentoId = (int)($contexto['atendimento_id'] ?? 0);
        $incidenteId = (int)($contexto['incidente_id'] ?? 0);
        $servico = htmlspecialchars((string)($contexto['servico_nome'] ?? $contexto['servico_codigo'] ?? 'Chamado'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $origem = htmlspecialchars((string)($contexto['endereco_origem'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $appUrl = rtrim((string)(defined('APP_URL') ? APP_URL : 'https://guinchafacil.com.br'), '/');
        $painelUrl = $appUrl . '/especialista/dashboard';

        $linhas = [
            '<b>GuinchoFacil</b>',
            'Ola, <b>' . $nome . '</b>.',
            'Ha um chamado disponivel no seu raio de atendimento.',
        ];
        if ($atendimentoId > 0) {
            $linhas[] = 'Atendimento: <b>#' . $atendimentoId . '</b>';
        }
        if ($incidenteId > 0) {
            $linhas[] = 'Incidente: <b>#' . $incidenteId . '</b>';
        }
        if ($servico !== '') {
            $linhas[] = 'Servico: <b>' . $servico . '</b>';
        }
        if ($origem !== '') {
            $linhas[] = 'Origem: ' . $origem;
        }
        $linhas[] = 'Abra o painel para decidir imediatamente: ' . $painelUrl;
        return implode("\n", $linhas);
    }

    private static function buildReplyMarkup(int $usuarioId, array $contexto): array
    {
        $atendimentoId = (int)($contexto['atendimento_id'] ?? 0);
        $especialistaId = (int)($contexto['especialista_id'] ?? 0);
        if ($atendimentoId <= 0 || $especialistaId <= 0) {
            return [];
        }

        $acceptToken = PushActionTokenService::gerar($usuarioId, $especialistaId, $atendimentoId, 'aceitar');
        $declineToken = PushActionTokenService::gerar($usuarioId, $especialistaId, $atendimentoId, 'recusar');
        $baseUrl = rtrim((string)(defined('APP_URL') ? APP_URL : 'https://guinchafacil.com.br'), '/');

        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Aceitar',
                        'url' => $baseUrl . '/push/acao/aceitar/' . $atendimentoId . '?token=' . rawurlencode($acceptToken),
                    ],
                    [
                        'text' => 'Recusar',
                        'url' => $baseUrl . '/push/acao/recusar/' . $atendimentoId . '?token=' . rawurlencode($declineToken),
                    ],
                ],
            ],
        ];
    }

    private static function postJson(string $url, array $payload): bool
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
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
        return is_array($decoded) && !empty($decoded['ok']);
    }

    private static function resolveChatId(int $usuarioId): string
    {
        $usuario = Usuario::buscarPorId($usuarioId);
        $chatId = trim((string)($usuario['telegram_chat_id'] ?? ''));
        if ($chatId !== '') {
            return $chatId;
        }

        $fallback = self::envValue('TELEGRAM_CHAT_ID', '');
        if ($fallback !== '') {
            return $fallback;
        }

        return self::envValue('TELEGRAM_DEFAULT_CHAT_ID', '');
    }

    private static function botToken(): string
    {
        $token = self::envValue('TELEGRAM_BOT_TOKEN', '');
        if ($token !== '') {
            return $token;
        }

        return self::envValue('TELEGRAM_TOKEN', '');
    }

    private static function envValue(string $key, string $fallback = ''): string
    {
        if (function_exists('env')) {
            $value = (string)env($key, $fallback);
            return trim($value);
        }

        $value = getenv($key);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }

        return $fallback;
    }
}
