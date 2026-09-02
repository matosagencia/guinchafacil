<?php

declare(strict_types=1);

class ConfigSecurityService
{
    private const ENV_LOCAL = '.env.local';
    private const ENV_FILE = '.env';
    private const EXTERNAL_ENV_DIR = 'guinchafacil-secrets';

    public static function loadEnvFiles(string $root): void
    {
        $runtime = self::inspectRuntime($root);
        $filesToLoad = [];

        if ($runtime['env_local_present']) {
            $filesToLoad[] = $runtime['env_local_path'];
        }

        if ($runtime['dotenv_fallback_allowed'] && $runtime['env_present']) {
            $filesToLoad[] = $runtime['env_path'];
        }

        foreach ($filesToLoad as $envFile) {
            self::loadEnvFile($envFile);
        }
    }

    public static function validateEnvironment(array $env): void
    {
        $resolved = [];
        foreach ([
            'DB_HOST', 'DB_NAME', 'DB_USER', 'ENCRYPTION_KEY', 'APP_ENV', 'PAYMENT_GATEWAY_ACTIVE',
            'SIMULATION_ENABLED', 'SIMULATION_ADMIN_TOKEN', 'MP_ENV',
            'MP_ACCESS_TOKEN', 'MP_PUBLIC_KEY',
            'MP_ACCESS_TOKEN_SANDBOX', 'MP_PUBLIC_KEY_SANDBOX',
            'MP_ACCESS_TOKEN_PROD', 'MP_PUBLIC_KEY_PROD',
        ] as $key) {
            $resolved[$key] = self::resolveValue($env, $key);
        }

        $issues = self::evaluateManagedEnvironment($resolved);
        $appEnv = strtolower((string)($resolved['APP_ENV'] ?: 'production'));

        if (!empty($issues['critical']) && $appEnv !== 'development') {
            throw new RuntimeException(
                'Configuração obrigatória ausente ou insegura: ' . implode(', ', $issues['critical']) . '. Configure .env.local ou variáveis externas.'
            );
        }
    }

    public static function validateEnvironmentFiles(string $root, array $env): void
    {
        $runtime = self::inspectRuntime($root);
        $appEnv = strtolower((string)(self::resolveValue($env, 'APP_ENV') ?: $runtime['effective_app_env'] ?? 'production'));

        if (!$runtime['env_local_present'] && !$runtime['dotenv_fallback_allowed'] && $appEnv !== 'development') {
            throw new RuntimeException(
                'Arquivo .env.local ausente para ambiente não-desenvolvimento. Configure variáveis externas ou .env.local seguro.'
            );
        }
    }

    public static function inspectRuntime(string $root): array
    {
        $envLocalPath = self::resolveManagedEnvPath($root);
        $embeddedEnvLocalPath = $root . DIRECTORY_SEPARATOR . self::ENV_LOCAL;
        $envPath = $root . DIRECTORY_SEPARATOR . self::ENV_FILE;
        $envBackupPath = $root . DIRECTORY_SEPARATOR . '.env.backup';
        $processAppEnv = strtolower(trim((string)(getenv('APP_ENV') !== false ? getenv('APP_ENV') : ($_ENV['APP_ENV'] ?? ''))));
        $processAllowFallback = strtolower(trim((string)(getenv('ALLOW_INSECURE_DOTENV_FALLBACK') !== false ? getenv('ALLOW_INSECURE_DOTENV_FALLBACK') : ($_ENV['ALLOW_INSECURE_DOTENV_FALLBACK'] ?? 'false'))));
        $envLocalPreview = is_file($envLocalPath) ? self::parseEnvFile($envLocalPath) : [];
        $envPreview = is_file($envPath) ? self::parseEnvFile($envPath) : [];
        $effectiveAppEnv = strtolower((string)($envLocalPreview['APP_ENV'] ?? $processAppEnv ?: ($envPreview['APP_ENV'] ?? 'production')));
        $fallbackAllowed = $processAllowFallback === 'true' || $effectiveAppEnv === 'development';
        $exposedArtifacts = [];

        foreach ([$envPath, $envBackupPath] as $candidate) {
            if (is_file($candidate)) {
                $exposedArtifacts[] = basename($candidate);
            }
        }

        $filesDir = $root . DIRECTORY_SEPARATOR . 'files';
        if (is_dir($filesDir)) {
            foreach ((array)glob($filesDir . DIRECTORY_SEPARATOR . '*.zip') as $zipFile) {
                $exposedArtifacts[] = 'files/' . basename($zipFile);
            }
        }

        return [
            'env_local_path' => $envLocalPath,
            'embedded_env_local_path' => $embeddedEnvLocalPath,
            'env_path' => $envPath,
            'env_backup_path' => $envBackupPath,
            'env_local_present' => is_file($envLocalPath),
            'embedded_env_local_present' => is_file($embeddedEnvLocalPath),
            'env_local_external' => self::normalizePath($envLocalPath) !== self::normalizePath($embeddedEnvLocalPath),
            'env_present' => is_file($envPath),
            'env_backup_present' => is_file($envBackupPath),
            'dotenv_fallback_allowed' => $fallbackAllowed,
            'dotenv_fallback_used' => !$envLocalPreview && is_file($envPath) && $fallbackAllowed,
            'effective_app_env' => $effectiveAppEnv,
            'allow_insecure_fallback' => $processAllowFallback === 'true',
            'exposed_artifacts' => $exposedArtifacts,
        ];
    }

