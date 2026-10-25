<?php
declare(strict_types=1);
/** Entrypoint CLI do backend de consulta HTTP QA; identidade vem da sessao do router. */
final class GFInvoiceAdminAccess
{
    public static function report(PDO $db,int $actor,?int $partner,string $reference):array
    {
        $owned=false;$phase='GUARD';
        try {
            GFDirectBilling::guard($db);
            if($db->inTransaction())throw new LogicException('FIN_ADMIN_READ_OWNS_TRANSACTION_REQUIRED');
            $db->exec('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');$owned=true;$phase='AUTHORIZE';
            $q=$db->prepare('SELECT id,tipo,ativo FROM usuarios WHERE id=?');$q->execute([$actor]);$user=$q->fetch(PDO::FETCH_ASSOC);
            if(!$user || (int)$user['ativo']!==1 || $user['tipo']!=='admin'){
                $db->rollBack();$owned=false;return ['ok'=>false,'http'=>403,'erro'=>'acesso_negado'];
            }
            $phase='SCAN';$report=GFInvoiceReadModel::scan($db,$partner,$reference);$db->commit();$owned=false;
            return ['ok'=>true,'http'=>200,'report'=>$report];
        }catch(Throwable $e){
            if($owned && $db->inTransaction())$db->rollBack();
            return ['ok'=>false,'http'=>500,'erro'=>'consulta_indisponivel','diagnostic'=>['project'=>'GuinchaFacil','class'=>self::class,'function'=>'report','component'=>'invoice_http','phase'=>$phase,'error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null]];
        }
    }
}
