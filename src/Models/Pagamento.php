<?php

declare(strict_types=1);

class Pagamento
{
    private static function logErr(string $method, string $phase, string $message, array $ctx = []): void
    {
        $line = '[Pagamento][' . $method . '][' . $phase . '] ' . $message;
        if (!empty($ctx)) {
            $line .= ' | ctx=' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log($line);
    }

    /**
     * §GATEWAY-STATUS-01: decodifica webhook_payload (o corpo bruto que o
     * MercadoPago ou o PagSeguro devolveram no momento da aprovação —
     * gravado por Pagamento::aprovar()) e extrai um resumo legível de como o
     * gateway realmente processou o pagamento (status, motivo, forma de
     * pagamento). Existe pra dar transparência no painel financeiro sobre o
     * que aconteceu do lado do gateway, sem exigir que o admin abra o
     * dashboard do MP/PagSeguro pra entender uma cobrança.
     *
     * Resiliente de propósito: os dois gateways têm formatos de payload
     * bem diferentes (MP é o objeto de pagamento da API v1; PagSeguro pode
     * vir como XML já convertido em array ou como JSON da Orders API,
     * dependendo do fluxo que gerou o registro — legado Checkout Pro/XML ou
     * transparente novo). Quando o formato não bate com nenhum padrão
     * conhecido, cai num fallback que ainda mostra algo útil em vez de
     * quebrar a tela.
     *
     * @return array{gateway: string, status: string, detalhe: string, forma_pagamento: string, bruto: ?array}
     */
    public static function statusGatewayResumo(array $pagamento): array
    {
        $metodo = strtolower(trim((string)($pagamento['metodo'] ?? '')));
        $vazio = [
            'gateway' => $metodo !== '' ? $metodo : 'desconhecido',
            'status' => '',
            'detalhe' => '',
            'forma_pagamento' => '',
            'bruto' => null,
        ];

        $vazio['bandeira'] = '';

        $payloadRaw = trim((string)($pagamento['webhook_payload'] ?? ''));
        if ($payloadRaw === '') {
            return $vazio;
        }

        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            // Não é JSON (payload legado em outro formato) — ainda assim
            // mostra os primeiros caracteres pra não ficar em branco.
            $vazio['detalhe'] = mb_substr($payloadRaw, 0, 200);
            return $vazio;
        }

        // Formato MercadoPago (API v1 payments): status, status_detail,
        // payment_type_id ('pix', 'credit_card', 'debit_card', 'ticket'...),
        // payment_method_id é a BANDEIRA/meio específico ('visa', 'master',
        // 'amex', 'elo', 'pix', 'bolbradesco'/'pec' pra boleto...).
        if (isset($decoded['status']) && (isset($decoded['payment_type_id']) || isset($decoded['payment_method_id']) || $metodo === 'mercadopago')) {
            return [
                'gateway' => 'mercadopago',
                'status' => (string)($decoded['status'] ?? ''),
                'detalhe' => (string)($decoded['status_detail'] ?? ''),
                'forma_pagamento' => (string)($decoded['payment_type_id'] ?? $decoded['payment_method_id'] ?? ''),
                'bandeira' => (string)($decoded['payment_method_id'] ?? ''),
                'bruto' => $decoded,
            ];
        }

        // Formato PagSeguro (Orders/Transactions API — status numérico +
        // paymentMethod.type/code). 'type' = categoria (CREDIT_CARD, PIX,
        // BOLETO...), 'brand' = bandeira do cartão quando aplicável.
        if (isset($decoded['status']) && (isset($decoded['paymentMethod']) || isset($decoded['charges']) || $metodo === 'pagseguro')) {
            $forma = '';
            $bandeira = '';
            if (isset($decoded['paymentMethod']['type'])) {
                $forma = (string)$decoded['paymentMethod']['type'];
                $bandeira = (string)($decoded['paymentMethod']['brand'] ?? $decoded['paymentMethod']['card']['brand'] ?? '');
            } elseif (isset($decoded['charges'][0]['payment_method']['type'])) {
                $forma = (string)$decoded['charges'][0]['payment_method']['type'];
                $bandeira = (string)($decoded['charges'][0]['payment_method']['card']['brand'] ?? '');
            }
            return [
                'gateway' => 'pagseguro',
                'status' => (string)($decoded['status'] ?? ''),
                'detalhe' => (string)($decoded['status_detail'] ?? $decoded['reason'] ?? ''),
                'forma_pagamento' => $forma,
                'bandeira' => $bandeira,
                'bruto' => $decoded,
            ];
        }

        // Formato não reconhecido — devolve o array decodificado bruto pra
        // exibição em "ver detalhes", sem tentar adivinhar campos.
        $vazio['bruto'] = $decoded;
        return $vazio;
    }

