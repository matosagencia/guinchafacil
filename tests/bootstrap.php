<?php
declare(strict_types=1);

/**
 * Bootstrap PHPUnit — define constants, substitui getPDO() por SQLite in-memory
 * e carrega as classes sob teste sem passar por config.php nem pelo banco real.
 */

// ─── Autoloader (PHPUnit + PHPMailer) ────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

// ─── Constante de ambiente de teste ──────────────────────────────────────────
define('TESTING', true);

// ─── Exceção de controle de fluxo (substitui exit no WebhookController) ─────
class HttpResponseException extends RuntimeException
{
    public $code;
    public string $body;

    public function __construct(int $code, string $body = '')
    {
        $this->code = $code;
        $this->body = $body;
        parent::__construct($body, $code);
    }
}

// ─── Constantes necessárias pelas classes sob teste ──────────────────────────
define('MP_ACCESS_TOKEN',   'TEST_MP_ACCESS_TOKEN');
define('MP_WEBHOOK_SECRET', 'TEST_WEBHOOK_SECRET_abc123xyz');
define('ADMIN_EMAIL',       'admin@test.com');
define('SMTP_FROM_EMAIL',   'noreply@test.com');
define('PS_TOKEN',          'TEST_PS_TOKEN');
define('PS_EMAIL',          'ps@test.com');
define('PS_ENV',            'sandbox');
define('PS_BASE_URL',       'https://ws.sandbox.pagseguro.uol.com.br');
define('PS_CHECKOUT_URL',   'https://sandbox.pagseguro.uol.com.br');
define('ENCRYPTION_KEY',    'test-encryption-key-32chars!!!!!');
define('APP_ENV',           'testing');
define('APP_URL',           'http://localhost');
define('UPLOAD_PATH',       sys_get_temp_dir() . '/gf-test-uploads/');
define('PUBLIC_PATH',       sys_get_temp_dir() . '/gf-test-root/public');
define('LOG_DIR',           sys_get_temp_dir() . '/gf-test-logs');
define('MAX_UPLOAD_SIZE',   5 * 1024 * 1024);
define('DB_HOST',           'localhost');
define('DB_NAME',           'test_guinchafacil');
define('DB_USER',           'root');
define('DB_PASS',           '');
define('DB_CHARSET',        'utf8mb4');

