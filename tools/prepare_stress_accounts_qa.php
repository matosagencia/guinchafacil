<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Usuario.php';
require_once dirname(__DIR__) . '/src/Models/Guincho.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ServiceType.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ProviderCapability.php';

// Cria, DIRETO no banco (sem passar pela UI), um lote de contas para os
// specs de stress que precisam de volume (ex.: stress-concorrencia.spec.ts
// com 50-100 prestadores tentando aceitar o mesmo pedido). Diferente de
// onboarding-stress.spec.ts (que exercita o cadastro REAL via UI, incluindo
// upload de documentos, e por isso é o teste que valida o formulário de
// cadastro em si), este script existe só para dar massa rápida a testes que
// não estão testando o cadastro — estão testando o que acontece DEPOIS que
// as contas já existem.
//
// Uso: php prepare_stress_accounts_qa.php [runTag] [clientes] [guinchos] [multisservico] [especialistas]
// Padrão: 5 de cada, runTag = timestamp atual.

$runTag = trim((string)($argv[1] ?? '')) ?: (string)(time() * 1000);
$nClientes = isset($argv[2]) ? max(0, (int)$argv[2]) : 5;
$nGuinchos = isset($argv[3]) ? max(0, (int)$argv[3]) : 5;
$nMulti = isset($argv[4]) ? max(0, (int)$argv[4]) : 5;
$nEspecialistas = isset($argv[5]) ? max(0, (int)$argv[5]) : 5;

function qaStressEnsureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf, string $senha): int
{
    $usuario = Usuario::buscarPorEmail($email);
    if ($usuario) {
        return (int)$usuario['id'];
    }
    return (int)Usuario::criar([
        'nome' => $nome,
        'email' => $email,
        'senha_hash' => password_hash($senha, PASSWORD_BCRYPT),
        'telefone' => $telefone,
        'cpf' => $cpf,
        'tipo' => $tipo,
    ]);
}

/**
 * Bug real (stress agregado, 31/07/2026 — 4 workers em paralelo): qaStressCpf()
 * só dependia do índice dentro da categoria (ex.: 200_000_000 + $i pros
 * guinchos), NUNCA do runTag — diferente do e-mail, que já é único por run.
 * Resultado: toda nova execução (ou worker paralelo concorrente) gerava o
 * MESMO CPF pro guincho #1, #2... de execuções anteriores/simultâneas,
 * batendo na UNIQUE KEY de `cpf` ("Duplicate entry"). Este helper mistura o
 * runTag no seed (mesma ideia do e-mail), mantendo determinismo por run
 * (reexecutar o MESMO runTag continua dando o mesmo CPF) sem colidir entre
 * runs diferentes.
 */
function qaStressCpfSeed(string $runTag, int $categoriaOffset, int $i): int
{
    return $categoriaOffset + $i + ((int)(crc32($runTag) % 100_000) * 1000);
}

function qaStressCpf(int $seed): string
{
    $base = str_pad((string)($seed % 1_000_000_000), 9, '0', STR_PAD_LEFT);
    $digits = array_map('intval', str_split($base));
    $factor = count($digits) + 1;
    $sum = 0;
    foreach ($digits as $i => $d) { $sum += $d * ($factor - $i); }
    $digits[] = ((10 * $sum) % 11) % 10;
    $factor = count($digits) + 1;
    $sum = 0;
    foreach ($digits as $i => $d) { $sum += $d * ($factor - $i); }
    $digits[] = ((10 * $sum) % 11) % 10;
    return implode('', $digits);
}

