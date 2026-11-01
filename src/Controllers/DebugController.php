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
    public function seedPreCotacao(): void    {        if (!defined('APP_ENV') || !in_array(APP_ENV, ['local', 'development', 'testing'], true)) {            http_response_code(404);            return;        }        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));        $agora = time();        $_SESSION['pre_cotacao'] = [            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),            'criado_em' => date('c'),            'expira_em' => $agora + 900,            'lat_origem' => -22.9068,            'lng_origem' => -43.1729,            'lat_destino' => -22.9013,            'lng_destino' => -43.1765,            'tipo_problema' => 'pneu',            'categoria' => 'popular',            'service_code' => 'TOW_CAR',            'service_type_id' => 0,            'requires_destination' => true,            'endereco_origem' => 'Praça Mauá, 10, Rio de Janeiro, RJ',            'endereco_destino' => 'Rua Primeiro de Março, 100, Rio de Janeiro, RJ',            'destino' => 'Rua Primeiro de Março, 100, Rio de Janeiro, RJ',            'distancia_km' => 0.5,            'custo_estimado' => 149.90,            'valor' => 149.90,            'origem_tarifa' => 'precificacao_oficial',            'regra_id' => 1,            'regra_version' => 'local-seed',            'detalhamento' => [],            'fonte_km' => 'seed',            'zona_id' => null,        ];        header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/pre-cotacao?resultado=1', true, 302);    }    public function jslog(): void
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
