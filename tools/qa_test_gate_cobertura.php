<?php

declare(strict_types=1);

// Diagnostic script used by QA only. No browser access.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Usuario.php';
require_once dirname(__DIR__) . '/src/Models/Guincho.php';
require_once dirname(__DIR__) . '/src/Services/CoberturaService.php';

const QA_GATE_EMAIL = 'qa.gate.cobertura@guinchafacil.com';
const QA_GATE_PASSWORD = 'test123';
const QA_GATE_NOME = 'Guincho QA Gate Cobertura';
const QA_GATE_TELEFONE = '21999990090';
const QA_GATE_CPF = '10987654333';
const QA_GATE_LAT = -23.55052;
const QA_GATE_LNG = -46.63331;

function saida(array $dados): void
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function ensureGateGuincho(): int
{
    $pdo = getPDO();

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([QA_GATE_EMAIL]);
    $usuarioId = (int)$stmt->fetchColumn();

    $senhaHash = password_hash(QA_GATE_PASSWORD, PASSWORD_BCRYPT);
    if ($usuarioId > 0) {
        $pdo->prepare('UPDATE usuarios SET nome = ?, senha_hash = ?, telefone = ?, cpf = ?, tipo = ?, ativo = 1 WHERE id = ?')
            ->execute([QA_GATE_NOME, $senhaHash, QA_GATE_TELEFONE, QA_GATE_CPF, 'guincho', $usuarioId]);
    } else {
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, ativo) VALUES (?,?,?,?,?,?,1)')
            ->execute([QA_GATE_NOME, QA_GATE_EMAIL, $senhaHash, QA_GATE_TELEFONE, QA_GATE_CPF, 'guincho']);
        $usuarioId = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT id FROM guinchos WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $guinchoId = (int)$stmt->fetchColumn();
    if ($guinchoId <= 0) {
        $guinchoId = (int)Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '33344455566',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'QAGT001',
            'cidade_placa' => 'Sao Paulo',
            'uf_placa' => 'SP',
            'capacidade_ton' => 8.0,
            'raio_cobertura_km' => 50,
            'chave_pix' => QA_GATE_EMAIL,
            'chave_pix_tipo' => 'email',
            'lat_operacao' => QA_GATE_LAT,
            'lng_operacao' => QA_GATE_LNG,
        ]);
    }

    Guincho::solicitarReboque($guinchoId, [
        'placa_guincho' => 'QAGT001',
        'cidade_placa' => 'Sao Paulo',
        'uf_placa' => 'SP',
        'capacidade_ton' => 8.0,
        'cnh_numero' => '33344455566',
        'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
    ]);
    Guincho::aprovar($guinchoId);

    $pdo->prepare('UPDATE guinchos SET reboque_aprovado = 1, aprovado = 1, disponivel = 1, lat_atual = ?, lng_atual = ?, lat_operacao = ?, lng_operacao = ?, raio_cobertura_km = 50 WHERE id = ?')
        ->execute([QA_GATE_LAT, QA_GATE_LNG, QA_GATE_LAT, QA_GATE_LNG, $guinchoId]);

    return $guinchoId;
}

try {
    $guinchoId = ensureGateGuincho();

    $foraDeCobertura = CoberturaService::existeGuinchoAlcancavel(-3.4653, -62.2159, 'TOWING', null);
    $dentroDeCobertura = CoberturaService::existeGuinchoAlcancavel(QA_GATE_LAT, QA_GATE_LNG, 'TOWING', null);

    $ok = ($foraDeCobertura === false) && ($dentroDeCobertura === true);

    saida([
        'ok' => $ok,
        'guincho_id' => $guinchoId,
        'fora_de_cobertura_bloqueou' => $foraDeCobertura === false,
        'dentro_de_cobertura_liberou' => $dentroDeCobertura === true,
        'mensagem' => $ok
            ? 'Gate de cobertura funcionando: bloqueia fora do raio, libera dentro dele.'
            : 'FALHOU: ponto dentro da cobertura QA foi bloqueado.',
    ]);

    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    saida([
        'ok' => false,
        'erro' => $e->getMessage(),
    ]);
    exit(1);
}
