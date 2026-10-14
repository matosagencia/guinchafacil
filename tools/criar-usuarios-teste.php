<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$_SERVER['REQUEST_URI'] = '/cli';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'cli';
$_SERVER['SCRIPT_NAME'] = '/cli.php';
if (!defined('REQUEST_ID')) define('REQUEST_ID', 'cli_seed');
if (!defined('CSP_SCRIPT_NONCE')) define('CSP_SCRIPT_NONCE', 'cli');

$raiz = dirname(__DIR__);
require_once $raiz . '/config.php';
if (!function_exists('getPDO')) require_once $raiz . '/src/Database.php';

$pdo = getPDO();
$senha = 'teste123';
$hash = password_hash($senha, PASSWORD_BCRYPT);

echo "Hash gerado (len=" . strlen($hash) . "): " . substr($hash, 0, 20) . "...\n\n";

$usuarios = [
    [
        'nome'  => 'Admin Teste',
        'email' => 'admin@teste.local',
        'tipo'  => 'admin',
        'cpf'   => '11111111111',
        'tel'   => '21999990001',
    ],
    [
        'nome'  => 'Cliente Teste',
        'email' => 'cliente@teste.local',
        'tipo'  => 'cliente',
        'cpf'   => '22222222222',
        'tel'   => '21999990002',
    ],
    [
        'nome'  => 'Guincho Teste',
        'email' => 'guincho@teste.local',
        'tipo'  => 'guincho',
        'cpf'   => '33333333333',
        'tel'   => '21999990003',
    ],
    [
        'nome'  => 'Oficina Teste',
        'email' => 'oficina@teste.local',
        'tipo'  => 'oficina',
        'cpf'   => '44444444444',
        'tel'   => '21999990004',
    ],
];

function colunas(PDO $pdo, string $tabela): array {
    try {
        return $pdo->query("SHOW COLUMNS FROM {$tabela}")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) {
        return [];
    }
}

