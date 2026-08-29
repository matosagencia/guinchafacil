<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Services/Logger.php';
require_once __DIR__ . '/../Services/MarketingAttributionService.php';
require_once __DIR__ . '/../Services/MarketingSpendService.php';
require_once __DIR__ . '/../Services/FinancialAttributionReportService.php';
require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
require_once __DIR__ . '/../Models/Pricing/PricingZone.php';
require_once __DIR__ . '/../Models/Cidade.php';

final class AdminFinanceAttributionController extends BaseController
{
    /** Centraliza demanda, cobertura, campanhas e retorno financeiro em um único tabuleiro. */
    public function marketingCentral(): void
    {
        AuthService::requireAuth('admin');
        $inicio = (string)($_GET['inicio'] ?? date('Y-m-01'));
        $fim = (string)($_GET['fim'] ?? date('Y-m-d'));
        $resumo = FinancialAttributionReportService::resumo($inicio, $fim);
        $porCanal = FinancialAttributionReportService::porCanal($inicio, $fim);
        $demanda = PreQuoteDemandService::listarPrioridades(30, 12);
        $demandaServicos = PreQuoteDemandService::resumoPorServico(30);
        $zonas = PricingZone::listarPorOrdemExpansao();
        $cidades = Cidade::listarAtivas();
        $gastos = MarketingSpendService::listar($inicio, $fim);
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/marketing_central.php';
    }

