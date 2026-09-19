<?php
declare(strict_types=1);

final class PedidoIndicacaoOficina
{
    public const SELECIONADA='SELECIONADA';
    public const EM_TRANSITO='EM_TRANSITO';
    public const AGUARDANDO_CHECKIN='AGUARDANDO_CHECKIN';
    public const CHECKIN_PENDENTE='CHECKIN_PENDENTE';
    public const QUALIFICADA='QUALIFICADA';
    public const REJEITADA='REJEITADA';
    public const COBRANCA_GERADA='COBRANCA_GERADA';
    public const LIQUIDADA='LIQUIDADA';
    public const ESTORNADA='ESTORNADA';
    public const CANCELADA='CANCELADA';
    public const TERMINAIS=[self::COBRANCA_GERADA,self::LIQUIDADA,self::ESTORNADA,self::REJEITADA,self::CANCELADA];

    public static function buscarPorPedido(int $pedidoId, bool $lock=false): ?array
    {
        $sql='SELECT i.*, p.legal_name, p.trade_name, p.pix_key, ws.address, ws.latitude, ws.longitude, ws.taxa_indicacao_fixa, ws.regra_versao, ws.status_parceria, ws.raio_checkin_m FROM pedido_indicacoes_oficina i JOIN providers p ON p.id=i.provider_id LEFT JOIN provider_workshop_settings ws ON ws.provider_id=p.id WHERE i.pedido_id=? LIMIT 1';
        if($lock) $sql.=' FOR UPDATE';
        $s=getPDO()->prepare($sql);$s->execute([$pedidoId]);return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public static function buscarPorId(int $id, bool $lock=false): ?array
    {
        $sql='SELECT i.*, p.legal_name, p.trade_name, p.pix_key, ws.address, ws.latitude, ws.longitude, ws.taxa_indicacao_fixa, ws.regra_versao, ws.status_parceria, ws.raio_checkin_m FROM pedido_indicacoes_oficina i JOIN providers p ON p.id=i.provider_id LEFT JOIN provider_workshop_settings ws ON ws.provider_id=p.id WHERE i.id=? LIMIT 1';
        if($lock) $sql.=' FOR UPDATE';
        $s=getPDO()->prepare($sql);$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public static function listarOficinasAtivas(): array
    {
        $s=getPDO()->query("SELECT p.*,ws.taxa_indicacao_fixa,ws.raio_checkin_m FROM providers p JOIN provider_workshop_settings ws ON ws.provider_id=p.id WHERE p.provider_type='WORKSHOP' AND p.approval_status='APPROVED' AND p.active=1 AND ws.status_parceria='ATIVO' ORDER BY COALESCE(p.trade_name,p.legal_name)");return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function listarPendentes(): array
    {
        $s=getPDO()->query("SELECT i.*,p.legal_name,p.trade_name,ws.taxa_indicacao_fixa FROM pedido_indicacoes_oficina i JOIN providers p ON p.id=i.provider_id LEFT JOIN provider_workshop_settings ws ON ws.provider_id=p.id WHERE i.status IN ('CHECKIN_PENDENTE','QUALIFICADA','COBRANCA_GERADA','ESTORNADA') ORDER BY i.updated_at DESC");return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function criar(array $d): array
    {
        $pdo=getPDO();$s=$pdo->prepare('INSERT INTO pedido_indicacoes_oficina (pedido_id,provider_id,status,regra_comissao_snapshot_json,idempotency_key,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $s->execute([(int)$d['pedido_id'],(int)$d['provider_id'],(string)($d['status']??self::SELECIONADA),isset($d['regra_snapshot'])?json_encode($d['regra_snapshot'],JSON_UNESCAPED_UNICODE):null,(string)$d['idempotency_key']]);
        return self::buscarPorId((int)$pdo->lastInsertId()) ?? self::buscarPorPedido((int)$d['pedido_id']);
    }
    public static function atualizar(int $id,array $d): bool
    {
        $sets=[];$vals=[];foreach(['status','checkin_evidencia_id','checkin_geofence_ok','checkin_distancia_m','revisao_admin_id','revisao_admin_nota','order_charge_item_id'] as $k){if(array_key_exists($k,$d)){$sets[]="`$k`=?";$vals[]=$d[$k];}}if(!$sets)return true;$sets[]='updated_at=NOW()';$vals[]=$id;$s=getPDO()->prepare('UPDATE pedido_indicacoes_oficina SET '.implode(',',$sets).' WHERE id=?');return $s->execute($vals);
    }
}
