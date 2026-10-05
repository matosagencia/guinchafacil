<?php
declare(strict_types=1);
// File: guinchafacil/src/Controllers/OficinaController.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/OficinaOperador.php';
require_once __DIR__ . '/../Models/OficinaOrcamento.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Services/GeoService.php';

class OficinaController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        AuthService::requireAuth('oficina');
    }

    private function usuarioId(): int
    {
        $u = AuthService::getCurrentUser();
        return (int)($u['id'] ?? 0);
    }

    private function getOficina(): array
    {
        $of = OficinaOperador::buscarPorUsuarioId($this->usuarioId());
        if (!$of) {
            http_response_code(403);
            echo '<h1>403 - Oficina nao vinculada a este usuario</h1>';
            echo '<p>Peca ao admin para vincular seu login (oficinas.usuario_id).</p>';
            exit;
        }
        return $of;
    }

    // --- DASHBOARD ---
    public function dashboard(): void
    {
        $oficina = $this->getOficina();
        $pedidos = $this->buscarPedidosProximos($oficina);
        $csrfToken = AuthService::gerarCsrfToken();

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM pedidos
             WHERE oficina_id = ? AND DATE(criado_em) = CURDATE()"
        );
        $stmt->execute([(int)$oficina['id']]);
        $atendimentosHoje = (int)$stmt->fetchColumn();

        require __DIR__ . '/../Views/oficina/dashboard.php';
    }

    private function buscarPedidosProximos(array $oficina, int $raioPadrao = 15): array
    {
        if (empty($oficina['latitude']) || empty($oficina['longitude'])) {
            return [];
        }
        $raio = (float)($oficina['raio_atendimento_km'] ?? $raioPadrao);

        // Fonte unica: Pedido::listarFilaElegivelParaOficina (contrato A->B).
        // Inclui expiracao_aceite > NOW() e JOINs de cliente/veiculo que a
        // versao anterior (SQL inline) nao tinha.
        $pedidos = Pedido::listarFilaElegivelParaOficina(
            (float)$oficina['latitude'],
            (float)$oficina['longitude'],
            $raio
        );

        // Compat com _offer_card.php: distancia_oficina_km -> distancia_km
        foreach ($pedidos as &$p) {
            $p['distancia_km'] = $p['distancia_oficina_km'] ?? null;
        }
        unset($p);

        // Preserva ordem "mais perto primeiro" da versao anterior.
        usort($pedidos, static fn(array $a, array $b): int =>
            ((float)($a['distancia_km'] ?? PHP_FLOAT_MAX))
            <=> ((float)($b['distancia_km'] ?? PHP_FLOAT_MAX))
        );

        // Recusados e' estado de sessao de B — filtro vive aqui.
        $recusados = $_SESSION['oficina_recusados'] ?? [];
        if (!empty($recusados) && is_array($recusados)) {
            $pedidos = array_values(array_filter(
                $pedidos,
                static fn(array $p): bool => !isset($recusados[(int)$p['id']])
            ));
        }

        return $pedidos;
    }

    public function pedidosDisponiveis(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $oficina = $this->getOficina();
        echo json_encode([
            'ok'      => true,
            'pedidos' => $this->buscarPedidosProximos($oficina),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- ACEITAR ---
    public function aceitar(int $id): void
    {
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            $this->setFlashMessage('Sessao expirada. Tente novamente.', 'error');
            $this->redirect('/oficina/dashboard');
        }
        $oficina = $this->getOficina();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido) {
            $this->setFlashMessage('Pedido nao encontrado.', 'error');
            $this->redirect('/oficina/dashboard');
        }

        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "UPDATE pedidos
                 SET oficina_id = ?, status = 'oficina_aceitou'
                 WHERE id = ? AND oficina_id IS NULL"
            );
            $stmt->execute([(int)$oficina['id'], $id]);

            if ($stmt->rowCount() === 0) {
                $this->setFlashMessage('Este pedido ja foi aceito por outra oficina.', 'error');
                $this->redirect('/oficina/dashboard');
            }

            $this->setFlashMessage('Pedido aceito! Iniciando atendimento.', 'success');
            $this->redirect('/oficina/pedido/' . $id);
        } catch (Throwable $e) {
            error_log('[OficinaController][aceitar] ' . $e->getMessage());
            $this->setFlashMessage('Erro ao aceitar pedido.', 'error');
            $this->redirect('/oficina/dashboard');
        }
    }

    // --- CANCELAR (post) ---
    /**
     * Espelha GuinchoController::cancelarAtendimento. Sempre JSON.
     * Regras de negócio vivem em CancelamentoService::cancelarPorOficina.
     */
    public function cancelarAtendimento(int $id): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (!AuthService::validarCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Token inválido.', 'penalidade_reputacao' => 0], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $oficina = $this->getOficina();
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        if ($motivo === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'erro' => 'Informe o motivo do cancelamento.', 'penalidade_reputacao' => 0], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once __DIR__ . '/../Services/CancelamentoService.php';
        try {
            $resultado = CancelamentoService::cancelarPorOficina($id, (int)$oficina['id'], $motivo);
            http_response_code($resultado['ok'] ? 200 : 409);
            echo json_encode([
                'ok' => (bool)$resultado['ok'],
                'erro' => $resultado['erro'],
                'penalidade_reputacao' => (float)($resultado['penalidade_reputacao'] ?? 0.0),
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[OficinaController][cancelarAtendimento] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'erro' => 'Erro interno ao cancelar.', 'penalidade_reputacao' => 0], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // --- ATENDIMENTO ---
    public function atendimento(int $id): void
    {
        $oficina = $this->getOficina();
        $pedido = Pedido::buscarPorId($id);

        if (!$pedido || (int)($pedido['oficina_id'] ?? 0) !== (int)$oficina['id']) {
            $this->setFlashMessage('Pedido nao esta sob sua responsabilidade.', 'error');
            $this->redirect('/oficina/dashboard');
        }

        $csrfToken = AuthService::gerarCsrfToken();
        $orcamentoPendente = OficinaOrcamento::buscarPendentePorPedido($id);

        require __DIR__ . '/../Views/oficina/atendimento.php';
    }

    public function atualizarStatus(int $id): void
    {
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $oficina = $this->getOficina();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido || (int)($pedido['oficina_id'] ?? 0) !== (int)$oficina['id']) {
            http_response_code(403); exit;
        }

        $novo = (string)($_POST['status'] ?? '');
        $permitidos = ['oficina_a_caminho','no_local','em_execucao_servico','concluido'];
        if (!in_array($novo, $permitidos, true)) {
            $this->setFlashMessage('Status invalido.', 'error');
            $this->redirect('/oficina/pedido/' . $id);
        }

        try {
            getPDO()->prepare("UPDATE pedidos SET status = ? WHERE id = ?")
                ->execute([$novo, $id]);
            $this->setFlashMessage('Status atualizado.', 'success');
        } catch (Throwable $e) {
            error_log('[OficinaController][atualizarStatus] ' . $e->getMessage());
            $this->setFlashMessage('Erro ao atualizar status.', 'error');
        }
        $this->redirect('/oficina/pedido/' . $id);
    }

    public function statusJson(int $id): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $oficina = $this->getOficina();
        $pedido = Pedido::buscarPorId($id);
        if (!$pedido || (int)($pedido['oficina_id'] ?? 0) !== (int)$oficina['id']) {
            http_response_code(404);
            echo json_encode(['ok' => false]);
            exit;
        }
        echo json_encode([
            'ok'     => true,
            'status' => $pedido['status'],
            'label'  => ucfirst(str_replace('_', ' ', (string)$pedido['status'])),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- DISPONIBILIDADE / LOCALIZACAO ---
    public function disponibilidade(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Token invalido.']);
            exit;
        }
        $oficina = $this->getOficina();
        $desejado = (int)($_POST['disponivel'] ?? 0) === 1;

        $ok = OficinaOperador::atualizarDisponibilidade((int)$oficina['id'], $desejado);
        echo json_encode(['ok' => $ok, 'disponivel' => $desejado]);
        exit;
    }

    public function atualizarLocalizacao(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Token invalido.']);
            exit;
        }
        $oficina = $this->getOficina();
        $lat = (float)($_POST['latitude'] ?? 0);
        $lng = (float)($_POST['longitude'] ?? 0);

        if (!GeoService::coordenadasValidas($lat, $lng)) {
            echo json_encode(['ok' => false, 'erro' => 'Coordenadas invalidas.']);
            exit;
        }
        $ok = OficinaOperador::atualizarLocalizacao((int)$oficina['id'], $lat, $lng);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // --- FINANCEIRO ---
    public function financeiro(): void
    {
        $oficina = $this->getOficina();
        $oficinaId = (int)$oficina['id'];

        $mes = (int)($_GET['mes'] ?? date('m'));
        $ano = (int)($_GET['ano'] ?? date('Y'));
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim    = date('Y-m-t', strtotime($inicio));

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT * FROM oficina_repasses
             WHERE oficina_id = ? AND criado_em BETWEEN ? AND ?
             ORDER BY criado_em DESC"
        );
        $stmt->execute([$oficinaId, $inicio . ' 00:00:00', $fim . ' 23:59:59']);
        $repasses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totais = ['bruto'=>0.0,'taxa'=>0.0,'liquido'=>0.0,'pago'=>0.0,'a_receber'=>0.0];
        foreach ($repasses as $r) {
            $totais['bruto']   += (float)$r['valor_bruto'];
            $totais['taxa']    += (float)$r['taxa_plataforma'];
            $totais['liquido'] += (float)$r['valor_liquido'];
            if ($r['status'] === 'pago')         $totais['pago']      += (float)$r['valor_liquido'];
            elseif ($r['status'] === 'pendente') $totais['a_receber'] += (float)$r['valor_liquido'];
        }

        require __DIR__ . '/../Views/oficina/financeiro.php';
    }

    // --- HISTORICO ---
    public function historico(): void
    {
        $oficina = $this->getOficina();
        $stmt = getPDO()->prepare(
            "SELECT * FROM pedidos WHERE oficina_id = ?
             ORDER BY criado_em DESC LIMIT 100"
        );
        $stmt->execute([(int)$oficina['id']]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        require __DIR__ . '/../Views/oficina/historico.php';
    }

    // --- PERFIL ---
    public function perfilForm(): void
    {
        $oficina = $this->getOficina();

        $stmt = getPDO()->prepare("SELECT tipo FROM oficina_servicos WHERE oficina_id = ? AND ativo = 1");
        $stmt->execute([(int)$oficina['id']]);
        $servicosAtuais = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'tipo');

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/oficina/perfil.php';
    }

    public function perfilSalvar(): void
    {
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }

        $oficina = $this->getOficina();
        $dados = [
            'nome'                => trim((string)($_POST['nome'] ?? '')),
            'telefone'            => preg_replace('/\D/', '', (string)($_POST['telefone'] ?? '')),
            'endereco'            => trim((string)($_POST['endereco'] ?? '')),
            'latitude'            => ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null,
            'longitude'           => ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null,
            'raio_atendimento_km' => max(1, min(200, (int)($_POST['raio_atendimento_km'] ?? 10))),
            'pix_chave'           => trim((string)($_POST['pix_chave'] ?? '')) ?: null,
            'pix_tipo'            => in_array($_POST['pix_tipo'] ?? '', ['cpf','cnpj','email','telefone','aleatoria'], true) ? $_POST['pix_tipo'] : null,
            'banco_codigo'        => trim((string)($_POST['banco_codigo'] ?? '')) ?: null,
            'banco_agencia'       => trim((string)($_POST['banco_agencia'] ?? '')) ?: null,
            'banco_conta'         => trim((string)($_POST['banco_conta'] ?? '')) ?: null,
        ];

        if (mb_strlen($dados['nome']) < 3) {
            $this->setFlashMessage('Nome deve ter ao menos 3 caracteres.', 'error');
            $this->redirect('/oficina/perfil');
        }
        if ($dados['endereco'] === '') {
            $this->setFlashMessage('Informe o endereco da oficina.', 'error');
            $this->redirect('/oficina/perfil');
        }

        try {
            getPDO()->prepare(
                "UPDATE oficinas SET
                    nome = :nome, telefone = :telefone, endereco = :endereco,
                    latitude = :latitude, longitude = :longitude,
                    raio_atendimento_km = :raio,
                    pix_chave = :pix_chave, pix_tipo = :pix_tipo,
                    banco_codigo = :banco_codigo, banco_agencia = :banco_agencia,
                    banco_conta = :banco_conta
                 WHERE id = :id"
            )->execute([
                ':nome'          => $dados['nome'],
                ':telefone'      => $dados['telefone'],
                ':endereco'      => $dados['endereco'],
                ':latitude'      => $dados['latitude'],
                ':longitude'     => $dados['longitude'],
                ':raio'          => $dados['raio_atendimento_km'],
                ':pix_chave'     => $dados['pix_chave'],
                ':pix_tipo'      => $dados['pix_tipo'],
                ':banco_codigo'  => $dados['banco_codigo'],
                ':banco_agencia' => $dados['banco_agencia'],
                ':banco_conta'   => $dados['banco_conta'],
                ':id'            => (int)$oficina['id'],
            ]);
            $this->setFlashMessage('Perfil atualizado!', 'success');
        } catch (Throwable $e) {
            error_log('[OficinaController][perfilSalvar] ' . $e->getMessage());
            $this->setFlashMessage('Erro ao salvar perfil.', 'error');
        }
        $this->redirect('/oficina/perfil');
    }

    public function salvarServicos(): void
    {
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $oficina = $this->getOficina();
        $oficinaId = (int)$oficina['id'];

        $permitidos = ['borracharia','eletrica','bateria','mecanica','chaveiro',
                       'funilaria','ar_condicionado','injecao','suspensao'];
        $marcados = array_values(array_intersect($permitidos, (array)($_POST['servicos'] ?? [])));

        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            $pdo->prepare("DELETE FROM oficina_servicos WHERE oficina_id = ?")->execute([$oficinaId]);

            if ($marcados) {
                $vals = [];
                $params = [];
                foreach ($marcados as $t) {
                    $vals[] = '(?, ?, 1)';
                    $params[] = $oficinaId;
                    $params[] = $t;
                }
                $sql = "INSERT INTO oficina_servicos (oficina_id, tipo, ativo) VALUES " . implode(',', $vals);
                $pdo->prepare($sql)->execute($params);
            }

            $pdo->commit();
            $this->setFlashMessage('Servicos atualizados!', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[OficinaController][salvarServicos] ' . $e->getMessage());
            $this->setFlashMessage('Erro ao salvar servicos.', 'error');
        }
        $this->redirect('/oficina/perfil');
    }

    // --- REGISTRAR EVIDENCIA ---
    public function registrarEvidencia(int $pedidoId): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Token invalido.']);
            exit;
        }
        $oficina = $this->getOficina();
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (int)($pedido['oficina_id'] ?? 0) !== (int)$oficina['id']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'erro' => 'Pedido nao e seu.']);
            exit;
        }

        $tipos = ['chegada','servico_iniciado','orcamento_enviado','servico_concluido'];
        $tipo = (string)($_POST['tipo'] ?? '');
        if (!in_array($tipo, $tipos, true)) {
            echo json_encode(['ok' => false, 'erro' => 'Tipo invalido.']);
            exit;
        }

        $fotoPath = null;
        if (!empty($_FILES['foto']['name']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $fotoPath = $this->armazenarFoto($_FILES['foto'], $pedidoId, $tipo);
            if ($fotoPath === null) {
                echo json_encode(['ok' => false, 'erro' => 'Foto invalida (tipo/tamanho).']);
                exit;
            }
        }

        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO oficina_evidencias
                    (pedido_id, oficina_id, tipo, foto_path, observacao, latitude, longitude)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $pedidoId,
                (int)$oficina['id'],
                $tipo,
                $fotoPath,
                trim((string)($_POST['observacao'] ?? '')) ?: null,
                ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null,
                ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null,
            ]);

            $basePath = defined('BASE_PATH') ? BASE_PATH : '';
            echo json_encode([
                'ok' => true,
                'evidencia_id' => (int)$pdo->lastInsertId(),
                'foto_url' => $fotoPath ? ($basePath . '/oficina/arquivo/' . $fotoPath) : null,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[OficinaController][registrarEvidencia] ' . $e->getMessage());
            echo json_encode(['ok' => false, 'erro' => 'Erro ao salvar evidencia.']);
        }
        exit;
    }

    private function armazenarFoto(array $file, int $pedidoId, string $tipo): ?string
    {
        if (($file['size'] ?? 0) > 8 * 1024 * 1024) return null;

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file((string)$file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) return null;

        $destDir = dirname((string)PUBLIC_PATH) . '/storage/oficina';
        if (!is_dir($destDir)) @mkdir($destDir, 0770, true);

        $storedName = sprintf('ped%d_%s_%s.%s',
            $pedidoId, $tipo, bin2hex(random_bytes(6)), $allowed[$mime]
        );
        $dest = $destDir . '/' . $storedName;

        $ok = is_uploaded_file((string)$file['tmp_name'])
            ? move_uploaded_file((string)$file['tmp_name'], $dest)
            : rename((string)$file['tmp_name'], $dest);

        return $ok ? $storedName : null;
    }

    // --- SERVIR FOTO ---
    public function servirFoto(string $nome): void
    {
        $nome = basename($nome);
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.(jpg|png|webp)$/', $nome)) {
            http_response_code(400); exit;
        }

        $oficina = $this->getOficina();
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT foto_path FROM oficina_evidencias
             WHERE foto_path = ? AND oficina_id = ? LIMIT 1"
        );
        $stmt->execute([$nome, (int)$oficina['id']]);
        if (!$stmt->fetchColumn()) {
            http_response_code(403); exit;
        }

        $path = dirname((string)PUBLIC_PATH) . '/storage/oficina/' . $nome;
        if (!is_file($path)) { http_response_code(404); exit; }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    // --- ENVIAR ORCAMENTO ---
    public function enviarOrcamento(int $pedidoId): void
    {
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit;
        }
        $oficina = $this->getOficina();
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (int)($pedido['oficina_id'] ?? 0) !== (int)$oficina['id']) {
            $this->setFlashMessage('Pedido nao e seu.', 'error');
            $this->redirect('/oficina/dashboard');
        }

        $valor = (float)($_POST['valor_total'] ?? 0);
        $desc  = trim((string)($_POST['descricao'] ?? ''));
        if ($valor <= 0 || $desc === '') {
            $this->setFlashMessage('Preencha valor e descricao.', 'error');
            $this->redirect('/oficina/pedido/' . $pedidoId);
        }

        try {
            $pdo = getPDO();
            $pdo->prepare(
                "INSERT INTO oficina_orcamentos (pedido_id, oficina_id, valor_total, descricao, status)
                 VALUES (?, ?, ?, ?, 'pendente')"
            )->execute([$pedidoId, (int)$oficina['id'], $valor, $desc]);

            $pdo->prepare("UPDATE pedidos SET status = 'orcamento_enviado' WHERE id = ?")
                ->execute([$pedidoId]);

            $this->setFlashMessage('Orcamento enviado. Aguardando resposta.', 'success');
        } catch (Throwable $e) {
            error_log('[OficinaController][enviarOrcamento] ' . $e->getMessage());
            $this->setFlashMessage('Erro ao enviar orcamento.', 'error');
        }
        $this->redirect('/oficina/pedido/' . $pedidoId);
    }

    

    /** Financeiro com filtro + tabela. */
    public function financeiroPage(): void
    {
        $oficina = $this->getOficina();
        $oficinaId = (int)$oficina['id'];

        $mes = (int)($_GET['mes'] ?? date('m'));
        $ano = (int)($_GET['ano'] ?? date('Y'));
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim    = date('Y-m-t', strtotime($inicio));

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT * FROM oficina_repasses
             WHERE oficina_id = ? AND criado_em BETWEEN ? AND ?
             ORDER BY criado_em DESC"
        );
        $stmt->execute([$oficinaId, $inicio . ' 00:00:00', $fim . ' 23:59:59']);
        $repasses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totais = ['bruto'=>0.0,'taxa'=>0.0,'liquido'=>0.0,'pago'=>0.0,'a_receber'=>0.0];
        foreach ($repasses as $r) {
            $totais['bruto']   += (float)$r['valor_bruto'];
            $totais['taxa']    += (float)$r['taxa_plataforma'];
            $totais['liquido'] += (float)$r['valor_liquido'];
            if ($r['status'] === 'pago')         $totais['pago']      += (float)$r['valor_liquido'];
            elseif ($r['status'] === 'pendente') $totais['a_receber'] += (float)$r['valor_liquido'];
        }

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/oficina/financeiro.php';
    }

    /** Recusa um pedido (adiciona a uma lista de skip por sessão). */
    public function recusar(int $id): void
    {
        $oficina = $this->getOficina();
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->redirect('/oficina/dashboard');
        }
        if (!isset($_SESSION['oficina_recusados']) || !is_array($_SESSION['oficina_recusados'])) {
            $_SESSION['oficina_recusados'] = [];
        }
        $_SESSION['oficina_recusados'][(int)$id] = time();
        // Expira lista em 1h
        foreach ($_SESSION['oficina_recusados'] as $pid => $ts) {
            if (time() - $ts > 3600) unset($_SESSION['oficina_recusados'][$pid]);
        }
        $this->setFlashMessage('Pedido recusado.', 'info');
        $this->redirect($_SERVER['HTTP_REFERER'] ?? '/oficina/dashboard');
    }

    /** Página HTML com todos os pedidos disponíveis. */
    public function pedidosPage(): void
    {
        $oficina = $this->getOficina();
        $pedidos = $this->buscarPedidosProximos($oficina);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/oficina/pedidos.php';
    }
}