    public static function resolveManagedEnvPath(string $root): string
    {
        $configured = trim((string)(
            getenv('GUINCHAFACIL_ENV_FILE')
            ?: getenv('GF_ENV_FILE')
            ?: ($_ENV['GUINCHAFACIL_ENV_FILE'] ?? '')
            ?: ($_ENV['GF_ENV_FILE'] ?? '')
            ?: ''
        ));
        if ($configured !== '') {
            return self::normalizeCandidatePath($configured, $root);
        }

        foreach (self::managedEnvCandidates($root) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return self::managedEnvDefaultExternalPath($root);
    }

    public static function auditManagedEnvironment(string $root, array $env = []): array
    {
        $runtime = self::inspectRuntime($root);
        $envLocal = is_file($runtime['env_local_path']) ? self::parseEnvFile($runtime['env_local_path']) : [];
        $envLegacy = is_file($runtime['env_path']) ? self::parseEnvFile($runtime['env_path']) : [];
        $effective = array_merge($envLegacy, $envLocal);

        foreach ([
            'APP_ENV', 'APP_DEBUG', 'HTTPS_ONLY', 'ALLOW_INSECURE_DOTENV_FALLBACK',
            'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'ENCRYPTION_KEY',
            'PAYMENT_GATEWAY_ACTIVE', 'MP_ENV', 'MP_ACCESS_TOKEN', 'MP_PUBLIC_KEY',
            'MP_ACCESS_TOKEN_SANDBOX', 'MP_ACCESS_TOKEN_PROD',
            'MP_PUBLIC_KEY_SANDBOX', 'MP_PUBLIC_KEY_PROD',
            'COMPANY_WHATSAPP', 'SERPAPI_KEY', 'PROSPECCAO_URL_PRE_CADASTRO',
            'PROSPECCAO_OFERTA_RECIPROCIDADE', 'PROSPECCAO_CATEGORIAS_ALVO',
            'PROSPECCAO_QUOTA_ALVO_PADRAO', 'PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO',
            'PROSPECCAO_RAIO_PADRAO_KM',
            'SIMULATION_ENABLED', 'SIMULATION_ADMIN_TOKEN',
        ] as $key) {
            $processValue = self::resolveValue($env, $key);
            if ($processValue !== '') {
                $effective[$key] = $processValue;
            }
        }

        $evaluated = self::evaluateManagedEnvironment($effective);
        $warnings = $evaluated['warnings'];
        $infos = [];
        $recommendations = [];

        if ($runtime['env_local_present']) {
            $infos[] = $runtime['env_local_external']
                ? '.env.local externo presente'
                : '.env.local presente';
        } else {
            $warnings[] = '.env.local ausente no pacote atual';
            $recommendations[] = 'Criar .env.local seguro e mover as credenciais ativas para ele.';
        }

        if ($runtime['embedded_env_local_present'] && $runtime['env_local_external']) {
            $warnings[] = '.env.local ainda existe dentro do diretório publicado';
            $recommendations[] = 'Remover o .env.local residual do webroot após validar a cópia externa.';
        }

        if ($runtime['env_present']) {
            $warnings[] = '.env legado presente no deploy';
            $recommendations[] = 'Remover ou arquivar o .env legado fora do diretório publicado.';
        }

        if ($runtime['env_backup_present']) {
            $warnings[] = '.env.backup presente no deploy';
            $recommendations[] = 'Remover backups de ambiente do diretório publicado.';
        }

        if ($runtime['dotenv_fallback_allowed']) {
            $warnings[] = 'Fallback inseguro de .env está permitido';
            $recommendations[] = 'Desabilitar ALLOW_INSECURE_DOTENV_FALLBACK em produção.';
        }

        if ($runtime['dotenv_fallback_used']) {
            $evaluated['critical'][] = '.env legado está sendo usado como fonte efetiva de configuração';
        }

        if (!empty($runtime['exposed_artifacts'])) {
            $warnings[] = 'Artefatos legados detectados: ' . implode(', ', $runtime['exposed_artifacts']);
            $recommendations[] = 'Mover ZIPs e arquivos legados sensíveis para fora do diretório servido pelo Apache.';
        }

        return [
            'runtime' => $runtime,
            'critical' => array_values(array_unique($evaluated['critical'])),
            'warnings' => array_values(array_unique($warnings)),
            'infos' => array_values(array_unique($infos)),
            'recommendations' => array_values(array_unique($recommendations)),
            'effective_env' => $effective,
        ];
    }

    public static function validateManagedEnvMap(array $envMap): array
    {
        return self::evaluateManagedEnvironment($envMap);
    }

    public static function parseManagedEnvFile(string $path): array
    {
        return is_file($path) ? self::parseEnvFile($path) : [];
    }

    public static function formatManagedEnvValue(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#"\']/', $value) === 1 || str_contains($value, '\\')) {
            $escaped = str_replace(
                ["\\", "\"", "\n"],
                ["\\\\", "\\\"", "\\n"],
                $value
            );
            return '"' . $escaped . '"';
        }

        return $value;
    }