// ─── PDO SQLite in-memory (stub de getPDO) ───────────────────────────────────
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Registra NOW() como função user-defined para compatibilidade com MySQL
        $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'), 0);
        // GREATEST/LEAST são MySQL-only; o SQLite não as tem. Registramos
        // equivalentes para que UPDATEs como `GREATEST(0, reputacao - ?)`
        // (requeue de cancelamento) rodem no harness. Ignoram NULLs.
        $pdo->sqliteCreateFunction('GREATEST', fn(...$a) => max(array_map('floatval', array_filter($a, fn($v) => $v !== null))), -1);
        $pdo->sqliteCreateFunction('LEAST', fn(...$a) => min(array_map('floatval', array_filter($a, fn($v) => $v !== null))), -1);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pagamentos (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id             INTEGER NOT NULL UNIQUE,
                metodo                TEXT    NOT NULL DEFAULT 'mercadopago',
                id_externo            TEXT,
                status                TEXT    NOT NULL DEFAULT 'pendente',
                valor_total           REAL    NOT NULL DEFAULT 0,
                valor_guincho         REAL    NOT NULL DEFAULT 0,
                valor_plataforma      REAL    NOT NULL DEFAULT 0,
                status_pix            TEXT    NOT NULL DEFAULT 'pendente',
                id_transacao_pix      TEXT,
                pago_guincho          INTEGER NOT NULL DEFAULT 0,
                data_pagamento_guincho TEXT,
                pix_tentativas        INTEGER NOT NULL DEFAULT 0,
                proximo_repasse_em    TEXT,
                criado_em             TEXT
            )
        ");

        // §HIBRIDO-COMPLEMENTAR-01 — arquiva o pagamento aprovado original
        // (socorro no local) antes de resetar `pagamentos` pra cobrança
        // complementar do reboque (UNIQUE(pedido_id) impede duas linhas).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pagamentos_arquivados (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL,
                pagamento_id_original INTEGER,
                fase TEXT NOT NULL DEFAULT 'socorro_local',
                metodo TEXT NOT NULL,
                valor_total REAL NOT NULL DEFAULT 0,
                valor_guincho REAL NOT NULL DEFAULT 0,
                valor_plataforma REAL NOT NULL DEFAULT 0,
                status TEXT NOT NULL,
                id_externo TEXT,
                id_transacao_pix TEXT,
                status_pix_original TEXT,
                pago_guincho_original INTEGER,
                webhook_payload TEXT,
                criado_em_original TEXT,
                data_pagamento_original TEXT,
                arquivado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedidos (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                guincho_id       INTEGER,
                cliente_id       INTEGER,
                veiculo_id       INTEGER,
                status           TEXT    NOT NULL DEFAULT 'aguardando_pagamento',
                custo_estimado   REAL    NOT NULL DEFAULT 0,
                custo_final      REAL,
                tipo_problema    TEXT,
                descricao_problema TEXT,
                endereco_origem  TEXT,
                lat_origem       REAL,
                lng_origem       REAL,
                endereco_destino TEXT,
                lat_destino      REAL,
                lng_destino      REAL,
                cliente_nome     TEXT,
                cliente_email    TEXT,
                expiracao_aceite TEXT,
                raio_atual_km    INTEGER,
                foto_plataforma  TEXT,
                foto_destino     TEXT,
                cancelado_por    TEXT,
                motivo_cancelamento TEXT,
                taxa_cancelamento REAL DEFAULT 0,
                cancelado_em     TEXT,
                distancia_km     REAL DEFAULT 0,
                distancia_guincho_origem_km REAL,
                score_minimo_atual REAL DEFAULT 0,
                service_type_id  INTEGER,
                attendance_mode  TEXT DEFAULT 'TOWING',
                veiculo_esta_batido INTEGER,
                rodas_travadas   INTEGER,
                local_dificil_acesso INTEGER,
                em_garagem_subsolo INTEGER,
                criado_em        TEXT
            )
        ");

        // chat_mensagens — usada pelo simulador (fase 9) e pelo ChatService.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_mensagens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                mensagem TEXT NOT NULL,
                idempotency_key TEXT,
                lida INTEGER NOT NULL DEFAULT 0,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS veiculos (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id   INTEGER,
                placa       TEXT,
                marca       TEXT,
                modelo      TEXT,
                ano         INTEGER,
                cor         TEXT,
                tipo        TEXT,
                categoria_tarifa TEXT,
                ativo       INTEGER DEFAULT 1,
                criado_em   TEXT
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS feriados (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                data TEXT NOT NULL,
                nome TEXT NOT NULL,
                recorrente_anual INTEGER NOT NULL DEFAULT 1,
                ativo INTEGER NOT NULL DEFAULT 1,
                criado_em TEXT,
                atualizado_em TEXT
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS guinchos (
                id             INTEGER,
                usuario_id     INTEGER,
                placa_guincho  TEXT,
                chave_pix      TEXT,
                chave_pix_tipo TEXT DEFAULT 'aleatoria',
                aprovado       INTEGER DEFAULT 0,
                disponivel     INTEGER DEFAULT 0,
                lat_atual      REAL,
                lng_atual      REAL,
                lat_operacao   REAL,
                lng_operacao   REAL,
                reputacao      REAL DEFAULT 0,
                total_cancelamentos INTEGER DEFAULT 0,
                total_avaliacoes INTEGER DEFAULT 0,
                modelo_veiculo TEXT,
                foto_veiculo   TEXT,
                capacidade_ton REAL,
                raio_cobertura_km INTEGER DEFAULT 20,
                criado_em      TEXT
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id         INTEGER,
                nome       TEXT,
                email      TEXT,
                senha_hash TEXT,
                telefone   TEXT,
                cpf        TEXT,
                tipo       TEXT    DEFAULT 'guincho',
                ativo      INTEGER DEFAULT 1
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logs_webhook (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                fonte       TEXT,
                evento      TEXT,
                payload     TEXT,
                status_http INTEGER,
                criado_em   TEXT
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT,
                application TEXT,
                system TEXT,
                cls TEXT,
                func TEXT,
                file TEXT,
                phase TEXT,
                code TEXT,
                request_id TEXT,
                run_id TEXT,
                pedido_id INTEGER,
                usuario_id INTEGER,
                guincho_id INTEGER,
                duration_ms INTEGER,
                msg TEXT,
                uri TEXT,
                ip TEXT,
                ctx_json TEXT,
                criado_em TEXT
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL,
                pagamento_id INTEGER NOT NULL,
                job_type TEXT NOT NULL,
                idempotency_key TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'queued',
                attempt_count INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 5,
                worker_id TEXT,
                locked_at TEXT,
                available_at TEXT,
                last_error TEXT,
                payload_json TEXT,
                finished_at TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_job_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_job_id INTEGER NOT NULL,
                attempt_number INTEGER NOT NULL,
                worker_id TEXT,
                success INTEGER NOT NULL DEFAULT 0,
                response_json TEXT,
                error_message TEXT,
                created_at TEXT
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS configuracoes (
                chave TEXT PRIMARY KEY,
                valor TEXT
            )
        ");

        // Pacote L1.7: PedidoTransitionService::approvePayment() grava o
        // ledger (PayoutLedgerService::registrarSplitAprovado()) DENTRO da
        // mesma transação que move o pedido para aguardando_guincho — sem
        // esta tabela, o INSERT falhava com "no such table" e o catch()
        // fazia rollback de tudo silenciosamente, deixando o pedido preso
        // em aguardando_pagamento (WebhookControllerTest::testFluxoCompletoAprovaMp
        // e outros 5 testes falhavam exatamente por isso — faltava aqui,
        // não era regressão de código).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payout_ledger_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pagamento_id INTEGER NOT NULL,
                pedido_id INTEGER NOT NULL,
                entry_type TEXT NOT NULL,
                valor REAL NOT NULL,
                referencia_externa TEXT,
                metadata_json TEXT,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Pacote L1.6 (pendência #32): CancellationCalculationServiceTest
        // usa PedidoPercursoResumo::buscar(), que faz SELECT nesta tabela
        // (ver install/migration_por_v1.sql) — faltava aqui no stub SQLite.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_percurso_resumos (
                pedido_id INTEGER NOT NULL,
                fase TEXT NOT NULL,
                total_points INTEGER NOT NULL DEFAULT 0,
                valid_points INTEGER NOT NULL DEFAULT 0,
                rejected_points INTEGER NOT NULL DEFAULT 0,
                started_at TEXT,
                last_point_at TEXT,
                duration_seconds INTEGER NOT NULL DEFAULT 0,
                distance_raw_m REAL NOT NULL DEFAULT 0,
                distance_validated_m REAL NOT NULL DEFAULT 0,
                max_gap_seconds INTEGER NOT NULL DEFAULT 0,
                max_speed_kmh REAL NOT NULL DEFAULT 0,
                tracking_quality TEXT NOT NULL DEFAULT 'unknown',
                last_street TEXT,
                last_latitude REAL,
                last_longitude REAL,
                updated_at TEXT,
                PRIMARY KEY (pedido_id, fase)
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS avaliacoes (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id  INTEGER,
                cliente_id INTEGER,
                guincho_id INTEGER,
                estrelas   INTEGER,
                comentario TEXT,
                criado_em  TEXT
            )
        ");

        // Pacote L1.6: CancellationSnapshot (preview/confirmação de
        // cancelamento) e a auditoria de cancelamento (best-effort, com
        // catch próprio no código de produção — mas os testes de integração
        // fazem asserções diretas sobre estas tabelas).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cancelamento_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL,
                actor_type TEXT NOT NULL,
                actor_id INTEGER NOT NULL,
                formula_version TEXT NOT NULL DEFAULT 'v1',
                factors_json TEXT,
                por_quality TEXT,
                fee_amount REAL NOT NULL DEFAULT 0,
                refund_amount REAL NOT NULL DEFAULT 0,
                penalty_amount REAL NOT NULL DEFAULT 0,
                snapshot_hash TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                expires_at TEXT NOT NULL,
                confirmed_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_evidencias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL,
                guincho_id INTEGER NOT NULL,
                tipo TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'accepted',
                nonce_token TEXT NOT NULL,
                nonce_expires_at TEXT NOT NULL,
                point_id INTEGER NOT NULL,
                latitude REAL NOT NULL,
                longitude REAL NOT NULL,
                accuracy_m REAL,
                device_timestamp TEXT,
                server_timestamp TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                original_name TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                size_bytes INTEGER NOT NULL,
                sha256 TEXT NOT NULL,
                metadata_json TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uk_nonce_token ON pedido_evidencias(nonce_token)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uk_pedido_tipo_sha256 ON pedido_evidencias(pedido_id, tipo, sha256)");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_cancelamentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL,
                ator_tipo TEXT NOT NULL,
                ator_id INTEGER,
                motivo TEXT NOT NULL,
                status_anterior TEXT NOT NULL,
                penalidade REAL NOT NULL DEFAULT 0,
                ip TEXT,
                user_agent TEXT,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Pacote L1.3/L1.4: PedidoTransitionService (aceite idempotente) e
        // ProofOfRoadService (rastreamento) gravam nestas duas tabelas —
        // faltavam no stub, causando "no such table" em cascata assim que o
        // fix de senha_hash parou de mascarar o problema mais cedo no setUp().
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_idempotency (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                idempotency_key TEXT NOT NULL UNIQUE,
                pedido_id INTEGER NOT NULL,
                operation TEXT NOT NULL,
                actor_type TEXT NOT NULL,
                actor_id INTEGER NOT NULL,
                success INTEGER NOT NULL DEFAULT 0,
                response_json TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_localizacoes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL,
                guincho_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                fase TEXT NOT NULL,
                sequence_number INTEGER NOT NULL,
                client_point_id TEXT NOT NULL,
                latitude REAL NOT NULL,
                longitude REAL NOT NULL,
                accuracy_m REAL,
                speed_mps REAL,
                heading_deg REAL,
                device_timestamp TEXT,
                server_timestamp TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                received_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                previous_point_id INTEGER,
                distance_raw_m REAL NOT NULL DEFAULT 0,
                distance_validated_m REAL NOT NULL DEFAULT 0,
                distance_accumulated_m REAL NOT NULL DEFAULT 0,
                elapsed_ms INTEGER,
                calculated_speed_kmh REAL,
                street_name TEXT,
                street_source TEXT,
                match_confidence REAL,
                is_valid INTEGER NOT NULL DEFAULT 1,
                rejection_code TEXT,
                hash_previous TEXT,
                hash_current TEXT,
                request_id TEXT,
                run_id TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (pedido_id, client_point_id),
                UNIQUE (pedido_id, sequence_number)
            )
        ");

        // Pacote de simulação (SimulationRun/SimulationStep) — schema espelha
        // install/migrate.php (simulation_runs/simulation_steps), convertido
        // para SQLite. SimulationRun::assertSchemaReady() valida a presença
        // destas colunas antes de qualquer INSERT.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS simulation_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id TEXT NOT NULL UNIQUE,
                engine TEXT NOT NULL DEFAULT 'php_internal',
                suite TEXT,
                status TEXT NOT NULL DEFAULT 'running',
                pix_dry_run INTEGER NOT NULL DEFAULT 1,
                pedido_id INTEGER,
                total_fases INTEGER NOT NULL DEFAULT 0,
                fases_ok INTEGER NOT NULL DEFAULT 0,
                fases_erro INTEGER NOT NULL DEFAULT 0,
                duracao_ms INTEGER,
                requested_by INTEGER,
                requested_at TEXT,
                target_environment TEXT,
                target_url TEXT,
                browser TEXT,
                viewport TEXT,
                locale TEXT,
                timezone TEXT,
                worker_id TEXT,
                worker_pid INTEGER,
                heartbeat_at TEXT,
                started_at TEXT,
                finished_at TEXT,
                exit_code INTEGER,
                configuration_json TEXT,
                summary_json TEXT,
                app_version TEXT,
                git_commit TEXT,
                iniciado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finalizado_em TEXT
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS simulation_steps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id TEXT NOT NULL,
                fase TEXT NOT NULL,
                ok INTEGER NOT NULL DEFAULT 1,
                mensagem TEXT NOT NULL,
                system TEXT,
                class TEXT,
                function TEXT,
                file TEXT,
                phase TEXT,
                code TEXT,
                status TEXT,
                duration_ms INTEGER,
                expected_json TEXT,
                actual_json TEXT,
                error_message TEXT,
                stack_trace TEXT,
                started_at TEXT,
                finished_at TEXT,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // ── Etapa 8 (produtos e estoque) — espelha migration_produtos_estoque_v1 ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS produtos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sku TEXT NOT NULL UNIQUE,
                nome TEXT NOT NULL,
                categoria TEXT NOT NULL DEFAULT 'bateria',
                descricao TEXT,
                especificacao TEXT,
                preco_referencia REAL,
                unidade TEXT NOT NULL DEFAULT 'un',
                active INTEGER NOT NULL DEFAULT 1,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS provider_produtos_estoque (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                provider_id INTEGER NOT NULL,
                produto_id INTEGER NOT NULL,
                quantidade INTEGER NOT NULL DEFAULT 0,
                preco_venda REAL,
                active INTEGER NOT NULL DEFAULT 1,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (provider_id, produto_id)
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS estoque_movimentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                provider_id INTEGER NOT NULL,
                produto_id INTEGER NOT NULL,
                pedido_id INTEGER,
                tipo TEXT NOT NULL,
                quantidade INTEGER NOT NULL,
                saldo_apos INTEGER NOT NULL,
                hash_idempotencia TEXT UNIQUE,
                descricao TEXT,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // §COBERTURA-RAIO-01 (06/08/2026): Etapa 5 (diagnóstico e orçamento
        // complementar) nunca tinha tabelas espelhadas aqui — qualquer teste
        // que tocasse DiagnosticoService/PedidoOrcamento/PedidoDiagnostico
        // falhava com "no such table". Espelha
        // migration_diagnostico_orcamento_v1.sql (sem FK/CASCADE, que o
        // SQLite de teste não precisa reforçar).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_diagnosticos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL UNIQUE,
                guincho_id INTEGER NOT NULL,
                resultado TEXT NOT NULL,
                descricao TEXT,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pedido_orcamentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pedido_id INTEGER NOT NULL UNIQUE,
                diagnostico_id INTEGER NOT NULL,
                itens_json TEXT NOT NULL,
                valor_total REAL NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'PENDENTE',
                decidido_em TEXT,
                criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS order_charge_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                provider_id INTEGER,
                service_execution_id INTEGER,
                phase_code TEXT NOT NULL,
                charge_type TEXT NOT NULL,
                description TEXT NOT NULL,
                quantity REAL NOT NULL DEFAULT 1,
                unit_amount REAL NOT NULL DEFAULT 0,
                gross_amount REAL NOT NULL DEFAULT 0,
                discount_amount REAL NOT NULL DEFAULT 0,
                platform_fee_amount REAL NOT NULL DEFAULT 0,
                provider_net_amount REAL NOT NULL DEFAULT 0,
                charge_status TEXT NOT NULL DEFAULT 'PENDING',
                payable_status TEXT NOT NULL DEFAULT 'NOT_ELIGIBLE',
                calculation_version TEXT NOT NULL,
                calculation_context_json TEXT,
                evidence_required INTEGER NOT NULL DEFAULT 0,
                evidence_validated_at TEXT,
                approved_at TEXT,
                payable_at TEXT,
                paid_at TEXT,
                blocked_at TEXT,
                cancelled_at TEXT,
                block_reason_code TEXT,
                idempotency_key TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_charge_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT, charge_item_id INTEGER NOT NULL UNIQUE,
            order_id INTEGER NOT NULL, amount REAL NOT NULL DEFAULT 0, method TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'PENDING', external_id TEXT, approved_at TEXT,
            idempotency_key TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");

        // ── Etapa 15 (compatibilidade prestador × veículo) ──
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS provider_service_vehicle_capabilities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                provider_id INTEGER NOT NULL,
                service_type_id INTEGER NOT NULL,
                vehicle_category TEXT NOT NULL,
                approval_status TEXT NOT NULL DEFAULT 'APPROVED',
                enabled INTEGER NOT NULL DEFAULT 1,
                max_vehicle_weight_kg REAL,
                supports_electric INTEGER NOT NULL DEFAULT 1,
                supports_hybrid INTEGER NOT NULL DEFAULT 1,
                supports_locked_wheels INTEGER NOT NULL DEFAULT 0,
                supports_damaged_vehicle INTEGER NOT NULL DEFAULT 0,
                supports_subsoil_access INTEGER NOT NULL DEFAULT 0,
                requires_manual_confirmation INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (provider_id, service_type_id, vehicle_category)
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS order_vehicle_requirements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL UNIQUE,
                vehicle_id INTEGER NOT NULL,
                vehicle_category TEXT,
                declared_vehicle_type TEXT,
                fuel_type TEXT,
                electric_vehicle INTEGER NOT NULL DEFAULT 0,
                hybrid_vehicle INTEGER NOT NULL DEFAULT 0,
                damaged_vehicle INTEGER,
                wheels_locked INTEGER,
                underground_location INTEGER,
                difficult_access INTEGER,
                spare_tire_available INTEGER,
                locking_bolt_present INTEGER,
                requires_platform INTEGER NOT NULL DEFAULT 0,
                manual_review_required INTEGER NOT NULL DEFAULT 0,
                verification_status TEXT,
                requirements_json TEXT,
                snapshot_version TEXT NOT NULL DEFAULT 'v1',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_vehicle_requirements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service_type_id INTEGER NOT NULL,
                vehicle_category TEXT,
                requires_platform INTEGER NOT NULL DEFAULT 0,
                requires_winch INTEGER NOT NULL DEFAULT 0,
                requires_dolly INTEGER NOT NULL DEFAULT 0,
                requires_battery_tester INTEGER NOT NULL DEFAULT 0,
                requires_jump_starter INTEGER NOT NULL DEFAULT 0,
                requires_hydraulic_jack INTEGER NOT NULL DEFAULT 0,
                minimum_unit_capacity_kg REAL,
                electric_certification_required INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (service_type_id, vehicle_category)
            )
        ");

        // Pacote L2 (Etapa 13 — precificação por zona/cidade, 26/07/2026):
        // ZonePricingServiceTest e ConversionServiceTest (cobrança
        // complementar de reboque) precisam destas tabelas — espelham
        // install/migration_pricing_zones_v1.sql e
        // install/migration_service_catalog_v1.sql (versão simplificada
        // pro harness SQLite, sem FKs).
        // §HIBRIDO-COMPLEMENTAR-01 — ConversionServiceHibridoTest precisa
        // revalidar capacidade de reboque do prestador (guinchoValidoParaHibrido/
        // ProviderCapability::possuiCapacidadeReboqueAprovada); espelha
        // install/migration_service_catalog_v1.sql (versão simplificada).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS provider_capabilities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                provider_id INTEGER NOT NULL,
                service_type_id INTEGER NOT NULL,
                enabled INTEGER NOT NULL DEFAULT 0,
                approval_status TEXT NOT NULL DEFAULT 'PENDING',
                base_price REAL,
                price_per_km REAL,
                price_per_minute REAL,
                night_surcharge REAL,
                holiday_surcharge REAL,
                coverage_radius_km INTEGER,
                estimated_duration_minutes INTEGER,
                requires_inventory INTEGER NOT NULL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (provider_id, service_type_id)
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT,
                attendance_mode TEXT NOT NULL DEFAULT 'ON_SITE',
                requires_destination INTEGER NOT NULL DEFAULT 0,
                allows_conversion_to_towing INTEGER NOT NULL DEFAULT 0,
                requires_diagnostic INTEGER NOT NULL DEFAULT 0,
                requires_parts INTEGER NOT NULL DEFAULT 0,
                requires_before_evidence INTEGER NOT NULL DEFAULT 1,
                requires_after_evidence INTEGER NOT NULL DEFAULT 1,
                estimated_duration_minutes INTEGER NOT NULL DEFAULT 30,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT,
                updated_at TEXT
            )
        ");
        // §COBERTURA-RAIO-01 (06/08/2026): schema estava travado na Etapa 13
        // original (city_id/code/name/polygon_geojson só) — desde
        // migration_pricing_zones_v2_cidade.sql / v3_expansao.sql / v5_metas.sql
        // (produção real, MySQL) a tabela ganhou cidade_id, ordem_expansao,
        // status_expansao, bairros_referencia e as colunas meta_* usadas por
        // TerritorioMetasService/CoberturaService. Sem isso aqui, qualquer teste
        // que toque nessas colunas falha com "no such column" contra o SQLite
        // de testes, mesmo com o código de produção correto.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cidades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                uf TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                ativo INTEGER NOT NULL DEFAULT 1,
                criado_em TEXT,
                atualizado_em TEXT
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pricing_zones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                city_id TEXT,
                cidade_id INTEGER,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                polygon_geojson TEXT,
                active INTEGER NOT NULL DEFAULT 1,
                ordem_expansao INTEGER,
                status_expansao TEXT NOT NULL DEFAULT 'nao_ativada',
                bairros_referencia TEXT,
                meta_prestadores_min INTEGER,
                meta_prestadores_max INTEGER,
                meta_disponibilidade_simultanea INTEGER,
                meta_atendimentos_mes1 INTEGER,
                meta_atendimentos_mes2 INTEGER,
                meta_atendimentos_mes3 INTEGER,
                meta_margem_operacional_min_pct REAL,
                meta_margem_pos_marketing_min_pct REAL,
                meta_composicao_prestadores TEXT,
                meta_ciclo_inicio TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_price_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pricing_zone_id INTEGER NOT NULL,
                service_type_id INTEGER NOT NULL,
                vehicle_category TEXT,
                base_customer_price REAL NOT NULL DEFAULT 0,
                minimum_customer_price REAL NOT NULL DEFAULT 0,
                maximum_customer_price REAL,
                provider_base_amount REAL NOT NULL DEFAULT 0,
                platform_fee_type TEXT NOT NULL DEFAULT 'PERCENTAGE',
                platform_fee_value REAL NOT NULL DEFAULT 0,
                included_distance_km REAL NOT NULL DEFAULT 0,
                extra_distance_price REAL NOT NULL DEFAULT 0,
                included_minutes INTEGER NOT NULL DEFAULT 0,
                extra_minute_price REAL NOT NULL DEFAULT 0,
                night_multiplier REAL NOT NULL DEFAULT 1.0,
                holiday_multiplier REAL NOT NULL DEFAULT 1.0,
                effective_from TEXT,
                effective_until TEXT,
                active INTEGER NOT NULL DEFAULT 1,
                version INTEGER NOT NULL DEFAULT 1,
                created_at TEXT,
                updated_at TEXT
            )
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_pricing_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service_type_id INTEGER NOT NULL,
                cidade_id INTEGER,
                base_fee REAL NOT NULL DEFAULT 0,
                pickup_km_price REAL NOT NULL DEFAULT 0,
                tow_km_price REAL,
                labor_fee REAL NOT NULL DEFAULT 0,
                minimum_price REAL NOT NULL DEFAULT 0,
                night_multiplier REAL NOT NULL DEFAULT 1.0,
                holiday_multiplier REAL NOT NULL DEFAULT 1.0,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        // Seeds de configuração padrão
        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");
        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");
    }

    return $pdo;
}

// ─── include_path: resolve 'require_once GeoService.php' em RankingService ──
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../src/Services/');

// ─── Classes sob teste ────────────────────────────────────────────────────────
require_once __DIR__ . '/../src/Services/PixService.php';
require_once __DIR__ . '/../src/Services/EstornoService.php';

// WebhookController e suas dependências (config.php emitirá E_WARNING para
// constantes já definidas acima — não-fatal; os valores do bootstrap prevalecem)
require_once __DIR__ . '/../src/Models/Pagamento.php';
require_once __DIR__ . '/../src/Models/Pedido.php';
require_once __DIR__ . '/../src/Models/Configuracao.php';
require_once __DIR__ . '/../src/Models/Guincho.php';
require_once __DIR__ . '/../src/Models/SimulationRun.php';
require_once __DIR__ . '/../src/Models/SimulationStep.php';
require_once __DIR__ . '/../src/Services/SimulationService.php';
require_once __DIR__ . '/../src/Services/AvaliacaoService.php';
require_once __DIR__ . '/../src/Services/HealthService.php';

$_errLevel = error_reporting(E_ALL & ~E_WARNING);
require_once __DIR__ . '/../src/Controllers/WebhookController.php';
error_reporting($_errLevel);
