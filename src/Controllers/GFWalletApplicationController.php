<?php
declare(strict_types=1);
final class GFWalletApplicationController extends BaseController
{
    public function partner():void {$this->show('partner');}
    public function admin():void {$this->show('admin');}
    private function show(string $scope):void
    {
        $user=AuthService::requireAuth($scope==='admin'?'admin':'guincho',false);
        foreach($_GET as $key=>$value)if(!in_array($key,['guincho_id','formato'],true) || !is_string($value)){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        $raw=$_GET['guincho_id']??'';$format=$_GET['formato']??'html';
        if(!in_array($format,['json','html'],true) || ($raw!=='' && (!preg_match('/^[1-9][0-9]{0,9}$/D',$raw) || (int)$raw>2147483647))){$this->reply(422,['ok'=>false,'erro'=>'parametro_invalido']);return;}
        session_write_close();$operation=bin2hex(random_bytes(8));
        require_once __DIR__ . '/../Services/Finance/GFPayoutHttpExecutor.php';
        $r=GFPayoutHttpExecutor::run(['mode'=>'wallet_report','scope'=>$scope,'actor'=>(int)$user['id'],'partner'=>$raw===''?null:(int)$raw]);
        if(!$r['ok']){$this->reply((int)$r['http'],['ok'=>false,'erro'=>$r['erro'],'operation'=>$operation]);return;}
        $report=$r['report'];if($format==='json'){$this->reply(200,['ok'=>true,'report'=>$report]);return;}
        header('Content-Type: text/html; charset=UTF-8');require __DIR__.'/../Views/admin/fin3_carteira.php';
    }
    private function reply(int $status,array $body):void {http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
}
