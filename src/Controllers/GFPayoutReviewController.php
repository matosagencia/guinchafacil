<?php
declare(strict_types=1);
final class GFPayoutReviewController extends BaseController
{
    private function work(array $job):array
    {
        require_once __DIR__ . '/../Services/Finance/GFPayoutHttpExecutor.php';
        return GFPayoutHttpExecutor::run($job);
    }
    public function form():void
    {
        $user=AuthService::requireAuth('admin',false);$raw=$_GET['job_id']??'';
        if(count($_GET)!==1 || !is_string($raw) || !preg_match('/^[1-9][0-9]{0,9}$/D',$raw) || (int)$raw>2147483647){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $csrf=AuthService::gerarCsrfToken();$key=bin2hex(random_bytes(16));session_write_close();$r=$this->work(['mode'=>'review_inspect','actor'=>(int)$user['id'],'job'=>(int)$raw]);
        if(!$r['ok']){$this->reply((int)$r['http'],['ok'=>false,'erro'=>$r['erro']]);return;}
        header('Content-Type: text/html; charset=UTF-8');require __DIR__.'/../Views/admin/fin3_triagem_repasse.php';
    }
    public function create():void
    {
        $user=AuthService::requireAuth('admin',false);
        if(!AuthService::validarCsrfToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')){$this->reply(403,['ok'=>false,'erro'=>'csrf_invalido']);return;}
        foreach($_POST as $k=>$v)if(!in_array($k,['csrf_token','job_id','request_key','justificativa'],true) || !is_string($v)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $job=$_POST['job_id']??'';$key=$_POST['request_key']??'';$reason=$_POST['justificativa']??'';
        if(!preg_match('/^[1-9][0-9]{0,9}$/D',$job) || (int)$job>2147483647 || !preg_match('/^[a-f0-9]{32}$/D',$key)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        session_write_close();$r=$this->work(['mode'=>'review_create','actor'=>(int)$user['id'],'job'=>(int)$job,'request_key'=>$key,'reason'=>$reason]);
        $http=(int)$r['http'];unset($r['diagnostic'],$r['http']);$this->reply($http,$r);
    }
    private function reply(int $http,array $data):void
    {
        http_response_code($http);
        if(AuthService::isJsonRequest()){header('Content-Type: application/json; charset=UTF-8');echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return;}
        header('Content-Type: text/html; charset=UTF-8');$e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $messages=['resultado_indeterminado_consulte'=>'Nao foi possivel confirmar a resposta. Consulte o estado atual antes de repetir a mesma solicitacao.','csrf_invalido'=>'Atualize a pagina e tente novamente.','triagem_ja_aberta'=>'Ja existe uma demanda em analise para este repasse.','idempotencia_divergente'=>'A solicitacao diverge do registro anterior. Confira os dados antes de continuar.','justificativa_invalida'=>'Confira o tamanho da justificativa.','acesso_negado'=>'Seu perfil nao permite esta operacao.'];
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Triagem de repasse</title><main><h1>'.($http<400?'Demanda registrada':'Nao foi possivel concluir a solicitacao').'</h1>';
        if(!empty($data['ok']))echo '<p>Demanda '.$e($data['id']).' pendente de analise. Valor R$ '.$e($data['amount']).'. Nenhuma transferencia foi executada por esta solicitacao.</p>';
        else echo '<p>'.$e($messages[$data['erro']??'']??'Confira os dados e tente novamente. Se a falha persistir, consulte o registro da operacao.').'</p>';
        echo '<a href="/admin/saques">Voltar aos repasses</a></main></html>';
    }
}
