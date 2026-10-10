<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$canonical = 'https://guinchafacil.com.br/parceiros/oficinas';
$description = 'Encha suas horas vagas com clientes qualificados. Cadastre sua oficina parceira e receba oportunidades locais pela GuinchaFácil.';
$faqs = [['Como funciona o orçamento?','Você envia pelo painel e o cliente aprova ou recusa.'],['E se o cliente não aprovar?','Sem penalidade.'],['E se eu precisar de peças?','Você compra direto do fornecedor; a plataforma não intermedia.'],['Como recebo?','PIX em D+7 após a conclusão.'],['Tem contrato de exclusividade?','Não.'],['Posso recusar chamados?','Sim.'],['Como funciona o repasse?','No pagamento online, o PIX considera a taxa da plataforma prevista.'],['Qual o prazo de pagamento?','O exemplo operacional é D+7 após a conclusão.'],['Como funciona a fatura semanal?','Quando aplicável, a comissão é faturada para pagamento via PIX no ciclo informado.'],['O que acontece se eu atrasar?','A conta pode ser bloqueada até regularizar.'],['Posso aumentar meu raio depois?','Sim, conforme sua capacidade e cidades atendidas.'],['Como funciona o PIX?','O repasse é enviado à chave PIX cadastrada no painel.'],['Tem taxa de adesão?','Não há taxa de adesão.'],['Como funciona a reputação?','Avaliações e qualidade dos atendimentos compõem seu histórico.'],['Como entro em contato com o suporte?','Use os canais disponíveis no painel.']];
$schemas = [['@context'=>'https://schema.org','@type'=>'Organization','name'=>'GuinchaFácil','url'=>'https://guinchafacil.com.br/','logo'=>'https://guinchafacil.com.br/public/assets/img/logo-48.png'],['@context'=>'https://schema.org','@type'=>'Service','name'=>'Programa Oficina Parceira GuinchaFácil','provider'=>['@type'=>'Organization','name'=>'GuinchaFácil'],'areaServed'=>['@type'=>'Country','name'=>'Brasil'],'serviceType'=>'Conexão de oficinas parceiras a clientes qualificados'],['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>array_map(static fn(array $faq): array => ['@type'=>'Question','name'=>$faq[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>$faq[1]]], $faqs)]];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seja uma Oficina Parceira | GuinchaFácil</title><meta name="description" content="<?= $e($description) ?>"><link rel="canonical" href="<?= $canonical ?>"><meta property="og:type" content="website"><meta property="og:locale" content="pt_BR"><meta property="og:site_name" content="GuinchaFácil"><meta property="og:title" content="Seja uma Oficina Parceira | GuinchaFácil"><meta property="og:description" content="<?= $e($description) ?>"><meta property="og:image" content="https://guinchafacil.com.br/public/assets/img/landing/mechanic-help.jpg"><meta property="og:url" content="<?= $canonical ?>"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/tokens.css"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-landing.css"><style>.gf-table{width:100%;min-width:620px;border-collapse:collapse}.gf-table th,.gf-table td{padding:16px;text-align:left;vertical-align:top;border-bottom:1px solid var(--gf-line)}.gf-table th{background:var(--gf-soft);color:var(--gf-dark)}.gf-process.gf-process-4{grid-template-columns:repeat(4,minmax(0,1fr))}@media(max-width:900px){.gf-process.gf-process-4{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:780px){.gf-process.gf-process-4{grid-template-columns:1fr}}</style><?php foreach ($schemas as $schema): ?><script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script><?php endforeach; ?><style>
.gf-simulator{display:flex;flex-direction:column;gap:18px;padding:8px 0}
.gf-sim-row{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center}
.gf-sim-row label{display:flex;flex-direction:column;gap:2px}
.gf-sim-row label b{font-size:1rem;color:var(--gf-dark)}
.gf-sim-row label small{font-size:.82rem;color:var(--gf-muted)}
.gf-sim-row input[type="range"]{grid-column:1/-1;width:100%;accent-color:#2fb34a}
.gf-sim-row input[type="number"]{width:120px;padding:8px 12px;border:1px solid var(--gf-line);border-radius:8px;font-size:1rem}
.gf-sim-row output{font-size:1.4rem;font-weight:800;color:#2fb34a;min-width:60px;text-align:right}
.gf-sim-result{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:18px;background:var(--gf-soft);border-radius:12px}
.gf-sim-result>div{display:flex;flex-direction:column;gap:2px}
.gf-sim-result small{font-size:.78rem;color:var(--gf-muted)}
.gf-sim-result b{font-size:1.3rem;color:var(--gf-dark)}
.gf-sim-note{margin:0;font-size:.8rem}
@media(max-width:780px){.gf-sim-result{grid-template-columns:1fr}}
</style></head><body class="gf-landing"><header class="gf-nav"><a class="gf-brand" href="<?= $e($bp) ?>/"><img src="<?= $e($bp) ?>/public/assets/img/logo-48.png" alt="Logotipo GuinchaFácil" width="40" height="40"><span>Guincha<strong>Fácil</strong></span></a><nav aria-label="Navegação principal"><a href="<?= $e($bp) ?>/parceiros">Programa de parceiros</a><a class="gf-login" href="<?= $e($bp) ?>/login">Entrar</a></nav></header>
<main><section class="gf-hero" aria-labelledby="partner-title"><div><p class="gf-eyebrow">Para oficinas</p><h1 id="partner-title">Encha suas horas vagas <em>com clientes qualificados.</em></h1><p class="gf-lead">A GuinchaFácil conecta motoristas que precisam de atendimento à rede de oficinas parceiras. Mostre seu interesse e conheça a operação.</p><a class="gf-cta" href="#interesse"><span><b>Quero ser oficina parceira</b><small>Envie seu interesse para análise</small></span><span class="gf-arrow">→</span></a></div><div class="gf-card"><div class="gf-card-head"><span class="gf-dot"></span><b>Como funciona</b></div><div class="gf-location"><span>1</span><div><small>Você demonstra interesse</small><b>Sem cadastro automático</b></div></div><div class="gf-location"><span>2</span><div><small>A equipe entra em contato</small><b>Operação explicada com clareza</b></div></div><div class="gf-location"><span>3</span><div><small>O perfil é avaliado</small><b>Entrada sujeita à aprovação</b></div></div></div></section>
<section class="gf-trust"><div><b>Clientes qualificados</b><span>Oportunidades encaminhadas de acordo com a operação local.</span></div><div><b>Risco sob controle</b><span>Condições e fluxo são explicados antes da aprovação.</span></div><div><b>Rede local</b><span>Construa presença digital na jornada de socorro veicular.</span></div></section>
<section class="gf-section" aria-labelledby="payment-title"><p class="gf-eyebrow">Pagamento</p><h2 id="payment-title">Como você é pago</h2><div class="gf-card" style="transform:none;padding:0;overflow:auto"><table class="gf-table"><thead><tr><th>Situação</th><th>O que acontece</th></tr></thead><tbody><tr><td>Cliente paga online</td><td>Você recebe via PIX em D+7 após a conclusão, descontada a taxa da plataforma e a reserva de gateway.</td></tr><tr><td>Cliente paga na chegada</td><td>Você recebe o valor cheio do cliente. A comissão fica faturada e é paga em D+7 via PIX.</td></tr><tr><td>Cliente cancela antes do aceite</td><td>Sem penalidade para você.</td></tr><tr><td>Cliente cancela durante o atendimento</td><td>Retenção conforme a regra da plataforma, considerando seu deslocamento já feito.</td></tr><tr><td>Você cancela sem motivo</td><td>Penalidade de reputação e oportunidade reaberta para outro parceiro.</td></tr></tbody></table></div></section>
<section class="gf-section" aria-labelledby="scenarios-title"><p class="gf-eyebrow">Cenários reais</p><h2 id="scenarios-title">E se...</h2><div class="gf-process gf-process-4"><article><i>01</i><h3>Como funciona o orçamento?</h3><p>Você envia pelo painel e o cliente aprova ou recusa.</p></article><article><i>02</i><h3>O cliente não aprovar?</h3><p>Sem penalidade.</p></article><article><i>03</i><h3>Eu precisar de peças?</h3><p>Você compra direto do fornecedor; a plataforma não intermedia.</p></article><article><i>04</i><h3>Como recebo?</h3><p>PIX em D+7 após conclusão.</p></article><article><i>05</i><h3>Tem exclusividade?</h3><p>Não. Sua oficina continua livre para atender sua carteira.</p></article><article><i>06</i><h3>Posso recusar chamados?</h3><p>Sim, conforme sua disponibilidade operacional.</p></article></div></section>
<section class="gf-section" aria-labelledby="simulation-title">
  <p class="gf-eyebrow">Simulador</p>
  <h2 id="simulation-title">Quanto sua oficina pode faturar?</h2>
  <p class="gf-muted">Ajuste os campos abaixo e veja a estimativa de repasse líquido. Os valores consideram a taxa da plataforma.</p>
  <div class="gf-card" style="transform:none">
    <div class="gf-simulator" id="gfSimulatorOficina">
      <div class="gf-sim-row">
        <label for="gfSimOrcamentos"><b>Orçamentos por semana</b><small>Quantos atendimentos vocÉ fecha em mÉdia?</small></label>
        <output id="gfSimOrcamentosOut">5</output>
        <input type="range" id="gfSimOrcamentos" min="1" max="30" step="1" value="5" aria-labelledby="gfSimOrcamentosOut">
      </div>
      <div class="gf-sim-row">
        <label for="gfSimTicket"><b>Ticket mÉdio (R\$)</b><small>Valor mÉdio do serviço na sua regiÁo.</small></label>
        <input type="number" id="gfSimTicket" min="100" max="2000" step="10" value="350">
      </div>
      <div class="gf-sim-result">
        <div><small>Líquido por atendimento</small><b id="gfSimPorAtendimento">R\$ 297,50</b></div>
        <div><small>Líquido por semana</small><b id="gfSimSemana">R\$ 1.487,50</b></div>
        <div><small>Líquido por mÉs</small><b id="gfSimMes">R\$ 6.440,88</b></div>
      </div>
      <p class="gf-muted gf-sim-note">Valores ilustrativos. Variam por regiÁo, demanda e tipo de serviço. NÁo hÁ garantia de renda.</p>
    </div>
  </div>
</section>
<section class="gf-section" aria-labelledby="inside-title"><p class="gf-eyebrow">Operação</p><h2 id="inside-title">Como funciona por dentro</h2><div class="gf-process gf-process-4"><article><i>01</i><h3>Notificação</h3><p>Você recebe a notificação no painel e no celular.</p></article><article><i>02</i><h3>Conferência</h3><p>Confere dados do chamado, distância, valor e destino.</p></article><article><i>03</i><h3>Status</h3><p>Aceita ou recusa e atualiza o atendimento pelo painel.</p></article><article><i>04</i><h3>Avaliação e PIX</h3><p>O cliente avalia e o repasse cai em D+7 na chave PIX cadastrada.</p></article></div></section>
<section class="gf-section" aria-labelledby="faq-title"><p class="gf-eyebrow">Dúvidas frequentes</p><h2 id="faq-title">Perguntas antes de participar</h2><div class="gf-process gf-process-4"><?php foreach ($faqs as $index => $faq): ?><article><i><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></i><h3><?= $e($faq[0]) ?></h3><p><?= $e($faq[1]) ?></p></article><?php endforeach; ?></div></section>
<section class="gf-section" id="interesse" aria-labelledby="interest-title"><p class="gf-eyebrow">Próximo passo</p><h2 id="interest-title">Quer conversar sobre ser parceiro?</h2><p class="gf-muted">Preencha o formulário. Seu contato entra na fila de prospecção; isso não cria uma conta nem aprova a oficina automaticamente.</p><?php if ($enviado): ?><p class="gf-muted"><strong>Interesse recebido.</strong> Nossa equipe entrará em contato.</p><?php elseif ($erro !== ''): ?><p class="gf-muted">Não foi possível registrar o interesse agora. Confira os dados e tente novamente.</p><?php endif; ?><form method="post" action="<?= $e($bp) ?>/parceiros/interesse" class="gf-process"><input type="hidden" name="csrf_token" value="<?= $e((string)$csrf_token) ?>"><input type="hidden" name="tipo_parceiro" value="oficina"><label>Nome da oficina<input required maxlength="180" name="nome_oficina"></label><label>WhatsApp / telefone<input required maxlength="30" name="telefone" inputmode="tel"></label><label>CNPJ <small>(opcional)</small><input maxlength="30" name="cnpj" inputmode="numeric"></label><button class="gf-cta" type="submit"><span><b>Quero falar com a equipe</b><small>Interesse sujeito à análise</small></span><span class="gf-arrow">→</span></button></form></section></main><footer class="gf-footer"><span>&copy; <?= date('Y') ?> GuinchaFácil</span><div><a href="<?= $e($bp) ?>/parceiros">Programa de parceiros</a><a href="<?= $e($bp) ?>/">Página inicial</a></div></footer><script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
  var elOrc = document.getElementById('gfSimOrcamentos');
  var elTicket = document.getElementById('gfSimTicket');
  var elOrcOut = document.getElementById('gfSimOrcamentosOut');
  var elPorAt = document.getElementById('gfSimPorAtendimento');
  var elSemana = document.getElementById('gfSimSemana');
  var elMes = document.getElementById('gfSimMes');
  if (!elOrc || !elTicket) return;

  var TAXA = 0.15;

  function fmt(v){
    return 'R\$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function calc(){
    var orc = parseInt(elOrc.value, 10) || 0;
    var ticket = parseFloat(elTicket.value) || 0;
    var porAt = ticket * (1 - TAXA);
    var semana = porAt * orc;
    var mes = semana * 4.33;
    elOrcOut.textContent = orc;
    elPorAt.textContent = fmt(porAt);
    elSemana.textContent = fmt(semana);
    elMes.textContent = fmt(mes);
  }

  elOrc.addEventListener('input', calc);
  elTicket.addEventListener('input', calc);
  calc();
})();
</script></body></html>
