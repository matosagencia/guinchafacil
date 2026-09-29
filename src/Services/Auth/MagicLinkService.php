<?php

declare(strict_types=1);

/**
 * Magic link de login recorrente (sem senha).
 *
 * Fluxo:
 *   1. Cliente informa telefone OU email
 *   2. Geramos token aleatorio, salvamos hash em usuarios.magic_token_hash
 *   3. Enviamos link via WhatsApp (padrao), email (fallback) ou SMS (Brevo, se admin ligar)
 *   4. Cliente clica, consumimos o token e logamos
 *
 * Seguranca:
 *   - Token: 32 bytes random em hex (64 chars)
 *   - DB guarda SHA-256(token), nunca o token bruto
 *   - TTL: 15 minutos
 *   - Uso unico (limpa magic_token_hash apos validar)
 */
class MagicLinkService
{
    private const TTL_MINUTOS = 15;
    private const RATE_LIMIT_JANELA_SEG = 900; // 15 min
    private const RATE_LIMIT_MAX = 3;

    /**
     * Gera e envia um magic link. Retorna ['ok'=>bool, 'canal'=>string|null, 'erro'=>string|null].
     */
    public static function solicitar(string $identificador, ?string $retorno = null): array
    {
        $identificador = trim($identificador);
        if ($identificador === '') {
            return ['ok' => false, 'canal' => null, 'erro' => 'Informe telefone ou email.'];
        }

        // Normaliza: se tem @, e' email; senao, telefone
        $isEmail = strpos($identificador, '@') !== false;
        $telefone = null;
        $email = null;
        if ($isEmail) {
            $email = strtolower($identificador);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'canal' => null, 'erro' => 'Email invalido.'];
            }
        } else {
            $telefone = preg_replace('/\D+/', '', $identificador);
            if (strlen((string)$telefone) < 10 || strlen((string)$telefone) > 11) {
                return ['ok' => false, 'canal' => null, 'erro' => 'Telefone invalido.'];
            }
        }

        // Rate limit por identificador
        $rlKey = 'magic_link_' . md5((string)$identificador);
        $limiter = new RateLimiter();
        if (!$limiter->checkLimit($rlKey, self::RATE_LIMIT_MAX, self::RATE_LIMIT_JANELA_SEG)) {
            return ['ok' => false, 'canal' => null, 'erro' => 'Muitas solicitacoes. Aguarde alguns minutos.'];
        }

        // Procura usuario
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            $isEmail
                ? 'SELECT id, nome, email, telefone, tipo, ativo FROM usuarios WHERE LOWER(email) = ? LIMIT 1'
                : 'SELECT id, nome, email, telefone, tipo, ativo FROM usuarios WHERE telefone = ? LIMIT 1'
        );
        $stmt->execute([$isEmail ? $email : $telefone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Nao revela se existe ou nao (evita enumeracao). Retorna OK mesmo se nao existir.
        if (!$user || (int)$user['ativo'] !== 1) {
            error_log('[MagicLink] identificador nao encontrado ou inativo: ' . ($isEmail ? 'email' : 'tel'));
            return ['ok' => true, 'canal' => null, 'erro' => null]; // silencioso
        }

        // Gera token
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiraEm = date('Y-m-d H:i:s', time() + (self::TTL_MINUTOS * 60));

        // Decide canal: WhatsApp > email > SMS (admin)
        $smsHabilitado = Configuracao::get('sms_enabled', '0') === '1';
        $canal = self::escolherCanal($user, $smsHabilitado);
        if ($canal === null) {
            return ['ok' => false, 'canal' => null, 'erro' => 'Nao temos um canal disponivel para enviar o link. Tente outro telefone ou email.'];
        }

        // Salva hash (single-use)
        $pdo->prepare(
            'UPDATE usuarios SET magic_token_hash = ?, magic_token_expira_em = ?, magic_token_canal = ? WHERE id = ?'
        )->execute([$hash, $expiraEm, $canal, (int)$user['id']]);

        // Monta link
        $urlBase = rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/');
        if ($urlBase === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
            $urlBase = $scheme . '://' . $host;
        }
        $link = $urlBase . '/auth/magic/' . $token
            . ($retorno ? '?retorno=' . urlencode(AuthService::sanitizeReturnPath($retorno)) : '');

        // Envia
        $enviou = self::enviar($canal, $user, $link);
        if (!$enviou) {
            // Se falhou, limpa o token pra nao deixar hash orfao
            $pdo->prepare('UPDATE usuarios SET magic_token_hash = NULL, magic_token_expira_em = NULL, magic_token_canal = NULL WHERE id = ?')->execute([(int)$user['id']]);
            return ['ok' => false, 'canal' => $canal, 'erro' => 'Nao foi possivel enviar o link agora. Tente novamente em instantes.'];
        }

        $limiter->recordAttempt($rlKey, self::RATE_LIMIT_MAX, self::RATE_LIMIT_JANELA_SEG);
        return ['ok' => true, 'canal' => $canal, 'erro' => null];
    }

    /**
     * Valida o token e retorna o usuario (ou null se invalido/expirado).
     * Limpa o token apos uso.
     */
    public static function consumir(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $hash = hash('sha256', $token);

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, nome, email, telefone, tipo, ativo, magic_token_expira_em
               FROM usuarios
              WHERE magic_token_hash = ? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int)$user['ativo'] !== 1) return null;
        if (strtotime((string)$user['magic_token_expira_em']) < time()) {
            // Expirado: limpa
            $pdo->prepare('UPDATE usuarios SET magic_token_hash = NULL, magic_token_expira_em = NULL, magic_token_canal = NULL WHERE id = ?')->execute([(int)$user['id']]);
            return null;
        }

        // Uso unico
        $pdo->prepare(
            'UPDATE usuarios SET magic_token_hash = NULL, magic_token_expira_em = NULL, magic_token_canal = NULL, ultimo_login = NOW() WHERE id = ?'
        )->execute([(int)$user['id']]);

        // Reconsulta sem os campos de token
        $stmt = $pdo->prepare('SELECT id, nome, email, telefone, tipo, perfil_status FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$user['id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function escolherCanal(array $user, bool $smsHabilitado): ?string
    {
        $telefone = preg_replace('/\D+/', '', (string)($user['telefone'] ?? ''));
        if (strlen((string)$telefone) >= 10 && self::wapiConfigurado()) {
            return 'whatsapp';
        }
        if (!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if ($smsHabilitado && strlen((string)$telefone) >= 10 && self::brevoConfigurado()) {
            return 'sms';
        }
        return null;
    }

    private static function enviar(string $canal, array $user, string $link): bool
    {
        try {
            switch ($canal) {
                case 'whatsapp': return self::enviarWhatsApp($user, $link);
                case 'email':    return self::enviarEmail($user, $link);
                case 'sms':      return self::enviarSms($user, $link);
            }
        } catch (Throwable $e) {
            error_log('[MagicLink][enviar] ' . $e->getMessage());
        }
        return false;
    }

    private static function wapiConfigurado(): bool
    {
        return trim((string)getenv('WAPI_BASE_URL')) !== ''
            && trim((string)getenv('WAPI_INSTANCE_ID')) !== ''
            && trim((string)getenv('WAPI_TOKEN')) !== '';
    }

    private static function brevoConfigurado(): bool
    {
        return trim((string)(defined('BREVO_API_KEY') ? BREVO_API_KEY : '')) !== '';
    }

    private static function enviarWhatsApp(array $user, string $link): bool
    {
        $base = rtrim((string)getenv('WAPI_BASE_URL'), '/');
        $instance = (string)getenv('WAPI_INSTANCE_ID');
        $token = (string)getenv('WAPI_TOKEN');
        $timeout = (int)(getenv('WAPI_TIMEOUT_SECONDS') ?: 10);

        $numero = preg_replace('/\D+/', '', (string)$user['telefone']);
        if (strlen($numero) <= 11) $numero = '55' . $numero; // DDI Brasil

        $msg = "GuinchaFacil: seu link de acesso (valido 15 min):\n{$link}\n\nSe nao foi voce, ignore esta mensagem.";
        $url = "{$base}/message/text?instance_id={$instance}&access_token={$token}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['number' => $numero, 'text' => $msg]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $resp !== false && $code >= 200 && $code < 300;
    }

    private static function enviarEmail(array $user, string $link): bool
    {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return false;
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string)(defined('SMTP_HOST') ? SMTP_HOST : '');
            $mail->Port = (int)(defined('SMTP_PORT') ? SMTP_PORT : 587);
            $mail->SMTPAuth = true;
            $mail->Username = (string)(defined('SMTP_USER') ? SMTP_USER : '');
            $mail->Password = (string)(defined('SMTP_PASS') ? SMTP_PASS : '');
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string)(defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@guinchafacil.com.br'), (string)(defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'GuinchaFacil'));
            $mail->addAddress((string)$user['email'], (string)$user['nome']);
            $mail->Subject = 'Seu link de acesso ao GuinchaFacil';
            $mail->isHTML(true);
            $mail->Body = '<p>Ola, ' . htmlspecialchars((string)$user['nome'], ENT_QUOTES, 'UTF-8') . '.</p>'
                . '<p>Clique no link abaixo para entrar (valido por 15 minutos):</p>'
                . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Entrar no GuinchaFacil</a></p>'
                . '<p>Se nao foi voce, ignore esta mensagem.</p>';
            $mail->AltBody = "Acesse: {$link}";
            return $mail->send();
        } catch (Throwable $e) {
            error_log('[MagicLink][email] ' . $e->getMessage());
            return false;
        }
    }

    private static function enviarSms(array $user, string $link): bool
    {
        $key = (string)(defined('BREVO_API_KEY') ? BREVO_API_KEY : '');
        if ($key === '') return false;
        $sender = (string)(defined('BREVO_SMS_SENDER') ? BREVO_SMS_SENDER : 'GuinchaFacil');

        $numero = preg_replace('/\D+/', '', (string)$user['telefone']);
        if (strlen($numero) <= 11) $numero = '55' . $numero;

        $payload = json_encode([
            'sender' => $sender,
            'recipient' => $numero,
            'content' => "GuinchaFacil: acesso em {$link} (valido 15 min)",
            'type' => 'transactional',
        ]);

        $ch = curl_init('https://api.brevo.com/v3/transactionalSMS/sms');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $key,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $resp !== false && $code >= 200 && $code < 300;
    }
}