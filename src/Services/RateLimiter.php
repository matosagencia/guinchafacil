<?php
// File: guinchafacil/src/Services/RateLimiter.php

require_once __DIR__ . '/RequestIpResolver.php';
require_once __DIR__ . '/Logger.php';

/**
 * RateLimiter - controle de tentativas por IP/rota
 * Usa tabela rate_limit. Compatível com colunas: ip, rota, tentativas,
 * primeira_tentativa, bloqueado_ate, criado_em, atualizado_em
 */
class RateLimiter
{
    private ?\PDO $pdo = null;

    private function db(): \PDO
    {
        if (!$this->pdo) {
            $this->pdo = getPDO();
        }
        return $this->pdo;
    }

    /**
     * Verifica se a chave ainda está dentro do limite.
     * Retorna true se pode prosseguir, false se bloqueado.
     */
    public function checkLimit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $ip   = $this->getIp();
        $rota = $key;
        $pdo  = $this->db();

        try {
            $stmt = $pdo->prepare(
                "SELECT tentativas, bloqueado_ate, primeira_tentativa FROM rate_limit WHERE ip = ? AND rota = ? LIMIT 1"
            );
            $stmt->execute([$ip, $rota]);
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Tabela pode não existir ainda: permite a requisição
            Logger::exception('RateLimiter', 'checkLimit', 'rate_limit', $e, ['ip' => $ip, 'rota' => $rota]);
            return true;
        }

        if (!$r) {
            return true; // nenhum registro, primeira vez
        }

        // Está bloqueado por tempo?
        if (!empty($r['bloqueado_ate']) && strtotime($r['bloqueado_ate']) > time()) {
            return false;
        }

        // Janela de tempo expirou? → zera contagem. UPDATE guardado por
        // primeira_tentativa < limite (não só ip/rota): mesmo se duas
        // requisições concorrentes caírem aqui, ambas fazem o mesmo reset
        // idempotente (zera pra 0), não há dado perdido — o pior caso é um
        // reset redundante, nunca contagem inconsistente.
        $inicio = !empty($r['primeira_tentativa']) ? strtotime($r['primeira_tentativa']) : 0;
        if ($inicio && (time() - $inicio) > $windowSeconds) {
            try {
                $pdo->prepare(
                    "UPDATE rate_limit SET tentativas = 0, bloqueado_ate = NULL, primeira_tentativa = NOW()
                     WHERE ip = ? AND rota = ? AND primeira_tentativa = ?"
                )->execute([$ip, $rota, $r['primeira_tentativa']]);
            } catch (\PDOException $e) {
                Logger::exception('RateLimiter', 'checkLimit', 'rate_limit', $e, ['ip' => $ip, 'rota' => $rota, 'fase' => 'reset_janela']);
            }
            return true;
        }

        return ((int)$r['tentativas'] < $maxAttempts);
    }

    /**
     * Registra uma tentativa. Bloqueia se exceder maxAttempts.
     *
     * §RATE-ATOMIC-01: usa INSERT ... ON DUPLICATE KEY UPDATE sobre a
     * UNIQUE KEY uk_ip_rota (ip, rota) para incrementar `tentativas` de
     * forma atômica em uma única ida ao banco. Antes, o método fazia
     * SELECT seguido de INSERT/UPDATE separados: sob concorrência (duas
     * requisições simultâneas sem registro prévio), ambas liam "sem
     * registro" e tentavam INSERT; a segunda batia em violação de UNIQUE,
     * a exceção era só logada e a tentativa era descartada — perda
     * silenciosa de contagem, abrindo brecha pra furar o limite em rajada.
     * O cálculo de bloqueio é feito em um segundo UPDATE, condicionado por
     * WHERE (guardado por tentativas/estado atual), portanto idempotente e
     * sem risco de sobrescrever um bloqueio já mais recente.
     */
    public function recordAttempt(string $key, int $maxAttempts = 5, int $blockSeconds = 900): void
    {
        $ip   = $this->getIp();
        $rota = $key;
        $pdo  = $this->db();

        try {
            $pdo->prepare(
                "INSERT INTO rate_limit (ip, rota, tentativas, primeira_tentativa, bloqueado_ate)
                 VALUES (:ip, :rota, 1, NOW(), NULL)
                 ON DUPLICATE KEY UPDATE tentativas = tentativas + 1"
            )->execute([':ip' => $ip, ':rota' => $rota]);

            $pdo->prepare(
                "UPDATE rate_limit
                    SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL :block_seconds SECOND)
                  WHERE ip = :ip AND rota = :rota
                    AND tentativas >= :max_attempts
                    AND (bloqueado_ate IS NULL OR bloqueado_ate < NOW())"
            )->execute([
                ':ip' => $ip,
                ':rota' => $rota,
                ':max_attempts' => $maxAttempts,
                ':block_seconds' => $blockSeconds,
            ]);
        } catch (\PDOException $e) {
            Logger::exception('RateLimiter', 'recordAttempt', 'rate_limit', $e, [
                'ip' => $ip, 'rota' => $rota, 'max_attempts' => $maxAttempts, 'block_seconds' => $blockSeconds,
            ]);
        }
    }

    private function getIp(): string
    {
        // §IP-CANONICO-01: X-Forwarded-For é enviado pelo cliente e não é
        // confiável sem um proxy reconhecido na frente — ver RequestIpResolver.
        return RequestIpResolver::resolve();
    }
}
