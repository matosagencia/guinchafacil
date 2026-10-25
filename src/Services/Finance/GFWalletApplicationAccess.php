<?php
declare(strict_types=1);
final class GFWalletApplicationAccess
{
    public static function report(PDO $db,int $actor,string $scope,?int $partner=null):array
    {
        $owned=false;$phase='GUARD';
        try {
            GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_WALLET_HTTP_OWNS_TRANSACTION');
            $db->exec('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');$owned=true;$phase='AUTHORIZE';
            if($db->query('SELECT @@tx_isolation')->fetchColumn()!=='REPEATABLE-READ')throw new LogicException('FIN_WALLET_HTTP_REPEATABLE_READ');
            $q=$db->prepare('SELECT tipo,ativo FROM usuarios WHERE id=?');$q->execute([$actor]);$user=$q->fetch(PDO::FETCH_ASSOC);
            if(!$user || (int)$user['ativo']!==1 || !in_array($scope,['partner','admin'],true) || ($scope==='admin' && $user['tipo']!=='admin') || ($scope==='partner' && $user['tipo']!=='guincho'))throw new DomainException('FIN_WALLET_HTTP_FORBIDDEN');
            if($scope==='partner'){
                $q=$db->prepare('SELECT id FROM guinchos WHERE usuario_id=?');$q->execute([$actor]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
                if(count($ids)!==1 || ($partner!==null && $partner!==(int)$ids[0]))throw new DomainException('FIN_WALLET_HTTP_FORBIDDEN');$partner=(int)$ids[0];
            }
            $phase='PROJECT';$wallet=null;
            if($scope==='partner'){$wallet=CarteiraService::saldoGuincho($partner);$r=CarteiraService::extratoGuincho($partner,1000);}
            else $r=CarteiraService::repassesPendentesOuComFalha(1000);
            if(!$r['ok'] || ($wallet!==null && !$wallet['ok']))throw new DomainException('FIN_WALLET_HTTP_PROJECTION_FAILED');
            $rows=[];
            foreach($r['linhas'] as $row){
                if($partner!==null && (int)$row['guincho_id']!==$partner)continue;
                $reserved=0;$q=$db->prepare("SELECT payload_json FROM payment_jobs WHERE pagamento_id=? AND job_type='pix_payout' AND status IN ('queued','retry','running')");$q->execute([$row['pagamento_id']]);
                foreach($q->fetchAll(PDO::FETCH_COLUMN) as $raw){$job=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(($job['version']??null)!=='R11AL' || ($job['pagamento_id']??null)!==(int)$row['pagamento_id'] || ($job['guincho_id']??null)!==(int)$row['guincho_id'])throw new DomainException('FIN_WALLET_HTTP_RESERVATION_DRIFT');$amount=GFExactMoney::cents(GFMoneyInput::decimal($job['amount']??null));if($amount>GFExactMoney::MAX_CENTS-$reserved)throw new OverflowException('FIN_WALLET_HTTP_RESERVATION_OVERFLOW');$reserved+=$amount;}
                if($reserved>(int)$row['remaining'])throw new DomainException('FIN_WALLET_HTTP_OVER_RESERVED');
                $rows[]=['order_id'=>(int)$row['pedido_id'],'payment_id'=>(int)$row['pagamento_id'],'partner_id'=>(int)$row['guincho_id'],'order_status'=>$row['pedido_status'],'payout_status'=>$row['status_pix']??'pendente','paid'=>$row['valor_repassado'],'pending'=>$row['valor_pendente'],'refunded'=>$row['valor_estornado'],'reserved'=>GFExactMoney::decimal($reserved),'unreserved'=>GFExactMoney::decimal((int)$row['remaining']-$reserved)];
            }
            if($scope==='admin')foreach($rows as &$row){$row['review_jobs']=GFReconciliation::rows($db,"SELECT j.id,j.status,d.state AS dispatch_state FROM payment_jobs j LEFT JOIN fin3_payout_dispatch_qa d ON d.job_id=j.id WHERE j.pagamento_id=? AND j.job_type='pix_payout' AND j.status<>'completed' AND (j.status IN ('retry','failed') OR d.state IN ('review','rejected','uncertain')) ORDER BY j.id",[$row['payment_id']]);}unset($row);
            $public=null;
            if($wallet!==null){$public=[];foreach(['saldo_em_compensacao','saldo_pago','saldo_estornado','saldo_disponivel','total_pedidos','repasses_com_falha'] as $key)$public[$key]=$wallet[$key];}
            $report=['version'=>'R11AU','scope'=>$scope,'partner_id'=>$partner,'wallet'=>$public,'rows'=>$rows,'limit'=>1000,'possibly_truncated'=>count($r['linhas'])===1000,'limits'=>['automatic_payout'=>true,'manual_withdrawal'=>false,'gateway_real'=>false,'available_semantics'=>'saldo_disponivel is ledger projection; unreserved is per row after active reservations']];
            $report['evidence_sha256']=hash('sha256',json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'report'=>$report];
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return ['ok'=>false,'http'=>$e->getMessage()==='FIN_WALLET_HTTP_FORBIDDEN'?403:500,'erro'=>$e->getMessage()==='FIN_WALLET_HTTP_FORBIDDEN'?'acesso_negado':'consulta_indisponivel','diagnostic'=>['project'=>'GuinchaFacil','class'=>self::class,'function'=>'report','component'=>'wallet_http','phase'=>$phase,'error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null]];}
    }
}
