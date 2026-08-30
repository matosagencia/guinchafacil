<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Incidente.php';
require_once __DIR__ . '/../Services/GeoService.php';
require_once __DIR__ . '/EspecialistaAtendimentoStateMachine.php';

final class EspecialistaDispatchService
{
    private const SERVICE_MAP = [
        'BATTERY_DIAG' => ['BATTERY_DIAG', 'BATTERY_TEST', 'ELECTRICAL_DIAG'],
        'BATTERY_JUMP' => ['BATTERY_JUMP', 'JUMP_START'],
        'BATTERY_REPLACE' => ['BATTERY_REPLACE', 'BATTERY_REPLACEMENT'],
        'TIRE_CHANGE' => ['TIRE_CHANGE'],
        'FUEL_DELIVERY' => ['FUEL_DELIVERY'],
        'LOCKOUT' => ['LOCKOUT', 'AUTOMOTIVE_LOCKSMITH'],
        'ELECTRICAL_DIAG' => ['ELECTRICAL_DIAG', 'ELECTRICAL_DIAGNOSIS'],
    ];

    public static function disparar(int $incidenteId, string $servicoCodigo, float $providerAmount, float $customerAmount, float $platformAmount): ?int
    {
        $candidatos = self::candidatos($incidenteId, $servicoCodigo);
        if (!$candidatos) return null;
        $codes = self::SERVICE_MAP[strtoupper($servicoCodigo)] ?? [strtoupper($servicoCodigo)];
        $marks = implode(',', array_fill(0, count($codes), '?'));
        $stmt = getPDO()->prepare("SELECT id FROM servicos_especialista WHERE ativo=1 AND codigo IN ($marks) LIMIT 1");
        $stmt->execute($codes);
        $servicoId = (int)$stmt->fetchColumn();
        if ($servicoId <= 0) return null;
        return self::ofertar($incidenteId, (int)$candidatos[0]['especialista_id'], $servicoId, $providerAmount, $platformAmount, $customerAmount);
    }

    public static function candidatos(int $incidenteId, string $servicoCodigo): array
    {
        $incidente = Incidente::buscarPorId($incidenteId);
        if (!$incidente) return [];
        return self::candidatosPorCoordenada((float)$incidente['lat_origem'], (float)$incidente['lng_origem'], $servicoCodigo);
    }

    public static function candidatosPorCoordenada(float $latOrigem, float $lngOrigem, string $servicoCodigo): array
    {
        $codigos = self::SERVICE_MAP[strtoupper($servicoCodigo)] ?? [strtoupper($servicoCodigo)];
        $marks = implode(',', array_fill(0, count($codigos), '?'));
        $sql = "SELECT e.id AS especialista_id, u.nome, e.lat_atual, e.lng_atual,
                       e.raio_atendimento_km, e.reputacao, s.codigo AS servico_codigo
                  FROM especialistas e
                  JOIN usuarios u ON u.id=e.usuario_id AND u.ativo=1
                  JOIN especialista_servicos es ON es.especialista_id=e.id AND es.habilitado=1
                  JOIN servicos_especialista s ON s.id=es.servico_id AND s.ativo=1
                 WHERE e.aprovado=1 AND e.disponivel=1 AND s.codigo IN ($marks)
                   AND e.lat_atual IS NOT NULL AND e.lng_atual IS NOT NULL";
        $stmt = getPDO()->prepare($sql);
        $stmt->execute($codigos);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dist = GeoService::haversine($latOrigem, $lngOrigem, (float)$row['lat_atual'], (float)$row['lng_atual']);
            if ($dist <= (float)$row['raio_atendimento_km']) {
                $row['distancia_km'] = round($dist, 3);
                $row['score'] = round(((float)$row['reputacao'] * 10) - $dist, 4);
                $out[] = $row;
            }
        }
        usort($out, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return $out;
    }

    public static function ofertar(int $incidenteId, int $especialistaId, int $servicoId, float $providerAmount, float $platformAmount, float $customerAmount): int
    {
        $pdo = getPDO();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id, status FROM atendimentos_especialista WHERE incidente_id=? AND status IN (\'ofertado\',\'aceito\',\'a_caminho\',\'no_local\',\'em_diagnostico\',\'em_execucao\') FOR UPDATE');
            $lock->execute([$incidenteId]);
            if ($lock->fetch()) throw new DomainException('Incidente já possui atendimento ativo.');
            $stmt = $pdo->prepare('INSERT INTO atendimentos_especialista
                (incidente_id, especialista_id, servico_solicitado_id, status, ofertado_em, expiracao_oferta, provider_amount, platform_amount, customer_amount)
                VALUES (?,?,?,? ,NOW(),DATE_ADD(NOW(), INTERVAL 5 MINUTE),?,?,?)');
            $stmt->execute([$incidenteId, $especialistaId, $servicoId, 'ofertado', $providerAmount, $platformAmount, $customerAmount]);
            Incidente::atualizarStatus($incidenteId, 'especialista_designado', null, $pdo);
            $id = (int)$pdo->lastInsertId();
            if ($ownTransaction) $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function expirarOfertas(): int
    {
        $stmt=getPDO()->prepare("UPDATE atendimentos_especialista SET status='procurando', especialista_id=NULL WHERE status='ofertado' AND expiracao_oferta IS NOT NULL AND expiracao_oferta < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
