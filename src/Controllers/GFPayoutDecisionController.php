<?php
declare(strict_types=1);
final class GFPayoutDecisionController extends BaseController
{
    private function work(array $job):array
    {
        require_once __DIR__ . '/../Services/Finance/GFPayoutHttpExecutor.php';
        return GFPayoutHttpExecutor::run($job);
    }
    public function listing():void {$this->page(false);}
    public function form():void {$this->page(true);}
    private function page(bool $single):void
    {
        $user=AuthService::requireAuth('gerente',false);$job=['mode'=>'decision_read','actor'=>(int)$user['id']];
        if($single){$id=$_GET['demanda_id']??'';if(count($_GET)!==1 || !is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D',$id) || (int)$id>2147483647){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}$job['case']=(int)$id;}
        elseif($_GET){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $csrf=AuthService::gerarCsrfToken();$key=bin2hex(random_bytes(16));session_write_close();$r=$this->work($job);
        if(!$r['ok']){$this->reply((int)$r['http'],['ok'=>false,'erro'=>$r['erro']]);return;}
        header('Content-Type: text/html; charset=UTF-8');require __DIR__.'/../Views/admin/fin3_decisao_repasse.php';
    }
    public function decide():void
    {
        $user=AuthService::requireAuth('gerente',false);
        if(!AuthService::validarCsrfToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')){$this->reply(403,['ok'=>false,'erro'=>'csrf_invalido']);return;}
        foreach($_POST as $k=>$v)if(!in_array($k,['csrf_token','demanda_id','request_key','revision','veredito','nota'],true) || !is_string($v)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $id=$_POST['demanda_id']??'';$key=$_POST['request_key']??'';$revision=$_POST['revision']??'';$verdict=$_POST['veredito']??'';$note=$_POST['nota']??'';
        if(!preg_match('/^[1-9][0-9]{0,9}$/D',$id) || (int)$id>2147483647 || !preg_match('/^[a-f0-9]{32}$/D',$key) || !preg_match('/^[a-f0-9]{64}$/D',$revision) || !in_array($verdict,['aprovar','rejeitar'],true)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        session_write_close();$r=$this->work(['mode'=>'decision_apply','actor'=>(int)$user['id'],'case'=>(int)$id,'key'=>$key,'revision'=>$revision,'verdict'=>$verdict,'note'=>$note]);$http=(int)$r['http'];unset($r['http'],$r['diagnostic']);$this->reply($http,$r);
    }
    private function reply(int $http,array $r):void
    {
        http_response_code($http);if(AuthService::isJsonRequest()){header('Content-Type: application/json; charset=UTF-8');echo json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return;}
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');header('Content-Type: text/html; charset=UTF-8');
        $messages=['resultado_indeterminado_consulte'=>'Nao foi possivel confirmar a resposta. Consulte o estado atual antes de repetir a mesma solicitacao.','csrf_invalido'=>'Atualize a pagina e tente novamente.','solicitante_nao_pode_decidir'=>'Outro responsavel precisa analisar a sua solicitacao.','segundo_responsavel_deve_ser_distinto'=>'A segunda decisao exige outro responsavel.','demanda_alterada_atualize_a_pagina'=>'A demanda mudou. Atualize a pagina antes de decidir.','contexto_financeiro_divergente'=>'O repasse mudou desde a triagem. Confira o resultado antes de aprovar.','nota_invalida'=>'Confira o tamanho da nota da decisao.','politica_de_aprovacao_alterada'=>'A regra de aprovacao mudou. A demanda precisa de nova conferencia.'];
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Decisao de demanda</title><main><h1>'.(!empty($r['ok'])?'Decisao registrada':'Decisao nao registrada').'</h1>';
        if(!empty($r['ok']))echo '<p>Demanda '.$e($r['id']).' · Estado atual: '.$e($r['current_status']).'.</p><p>A decisao nao executa transferencia. A recuperacao financeira sera conferida em etapa propria.</p>';
        else echo '<p>'.$e($messages[$r['erro']??'']??'Confira a demanda e tente novamente. Se persistir, consulte o registro da operacao.').'</p>';
        echo '<a href="/admin/repasses/demandas">Voltar as demandas</a></main></html>';
    }
}