    private static function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $pdo = getPDO();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$table})");
            $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $cache[$key] = in_array($column, array_column($cols, 'name'), true);
            return $cache[$key];
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        $cache[$key] = (int)$stmt->fetchColumn() > 0;
        return $cache[$key];
    }

    private static function paymentDateExpression(string $alias = ''): string
    {
        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        if (self::hasColumn('pagamentos', 'data_pagamento')) {
            return 'COALESCE(' . $prefix . 'data_pagamento, ' . $prefix . 'criado_em)';
        }
        return $prefix . 'criado_em';
    }

    /**
     * Soma aprovada hoje por gateway — usado por GatewayRotationService pra
     * decidir se o limite diário configurado pelo admin foi ultrapassado.
     * Usa a mesma expressão de data que os relatórios já usam
     * (paymentDateExpression: data_pagamento com fallback pra criado_em),
     * pra não divergir do que o admin já vê em outros lugares do sistema.
     */
    public static function totalAprovadoHojePorGateway(string $metodo): float
    {
        try {
            $dataExpr = self::paymentDateExpression();
            $stmt = getPDO()->prepare(
                "SELECT COALESCE(SUM(valor_total), 0)
                   FROM pagamentos
                  WHERE metodo = ?
                    AND status = 'aprovado'
                    AND DATE({$dataExpr}) = CURRENT_DATE"
            );
            $stmt->execute([$metodo]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            self::logErr('totalAprovadoHojePorGateway', 'query', $e->getMessage(), ['metodo' => $metodo]);
            return 0.0;
        }
    }

    public static function criar(int $pedido_id, string $metodo, float $valor_total, float $valor_guincho, float $valor_plataforma): int|false
    {
        try {
            $metodo = trim($metodo) !== '' ? trim($metodo) : 'freeflow';
            $sql = "INSERT INTO pagamentos (
                        pedido_id, metodo, valor_total, valor_guincho, valor_plataforma, status, criado_em
                    ) VALUES (
                        :pedido_id, :metodo, :valor_total, :valor_guincho, :valor_plataforma, 'pendente', NOW()
                    )";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([
                ':pedido_id' => $pedido_id,
                ':metodo' => $metodo,
                ':valor_total' => $valor_total,
                ':valor_guincho' => $valor_guincho,
                ':valor_plataforma' => $valor_plataforma,
            ]);

            return (int)getPDO()->lastInsertId();
        } catch (Throwable $e) {
            // §PAY-RETRY-01: `pagamentos` tem UNIQUE(pedido_id) — correto para nunca
            // ter dois pagamentos vivos pro mesmo pedido, mas isso também bloqueava
            // QUALQUER nova tentativa depois da primeira (voltar do checkout, trocar
            // de gateway, sessão cair no meio) com "não conseguimos registrar a
            // transação", mesmo a tentativa anterior nunca tendo sido aprovada —
            // achado real testando o sandbox MercadoPago (pedido travava pra sempre).
            // Em vez de falhar, reaproveita a linha existente quando ela ainda não
            // foi aprovada; se já foi aprovada, mantém o bloqueio (comportamento
            // antigo) — nunca sobrescreve um pagamento aprovado.
            if (self::isDuplicatePedidoError($e)) {
                return self::reiniciarTentativa($pedido_id, $metodo, $valor_total);
            }
            self::logErr(__FUNCTION__, 'insert', $e->getMessage(), ['pedido_id' => $pedido_id, 'metodo' => $metodo]);
            return false;
        }
    }

    /**
     * §HIBRIDO-COMPLEMENTAR-01 (27/07/2026): `pagamentos` tem UNIQUE(pedido_id)
     * e reiniciarTentativa() recusa de propósito reaproveitar a linha quando
     * o status já é 'aprovado' (guarda geral correta — nunca sobrescrever um
     * pagamento aprovado por engano/retry). Isso é certo para o caso comum,
     * mas descobri que também bloqueava por completo o único fluxo que
     * LEGITIMAMENTE precisa de uma segunda cobrança real pro mesmo pedido: a
     * conversão de socorro no local para reboque (ConversionService), tanto
     * no caminho híbrido quanto no não-híbrido. Sem este método, a cobrança
     * complementar nunca conseguia nem ser criada.
     *
     * Este método é o único ponto autorizado a "destravar" essa guarda: só
     * age quando existe de fato um pagamento aprovado anterior (a cobrança do
     * socorro no local), arquiva os dados completos dele em
     * `pagamentos_arquivados` (histórico permanente — nunca apagado) e SÓ
     * DEPOIS reseta a linha viva de `pagamentos` para 'pendente', liberando
     * reiniciarTentativa() para operar normalmente na cobrança nova. Devolve
     * os dados arquivados (não só um bool) porque o valor_total ali é a fonte
     * inequívoca do crédito de conversão — nunca mais uma query solta em
     * `pagamentos` filtrando só por status='aprovado', que não distinguia
     * "o pagamento do socorro" de qualquer outro estado possível da mesma
     * linha.
     *
     * @return array<string,mixed>|false Dados do pagamento arquivado (inclui
     *   valor_total original) ou false se não havia pagamento aprovado prévio
     *   — nesse caso o chamador NÃO deve prosseguir com a cobrança
     *   complementar (indica inconsistência: conversão sem cobrança inicial
     *   paga, que nunca deveria acontecer no fluxo normal).
     */
    public static function arquivarParaCobrancaComplementar(int $pedido_id, string $fase = 'socorro_local'): array|false
    {
        $pdo = getPDO();
        try {
            $stmt = $pdo->prepare('SELECT * FROM pagamentos WHERE pedido_id = :pedido_id LIMIT 1');
            $stmt->execute([':pedido_id' => $pedido_id]);
            $atual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$atual || (string)($atual['status'] ?? '') !== 'aprovado') {
                self::logErr(__FUNCTION__, 'blocked',
                    'Só é possível arquivar para cobrança complementar quando existe pagamento aprovado anterior para o pedido.',
                    ['pedido_id' => $pedido_id, 'status_atual' => $atual['status'] ?? null]);
                return false;
            }

            // §ARQUIVAMENTO-COMPLETO-01 (27/07/2026, achado em revisão de
            // código): a primeira versão deste método só copiava metodo/
            // valor_total/valor_guincho/valor_plataforma/status/id_externo/
            // webhook_payload/criado_em — faltavam id_transacao_pix (já
            // corrigido antes) e, apontado nesta revisão: status_pix,
            // pago_guincho, data_pagamento (data de APROVAÇÃO, não de
            // criação) e o próprio `pagamentos.id` original (necessário pra
            // relacionar de volta com payout_ledger_entries antigos, já que
            // a linha é REAPROVEITADA — o mesmo `pagamentos.id` passa a
            // valer pra cobrança complementar depois do arquivamento).
            // Lista construída dinamicamente (coluna só entra se existir
            // nas duas tabelas) pra não quebrar em ambientes/migrations
            // fora de sincronia.
            $mapaColunas = [
                'pagamento_id_original' => ['origem' => null, 'valor' => (int)$atual['id']],
                'id_transacao_pix' => ['origem' => 'id_transacao_pix', 'valor' => $atual['id_transacao_pix'] ?? null],
                'status_pix_original' => ['origem' => 'status_pix', 'valor' => $atual['status_pix'] ?? null],
                'pago_guincho_original' => ['origem' => 'pago_guincho', 'valor' => isset($atual['pago_guincho']) ? (int)$atual['pago_guincho'] : null],
                'data_pagamento_original' => ['origem' => 'data_pagamento', 'valor' => $atual['data_pagamento'] ?? null],
            ];
            $colunasExtra = [];
            $valoresExtra = [];
            foreach ($mapaColunas as $colunaArquivo => $def) {
                if (!self::hasColumn('pagamentos_arquivados', $colunaArquivo)) {
                    continue;
                }
                // 'pagamento_id_original' não tem coluna de origem em
                // `pagamentos` (é o próprio id da linha, sempre disponível);
                // as demais só entram se a coluna também existir na origem.
                if ($def['origem'] !== null && !self::hasColumn('pagamentos', $def['origem'])) {
                    continue;
                }
                $colunasExtra[] = $colunaArquivo;
                $valoresExtra[':' . $colunaArquivo] = $def['valor'];
            }

            $pdo->prepare(
                "INSERT INTO pagamentos_arquivados
                    (pedido_id, fase, metodo, valor_total, valor_guincho, valor_plataforma, status,
                     id_externo, webhook_payload, criado_em_original, arquivado_em"
                . (count($colunasExtra) ? ', ' . implode(', ', $colunasExtra) : '') . ")
                 VALUES (:pedido_id, :fase, :metodo, :valor_total, :valor_guincho, :valor_plataforma, :status,
                         :id_externo, :webhook_payload, :criado_em_original, NOW()"
                . (count($colunasExtra) ? ', ' . implode(', ', array_keys($valoresExtra)) : '') . ")"
            )->execute([
                ':pedido_id' => $pedido_id,
                ':fase' => $fase,
                ':metodo' => (string)$atual['metodo'],
                ':valor_total' => (float)$atual['valor_total'],
                ':valor_guincho' => (float)($atual['valor_guincho'] ?? 0),
                ':valor_plataforma' => (float)($atual['valor_plataforma'] ?? 0),
                ':status' => (string)$atual['status'],
                ':id_externo' => $atual['id_externo'] ?? null,
                ':webhook_payload' => $atual['webhook_payload'] ?? null,
                ':criado_em_original' => $atual['criado_em'] ?? null,
            ] + $valoresExtra);

            $sets = ["status = 'pendente'", 'valor_guincho = 0', 'valor_plataforma = 0'];
            if (self::hasColumn('pagamentos', 'id_externo')) {
                $sets[] = 'id_externo = NULL';
            }
            if (self::hasColumn('pagamentos', 'webhook_payload')) {
                $sets[] = 'webhook_payload = NULL';
            }
            if (self::hasColumn('pagamentos', 'status_pix')) {
                // NOT NULL DEFAULT 'pendente' no schema (MySQL e SQLite de
                // teste) — resetar pro estado inicial de uma cobrança nova,
                // não pra NULL (violaria a constraint).
                $sets[] = "status_pix = 'pendente'";
            }
            if (self::hasColumn('pagamentos', 'id_transacao_pix')) {
                $sets[] = 'id_transacao_pix = NULL';
            }
            if (self::hasColumn('pagamentos', 'pago_guincho')) {
                $sets[] = 'pago_guincho = 0';
            }
            if (self::hasColumn('pagamentos', 'data_pagamento')) {
                $sets[] = 'data_pagamento = NULL';
            }
            $pdo->prepare('UPDATE pagamentos SET ' . implode(', ', $sets) . ' WHERE pedido_id = :pedido_id')
                ->execute([':pedido_id' => $pedido_id]);

            self::logErr(__FUNCTION__, 'archived',
                'Pagamento anterior arquivado; linha de pagamentos resetada para nova cobrança complementar.',
                ['pedido_id' => $pedido_id, 'fase' => $fase, 'valor_arquivado' => (float)$atual['valor_total']]);

            return $atual;
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'error', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return false;
        }
    }

    /** Lê o pagamento arquivado de uma fase específica (mais recente primeiro) — fonte de verdade do valor pago naquela fase, imune a ser sobrescrito por cobranças seguintes do mesmo pedido. */
    public static function buscarPagamentoArquivado(int $pedido_id, string $fase = 'socorro_local'): ?array
    {
        $stmt = getPDO()->prepare(
            'SELECT * FROM pagamentos_arquivados WHERE pedido_id = :pedido_id AND fase = :fase ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':pedido_id' => $pedido_id, ':fase' => $fase]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * §WEBHOOK-ARQUIVADO-01 (27/07/2026, achado em revisão): webhooks de
     * gateway não são garantidos entregues uma única vez nem em ordem — um
     * reenvio/atraso do webhook do pagamento ORIGINAL (socorro no local,
     * já arquivado por arquivarParaCobrancaComplementar()) podia chegar
     * DEPOIS que a linha viva de `pagamentos` já foi resetada e reaproveitada
     * para a cobrança complementar do reboque. A checagem de idempotência
     * de webhook (Pagamento::buscarPorIdExterno) só olhava a tabela viva —
     * não encontrando o id_externo antigo lá (porque foi limpo no reset),
     * o webhook atrasado passava pela idempotência e acabava aprovando a
     * linha viva (a cobrança COMPLEMENTAR) usando o payload/id_externo do
     * pagamento ANTIGO, sem o cliente ter pago o complementar de fato.
     * Este método fecha essa lacuna: o controller de webhook deve checar
     * aqui ANTES de prosseguir — se o id_externo já existe em
     * `pagamentos_arquivados`, é garantidamente um evento de um ciclo de
     * cobrança já encerrado (arquivado), então deve ser ignorado como
     * duplicata, nunca usado para aprovar o que estiver vivo agora.
     */
    public static function buscarArquivadoPorIdExterno(string $id_externo): ?array
    {
        try {
            $stmt = getPDO()->prepare('SELECT * FROM pagamentos_arquivados WHERE id_externo = :id_externo LIMIT 1');
            $stmt->execute([':id_externo' => $id_externo]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['id_externo' => $id_externo]);
            return null;
        }
    }

    private static function isDuplicatePedidoError(Throwable $e): bool
    {
        if (!($e instanceof PDOException)) {
            return false;
        }
        $sqlState = (string)($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        // MySQL/MariaDB: 23000 + 1062. SQLite (testes): 23000 + mensagem "UNIQUE".
        $isIntegrity = $sqlState === '23000' || stripos($e->getMessage(), 'unique') !== false;
        $isDupe = $driverCode === 1062 || stripos($e->getMessage(), 'unique') !== false;
        return $isIntegrity && $isDupe && stripos($e->getMessage(), 'pedido_id') !== false;
    }

    /**
     * Reaproveita a linha de `pagamentos` já existente para o pedido (tentativa
     * anterior abandonada ou com falha), em vez de deixar o pedido travado
     * permanentemente por causa do UNIQUE(pedido_id). Nunca mexe em pagamento
     * já aprovado.
     */
    private static function reiniciarTentativa(int $pedido_id, string $metodo, float $valor_total): int|false
    {
        try {
            $stmt = getPDO()->prepare('SELECT id, status FROM pagamentos WHERE pedido_id = :pedido_id LIMIT 1');
            $stmt->execute([':pedido_id' => $pedido_id]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existente) {
                return false;
            }
            if (($existente['status'] ?? '') === 'aprovado') {
                self::logErr('reiniciarTentativa', 'blocked', 'Pedido já tem pagamento aprovado; nova tentativa recusada.', ['pedido_id' => $pedido_id]);
                return false;
            }

            $sets = [
                'metodo = :metodo',
                'valor_total = :valor_total',
                'valor_guincho = 0',
                'valor_plataforma = 0',
                "status = 'pendente'",
            ];
            $params = [
                ':metodo' => $metodo,
                ':valor_total' => $valor_total,
                ':id' => $existente['id'],
            ];
            if (self::hasColumn('pagamentos', 'id_externo')) {
                $sets[] = 'id_externo = NULL';
            }
            if (self::hasColumn('pagamentos', 'webhook_payload')) {
                $sets[] = 'webhook_payload = NULL';
            }
            if (self::hasColumn('pagamentos', 'status_pix')) {
                // §STATUS-PIX-NOT-NULL-01 (27/07/2026, achado ao implementar
                // arquivarParaCobrancaComplementar): status_pix é
                // ENUM(...) NOT NULL DEFAULT 'pendente' em produção (e no
                // schema espelho do SQLite de testes) — 'status_pix = NULL'
                // violava a constraint sempre que este caminho fosse
                // executado. Não pegou em nenhum teste existente porque
                // nenhum cobria reiniciarTentativa() até agora. Reset pro
                // valor inicial válido, não NULL.
                $sets[] = "status_pix = 'pendente'";
            }

            $sql = 'UPDATE pagamentos SET ' . implode(', ', $sets) . ' WHERE id = :id AND status != \'aprovado\'';
            $upd = getPDO()->prepare($sql);
            $upd->execute($params);

            // Não confia em rowCount(): por padrão o MySQL/PDO só conta linha como
            // afetada quando algum valor muda de fato — uma retentativa idêntica à
            // anterior (mesmo método/valor, já 'pendente') executa com sucesso mas
            // rowCount() vem 0, o que fazia essa função falhar por engano numa
            // segunda tentativa seguida (achado testando o sandbox MercadoPago:
            // 1ª retentativa funcionou, 2ª — idêntica — falhou com esse bug).
            // Confirma pelo estado final da linha, não pelo rowCount().
            $check = getPDO()->prepare('SELECT status FROM pagamentos WHERE id = :id');
            $check->execute([':id' => $existente['id']]);
            $statusFinal = $check->fetchColumn();
            if ($statusFinal === false || $statusFinal === 'aprovado') {
                self::logErr('reiniciarTentativa', 'blocked', 'Estado final inesperado após tentativa de reaproveitar linha.', ['pedido_id' => $pedido_id, 'pagamento_id' => $existente['id'], 'status_final' => $statusFinal]);
                return false;
            }

            self::logErr('reiniciarTentativa', 'reused', 'Linha de pagamento reaproveitada para nova tentativa.', ['pedido_id' => $pedido_id, 'pagamento_id' => $existente['id'], 'metodo' => $metodo]);
            return (int)$existente['id'];
        } catch (Throwable $e) {
            self::logErr('reiniciarTentativa', 'update', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return false;
        }
    }

    public static function buscarPorPedido(int $pedido_id): array|false
    {
        try {
            $sql = "SELECT p.*, pe.tipo_problema, pe.endereco_origem
                    FROM pagamentos p
                    JOIN pedidos pe ON p.pedido_id = pe.id
                    WHERE p.pedido_id = :pedido_id
                    ORDER BY p.id DESC
                    LIMIT 1";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([':pedido_id' => $pedido_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return false;
        }
    }

    public static function buscarPorIdExterno(string $id_externo): array|false
    {
        try {
            if (!self::hasColumn('pagamentos', 'id_externo')) {
                return false;
            }

            $stmt = getPDO()->prepare('SELECT * FROM pagamentos WHERE id_externo = :id_externo LIMIT 1');
            $stmt->execute([':id_externo' => $id_externo]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['id_externo' => $id_externo]);
            return false;
        }
    }

    public static function contarAprovadosPorPedido(int $pedido_id): int
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT COUNT(*) FROM pagamentos WHERE pedido_id = :pedido_id AND status = 'aprovado'"
            );
            $stmt->execute([':pedido_id' => $pedido_id]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'count', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return 0;
        }
    }

    public static function buscarAprovadoPorPedido(int $pedido_id): array|false
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT * FROM pagamentos WHERE pedido_id = :pedido_id AND status = 'aprovado' ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([':pedido_id' => $pedido_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return false;
        }
    }

    public static function prepararRepassePix(int $pedido_id, float $valor_guincho, float $valor_plataforma): array
    {
        $qtdAprovados = self::contarAprovadosPorPedido($pedido_id);
        if ($qtdAprovados !== 1) {
            return [
                'ok' => false,
                'erro' => "PIX-GUARD-01: pedido {$pedido_id} deve ter exatamente 1 pagamento aprovado; encontrados {$qtdAprovados}.",
                'pagamento' => false,
            ];
        }

        $pagamento = self::buscarAprovadoPorPedido($pedido_id);
        if (!$pagamento) {
            return [
                'ok' => false,
                'erro' => "PIX-GUARD-01: pagamento aprovado do pedido {$pedido_id} nao encontrado.",
                'pagamento' => false,
            ];
        }

        try {
            $stmt = getPDO()->prepare(
                "UPDATE pagamentos
                 SET valor_guincho = ?, valor_plataforma = ?, status_pix = 'processando'
                 WHERE id = ? AND status = 'aprovado' AND pago_guincho = 0"
            );
            $stmt->execute([$valor_guincho, $valor_plataforma, (int)$pagamento['id']]);
            if ($stmt->rowCount() !== 1) {
                return [
                    'ok' => false,
                    'erro' => "PIX-GUARD-01: pagamento {$pagamento['id']} ja processado ou indisponivel para repasse.",
                    'pagamento' => $pagamento,
                ];
            }
            $pagamento['valor_guincho'] = $valor_guincho;
            $pagamento['valor_plataforma'] = $valor_plataforma;
            return ['ok' => true, 'erro' => null, 'pagamento' => $pagamento];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return ['ok' => false, 'erro' => $e->getMessage(), 'pagamento' => $pagamento];
        }
    }

    /**
     * Pacote L1.7: agora roda em transação própria e grava um lançamento
     * `debito_repasse_guincho` no ledger append-only (payout_ledger_entries)
     * atomicamente com a marcação de pago_guincho=1 — é o ponto exato em
     * que o dinheiro "sai" para o guincheiro.
     */
    public static function confirmarRepassePix(int $pagamento_id, string $id_transacao): bool
    {
        require_once __DIR__ . '/../Services/Payment/PayoutLedgerService.php';
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // Mesma condição já usada em EstornoService::estornar(): SQLite (usado
            // no bootstrap de testes) não suporta a cláusula FOR UPDATE — sem esta
            // checagem, o SELECT lançava exceção, o catch fazia rollback silencioso
            // e confirmarRepassePix() sempre retornava false em ambiente de teste,
            // deixando status_pix preso em 'processando' (achado ao investigar
            // PixServiceTest::testReprocessarComSucessoDaApiAtualizaBanco).
            $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $row = $pdo->prepare("SELECT pedido_id, valor_guincho FROM pagamentos WHERE id = ?" . $lockClause);
            $row->execute([$pagamento_id]);
            $pag = $row->fetch(PDO::FETCH_ASSOC);
            if (!$pag) {
                $pdo->rollBack();
                return false;
            }

            $stmt = $pdo->prepare(
                "UPDATE pagamentos
                 SET id_transacao_pix = ?, status_pix = 'concluido',
                     pago_guincho = 1, data_pagamento_guincho = NOW()
                 WHERE id = ? AND status = 'aprovado' AND status_pix = 'processando' AND pago_guincho = 0"
            );
            $stmt->execute([$id_transacao, $pagamento_id]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            PayoutLedgerService::registrarRepasseConcluido($pdo, $pagamento_id, (int)$pag['pedido_id'], (float)$pag['valor_guincho'], $id_transacao);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['pagamento_id' => $pagamento_id]);
            return false;
        }
    }

    public static function aprovar(int $id, string $id_externo, string $webhook_payload = ''): bool
    {
        try {
            $sets = ["status = 'aprovado'"];
            $params = [':id' => $id];

            if (self::hasColumn('pagamentos', 'id_externo')) {
                $sets[] = 'id_externo = :id_externo';
                $params[':id_externo'] = $id_externo;
            }
            if (self::hasColumn('pagamentos', 'webhook_payload')) {
                $sets[] = 'webhook_payload = :webhook_payload';
                $params[':webhook_payload'] = $webhook_payload;
            }
            if (self::hasColumn('pagamentos', 'data_pagamento')) {
                $sets[] = 'data_pagamento = NOW()';
            }

            $sql = 'UPDATE pagamentos SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $stmt = getPDO()->prepare($sql);
            return $stmt->execute($params);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['id' => $id, 'id_externo' => $id_externo]);
            return false;
        }
    }

    public static function atualizarSplit(int $id, float $valor_guincho, float $valor_plataforma): bool
    {
        try {
            $stmt = getPDO()->prepare(
                'UPDATE pagamentos SET valor_guincho = :valor_guincho, valor_plataforma = :valor_plataforma WHERE id = :id'
            );
            return $stmt->execute([
                ':id' => $id,
                ':valor_guincho' => $valor_guincho,
                ':valor_plataforma' => $valor_plataforma,
            ]);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['id' => $id]);
            return false;
        }
    }

    public static function marcarGuinhoPago(int $id): bool
    {
        try {
            $sets = ["pago_guincho = 1", "status_pix = 'concluido'"];
            if (self::hasColumn('pagamentos', 'data_pagamento_guincho')) {
                $sets[] = 'data_pagamento_guincho = NOW()';
            }
            $stmt = getPDO()->prepare("UPDATE pagamentos SET " . implode(', ', $sets) . " WHERE id = :id AND status = 'aprovado' AND pago_guincho = 0");
            return $stmt->execute([':id' => $id]);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['id' => $id]);
            return false;
        }
    }

    public static function listar(array $filtros = [], int $pagina = 1, int $porPagina = 20): array
    {
        try {
            $porPagina = max(1, min(5000, $porPagina));
            $offset = max(0, ($pagina - 1) * $porPagina);
            $where = [];
            $valores = [];
            $dataInicio = $filtros['data_inicio'] ?? $filtros['inicio'] ?? '';
            $dataFim = $filtros['data_fim'] ?? $filtros['fim'] ?? '';

            if (!empty($filtros['status'])) {
                $where[] = 'p.status = :status';
                $valores[':status'] = $filtros['status'];
            }

            if (!empty($filtros['metodo'])) {
                $where[] = $filtros['metodo'] === 'freeflow'
                    ? "(COALESCE(NULLIF(p.metodo, ''), 'freeflow') = :metodo)"
                    : 'p.metodo = :metodo';
                $valores[':metodo'] = $filtros['metodo'];
            }

            if (!empty($dataInicio)) {
                $where[] = 'DATE(' . self::paymentDateExpression('p') . ') >= :data_inicio';
                $valores[':data_inicio'] = $dataInicio;
            }

            if (!empty($dataFim)) {
                $where[] = 'DATE(' . self::paymentDateExpression('p') . ') <= :data_fim';
                $valores[':data_fim'] = $dataFim;
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            $sql = "SELECT
                        p.*,
                        COALESCE(NULLIF(p.metodo, ''), 'freeflow') AS metodo_normalizado,
                        pe.tipo_problema,
                        uc.nome AS cliente_nome,
                        ug.nome AS guincho_nome
                    FROM pagamentos p
                    JOIN pedidos pe ON p.pedido_id = pe.id
                    JOIN usuarios uc ON pe.cliente_id = uc.id
                    LEFT JOIN guinchos g ON pe.guincho_id = g.id
                    LEFT JOIN usuarios ug ON g.usuario_id = ug.id
                    {$whereClause}
                    ORDER BY p.criado_em DESC
                    LIMIT :limit OFFSET :offset";

            $valores[':limit'] = $porPagina;
            $valores[':offset'] = $offset;
            $stmt = getPDO()->prepare($sql);
            foreach ($valores as $k => $v) {
                $type = in_array($k, [':offset', ':limit'], true) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($k, $v, $type);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['filtros' => $filtros, 'pagina' => $pagina, 'por_pagina' => $porPagina]);
            return [];
        }
    }

    public static function contar(array $filtros = []): int
    {
        try {
            $where = [];
            $valores = [];
            $dataInicio = $filtros['data_inicio'] ?? $filtros['inicio'] ?? '';
            $dataFim = $filtros['data_fim'] ?? $filtros['fim'] ?? '';

            if (!empty($filtros['status'])) {
                $where[] = 'p.status = :status';
                $valores[':status'] = $filtros['status'];
            }

            if (!empty($filtros['metodo'])) {
                $where[] = $filtros['metodo'] === 'freeflow'
                    ? "(COALESCE(NULLIF(p.metodo, ''), 'freeflow') = :metodo)"
                    : 'p.metodo = :metodo';
                $valores[':metodo'] = $filtros['metodo'];
            }

            if (!empty($dataInicio)) {
                $where[] = 'DATE(' . self::paymentDateExpression('p') . ') >= :data_inicio';
                $valores[':data_inicio'] = $dataInicio;
            }

            if (!empty($dataFim)) {
                $where[] = 'DATE(' . self::paymentDateExpression('p') . ') <= :data_fim';
                $valores[':data_fim'] = $dataFim;
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
            $stmt = getPDO()->prepare("SELECT COUNT(*) FROM pagamentos p {$whereClause}");
            $stmt->execute($valores);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'count', $e->getMessage(), ['filtros' => $filtros]);
            return 0;
        }
    }

    public static function totalPorPeriodo(string $data_inicio, string $data_fim): array
    {
        try {
            $dateExpr = self::paymentDateExpression();
            $sql = "SELECT 
                        COALESCE(SUM(valor_total), 0) AS total_valor_total,
                        COALESCE(SUM(valor_guincho), 0) AS total_valor_guincho,
                        COALESCE(SUM(valor_plataforma), 0) AS total_valor_plataforma
                    FROM pagamentos
                    WHERE status = 'aprovado'
                      AND DATE({$dateExpr}) BETWEEN :data_inicio AND :data_fim";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([
                ':data_inicio' => $data_inicio,
                ':data_fim' => $data_fim,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'valor_total' => (float)($result['total_valor_total'] ?? 0),
                'valor_guincho' => (float)($result['total_valor_guincho'] ?? 0),
                'valor_plataforma' => (float)($result['total_valor_plataforma'] ?? 0),
            ];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['data_inicio' => $data_inicio, 'data_fim' => $data_fim]);
            return [
                'valor_total' => 0.0,
                'valor_guincho' => 0.0,
                'valor_plataforma' => 0.0,
            ];
        }
    }

    public static function periodBounds(): array
    {
        try {
            $dateExpr = self::paymentDateExpression();
            $stmt = getPDO()->query(
                "SELECT
                    MIN(DATE({$dateExpr})) AS min_date,
                    MAX(DATE({$dateExpr})) AS max_date
                 FROM pagamentos"
            );
            $row = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
            return [
                'min_date' => !empty($row['min_date']) ? (string)$row['min_date'] : null,
                'max_date' => !empty($row['max_date']) ? (string)$row['max_date'] : null,
            ];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage());
            return ['min_date' => null, 'max_date' => null];
        }
    }

    public static function statusBreakdown(array $filtros = []): array
    {
        try {
            $dateExpr = self::paymentDateExpression();
            $where = [];
            $params = [];
            $dataInicio = $filtros['data_inicio'] ?? $filtros['inicio'] ?? '';
            $dataFim = $filtros['data_fim'] ?? $filtros['fim'] ?? '';

            if (!empty($filtros['metodo'])) {
                $where[] = $filtros['metodo'] === 'freeflow'
                    ? "(COALESCE(NULLIF(metodo, ''), 'freeflow') = :metodo)"
                    : 'metodo = :metodo';
                $params[':metodo'] = (string)$filtros['metodo'];
            }
            if (!empty($dataInicio)) {
                $where[] = 'DATE(' . $dateExpr . ') >= :data_inicio';
                $params[':data_inicio'] = (string)$dataInicio;
            }
            if (!empty($dataFim)) {
                $where[] = 'DATE(' . $dateExpr . ') <= :data_fim';
                $params[':data_fim'] = (string)$dataFim;
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = getPDO()->prepare(
                "SELECT status, COUNT(*) AS total
                   FROM pagamentos
                   {$whereClause}
                  GROUP BY status
                  ORDER BY total DESC, status ASC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['filtros' => $filtros]);
            return [];
        }
    }

    public static function methodBreakdown(array $filtros = []): array
    {
        try {
            $dateExpr = self::paymentDateExpression();
            $where = [];
            $params = [];
            $dataInicio = $filtros['data_inicio'] ?? $filtros['inicio'] ?? '';
            $dataFim = $filtros['data_fim'] ?? $filtros['fim'] ?? '';

            if (!empty($filtros['status'])) {
                $where[] = 'status = :status';
                $params[':status'] = (string)$filtros['status'];
            }
            if (!empty($dataInicio)) {
                $where[] = 'DATE(' . $dateExpr . ') >= :data_inicio';
                $params[':data_inicio'] = (string)$dataInicio;
            }
            if (!empty($dataFim)) {
                $where[] = 'DATE(' . $dateExpr . ') <= :data_fim';
                $params[':data_fim'] = (string)$dataFim;
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = getPDO()->prepare(
                "SELECT COALESCE(NULLIF(metodo, ''), 'freeflow') AS metodo, COUNT(*) AS total, COALESCE(SUM(valor_total), 0) AS valor_total
                   FROM pagamentos
                   {$whereClause}
                  GROUP BY COALESCE(NULLIF(metodo, ''), 'freeflow')
                  ORDER BY valor_total DESC, total DESC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['filtros' => $filtros]);
            return [];
        }
    }

    public static function approvedSeries(string $dataInicio, string $dataFim): array
    {
        try {
            $dateExpr = self::paymentDateExpression();
            $stmt = getPDO()->prepare(
                "SELECT DATE({$dateExpr}) AS dia,
                        COUNT(*) AS total_pagamentos,
                        COALESCE(SUM(valor_total), 0) AS valor_total
                   FROM pagamentos
                  WHERE status = 'aprovado'
                    AND DATE({$dateExpr}) BETWEEN :data_inicio AND :data_fim
                  GROUP BY DATE({$dateExpr})
                  ORDER BY DATE({$dateExpr}) ASC"
            );
            $stmt->execute([
                ':data_inicio' => $dataInicio,
                ':data_fim' => $dataFim,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $byDay = [];
            foreach ($rows as $row) {
                $byDay[(string)$row['dia']] = $row;
            }

            $series = [];
            $cursor = strtotime($dataInicio);
            $end = strtotime($dataFim);
            while ($cursor !== false && $end !== false && $cursor <= $end) {
                $dia = date('Y-m-d', $cursor);
                $row = $byDay[$dia] ?? [];
                $series[] = [
                    'date' => $dia,
                    'label' => date('d/m', $cursor),
                    'total_pagamentos' => (int)($row['total_pagamentos'] ?? 0),
                    'valor_total' => (float)($row['valor_total'] ?? 0),
                ];
                $cursor = strtotime('+1 day', $cursor);
            }

            return $series;
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['data_inicio' => $dataInicio, 'data_fim' => $dataFim]);
            return [];
        }
    }

    public static function listarPorGuincho(int $guincho_id, int $mes = 0, int $ano = 0): array
    {
        try {
            $where = ['p.guincho_id = ?', "pg.status = 'aprovado'"];
            $params = [$guincho_id];

            if ($mes > 0) {
                $where[] = 'MONTH(pg.criado_em) = ?';
                $params[] = $mes;
            }
            if ($ano > 0) {
                $where[] = 'YEAR(pg.criado_em) = ?';
                $params[] = $ano;
            }

            $stmt = getPDO()->prepare(
                'SELECT pg.*, p.endereco_origem, p.endereco_destino, p.criado_em AS pedido_em
'
                . 'FROM pagamentos pg
'
                . 'JOIN pedidos p ON p.id = pg.pedido_id
'
                . 'WHERE ' . implode(' AND ', $where) . '
'
                . 'ORDER BY pg.criado_em DESC'
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['guincho_id' => $guincho_id, 'mes' => $mes, 'ano' => $ano]);
            return [];
        }
    }

    public static function adminInsights(string $dataInicio, string $dataFim): array
    {
        try {
            $dateExpr = self::paymentDateExpression();
            $stmt = getPDO()->prepare(
                "SELECT
                    COUNT(*) AS total_registros,
                    SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) AS aprovados,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
                    SUM(CASE WHEN status = 'estornado' THEN 1 ELSE 0 END) AS estornados,
                    SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) AS rejeitados,
                    COALESCE(SUM(CASE WHEN status = 'aprovado' THEN valor_total ELSE 0 END), 0) AS receita_aprovada,
                    COALESCE(SUM(CASE WHEN status = 'estornado' THEN valor_total ELSE 0 END), 0) AS valor_estornado,
                    COALESCE(SUM(CASE WHEN status = 'aprovado' AND pago_guincho = 0 THEN valor_guincho ELSE 0 END), 0) AS repasse_pendente,
                    COALESCE(AVG(CASE WHEN status = 'aprovado' THEN valor_total END), 0) AS ticket_medio
                 FROM pagamentos
                 WHERE DATE({$dateExpr}) BETWEEN :data_inicio AND :data_fim"
            );
            $stmt->execute([
                ':data_inicio' => $dataInicio,
                ':data_fim' => $dataFim,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $stmtCancel = getPDO()->prepare(
                "SELECT
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) AS pedidos_cancelados,
                    SUM(CASE WHEN status = 'cancelado' AND COALESCE(taxa_cancelamento, 0) > 0 THEN 1 ELSE 0 END) AS cancelamentos_com_taxa,
                    COALESCE(SUM(CASE WHEN status = 'cancelado' THEN taxa_cancelamento ELSE 0 END), 0) AS taxa_retida_total
                 FROM pedidos
                 WHERE DATE(criado_em) BETWEEN :data_inicio AND :data_fim"
            );
            $stmtCancel->execute([
                ':data_inicio' => $dataInicio,
                ':data_fim' => $dataFim,
            ]);
            $cancel = $stmtCancel->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_registros' => (int)($row['total_registros'] ?? 0),
                'aprovados' => (int)($row['aprovados'] ?? 0),
                'pendentes' => (int)($row['pendentes'] ?? 0),
                'estornados' => (int)($row['estornados'] ?? 0),
                'rejeitados' => (int)($row['rejeitados'] ?? 0),
                'receita_aprovada' => (float)($row['receita_aprovada'] ?? 0),
                'valor_estornado' => (float)($row['valor_estornado'] ?? 0),
                'repasse_pendente' => (float)($row['repasse_pendente'] ?? 0),
                'ticket_medio' => (float)($row['ticket_medio'] ?? 0),
                'pedidos_cancelados' => (int)($cancel['pedidos_cancelados'] ?? 0),
                'cancelamentos_com_taxa' => (int)($cancel['cancelamentos_com_taxa'] ?? 0),
                'taxa_retida_total' => (float)($cancel['taxa_retida_total'] ?? 0),
            ];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['inicio' => $dataInicio, 'fim' => $dataFim]);
            return [
                'total_registros' => 0,
                'aprovados' => 0,
                'pendentes' => 0,
                'estornados' => 0,
                'rejeitados' => 0,
                'receita_aprovada' => 0.0,
                'valor_estornado' => 0.0,
                'repasse_pendente' => 0.0,
                'ticket_medio' => 0.0,
                'pedidos_cancelados' => 0,
                'cancelamentos_com_taxa' => 0,
                'taxa_retida_total' => 0.0,
            ];
        }
    }

    public static function topGuinchos(string $dataInicio, string $dataFim, int $limit = 5): array
    {
        try {
            $dateExpr = self::paymentDateExpression('pg');
            $stmt = getPDO()->prepare(
                "SELECT
                    COALESCE(ug.nome, 'Sem guincho') AS nome,
                    COUNT(*) AS corridas,
                    COALESCE(SUM(pg.valor_guincho), 0) AS valor_guincho
                 FROM pagamentos pg
                 JOIN pedidos p ON p.id = pg.pedido_id
                 LEFT JOIN guinchos g ON g.id = p.guincho_id
                 LEFT JOIN usuarios ug ON ug.id = g.usuario_id
                 WHERE pg.status = 'aprovado'
                   AND DATE({$dateExpr}) BETWEEN :data_inicio AND :data_fim
                 GROUP BY COALESCE(ug.nome, 'Sem guincho')
                 ORDER BY valor_guincho DESC, corridas DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':data_inicio', $dataInicio, PDO::PARAM_STR);
            $stmt->bindValue(':data_fim', $dataFim, PDO::PARAM_STR);
            $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['inicio' => $dataInicio, 'fim' => $dataFim, 'limit' => $limit]);
            return [];
        }
    }

    public static function topClientes(string $dataInicio, string $dataFim, int $limit = 5): array
    {
        try {
            $dateExpr = self::paymentDateExpression('pg');
            $stmt = getPDO()->prepare(
                "SELECT
                    COALESCE(uc.nome, 'Sem cliente') AS nome,
                    COUNT(*) AS pedidos,
                    COALESCE(SUM(pg.valor_total), 0) AS valor_total
                 FROM pagamentos pg
                 JOIN pedidos p ON p.id = pg.pedido_id
                 LEFT JOIN usuarios uc ON uc.id = p.cliente_id
                 WHERE DATE({$dateExpr}) BETWEEN :data_inicio AND :data_fim
                 GROUP BY COALESCE(uc.nome, 'Sem cliente')
                 ORDER BY valor_total DESC, pedidos DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':data_inicio', $dataInicio, PDO::PARAM_STR);
            $stmt->bindValue(':data_fim', $dataFim, PDO::PARAM_STR);
            $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['inicio' => $dataInicio, 'fim' => $dataFim, 'limit' => $limit]);
            return [];
        }
    }

    public static function extratoGuincho(int $guinchoId, int $mes = 0, int $ano = 0): array
    {
        try {
            $where = ['p.guincho_id = ?'];
            $params = [$guinchoId];

            if ($mes > 0) {
                $where[] = 'MONTH(p.criado_em) = ?';
                $params[] = $mes;
            }
            if ($ano > 0) {
                $where[] = 'YEAR(p.criado_em) = ?';
                $params[] = $ano;
            }

            $stmt = getPDO()->prepare(
                "SELECT
                    p.id AS pedido_id,
                    p.criado_em AS pedido_em,
                    p.status AS pedido_status,
                    p.tipo_problema,
                    p.endereco_origem,
                    p.endereco_destino,
                    p.custo_estimado,
                    p.custo_final,
                    p.taxa_cancelamento,
                    p.cancelado_por,
                    p.motivo_cancelamento,
                    pg.id AS pagamento_id,
                    pg.metodo,
                    pg.status AS pagamento_status,
                    pg.status_pix,
                    pg.valor_total,
                    pg.valor_guincho,
                    pg.valor_plataforma,
                    pg.pago_guincho,
                    pg.data_pagamento,
                    pg.data_pagamento_guincho,
                    pg.id_transacao_pix,
                    uc.nome AS cliente_nome
                 FROM pedidos p
                 LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
                 LEFT JOIN usuarios uc ON uc.id = p.cliente_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.criado_em DESC, pg.id DESC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['guincho_id' => $guinchoId, 'mes' => $mes, 'ano' => $ano]);
            return [];
        }
    }

    public static function totaisExtratoGuincho(int $guinchoId, int $mes = 0, int $ano = 0): array
    {
        try {
            $where = ['p.guincho_id = ?'];
            $params = [$guinchoId];
            if ($mes > 0) {
                $where[] = 'MONTH(p.criado_em) = ?';
                $params[] = $mes;
            }
            if ($ano > 0) {
                $where[] = 'YEAR(p.criado_em) = ?';
                $params[] = $ano;
            }

            $stmt = getPDO()->prepare(
                "SELECT
                    COUNT(*) AS total_pedidos,
                    SUM(CASE WHEN p.status = 'concluido' THEN 1 ELSE 0 END) AS concluidos,
                    SUM(CASE WHEN p.status = 'cancelado' THEN 1 ELSE 0 END) AS cancelados,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' THEN pg.valor_total ELSE 0 END), 0) AS valor_bruto_aprovado,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' THEN pg.valor_guincho ELSE 0 END), 0) AS valor_liquido_aprovado,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' AND COALESCE(pg.pago_guincho, 0) = 1 THEN pg.valor_guincho ELSE 0 END), 0) AS valor_pago_guincho,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' AND COALESCE(pg.pago_guincho, 0) = 0 THEN pg.valor_guincho ELSE 0 END), 0) AS valor_pendente_guincho,
                    COALESCE(SUM(CASE WHEN pg.status = 'estornado' THEN pg.valor_total ELSE 0 END), 0) AS valor_estornado,
                    COALESCE(SUM(CASE WHEN p.status = 'cancelado' THEN p.taxa_cancelamento ELSE 0 END), 0) AS taxa_retida_cancelamento
                 FROM pedidos p
                 LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
                 WHERE " . implode(' AND ', $where)
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_pedidos' => (int)($row['total_pedidos'] ?? 0),
                'concluidos' => (int)($row['concluidos'] ?? 0),
                'cancelados' => (int)($row['cancelados'] ?? 0),
                'valor_bruto_aprovado' => (float)($row['valor_bruto_aprovado'] ?? 0),
                'valor_liquido_aprovado' => (float)($row['valor_liquido_aprovado'] ?? 0),
                'valor_pago_guincho' => (float)($row['valor_pago_guincho'] ?? 0),
                'valor_pendente_guincho' => (float)($row['valor_pendente_guincho'] ?? 0),
                'valor_estornado' => (float)($row['valor_estornado'] ?? 0),
                'taxa_retida_cancelamento' => (float)($row['taxa_retida_cancelamento'] ?? 0),
            ];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['guincho_id' => $guinchoId, 'mes' => $mes, 'ano' => $ano]);
            return [
                'total_pedidos' => 0,
                'concluidos' => 0,
                'cancelados' => 0,
                'valor_bruto_aprovado' => 0.0,
                'valor_liquido_aprovado' => 0.0,
                'valor_pago_guincho' => 0.0,
                'valor_pendente_guincho' => 0.0,
                'valor_estornado' => 0.0,
                'taxa_retida_cancelamento' => 0.0,
            ];
        }
    }

    public static function extratoCliente(int $clienteId, int $mes = 0, int $ano = 0): array
    {
        try {
            $where = ['p.cliente_id = ?'];
            $params = [$clienteId];

            if ($mes > 0) {
                $where[] = 'MONTH(p.criado_em) = ?';
                $params[] = $mes;
            }
            if ($ano > 0) {
                $where[] = 'YEAR(p.criado_em) = ?';
                $params[] = $ano;
            }

            $stmt = getPDO()->prepare(
                "SELECT
                    p.id AS pedido_id,
                    p.criado_em AS pedido_em,
                    p.status AS pedido_status,
                    p.tipo_problema,
                    p.endereco_origem,
                    p.endereco_destino,
                    p.custo_estimado,
                    p.custo_final,
                    p.taxa_cancelamento,
                    p.cancelado_por,
                    p.motivo_cancelamento,
                    pg.id AS pagamento_id,
                    pg.metodo,
                    pg.status AS pagamento_status,
                    pg.status_pix,
                    pg.valor_total,
                    pg.valor_guincho,
                    pg.valor_plataforma,
                    pg.data_pagamento,
                    pg.data_pagamento_guincho,
                    ug.nome AS guincho_nome
                 FROM pedidos p
                 LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
                 LEFT JOIN guinchos g ON g.id = p.guincho_id
                 LEFT JOIN usuarios ug ON ug.id = g.usuario_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.criado_em DESC, pg.id DESC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['cliente_id' => $clienteId, 'mes' => $mes, 'ano' => $ano]);
            return [];
        }
    }

    public static function totaisExtratoCliente(int $clienteId, int $mes = 0, int $ano = 0): array
    {
        try {
            $where = ['p.cliente_id = ?'];
            $params = [$clienteId];
            if ($mes > 0) {
                $where[] = 'MONTH(p.criado_em) = ?';
                $params[] = $mes;
            }
            if ($ano > 0) {
                $where[] = 'YEAR(p.criado_em) = ?';
                $params[] = $ano;
            }

            $stmt = getPDO()->prepare(
                "SELECT
                    COUNT(*) AS total_pedidos,
                    SUM(CASE WHEN p.status = 'concluido' THEN 1 ELSE 0 END) AS concluidos,
                    SUM(CASE WHEN p.status = 'cancelado' THEN 1 ELSE 0 END) AS cancelados,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' THEN pg.valor_total ELSE 0 END), 0) AS valor_pago,
                    COALESCE(SUM(CASE WHEN pg.status = 'estornado' THEN pg.valor_total ELSE 0 END), 0) AS valor_estornado,
                    COALESCE(SUM(CASE WHEN p.status = 'cancelado' THEN p.taxa_cancelamento ELSE 0 END), 0) AS taxa_cancelamento_total
                 FROM pedidos p
                 LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
                 WHERE " . implode(' AND ', $where)
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_pedidos' => (int)($row['total_pedidos'] ?? 0),
                'concluidos' => (int)($row['concluidos'] ?? 0),
                'cancelados' => (int)($row['cancelados'] ?? 0),
                'valor_pago' => (float)($row['valor_pago'] ?? 0),
                'valor_estornado' => (float)($row['valor_estornado'] ?? 0),
                'taxa_cancelamento_total' => (float)($row['taxa_cancelamento_total'] ?? 0),
            ];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['cliente_id' => $clienteId, 'mes' => $mes, 'ano' => $ano]);
            return [
                'total_pedidos' => 0,
                'concluidos' => 0,
                'cancelados' => 0,
                'valor_pago' => 0.0,
                'valor_estornado' => 0.0,
                'taxa_cancelamento_total' => 0.0,
            ];
        }
    }

    public static function totaisPorGuincho(int $guincho_id, int $mes = 0, int $ano = 0): array
    {
        try {
            $where = ['p.guincho_id = ?', "pg.status = 'aprovado'"];
            $params = [$guincho_id];

            if ($mes > 0) {
                $where[] = 'MONTH(pg.criado_em) = ?';
                $params[] = $mes;
            }
            if ($ano > 0) {
                $where[] = 'YEAR(pg.criado_em) = ?';
                $params[] = $ano;
            }

            $stmt = getPDO()->prepare(
                'SELECT
'
                . '    COUNT(*) AS total_corridas,
'
                . '    COALESCE(SUM(pg.valor_guincho), 0) AS total_recebido,
'
                . '    COALESCE(SUM(CASE WHEN MONTH(pg.criado_em) = MONTH(NOW()) AND YEAR(pg.criado_em) = YEAR(NOW()) THEN pg.valor_guincho END), 0) AS recebido_mes
'
                . 'FROM pagamentos pg
'
                . 'JOIN pedidos p ON p.id = pg.pedido_id
'
                . 'WHERE ' . implode(' AND ', $where)
            );
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_corridas' => 0,
                'total_recebido' => 0,
                'recebido_mes' => 0,
            ];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['guincho_id' => $guincho_id, 'mes' => $mes, 'ano' => $ano]);
            return [
                'total_corridas' => 0,
                'total_recebido' => 0,
                'recebido_mes' => 0,
            ];
        }
    }

    public static function marcarGuinhoPagoPorPedido(int $pedido_id): bool
    {
        try {
            $sets = ["pago_guincho = 1", "status_pix = 'concluido'"];
            if (self::hasColumn('pagamentos', 'data_pagamento_guincho')) {
                $sets[] = 'data_pagamento_guincho = NOW()';
            }
            $stmt = getPDO()->prepare("UPDATE pagamentos SET " . implode(', ', $sets) . " WHERE pedido_id = ? AND status = 'aprovado' AND pago_guincho = 0");
            return $stmt->execute([$pedido_id]);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return false;
        }
    }
}
