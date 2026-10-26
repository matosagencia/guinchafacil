<?php
declare(strict_types=1);
final class GFPayoutRecoveryController extends BaseController
{
    private function work(array $job):array
    {
        require_once __DIR__ . '/../Services/Finance/GFPayoutHttpExecutor.php';
        return GFPayoutHttpExecutor::run($job);
    }
    public function form():void
    {
        $user=AuthService::requireAuth('admin',false);$raw=$_GET['demanda_id']??'';
        if(count($_GET)!==1 || !is_string($raw) || !preg_match('/^[1-9][0-9]{0,9}$/D',$raw) || (int)$raw>2147483647){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $csrf=AuthService::gerarCsrfToken();$key=bin2hex(random_bytes(16));session_write_close();$r=$this->work(['mode'=>'recovery_read','actor'=>(int)$user['id'],'case'=>(int)$raw]);
        if(!$r['ok']){$this->reply((int)$r['http'],['ok'=>false,'erro'=>$r['erro']]);return;}
        header('Content-Type: text/html; charset=UTF-8');require __DIR__.'/../Views/admin/fin3_recuperacao_repasse.php';
    }
    public function queue():void
    {
        $user=AuthService::requireAuth('admin',false);
        if(!AuthService::validarCsrfToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')){$this->reply(403,['ok'=>false,'erro'=>'csrf_invalido']);return;}
        foreach($_POST as $k=>$v)if(!in_array($k,['csrf_token','demanda_id','request_key','revision'],true) || !is_string($v)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $id=$_POST['demanda_id']??'';$key=$_POST['request_key']??'';$revision=$_POST['revision']??'';
        if(!preg_match('/^[1-9][0-9]{0,9}$/D',$id) || (int)$id>2147483647 || !preg_match('/^[a-f0-9]{32}$/D',$key) || !preg_match('/^[a-f0-9]{64}$/D',$revision)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        session_write_close();$r=$this->work(['mode'=>'recovery_queue','actor'=>(int)$user['id'],'case'=>(int)$id,'key'=>$key,'revision'=>$revision]);$http=(int)$r['http'];unset($r['http'],$r['diagnostic']);$this->reply($http,$r);
    }
    private function reply(int $http,array $data):void
    {
        http_response_code($http);if(AuthService::isJsonRequest()){header('Content-Type: application/json; charset=UTF-8');echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return;}
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');header('Content-Type: text/html; charset=UTF-8');
        $messages=['resultado_indeterminado_consulte'=>'Nao foi possivel confirmar a resposta. Consulte o estado atual antes de repetir a mesma solicitacao.','csrf_invalido'=>'Atualize a pagina e tente novamente.','demanda_sem_aprovacao_final'=>'A demanda precisa concluir as aprovacoes antes de seguir.','aprovador_inativo_ou_sem_permissao'=>'Uma aprovacao precisa ser conferida por responsavel habilitado.','resultado_externo_exige_conciliacao'=>'Ja existe registro de despacho. Confira o resultado antes de qualquer nova tentativa.','ausencia_de_envio_nao_comprovada'=>'A ausencia de envio nao foi comprovada. Encaminhe para conciliacao.','recuperacao_ja_encaminhada'=>'A recuperacao desta demanda ja foi encaminhada. Consulte o resultado existente.','demanda_alterada_atualize_a_pagina'=>'A demanda mudou. Atualize a pagina antes de continuar.'];
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Recuperacao de repasse QA</title><main><h1>'.(!empty($data['ok'])?'Recuperacao conferida':'Recuperacao nao liberada').'</h1>';
        if(!empty($data['ok']))echo '<p>Demanda '.$e($data['id']).' · Job '.$e($data['job_id']).' · '.$e($data['financial_status']).'.</p><p>O encaminhamento nao e comprovante de pagamento. Este ambiente usa somente simulacao QA.</p><a href="/admin/repasses/recuperacao?demanda_id='.$e($data['id']).'">Consultar resultado</a>';
        else echo '<p>'.$e($messages[$data['erro']??'']??'Confira a demanda e os registros da operacao antes de continuar.').'</p>';
        echo '<p><a href="/admin/repasses/demandas">Voltar as demandas</a></p></main></html>';
    }
}
