<?php
declare(strict_types=1);
require_once __DIR__.'/../Models/Provider/Provider.php';
require_once __DIR__.'/../Models/Pedido.php';
require_once __DIR__.'/../Models/PedidoIndicacaoOficina.php';
require_once __DIR__.'/../Models/Financial/OrderChargeItem.php';
require_once __DIR__.'/../Models/Financial/ChargeCodes.php';
require_once __DIR__.'/../Models/Configuracao.php';
require_once __DIR__.'/AuditTrailService.php';
require_once __DIR__.'/POR/GeofenceService.php';

final class IndicacaoOficinaService
{
    public static function ativo(): bool { return (string)Configuracao::get('monetizacao_oficinas_ativo','0')==='1'; }
    private static function ensureAtivo(): void { if(!self::ativo()) throw new RuntimeException('Monetização de oficinas desativada.'); }
    public static function registrarSelecao(int $pedidoId,int $providerId,int $actorId): array
    {
        self::ensureAtivo();$pedido=Pedido::buscarPorId($pedidoId);$provider=Provider::buscarPorId($providerId);
        if(!$pedido|| (int)($pedido['cliente_id']??0)!==$actorId) throw new InvalidArgumentException('IND-001: pedido inválido.');
        if(!$provider||$provider['provider_type']!==Provider::TYPE_WORKSHOP||$provider['approval_status']!=='APPROVED'||(int)$provider['active']!==1) throw new InvalidArgumentException('IND-001: oficina parceira inválida.');
        $settings=self::settings($providerId);if(!$settings||$settings['status_parceria']!=='ATIVO') throw new InvalidArgumentException('IND-001: oficina inativa.');
        $row=PedidoIndicacaoOficina::criar(['pedido_id'=>$pedidoId,'provider_id'=>$providerId,'idempotency_key'=>'indicacao_oficina:'.$pedidoId,'regra_snapshot'=>['taxa_indicacao_fixa'=>(float)$settings['taxa_indicacao_fixa'],'regra_versao'=>$settings['regra_versao'],'provider_id'=>$providerId]]);
        AuditTrailService::evento('indicacao_oficina_selecionada',__CLASS__,__FUNCTION__,['pedido_id'=>$pedidoId,'provider_id'=>$providerId,'actor_id'=>$actorId]);return $row;
    }
    public static function processarEntrega(int $pedidoId): array
    {
        $row=PedidoIndicacaoOficina::buscarPorPedido($pedidoId);if(!$row)return [];
        if(in_array($row['status'],PedidoIndicacaoOficina::TERMINAIS,true))return $row;
        $pedido=Pedido::buscarPorId($pedidoId);$lat=(float)($pedido['lat_destino']??0);$lng=(float)($pedido['lng_destino']??0);$radius=(int)($row['raio_checkin_m']??150);
        if(!$lat||!$lng){PedidoIndicacaoOficina::atualizar((int)$row['id'],['status'=>PedidoIndicacaoOficina::CHECKIN_PENDENTE,'checkin_geofence_ok'=>0]);AuditTrailService::evento('indicacao_oficina_checkin_pendente',__CLASS__,__FUNCTION__,['pedido_id'=>$pedidoId,'motivo'=>'destino_sem_coordenadas']);return PedidoIndicacaoOficina::buscarPorId((int)$row['id']);}
        PedidoIndicacaoOficina::atualizar((int)$row['id'],['status'=>PedidoIndicacaoOficina::AGUARDANDO_CHECKIN]);return PedidoIndicacaoOficina::buscarPorId((int)$row['id']);
    }
    public static function confirmarCheckin(int $pedidoId,array $payload): array
    {
        self::ensureAtivo();$pdo=getPDO();$pdo->beginTransaction();try{$row=PedidoIndicacaoOficina::buscarPorPedido($pedidoId,true);$pedido=Pedido::buscarPorId($pedidoId);$guinchoId=(int)($payload['guincho_id']??0);if(!$row||!$pedido)throw new InvalidArgumentException('IND-001: indicação inexistente.');if($guinchoId<=0||(int)($pedido['guincho_id']??0)!==$guinchoId)throw new RuntimeException('IND-001: guincho não vinculado ao pedido.');if(in_array($row['status'],[PedidoIndicacaoOficina::COBRANCA_GERADA,PedidoIndicacaoOficina::LIQUIDADA],true)){ $pdo->commit();return $row; }$evidencia=EvidenceService::storeUploadedEvidence($pedido,(int)$payload['guincho_id'],'CHECKIN_OFICINA',$payload['file'],$payload['qr_token']);$valor=(float)($row['taxa_indicacao_fixa']??30);$item=OrderChargeItem::criar(['order_id'=>$pedidoId,'provider_id'=>(int)$row['provider_id'],'phase_code'=>ChargeCodes::PHASE_WORKSHOP_REFERRAL,'charge_type'=>ChargeCodes::TYPE_REFERRAL_FEE,'description'=>'Taxa fixa por indicação de oficina parceira','quantity'=>1,'unit_amount'=>$valor,'gross_amount'=>$valor,'platform_fee_amount'=>$valor,'provider_net_amount'=>0,'charge_status'=>ChargeCodes::CHARGE_PENDING,'payable_status'=>ChargeCodes::PAYABLE_NOT_ELIGIBLE,'calculation_version'=>(string)($row['regra_versao']??'v1'),'calculation_context'=>['source'=>'workshop_referral','provider_id'=>(int)$row['provider_id'],'taxa'=>$valor],'evidence_required'=>true,'idempotency_key'=>'referral_fee:'.$pedidoId]);PedidoIndicacaoOficina::atualizar((int)$row['id'],['status'=>PedidoIndicacaoOficina::COBRANCA_GERADA,'checkin_evidencia_id'=>$evidencia['id'],'checkin_geofence_ok'=>1,'order_charge_item_id'=>$item['id']]);$pdo->commit();AuditTrailService::evento('indicacao_oficina_cobranca_gerada',__CLASS__,__FUNCTION__,['pedido_id'=>$pedidoId,'order_charge_item_id'=>$item['id'],'valor'=>$valor]);return PedidoIndicacaoOficina::buscarPorId((int)$row['id']);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function revisarManualmente(int $id,int $adminId,string $veredito,string $nota=''): array
    {
        self::ensureAtivo();$row=PedidoIndicacaoOficina::buscarPorId($id,true);if(!$row)throw new InvalidArgumentException('Indicação não encontrada.');$veredito=strtoupper($veredito);if(!in_array($veredito,['APROVAR','REJEITAR'],true))throw new InvalidArgumentException('Veredito inválido.');$status=$veredito==='APROVAR'?PedidoIndicacaoOficina::QUALIFICADA:PedidoIndicacaoOficina::REJEITADA;PedidoIndicacaoOficina::atualizar($id,['status'=>$status,'revisao_admin_id'=>$adminId,'revisao_admin_nota'=>mb_substr($nota,0,255)]);AuditTrailService::evento('indicacao_oficina_revisada',__CLASS__,__FUNCTION__,['indicacao_id'=>$id,'admin_id'=>$adminId,'veredito'=>$veredito]);return PedidoIndicacaoOficina::buscarPorId($id);
    }
    public static function estornar(int $id,int $adminId,string $motivo): array
    {
        self::ensureAtivo();$row=PedidoIndicacaoOficina::buscarPorId($id);if(!$row||empty($row['order_charge_item_id']))throw new InvalidArgumentException('Cobrança da indicação não encontrada.');$orig=OrderChargeItem::buscarPorId((int)$row['order_charge_item_id']);$rev=OrderChargeItem::criar(['order_id'=>(int)$row['pedido_id'],'provider_id'=>(int)$row['provider_id'],'phase_code'=>ChargeCodes::PHASE_WORKSHOP_REFERRAL,'charge_type'=>ChargeCodes::TYPE_REFUND,'description'=>'Estorno de taxa de indicação de oficina','gross_amount'=>-(float)$orig['gross_amount'],'platform_fee_amount'=>-(float)$orig['platform_fee_amount'],'provider_net_amount'=>0,'charge_status'=>ChargeCodes::CHARGE_REFUNDED,'payable_status'=>ChargeCodes::PAYABLE_REVERSED,'calculation_version'=>'reversal-v1','calculation_context'=>['original_charge_item_id'=>(int)$orig['id'],'motivo'=>$motivo,'admin_id'=>$adminId],'idempotency_key'=>'referral_fee_reversal:'.$id]);PedidoIndicacaoOficina::atualizar($id,['status'=>PedidoIndicacaoOficina::ESTORNADA]);AuditTrailService::evento('indicacao_oficina_estornada',__CLASS__,__FUNCTION__,['indicacao_id'=>$id,'motivo'=>$motivo,'admin_id'=>$adminId]);return $rev;
    }
    private static function settings(int $providerId): ?array{$s=getPDO()->prepare('SELECT * FROM provider_workshop_settings WHERE provider_id=? LIMIT 1');$s->execute([$providerId]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
}
