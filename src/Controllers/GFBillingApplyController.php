<?php
declare(strict_types=1);
final class GFBillingApplyController extends BaseController
{
    public function form():void
    {
        $user=AuthService::requireAuth('admin',false);$raw=$_GET['fatura_id']??'';
        if(count($_GET)!==1 || !is_string($raw) || !preg_match('/^[1-9][0-9]{0,9}$/D',$raw) || (int)$raw>2147483647){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $csrf=AuthService::gerarCsrfToken();$key=bin2hex(random_bytes(16));session_write_close();$r=$this->work(['mode'=>'block_read','actor'=>(int)$user['id'],'invoice'=>(int)$raw]);
        if(!$r['ok']){$this->reply((int)$r['http'],['ok'=>false,'erro'=>$r['erro']]);return;}
        header('Content-Type: text/html; charset=UTF-8');$e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bloqueio por fatura QA</title><main><a href="/admin/cobranca">Voltar ao planejamento</a><h1>Conferir bloqueio por fatura</h1><p>Fatura '.$e($r['invoice_id']).' · '.$e($r['status']).' · R$ '.$e($r['amount']).'</p><p>Vencimento '.$e($r['due_at']).' · Referencia do servidor '.$e($r['server_reference']).'</p><p>Esta acao altera o estado da fatura e registra auditoria. Nao envia cobranca ou notificacao.</p>';
        if($r['eligible'])echo '<form method="post" action="/admin/cobranca/bloqueio"><input type="hidden" name="csrf_token" value="'.$e($csrf).'"><input type="hidden" name="request_key" value="'.$e($key).'"><input type="hidden" name="revision" value="'.$e($r['revision']).'"><input type="hidden" name="fatura_id" value="'.$e($r['invoice_id']).'"><label for="motivo">Motivo da aplicacao</label><textarea id="motivo" name="motivo" minlength="20" maxlength="500" required></textarea><button>Aplicar bloqueio nesta fatura QA</button></form>';
        else echo '<p>Nao ha bloqueio disponivel para aplicar neste estado e configuracao.</p>';echo '</main></html>';
    }
    public function apply():void
    {
        $user=AuthService::requireAuth('admin',false);
        if($_GET){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        if(!AuthService::validarCsrfToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')){$this->reply(403,['ok'=>false,'erro'=>'csrf_invalido']);return;}
        foreach($_POST as $k=>$v)if(!in_array($k,['csrf_token','fatura_id','request_key','revision','motivo'],true) || !is_string($v)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $id=$_POST['fatura_id']??'';$key=$_POST['request_key']??'';$revision=$_POST['revision']??'';
        if(!preg_match('/^[1-9][0-9]{0,9}$/D',$id) || (int)$id>2147483647 || !preg_match('/^[a-f0-9]{32}$/D',$key) || !preg_match('/^[a-f0-9]{64}$/D',$revision)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        session_write_close();$r=$this->work(['mode'=>'block_apply','actor'=>(int)$user['id'],'invoice'=>(int)$id,'key'=>$key,'revision'=>$revision,'reason'=>$_POST['motivo']??'']);$http=(int)$r['http'];unset($r['http'],$r['diagnostic']);$this->reply($http,$r);
    }
    private function work(array $job):array
    {
        $c=$GLOBALS['gf_as_http_config']??null;if(!is_array($c) || !defined('GF_AS_HTTP_RUNTIME'))throw new LogicException('AZ_RUNTIME_REQUIRED');
        $prefix=$c['run'].'/block_'.bin2hex(random_bytes(8));$job+=['parent'=>$c['parent'],'result'=>$prefix.'.result.json'];apWrite($prefix.'.job.json',$job);apJoin(apLaunch($c['package'].'/worker.php',['--job='.$prefix.'.job.json'],$prefix),30);return apJson($job['result']);
    }
    private function reply(int $http,array $data):void
    {
        http_response_code($http);if(AuthService::isJsonRequest()){header('Content-Type: application/json; charset=UTF-8');echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return;}
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Aplicacao de bloqueio QA</title><main><h1>'.(!empty($data['ok'])?'Acao conferida':'Acao nao aplicada').'</h1>';
        if(!empty($data['ok']))echo '<p>Fatura '.$e($data['invoice_id']).' · Estado atual '.$e($data['current_status']).'.</p><p>Nenhuma notificacao ou cobranca foi enviada.</p>';
        else echo '<p>Confira o estado, o vencimento, a configuracao e os registros da operacao antes de continuar.</p>';
        echo '<a href="/admin/cobranca">Voltar ao planejamento</a></main></html>';
    }
}
