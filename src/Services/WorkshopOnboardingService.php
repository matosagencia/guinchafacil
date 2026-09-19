<?php
declare(strict_types=1);
require_once __DIR__.'/../Models/Provider/Provider.php';
require_once __DIR__.'/../Models/Configuracao.php';
require_once __DIR__.'/AuditTrailService.php';
final class WorkshopOnboardingService
{
 public static function cadastrar(array $dados,int $actorId):array
 {
  $id=Provider::criar(['provider_type'=>Provider::TYPE_WORKSHOP,'legal_name'=>trim((string)($dados['legal_name']??'')),'trade_name'=>trim((string)($dados['trade_name']??'')),'document_type'=>$dados['document_type']??'CNPJ','document_number'=>preg_replace('/\D/','',(string)($dados['document_number']??'')),'payment_recipient_type'=>'WORKSHOP','pix_key'=>trim((string)($dados['pix_key']??''))]);
  $s=getPDO()->prepare('INSERT INTO provider_workshop_settings (provider_id,taxa_indicacao_fixa,regra_versao,status_parceria,raio_checkin_m,address,latitude,longitude,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
  $s->execute([$id,(float)($dados['taxa_indicacao_fixa']??30),'v1','ATIVO',(int)($dados['raio_checkin_m']??150),trim((string)($dados['address']??'')),($dados['latitude']??null),($dados['longitude']??null)]);
  AuditTrailService::evento('oficina_parceira_cadastrada',__CLASS__,__FUNCTION__,['provider_id'=>$id,'actor_id'=>$actorId]);return Provider::buscarPorId($id)??['id'=>$id];
 }
 public static function aprovar(int $providerId,int $adminId):array{$s=getPDO()->prepare("UPDATE providers SET approval_status='APPROVED',active=1,updated_at=NOW() WHERE id=? AND provider_type='WORKSHOP'");$s->execute([$providerId]);AuditTrailService::evento('oficina_parceira_aprovada',__CLASS__,__FUNCTION__,['provider_id'=>$providerId,'admin_id'=>$adminId]);return Provider::buscarPorId($providerId)??[];}
 public static function suspender(int $providerId,int $adminId,string $motivo):array{$s=getPDO()->prepare("UPDATE provider_workshop_settings ws JOIN providers p ON p.id=ws.provider_id SET ws.status_parceria='SUSPENSO',p.active=0,p.updated_at=NOW() WHERE ws.provider_id=? AND p.provider_type='WORKSHOP'");$s->execute([$providerId]);AuditTrailService::evento('oficina_parceira_suspensa',__CLASS__,__FUNCTION__,['provider_id'=>$providerId,'admin_id'=>$adminId,'motivo'=>$motivo]);return Provider::buscarPorId($providerId)??[];}
}