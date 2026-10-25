<?php
declare(strict_types=1);
/** Controller de consulta, despachado pelo index.php original na copia QA. */
final class GFInvoiceApplicationController extends BaseController
{
    public function index():void {$this->show(null);}
    public function detail(int $id):void {$this->show($id);}
    private function show(?int $id):void
    {
        $user=AuthService::requireAuth('admin',false);
        $config=$GLOBALS['gf_as_http_config']??null;
        if(!is_array($config) || !defined('GF_AS_HTTP_RUNTIME'))throw new LogicException('AS_RUNTIME_REQUIRED');
        foreach($_GET as $key=>$value)if(!in_array($key,['parceiro_id','referencia','formato'],true) || !is_string($value)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $reference=$_GET['referencia']??$config['reference'];$format=$_GET['formato']??'html';$partner=$_GET['parceiro_id']??'';
        $at=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$reference,new DateTimeZone('America/Sao_Paulo'));
        if(!$at || $at->format('Y-m-d H:i:s')!==$reference || !in_array($format,['html','json'],true) || ($partner!=='' && (!preg_match('/^[1-9][0-9]{0,9}$/D',$partner) || (int)$partner>2147483647))){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        session_write_close();$operation=bin2hex(random_bytes(8));$prefix=$config['run'].'/app_'.$operation;
        $job=['mode'=>'admin_report','parent'=>$config['parent'],'actor'=>(int)$user['id'],'partner'=>$partner===''?null:(int)$partner,'reference'=>$reference,'format'=>'json','result'=>$prefix.'.result.json'];
        apWrite($prefix.'.job.json',$job);apJoin(apLaunch($config['package'].'/worker.php',['--job='.$prefix.'.job.json'],$prefix),20);$result=apJson($job['result']);
        if(!$result['ok']){$this->reply((int)$result['http'],['ok'=>false,'erro'=>$result['erro'],'operation'=>$operation]);return;}
        $report=$result['report'];$invoice=null;
        if($id!==null){foreach($report['invoices'] as $row)if($row['invoice_id']===$id){$invoice=$row;break;}if($invoice===null){$this->reply(404,['ok'=>false,'erro'=>'fatura_nao_encontrada']);return;}}
        if($format==='json'){$this->reply(200,$id===null?['ok'=>true,'report'=>$report]:['ok'=>true,'invoice'=>$invoice]);return;}
        header('Content-Type: text/html; charset=UTF-8');require __DIR__.'/../Views/admin/fin3_faturas.php';
    }
    private function reply(int $status,array $body):void
    {
        http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    }
}
