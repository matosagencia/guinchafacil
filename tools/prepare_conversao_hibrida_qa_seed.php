<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Usuario.php';
require_once dirname(__DIR__) . '/src/Models/Guincho.php';
require_once dirname(__DIR__) . '/src/Models/Veiculo.php';
require_once dirname(__DIR__) . '/src/Models/Pedido.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ServiceCategory.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ServiceType.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ProviderCapability.php';
require_once dirname(__DIR__) . '/src/Services/POR/ProofOfRoadService.php';

// Seed para E2E-HIBRIDO-001/002 (qa/suites/conversao-hibrida-complementar.spec.ts)
// — pane elétrica atendida por um prestador HÍBRIDO (já nasce com
// ProviderCapability aprovada tanto para ELECTRICAL_DIAGNOSIS quanto para
// TOW_CAR, além de guinchos.reboque_aprovado=1 quando a coluna existir).
// Diferente do seed de E2E-SOCORRO-001 (especialista puro, sem reboque —
// conversão SOLTA o pedido pra fila comum), aqui a conversão deve manter o
// MESMO prestador vinculado (ConversionService::finalizarCaminhoHibrido) e
// cobrar um complementar de reboque sem trocar de guincho_id — ver
// §HIBRIDO-COMPLEMENTAR-01 em install/migration_hibrido_complementar_v1.sql.
//
// Subcomandos (argv[1]):
//   (nenhum) / 'setup'
//     -> cria/reseta cliente + veículo + pedido em 'aguardando_pagamento'
//        (ON_SITE, ELECTRICAL_DIAGNOSIS) e um guincho HÍBRIDO aprovado nas
//        duas capacidades. Devolve pedido_id, guincho_hibrido_id e
//        credenciais. Também REAPROVA a capacidade de reboque do híbrido
//        caso um teste anterior (suspender-capacidade-reboque) a tenha
//        suspendido — setup precisa ser idempotente mesmo depois do
//        cenário de downgrade.
//   'atribuir-hibrido <pedido_id>'
//     -> atribui o guincho híbrido ao pedido (já em 'aguardando_guincho'
//        pelo webhook real do primeiro pagamento) e avança para
//        'a_caminho' — mesmo padrão de qaSocorroAtribuirGuinchoEAvancar em
//        prepare_atendimento_socorro_qa_seed.php, sem depender do
//        algoritmo de matching real (o cenário testa a CONVERSÃO, não o
//        matching).
//   'suspender-capacidade-reboque <guincho_id>'
//     -> suspende (ProviderCapability::suspender) a capacidade TOW_CAR do
//        guincho híbrido — usado no cenário E2E-HIBRIDO em que o
//        prestador perde a aptidão de reboque ANTES de pagar o
//        complementar, forçando o downgrade para fila comum
//        (PedidoTransitionService::approvePayment -> guinchoAindaValidoParaHibrido).
//   'reaprovar-capacidade-reboque <guincho_id>'
//     -> reverte o subcomando acima (aprova de novo) — chamado pelo
//        próprio 'setup' para manter os testes independentes/reexecutáveis.

const QA_CLIENTE_EMAIL = 'pw_hibrido_cliente@guinchafacil.com';
const QA_HIBRIDO_EMAIL = 'pw_hibrido_guincho@guinchafacil.com';
const QA_PASSWORD = 'test123';
const QA_DESCRICAO = 'Seed local E2E-HIBRIDO-001 (pane elétrica -> conversão híbrida com complementar)';

// §ROTA-REUSO-01: reaproveita EXATAMENTE os mesmos pontos de
// qa/helpers/atendimento.ts (rjEspecialistaApproachRoute / rjDeliveryRoute)
// em vez de inventar coordenadas novas — essas rotas já foram amostradas
// via OSRM (ruas reais, "Avenida Ayrton Senna") e validadas pelos specs
// RJ-TOW-00x e E2E-SOCORRO-001. Origem = último ponto de
// rjEspecialistaApproachRoute (= primeiro ponto de rjDeliveryRoute);
// destino = último ponto de rjDeliveryRoute. Isso permite ao spec híbrido
// reusar as MESMAS rotas sem depender de um novo geocode/sampling.
const ORIGEM_LAT = -22.997682;
const ORIGEM_LNG = -43.36643;
const ORIGEM_ENDERECO = 'Avenida Ayrton Senna, Barra da Tijuca, Rio de Janeiro - RJ (QA híbrido)';

// Destino (oficina) coletado só no momento da conversão.
const DESTINO_LAT = -22.987421;
const DESTINO_LNG = -43.36559;
const DESTINO_ENDERECO = 'Avenida Ayrton Senna, 2200, Barra da Tijuca, Rio de Janeiro - RJ (QA híbrido)';

