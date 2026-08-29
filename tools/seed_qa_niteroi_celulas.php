<?php

declare(strict_types=1);

/**
 * tools/seed_qa_niteroi_celulas.php
 * §CELULAS-NITEROI-01 (05/08/2026): suíte de QA pra popular o painel
 * "Metas & Território" com dados REAIS de cadastro — guinchos e
 * especialistas homologados dentro da célula 1 de Niterói (Praias da Baía
 * Central), clientes da região, e um cenário explícito de BLOQUEIO de
 * cadastro fora de região habilitada.
 *
 * Diferente dos outros seeds de tools/*.php (que inserem via SQL cru
 * reproduzindo as colunas à mão), este chama, via Reflection, os MESMOS
 * métodos privados que AuthController::registroGuincho()/registroCliente()
 * usam de verdade (criarUsuario/criarEndereco/criarGuincho/
 * declararCapacidadesDoRegistro/validarDadosGuincho) — assim o seed nunca
 * diverge do código real de cadastro, e o teste de bloqueio exercita a
 * validação de produção, não uma reimplementação.
 *
 * O que este script NÃO faz (fora de escopo, documentado em vez de fingir
 * que cobre): não cria pedidos nem pagamentos — só cadastro de prestador/
 * cliente. Os números financeiros de "Metas & Território" continuam
 * zerados até você criar pedidos reais pela própria interface.
 *
 * Uso:
 *   php tools/seed_qa_niteroi_celulas.php            (dry-run, não grava nada)
 *   php tools/seed_qa_niteroi_celulas.php --confirm   (grava de verdade)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Acesso negado. Use o terminal.\n");
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Controllers/BaseController.php';
require_once dirname(__DIR__) . '/src/Controllers/AuthController.php';
require_once dirname(__DIR__) . '/src/Models/Cidade.php';
require_once dirname(__DIR__) . '/src/Models/Pricing/PricingZone.php';
require_once dirname(__DIR__) . '/src/Services/Logger.php';

$confirm = in_array('--confirm', array_slice($argv, 1), true);
echo "Modo: " . ($confirm ? "CONFIRM (vai gravar)" : "DRY-RUN (nada será gravado — use --confirm pra aplicar)") . "\n";
echo str_repeat('-', 70) . "\n";

/** Invoca um método privado/protegido de um objeto via Reflection. */
function chamarPrivado(object $obj, string $metodo, array $args = [])
{
    $r = new ReflectionMethod($obj, $metodo);
    $r->setAccessible(true);
    return $r->invokeArgs($obj, $args);
}

function gerarCpfQa(int $base): string
{
    $base9 = str_pad((string)(abs($base) % 1000000000), 9, '0', STR_PAD_LEFT);
    $soma = 0;
    for ($i = 0; $i < 9; $i++) $soma += (int)$base9[$i] * (10 - $i);
    $d1 = (($soma * 10) % 11) % 10;
    $parcial = $base9 . $d1;
    $soma = 0;
    for ($i = 0; $i < 10; $i++) $soma += (int)$parcial[$i] * (11 - $i);
    $d2 = (($soma * 10) % 11) % 10;
    return $base9 . $d1 . $d2;
}

function cpfQa(PDO $pdo, string $sufixo): string
{
    $base = abs((int)crc32($sufixo));
    for ($tentativa = 0; $tentativa < 1000; $tentativa++) {
        $cpf = gerarCpfQa($base + $tentativa);
        $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE cpf = ? LIMIT 1');
        $stmt->execute([$cpf]);
        if (!$stmt->fetchColumn()) return $cpf;
    }
    throw new RuntimeException("Não foi possível gerar CPF QA único para {$sufixo}.");
}

$auth = new AuthController();

$arquivosQa = ['doc_cnh_frente' => ['error' => UPLOAD_ERR_OK], 'doc_cnh_verso' => ['error' => UPLOAD_ERR_OK]];

