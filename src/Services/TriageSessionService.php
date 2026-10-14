<?php
declare(strict_types=1);

/**
 * Contrato de continuidade da cotação entre pré-cotação, autenticação e checkout.
 * O payload não é confiado ao navegador após ser finalizado: fica apenas na sessão.
 */
final class TriageSessionService
{
    public const SESSION_ID_KEY = 'triage_session_id';
    private const SESSIONS_KEY = 'triage_sessions';
    private const CHECKOUT_KEY = 'checkout_triagem';
    private const TTL_SECONDS = 1800;

    public static function finalizar(array $dadosCotacao): array
    {
        $dados = self::normalizar($dadosCotacao);
        $id = bin2hex(random_bytes(16));
        $agora = time();

        $_SESSION[self::SESSION_ID_KEY] = $id;
        $_SESSION[self::SESSIONS_KEY][$id] = [
            'id' => $id,
            'versao' => 1,
            'criado_em' => $agora,
            'expira_em' => $agora + self::TTL_SECONDS,
            'dados' => $dados,
        ];

        self::log('finalizar', 'TRIAGE-SAVE', 'Cotação persistida na sessão.', ['triage_session_id' => $id]);
        return $_SESSION[self::SESSIONS_KEY][$id];
    }

    public static function reconstruir(string $id): ?array
    {
        $id = trim($id);
        $atual = (string)($_SESSION[self::SESSION_ID_KEY] ?? '');
        $registro = $_SESSION[self::SESSIONS_KEY][$id] ?? null;

        if ($id === '' || !is_array($registro) || !hash_equals($atual, $id)) {
            self::log('reconstruir', 'TRIAGE-REJECT', 'ID de triagem ausente, inválido ou não pertence à sessão.', ['triage_session_id' => $id]);
            return null;
        }
        if ((int)($registro['expira_em'] ?? 0) <= time()) {
            unset($_SESSION[self::SESSIONS_KEY][$id], $_SESSION[self::SESSION_ID_KEY], $_SESSION[self::CHECKOUT_KEY]);
            self::log('reconstruir', 'TRIAGE-EXPIRED', 'Cotação expirada removida da sessão.', ['triage_session_id' => $id]);
            return null;
        }

        $_SESSION[self::CHECKOUT_KEY] = $registro;
        self::log('reconstruir', 'TRIAGE-RESTORE', 'Checkout reconstruído a partir da cotação.', ['triage_session_id' => $id]);
        return $registro;
    }

    public static function registrarPedido(string $id, int $usuarioId, int $pedidoId): void
    {
        if (!isset($_SESSION[self::SESSIONS_KEY][$id]) || $pedidoId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('Não foi possível vincular o pedido à triagem.');
        }
        $_SESSION[self::SESSIONS_KEY][$id]['usuario_id'] = $usuarioId;
        $_SESSION[self::SESSIONS_KEY][$id]['pedido_id'] = $pedidoId;
        $_SESSION[self::CHECKOUT_KEY] = $_SESSION[self::SESSIONS_KEY][$id];
        self::log('registrarPedido', 'TRIAGE-ORDER-LINK', 'Pedido vinculado à cotação.', ['triage_session_id' => $id, 'pedido_id' => $pedidoId]);
    }

    private static function normalizar(array $entrada): array
    {
        $origem = is_array($entrada['origem'] ?? null) ? $entrada['origem'] : [];
        $destino = is_array($entrada['destino'] ?? null) ? $entrada['destino'] : [];
        $dados = [
            'tipo_problema' => trim((string)($entrada['tipo_problema'] ?? $entrada['problema'] ?? 'outro')),
            'descricao_problema' => trim((string)($entrada['descricao_problema'] ?? $entrada['descricao'] ?? '')),
            'lat_origem' => self::numero($entrada['lat_origem'] ?? $origem['lat'] ?? null),
            'lng_origem' => self::numero($entrada['lng_origem'] ?? $origem['lng'] ?? null),
            'endereco_origem' => trim((string)($entrada['endereco_origem'] ?? $origem['endereco'] ?? '')),
            'lat_destino' => self::numero($entrada['lat_destino'] ?? $destino['lat'] ?? null),
            'lng_destino' => self::numero($entrada['lng_destino'] ?? $destino['lng'] ?? null),
            'endereco_destino' => trim((string)($entrada['endereco_destino'] ?? $destino['endereco'] ?? '')),
            'distancia_km' => self::numero($entrada['distancia_km'] ?? $entrada['distancia'] ?? null),
            'custo_estimado' => self::numero($entrada['custo_estimado'] ?? $entrada['valor_estimado'] ?? null),
        ];

        $tipos = ['eletrica', 'pneu', 'colisao', 'bateria', 'combustivel', 'outro'];
        if (!in_array($dados['tipo_problema'], $tipos, true)) $dados['tipo_problema'] = 'outro';
        foreach (['lat_origem', 'lng_origem', 'lat_destino', 'lng_destino', 'distancia_km', 'custo_estimado'] as $campo) {
            if ($dados[$campo] === null) throw new InvalidArgumentException("Campo de cotação inválido: {$campo}.");
        }
        if ($dados['endereco_origem'] === '' || $dados['endereco_destino'] === '' || $dados['distancia_km'] < 0 || $dados['custo_estimado'] < 0) {
            throw new InvalidArgumentException('Dados obrigatórios da cotação estão ausentes ou inválidos.');
        }
        return $dados;
    }

    private static function numero(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float)$valor : null;
    }

    private static function log(string $funcao, string $fase, string $mensagem, array $contexto): void
    {
        if (class_exists('Logger')) {
            Logger::log(Logger::LEVEL_INFO, 'TriageSessionService', $funcao, 'triage', "[{$fase}] {$mensagem}", $contexto);
            return;
        }
        error_log('[TriageSessionService][' . $fase . '] ' . $mensagem);
    }
}