function qaStressCriarGuincho(int $usuarioId, string $placa, bool $reboque, array $capacidades): int
{
    $existente = Guincho::buscarPorUsuario($usuarioId);
    if ($existente) {
        $guinchoId = (int)$existente['id'];
    } else {
        $guinchoId = (int)Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => (string)(random_int(10_000_000_000, 99_999_999_999)),
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => $placa,
            'capacidade_ton' => 6.5,
            'raio_cobertura_km' => 50,
            'chave_pix' => 'qa-stress-' . $placa . '@guinchafacil.com',
            'chave_pix_tipo' => 'email',
            'foto_veiculo' => null,
            'doc_cnh_frente' => null,
            'doc_cnh_verso' => null,
        ]);
    }

    $pdo = getPDO();
    $fields = ['aprovado = ?' => 1, 'disponivel = ?' => 1, 'lat_atual = ?' => -22.897419, 'lng_atual = ?' => -43.199037];
    $stmtCol = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'guinchos' AND COLUMN_NAME IN ('oferece_reboque','reboque_aprovado')");
    $stmtCol->execute([DB_NAME]);
    $colunas = array_column($stmtCol->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    if (in_array('oferece_reboque', $colunas, true)) $fields['oferece_reboque = ?'] = $reboque ? 1 : 0;
    if (in_array('reboque_aprovado', $colunas, true)) $fields['reboque_aprovado = ?'] = $reboque ? 1 : 0;

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = $guinchoId;
    $pdo->prepare($sql)->execute($params);

    foreach ($capacidades as $codigo) {
        $tipo = ServiceType::buscarPorCodigo($codigo);
        if (!$tipo) continue;
        $capId = ProviderCapability::declarar($guinchoId, (int)$tipo['id'], ['estimated_duration_minutes' => 40]);
        ProviderCapability::aprovar($capId, 0);
    }

    return $guinchoId;
}

try {
    $clientes = [];
    for ($i = 1; $i <= $nClientes; $i++) {
        $email = "qa.cliente.{$runTag}.{$i}@guinchafacil.com";
        $id = qaStressEnsureUsuario($email, 'cliente', "Cliente Stress {$i} {$runTag}", '2199' . str_pad((string)(1000 + $i), 6, '0', STR_PAD_LEFT), qaStressCpf(qaStressCpfSeed($runTag, 100_000_000, $i)), 'test12345');
        $clientes[] = $id;
    }

    $guinchos = [];
    for ($i = 1; $i <= $nGuinchos; $i++) {
        $email = "qa.guincho.{$runTag}.{$i}@guinchafacil.com";
        $usuarioId = qaStressEnsureUsuario($email, 'guincho', "Guincho Stress {$i} {$runTag}", '2199' . str_pad((string)(2000 + $i), 6, '0', STR_PAD_LEFT), qaStressCpf(qaStressCpfSeed($runTag, 200_000_000, $i)), 'test12345');
        $guinchos[] = qaStressCriarGuincho($usuarioId, 'QST' . str_pad((string)$i, 4, '0', STR_PAD_LEFT), true, []);
    }

    $multisservico = [];
    for ($i = 1; $i <= $nMulti; $i++) {
        $email = "qa.guincho.{$runTag}-multi.{$i}@guinchafacil.com";
        $usuarioId = qaStressEnsureUsuario($email, 'guincho', "Guincho Multi {$i} {$runTag}", '2199' . str_pad((string)(3000 + $i), 6, '0', STR_PAD_LEFT), qaStressCpf(qaStressCpfSeed($runTag, 300_000_000, $i)), 'test12345');
        $multisservico[] = qaStressCriarGuincho($usuarioId, 'QSM' . str_pad((string)$i, 4, '0', STR_PAD_LEFT), true, ['ELECTRICAL_DIAGNOSIS', 'BATTERY_REPLACEMENT', 'TIRE_CHANGE']);
    }

    $especialistas = [];
    for ($i = 1; $i <= $nEspecialistas; $i++) {
        $email = "qa.guincho.{$runTag}-especialista.{$i}@guinchafacil.com";
        $usuarioId = qaStressEnsureUsuario($email, 'guincho', "Especialista Stress {$i} {$runTag}", '2199' . str_pad((string)(4000 + $i), 6, '0', STR_PAD_LEFT), qaStressCpf(qaStressCpfSeed($runTag, 400_000_000, $i)), 'test12345');
        $especialistas[] = qaStressCriarGuincho($usuarioId, 'QSE' . str_pad((string)$i, 4, '0', STR_PAD_LEFT), false, ['ELECTRICAL_DIAGNOSIS', 'BATTERY_REPLACEMENT']);
    }

    echo json_encode([
        'ok' => true,
        'run_id' => $runTag,
        'clientes' => $clientes,
        'guinchos' => $guinchos,
        'multisservico' => $multisservico,
        'especialistas' => $especialistas,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
