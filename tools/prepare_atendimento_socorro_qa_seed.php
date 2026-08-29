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
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';

// Seed para E2E-SOCORRO-001 (qa/suites/atendimento-socorro-conversao.spec.ts)
// — pane elétrica atendida por especialista puro (sem capacidade de
// reboque), diagnóstico real REQUER_REBOQUE, cliente aprova a conversão via
// rota real (/cliente/conversao/decidir), e como o especialista NÃO tem
// ProviderCapability aprovada de TOWING, ConversionService::decidirConversao
// libera o pedido de volta pra fila (attendance_mode vira 'TOWING' — ver
// PedidoTransitionService linha ~193) — este script então atribui um
// guincho COMUM (reboque_aprovado=1) pra concluir o reboque, igual ao
// RJ-TOW-001. Reaproveita o tipo de serviço 'ELECTRICAL_DIAGNOSIS'
// ('Diagnóstico de Pane Elétrica') já seedado por
// install/migration_service_catalog_v1.sql — não cria um novo tipo.
//
// Diferente dos seeds RJ-TOW (que já nascem em 'a_caminho'), o pedido aqui
// nasce em 'aguardando_pagamento' SEM guincho atribuído — o pagamento real
// (Payment Brick, mesmo padrão do E2E-PAY-004) e a atribuição do
// especialista são feitos por chamadas SEPARADAS deste script
// (subcomandos), porque a atribuição só faz sentido depois que o pagamento
// aprovar de verdade (o pedido precisa estar em 'aguardando_guincho').
//
// Subcomandos (argv[1]):
//   (nenhum) / 'setup'         -> cria/reseta cliente+veículo+pedido em
//                                 aguardando_pagamento, especialista puro
//                                 (sem reboque) e guincho comum de reboque.
//                                 Devolve pedido_id e credenciais.
//   'atribuir-especialista <pedido_id>'
//                              -> atribui o especialista ao pedido (já em
//                                 aguardando_guincho pelo webhook real do
//                                 pagamento) e avança pra 'a_caminho'.
//   'atribuir-reboque <pedido_id>'
//                              -> atribui o guincho comum de reboque ao
//                                 pedido (já solto de volta pra fila como
//                                 TOWING pela conversão real) e avança pra
//                                 'a_caminho'.

const QA_CLIENTE_EMAIL = 'pw_socorro_cliente@guinchafacil.com';
const QA_ESPECIALISTA_EMAIL = 'pw_socorro_especialista@guinchafacil.com';
const QA_GUINCHO_EMAIL = 'pw_socorro_guincho@guinchafacil.com';
const QA_PASSWORD = 'test123';
const QA_DESCRICAO = 'Seed local E2E-SOCORRO-001 (pane elétrica -> conversão para reboque)';

// Origem (onde o cliente está com a pane elétrica) — Av. Ayrton Senna,
// mesma região dos seeds RJ-TOW, mas endereço próprio pra não colidir.
const ORIGEM_LAT = -22.997682;
const ORIGEM_LNG = -43.36643;
const ORIGEM_ENDERECO = 'Avenida Ayrton Senna, Barra da Tijuca, Rio de Janeiro - RJ';

// Destino (oficina, só relevante depois da conversão para reboque).
const DESTINO_LAT = -22.987421;
const DESTINO_LNG = -43.36559;
const DESTINO_ENDERECO = 'Avenida Ayrton Senna, 2200, Barra da Tijuca, Rio de Janeiro - RJ';

const DISTANCIA_KM = 1.2;

// Especialista nasce no mesmo ponto de partida de rjEspecialistaApproachRoute
// (qa/helpers/atendimento.ts) — 1000m da origem pela Av. Ayrton Senna — para
// que o spec possa reaproveitar a MESMA rota amostrada via OSRM em vez de
// inventar uma nova sequência de pontos.
const ESPECIALISTA_INICIO_LAT = -22.999814;
const ESPECIALISTA_INICIO_LNG = -43.365498;

// Guincho comum (assume DEPOIS da conversão) nasce a 700m da origem —
// mesmo ponto de partida do RJ-TOW-001.
const GUINCHO_INICIO_LAT = -22.999862;
const GUINCHO_INICIO_LNG = -43.36489;

function qaSocorroHasColumn(string $table, string $column): bool
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

function qaSocorroEnsureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
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
function qaSocorroBuscarTipoServico(): array
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

