<?php

require_once __DIR__ . '/../Models/PushSubscription.php';
require_once __DIR__ . '/PushVapidService.php';
require_once __DIR__ . '/TelegramNotificationService.php';
require_once __DIR__ . '/WhatsAppNotificationService.php';

final class PushNotificationService
{
    public static function wakeupEspecialista(int $usuarioId, array $contexto = []): int
    {
        $subscriptions = PushSubscription::listarPorUsuario($usuarioId, 'especialista');
        $sent = 0;
        foreach ($subscriptions as $subscription) {
            if (self::sendEmptyPush((string)$subscription['endpoint'])) {
                $sent++;
            }
        }

        if ($sent > 0) {
            Logger::event([
                'level' => Logger::LEVEL_INFO,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'PUSH',
                'phase' => 'wakeup',
                'code' => 'PUSH-010',
                'message' => 'Push de wakeup enviado ao especialista.',
                'context' => $contexto + ['usuario_id' => $usuarioId, 'subscriptions' => count($subscriptions), 'sent' => $sent],
            ]);
            return $sent;
        }

        $telegramSent = TelegramNotificationService::sendFallbackToEspecialista($usuarioId, $contexto);
        if ($telegramSent) {
            Logger::event([
                'level' => Logger::LEVEL_INFO,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'PUSH',
                'phase' => 'fallback',
                'code' => 'PUSH-011',
                'message' => 'Fallback Telegram acionado após falha de push.',
                'context' => $contexto + ['usuario_id' => $usuarioId, 'subscriptions' => count($subscriptions), 'sent' => 1, 'canal' => 'telegram'],
            ]);
            return 1;
        }

        $whatsAppSent = WhatsAppNotificationService::sendFallbackToEspecialista($usuarioId, $contexto);
        if ($whatsAppSent) {
            Logger::event([
                'level' => Logger::LEVEL_INFO,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'PUSH',
                'phase' => 'fallback',
                'code' => 'PUSH-013',
                'message' => 'Fallback WhatsApp acionado apos falha de push e Telegram.',
                'context' => $contexto + ['usuario_id' => $usuarioId, 'subscriptions' => count($subscriptions), 'sent' => 1, 'canal' => 'whatsapp_self_hosted'],
            ]);
            return 1;
        }

        Logger::event([
            'level' => Logger::LEVEL_WARN,
            'class' => __CLASS__,
            'function' => __FUNCTION__,
            'system' => 'PUSH',
            'phase' => 'fallback',
            'code' => 'PUSH-012',
            'message' => 'Nenhum canal de notificação conseguiu entregar o alerta.',
            'context' => $contexto + ['usuario_id' => $usuarioId, 'subscriptions' => count($subscriptions)],
        ]);

        return 0;
    }

    private static function sendEmptyPush(string $endpoint): bool
    {
        $headers = PushVapidService::authorizationHeaders($endpoint);
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'GuinchaFacil/1.0 (+https://guinchafacil.com.br)',
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return false;
        }

        if (in_array($status, [201, 202, 204], true)) {
            return true;
        }

        if (in_array($status, [404, 410], true)) {
            $hash = hash('sha256', $endpoint);
            PushSubscription::deleteByEndpointHash($hash);
            return false;
        }

        return false;
    }
}
