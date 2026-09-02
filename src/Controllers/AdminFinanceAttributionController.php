<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Services/Logger.php';
require_once __DIR__ . '/../Services/MarketingAttributionService.php';
require_once __DIR__ . '/../Services/MarketingSpendService.php';
require_once __DIR__ . '/../Services/FinancialAttributionReportService.php';
require_once __DIR__ . '/../Services/PreQuoteDemandService.php';
require_once __DIR__ . '/../Services/Prospeccao/SerpApiMapsClient.php';
require_once __DIR__ . '/../Services/Prospeccao/RegiaoQuotaService.php';
require_once __DIR__ . '/../Services/Prospeccao/MensagemPersuasaoService.php';
require_once __DIR__ . '/../Services/Prospeccao/ProspeccaoParceirosService.php';
require_once __DIR__ . '/../Services/Prospeccao/ProspeccaoSchemaInstaller.php';
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
        $prospeccao = $this->carregarProspecaoPainel();
        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/admin/marketing_central.php';
    }

    public function prospeccaoBuscar(): void
    {
        $this->executarProspecaoAction(function (ProspeccaoParceirosService $service): void {
            $regiaoId = (int)($_POST['regiao_id'] ?? 0);
            $paginas = max(1, min(3, (int)($_POST['paginas'] ?? 1)));
            $inseridos = $service->buscarLeadsParaRegiao($regiaoId, $paginas);
            $_SESSION['flash_success'] = count($inseridos) . ' lead(s) novo(s) coletado(s).';
        });
        $this->redirect('/admin/marketing#prospeccao');
    }

    public function prospeccaoRegiaoSalvar(): void
    {
        $this->executarProspecaoAction(function (ProspeccaoParceirosService $service): void {
            $regiaoService = $this->prospeccaoRegiaoService();
            $nome = trim((string)($_POST['nome'] ?? ''));
            $cidade = trim((string)($_POST['cidade'] ?? ''));
            $uf = strtoupper(trim((string)($_POST['uf'] ?? '')));
            $lat = (float)str_replace(',', '.', (string)($_POST['lat'] ?? '0'));
            $lng = (float)str_replace(',', '.', (string)($_POST['lng'] ?? '0'));
            $raio = max(1, (int)($_POST['raio_km'] ?? PROSPECCAO_RAIO_PADRAO_KM));
            $categorias = trim((string)($_POST['categorias_alvo'] ?? PROSPECCAO_CATEGORIAS_ALVO));
            $quota = max(1, (int)($_POST['quota_alvo'] ?? PROSPECCAO_QUOTA_ALVO_PADRAO));
            $prioridade = (int)($_POST['prioridade_fuseki'] ?? PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO);

            if ($nome === '' || $cidade === '' || !preg_match('/^[A-Z]{2}$/', $uf) || $lat === 0.0 || $lng === 0.0) {
                throw new RuntimeException('Preencha nome, cidade, UF e coordenadas validas.');
            }

            $regiaoService->criar([
                'nome' => $nome,
                'cidade' => $cidade,
                'uf' => $uf,
                'lat' => $lat,
                'lng' => $lng,
                'raio_km' => $raio,
                'categorias_alvo' => $categorias,
                'quota_alvo' => $quota,
                'prioridade_fuseki' => $prioridade,
            ]);

            $service->registrarAtividade('operacao', 'regiao_criada', [
                'titulo' => 'Região criada manualmente',
                'regiao_id' => null,
                'detalhes' => [
                    'nome' => $nome,
                    'cidade' => $cidade,
                    'uf' => $uf,
                ],
            ]);

            $_SESSION['flash_success'] = 'Regiao criada com sucesso.';
        });
        $this->redirect('/admin/marketing#prospeccao');
    }

    public function prospeccaoMarcarEnviado(int $leadId): void
    {
        $this->executarProspecaoAction(function (ProspeccaoParceirosService $service) use ($leadId): void {
            $mensagem = (string)($_POST['mensagem_texto'] ?? '');
            $waLink = trim((string)($_POST['wa_link'] ?? ''));
            $usuarioId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
            $service->marcarComoEnviado($leadId, $mensagem, $waLink !== '' ? $waLink : null, $usuarioId);
            $_SESSION['flash_success'] = 'Lead marcado como enviado.';
        });
        $this->redirect('/admin/marketing#prospeccao');
    }

    public function prospeccaoConfirmarCadastro(int $leadId): void
    {
        $this->executarProspecaoAction(function (ProspeccaoParceirosService $service) use ($leadId): void {
            $service->confirmarCadastro($leadId);
            $_SESSION['flash_success'] = 'Cadastro confirmado e quota atualizada.';
        });
        $this->redirect('/admin/marketing#prospeccao');
    }

    public function prospeccaoSincronizarZonas(): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        try {
            $this->ensureProspecaoSchema();
            $regiaoService = $this->prospeccaoRegiaoService();
            $prospeccaoService = $this->prospeccaoService($regiaoService);
            $zonas = $this->carregarZonasTerritoriaisProspecao();
            $importadas = 0;
            $atualizadas = 0;

            foreach ($zonas as $zona) {
                if (empty($zona['centro']['lat']) || empty($zona['centro']['lng'])) {
                    continue;
                }

                $existente = $regiaoService->buscarPorNomeCidadeUf(
                    (string)$zona['name'],
                    (string)$zona['cidade'],
                    (string)$zona['uf']
                );

                $regiaoService->salvarOuAtualizar([
                    'nome' => (string)$zona['name'],
                    'cidade' => (string)$zona['cidade'],
                    'uf' => (string)$zona['uf'],
                    'lat' => (float)$zona['centro']['lat'],
                    'lng' => (float)$zona['centro']['lng'],
                    'raio_km' => (int)($zona['raio_km_sugerido'] ?? PROSPECCAO_RAIO_PADRAO_KM),
                    'categorias_alvo' => (string)$zona['categorias_alvo'],
                    'quota_alvo' => (int)($zona['quota_alvo_sugerida'] ?? PROSPECCAO_QUOTA_ALVO_PADRAO),
                    'prioridade_fuseki' => (int)($zona['prioridade_fuseki'] ?? PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO),
                    'status' => 'ativa',
                ]);

                if ($existente) {
                    $atualizadas++;
                } else {
                    $importadas++;
                }
            }

            $prospeccaoService->registrarAtividade('operacao', 'sincronizar_zonas', [
                'titulo' => 'Sincronização de zonas de Niterói',
                'detalhes' => [
                    'importadas' => $importadas,
                    'atualizadas' => $atualizadas,
                    'total_detectado' => count($zonas),
                ],
            ]);

            $_SESSION['flash_success'] = sprintf(
                'Sincronização de Niterói concluída: %d importada(s), %d atualizada(s).',
                $importadas,
                $atualizadas
            );
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            Logger::log(Logger::LEVEL_ERROR, __CLASS__, __FUNCTION__, 'marketing', 'Falha na sincronização das zonas de Niterói', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }

        $this->redirect('/admin/marketing#prospeccao');
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

    private function carregarProspecaoPainel(): array
    {
        $regioes = [];
        $fila = [];
        $historico = [];
        $erroFila = null;
        $setupError = null;
        $serpApiConfigurada = trim((string)SERPAPI_KEY) !== '';
        $zonasTerritoriais = [];

        try {
            $this->ensureProspecaoSchema();
            $regiaoService = $this->prospeccaoRegiaoService();
            $prospeccaoService = $this->prospeccaoService($regiaoService);
            $regioes = $regiaoService->listarRegioesAtivas();
            $zonasTerritoriais = $this->carregarZonasTerritoriaisProspecao();
            $historico = $prospeccaoService->listarHistorico(30);

            try {
                $fila = $prospeccaoService->gerarFilaDoDia(20);
            } catch (Throwable $e) {
                $erroFila = $e->getMessage();
            }

            $serpApiConfigurada = $prospeccaoService->serpApiConfigurada();
        } catch (Throwable $e) {
            $setupError = $e->getMessage();
            $erroFila = $erroFila ?? $e->getMessage();
        }

        $leadsPendentes = count($fila);
        $vagasRestantes = 0;
        foreach ($regioes as $regiao) {
            $vagasRestantes += max(0, (int)($regiao['quota_alvo'] ?? 0) - (int)($regiao['quota_atingida'] ?? 0));
        }

        return [
            'schema_ok' => $setupError === null,
            'setup_error' => $setupError,
            'serpapi_configurada' => $serpApiConfigurada,
            'serpapi_key' => SERPAPI_KEY,
            'company_whatsapp' => COMPANY_WHATSAPP,
            'url_pre_cadastro' => PROSPECCAO_URL_PRE_CADASTRO,
            'oferta_reciprocidade' => PROSPECCAO_OFERTA_RECIPROCIDADE,
            'categorias_padrao' => PROSPECCAO_CATEGORIAS_ALVO,
            'quota_padrao' => PROSPECCAO_QUOTA_ALVO_PADRAO,
            'prioridade_padrao' => PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO,
            'raio_padrao' => PROSPECCAO_RAIO_PADRAO_KM,
            'zonas_territoriais' => $zonasTerritoriais,
            'regioes' => $regioes,
            'fila' => $fila,
            'historico' => $historico,
            'leads_pendentes' => $leadsPendentes,
            'vagas_restantes' => $vagasRestantes,
            'erro_fila' => $erroFila,
            'resumo' => [
                'regioes_ativas' => count($regioes),
                'leads_na_fila' => $leadsPendentes,
                'vagas_restantes' => $vagasRestantes,
                'serpapi_ativa' => $serpApiConfigurada,
            ],
        ];
    }

    private function prospeccaoService(?RegiaoQuotaService $regiaoService = null): ProspeccaoParceirosService
    {
        $this->ensureProspecaoSchema();
        $pdo = getPDO();
        $regiaoService = $regiaoService ?? new RegiaoQuotaService($pdo);
        $mensagemService = new MensagemPersuasaoService(
            PROSPECCAO_URL_PRE_CADASTRO,
            null,
            PROSPECCAO_OFERTA_RECIPROCIDADE,
            COMPANY_WHATSAPP
        );

        return new ProspeccaoParceirosService(
            $pdo,
            new SerpApiMapsClient(SERPAPI_KEY),
            $regiaoService,
            $mensagemService
        );
    }

    private function prospeccaoRegiaoService(): RegiaoQuotaService
    {
        $this->ensureProspecaoSchema();
        return new RegiaoQuotaService(getPDO());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function carregarZonasTerritoriaisProspecao(): array
    {
        $pdo = getPDO();
        $regiaoService = $this->prospeccaoRegiaoService();
        $cidade = null;

        try {
            $stmt = $pdo->prepare("SELECT * FROM cidades WHERE slug = ? OR (nome = ? AND uf = ?) LIMIT 1");
            $stmt->execute(['niteroi-rj', 'Niterói', 'RJ']);
            $cidade = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $cidade = null;
        }

        $cidadeId = $cidade ? (int)($cidade['id'] ?? 0) : 0;
        $zonas = PricingZone::listarPorOrdemExpansao($cidadeId > 0 ? $cidadeId : null, true);
        $resultado = [];

        foreach ($zonas as $zona) {
            $status = (string)($zona['status_expansao'] ?? 'nao_ativada');
            if (!in_array($status, ['pedra_viva', 'pedra_morta'], true) && !str_starts_with((string)($zona['code'] ?? ''), 'niteroi-')) {
                continue;
            }

            $centro = $this->calcularCentroZona((string)($zona['polygon_geojson'] ?? ''));
            $resultado[] = [
                'id' => (int)$zona['id'],
                'code' => (string)($zona['code'] ?? ''),
                'name' => (string)($zona['name'] ?? ''),
                'cidade' => $cidade ? (string)($cidade['nome'] ?? 'Niterói') : 'Niterói',
                'uf' => $cidade ? (string)($cidade['uf'] ?? 'RJ') : 'RJ',
                'status_expansao' => $status,
                'ordem_expansao' => $zona['ordem_expansao'] !== null ? (int)$zona['ordem_expansao'] : null,
                'bairros_referencia' => (string)($zona['bairros_referencia'] ?? ''),
                'raio_km_sugerido' => $this->raioSugeridoDaZona($zona),
                'quota_alvo_sugerida' => $this->quotaSugeridaDaZona($zona),
                'prioridade_fuseki' => $zona['ordem_expansao'] !== null ? (int)$zona['ordem_expansao'] : PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO,
                'categorias_alvo' => PROSPECCAO_CATEGORIAS_ALVO,
                'centro' => $centro,
                'regiao_existente' => $cidade ? $regiaoService->buscarPorNomeCidadeUf((string)($zona['name'] ?? ''), (string)($cidade['nome'] ?? 'Niterói'), (string)($cidade['uf'] ?? 'RJ')) : null,
            ];
        }

        return $resultado;
    }

    private function calcularCentroZona(string $polygonGeojson): ?array
    {
        if (trim($polygonGeojson) === '') {
            return null;
        }

        $decoded = json_decode($polygonGeojson, true);
        if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'Polygon') {
            return null;
        }

        $anel = $decoded['coordinates'][0] ?? null;
        if (!is_array($anel) || count($anel) < 3) {
            return null;
        }

        $minLat = $maxLat = null;
        $minLng = $maxLng = null;
        foreach ($anel as $point) {
            if (!is_array($point) || count($point) < 2) {
                continue;
            }
            $lng = (float)$point[0];
            $lat = (float)$point[1];
            $minLat = $minLat === null ? $lat : min($minLat, $lat);
            $maxLat = $maxLat === null ? $lat : max($maxLat, $lat);
            $minLng = $minLng === null ? $lng : min($minLng, $lng);
            $maxLng = $maxLng === null ? $lng : max($maxLng, $lng);
        }

        if ($minLat === null || $minLng === null || $maxLat === null || $maxLng === null) {
            return null;
        }

        return [
            'lat' => round(($minLat + $maxLat) / 2, 6),
            'lng' => round(($minLng + $maxLng) / 2, 6),
        ];
    }

    /**
     * @param array<string, mixed> $zona
     */
    private function raioSugeridoDaZona(array $zona): int
    {
        $status = (string)($zona['status_expansao'] ?? 'nao_ativada');
        if ($status === 'pedra_viva') {
            return max(10, (int)PROSPECCAO_RAIO_PADRAO_KM);
        }

        return max(8, (int)PROSPECCAO_RAIO_PADRAO_KM);
    }

    /**
     * @param array<string, mixed> $zona
     */
    private function quotaSugeridaDaZona(array $zona): int
    {
        $quota = (int)($zona['meta_prestadores_min'] ?? 0);
        if ($quota <= 0) {
            $quota = (int)($zona['meta_guinchos_min'] ?? 0) + (int)($zona['meta_especialistas_min'] ?? 0);
        }
        return $quota > 0 ? $quota : (int)PROSPECCAO_QUOTA_ALVO_PADRAO;
    }

    private function ensureProspecaoSchema(): void
    {
        try {
            ProspeccaoSchemaInstaller::ensure(
                getPDO(),
                __DIR__ . '/../../../install/migration_prospeccao_parceiros_v1.sql'
            );
            ProspeccaoSchemaInstaller::ensureAdditional(
                getPDO(),
                __DIR__ . '/../../../install/migration_prospeccao_parceiros_v2_historico.sql',
                ['prospeccao_atividades']
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Nao foi possivel inicializar o schema de prospeccao: ' . $e->getMessage(), 0, $e);
        }
    }

    private function executarProspecaoAction(callable $callback): void
    {
        AuthService::requireAuth('admin');
        if (!AuthService::validarCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit;
        }

        try {
            $callback($this->prospeccaoService());
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            Logger::log(Logger::LEVEL_ERROR, __CLASS__, __FUNCTION__, 'marketing', 'Falha na prospecao', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }
    }
}