/** Especialista puro: aprovado como prestador, SEM oferece_reboque/reboque_aprovado, com ProviderCapability aprovada só para ELECTRICAL_DIAGNOSIS. */
function qaSocorroEnsureEspecialista(int $usuarioId, int $serviceTypeId): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario($usuarioId);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '44455566677',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => '',
            'capacidade_ton' => 0,
            'raio_cobertura_km' => 30,
            'chave_pix' => 'qa-socorro-especialista-chave-pix',
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
        'lat_atual = ?' => ESPECIALISTA_INICIO_LAT,
        'lng_atual = ?' => ESPECIALISTA_INICIO_LNG,
        'chave_pix = ?' => 'qa-socorro-especialista-chave-pix',
        'chave_pix_tipo = ?' => 'email',
    ];
    // Nunca deixa este prestador virar apto a reboque — é o ponto central
    // do cenário (conversão precisa soltar o pedido pra outro guincho).
    if (qaSocorroHasColumn('guinchos', 'oferece_reboque')) {
        $fields['oferece_reboque = ?'] = 0;
    }
    if (qaSocorroHasColumn('guinchos', 'reboque_aprovado')) {
        $fields['reboque_aprovado = ?'] = 0;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    $capabilityId = ProviderCapability::declarar((int)$guincho['id'], $serviceTypeId, [
        'estimated_duration_minutes' => 40,
    ]);
    // Aprovação administrativa real (Etapa 2 do domínio) — admin_id=0 é
    // aceitável em contexto de seed CLI (sem sessão admin de verdade), só
    // grava auditoria com esse id.
    ProviderCapability::aprovar($capabilityId, 0);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

/** Guincho comum de reboque — mesmo padrão do RJ-TOW-001 (reboque_aprovado=1 direto). */
function qaSocorroEnsureGuinchoReboque(int $usuarioId): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario($usuarioId);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '55566677788',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'QAS3001',
            'capacidade_ton' => 3.5,
            'raio_cobertura_km' => 30,
            'chave_pix' => 'qa-socorro-guincho-chave-pix',
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
        'lat_atual = ?' => GUINCHO_INICIO_LAT,
        'lng_atual = ?' => GUINCHO_INICIO_LNG,
        'chave_pix = ?' => 'qa-socorro-guincho-chave-pix',
        'chave_pix_tipo = ?' => 'email',
    ];
    if (qaSocorroHasColumn('guinchos', 'oferece_reboque')) {
        $fields['oferece_reboque = ?'] = 1;
    }
    if (qaSocorroHasColumn('guinchos', 'reboque_aprovado')) {
        $fields['reboque_aprovado = ?'] = 1;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

function qaSocorroEnsureVeiculo(int $clienteId): array
{
    $veiculos = Veiculo::listarPorUsuario($clienteId);
    if (!empty($veiculos)) {
        return $veiculos[0];
    }

    $veiculoId = (int)Veiculo::criar($clienteId, [
        'placa' => 'QAS0C01',
        'marca' => 'Fiat',
        'modelo' => 'Argo',
        'ano' => 2022,
        'cor' => 'Branco',
        'tipo' => 'passeio',
    ]);

    $stmt = getPDO()->prepare("SELECT * FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function qaSocorroFindReusablePedido(int $clienteId): ?array
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

function qaSocorroResetPedidoParaAguardandoPagamento(array $pedido, int $serviceTypeId): array
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
    if (qaSocorroHasColumn('pedidos', 'custo_final')) {
        $fields['custo_final = ?'] = 0;
    }
    if (qaSocorroHasColumn('pedidos', 'foto_plataforma')) {
        $fields['foto_plataforma = ?'] = null;
    }
    if (qaSocorroHasColumn('pedidos', 'foto_destino')) {
        $fields['foto_destino = ?'] = null;
    }
    if (qaSocorroHasColumn('pedidos', 'cancelado_por')) {
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
    if (qaSocorroHasColumn('pedido_evidencias', 'pedido_id')) {
        $pdo->prepare('DELETE FROM pedido_evidencias WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    }
    $pdo->prepare('DELETE FROM pedido_localizacoes WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    $pdo->prepare('DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    $pdo->prepare("DELETE FROM pagamentos WHERE pedido_id = ?")->execute([(int)$pedido['id']]);

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

function qaSocorroCreatePedido(int $clienteId, int $veiculoId, int $serviceTypeId): array
{
    $pedidoId = Pedido::criar([
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'tipo_problema' => 'Pane elétrica QA (E2E-SOCORRO-001)',
        'descricao_problema' => QA_DESCRICAO,
        'lat_origem' => ORIGEM_LAT,
        'lng_origem' => ORIGEM_LNG,
        'endereco_origem' => ORIGEM_ENDERECO,
        // Placeholder = mesma coordenada da origem: pedido de socorro no
        // local (ELECTRICAL_DIAGNOSIS, requires_destination=0) não coleta
        // destino real na criação — só quando (e se) a conversão para
        // reboque acontecer é que o endereço real (DESTINO_LAT/LNG) é
        // coletado de verdade, via ConversionService (ver spec).
        'lat_destino' => ORIGEM_LAT,
        'lng_destino' => ORIGEM_LNG,
        'endereco_destino' => ORIGEM_ENDERECO,
        'distancia_km' => 0.0,
        // Valor inicial do socorro no local (deslocamento + diagnóstico) —
        // NÃO cobre reboque (ver §COBRANCA-REBOQUE-01 em ConversionService).
        // O valor real do reboque (se a conversão acontecer) é recalculado
        // e sobrescrito em custo_estimado só naquele momento.
        'custo_estimado' => 89.90,
        'status' => 'aguardando_pagamento',
        'raio_atual_km' => 10,
        'score_minimo_atual' => 0.5,
    ]);

    if (!$pedidoId) {
        throw new RuntimeException('Falha ao criar pedido QA E2E-SOCORRO-001.');
    }

    $pdo = getPDO();
    $pdo->prepare('UPDATE pedidos SET service_type_id = ?, attendance_mode = ? WHERE id = ?')
        ->execute([$serviceTypeId, 'ON_SITE', (int)$pedidoId]);

    return Pedido::buscarPorId((int)$pedidoId) ?: [];
}

function qaSocorroSeedInitialPoint(int $pedidoId, int $guinchoId, int $usuarioId, float $lat, float $lng): void
{
    $point = ProofOfRoadService::ingestPoint($pedidoId, $guinchoId, $usuarioId, [
        'latitude' => $lat,
        'longitude' => $lng,
        'accuracy_m' => 8,
        'speed_mps' => 0,
        'heading_deg' => 30,
        'device_timestamp' => (string)(time() * 1000),
        'sequence' => 1,
        'client_point_id' => 'qa-socorro-seed-' . bin2hex(random_bytes(4)),
    ]);

    if (empty($point['ok']) || empty($point['accepted'])) {
        throw new RuntimeException('Falha ao criar ponto GPS inicial para seed QA E2E-SOCORRO-001.');
    }
}

function qaSocorroAtribuirGuinchoEAvancar(int $pedidoId, int $guinchoId, int $usuarioIdParaGps, float $latInicio, float $lngInicio): array
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

    qaSocorroSeedInitialPoint($pedidoId, $guinchoId, $usuarioIdParaGps, $latInicio, $lngInicio);

    return Pedido::buscarPorId($pedidoId) ?: [];
}

try {
    $subcomando = $argv[1] ?? 'setup';

    if ($subcomando === 'setup') {
        $tipoServico = qaSocorroBuscarTipoServico();

        $cliente = qaSocorroEnsureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA Socorro', '21999993001', '11122233303');
        $especialistaUsuario = qaSocorroEnsureUsuario(QA_ESPECIALISTA_EMAIL, 'guincho', 'Especialista QA Socorro', '21999993002', '10987654303');
        $guinchoUsuario = qaSocorroEnsureUsuario(QA_GUINCHO_EMAIL, 'guincho', 'Guincho QA Socorro', '21999993003', '10987654304');

        $especialista = qaSocorroEnsureEspecialista((int)$especialistaUsuario['id'], (int)$tipoServico['id']);
        $guinchoReboque = qaSocorroEnsureGuinchoReboque((int)$guinchoUsuario['id']);
        $veiculo = qaSocorroEnsureVeiculo((int)$cliente['id']);

        $pedido = qaSocorroFindReusablePedido((int)$cliente['id']);
        if ($pedido) {
            $pedido = qaSocorroResetPedidoParaAguardandoPagamento($pedido, (int)$tipoServico['id']);
        } else {
            $pedido = qaSocorroCreatePedido((int)$cliente['id'], (int)$veiculo['id'], (int)$tipoServico['id']);
        }

        echo json_encode([
            'ok' => true,
            'pedido_id' => (int)$pedido['id'],
            'status' => (string)$pedido['status'],
            'service_type_id' => (int)$tipoServico['id'],
            'cliente_email' => QA_CLIENTE_EMAIL,
            'especialista_email' => QA_ESPECIALISTA_EMAIL,
            'especialista_guincho_id' => (int)$especialista['id'],
            'guincho_reboque_email' => QA_GUINCHO_EMAIL,
            'guincho_reboque_id' => (int)$guinchoReboque['id'],
            'especialista_usuario_id' => (int)$especialistaUsuario['id'],
            'guincho_usuario_id' => (int)$guinchoUsuario['id'],
            'cliente_url' => '/cliente/pedido/' . (int)$pedido['id'],
            'checkout_url' => '/pagamento/checkout/' . (int)$pedido['id'],
            'especialista_url' => '/guincho/atendimento/' . (int)$pedido['id'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } elseif ($subcomando === 'atribuir-especialista') {
        $pedidoId = (int)($argv[2] ?? 0);
        if ($pedidoId <= 0) {
            throw new RuntimeException('Uso: atribuir-especialista <pedido_id>');
        }
        $especialistaUsuario = Usuario::buscarPorEmail(QA_ESPECIALISTA_EMAIL);
        if (!$especialistaUsuario) {
            throw new RuntimeException('Especialista QA não encontrado — rode o subcomando "setup" primeiro.');
        }
        $especialista = Guincho::buscarPorUsuario((int)$especialistaUsuario['id']);
        if (!$especialista) {
            throw new RuntimeException('Guincho do especialista QA não encontrado — rode o subcomando "setup" primeiro.');
        }

        $pedido = qaSocorroAtribuirGuinchoEAvancar(
            $pedidoId,
            (int)$especialista['id'],
            (int)$especialistaUsuario['id'],
            ESPECIALISTA_INICIO_LAT,
            ESPECIALISTA_INICIO_LNG
        );

        echo json_encode([
            'ok' => true,
            'pedido_id' => (int)$pedido['id'],
            'status' => (string)$pedido['status'],
            'guincho_id' => (int)$especialista['id'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } elseif ($subcomando === 'atribuir-reboque') {
        $pedidoId = (int)($argv[2] ?? 0);
        if ($pedidoId <= 0) {
            throw new RuntimeException('Uso: atribuir-reboque <pedido_id>');
        }
        $guinchoUsuario = Usuario::buscarPorEmail(QA_GUINCHO_EMAIL);
        if (!$guinchoUsuario) {
            throw new RuntimeException('Guincho de reboque QA não encontrado — rode o subcomando "setup" primeiro.');
        }
        $guincho = Guincho::buscarPorUsuario((int)$guinchoUsuario['id']);
        if (!$guincho) {
            throw new RuntimeException('Guincho de reboque QA não encontrado — rode o subcomando "setup" primeiro.');
        }

        $pedido = qaSocorroAtribuirGuinchoEAvancar(
            $pedidoId,
            (int)$guincho['id'],
            (int)$guinchoUsuario['id'],
            GUINCHO_INICIO_LAT,
            GUINCHO_INICIO_LNG
        );

        echo json_encode([
            'ok' => true,
            'pedido_id' => (int)$pedido['id'],
            'status' => (string)$pedido['status'],
            'guincho_id' => (int)$guincho['id'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } elseif ($subcomando === 'ligar-road-match') {
        // Liga POR-VAL-010 (aderência à malha viária, ver PorThresholds) só
        // para esta rodada de teste — aditivo/opt-in, nunca ligado por
        // padrão em produção (ver comentário em PorThresholds::roadMatchEnabled()).
        Configuracao::set('por_road_match_enabled', '1', 'QA E2E-SOCORRO-001 — bônus POR-VAL-010');
        echo json_encode(['ok' => true, 'por_road_match_enabled' => true], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } elseif ($subcomando === 'desligar-road-match') {
        Configuracao::set('por_road_match_enabled', '0', 'QA E2E-SOCORRO-001 — restaurado ao padrão desligado');
        echo json_encode(['ok' => true, 'por_road_match_enabled' => false], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        throw new RuntimeException("Subcomando desconhecido: {$subcomando}");
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
