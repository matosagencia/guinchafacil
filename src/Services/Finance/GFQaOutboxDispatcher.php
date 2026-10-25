<?php
declare(strict_types=1);
require_once __DIR__.'/GFReconciliation.php';
interface GFQaOutboxSink
{
    public function identity():string;
    public function deliver(string $key,string $fingerprint,array $message):array;
    public function lookup(string $key):?array;
}
final class GFQaOutboxDispatcher
{
    public static function key(array $outbox):string {return hash('sha256','outbox-v1|'.$outbox['event_key'].'|'.$outbox['event_type']);}
    public static function fingerprint(array $outbox):string {return hash('sha256',json_encode([$outbox['event_key'],$outbox['event_type'],$outbox['payload_json']],JSON_THROW_ON_ERROR));}
    public static function validateAck(array $ack,string $key,string $fingerprint):void
    {
        if(($ack['key']??null)!==$key || ($ack['fingerprint']??null)!==$fingerprint || ($ack['status']??null)!=='stored' || ($ack['id']??null)!=='qa_outbox_'.hash('sha256',$key.'|'.$fingerprint))throw new DomainException('FIN_OUTBOX_ACK_MISMATCH');
    }
    private static function context(PDO $db,int $id):array
    {
        $r=GFReconciliation::rows($db,'SELECT a.pedido_id,a.pagamento_id FROM fin3_post_commit_outbox o JOIN fin3_operational_approval a ON a.event_key=o.event_key WHERE o.id=?',[$id]);
        if(count($r)!==1)throw new DomainException('FIN_OUTBOX_SOURCE_MISSING');
        GFReconciliation::rows($db,'SELECT id FROM pedidos WHERE id=?',[$r[0]['pedido_id']],true);
        $v=GFReconciliation::online($db,(int)$r[0]['pagamento_id'],true);
        if((int)$v['outbox']['id']!==$id || $v['outbox']['event_type']!=='payment_approved')throw new DomainException('FIN_OUTBOX_EVENT_NOT_MIGRATED');return $v['outbox'];
    }
    private static function log(string $phase,string $status,array $context):void
    {
        $r=['project'=>'GuinchaFacil','class'=>self::class,'function'=>'process','component'=>'outbox_delivery','phase'=>$phase,'status'=>$status,'message'=>$context['error']??$phase,'context'=>$context];
        if(isset($GLOBALS['gf_fin_log_sink']))($GLOBALS['gf_fin_log_sink'])($r);else error_log(json_encode($r,JSON_THROW_ON_ERROR));
    }
    public static function process(PDO $db,int $id,GFQaOutboxSink $sink,?callable $hook=null):array
    {
        $owned=false;$token=null;$phase='GUARD';
        try {
            GFReconciliation::guard($db);
            if(!defined('GF_FIN_OUTBOX_QA') || GF_FIN_OUTBOX_QA!==true)throw new LogicException('FIN_OUTBOX_QA_REQUIRED');
            if($db->inTransaction())throw new LogicException('FIN_OUTBOX_OWNS_TRANSACTION_REQUIRED');
            $sinkId=$sink->identity();if(!preg_match('/^qa_sink_[a-f0-9]{64}$/D',$sinkId))throw new DomainException('FIN_OUTBOX_SINK_IDENTITY_INVALID');
            $phase='CLAIM';$db->beginTransaction();$owned=true;$out=self::context($db,$id);$key=self::key($out);$fp=self::fingerprint($out);
            $rows=GFReconciliation::rows($db,'SELECT *, (lease_until>NOW(6)) AS active FROM fin3_outbox_delivery_qa WHERE outbox_id=?',[$id],true);$d=$rows[0]??null;
            if($d && ($d['fingerprint']!==$fp || $d['event_key']!==$out['event_key'] || $d['sink_id']!==$sinkId))throw new DomainException('FIN_OUTBOX_FROZEN_SOURCE_DRIFT');
            if($out['status']==='done') {
                if(!$d || $d['state']!=='confirmed' || $d['claim_token']!==null || $d['lease_until']!==null)throw new DomainException('FIN_OUTBOX_DONE_WITHOUT_PROOF');
                $stored=json_decode($d['ack_json'],true,512,JSON_THROW_ON_ERROR);self::validateAck($stored,$key,$fp);$db->commit();$owned=false;
                $ack=$sink->lookup($key);if($ack===null)throw new DomainException('FIN_OUTBOX_SINK_RECEIPT_MISSING');self::validateAck($ack,$key,$fp);
                if($ack!==$stored)throw new DomainException('FIN_OUTBOX_SINK_RECEIPT_DRIFT');return ['ok'=>true,'processed'=>false,'duplicate_verified'=>true,'status'=>'confirmed'];
            }
            if($d && $d['state']==='review'){$db->commit();$owned=false;return ['ok'=>false,'processed'=>false,'erro'=>'FIN_OUTBOX_ADMIN_REVIEW_REQUIRED'];}
            if($d && $d['claim_token']!==null && (int)$d['active']===1){$db->commit();$owned=false;return ['ok'=>true,'processed'=>false,'status'=>'busy'];}
            if($out['status']!=='pending')throw new DomainException('FIN_OUTBOX_STATE_NOT_PENDING');
            if($d && (int)$d['claim_count']>=5){
                $db->prepare("UPDATE fin3_outbox_delivery_qa SET state='review',claim_token=NULL,lease_until=NULL,last_error='FIN_OUTBOX_RECOVERY_LIMIT_REVIEW_REQUIRED',updated_at=NOW(6) WHERE outbox_id=?")->execute([$id]);
                $db->commit();$owned=false;return ['ok'=>false,'processed'=>false,'erro'=>'FIN_OUTBOX_RECOVERY_LIMIT_REVIEW_REQUIRED'];
            }
            $token=bin2hex(random_bytes(16));
            if(!$d)$db->prepare("INSERT INTO fin3_outbox_delivery_qa(outbox_id,event_key,fingerprint,sink_id,state,claim_token,lease_until,claim_count) VALUES (?,?,?,?,'sending',?,DATE_ADD(NOW(6),INTERVAL 90 SECOND),1)")->execute([$id,$out['event_key'],$fp,$sinkId,$token]);
            else $db->prepare("UPDATE fin3_outbox_delivery_qa SET state='sending',claim_token=?,lease_until=DATE_ADD(NOW(6),INTERVAL 90 SECOND),claim_count=claim_count+1,updated_at=NOW(6) WHERE outbox_id=?")->execute([$token,$id]);
            $db->commit();$owned=false;if($hook)$hook('after_claim');
            $phase='DELIVERY';$ack=$sink->lookup($key);
            if($ack===null)$ack=$sink->deliver($key,$fp,['event_type'=>$out['event_type'],'event_key'=>$out['event_key'],'payload'=>json_decode($out['payload_json'],true,512,JSON_THROW_ON_ERROR)]);
            if($hook)$hook('after_sink');self::validateAck($ack,$key,$fp);if($hook)$hook('before_finalize');
            $phase='FINALIZE';$db->beginTransaction();$owned=true;$current=self::context($db,$id);
            $d=GFReconciliation::rows($db,'SELECT *, (lease_until>NOW(6)) AS active FROM fin3_outbox_delivery_qa WHERE outbox_id=?',[$id],true)[0]??null;
            if(!$d || $d['claim_token']!==$token || !(int)$d['active'])throw new DomainException('FIN_OUTBOX_CLAIM_LOST');
            if($d['fingerprint']!==self::fingerprint($current) || $fp!==$d['fingerprint'])throw new DomainException('FIN_OUTBOX_FROZEN_SOURCE_DRIFT');
            $db->prepare("UPDATE fin3_post_commit_outbox SET status='done' WHERE id=?")->execute([$id]);
            if($hook)$hook('after_done');
            $q=$db->prepare("UPDATE fin3_outbox_delivery_qa SET state='confirmed',claim_token=NULL,lease_until=NULL,ack_json=?,last_error=NULL,updated_at=NOW(6) WHERE outbox_id=? AND claim_token=?");$q->execute([json_encode($ack,JSON_THROW_ON_ERROR),$id,$token]);if($q->rowCount()!==1)throw new DomainException('FIN_OUTBOX_CLAIM_LOST');
            $db->commit();$owned=false;self::log('FINALIZE_COMMIT','PASS',['outbox_id'=>$id]);return ['ok'=>true,'processed'=>true,'status'=>'confirmed','duplicate_verified'=>false];
        } catch(Throwable $e) {
            if($owned && $db->inTransaction())$db->rollBack();$secondary=null;
            if($token!==null) {
                try {
                    $q=$db->prepare("UPDATE fin3_outbox_delivery_qa SET state=?,claim_token=NULL,lease_until=NULL,last_error=?,updated_at=NOW(6) WHERE outbox_id=? AND claim_token=?");$q->execute([$e instanceof DomainException?'review':'uncertain',$e->getMessage(),$id,$token]);
                    // A fonte permanece pending; o journal sinaliza revisao sem fingir entrega.
                } catch(Throwable $writeError){$secondary=$writeError->getMessage();}
            }
            self::log($phase,'FAIL',['outbox_id'=>$id,'error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'diagnostic_write_error'=>$secondary]);return ['ok'=>false,'processed'=>false,'erro'=>$e->getMessage()];
        }
    }
}
