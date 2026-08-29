<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Models/Demanda.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/AuditTrailService.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/DebugMode.php';
require_once __DIR__ . '/Evidence/EvidenceService.php';
require_once __DIR__ . '/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/PaymentJobService.php';
require_once __DIR__ . '/EstornoService.php';
require_once __DIR__ . '/RequestIpResolver.php';

/**
 * DemandaService — núcleo de separação de deveres do GuinchaFácil.
 *
 * Regra suprema: um funcionário NUNCA executa uma ação sensível diretamente.
 * Ele só cria uma "demanda" (registro pendente). Um gerente (nunca o próprio
 * solicitante, nunca alguém que já decidiu essa mesma demanda) decide
 * aprovar ou rejeitar — só depois disso, e só dentro deste service, é que
 * a ação real acontece (delegando para os serviços já existentes:
 * PedidoTransitionService, PaymentJobService, EstornoService).
 *
 * Isso significa que:
 *   - um funcionário malicioso sozinho não tem NENHUM poder de execução —
 *     na pior hipótese, cria demandas absurdas que um gerente vai rejeitar
 *     (e a taxa de rejeição dele fica visível pro admin);
 *   - um gerente malicioso sozinho só consegue aprovar demandas de BAIXO
 *     valor (abaixo do limiar configurado em `demanda_valor_dupla_aprovacao`)
 *     — acima disso, dois gerentes DIFERENTES precisam concordar;
 *   - alteração de dados sensíveis (ex.: chave PIX de guincheiro) exige
 *     dupla aprovação sempre, independente de "valor", porque não tem valor
 *     monetário direto mas pode redirecionar repasses futuros;
 *   - toda decisão (aprovação OU rejeição) exige nota justificada do
 *     gerente — não existe "aprovar sem dizer por quê".
 */
class DemandaService
{
    /** Tipos de alteração de dados permitidos — nunca aceitar campo vindo de fora sem checar esta lista. */
    private const CAMPOS_ALTERACAO_PERMITIDOS = [
        'pedido.observacao_interna' => ['tabela' => 'pedidos', 'coluna' => 'observacao_interna', 'tipo' => 'string'],
        'guincho.chave_pix' => ['tabela' => 'guinchos', 'coluna' => 'chave_pix', 'tipo' => 'string'],
        'guincho.chave_pix_tipo' => ['tabela' => 'guinchos', 'coluna' => 'chave_pix_tipo', 'tipo' => 'string'],
    ];

    // ─── Criação (só funcionário) ──────────────────────────────────────────

