<?php
declare(strict_types=1);
require_once __DIR__.'/BaseController.php';
require_once __DIR__.'/../Services/IndicacaoOficinaService.php';
require_once __DIR__.'/../Services/WorkshopOnboardingService.php';
require_once __DIR__.'/../Models/Provider/Provider.php';
require_once __DIR__.'/../Models/Pedido.php';
require_once __DIR__.'/../Models/Guincho.php';
require_once __DIR__.'/../Services/Evidence/EvidenceService.php';
require_once __DIR__.'/../Models/PedidoIndicacaoOficina.php';
require_once __DIR__.'/../Models/PedidoBypassCase.php';
require_once __DIR__.'/../Services/AuditTrailService.php';

final class OficinaParceriaController extends BaseController
{
 private function json(array $data,int $status=200):void{if(!headers_sent())header('Content-Type: application/json; charset=utf-8');http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
 private function csrf():void{if(!AuthService::validarCsrfToken($_POST['csrf_token']??''))$this->json(['ok'=>false,'erro'=>'CSRF inválido.'],403);}
 public function clienteOficinas():void{AuthService::requireAuth('cliente');if(!IndicacaoOficinaService::ativo())$this->json(['ok'=>true,'oficinas'=>[]]);$this->json(['ok'=>true,'oficinas'=>PedidoIndicacaoOficina::listarOficinasAtivas()]);}
 public function selecionar():void{AuthService::requireAuth('cliente');$this->csrf();try{$u=AuthService::getCurrentUser();$r=IndicacaoOficinaService::registrarSelecao((int)($_POST['pedido_id']??0),(int)($_POST['provider_id']??0),(int)($u['id']??0));$this->json(['ok'=>true,'indicacao'=>$r]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function nonce(int $pedidoId):void{AuthService::requireAuth('guincho');try{$pedido=Pedido::buscarPorId($pedidoId);$row=PedidoIndicacaoOficina::buscarPorPedido($pedidoId);if(!$pedido||!$row)$this->json(['ok'=>false,'erro'=>'Indicação não encontrada.'],404);$this->json(['ok'=>true,'nonce'=>EvidenceService::issueNonce($pedido,'CHECKIN_OFICINA')]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}} public function checkin():void{AuthService::requireAuth('guincho');$this->csrf();try{$u=AuthService::getCurrentUser();$g=Guincho::buscarPorUsuario((int)($u['id']??0));$r=IndicacaoOficinaService::confirmarCheckin((int)($_POST['pedido_id']??0),['guincho_id'=>(int)($g['id']??0),'qr_token'=>(string)($_POST['qr_token']??''),'file'=>$_FILES['foto']??[]]);$this->json(['ok'=>true,'indicacao'=>$r]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function admin():void{AuthService::requireAuth('admin');$indicacoes=PedidoIndicacaoOficina::listarPendentes();$oficinas=ProviderWorkshopService::listarParaAdmin();require __DIR__.'/../Views/admin/oficinas_parceiras.php';}
 public function cadastrar():void{AuthService::requireAuth('admin');$this->csrf();try{$u=AuthService::getCurrentUser();$r=WorkshopOnboardingService::cadastrar($_POST,(int)($u['id']??0));$this->json(['ok'=>true,'provider'=>$r]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function aprovar():void{AuthService::requireAuth('admin');$this->csrf();try{$u=AuthService::getCurrentUser();$this->json(['ok'=>true,'provider'=>WorkshopOnboardingService::aprovar((int)($_POST['provider_id']??0),(int)($u['id']??0))]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function suspender():void{AuthService::requireAuth('admin');$this->csrf();try{$u=AuthService::getCurrentUser();$this->json(['ok'=>true,'provider'=>WorkshopOnboardingService::suspender((int)($_POST['provider_id']??0),(int)($u['id']??0),(string)($_POST['motivo']??''))]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function reativar():void{AuthService::requireAuth('admin');$this->csrf();try{$u=AuthService::getCurrentUser();$this->json(['ok'=>true,'provider'=>WorkshopOnboardingService::reativar((int)($_POST['provider_id']??0),(int)($u['id']??0))]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function atualizar():void{AuthService::requireAuth('admin');$this->csrf();try{$u=AuthService::getCurrentUser();$this->json(['ok'=>true,'settings'=>ProviderWorkshopService::atualizarRegras((int)($_POST['provider_id']??0),$_POST,(int)($u['id']??0))]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function revisar():void{AuthService::requireAuth('admin');$this->csrf();try{$u=AuthService::getCurrentUser();$this->json(['ok'=>true,'indicacao'=>IndicacaoOficinaService::revisarManualmente((int)($_POST['id']??0),(int)($u['id']??0),(string)($_POST['veredito']??''),(string)($_POST['nota']??''))]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function estornar():void{AuthService::requireAuth('admin');$this->csrf();try{$u=AuthService::getCurrentUser();$this->json(['ok'=>true,'estorno'=>IndicacaoOficinaService::estornar((int)($_POST['id']??0),(int)($u['id']??0),(string)($_POST['motivo']??''))]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
 public function bypassCasos():void{AuthService::requireAuth('admin');$this->json(['ok'=>true,'casos'=>PedidoBypassCase::listar()]);}
 public function bypassDecidir():void{AuthService::requireAuth('admin');$this->csrf();try{$u=AuthService::getCurrentUser();$id=(int)($_POST['id']??0);$status=strtoupper((string)($_POST['status']??''));$ok=PedidoBypassCase::decidir($id,(int)($u['id']??0),$status,(string)($_POST['nota']??''));AuditTrailService::evento('pedido_bypass_decidido',__CLASS__,__FUNCTION__,['case_id'=>$id,'status'=>$status,'admin_id'=>(int)($u['id']??0)]);$this->json(['ok'=>$ok]);}catch(Throwable $e){$this->json(['ok'=>false,'erro'=>$e->getMessage()],422);}}
}