    public function campanhaSalvar(): void
    {
        $admin = AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
        $nome = trim((string)($_POST['nome'] ?? ''));
        $objetivos = ['captar_clientes','captar_guinchos','captar_especialistas','aumentar_cobertura','reativar_demanda'];
        $publicos = ['cliente','guincho','especialista','misto'];
        $status = ['rascunho','planejada','ativa','pausada','encerrada'];
        $objetivo = (string)($_POST['objetivo'] ?? 'captar_clientes');
        $publico = (string)($_POST['publico'] ?? 'cliente');
        $situacao = (string)($_POST['status'] ?? 'rascunho');
        if ($nome === '' || !in_array($objetivo, $objetivos, true) || !in_array($publico, $publicos, true) || !in_array($situacao, $status, true)) {
            $this->redirect('/admin/marketing?erro=campanha_invalida');
        }
        $slug = trim((string)($_POST['utm_campaign'] ?? ''));
        if ($slug === '') { $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)) ?? 'campanha'); }
        $sql = 'INSERT INTO marketing_campaigns (nome,objetivo,publico,pricing_zone_id,service_type_id,canal,utm_campaign,mensagem,landing_url,orcamento_planejado,inicio,fim,status,criado_por_admin_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        getPDO()->prepare($sql)->execute([$nome,$objetivo,$publico,(int)($_POST['pricing_zone_id'] ?? 0) ?: null,(int)($_POST['service_type_id'] ?? 0) ?: null,MarketingAttributionService::normalizarCanal((string)($_POST['canal'] ?? 'organico')),$slug,trim((string)($_POST['mensagem'] ?? '')),trim((string)($_POST['landing_url'] ?? '')),max(0,(float)str_replace(',','.',(string)($_POST['orcamento_planejado'] ?? 0))),($_POST['inicio'] ?? '') ?: null,($_POST['fim'] ?? '') ?: null,$situacao,(int)$admin['id']]);
        Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'marketing', 'Campanha criada', ['admin_id'=>$admin['id'],'nome'=>$nome,'objetivo'=>$objetivo]);
        $this->redirect('/admin/marketing?ok=campanha');
    }

    public function visaoUnificada(): void { AuthService::requireAuth('admin'); $d=(string)($_GET['inicio']??date('Y-m-01'));$a=(string)($_GET['fim']??date('Y-m-d'));$resumo=FinancialAttributionReportService::resumo($d,$a);$porCanal=FinancialAttributionReportService::porCanal($d,$a);$celulas=FinancialAttributionReportService::porCelula($d,$a);$gastos=MarketingSpendService::listar($d,$a);$csrfToken=AuthService::gerarCsrfToken();require __DIR__.'/../Views/admin/financeiro_atribuicao.php'; }
    public function gastoSalvar(): void { $admin=AuthService::requireAuth('admin');if(!AuthService::validarCsrfToken($_POST['csrf_token']??'')){http_response_code(403);exit;} $data=(string)($_POST['data']??'');$valor=(float)str_replace(',','.',(string)($_POST['valor_gasto']??0));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$data)||$valor<0){$this->redirect('/admin/financeiro/visao-unificada?erro=gasto_invalido');} MarketingSpendService::salvar(['data'=>$data,'canal'=>$_POST['canal']??'organico','campanha'=>$_POST['campanha']??'','valor_gasto'=>$valor,'cidade_id'=>$_POST['cidade_id']??null],(int)$admin['id']);$this->redirect('/admin/financeiro/visao-unificada?ok=gasto'); }
    public function gastoImportar(): void { $admin=AuthService::requireAuth('admin');if(!AuthService::validarCsrfToken($_POST['csrf_token']??'')){http_response_code(403);exit;} $f=$_FILES['csv']??null;if(!$f||($f['error']??1)!==UPLOAD_ERR_OK){$this->redirect('/admin/financeiro/visao-unificada?erro=csv');} $h=fopen($f['tmp_name'],'rb');$header=fgetcsv($h,0,';');if(!$header)$header=fgetcsv($h,0,',');$header=array_map(static fn($v)=>strtolower(trim((string)$v)),(array)$header);$n=0;while(($row=fgetcsv($h,0,';'))!==false){if(count($row)<3)continue;$data=$row[array_search('data',$header,true)?:0]??'';$camp=$row[array_search('campanha',$header,true)?:1]??'';$canal=$row[array_search('canal',$header,true)?:2]??'google_ads';$valor=$row[array_search('valor_gasto',$header,true)?:3]??0;MarketingSpendService::salvar(['data'=>$data,'campanha'=>$camp,'canal'=>$canal,'valor_gasto'=>str_replace(',','.',$valor),'origem_lancamento'=>'import_csv'],(int)$admin['id']);$n++;}fclose($h);Logger::log(Logger::LEVEL_INFO,__CLASS__,__FUNCTION__,'financeiro',"Importação de gastos concluída: {$n} linha(s)",['admin_id'=>$admin['id']]);$this->redirect('/admin/financeiro/visao-unificada?ok=import');}
    public function gastoExcluir(): void { $admin=AuthService::requireAuth('admin');$id=(int)($_POST['id']??0);if(!AuthService::validarCsrfToken($_POST['csrf_token']??'')){http_response_code(403);exit;} $s=getPDO()->prepare('SELECT * FROM gastos_marketing WHERE id=? AND ativo=1');$s->execute([$id]);$antes=$s->fetch(PDO::FETCH_ASSOC);if($antes){getPDO()->prepare('UPDATE gastos_marketing SET ativo=0,excluido_por_admin_id=?,excluido_em=NOW() WHERE id=?')->execute([(int)$admin['id'],$id]);Logger::log(Logger::LEVEL_WARN,__CLASS__,__FUNCTION__,'financeiro',"Gasto de marketing #{$id} excluído logicamente",['admin_id'=>$admin['id'],'antes'=>$antes,'depois'=>['ativo'=>0]]);} $this->redirect('/admin/financeiro/visao-unificada?ok=excluido'); }
    public function exportarCsv(): void { AuthService::requireAuth('admin');$d=(string)($_GET['inicio']??date('Y-m-01'));$a=(string)($_GET['fim']??date('Y-m-d'));$rows=FinancialAttributionReportService::porCelula($d,$a);header('Content-Type:text/csv; charset=UTF-8');header('Content-Disposition:attachment; filename="financeiro_atribuicao_'.date('Ymd').'.csv"');$o=fopen('php://output','w');fputcsv($o,['Canal','Cidade','Serviço','Categoria','Pedidos','Bruto','Comissão'], ';');foreach($rows as $r)fputcsv($o,[$r['canal'],$r['cidade'],$r['servico'],$r['categoria'],$r['pedidos'],$r['bruto'],$r['comissao']],';');fclose($o);exit;}
}
