<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/DebugController.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/DebugMode.php';
require_once __DIR__ . '/../Services/Logger.php';

/**
 * Espelho no backend do erro JS capturado por public/assets/js/debug.js.
 *
 * Faz parte do modo de debug global: como o console do navegador não é
 * visível para quem (ou qual IA) está analisando os logs do servidor depois
 * do fato, esta rota grava erros JS não tratados nos mesmos logs
 * estruturados (app-YYYY-MM-DD.jsonl / app_logs) usados pelo resto do
 * sistema, com o mesmo formato sistema/classe/função/localização.
 *
 * Só aceita e grava quando `debug_mode_ativo` está ligado — em modo normal
 * de produção esta rota não grava nada, pra não virar um vetor de flood de
 * logs a partir do navegador de qualquer visitante.
 */
class DebugController
{
    public function jslog(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!DebugMode::ativo()) {
            // Silenciosamente noop: rota existe sempre, mas só grava algo
            // quando o admin ligou o modo de debug.
            echo json_encode(['ok' => true, 'gravado' => false]);
            return;
        }

        $raw = (string)file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => 'payload inválido']);
            return;
        }

        $nivel = strtoupper((string)($payload['nivel'] ?? 'ERROR'));
        $sistema = (string)($payload['sistema'] ?? 'js-frontend');
        $mensagem = substr((string)($payload['mensagem'] ?? ''), 0, 2000);
        $contexto = is_array($payload['contexto'] ?? null) ? $payload['contexto'] : [];
        $stack = substr((string)($payload['stack'] ?? ''), 0, 4000);
        $url = substr((string)($payload['url'] ?? ''), 0, 500);

        $usuario = $_SESSION['user'] ?? null;

        Logger::event([
            'level' => in_array($nivel, ['DEBUG', 'INFO', 'WARN', 'ERROR'], true) ? $nivel : Logger::LEVEL_ERROR,
            'class' => 'FrontendJS',
            'function' => $sistema,
            'system' => 'js-runtime',
            'phase' => 'jslog_mirror',
            'message' => $mensagem,
            'usuario_id' => is_array($usuario) ? ($usuario['id'] ?? null) : null,
            'context' => array_merge($contexto, [
                'url' => $url,
                'stack' => $stack,
            ]),
        ]);

        echo json_encode(['ok' => true, 'gravado' => true]);
    }
}
