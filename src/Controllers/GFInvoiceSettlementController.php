<?php
declare(strict_types=1);
final class GFInvoiceSettlementController extends BaseController
{
    private static function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    private static function start(string $title):void
    {
        header('Content-Type: text/html; charset=UTF-8');echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.self::e($title).'</title><style>body{font:16px/1.6 system-ui,sans-serif;background:#f4f6f9;color:#172638;margin:0}main{max-width:760px;margin:40px auto;padding:28px;background:white;border:1px solid #dbe1e8;border-radius:12px}h1{line-height:1.2}label{display:block;margin-top:18px;font-weight:600}input,textarea,button{box-sizing:border-box;font:inherit;padding:12px;border-radius:6px;border:1px solid #8191a4}input:not([type=hidden]),textarea{width:100%}button{margin-top:20px;background:#164b85;color:white;cursor:pointer}a{color:#164b85}.notice{padding:14px;background:#edf3fa;border-left:4px solid #164b85}@media(max-width:600px){main{margin:12px;padding:18px}}</style></head><body><main><a href="/admin/cobranca">Planejamento de cobranca</a><h1>'.self::e($title).'</h1>';
    }
    public function form():void
    {
        $user=AuthService::requireAuth('admin',false);
        if(!$_GET){session_write_close();self::start('Conferir baixa de fatura');echo '<p class="notice">Ambiente de testes: comprovantes simulados, sem movimentacao bancaria real.</p><form method="get" action="/admin/cobranca/baixa"><label for="fatura_id">Numero da fatura</label><input id="fatura_id" name="fatura_id" inputmode="numeric" pattern="[1-9][0-9]{0,9}" required><button>Consultar fatura</button></form></main></body></html>';return;}
        $id=$_GET['fatura_id']??'';if(count($_GET)!==1 || !is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D',$id) || (int)$id>2147483647){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $csrf=AuthService::gerarCsrfToken();$key=bin2hex(random_bytes(16));session_write_close();$r=$this->work(['mode'=>'settlement_access_read','actor'=>(int)$user['id'],'invoice'=>(int)$id]);if(!$r['ok']){$this->reply((int)$r['http'],['ok'=>false,'erro'=>$r['erro']]);return;}
        self::start('Conferir baixa de fatura');echo '<p class="notice">Ambiente de testes: comprovantes simulados, sem movimentacao bancaria real.</p><p>Fatura <strong>'.self::e($r['invoice_id']).'</strong> · Estado <strong>'.self::e($r['status']).'</strong></p><p>Valor a conferir: <strong>R$ '.self::e($r['amount']).'</strong><br>Vencimento: '.self::e($r['due_at']).'</p>';
        if($r['settlement_available'])echo '<p>Informe o identificador do pagamento. O servidor consultara e validara o comprovante antes de registrar a baixa. Outras dividas bloqueadas continuam impedindo a liberacao do parceiro.</p><form method="post" action="/admin/cobranca/baixa"><input type="hidden" name="csrf_token" value="'.self::e($csrf).'"><input type="hidden" name="request_key" value="'.self::e($key).'"><input type="hidden" name="fatura_id" value="'.self::e($id).'"><label for="payment_id">Identificador do pagamento QA</label><input id="payment_id" name="payment_id" inputmode="numeric" pattern="[0-9]{1,32}" maxlength="32" required><label for="motivo">Motivo da conferencia</label><textarea id="motivo" name="motivo" minlength="20" maxlength="500" rows="4" required></textarea><button>Validar comprovante e registrar baixa QA</button></form>';
        else echo '<p>Esta fatura nao possui baixa disponivel neste estado.</p>';echo '</main></body></html>';
    }
    public function apply():void
    {
        $user=AuthService::requireAuth('admin',false);if($_GET){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        if(!AuthService::validarCsrfToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')){$this->reply(403,['ok'=>false,'erro'=>'csrf_invalido']);return;}
        foreach($_POST as $k=>$v)if(!in_array($k,['csrf_token','request_key','fatura_id','payment_id','motivo'],true) || !is_string($v)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $id=$_POST['fatura_id']??'';$key=$_POST['request_key']??'';$payment=$_POST['payment_id']??'';
        if(!preg_match('/^[1-9][0-9]{0,9}$/D',$id) || (int)$id>2147483647 || !preg_match('/^[a-f0-9]{32}$/D',$key) || !preg_match('/^[0-9]{1,32}$/D',$payment)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        session_write_close();$r=$this->work(['mode'=>'settlement_access_apply','actor'=>(int)$user['id'],'invoice'=>(int)$id,'key'=>$key,'payment_id'=>$payment,'reason'=>$_POST['motivo']??'']);$http=(int)$r['http'];unset($r['http'],$r['diagnostic']);$this->reply($http,$r);
    }
    private function work(array $job):array
    {
        $phase='DISPATCH';$operation=bin2hex(random_bytes(8));try{
            $c=$GLOBALS['gf_as_http_config']??null;if(!is_array($c) || !defined('GF_AS_HTTP_RUNTIME'))throw new LogicException('BB_ISOLATED_RUNTIME_REQUIRED');
            if($job['mode']==='settlement_access_apply'){if(!isset($c['settlement_lookup_file'],$c['settlement_lookup_sha256']))throw new LogicException('BB_PRIVATE_LOOKUP_NOT_CONFIGURED');$job+=['lookup_file'=>$c['settlement_lookup_file'],'lookup_sha256'=>$c['settlement_lookup_sha256']];}
            $prefix=$c['run'].'/settlement_http_'.$operation;$job+=['parent'=>$c['parent'],'result'=>$prefix.'.result.json'];apWrite($prefix.'.job.json',$job);$phase='CHILD_WORKER';apJoin(apLaunch($c['package'].'/worker.php',['--job='.$prefix.'.job.json'],$prefix),30);return apJson($job['result']);
        }catch(Throwable $e){error_log(json_encode(['project'=>'GuinchaFacil','class'=>self::class,'function'=>'work','component'=>'settlement_interface','phase'=>$phase,'status'=>'FAIL','operation'=>$operation,'error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'remaining_percent'=>40],JSON_THROW_ON_ERROR));return ['ok'=>false,'http'=>500,'erro'=>'baixa_indisponivel','operation'=>$operation];}
    }
    private function reply(int $http,array $data):void
    {
        http_response_code($http);if(AuthService::isJsonRequest()){header('Content-Type: application/json; charset=UTF-8');echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return;}
        self::start(!empty($data['ok'])?'Baixa conferida':'Baixa nao registrada');if(!empty($data['ok'])){echo '<p>Fatura '.self::e($data['invoice_id']).' · Estado atual '.self::e($data['current_status']).'.</p>';if($data['partner_currently_blocked'])echo '<p data-partner-blocked="true">O parceiro continua bloqueado por outras faturas.</p>';else echo '<p data-partner-blocked="false">O parceiro esta sem bloqueio financeiro ativo neste momento.</p>';echo '<p>Conferencia realizada em QA. Nenhuma movimentacao bancaria ou notificacao foi enviada.</p>';}
        else echo '<p>Confira a fatura e o identificador do pagamento. A baixa exige comprovante validado e valor exato.</p>';echo '<a href="/admin/cobranca/baixa">Consultar outra fatura</a></main></body></html>';
    }
}