    private static function resolveValue(array $env, string $key): string
    {
        $processValue = getenv($key);
        if ($processValue !== false) {
            return trim((string)$processValue);
        }

        return trim((string)($env[$key] ?? $_ENV[$key] ?? ''));
    }

    private static function firstSecureValue(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '' && !self::isPlaceholder($value)) {
                return $value;
            }
        }

        return null;
    }

    private static function evaluateManagedEnvironment(array $resolved): array
    {
        $critical = [];
        $warnings = [];

        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'ENCRYPTION_KEY'];
        $appEnv = strtolower(trim((string)($resolved['APP_ENV'] ?? 'production')));
        $gateway = strtolower(trim((string)($resolved['PAYMENT_GATEWAY_ACTIVE'] ?? 'mercadopago')));
        $simulationEnabled = strtolower(trim((string)($resolved['SIMULATION_ENABLED'] ?? 'false'))) === 'true';

        if ($simulationEnabled) {
            $required[] = 'SIMULATION_ADMIN_TOKEN';
        }

        if ($gateway === 'mercadopago') {
            $required[] = 'MP_ENV';

            $mpEnv = strtolower(trim((string)($resolved['MP_ENV'] ?? 'production')));
            $effectiveToken = self::firstSecureValue([
                (string)($resolved['MP_ACCESS_TOKEN'] ?? ''),
                $mpEnv === 'sandbox'
                    ? (string)($resolved['MP_ACCESS_TOKEN_SANDBOX'] ?? '')
                    : (string)($resolved['MP_ACCESS_TOKEN_PROD'] ?? ''),
            ]);
            $effectivePublicKey = self::firstSecureValue([
                (string)($resolved['MP_PUBLIC_KEY'] ?? ''),
                $mpEnv === 'sandbox'
                    ? (string)($resolved['MP_PUBLIC_KEY_SANDBOX'] ?? '')
                    : (string)($resolved['MP_PUBLIC_KEY_PROD'] ?? ''),
            ]);

            if ($effectiveToken === null) {
                $required[] = $mpEnv === 'sandbox' ? 'MP_ACCESS_TOKEN_SANDBOX' : 'MP_ACCESS_TOKEN_PROD';
            }

            if ($effectivePublicKey === null) {
                $required[] = $mpEnv === 'sandbox' ? 'MP_PUBLIC_KEY_SANDBOX' : 'MP_PUBLIC_KEY_PROD';
            }
        }

        foreach (array_unique($required) as $key) {
            $value = trim((string)($resolved[$key] ?? ''));
            if ($value === '' || self::isPlaceholder($value)) {
                $critical[] = $key;
            }
        }

        if (!empty($resolved['ENCRYPTION_KEY']) && strlen(trim((string)$resolved['ENCRYPTION_KEY'])) < 24) {
            $critical[] = 'ENCRYPTION_KEY fraca (< 24 chars)';
        }

        if ($simulationEnabled && !empty($resolved['SIMULATION_ADMIN_TOKEN']) && strlen(trim((string)$resolved['SIMULATION_ADMIN_TOKEN'])) < 24) {
            $critical[] = 'SIMULATION_ADMIN_TOKEN fraco (< 24 chars)';
        }

        if ($appEnv === 'production' && strtolower(trim((string)($resolved['APP_DEBUG'] ?? 'false'))) === 'true') {
            $warnings[] = 'APP_DEBUG=true em produção';
        }

        if ($appEnv === 'production' && strtolower(trim((string)($resolved['HTTPS_ONLY'] ?? 'false'))) !== 'true') {
            $warnings[] = 'HTTPS_ONLY=false em produção';
        }

        return [
            'critical' => array_values(array_unique($critical)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private static function isPlaceholder(string $value): bool
    {
        return in_array(strtolower(trim($value)), [
            'change-me',
            'change-this-secret-key',
            'your-token-here',
            'example',
        ], true);
    }

    /**
     * §ENV-STALE-WORKER-01: sob Apache/mod_php os workers são processos
     * persistentes, reutilizados em várias requisições — e putenv() grava
     * no ambiente do PROCESSO, que sobrevive entre requests. Um antigo guard
     * aqui ("if getenv($key) !== false, continue") pulava a releitura do
     * arquivo sempre que a chave já tinha sido carregada por ESTE MESMO
     * loader numa requisição anterior do mesmo worker — ou seja, depois da
     * primeira requisição, aquele worker nunca mais via alterações feitas
     * via /admin/env (troca de gateway, credenciais, etc.) até o Apache
     * reiniciar. Confirmado na prática: Suite E (E2E-GOV-E2) trocava
     * PAYMENT_GATEWAY_ACTIVE para 'pagseguro' — persistido corretamente no
     * arquivo e confirmado via CLI (processo novo a cada chamada) — mas o
     * checkout servido pelo Apache continuava mostrando o painel do
     * MercadoPago, porque o worker que atendeu aquela requisição já tinha
     * carregado 'mercadopago' antes.
     *
     * Fix: sempre sobrescrever com o valor atual do arquivo a cada request.
     * O arquivo .env (gerenciado via ConfigSecurityService/AdminController)
     * é a única fonte de verdade destas chaves neste projeto — não há
     * injeção de env vars reais pelo SO/orquestrador a proteger aqui.
     */
    private static function loadEnvFile(string $envFile): void
    {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || !str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $trimmed, 2);
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $value = self::normalizeEnvValue($val);
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    private static function parseEnvFile(string $path): array
    {
        $result = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || !str_contains($trimmed, '=')) {
                continue;
            }
            [$key, $val] = explode('=', $trimmed, 2);
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $result[$key] = self::normalizeEnvValue($val);
        }

        return $result;
    }

    private static function normalizeEnvValue(string $value): string
    {
        $value = trim($value);
        $length = strlen($value);

        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return str_replace(
            ['\\n', '\\"', '\\\\'],
            ["\n", '"', '\\'],
            $value
        );
    }

    /** @return string[] */
    private static function managedEnvCandidates(string $root): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $parent = dirname($root);
        $grandParent = dirname($parent);

        return [
            $parent . DIRECTORY_SEPARATOR . self::EXTERNAL_ENV_DIR . DIRECTORY_SEPARATOR . self::ENV_LOCAL,
            $grandParent . DIRECTORY_SEPARATOR . self::EXTERNAL_ENV_DIR . DIRECTORY_SEPARATOR . self::ENV_LOCAL,
            $root . DIRECTORY_SEPARATOR . self::ENV_LOCAL,
        ];
    }

    private static function managedEnvDefaultExternalPath(string $root): string
    {
        $grandParent = dirname(dirname(rtrim($root, DIRECTORY_SEPARATOR)));
        return $grandParent . DIRECTORY_SEPARATOR . self::EXTERNAL_ENV_DIR . DIRECTORY_SEPARATOR . self::ENV_LOCAL;
    }

    private static function normalizeCandidatePath(string $path, string $root): string
    {
        $path = trim($path);
        if ($path === '') {
            return $root . DIRECTORY_SEPARATOR . self::ENV_LOCAL;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\')) {
            return $path;
        }

        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private static function normalizePath(string $path): string
    {
        return strtolower(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim($path, DIRECTORY_SEPARATOR)));
    }
}