// ── 1) Célula-1 (Niterói) precisa existir e ter polígono ────────────────
$celula1 = PricingZone::buscarPorCodigo('niteroi-celula-1');
if (!$celula1) {
    fwrite(STDERR, "Célula niteroi-celula-1 não encontrada — rode install/migrate.php primeiro.\n");
    exit(1);
}
if (empty($celula1['polygon_geojson'])) {
    fwrite(STDERR, "Célula niteroi-celula-1 não tem polígono desenhado — rode tools/aplicar_poligonos_celulas_niteroi.php --confirm primeiro.\n");
    exit(1);
}

$cidade = Cidade::buscarPorSlug('niteroi-rj');
if (!$cidade) {
    fwrite(STDERR, "Cidade niteroi-rj não encontrada.\n");
    exit(1);
}
$cidadeId = (int)$cidade['id'];

// Ponto real DENTRO do polígono da célula 1 (centroide dos vértices — pra
// um polígono convexo isso é matematicamente sempre interno, ver
// tools/aplicar_poligonos_celulas_niteroi.php / install/data/celulas_niteroi.geojson).
const CELULA1_LAT = -22.905642;
const CELULA1_LNG = -43.100263;
const CELULA1_ENDERECO = ['cep' => '24230000', 'logradouro' => 'Rua Gavião Peixoto', 'numero' => '100', 'bairro' => 'Icaraí', 'cidade' => 'Niterói', 'estado' => 'RJ'];

// Ponto bem fora de qualquer célula mapeada (Av. Paulista, São Paulo) —
// usado só pro cenário de bloqueio, nunca grava nada.
const FORA_DA_REGIAO_LAT = -23.561414;
const FORA_DA_REGIAO_LNG = -46.655881;

echo "Célula alvo: {$celula1['name']} ({$celula1['code']}), status atual: " . ($celula1['status_expansao'] ?? 'nao_ativada') . "\n";
echo "Cidade: {$cidade['nome']}/{$cidade['uf']} (id {$cidadeId})\n\n";

// ── 2) Cenário de bloqueio: tentativa de cadastro FORA de região habilitada ─
// Roda a validação real (AuthController::validarDadosGuincho) tanto num
// ponto fora de qualquer célula mapeada quanto — se a célula 1 ainda não
// estiver 'pedra_viva' neste momento — dentro dela também, provando que o
// gate bloqueia célula não habilitada. Não escreve nada no banco.
function montarDadosGuinchoTeste(string $sufixo, float $lat, float $lng, int $cidadeId): array
{
    $pdo = getPDO();
    return [
        'nome' => "QA Prestador {$sufixo}",
        'email' => "qa_prestador_{$sufixo}@guinchafacil.com",
        'senha' => 'Teste@12345',
        'confirmar_senha' => 'Teste@12345',
        'senha_hash' => password_hash('Teste@12345', PASSWORD_DEFAULT),
        'telefone' => '21999990000',
        'cpf' => cpfQa($pdo, 'prestador_' . $sufixo),
        'cidade_id' => $cidadeId,
        'oferece_reboque' => 1,
        'cnh_numero' => '123456789',
        'cnh_validade' => date('Y-m-d', strtotime('+2 years')),
        'placa_guincho' => 'QAT' . strtoupper(substr(md5($sufixo), 0, 4)),
        'cidade_placa' => 'Niterói',
        'uf_placa' => 'RJ',
        'capacidade_ton' => 3.5,
        'raio_cobertura_km' => 20,
        'chave_pix' => "qa_prestador_{$sufixo}@guinchafacil.com",
        'chave_pix_tipo' => 'email',
        'lat_operacao' => $lat,
        'lng_operacao' => $lng,
        'servicos' => ['reboque'],
        'cep' => CELULA1_ENDERECO['cep'],
        'logradouro' => CELULA1_ENDERECO['logradouro'],
        'numero' => CELULA1_ENDERECO['numero'],
        'bairro' => CELULA1_ENDERECO['bairro'],
        'cidade' => CELULA1_ENDERECO['cidade'],
        'estado' => CELULA1_ENDERECO['estado'],
    ];
}