    public static function criar(int $solicitanteId, string $tipo, array $dados): array
    {
        if (!in_array($tipo, Demanda::TIPOS, true)) {
            return ['ok' => false, 'erro' => 'Tipo de demanda inválido.'];
        }

        $solicitante = self::buscarUsuario($solicitanteId);
        if (!$solicitante || !in_array((string)$solicitante['tipo'], ['funcionario', 'admin'], true)) {
            // Defesa em profundidade: mesmo que a rota já exija perfil
            // 'funcionario', o service confere de novo — nunca confia só
            // no controller.
            return ['ok' => false, 'erro' => 'Apenas contas de funcionário podem criar demandas.'];
        }

        $justificativa = trim((string)($dados['justificativa'] ?? ''));
        $minChars = (int)Configuracao::get('demanda_justificativa_min_chars', '20');
        if (mb_strlen($justificativa) < $minChars) {
            return ['ok' => false, 'erro' => "Justificativa precisa ter pelo menos {$minChars} caracteres."];
        }

        $pedidoId = isset($dados['pedido_id']) ? (int)$dados['pedido_id'] : null;
        $guinchoId = isset($dados['guincho_id']) ? (int)$dados['guincho_id'] : null;
        $paymentJobId = isset($dados['payment_job_id']) ? (int)$dados['payment_job_id'] : null;
        $valorEnvolvido = isset($dados['valor_envolvido']) ? (float)$dados['valor_envolvido'] : null;

        $payload = [];
        $erroPayload = null;

        switch ($tipo) {
            case 'cancelamento':
                if (!$pedidoId) {
                    $erroPayload = 'Informe o pedido a cancelar.';
                }
                break;

            case 'conclusao_manual':
                if (!$pedidoId) {
                    $erroPayload = 'Informe o pedido a concluir manualmente.';
                    break;
                }
                $staged = self::estagiarComprovantes($pedidoId, $solicitanteId, $dados['comprovantes'] ?? []);
                if (!$staged['ok']) {
                    $erroPayload = $staged['erro'];
                    break;
                }
                $payload['comprovantes'] = $staged['comprovantes'];
                break;

            case 'pagamento':
                if (!$paymentJobId) {
                    $erroPayload = 'Informe o job de repasse a reprocessar.';
                }
                break;

            case 'reembolso':
                if (!$pedidoId) {
                    $erroPayload = 'Informe o pedido a estornar.';
                    break;
                }
                if ($valorEnvolvido !== null && $valorEnvolvido <= 0) {
                    $erroPayload = 'Valor de estorno inválido.';
                    break;
                }
                // Campo "valor_envolvido" em branco significa "estornar o
                // valor total" (ver rótulo no form) — não "sem valor". Se
                // não resolvermos isso pro valor real do pagamento agora, a
                // checagem de dupla aprovação logo abaixo (baseada em
                // $valorEnvolvido) nunca dispara pra reembolsos totais,
                // mesmo os de valor alto — furo de segurança: o caso mais
                // comum (estornar tudo) ficava justamente fora do controle
                // que deveria proteger valores altos.
                if ($valorEnvolvido === null) {
                    $stmtPg = getPDO()->prepare('SELECT valor_total FROM pagamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
                    $stmtPg->execute([$pedidoId]);
                    $valorPago = $stmtPg->fetchColumn();
                    if ($valorPago !== false) {
                        $valorEnvolvido = (float)$valorPago;
                    }
                }
                break;

            case 'alteracao_dados':
                $campo = (string)($dados['campo'] ?? '');
                if (!isset(self::CAMPOS_ALTERACAO_PERMITIDOS[$campo])) {
                    $erroPayload = 'Campo não permitido para alteração via demanda.';
                    break;
                }
                $valorNovo = trim((string)($dados['valor_novo'] ?? ''));
                if ($valorNovo === '') {
                    $erroPayload = 'Informe o novo valor.';
                    break;
                }
                if (strpos($campo, 'pedido.') === 0 && !$pedidoId) {
                    $erroPayload = 'Informe o pedido.';
                    break;
                }
                if (strpos($campo, 'guincho.') === 0 && !$guinchoId) {
                    $erroPayload = 'Informe o guincho.';
                    break;
                }
                $payload['campo'] = $campo;
                $payload['valor_novo'] = $valorNovo;
                break;
        }

        if ($erroPayload !== null) {
            return ['ok' => false, 'erro' => $erroPayload];
        }

        // alteracao_dados sempre exige dupla aprovação (não tem "valor" mas
        // pode redirecionar dinheiro futuro, ex.: chave PIX); pagamento e
        // reembolso exigem dupla aprovação a partir do limiar configurado.
        $limiar = (float)Configuracao::get('demanda_valor_dupla_aprovacao', '500.00');
        $requerDupla = $tipo === 'alteracao_dados'
            || ($valorEnvolvido !== null && $valorEnvolvido >= $limiar);

        // Idempotência: evita que um duplo-clique ou um retry de rede crie
        // duas demandas idênticas pro mesmo pedido/tipo no mesmo minuto.
        $hash = hash('sha256', implode('|', [
            $tipo, $solicitanteId, $pedidoId ?? 0, $guinchoId ?? 0, $paymentJobId ?? 0, date('Y-m-d H:i'),
        ]));

        try {
            $id = Demanda::criar([
                'tipo' => $tipo,
                'solicitante_id' => $solicitanteId,
                'pedido_id' => $pedidoId,
                'guincho_id' => $guinchoId,
                'payment_job_id' => $paymentJobId,
                'valor_envolvido' => $valorEnvolvido,
                'requer_dupla_aprovacao' => $requerDupla,
                'justificativa' => $justificativa,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'hash_idempotencia' => $hash,
                'ip' => RequestIpResolver::resolve(), // §IP-CANONICO-01
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uk_demanda_idempotencia')) {
                return ['ok' => false, 'erro' => 'Já existe uma demanda idêntica criada há poucos instantes.'];
            }
            Logger::exception('DemandaService', 'criar', 'demanda', $e, ['tipo' => $tipo]);
            return ['ok' => false, 'erro' => 'Erro interno ao criar demanda.'];
        }

        AuditTrailService::evento('demanda_criada', __CLASS__, __FUNCTION__, [
            'event_code' => 'DEM-NEW-001',
            'pedido_id' => $pedidoId,
            'usuario_id' => $solicitanteId,
            'demanda_id' => $id,
            'tipo' => $tipo,
            'requer_dupla_aprovacao' => $requerDupla,
        ]);

        return ['ok' => true, 'id' => $id];
    }

    // ─── Decisão (só gerente) ───────────────────────────────────────────────

    public static function decidir(int $demandaId, int $gerenteId, string $veredito, string $nota): array
    {
        if (!in_array($veredito, ['aprovar', 'rejeitar'], true)) {
            return ['ok' => false, 'erro' => 'Veredito inválido.'];
        }

        $gerente = self::buscarUsuario($gerenteId);
        if (!$gerente || !in_array((string)$gerente['tipo'], ['gerente', 'admin'], true)) {
            return ['ok' => false, 'erro' => 'Apenas gerentes podem decidir demandas.'];
        }

        $notaMin = (int)Configuracao::get('demanda_nota_gerente_min_chars', '10');
        if (mb_strlen(trim($nota)) < $notaMin) {
            return ['ok' => false, 'erro' => "A nota da decisão precisa ter pelo menos {$notaMin} caracteres — aprovar ou rejeitar sem justificar não é permitido."];
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $demanda = Demanda::buscarParaAtualizar($pdo, $demandaId);
            if (!$demanda) {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => 'Demanda não encontrada.'];
            }

            // Separação de deveres: o próprio solicitante nunca decide a
            // demanda dele, nem mesmo se por algum motivo tiver também
            // sessão/privilégio de gerente.
            if ((int)$demanda['solicitante_id'] === $gerenteId) {
                $pdo->rollBack();
                DebugMode::trace('DemandaService', 'decidir', 'demanda', 'bloqueado: gerente é o próprio solicitante', ['demanda_id' => $demandaId]);
                return ['ok' => false, 'erro' => 'Você não pode decidir uma demanda que você mesmo solicitou.'];
            }

            $status = (string)$demanda['status'];
            if (!in_array($status, [Demanda::STATUS_PENDENTE, Demanda::STATUS_APROVADA_PARCIAL], true)) {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => 'Esta demanda já foi decidida e não pode ser alterada.'];
            }

            if ($veredito === 'rejeitar') {
                Demanda::atualizar($pdo, $demandaId, [
                    'status' => Demanda::STATUS_REJEITADA,
                    'gerente_id' => $demanda['gerente_id'] ?? $gerenteId,
                    'decidido_em' => date('Y-m-d H:i:s'),
                    'nota_gerente' => $nota,
                ]);
                $pdo->commit();
                AuditTrailService::evento('demanda_rejeitada', __CLASS__, __FUNCTION__, [
                    'event_code' => 'DEM-REJ-001',
                    'pedido_id' => $demanda['pedido_id'],
                    'usuario_id' => $gerenteId,
                    'demanda_id' => $demandaId,
                ]);
                return ['ok' => true, 'status' => Demanda::STATUS_REJEITADA];
            }

            // veredito === 'aprovar'
            if ($status === Demanda::STATUS_PENDENTE) {
                if (!$demanda['requer_dupla_aprovacao']) {
                    Demanda::atualizar($pdo, $demandaId, [
                        'status' => Demanda::STATUS_APROVADA,
                        'gerente_id' => $gerenteId,
                        'decidido_em' => date('Y-m-d H:i:s'),
                        'nota_gerente' => $nota,
                    ]);
                    $pdo->commit();
                } else {
                    Demanda::atualizar($pdo, $demandaId, [
                        'status' => Demanda::STATUS_APROVADA_PARCIAL,
                        'gerente_id' => $gerenteId,
                        'decidido_em' => date('Y-m-d H:i:s'),
                        'nota_gerente' => $nota,
                    ]);
                    $pdo->commit();
                    AuditTrailService::evento('demanda_aprovacao_parcial', __CLASS__, __FUNCTION__, [
                        'event_code' => 'DEM-APV-001',
                        'pedido_id' => $demanda['pedido_id'],
                        'usuario_id' => $gerenteId,
                        'demanda_id' => $demandaId,
                    ]);
                    return ['ok' => true, 'status' => Demanda::STATUS_APROVADA_PARCIAL, 'mensagem' => 'Aprovação registrada — aguardando um segundo gerente (diferente de você) para liberar a execução.'];
                }
            } elseif ($status === Demanda::STATUS_APROVADA_PARCIAL) {
                // Segunda aprovação: precisa ser um gerente DIFERENTE do primeiro.
                if ((int)$demanda['gerente_id'] === $gerenteId) {
                    $pdo->rollBack();
                    return ['ok' => false, 'erro' => 'A segunda aprovação precisa ser de um gerente diferente do primeiro.'];
                }
                Demanda::atualizar($pdo, $demandaId, [
                    'status' => Demanda::STATUS_APROVADA,
                    'segundo_gerente_id' => $gerenteId,
                    'segundo_decidido_em' => date('Y-m-d H:i:s'),
                    'segunda_nota' => $nota,
                ]);
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception('DemandaService', 'decidir', 'demanda', $e, ['demanda_id' => $demandaId]);
            return ['ok' => false, 'erro' => 'Erro interno ao decidir demanda.'];
        }

        AuditTrailService::evento('demanda_aprovada', __CLASS__, __FUNCTION__, [
            'event_code' => 'DEM-APV-002',
            'usuario_id' => $gerenteId,
            'demanda_id' => $demandaId,
        ]);

        // Aprovação final (única ou segunda de duas) — executa de verdade agora.
        return self::executar($demandaId);
    }

    // ─── Execução real (só chamada internamente, após aprovação final) ─────

    private static function executar(int $demandaId): array
    {
        $demanda = Demanda::buscar($demandaId);
        if (!$demanda || $demanda['status'] !== Demanda::STATUS_APROVADA) {
            return ['ok' => false, 'erro' => 'Demanda não está pronta para execução.'];
        }

        $tipo = (string)$demanda['tipo'];
        $gerenteExecutor = (int)($demanda['segundo_gerente_id'] ?: $demanda['gerente_id']);
        $payload = json_decode((string)($demanda['payload_json'] ?? '{}'), true) ?: [];
        $resultado = ['ok' => false, 'erro' => 'Tipo de demanda sem execução implementada.'];

        try {
            switch ($tipo) {
                case 'cancelamento':
                    $r = PedidoTransitionService::cancelByAdmin((int)$demanda['pedido_id'], $gerenteExecutor, (string)$demanda['justificativa']);
                    $resultado = ['ok' => $r->ok, 'erro' => $r->error];
                    break;

                case 'conclusao_manual':
                    $comprovantes = self::montarComprovantesParaExecucao($payload['comprovantes'] ?? []);
                    $r = PedidoTransitionService::concludeManuallyByAdmin((int)$demanda['pedido_id'], $gerenteExecutor, (string)$demanda['justificativa'], $comprovantes);
                    $resultado = ['ok' => $r->ok, 'erro' => $r->error];
                    break;

                case 'pagamento':
                    $resultado = PaymentJobService::forceRetry((int)$demanda['payment_job_id'], $gerenteExecutor);
                    break;

                case 'reembolso':
                    // §ESTORNO-ARQUIVADO-01 (27/07/2026): reembolso manual via
                    // demanda é o ÚNICO chamador que passa incluirArquivado=true —
                    // decisão humana explícita e auditada (passou por
                    // aprovação, e acima do limiar por dupla aprovação),
                    // então pode alcançar um pagamento já arquivado por
                    // conversão pane->reboque (ver EstornoService::estornar()).
                    // Cancelamentos automáticos continuam com o default
                    // (false) — nunca reabrem sozinhos um serviço já prestado.
                    $r = EstornoService::estornar((int)$demanda['pedido_id'], $demanda['valor_envolvido'] !== null ? (float)$demanda['valor_envolvido'] : null, true);
                    $resultado = ['ok' => (bool)($r['sucesso'] ?? false), 'erro' => $r['erro'] ?? null];
                    break;

                case 'alteracao_dados':
                    $resultado = self::executarAlteracaoDados($demanda, $payload);
                    break;
            }
        } catch (Throwable $e) {
            Logger::exception('DemandaService', 'executar', 'demanda', $e, ['demanda_id' => $demandaId, 'tipo' => $tipo]);
            $resultado = ['ok' => false, 'erro' => 'Exceção ao executar: ' . $e->getMessage()];
        }

        $pdo = getPDO();
        if (!empty($resultado['ok'])) {
            Demanda::atualizar($pdo, $demandaId, [
                'status' => Demanda::STATUS_EXECUTADA,
                'executado_em' => date('Y-m-d H:i:s'),
            ]);
            AuditTrailService::evento('demanda_executada', __CLASS__, __FUNCTION__, [
                'event_code' => 'DEM-EXE-001',
                'pedido_id' => $demanda['pedido_id'],
                'usuario_id' => $gerenteExecutor,
                'demanda_id' => $demandaId,
                'tipo' => $tipo,
            ]);
        } else {
            Demanda::atualizar($pdo, $demandaId, [
                'status' => Demanda::STATUS_FALHOU,
                'erro_execucao' => substr((string)($resultado['erro'] ?? 'Falha desconhecida.'), 0, 1000),
            ]);
            AuditTrailService::evento('demanda_falhou', __CLASS__, __FUNCTION__, [
                'event_code' => 'DEM-EXE-002',
                'pedido_id' => $demanda['pedido_id'],
                'usuario_id' => $gerenteExecutor,
                'demanda_id' => $demandaId,
                'tipo' => $tipo,
                'erro' => $resultado['erro'] ?? null,
            ]);
        }

        return $resultado;
    }

    private static function executarAlteracaoDados(array $demanda, array $payload): array
    {
        $campo = (string)($payload['campo'] ?? '');
        $valorNovo = (string)($payload['valor_novo'] ?? '');
        if (!isset(self::CAMPOS_ALTERACAO_PERMITIDOS[$campo])) {
            return ['ok' => false, 'erro' => 'Campo não permitido (verificação de execução).'];
        }
        $def = self::CAMPOS_ALTERACAO_PERMITIDOS[$campo];
        $id = strpos($campo, 'pedido.') === 0 ? (int)$demanda['pedido_id'] : (int)$demanda['guincho_id'];
        if ($id <= 0) {
            return ['ok' => false, 'erro' => 'Registro alvo não identificado.'];
        }

        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE `{$def['tabela']}` SET `{$def['coluna']}` = ? WHERE id = ?");
        $stmt->execute([$valorNovo, $id]);
        return ['ok' => true, 'erro' => null];
    }

    // ─── Helpers de staging de comprovantes (conclusão manual) ─────────────

    /**
     * Move os comprovantes enviados pelo FUNCIONÁRIO (no momento da criação
     * da demanda) para a mesma pasta privada de evidências, mas ainda NADA
     * é aplicado ao pedido — só quando o gerente aprovar é que
     * concludeManuallyByAdmin() de fato lê esses arquivos e conclui.
     */
    private static function estagiarComprovantes(int $pedidoId, int $solicitanteId, array $comprovantes): array
    {
        $destDir = EvidenceService::privateStorageDir();
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0770, true);
        }

