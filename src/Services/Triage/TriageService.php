<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/Triage/TriageService.php
// ROADMAP socorro automotivo — Fundamento 3 (triagem do cliente).

require_once __DIR__ . '/TriageRuleEngine.php';
require_once __DIR__ . '/../../DTO/Triage/TriageRequest.php';
require_once __DIR__ . '/../../DTO/Triage/TriageResult.php';
require_once __DIR__ . '/../Logger.php';
require_once __DIR__ . '/../../Models/Catalog/ServiceType.php';

class TriageService
{
    private const TBL = 'triage_sessions';

    private TriageRuleEngine $engine;

    public function __construct(?TriageRuleEngine $engine = null)
    {
        $this->engine = $engine ?? new TriageRuleEngine();
    }

    /**
     * Avalia a triagem e persiste o resultado. Idempotente por
     * session_token (UNIQUE): reenviar o mesmo token com as mesmas
     * respostas apenas retorna a sessão já existente, sem duplicar.
     */
    public function avaliarEPersistir(string $sessionToken, TriageRequest $request, ?int $clienteId): array
    {
        $sessionToken = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionToken) ?? '', 0, 64);
        if ($sessionToken === '') {
            $sessionToken = bin2hex(random_bytes(16));
        }

        $pdo = getPDO();
        $existente = $this->buscarPorToken($sessionToken);
        if ($existente) {
            return $existente;
        }

        if (!in_array($request->symptomCode, TriageRuleEngine::SYMPTOMS, true)) {
            $request->symptomCode = 'NAO_SEI';
        }

        $resultado = $this->engine->avaliar($request);

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO " . self::TBL . "
                    (session_token, cliente_id, symptom_code, respostas_json, resultado,
                     recommended_service_code, alternative_service_codes_json, safety_risk,
                     explicacao, rule_version, created_at, completed_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
            );
            $stmt->execute([
                $sessionToken,
                $clienteId,
                $request->symptomCode,
                json_encode($request->respostas, JSON_UNESCAPED_UNICODE),
                $resultado->resultado,
                $resultado->recommendedServiceCode,
                json_encode($resultado->alternativeServiceCodes, JSON_UNESCAPED_UNICODE),
                $resultado->safetyRisk ? 1 : 0,
                mb_substr($resultado->explicacao, 0, 500),
                $resultado->ruleVersion,
            ]);
        } catch (\PDOException $e) {
            // Corrida com reenvio duplicado do mesmo token — não é erro real.
            Logger::exception('TriageService', 'avaliarEPersistir', 'triagem', $e,
                ['session_token' => $sessionToken, 'symptom_code' => $request->symptomCode]);
            $existente = $this->buscarPorToken($sessionToken);
            if ($existente) {
                return $existente;
            }
            throw $e;
        }

        Logger::log(Logger::LEVEL_INFO, 'TriageService', 'avaliarEPersistir', 'triagem',
            "Triagem concluída: {$request->symptomCode} -> {$resultado->resultado} ({$resultado->recommendedServiceCode})",
            [
                'session_token' => $sessionToken,
                'cliente_id' => $clienteId,
                'symptom_code' => $request->symptomCode,
                'resultado' => $resultado->resultado,
                'recommended_service_code' => $resultado->recommendedServiceCode,
                'safety_risk' => $resultado->safetyRisk,
                'rule_version' => $resultado->ruleVersion,
            ]);

        return $this->buscarPorToken($sessionToken);
    }

    public function buscarPorToken(string $sessionToken): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE session_token = ? LIMIT 1");
        $stmt->execute([$sessionToken]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['alternative_service_codes'] = json_decode((string)($row['alternative_service_codes_json'] ?? '[]'), true) ?: [];
        $row['respostas'] = json_decode((string)($row['respostas_json'] ?? '{}'), true) ?: [];
        return $row;
    }

    /** Resolve o service_type_id (catálogo, Etapa 1) recomendado por uma sessão, se ainda ativo. */
    public function resolverServiceTypeRecomendado(array $sessao): ?array
    {
        $code = $sessao['recommended_service_code'] ?? null;
        if (!$code) {
            return null;
        }
        $tipo = ServiceType::buscarPorCodigo((string)$code);
        return ($tipo && !empty($tipo['active'])) ? $tipo : null;
    }
}