echo "=== Cenário 1: cadastro FORA de qualquer célula mapeada (São Paulo) ===\n";
$errosFora = chamarPrivado($auth, 'validarDadosGuincho', [montarDadosGuinchoTeste('fora_regiao', FORA_DA_REGIAO_LAT, FORA_DA_REGIAO_LNG, $cidadeId), $arquivosQa]);
$bloqueadoFora = (bool)array_filter($errosFora, static fn(string $e): bool => str_contains($e, 'fora de todas as regiões'));
echo $bloqueadoFora ? "[OK] Bloqueado como esperado: " . implode(' | ', $errosFora) . "\n" : "[FALHOU] NÃO bloqueou — erros: " . implode(' | ', $errosFora) . "\n";

// §CELULAS-NITEROI-01 — semântica de status_expansao pra CADASTRO (definida
// pelo usuário em 05/08/2026):
//   nao_ativada -> BLOQUEIA cadastro (região ainda fechada, mensagem explica).
//   pedra_morta -> PERMITE cadastro (só não ativa automaticamente — isso é
//                  aprovação normal do admin via guinchos.aprovado, sem
//                  relação com este gate de região).
//   pedra_viva  -> PERMITE cadastro (mesma validação de região que pedra_morta).
// Este cenário lê o status ATUAL da célula 1 (sem escrever nada no banco) e
// valida o comportamento esperado pra esse status específico.
echo "\n=== Cenário 2: cadastro DENTRO da célula 1, respeitando o status atual dela ===\n";
$statusAntesDoTeste = $celula1['status_expansao'] ?? 'nao_ativada';
$errosDentro = chamarPrivado($auth, 'validarDadosGuincho', [montarDadosGuinchoTeste('dentro_status_atual', CELULA1_LAT, CELULA1_LNG, $cidadeId), $arquivosQa]);
$erroDeRegiao = array_filter($errosDentro, static fn(string $e): bool => str_contains($e, 'não está ativada') || str_contains($e, 'fora de todas as regiões'));

if ($statusAntesDoTeste === 'nao_ativada') {
    $bloqueadoComoEsperado = (bool)array_filter($errosDentro, static fn(string $e): bool => str_contains($e, 'não está ativada'));
    echo $bloqueadoComoEsperado
        ? "[OK] status='nao_ativada' bloqueou o cadastro como esperado: " . implode(' | ', $errosDentro) . "\n"
        : "[FALHOU] status='nao_ativada' deveria ter bloqueado, mas não bloqueou — erros: " . implode(' | ', $errosDentro) . "\n";
} elseif ($statusAntesDoTeste === 'pedra_morta') {
    echo empty($erroDeRegiao)
        ? "[OK] status='pedra_morta' PERMITIU o cadastro como esperado (região só não ativa automaticamente, aprovação continua manual pelo admin).\n"
        : "[FALHOU] status='pedra_morta' deveria permitir o cadastro (só bloqueia 'nao_ativada'), mas bloqueou — erros: " . implode(' | ', $errosDentro) . "\n";
} else { // pedra_viva
    echo empty($erroDeRegiao)
        ? "[OK] status='pedra_viva' PERMITIU o cadastro como esperado.\n"
        : "[FALHOU] status='pedra_viva' deveria permitir o cadastro, mas bloqueou — erros: " . implode(' | ', $errosDentro) . "\n";
}

if (!$confirm) {
    echo "\n" . str_repeat('-', 70) . "\n";
    echo "Dry-run concluído (só validação, nada grava mesmo sem --confirm). Rode com --confirm pra:\n";
    echo "  1) ativar a célula 1 (status_expansao = pedra_viva);\n";
    echo "  2) cadastrar de verdade guinchos/especialistas + clientes dentro dela;\n";
    echo "  3) confirmar que o mesmo cadastro dentro da célula agora É aceito.\n";
    exit(0);
}

// ── 3) Ativa a célula 1 (simula a decisão do admin, mesma função real) ──
echo "\n=== Ativando célula 1 como pedra_viva ===\n";
$okAtivacao = PricingZone::atualizarExpansao(
    (int)$celula1['id'],
    $celula1['ordem_expansao'] !== null ? (int)$celula1['ordem_expansao'] : 1,
    'pedra_viva',
    $celula1['bairros_referencia'] ?? null
);
echo $okAtivacao ? "[OK] Célula 1 ativada.\n" : "[FALHOU] Não conseguiu ativar a célula 1.\n";
Logger::log(Logger::LEVEL_INFO, 'ToolSeedQaNiteroi', 'run', 'admin',
    'Célula niteroi-celula-1 ativada como pedra_viva via tools/seed_qa_niteroi_celulas.php (QA)',
    ['zona_id' => (int)$celula1['id']]);