const DISTANCIA_KM = 1.2;

// Híbrido nasce no mesmo ponto de partida de rjEspecialistaApproachRoute[0]
// (1km da origem pela Av. Ayrton Senna) — mesma rota de aproximação real
// já usada por E2E-SOCORRO-001.
const HIBRIDO_INICIO_LAT = -22.999814;
const HIBRIDO_INICIO_LNG = -43.365498;

function qaHibridoHasColumn(string $table, string $column): bool
{
    $stmt = getPDO()->prepare(
        "SELECT COUNT(*)
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?"
    );
    $stmt->execute([DB_NAME, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function qaHibridoEnsureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
{
    $pdo = getPDO();
    $usuario = Usuario::buscarPorEmail($email);

    if (!$usuario) {
        $id = (int)Usuario::criar([
            'nome' => $nome,
            'email' => $email,
            'senha_hash' => password_hash(QA_PASSWORD, PASSWORD_BCRYPT),
            'telefone' => $telefone,
            'cpf' => $cpf,
            'tipo' => $tipo,
        ]);
        $usuario = Usuario::buscarPorId($id);
    }

    $pdo->prepare(
        "UPDATE usuarios
            SET senha_hash = ?, ativo = 1, tipo = ?, nome = ?, telefone = ?, cpf = ?
          WHERE id = ?"
    )->execute([
        password_hash(QA_PASSWORD, PASSWORD_BCRYPT),
        $tipo,
        $nome,
        $telefone,
        $cpf,
        (int)$usuario['id'],
    ]);

    return Usuario::buscarPorId((int)$usuario['id']) ?: $usuario;
}

/** Localiza o service_type ELECTRICAL_DIAGNOSIS já seedado (migration_service_catalog_v1.sql). */
function qaHibridoBuscarTipoServicoOnSite(): array
{
    $tipo = ServiceType::buscarPorCodigo('ELECTRICAL_DIAGNOSIS');
    if (!$tipo) {
        throw new RuntimeException(
            "Tipo de serviço 'ELECTRICAL_DIAGNOSIS' não encontrado — rode install/migration_service_catalog_v1.sql antes deste seed."
        );
    }
    if ((string)$tipo['attendance_mode'] !== 'ON_SITE' || empty($tipo['requires_diagnostic']) || empty($tipo['allows_conversion_to_towing'])) {
        throw new RuntimeException(
            "Tipo de serviço 'ELECTRICAL_DIAGNOSIS' não está configurado como esperado (ON_SITE + requires_diagnostic + allows_conversion_to_towing) — verifique o catálogo antes de rodar o teste."
        );
    }
    return $tipo;
}

/** Localiza o service_type TOW_CAR (attendance_mode=TOWING) já seedado. */
function qaHibridoBuscarTipoServicoReboque(): array
{
    $tipo = ServiceType::buscarPorCodigo('TOW_CAR');
    if (!$tipo) {
        throw new RuntimeException(
            "Tipo de serviço 'TOW_CAR' não encontrado — rode install/migration_service_catalog_v1.sql antes deste seed."
        );
    }
    if ((string)$tipo['attendance_mode'] !== 'TOWING') {
        throw new RuntimeException("Tipo de serviço 'TOW_CAR' não está com attendance_mode=TOWING — verifique o catálogo.");
    }
    return $tipo;
}

/**
 * Guincho HÍBRIDO: aprovado como prestador, reboque_aprovado=1 (coluna
 * legada, quando existir) E ProviderCapability aprovada nos DOIS
 * service_types (ELECTRICAL_DIAGNOSIS + TOW_CAR) — exatamente o par que
 * ConversionService::guinchoValidoParaHibrido() e
 * PedidoTransitionService::guinchoAindaValidoParaHibrido() checam
 * (aprovado + reboque_aprovado + ProviderCapability::possuiCapacidadeReboqueAprovada).
 */
function qaHibridoEnsureGuinchoHibrido(int $usuarioId, int $serviceTypeOnSiteId, int $serviceTypeReboqueId): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario($usuarioId);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '99988877766',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'QAH4001',
            'capacidade_ton' => 3.5,
            'raio_cobertura_km' => 30,
            'chave_pix' => 'qa-hibrido-guincho-chave-pix',
            'chave_pix_tipo' => 'email',
            'foto_veiculo' => null,
            'doc_cnh_frente' => null,
            'doc_cnh_verso' => null,
        ]);
        $guincho = Guincho::buscarPorId((int)$guinchoId);
    }

    $fields = [
        'aprovado = ?' => 1,
        'disponivel = ?' => 1,
        'lat_atual = ?' => HIBRIDO_INICIO_LAT,
        'lng_atual = ?' => HIBRIDO_INICIO_LNG,
        'chave_pix = ?' => 'qa-hibrido-guincho-chave-pix',
        'chave_pix_tipo = ?' => 'email',
    ];
    if (qaHibridoHasColumn('guinchos', 'oferece_reboque')) {
        $fields['oferece_reboque = ?'] = 1;
    }
    if (qaHibridoHasColumn('guinchos', 'reboque_aprovado')) {
        $fields['reboque_aprovado = ?'] = 1;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    // Capacidade ON_SITE (diagnóstico) — necessária pra atender o pedido
    // original de pane elétrica.
    $capOnSiteId = ProviderCapability::declarar((int)$guincho['id'], $serviceTypeOnSiteId, [
        'estimated_duration_minutes' => 40,
    ]);
    ProviderCapability::aprovar($capOnSiteId, 0);

    // Capacidade de REBOQUE — é ela que torna este prestador "híbrido" aos
    // olhos de ConversionService/PedidoTransitionService.
    $capReboqueId = ProviderCapability::declarar((int)$guincho['id'], $serviceTypeReboqueId, [
        'estimated_duration_minutes' => 45,
    ]);
    ProviderCapability::aprovar($capReboqueId, 0);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

function qaHibridoEnsureVeiculo(int $clienteId): array
{
    $veiculos = Veiculo::listarPorUsuario($clienteId);
    if (!empty($veiculos)) {
        return $veiculos[0];
    }

    $veiculoId = (int)Veiculo::criar($clienteId, [
        'placa' => 'QAH0C01',
        'marca' => 'Chevrolet',
        'modelo' => 'Onix',
        'ano' => 2021,
        'cor' => 'Prata',
        'tipo' => 'passeio',
    ]);

    $stmt = getPDO()->prepare("SELECT * FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function qaHibridoFindReusablePedido(int $clienteId): ?array
{
    $stmt = getPDO()->prepare(
        "SELECT *
           FROM pedidos
          WHERE cliente_id = ?
            AND descricao_problema = ?
          ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute([$clienteId, QA_DESCRICAO]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function qaHibridoResetPedidoParaAguardandoPagamento(array $pedido, int $serviceTypeId): array
{
    $pdo = getPDO();
    $fields = [
        'status = ?' => 'aguardando_pagamento',
        'guincho_id = ?' => null,
        'custo_estimado = ?' => 89.90,
        'lat_destino = ?' => ORIGEM_LAT,
        'lng_destino = ?' => ORIGEM_LNG,
        'endereco_destino = ?' => ORIGEM_ENDERECO,
        'distancia_km = ?' => DISTANCIA_KM,
        'service_type_id = ?' => $serviceTypeId,
        'attendance_mode = ?' => 'ON_SITE',
    ];
    if (qaHibridoHasColumn('pedidos', 'custo_final')) {
        $fields['custo_final = ?'] = 0;
    }
    if (qaHibridoHasColumn('pedidos', 'foto_plataforma')) {
        $fields['foto_plataforma = ?'] = null;
    }
    if (qaHibridoHasColumn('pedidos', 'foto_destino')) {
        $fields['foto_destino = ?'] = null;
    }
    if (qaHibridoHasColumn('pedidos', 'cancelado_por')) {
        $fields['cancelado_por = ?'] = null;
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$pedido['id'];
    $pdo->prepare($sql)->execute($params);

    foreach (['pedido_diagnosticos', 'pedido_orcamentos', 'pedido_conversoes'] as $tabelaOpcional) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
        );
        $stmt->execute([DB_NAME, $tabelaOpcional]);
        if ((int)$stmt->fetchColumn() > 0) {
            $pdo->prepare("DELETE FROM {$tabelaOpcional} WHERE pedido_id = ?")->execute([(int)$pedido['id']]);
        }
    }
    if (qaHibridoHasColumn('pedido_evidencias', 'pedido_id')) {
        $pdo->prepare('DELETE FROM pedido_evidencias WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    }
    $pdo->prepare('DELETE FROM pedido_localizacoes WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    $pdo->prepare('DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    $pdo->prepare('DELETE FROM pagamentos WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    // §HIBRIDO-COMPLEMENTAR-01: também limpa arquivamentos de rodadas
    // anteriores — senão um teste reexecutado acumula linhas de
    // pagamentos_arquivados de execuções passadas do mesmo pedido_id.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pagamentos_arquivados'");
    $stmt->execute([DB_NAME]);
    if ((int)$stmt->fetchColumn() > 0) {
        $pdo->prepare('DELETE FROM pagamentos_arquivados WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    }

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

function qaHibridoCreatePedido(int $clienteId, int $veiculoId, int $serviceTypeId): array
{
    $pedidoId = Pedido::criar([
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'tipo_problema' => 'Pane elétrica QA (E2E-HIBRIDO-001)',
        'descricao_problema' => QA_DESCRICAO,
        'lat_origem' => ORIGEM_LAT,
        'lng_origem' => ORIGEM_LNG,
        'endereco_origem' => ORIGEM_ENDERECO,
        // Placeholder = mesma coordenada da origem (mesmo padrão do seed de
        // E2E-SOCORRO-001) — destino real só é coletado se/quando a
        // conversão para reboque acontecer.
        'lat_destino' => ORIGEM_LAT,
        'lng_destino' => ORIGEM_LNG,
        'endereco_destino' => ORIGEM_ENDERECO,
        'distancia_km' => 0.0,
        'custo_estimado' => 89.90,
        'status' => 'aguardando_pagamento',
        'raio_atual_km' => 10,
        'score_minimo_atual' => 0.5,
    ]);

    if (!$pedidoId) {
        throw new RuntimeException('Falha ao criar pedido QA E2E-HIBRIDO-001.');
    }

    $pdo = getPDO();
    $pdo->prepare('UPDATE pedidos SET service_type_id = ?, attendance_mode = ? WHERE id = ?')
        ->execute([$serviceTypeId, 'ON_SITE', (int)$pedidoId]);

    return Pedido::buscarPorId((int)$pedidoId) ?: [];
}

function qaHibridoSeedInitialPoint(int $pedidoId, int $guinchoId, int $usuarioId, float $lat, float $lng): void
{
    $point = ProofOfRoadService::ingestPoint($pedidoId, $guinchoId, $usuarioId, [
        'latitude' => $lat,
        'longitude' => $lng,
        'accuracy_m' => 8,
        'speed_mps' => 0,
        'heading_deg' => 30,
        'device_timestamp' => (string)(time() * 1000),
        'sequence' => 1,
        'client_point_id' => 'qa-hibrido-seed-' . bin2hex(random_bytes(4)),
    ]);

    if (empty($point['ok']) || empty($point['accepted'])) {
        throw new RuntimeException('Falha ao criar ponto GPS inicial para seed QA E2E-HIBRIDO-001.');
    }
}

function qaHibridoAtribuirGuinchoEAvancar(int $pedidoId, int $guinchoId, int $usuarioIdParaGps, float $latInicio, float $lngInicio): array
{
    $pedido = Pedido::buscarPorId($pedidoId);
    if (!$pedido) {
        throw new RuntimeException("Pedido #{$pedidoId} não encontrado.");
    }
    if ((string)$pedido['status'] !== 'aguardando_guincho') {
        throw new RuntimeException(
            "Pedido #{$pedidoId} não está em 'aguardando_guincho' (está em '{$pedido['status']}') — " .
            "atribuição de guincho só faz sentido nesse ponto do fluxo real."
        );
    }

    Pedido::atribuirGuincho($pedidoId, $guinchoId);
    getPDO()->prepare("UPDATE pedidos SET status = 'a_caminho' WHERE id = ?")->execute([$pedidoId]);

    qaHibridoSeedInitialPoint($pedidoId, $guinchoId, $usuarioIdParaGps, $latInicio, $lngInicio);

    return Pedido::buscarPorId($pedidoId) ?: [];
}

try {
    $subcomando = $argv[1] ?? 'setup';

    if ($subcomando === 'setup') {
        $tipoOnSite = qaHibridoBuscarTipoServicoOnSite();
        $tipoReboque = qaHibridoBuscarTipoServicoReboque();

        $cliente = qaHibridoEnsureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA Híbrido', '21999994001', '11122233304');
        $hibridoUsuario = qaHibridoEnsureUsuario(QA_HIBRIDO_EMAIL, 'guincho', 'Guincho Híbrido QA', '21999994002', '10987654305');

        $hibrido = qaHibridoEnsureGuinchoHibrido((int)$hibridoUsuario['id'], (int)$tipoOnSite['id'], (int)$tipoReboque['id']);
        $veiculo = qaHibridoEnsureVeiculo((int)$cliente['id']);

        $pedido = qaHibridoFindReusablePedido((int)$cliente['id']);
        if ($pedido) {
            $pedido = qaHibridoResetPedidoParaAguardandoPagamento($pedido, (int)$tipoOnSite['id']);
        } else {
            $pedido = qaHibridoCreatePedido((int)$cliente['id'], (int)$veiculo['id'], (int)$tipoOnSite['id']);
        }

        echo json_encode([
            'ok' => true,
            'pedido_id' => (int)$pedido['id'],
            'status' => (string)$pedido['status'],
            'service_type_onsite_id' => (int)$tipoOnSite['id'],
            'service_type_reboque_id' => (int)$tipoReboque['id'],
            'cliente_email' => QA_CLIENTE_EMAIL,
            'hibrido_email' => QA_HIBRIDO_EMAIL,
            'hibrido_guincho_id' => (int)$hibrido['id'],
            'hibrido_usuario_id' => (int)$hibridoUsuario['id'],
            'destino_lat' => DESTINO_LAT,
            'destino_lng' => DESTINO_LNG,
            'destino_endereco' => DESTINO_ENDERECO,
            'cliente_url' => '/cliente/pedido/' . (int)$pedido['id'],
            'checkout_url' => '/pagamento/checkout/' . (int)$pedido['id'],
            'hibrido_url' => '/guincho/atendimento/' . (int)$pedido['id'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } elseif ($subcomando === 'atribuir-hibrido') {
        $pedidoId = (int)($argv[2] ?? 0);
        if ($pedidoId <= 0) {
            throw new RuntimeException('Uso: atribuir-hibrido <pedido_id>');
        }
        $hibridoUsuario = Usuario::buscarPorEmail(QA_HIBRIDO_EMAIL);
        if (!$hibridoUsuario) {
            throw new RuntimeException('Guincho híbrido QA não encontrado — rode o subcomando "setup" primeiro.');
        }
        $hibrido = Guincho::buscarPorUsuario((int)$hibridoUsuario['id']);
        if (!$hibrido) {
            throw new RuntimeException('Guincho híbrido QA não encontrado — rode o subcomando "setup" primeiro.');
        }

        $pedido = qaHibridoAtribuirGuinchoEAvancar(
            $pedidoId,
            (int)$hibrido['id'],
            (int)$hibridoUsuario['id'],
            HIBRIDO_INICIO_LAT,
            HIBRIDO_INICIO_LNG
        );

        echo json_encode([
            'ok' => true,
            'pedido_id' => (int)$pedido['id'],
            'status' => (string)$pedido['status'],
            'guincho_id' => (int)$hibrido['id'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } elseif ($subcomando === 'suspender-capacidade-reboque') {
        $guinchoId = (int)($argv[2] ?? 0);
        if ($guinchoId <= 0) {
            throw new RuntimeException('Uso: suspender-capacidade-reboque <guincho_id>');
        }
        $tipoReboque = qaHibridoBuscarTipoServicoReboque();
        $cap = ProviderCapability::buscar($guinchoId, (int)$tipoReboque['id']);
        if (!$cap) {
            throw new RuntimeException("Guincho #{$guinchoId} não tem capacidade TOW_CAR declarada — rode 'setup' primeiro.");
        }
        ProviderCapability::suspender((int)$cap['id'], 0);
        // Coluna legada — mantém coerente com o que ConversionService/
        // PedidoTransitionService também checam (array_key_exists antes de
        // ler o valor).
        if (qaHibridoHasColumn('guinchos', 'reboque_aprovado')) {
            getPDO()->prepare('UPDATE guinchos SET reboque_aprovado = 0 WHERE id = ?')->execute([$guinchoId]);
        }
        echo json_encode(['ok' => true, 'guincho_id' => $guinchoId, 'capacidade_reboque' => 'suspensa'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } elseif ($subcomando === 'reaprovar-capacidade-reboque') {
        $guinchoId = (int)($argv[2] ?? 0);
        if ($guinchoId <= 0) {
            throw new RuntimeException('Uso: reaprovar-capacidade-reboque <guincho_id>');
        }
        $tipoReboque = qaHibridoBuscarTipoServicoReboque();
        $cap = ProviderCapability::buscar($guinchoId, (int)$tipoReboque['id']);
        if (!$cap) {
            throw new RuntimeException("Guincho #{$guinchoId} não tem capacidade TOW_CAR declarada — rode 'setup' primeiro.");
        }
        ProviderCapability::aprovar((int)$cap['id'], 0);
        if (qaHibridoHasColumn('guinchos', 'reboque_aprovado')) {
            getPDO()->prepare('UPDATE guinchos SET reboque_aprovado = 1 WHERE id = ?')->execute([$guinchoId]);
        }
        echo json_encode(['ok' => true, 'guincho_id' => $guinchoId, 'capacidade_reboque' => 'aprovada'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        throw new RuntimeException("Subcomando desconhecido: {$subcomando}");
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
