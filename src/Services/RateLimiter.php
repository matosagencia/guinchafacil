<?php
// File: guinchafacil/src/Services/RateLimiter.php

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
            error_log('[RateLimiter] checkLimit error: ' . $e->getMessage());
            return true;
        }

        if (!$r) {
            return true; // nenhum registro, primeira vez
        }

        // Está bloqueado por tempo?
        if (!empty($r['bloqueado_ate']) && strtotime($r['bloqueado_ate']) > time()) {
            return false;
        }

        // Janela de tempo expirou? → zera contagem
        $inicio = !empty($r['primeira_tentativa']) ? strtotime($r['primeira_tentativa']) : 0;
        if ($inicio && (time() - $inicio) > $windowSeconds) {
            try {
                $pdo->prepare(
                    "UPDATE rate_limit SET tentativas = 0, bloqueado_ate = NULL, primeira_tentativa = NOW() WHERE ip = ? AND rota = ?"
                )->execute([$ip, $rota]);
            } catch (\PDOException $e) {
                error_log('[RateLimiter] reset error: ' . $e->getMessage());
            }
            return true;
        }

        return ((int)$r['tentativas'] < $maxAttempts);
    }

    /**
     * Registra uma tentativa. Bloqueia se exceder maxAttempts.
     */
    public function recordAttempt(string $key, int $maxAttempts = 5, int $blockSeconds = 900): void
    {
        $ip   = $this->getIp();
        $rota = $key;
        $pdo  = $this->db();

        try {
            $stmt = $pdo->prepare(
                "SELECT id, tentativas FROM rate_limit WHERE ip = ? AND rota = ? LIMIT 1"
            );
            $stmt->execute([$ip, $rota]);
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($r) {
                $novas = (int)$r['tentativas'] + 1;
                $bloquear = $novas >= $maxAttempts
                    ? date('Y-m-d H:i:s', time() + $blockSeconds)
                    : null;

                $pdo->prepare(
                    "UPDATE rate_limit SET tentativas = ?, bloqueado_ate = ? WHERE id = ?"
                )->execute([$novas, $bloquear, (int)$r['id']]);
            } else {
                $pdo->prepare(
                    "INSERT INTO rate_limit (ip, rota, tentativas, primeira_tentativa, bloqueado_ate) VALUES (?, ?, 1, NOW(), NULL)"
                )->execute([$ip, $rota]);
            }
        } catch (\PDOException $e) {
            error_log('[RateLimiter] recordAttempt error: ' . $e->getMessage());
        }
    }

    private function getIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Pega só o primeiro IP se houver lista
        return trim(explode(',', $ip)[0]);
    }
}