// ── 4) Prova de que o MESMO cadastro dentro da célula agora é aceito ────
echo "\n=== Revalidando cadastro DENTRO da célula 1, já habilitada ===\n";
$dadosGuinchoValido = montarDadosGuinchoTeste('celula1_ok', CELULA1_LAT, CELULA1_LNG, $cidadeId);
$errosAgora = chamarPrivado($auth, 'validarDadosGuincho', [$dadosGuinchoValido, $arquivosQa]);
echo empty($errosAgora) ? "[OK] Sem erros de região — cadastro liberado.\n" : "[AVISO] Ainda há erros (podem ser de outros campos, não de região): " . implode(' | ', $errosAgora) . "\n";

// ── 5) Cadastra de verdade: prestadores homologados dentro da célula 1 ──
// Composição alvo do piloto (subset pra QA rápida, não precisa bater os
// 10-15 completos): 2 guinchos leves (reboque) + 1 apoio (mecânica) +
// 1 elétrica + 1 pneu — dá pra ver o gráfico de composição colorido sem
// esperar 10 cadastros.
$pdo = getPDO();
$prestadoresQa = [
    ['sufixo' => 'guincho_leve_1', 'servicos' => ['reboque']],
    ['sufixo' => 'guincho_leve_2', 'servicos' => ['reboque']],
    ['sufixo' => 'apoio_mecanica_1', 'servicos' => ['mecanica']],
    ['sufixo' => 'eletrica_1', 'servicos' => ['eletrica']],
    ['sufixo' => 'pneu_1', 'servicos' => ['pneu']],
];

echo "\n=== Cadastrando prestadores QA dentro da célula 1 ===\n";
$guinchoIdsCriados = [];
foreach ($prestadoresQa as $p) {
    $dados = montarDadosGuinchoTeste($p['sufixo'], CELULA1_LAT, CELULA1_LNG, $cidadeId);
    $dados['servicos'] = $p['servicos'];
    $dados['oferece_reboque'] = in_array('reboque', $p['servicos'], true) ? 1 : 0;

    $existente = Usuario_buscarPorEmailSeguro($pdo, $dados['email']);
    if ($existente) {
        echo "  - {$dados['email']}: já existe (id {$existente}), pulando.\n";
        $guinchoIdsCriados[] = ['usuario_id' => (int)$existente, 'sufixo' => $p['sufixo']];
        continue;
    }

    $pdo->beginTransaction();
    try {
        $userId = chamarPrivado($auth, 'criarUsuario', [$pdo, $dados, 'guincho']);
        chamarPrivado($auth, 'criarEndereco', [$pdo, $userId, $dados, true]);

        $guinchoData = [
            'usuario_id' => $userId,
            'cidade_id' => $cidadeId,
            'cnh_numero' => $dados['cnh_numero'],
            'cnh_validade' => $dados['cnh_validade'],
            'placa_guincho' => $dados['placa_guincho'],
            'cidade_placa' => $dados['cidade_placa'],
            'uf_placa' => $dados['uf_placa'],
            'capacidade_ton' => $dados['capacidade_ton'],
            'marca_caminhao' => null,
            'modelo_caminhao' => null,
            'vehicle_brand_id' => null,
            'vehicle_model_id' => null,
            'raio_cobertura_km' => $dados['raio_cobertura_km'],
            'chave_pix' => $dados['chave_pix'],
            'chave_pix_tipo' => $dados['chave_pix_tipo'],
            'lat_operacao' => $dados['lat_operacao'],
            'lng_operacao' => $dados['lng_operacao'],
            'foto_veiculo' => null,
            'doc_cnh_frente' => null,
            'doc_cnh_verso' => null,
            'aprovado' => 0,
            'disponivel' => 0,
            'lat_atual' => null,
            'lng_atual' => null,
        ];
        $guinchoId = chamarPrivado($auth, 'criarGuincho', [$pdo, $guinchoData]);
        if (!$guinchoId) {
            throw new \RuntimeException('criarGuincho retornou vazio');
        }
        $pdo->prepare('UPDATE guinchos SET oferece_reboque = ?, reboque_aprovado = 0 WHERE id = ?')
            ->execute([$dados['oferece_reboque'], $guinchoId]);

        chamarPrivado($auth, 'declararCapacidadesDoRegistro', [$pdo, (int)$guinchoId, $dados['servicos']]);

        // Simula a aprovação do admin (docs conferidos, capacidades
        // liberadas) — sem isso o prestador não conta em NENHUMA métrica
        // do painel (aprovado=1 e provider_capabilities.approval_status
        // continuam PENDING por padrão, propositalmente, no cadastro real).
        $pdo->prepare('UPDATE guinchos SET aprovado = 1, disponivel = 1 WHERE id = ?')->execute([$guinchoId]);
        if ($dados['oferece_reboque']) {
            $pdo->prepare('UPDATE guinchos SET reboque_aprovado = 1 WHERE id = ?')->execute([$guinchoId]);
        }
        $pdo->prepare("UPDATE provider_capabilities SET enabled = 1, approval_status = 'APPROVED' WHERE provider_id = ?")->execute([$guinchoId]);

        $pdo->commit();
        echo "  - {$dados['email']}: criado (usuario #{$userId}, guincho #{$guinchoId}), aprovado.\n";
        $guinchoIdsCriados[] = ['usuario_id' => (int)$userId, 'guincho_id' => (int)$guinchoId, 'sufixo' => $p['sufixo']];
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo "  - {$dados['email']}: FALHOU (" . $e->getMessage() . ")\n";
    }
}

