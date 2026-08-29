<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Incidente.php';
require_once __DIR__ . '/Logger.php';

final class TowEscalationService
{
    public static function solicitar(int $incidenteId, int $clienteId): bool
    {
        Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'especialista_fallback', 'Iniciando solicitação de reboque.', ['incidente_id'=>$incidenteId,'cliente_id'=>$clienteId]);
        $pdo = getPDO();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM incidentes WHERE id=? AND cliente_id=? FOR UPDATE');
            $st->execute([$incidenteId, $clienteId]);
            $inc = $st->fetch(PDO::FETCH_ASSOC);
            if (!$inc) throw new DomainException('Incidente não encontrado.');
            if ($inc['status'] !== 'necessita_reboque') throw new DomainException('Este incidente não pode ser escalonado agora.');
            $pdo->prepare("UPDATE incidentes SET status='procurando_guincho', resolucao_tipo='reboque' WHERE id=?")->execute([$incidenteId]);
            $pdo->prepare("UPDATE pedidos SET status='aguardando_guincho', attendance_mode='TOWING', guincho_id=NULL WHERE incidente_id=?")->execute([$incidenteId]);
            // O deslocamento/diagnóstico do especialista é devido mesmo sem resolução local.
            $pdo->prepare("UPDATE financeiro_lancamentos SET status='confirmado' WHERE incidente_id=? AND tipo='repasse_especialista' AND referencia_tipo='atendimento_especialista' AND status IN ('pendente','processando')")->execute([$incidenteId]);
            $pdo->commit();
            Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'especialista_fallback', 'Fallback para guincho concluído.', ['incidente_id'=>$incidenteId]); return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
