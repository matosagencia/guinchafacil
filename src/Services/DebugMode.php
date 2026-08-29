<?php

declare(strict_types=1);

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../Models/Configuracao.php';

/**
 * Modo de debug global (arquitetura de observabilidade).
 *
 * Chave única no admin (`configuracoes.debug_mode_ativo`) que, quando ligada,
 * ativa logging verboso cross-cutting em TODO o sistema — backend PHP e
 * frontend JS — mostrando sistema/classe/função em execução e localização
 * exata do problema. Objetivo explícito: acelerar o diagnóstico tanto por
 * humanos quanto por IA, sem precisar instrumentar manualmente cada bug.
 *
 * Uso no backend:
 *   DebugMode::trace('PedidoTransitionService', 'concludeManuallyByAdmin',
 *       'pedido', 'validando preconditions', ['pedido_id' => $id]);
 *
 * Ao contrário de Logger::log()/event() (que sempre grava INFO/ERROR/etc.),
 * DebugMode::trace() só grava quando o modo de debug está ligado — permite
 * espalhar pontos de rastreio verbosos pelo código sem poluir os logs em
 * produção normal.
 */
class DebugMode
{
    private static ?bool $ativoCache = null;

    public static function ativo(): bool
    {
        if (self::$ativoCache !== null) {
            return self::$ativoCache;
        }

        try {
            self::$ativoCache = Configuracao::get('debug_mode_ativo', '0') === '1';
        } catch (Throwable $e) {
            // Se a tabela/config ainda não existir (ex.: instalação em curso),
            // falha de forma segura como desligado, sem quebrar a request.
            self::$ativoCache = false;
        }

        return self::$ativoCache;
    }

    /**
     * Força um novo cheque no banco na próxima chamada a ativo() — útil
     * imediatamente após salvar a configuração, no mesmo request.
     */
    public static function resetCache(): void
    {
        self::$ativoCache = null;
    }

    /**
     * Rastro verbose condicional: só grava se o modo de debug estiver ativo.
     * Captura automaticamente arquivo/linha de quem chamou, para localização
     * exata do bug sem precisar informar manualmente.
     */
    public static function trace(string $cls, string $func, string $system, string $msg, array $ctx = []): void
    {
        if (!self::ativo()) {
            return;
        }

        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];

        Logger::event([
            'level' => Logger::LEVEL_DEBUG,
            'class' => $cls,
            'function' => $func,
            'system' => $system,
            'file' => (string)($caller['file'] ?? ''),
            'phase' => 'debug_trace',
            'message' => $msg,
            'context' => array_merge($ctx, [
                'linha' => (int)($caller['line'] ?? 0),
            ]),
        ]);
    }

    /**
     * Valor cru pronto pra embutir no HTML como flag JS
     * (`window.APP_DEBUG = <?= DebugMode::jsFlag() ?>;`).
     */
    public static function jsFlag(): string
    {
        return self::ativo() ? 'true' : 'false';
    }
}