        $staged = [];
        foreach ($comprovantes as $c) {
            $tipo = (string)($c['tipo'] ?? '');
            $file = $c['file'] ?? null;
            if (!in_array($tipo, ['coleta', 'entrega'], true) || !is_array($file)) {
                continue;
            }
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return ['ok' => false, 'erro' => "Arquivo de comprovante ({$tipo}) ausente ou inválido."];
            }
            if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
                return ['ok' => false, 'erro' => 'Arquivo acima do limite de 5MB.'];
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file((string)$file['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($allowed[$mime])) {
                return ['ok' => false, 'erro' => 'Tipo MIME inválido para comprovante (use JPEG ou PNG).'];
            }

            $storedName = sprintf('staging_demanda_%s_%d_func%d_%s.%s', $tipo, $pedidoId, $solicitanteId, bin2hex(random_bytes(8)), $allowed[$mime]);
            $destPath = $destDir . DIRECTORY_SEPARATOR . $storedName;
            $moved = is_uploaded_file((string)$file['tmp_name'])
                ? move_uploaded_file((string)$file['tmp_name'], $destPath)
                : @rename((string)$file['tmp_name'], $destPath);
            if (!$moved) {
                return ['ok' => false, 'erro' => "Falha ao gravar comprovante ({$tipo})."];
            }

            $staged[] = [
                'tipo' => $tipo,
                'stored_path' => $destPath,
                'original_name' => (string)($file['name'] ?? $storedName),
            ];
        }

        if (empty($staged)) {
            return ['ok' => false, 'erro' => 'Envie ao menos um comprovante (coleta e/ou entrega).'];
        }

        return ['ok' => true, 'comprovantes' => $staged];
    }

    /** Reconstrói o formato $comprovante['file'] que concludeManuallyByAdmin() já sabe ler, a partir dos arquivos já estagiados. */
    private static function montarComprovantesParaExecucao(array $staged): array
    {
        $out = [];
        foreach ($staged as $s) {
            $path = (string)($s['stored_path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $out[] = [
                'tipo' => $s['tipo'],
                'file' => [
                    'tmp_name' => $path,
                    'name' => $s['original_name'] ?? basename($path),
                    'error' => UPLOAD_ERR_OK,
                    'size' => (int)@filesize($path),
                ],
            ];
        }
        return $out;
    }

    private static function buscarUsuario(int $id): ?array
    {
        $stmt = getPDO()->prepare('SELECT id, nome, tipo FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
