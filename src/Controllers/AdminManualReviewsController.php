<?php
/** F18F.1.13: fila de auditoria de conclusoes manuais. Sem mutacoes GET. */
class AdminManualReviewsController
{
    public function index(): void
    {
        AuthService::requireAuth('admin');
        $status = (string)($_GET['status'] ?? 'pendente');
        if (!in_array($status, ['pendente', 'confirmada', 'rejeitada', 'todos'], true)) {
            $status = 'pendente';
        }
        $pagina = max(1, min(100000, filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
        $pedidoId = filter_var($_GET['pedido_id'] ?? 0, FILTER_VALIDATE_INT);
        $pedidoId = $pedidoId && $pedidoId > 0 ? $pedidoId : 0;
        $pdo = getPDO();
        // Falha explicita se a migration obrigatoria nao foi aplicada.
        $campos = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'revisao_manual_status'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$campos) {
            http_response_code(503);
            echo 'Estrutura de revisao manual indisponivel. Verifique a migration_conclusao_manual_v1.sql.';
            return;
        }
        $where = ['concluido_manualmente = 1'];
        $params = [];
        if ($status !== 'todos') {
            $where[] = 'revisao_manual_status = :status';
            $params['status'] = $status;
        }
        if ($pedidoId > 0) {
            $where[] = 'id = :pedido_id';
            $params['pedido_id'] = $pedidoId;
        }
        $clause = implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE $clause");
        foreach ($params as $k => $v) $count->bindValue(':' . $k, $v, $k === 'pedido_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        $count->execute();
        $total = (int)$count->fetchColumn();
        $limite = 25;
        $totalPaginas = max(1, (int)ceil($total / $limite));
        $pagina = min($pagina, $totalPaginas);
        $offset = ($pagina - 1) * $limite;
        $query = $pdo->prepare("SELECT id, status, revisao_manual_status, concluido_manual_em, concluido_manual_admin_id,
                                       revisao_manual_em, revisao_manual_admin_id, revisao_manual_nota
                                  FROM pedidos WHERE $clause
                              ORDER BY COALESCE(concluido_manual_em, '1970-01-01') DESC, id DESC
                                 LIMIT :limite OFFSET :offset");
        foreach ($params as $k => $v) $query->bindValue(':' . $k, $v, $k === 'pedido_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        $query->bindValue(':limite', $limite, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $pedidos = $query->fetchAll(PDO::FETCH_ASSOC);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/revisoes_manuais.php';
    }
}
