<?php
// install-guincho-teste.php — cria usuário + guincho de teste completo
// APAGUE DEPOIS DE RODAR

require_once __DIR__ . '/config.php';

$pdo = getPDO();
$report = [];

// ─── Configurações do teste ───
$email        = 'guincho@teste.com';
$senha        = 'teste123';
$nomeUsuario  = 'Carlos Guincheiro';
$nomeFantasia = 'Auto Socorro Laranjeiras';

// Rua das Laranjeiras, 200 — Laranjeiras, Rio de Janeiro
$lat          = -22.9339;
$lng          = -43.1958;
$raioKm       = 50;
$endereco     = 'Rua das Laranjeiras, 200 — Laranjeiras, Rio de Janeiro/RJ';

// ─── 1. Cria/atualiza usuário ───
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$userId = $stmt->fetchColumn();

$hash = password_hash($senha, PASSWORD_BCRYPT);

if ($userId) {
    $pdo->prepare("UPDATE usuarios SET nome=?, senha_hash=?, tipo='guincho', ativo=1 WHERE id=?")
        ->execute([$nomeUsuario, $hash, $userId]);
    $report[] = "[OK] Usuário atualizado (id=$userId)";
} else {
    $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo, criado_em, perfil_status) VALUES (?, ?, ?, 'guincho', 1, NOW(), 'COMPLETO')")
        ->execute([$nomeUsuario, $email, $hash]);
    $userId = (int)$pdo->lastInsertId();
    $report[] = "[OK] Usuário criado (id=$userId)";
}

// ─── 2. Descobre cidade_id (opcional) ───
$cidadeId = null;
try {
    $stmt = $pdo->query("SELECT id FROM cidades WHERE ativo=1 AND nome LIKE '%Rio de Janeiro%' LIMIT 1");
    $cidadeId = $stmt->fetchColumn() ?: null;
    if ($cidadeId) {
        $report[] = "[OK] Cidade-alvo: Rio de Janeiro (id=$cidadeId)";
    } else {
        $report[] = "[AVISO] Nenhuma cidade 'Rio de Janeiro' ativa — usando NULL";
    }
} catch (Throwable $e) {
    $report[] = "[AVISO] Erro ao buscar cidade: " . $e->getMessage();
}

// ─── 3. Cria/atualiza guincho ───
$stmt = $pdo->prepare("SELECT id FROM guinchos WHERE usuario_id = ? LIMIT 1");
$stmt->execute([$userId]);
$guinchoId = $stmt->fetchColumn();

$dados = [
    'usuario_id'         => $userId,
    'cidade_id'          => $cidadeId,
    'cnh_numero'         => '12345678901',
    'cnh_validade'       => date('Y-m-d', strtotime('+3 years')),
    'placa_guincho'      => 'GFC1A23',
    'cidade_placa'       => 'RIO DE JANEIRO',
    'uf_placa'           => 'RJ',
    'capacidade_ton'     => 5.0,
    'marca_caminhao'     => 'Ford',
    'modelo_caminhao'    => 'Cargo 816',
    'tipo_guincho'       => 'plataforma',
    'categoria_cnh'      => 'E',
    'ear'                => 1,
    'ano_fabricacao'     => 2020,
    'equipamentos_json'  => json_encode(['plataforma', 'sinalizacao', 'bateria'], JSON_UNESCAPED_UNICODE),
    'raio_cobertura_km'  => $raioKm,
    'chave_pix'          => '21999998888',
    'chave_pix_tipo'     => 'telefone',
    'lat_operacao'       => $lat,
    'lng_operacao'       => $lng,
    'lat_atual'          => $lat,
    'lng_atual'          => $lng,
    'aprovado'           => 1,
    'disponivel'         => 1,
    'oferece_reboque'    => 1,
    'reboque_aprovado'   => 1,
];

