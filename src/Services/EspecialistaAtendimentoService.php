<?php
declare(strict_types=1);

require_once __DIR__ . '/EspecialistaAtendimentoStateMachine.php';

final class EspecialistaAtendimentoService
{
    public static function listarDoEspecialista(int $especialistaId): array
    {
        $stmt = getPDO()->prepare("SELECT a.*, i.tipo_problema, i.descricao_problema, i.endereco_origem,
                                          i.lat_origem, i.lng_origem, s.nome AS servico_nome
                                     FROM atendimentos_especialista a
                                     JOIN incidentes i ON i.id=a.incidente_id
                                     JOIN servicos_especialista s ON s.id=a.servico_solicitado_id
                                    WHERE a.especialista_id=?
                                    ORDER BY a.criado_em DESC");
        $stmt->execute([$especialistaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function aceitar(int $atendimentoId, int $especialistaId): array
    {
        return self::transicionar($atendimentoId, $especialistaId, 'aceito', ['ofertado']);
    }

    public static function transicionar(int $atendimentoId, int $especialistaId, string $status, array $origens = []): array
    {
        $pdo = getPDO();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM atendimentos_especialista WHERE id=? AND especialista_id=? FOR UPDATE');
            $stmt->execute([$atendimentoId, $especialistaId]);
            $a = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$a) throw new DomainException('Atendimento não encontrado.');
            if ($origens && !in_array($a['status'], $origens, true)) throw new DomainException('Atendimento não está disponível para esta ação.');
            EspecialistaAtendimentoStateMachine::validar((string)$a['status'], $status);
            $colunas = ['status=?'];
            $params = [$status];
            if ($status === 'aceito') { $colunas[] = 'aceito_em=NOW()'; }
            if ($status === 'a_caminho') { $colunas[] = 'iniciado_em=NOW()'; }
            if ($status === 'no_local') { $colunas[] = 'chegou_em=NOW()'; }
            if ($status === 'resolvido') { $colunas[] = 'concluido_em=NOW()'; }
            $params[] = $atendimentoId;
            $pdo->prepare('UPDATE atendimentos_especialista SET '.implode(', ', $colunas).' WHERE id=?')->execute($params);
            $map = ['aceito'=>'em_atendimento','a_caminho'=>'em_atendimento','no_local'=>'em_atendimento','em_diagnostico'=>'em_atendimento','em_execucao'=>'em_atendimento','resolvido'=>'resolvido_local','necessita_reboque'=>'necessita_reboque','cancelado'=>'cancelado'];
            if (isset($map[$status])) $pdo->prepare('UPDATE incidentes SET status=? WHERE id=?')->execute([$map[$status], $a['incidente_id']]);
            $pdo->commit();
            if ($status === 'resolvido') {
                require_once __DIR__ . '/IncidenteFinanceiroService.php';
                IncidenteFinanceiroService::confirmarRepasseEspecialista($atendimentoId);
            }
            $a['status'] = $status;
            return $a;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function registrarDiagnostico(int $atendimentoId, int $especialistaId, string $resultado, string $descricao, array $itens = []): array
    {
        $resultado = strtolower(trim($resultado));
        // Fase inicial: não existe orçamento livre nem venda de peças pelo
        // especialista. Qualquer cobrança adicional deve nascer de um item
        // previamente publicado e precificado pela plataforma; rejeitamos
        // também chamadas manuais com valores arbitrários.
        if (in_array($resultado, ['orcamento', 'orçamento'], true)) {
            throw new DomainException('Orçamentos e venda de peças ainda não estão disponíveis. Use um serviço do catálogo ou solicite reboque.');
        }
        foreach ($itens as $item) {
            $tipoItem = strtolower(trim((string)($item['tipo'] ?? 'servico')));
            if (in_array($tipoItem, ['peca', 'peça', 'material'], true)) {
                throw new DomainException('Peças e materiais só poderão ser cobrados pelo catálogo central da plataforma.');
            }
        }
        $destino = match ($resultado) {
            'resolvido' => 'resolvido',
            'orcamento', 'orçamento' => 'aguardando_aprovacao',
            'reboque', 'necessita_reboque' => 'necessita_reboque',
            default => throw new DomainException('Resultado de diagnóstico inválido.'),
        };
        if ($destino === 'aguardando_aprovacao') {
            if (!$itens) throw new DomainException('Informe ao menos um item para o orçamento.');
            foreach ($itens as $item) {
                if (max(0, (float)($item['valor_unitario'] ?? $item['valor'] ?? 0)) <= 0 || trim((string)($item['descricao'] ?? '')) === '') {
                    throw new DomainException('Item de orçamento inválido.');
                }
            }
        }
        $a = self::transicionar($atendimentoId, $especialistaId, 'em_diagnostico', ['no_local']);
        if ($destino === 'aguardando_aprovacao') {
            $pdo = getPDO();
            $stmt = $pdo->prepare('INSERT INTO atendimento_itens (atendimento_id,tipo,descricao,quantidade,valor_unitario,valor_total,status) VALUES (?,?,?,?,?,?,\'proposto\')');
            foreach ($itens as $item) {
                $q = max(1, (float)($item['quantidade'] ?? 1));
                $v = max(0, (float)($item['valor_unitario'] ?? $item['valor'] ?? 0));
                if ($v <= 0 || trim((string)($item['descricao'] ?? '')) === '') throw new DomainException('Item de orçamento inválido.');
                $stmt->execute([$atendimentoId, in_array(($item['tipo'] ?? 'servico'), ['servico','peca','material'], true) ? $item['tipo'] : 'servico', trim((string)$item['descricao']), $q, $v, round($q*$v,2)]);
            }
        }
        return self::transicionar($atendimentoId, $especialistaId, $destino, ['em_diagnostico']);
    }

    public static function decidirOrcamento(int $atendimentoId, int $clienteId, bool $aprovado): array
    {
        $pdo = getPDO();
        $pdo->beginTransaction();
        try {
        $stmt = $pdo->prepare('SELECT a.* FROM atendimentos_especialista a JOIN incidentes i ON i.id=a.incidente_id WHERE a.id=? AND i.cliente_id=? AND a.status=\'aguardando_aprovacao\' FOR UPDATE');
        $stmt->execute([$atendimentoId, $clienteId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) throw new DomainException('Orçamento não encontrado ou não está pendente.');
        $novo = $aprovado ? 'aprovado' : 'recusado';
        $pdo->prepare('UPDATE atendimento_itens SET status=? WHERE atendimento_id=? AND status=\'proposto\'')->execute([$novo, $atendimentoId]);
        if ($aprovado) {
            $pdo->prepare('UPDATE atendimentos_especialista SET status=\'em_execucao\' WHERE id=?')->execute([$atendimentoId]);
            $pdo->prepare("UPDATE incidentes SET status='em_atendimento' WHERE id=?")->execute([(int)$a['incidente_id']]);
            $sum = $pdo->prepare('SELECT COALESCE(SUM(valor_total),0) FROM atendimento_itens WHERE atendimento_id=? AND status=\'aprovado\'');
            $sum->execute([$atendimentoId]);
            require_once __DIR__ . '/IncidenteFinanceiroService.php';
            IncidenteFinanceiroService::registrar((int)$a['incidente_id'], 'cobranca_cliente', 'atendimento_especialista', $atendimentoId, (float)$sum->fetchColumn(), 'pendente');
        } else {
            $pdo->prepare("UPDATE atendimentos_especialista SET status='necessita_reboque' WHERE id=?")->execute([$atendimentoId]);
            $pdo->prepare("UPDATE incidentes SET status='necessita_reboque', resolucao_tipo='reboque' WHERE id=?")->execute([(int)$a['incidente_id']]);
        }
        $pdo->commit();
        return $a;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