// ── 6) Cadastra clientes reais dentro da célula 1 ────────────────────────
echo "\n=== Cadastrando clientes QA dentro da célula 1 ===\n";
for ($n = 1; $n <= 3; $n++) {
    $email = "qa_cliente_{$n}@guinchafacil.com";
    $existente = Usuario_buscarPorEmailSeguro($pdo, $email);
    if ($existente) {
        echo "  - {$email}: já existe (id {$existente}), pulando.\n";
        continue;
    }
    $dadosCliente = [
        'nome' => "QA Cliente {$n}",
        'email' => $email,
        'confirmar_senha' => 'Teste@12345',
        'senha_hash' => password_hash('Teste@12345', PASSWORD_DEFAULT),
        'telefone' => '2199999000' . $n,
        'cpf' => cpfQa($pdo, 'cliente_' . $n),
        'cep' => CELULA1_ENDERECO['cep'],
        'logradouro' => CELULA1_ENDERECO['logradouro'],
        'numero' => (string)(100 + $n),
        'bairro' => CELULA1_ENDERECO['bairro'],
        'cidade' => CELULA1_ENDERECO['cidade'],
        'estado' => CELULA1_ENDERECO['estado'],
    ];
    $pdo->beginTransaction();
    try {
        $userId = chamarPrivado($auth, 'criarUsuario', [$pdo, $dadosCliente, 'cliente']);
        chamarPrivado($auth, 'criarEndereco', [$pdo, $userId, $dadosCliente, true]);
        $pdo->commit();
        echo "  - {$email}: criado (usuario #{$userId}).\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo "  - {$email}: FALHOU (" . $e->getMessage() . ")\n";
    }
}

echo "\n" . str_repeat('-', 70) . "\n";
echo "Concluído. Célula 1 ativada + prestadores/clientes de QA cadastrados dentro dela.\n";
echo "Lembrete: nenhum pedido/pagamento foi criado — os números financeiros do painel continuam em zero até haver pedidos reais.\n";
echo "Senha de todas as contas QA: Teste@12345\n";

/** Busca id de usuário por email sem depender de model específico (idempotência do seed). */
function Usuario_buscarPorEmailSeguro(\PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}