if ($guinchoId) {
    $sets = [];
    $params = [];
    foreach ($dados as $col => $val) {
        $sets[] = "$col = ?";
        $params[] = $val;
    }
    $params[] = $guinchoId;
    try {
        $pdo->prepare("UPDATE guinchos SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        $report[] = "[OK] Guincho atualizado (id=$guinchoId)";
    } catch (Throwable $e) {
        $report[] = "[ERRO] UPDATE falhou: " . $e->getMessage();
    }
} else {
    $cols = array_keys($dados);
    $ph   = array_fill(0, count($cols), '?');
    try {
        $pdo->prepare("INSERT INTO guinchos (" . implode(',', $cols) . ", criado_em) VALUES (" . implode(',', $ph) . ", NOW())")
            ->execute(array_values($dados));
        $guinchoId = (int)$pdo->lastInsertId();
        $report[] = "[OK] Guincho criado (id=$guinchoId)";
    } catch (Throwable $e) {
        $report[] = "[ERRO] INSERT falhou: " . $e->getMessage();
        // Tenta sem colunas opcionais
        $basico = [
            'usuario_id' => $userId,
            'placa_guincho' => 'GFC1A23',
            'cidade_placa' => 'RIO DE JANEIRO',
            'uf_placa' => 'RJ',
            'capacidade_ton' => 5.0,
            'raio_cobertura_km' => $raioKm,
            'chave_pix' => '21999998888',
            'chave_pix_tipo' => 'telefone',
            'lat_operacao' => $lat,
            'lng_operacao' => $lng,
            'lat_atual' => $lat,
            'lng_atual' => $lng,
            'aprovado' => 1,
            'disponivel' => 1,
            'oferece_reboque' => 1,
            'reboque_aprovado' => 1,
            'cnh_numero' => '12345678901',
            'cnh_validade' => date('Y-m-d', strtotime('+3 years')),
        ];
        $cols = array_keys($basico);
        $ph   = array_fill(0, count($cols), '?');
        try {
            $pdo->prepare("INSERT INTO guinchos (" . implode(',', $cols) . ", criado_em) VALUES (" . implode(',', $ph) . ", NOW())")
                ->execute(array_values($basico));
            $guinchoId = (int)$pdo->lastInsertId();
            $report[] = "[OK] Guincho criado com dados básicos (id=$guinchoId)";
        } catch (Throwable $e2) {
            $report[] = "[ERRO] INSERT fallback também falhou: " . $e2->getMessage();
        }
    }
}

// ─── 4. Endereço de operação (na tabela enderecos) ───
if ($userId) {
    try {
        $pdo->prepare("DELETE FROM enderecos WHERE usuario_id = ? AND principal = 1")->execute([$userId]);
        $pdo->prepare("INSERT INTO enderecos (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, principal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)")
            ->execute([$userId, '22240004', 'Rua das Laranjeiras', '200', '', 'Laranjeiras', 'Rio de Janeiro', 'RJ']);
        $report[] = "[OK] Endereço de operação cadastrado";
    } catch (Throwable $e) {
        $report[] = "[AVISO] Endereço não cadastrado (opcional): " . $e->getMessage();
    }
}

// ─── 5. Verifica duplicatas de placa ───
try {
    $stmt = $pdo->query("SELECT id FROM guinchos WHERE placa_guincho = 'GFC1A23'");
    $placas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($placas) > 1) {
        $report[] = "[AVISO] Placa GFC1A23 duplicada em " . count($placas) . " registros (ids: " . implode(', ', $placas) . ")";
    }
} catch (Throwable $e) { /* silencioso */ }

// ─── 6. Verificação final ───
$report[] = "";
$report[] = "── ESTADO FINAL ──";
try {
    $stmt = $pdo->prepare("SELECT g.id, g.placa_guincho, g.aprovado, g.disponivel, g.oferece_reboque, g.reboque_aprovado, g.lat_atual, g.lng_atual, g.raio_cobertura_km, u.email, u.tipo, u.ativo
                            FROM guinchos g
                            JOIN usuarios u ON u.id = g.usuario_id
                            WHERE g.usuario_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $report[] = "ID do guincho:   " . $row['id'];
        $report[] = "Placa:           " . $row['placa_guincho'];
        $report[] = "Usuário:         " . $row['email'] . " (" . $row['tipo'] . ")";
        $report[] = "Ativo/Disponível: " . $row['ativo'] . "/" . $row['disponivel'];
        $report[] = "Reboque:         oferece=" . $row['oferece_reboque'] . " aprovado=" . $row['reboque_aprovado'];
        $report[] = "Posição GPS:     " . $row['lat_atual'] . ", " . $row['lng_atual'];
        $report[] = "Raio cobertura:  " . $row['raio_cobertura_km'] . " km";
    } else {
        $report[] = "[ERRO] Guincho não encontrado após o processo";
    }
} catch (Throwable $e) {
    $report[] = "[ERRO] Verificação falhou: " . $e->getMessage();
}

// ─── 7. Confirma que aparece na API pública ───
$report[] = "";
$report[] = "── API pública ──";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM guinchos WHERE aprovado=1 AND disponivel=1 AND lat_atual IS NOT NULL");
    $total = (int)$stmt->fetchColumn();
    $report[] = "Guinchos online no banco: $total";
} catch (Throwable $e) {
    $report[] = "[AVISO] " . $e->getMessage();
}

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer Guincho Teste</title>
<style>
body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}
.card{background:#1a1f2a;padding:16px;border-radius:10px;margin-bottom:12px}
.card h2{margin-top:0;color:#d97706}
</style>
</head><body>
<h1>🚚 Guincho de Teste Criado</h1>

<div class="card">
<h2>🔑 Credenciais para login</h2>
<pre>URL:   http://localhost:8080/login
Email: guincho@teste.com
Senha: teste123</pre>
</div>

<div class="card">
<h2>📍 Posição</h2>
<pre>Rua das Laranjeiras, 200 — Laranjeiras, Rio de Janeiro/RJ
Latitude:  -22.9339
Longitude: -43.1958
Raio de cobertura: 50 km</pre>
</div>

<div class="card">
<h2>📋 Relatório</h2>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
</div>

<div class="card">
<h2>🎯 Próximos passos</h2>
<pre>1. Faça login com as credenciais acima
2. Vá em /guincho/dashboard
3. Ative o toggle "Online" (canto superior direito)
4. Confirme a localização no modal que aparecer (arraste o pino se precisar)
5. Deixe a aba aberta — agora você está disponível para receber pedidos!

Para testar o fluxo completo:
- Abra /pre-cotacao em outra janela
- Use "Rua da Gamboa, 100" como origem
- Cliente escolhe "Levar o carro" → deve achar este guincho
- Ou "Me orientem" → Pneu → se sem oficina online → WhatsApp</pre>
</div>

<p class="del">APAGUE: install-guincho-teste.php</p>
</body></html>