foreach ($usuarios as $u) {
    echo "=== {$u['email']} ({$u['tipo']}) ===\n";

    // 1. Usuário
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$u['email']]);
    $existente = $stmt->fetchColumn();

    if ($existente) {
        $pdo->prepare(
            "UPDATE usuarios
                SET nome = ?, senha_hash = ?, tipo = ?, ativo = 1,
                    perfil_status = 'COMPLETO', cpf = ?, telefone = ?
              WHERE id = ?"
        )->execute([$u['nome'], $hash, $u['tipo'], $u['cpf'], $u['tel'], $existente]);
        $usuarioId = (int)$existente;
        echo "  usuario ATUALIZADO (id={$usuarioId})\n";
    } else {
        $pdo->prepare(
            "INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo, perfil_status, cpf, telefone, criado_em)
             VALUES (?, ?, ?, ?, 1, 'COMPLETO', ?, ?, NOW())"
        )->execute([$u['nome'], $u['email'], $hash, $u['tipo'], $u['cpf'], $u['tel']]);
        $usuarioId = (int)$pdo->lastInsertId();
        echo "  usuario CRIADO (id={$usuarioId})\n";
    }

    // 2. Linha relacionada por tipo
    if ($u['tipo'] === 'guincho') {
        $cols = colunas($pdo, 'guinchos');
        if (empty($cols)) {
            echo "  AVISO: tabela guinchos inacessivel, pulando.\n";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM guinchos WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$usuarioId]);
            $gId = $stmt->fetchColumn();

            // Só insere colunas que existem, com defaults razoáveis
            $dados = [
                'usuario_id'         => $usuarioId,
                'cnh_numero'         => '0000000000',
                'cnh_validade'       => date('Y-m-d', strtotime('+5 years')),
                'placa_guincho'      => 'TST1A23',
                'cidade_placa'       => 'Rio de Janeiro',
                'uf_placa'           => 'RJ',
                'capacidade_ton'     => 2.5,
                'raio_cobertura_km'  => 50,
                'chave_pix'          => $u['email'],
                'chave_pix_tipo'     => 'email',
                'lat_operacao'       => -22.89732378,
                'lng_operacao'       => -43.19546092,
                'lat_atual'          => -22.89732378,
                'lng_atual'          => -43.19546092,
                'aprovado'           => 1,
                'disponivel'         => 1,
                'reboque_aprovado'   => 1,
                'oferece_reboque'    => 1,
                'reputacao'          => 5.0,
                'total_avaliacoes'   => 0,
            ];
            $dadosFiltrados = array_intersect_key($dados, array_flip($cols));
            if ($gId) {
                $sets = [];
                $params = [];
                foreach ($dadosFiltrados as $k => $v) {
                    $sets[] = "{$k} = ?";
                    $params[] = $v;
                }
                $params[] = $gId;
                $pdo->prepare("UPDATE guinchos SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
                echo "  guincho ATUALIZADO (id={$gId})\n";
            } else {
                $colunasSql = implode(', ', array_keys($dadosFiltrados));
                $placeholders = implode(', ', array_fill(0, count($dadosFiltrados), '?'));
                $pdo->prepare("INSERT INTO guinchos ({$colunasSql}) VALUES ({$placeholders})")
                    ->execute(array_values($dadosFiltrados));
                $gId = (int)$pdo->lastInsertId();
                echo "  guincho CRIADO (id={$gId}) — aprovado, disponivel, reboque_aprovado\n";
            }
        }
    }

    if ($u['tipo'] === 'oficina') {
        $cols = colunas($pdo, 'oficinas');
        if (empty($cols)) {
            echo "  AVISO: tabela oficinas inacessivel, pulando.\n";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM oficinas WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$usuarioId]);
            $oId = $stmt->fetchColumn();

            $dados = [
                'usuario_id'        => $usuarioId,
                'nome'              => $u['nome'],
                'cnpj'              => '00000000000191',
                'razao_social'      => $u['nome'] . ' LTDA',
                'endereco'          => 'Rua Teste, 100',
                'lat'               => -22.89732378,
                'lng'               => -43.19546092,
                'telefone'          => $u['tel'],
                'email'             => $u['email'],
                'aprovado'          => 1,
                'ativo'             => 1,
                'disponivel'        => 1,
                'status_parceria'   => 'ativa',
                'reputacao'         => 5.0,
            ];
            $dadosFiltrados = array_intersect_key($dados, array_flip($cols));
            if ($oId) {
                $sets = [];
                $params = [];
                foreach ($dadosFiltrados as $k => $v) {
                    $sets[] = "{$k} = ?";
                    $params[] = $v;
                }
                $params[] = $oId;
                $pdo->prepare("UPDATE oficinas SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
                echo "  oficina ATUALIZADA (id={$oId})\n";
            } else {
                $colunasSql = implode(', ', array_keys($dadosFiltrados));
                $placeholders = implode(', ', array_fill(0, count($dadosFiltrados), '?'));
                $pdo->prepare("INSERT INTO oficinas ({$colunasSql}) VALUES ({$placeholders})")
                    ->execute(array_values($dadosFiltrados));
                $oId = (int)$pdo->lastInsertId();
                echo "  oficina CRIADA (id={$oId})\n";
            }
        }
    }

    echo "\n";
}

// Verificação final
echo "=== Verificacao de login ===\n";
foreach ($usuarios as $u) {
    $stmt = $pdo->prepare("SELECT senha_hash, tipo, ativo FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$u['email']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $ok = password_verify($senha, $row['senha_hash']);
        printf("  %-25s tipo=%-8s ativo=%d senha=%s\n",
            $u['email'], $row['tipo'], $row['ativo'], $ok ? 'OK' : 'FALHOU');
    } else {
        printf("  %-25s NAO ENCONTRADO\n", $u['email']);
    }
}

echo "\nPronto. Credenciais:\n";
echo "  admin@teste.local    / teste123\n";
echo "  cliente@teste.local  / teste123\n";
echo "  guincho@teste.local  / teste123\n";
echo "  oficina@teste.local  / teste123\n";