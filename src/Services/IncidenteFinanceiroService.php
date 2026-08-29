<?php
declare(strict_types=1);

final class IncidenteFinanceiroService
{
    public static function registrar(int $incidenteId, string $tipo, string $referenciaTipo, int $referenciaId, float $valor, string $status = 'confirmado'): int
    {
        $permitidos = ['cobranca_cliente','repasse_especialista','repasse_guincho','taxa_plataforma','estorno','ajuste'];
        if (!in_array($tipo, $permitidos, true)) throw new InvalidArgumentException('Tipo financeiro inválido.');
        $existing = getPDO()->prepare('SELECT id FROM financeiro_lancamentos WHERE incidente_id=? AND tipo=? AND referencia_tipo=? AND referencia_id=? ORDER BY id DESC LIMIT 1');
        $existing->execute([$incidenteId,$tipo,$referenciaTipo,$referenciaId]);
        $found = $existing->fetchColumn();
        if ($found) return (int)$found;
        $stmt = getPDO()->prepare('INSERT INTO financeiro_lancamentos (incidente_id,tipo,referencia_tipo,referencia_id,valor,status) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$incidenteId,$tipo,$referenciaTipo,$referenciaId,round($valor,2),$status]);
        return (int)getPDO()->lastInsertId();
    }

    public static function confirmarRepasseEspecialista(int $atendimentoId): int
    {
        $st=getPDO()->prepare("UPDATE financeiro_lancamentos SET status='confirmado' WHERE tipo='repasse_especialista' AND referencia_tipo='atendimento_especialista' AND referencia_id=? AND status IN ('pendente','processando')");
        $st->execute([$atendimentoId]); return $st->rowCount();
    }
}
