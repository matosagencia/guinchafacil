<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$v = fn($k) => htmlspecialchars((string)($p[$k] ?? ''));
$comissaoPct = isset($comissaoPct) ? (float)$comissaoPct : 15;
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-calculator"></i><span style="color:var(--theme-muted);font-size:.85rem;">Break-even, mídia paga (Meta e Google Ads), independência e comparação de praças — recalcula ao vivo.</span></div>
    <div class="ops-topbar__meta"><span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span>Simulador</span></div>
</div>

<div class="shell-ops shell-ops--no-worklist" id="planejamentoShell">

    <aside class="shell-ops-sidebar" id="planejamentoSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-workspace" id="planejamentoWorkspace" aria-label="Planejamento de lançamento" style="padding:24px;">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gestão</span>
            <h1><i class="fas fa-calculator me-2"></i>Planejamento de lançamento</h1>
            <p>Break-even, mídia paga (Meta e Google Ads), independência e comparação de praças — recalcula ao vivo. Cada cidade-alvo tem seu próprio cenário, salvo separadamente.</p>
        </div>
    </header>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3"><?php echo htmlspecialchars($flash['message']); ?></div>
    <?php endif; ?>

    <?php if (empty($cidades)): ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-triangle-exclamation me-2"></i>
        Nenhuma cidade-alvo cadastrada ainda — este cenário está usando os parâmetros globais legados.
        <a href="<?php echo $bp; ?>/admin/cidades">Cadastre a primeira cidade-alvo</a> pra começar a segmentar o planejamento por cidade.
    </div>
    <?php else: ?>
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <label class="form-label mb-0 fw-bold"><i class="fas fa-city me-1"></i>Cidade-alvo:</label>
            <select class="form-select w-auto" id="cidadeSeletor" onchange="if(this.value) window.location.href = <?php echo json_encode($bp . '/admin/planejamento?cidade_id='); ?> + this.value;">
                <?php foreach ($cidades as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo ($cidadeSelecionada && (int)$cidadeSelecionada['id'] === (int)$c['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['nome'] . '/' . $c['uf']); ?><?php echo empty($c['ativo']) ? ' (inativa)' : ''; ?>
                </option>
                <?php endforeach; ?>
            </select>
            <a href="<?php echo $bp; ?>/admin/cidades" class="ops-btn"><i class="fas fa-gear"></i> Gerenciar cidades</a>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo $bp; ?>/admin/planejamento/salvar" id="planForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="cidade_id" value="<?php echo (int)($cidadeSelecionada['id'] ?? 0); ?>">

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header fw-bold"><i class="fas fa-sliders me-2"></i>Parâmetros do negócio</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label small">Comissão da plataforma (%)</label>
                                <input type="number" step="0.1" class="form-control plan-in" id="comissao" value="<?php echo $comissaoPct; ?>">
                                <div class="form-text" style="font-size:.7rem">Vem de <code>comissao_plataforma</code> (Configurações). Aqui é só simulação.</div></div>
                            <div class="col-6"><label class="form-label small">Ticket médio (R$)</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_ticket" id="ticket" value="<?php echo $v('plan_ticket'); ?>"></div>
                            <div class="col-6"><label class="form-label small">Taxa mínima / atend. (R$)</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_taxa_min" id="taxaMin" value="<?php echo $v('plan_taxa_min'); ?>"></div>
                            <div class="col-6"><label class="form-label small">Infra / mês (R$)</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_custo_infra" id="infra" value="<?php echo $v('plan_custo_infra'); ?>"></div>
                            <div class="col-6"><label class="form-label small">Mídia paga total / mês (R$)</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_custo_midia" id="midia" value="<?php echo $v('plan_custo_midia'); ?>"></div>
                            <div class="col-6"><label class="form-label small">Mídia de manutenção (R$)</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_midia_manutencao" id="midiaManut" value="<?php echo $v('plan_midia_manutencao'); ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header fw-bold"><i class="fas fa-bullseye me-2"></i>Ponto de equilíbrio</div>
                    <div class="card-body">
                        <div class="row text-center g-3">
                            <div class="col-md-4"><div class="p-3 rounded" style="background:rgba(47,179,74,.08)">
                                <div class="small text-muted">Receita por atendimento</div><div class="fs-4 fw-bold" id="rReceita">—</div></div></div>
                            <div class="col-md-4"><div class="p-3 rounded" style="background:rgba(47,179,74,.08)">
                                <div class="small text-muted">Break-even / mês</div><div class="fs-4 fw-bold text-success" id="rBE">—</div>
                                <div class="small text-muted">atend. pagos</div></div></div>
                            <div class="col-md-4"><div class="p-3 rounded" style="background:rgba(47,179,74,.08)">
                                <div class="small text-muted">Por dia</div><div class="fs-4 fw-bold" id="rDia">—</div>
                                <div class="small text-muted">socorros/dia</div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ANDA SOZINHO -->
        <div class="row g-4 mt-1">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header fw-bold"><i class="fas fa-person-walking me-2"></i>Quando o negócio anda sozinho</div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-6"><label class="form-label small">% de chamados SEM mídia paga hoje</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_organico" id="organico" value="<?php echo $v('plan_organico'); ?>"></div>
                            <div class="col-6"><label class="form-label small">Meta de independência (%)</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_meta_organico" id="metaOrg" value="<?php echo $v('plan_meta_organico'); ?>"></div>
                        </div>

                        <div class="small fw-bold mb-1">Independência de mídia <span id="indepTxt" class="text-muted"></span></div>
                        <div class="progress mb-3" style="height:22px">
                            <div class="progress-bar bg-success" id="indepBar" role="progressbar" style="width:0%">0%</div>
                        </div>

                        <div class="small fw-bold mb-1">Rumo ao break-even do mês <span id="beTxt" class="text-muted"></span></div>
                        <div class="progress mb-2" style="height:22px">
                            <div class="progress-bar" id="beBar" role="progressbar" style="width:0%">0%</div>
                        </div>

                        <div class="p-2 rounded mt-3" id="sozinhoBox" style="background:rgba(47,179,74,.08)">
                            <div class="small">Break-even <strong>sem mídia paga cheia</strong> (só infra + manutenção): <strong id="beSemMidia">—</strong> atend./mês.</div>
                            <div class="small mt-1" id="sozinhoVeredito">—</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header fw-bold"><i class="fas fa-chart-pie me-2"></i>Origem dos chamados</div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="chartIndep" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- MÍDIA PAGA + UPLOAD DE PLANILHAS -->
        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-bold"><i class="fab fa-facebook me-2"></i>Meta Ads (Fase 1 — prestadores)</div>
                    <div class="card-body">
                        <div class="mb-3 p-2 rounded" style="border:1px dashed rgba(47,179,74,.4)">
                            <label class="form-label small mb-1"><i class="fas fa-file-csv me-1"></i>Importar relatório do Meta Ads (.csv)</label>
                            <input type="file" accept=".csv,text/csv" class="form-control form-control-sm" id="metaCsv">
                            <div class="small text-muted mt-1" id="metaCsvMsg">Some o gasto e os resultados/cadastros e calcula o CPL automaticamente.</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-4"><label class="form-label small">Orçamento/mês</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_meta_orcamento" id="metaOrc" value="<?php echo $v('plan_meta_orcamento'); ?>"></div>
                            <div class="col-4"><label class="form-label small">CPL especialista</label>
                                <input type="number" step="0.5" class="form-control plan-in" name="plan_meta_cpl_esp" id="cplEsp" value="<?php echo $v('plan_meta_cpl_esp'); ?>"></div>
                            <div class="col-4"><label class="form-label small">CPL guincho</label>
                                <input type="number" step="0.5" class="form-control plan-in" name="plan_meta_cpl_gui" id="cplGui" value="<?php echo $v('plan_meta_cpl_gui'); ?>"></div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between small"><span>Cadastros de especialista/mês:</span><strong id="mCadEsp">—</strong></div>
                        <div class="d-flex justify-content-between small"><span>Cadastros de guincho/mês:</span><strong id="mCadGui">—</strong></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-bold"><i class="fab fa-google me-2"></i>Google Ads (Fase 2 — motoristas)</div>
                    <div class="card-body">
                        <div class="mb-3 p-2 rounded" style="border:1px dashed rgba(47,179,74,.4)">
                            <label class="form-label small mb-1"><i class="fas fa-file-csv me-1"></i>Importar relatório do Google Ads (.csv)</label>
                            <input type="file" accept=".csv,text/csv" class="form-control form-control-sm" id="googleCsv">
                            <div class="small text-muted mt-1" id="googleCsvMsg">Some o custo e os cliques e calcula o CPC automaticamente.</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-3"><label class="form-label small">Orçamento</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_google_orcamento" id="gOrc" value="<?php echo $v('plan_google_orcamento'); ?>"></div>
                            <div class="col-3"><label class="form-label small">CPC (R$)</label>
                                <input type="number" step="0.1" class="form-control plan-in" name="plan_google_cpc" id="cpc" value="<?php echo $v('plan_google_cpc'); ?>"></div>
                            <div class="col-3"><label class="form-label small">Conv. LP (%)</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_google_conv" id="conv" value="<?php echo $v('plan_google_conv'); ?>"></div>
                            <div class="col-3"><label class="form-label small">Fecha (%)</label>
                                <input type="number" step="1" class="form-control plan-in" name="plan_fechamento" id="fecha" value="<?php echo $v('plan_fechamento'); ?>"></div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between small"><span>Custo por chamado (CPA):</span><strong id="gCpa">—</strong></div>
                        <div class="d-flex justify-content-between small"><span>Chamados abertos/mês:</span><strong id="gChamados">—</strong></div>
                        <div class="d-flex justify-content-between small"><span>Atendimentos pagos/mês:</span><strong id="gPagos">—</strong></div>
                        <div class="d-flex justify-content-between small"><span>Custo por atendimento pago:</span><strong id="gCustoPago">—</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar parâmetros</button>
        </div>
    </form>

    <!-- GRÁFICOS -->
    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header fw-bold"><i class="fas fa-chart-line me-2"></i>Runway — caixa acumulado</div>
                <div class="card-body"><canvas id="chartRunway" height="150"></canvas></div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header fw-bold"><i class="fas fa-city me-2"></i>Break-even por praça</div>
                <div class="card-body"><canvas id="chartCidades" height="150"></canvas></div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header fw-bold"><i class="fas fa-filter me-2"></i>Funil Google (mês)</div>
                <div class="card-body"><canvas id="chartFunnel" height="150"></canvas></div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header fw-bold"><i class="fas fa-chart-column me-2"></i>Receita × Custo (runway)</div>
                <div class="card-body"><canvas id="chartRecCusto" height="150"></canvas></div></div>
        </div>
    </div>

    <!-- Comparação de praças -->
    <div class="card mt-4">
        <div class="card-header fw-bold"><i class="fas fa-table me-2"></i>Praças (edite o ticket médio de cada cidade)</div>
        <div class="card-body p-0"><div class="table-responsive">
            <table class="table table-sm mb-0 align-middle"><thead><tr>
                <th>Cidade</th><th style="width:150px">Ticket (R$)</th><th>Receita/atend.</th><th>Break-even/mês</th><th>Por dia</th><th>Mídia</th><th>Veredito</th>
            </tr></thead><tbody id="cidadesBody"></tbody></table>
        </div></div>
    </div>

    <!-- Runway -->
    <div class="card mt-4">
        <div class="card-header fw-bold"><i class="fas fa-calendar me-2"></i>Runway (edite os atendimentos por mês)</div>
        <div class="card-body p-0"><div class="table-responsive">
            <table class="table table-sm mb-0 align-middle"><thead><tr>
                <th>Mês</th><th style="width:130px">Atendimentos</th><th>Receita</th><th>Custo</th><th>Resultado</th><th>Caixa acumulado</th>
            </tr></thead><tbody id="runwayBody"></tbody></table>
            <div class="p-2 small text-muted">Caixa necessário (pico do déficit): <strong id="caixaPico">—</strong></div>
        </div></div>
    </div>

    </section>

</div>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops--no-worklist,
// mesma arquitetura de grid do Dashboard — não há uma entidade única aqui
// pra virar worklist clicável (é uma calculadora, não uma listagem).
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
(function(){
    const $ = id => document.getElementById(id);
    const num = id => { const el=$(id); return el ? (parseFloat((el.value||'0').toString().replace(',','.'))||0) : 0; };
    const brl = v => 'R$ ' + (v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
    const int = v => Math.round(v||0).toLocaleString('pt-BR');

    const cidades = [
        ['Niterói (RJ)',175,'Baixo/Médio','Laboratório ideal — menor caixa, ao lado de casa'],
        ['Belo Horizonte (MG)',180,'Médio','Melhor escala — praça nº 2'],
        ['Rio de Janeiro (RJ)',195,'Alto','Só com caixa robusto e por zona'],
        ['São Paulo (SP)',210,'Muito alto','Verba "vira pó" se espalhada'],
    ];
    const meses = ['Mês 1','Mês 2','Mês 3','Mês 4','Mês 5','Mês 6','Mês 7','Mês 8'];
    const rampaDef = [0,0,25,55,90,116,150,185];

    const cb=$('cidadesBody');
    cidades.forEach((c,i)=>{ const tr=document.createElement('tr');
        tr.innerHTML=`<td><strong>${c[0]}</strong></td>
        <td><input type="number" step="1" class="form-control form-control-sm cidade-ticket" value="${c[1]}"></td>
        <td class="c-rec">—</td><td class="c-be fw-bold">—</td><td class="c-dia">—</td><td>${c[2]}</td><td class="small">${c[3]}</td>`;
        cb.appendChild(tr); });
    const rb=$('runwayBody');
    meses.forEach((m,i)=>{ const tr=document.createElement('tr');
        tr.innerHTML=`<td>${m}</td>
        <td><input type="number" step="1" class="form-control form-control-sm run-at" value="${rampaDef[i]}"></td>
        <td class="r-rec">—</td><td class="r-cus">—</td><td class="r-res">—</td><td class="r-cx fw-bold">—</td>`;
        rb.appendChild(tr); });

    // ── Charts ──
    let chRun, chCid, chFun, chInd, chRC;
    function initCharts(){
        if (typeof Chart === 'undefined') return;
        const green='#2fb34a', red='#dc3545', gray='#9aa0a6', blue='#4285f4';
        chInd = new Chart($('chartIndep'), {type:'doughnut',
            data:{labels:['Sem mídia (orgânico)','Com mídia paga'],datasets:[{data:[10,90],backgroundColor:[green,gray]}]},
            options:{plugins:{legend:{position:'bottom'}}}});
        chRun = new Chart($('chartRunway'), {type:'line',
            data:{labels:meses,datasets:[{label:'Caixa acumulado (R$)',data:[],borderColor:green,backgroundColor:'rgba(47,179,74,.15)',fill:true,tension:.25}]},
            options:{plugins:{legend:{display:false}}}});
        chCid = new Chart($('chartCidades'), {type:'bar',
            data:{labels:cidades.map(c=>c[0]),datasets:[{label:'Break-even/mês',data:[],backgroundColor:green}]},
            options:{plugins:{legend:{display:false}}}});
        chFun = new Chart($('chartFunnel'), {type:'bar',
            data:{labels:['Cliques','Chamados abertos','Atend. pagos'],datasets:[{label:'Qtde/mês',data:[],backgroundColor:[blue,'#f9ab00',green]}]},
            options:{plugins:{legend:{display:false}}}});
        chRC = new Chart($('chartRecCusto'), {type:'bar',
            data:{labels:meses,datasets:[{label:'Receita',data:[],backgroundColor:green},{label:'Custo',data:[],backgroundColor:gray}]},
            options:{}});
    }

    function calc(){
        const comissao=num('comissao')/100, ticket=num('ticket'), taxaMin=num('taxaMin');
        const custoTotal=num('midia')+num('infra');
        const receitaAtend=Math.max(ticket*comissao,taxaMin);
        $('rReceita').textContent=brl(receitaAtend);
        const be=receitaAtend>0?Math.ceil(custoTotal/receitaAtend):0;
        $('rBE').textContent=int(be); $('rDia').textContent=(be/30).toFixed(1);

        // Meta
        const metaOrc=num('metaOrc');
        $('mCadEsp').textContent=int(num('cplEsp')>0?metaOrc/num('cplEsp'):0);
        $('mCadGui').textContent=int(num('cplGui')>0?metaOrc/num('cplGui'):0);

        // Google
        const gOrc=num('gOrc'),cpc=num('cpc'),conv=num('conv')/100,fecha=num('fecha')/100;
        const cpa=conv>0?cpc/conv:0, chamados=cpa>0?gOrc/cpa:0, pagos=chamados*fecha;
        const cliques=cpc>0?gOrc/cpc:0;
        $('gCpa').textContent=brl(cpa); $('gChamados').textContent=int(chamados);
        $('gPagos').textContent=int(pagos); $('gCustoPago').textContent=pagos>0?brl(gOrc/pagos):'—';

        // Anda sozinho
        const org=num('organico'), meta=num('metaOrg')||40;
        const indepPct=Math.min(100, meta>0?(org/meta*100):0);
        $('indepBar').style.width=indepPct+'%'; $('indepBar').textContent=Math.round(org)+'%';
        $('indepTxt').textContent='('+Math.round(org)+'% de '+Math.round(meta)+'% da meta)';
        const beSemMidia=receitaAtend>0?Math.ceil((num('infra')+num('midiaManut'))/receitaAtend):0;
        $('beSemMidia').textContent=int(beSemMidia);
        const andaSozinho = org>=meta;
        $('sozinhoVeredito').innerHTML = andaSozinho
            ? '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Independência atingida: com '+Math.round(org)+'% de chamados orgânicos você pode cortar mídia para manutenção e sustentar '+int(beSemMidia)+' atend./mês.</span>'
            : 'Faltam <strong>'+Math.round(meta-org)+' pontos</strong> de chamados orgânicos para o negócio andar sozinho (recompra + indicação + demanda trazida pelo prestador).';

        // Cidades
        const cidadeBE=[];
        document.querySelectorAll('.cidade-ticket').forEach(inp=>{
            const t=parseFloat(inp.value)||0, rec=Math.max(t*comissao,taxaMin);
            const beC=rec>0?Math.ceil(custoTotal/rec):0;
            const tr=inp.closest('tr');
            tr.querySelector('.c-rec').textContent=brl(rec);
            tr.querySelector('.c-be').textContent=int(beC);
            tr.querySelector('.c-dia').textContent=(beC/30).toFixed(1);
            cidadeBE.push(beC);
        });

        // Runway
        let caixa=0,pico=0; const cxArr=[],recArr=[],cusArr=[];
        document.querySelectorAll('.run-at').forEach(inp=>{
            const at=parseFloat(inp.value)||0, rec=at*receitaAtend, res=rec-custoTotal;
            caixa+=res; if(caixa<pico)pico=caixa;
            const tr=inp.closest('tr');
            tr.querySelector('.r-rec').textContent=brl(rec);
            tr.querySelector('.r-cus').textContent=brl(custoTotal);
            tr.querySelector('.r-res').textContent=brl(res);
            const cx=tr.querySelector('.r-cx'); cx.textContent=brl(caixa); cx.style.color=caixa<0?'#dc3545':'#198754';
            cxArr.push(Math.round(caixa)); recArr.push(Math.round(rec)); cusArr.push(Math.round(custoTotal));
        });
        $('caixaPico').textContent=brl(-pico);

        // Break-even do mês: usa o maior atendimento previsto na rampa como "atual"
        const atendMax=Math.max(...Array.from(document.querySelectorAll('.run-at')).map(i=>parseFloat(i.value)||0));
        const bePct=be>0?Math.min(100,atendMax/be*100):0;
        $('beBar').style.width=bePct+'%'; $('beBar').textContent=Math.round(bePct)+'%';
        $('beBar').className='progress-bar '+(bePct>=100?'bg-success':'bg-warning');
        $('beTxt').textContent='(pico de '+int(atendMax)+' de '+int(be)+' atend.)';

        // Charts
        if(chInd){ chInd.data.datasets[0].data=[org,Math.max(0,100-org)]; chInd.update(); }
        if(chRun){ chRun.data.datasets[0].data=cxArr; chRun.update(); }
        if(chCid){ chCid.data.datasets[0].data=cidadeBE; chCid.update(); }
        if(chFun){ chFun.data.datasets[0].data=[Math.round(cliques),Math.round(chamados),Math.round(pagos)]; chFun.update(); }
        if(chRC){ chRC.data.datasets[0].data=recArr; chRC.data.datasets[1].data=cusArr; chRC.update(); }
    }

    // ── CSV import ──
    function parseNum(s){
        if(s==null) return NaN;
        s=String(s).replace(/["'R$\s]/g,'').replace(/[^\d.,-]/g,'');
        if(s.indexOf(',')>-1 && s.indexOf('.')>-1){ s=s.replace(/\./g,'').replace(',','.'); }
        else if(s.indexOf(',')>-1){ s=s.replace(',','.'); }
        return parseFloat(s);
    }
    function importCsv(text, resKeys){
        const delim = text.indexOf(';')>-1 ? ';' : (text.indexOf('\t')>-1 ? '\t' : ',');
        const lines=text.split(/\r?\n/).filter(l=>l.trim());
        if(lines.length<2) return null;
        const hdr=lines[0].split(delim).map(h=>h.trim().toLowerCase());
        const find=keys=>hdr.findIndex(h=>keys.some(k=>h.includes(k)));
        const sc=find(['amount spent','valor gasto','valor usado','importe','gasto','custo','cost']);
        const rc=find(resKeys);
        if(sc<0||rc<0) return {error:'Não achei as colunas de gasto/resultado no arquivo.'};
        let spend=0,res=0;
        for(let i=1;i<lines.length;i++){ const c=lines[i].split(delim);
            const s=parseNum(c[sc]), r=parseNum(c[rc]);
            if(!isNaN(s))spend+=s; if(!isNaN(r))res+=r; }
        return {spend,res};
    }
    function handleCsv(inputId, msgId, resKeys, apply){
        const el=$(inputId); if(!el) return;
        el.addEventListener('change', function(){
            const f=el.files&&el.files[0]; if(!f) return;
            const fr=new FileReader();
            fr.onload=function(){ const out=importCsv(String(fr.result), resKeys);
                const msg=$(msgId);
                if(!out||out.error){ msg.textContent=(out&&out.error)||'Falha ao ler o arquivo.'; msg.style.color='#dc3545'; return; }
                apply(out); calc(); msg.style.color='#198754';
                msg.textContent='Importado: gasto '+brl(out.spend)+' · '+int(out.res)+' resultados.'; };
            fr.readAsText(f,'utf-8');
        });
    }
    handleCsv('metaCsv','metaCsvMsg',['results','resultados','leads','cadastros','registrations'], out=>{
        $('metaOrc').value=Math.round(out.spend);
        if(out.res>0) $('cplEsp').value=(out.spend/out.res).toFixed(2);
    });
    handleCsv('googleCsv','googleCsvMsg',['clicks','cliques','clics'], out=>{
        $('gOrc').value=Math.round(out.spend);
        if(out.res>0) $('cpc').value=(out.spend/out.res).toFixed(2);
    });

    document.addEventListener('input', function(e){
        if(e.target.matches('.plan-in, .cidade-ticket, .run-at')) calc();
    });
    initCharts();
    calc();
})();
</script>
