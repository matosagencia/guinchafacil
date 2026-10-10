<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$canonical = 'https://guinchafacil.com.br/parceiros/guinchos';
$description = 'Receba chamados de reboque 24h sem mensalidade. Cadastre seu guincho parceiro e amplie sua operação com oportunidades da GuinchaFácil.';
$faqs = [['Como recebo chamados?', 'Após a aprovação, a operação encaminha oportunidades compatíveis com sua região e capacidade.'], ['Existe mensalidade?', 'Não. Não há taxa de adesão nem mensalidade.'], ['Posso atender em mais de uma região?', 'Sim. Informe e ajuste regiões, horários e raio conforme sua capacidade.'], ['O cadastro garante entrada na rede?', 'Não. Todo parceiro passa por avaliação operacional antes de ser aprovado.'], ['Quais dados preciso enviar?', 'Nome do negócio, telefone de contato, CNPJ opcional e a identificação como guincho parceiro.'], ['Como funciona o repasse?', 'No pagamento online, o PIX é enviado após a conclusão, descontadas comissão e reserva de gateway.'], ['Qual o prazo de pagamento?', 'O exemplo operacional é D+7 após a conclusão e a confirmação da chave PIX cadastrada.'], ['Tem contrato de exclusividade?', 'Não. Você continua livre para atender sua própria carteira e outras oportunidades.'], ['Posso recusar chamados?', 'Sim. Antes do aceite, você pode recusar sem penalidade.'], ['Como funciona a fatura semanal?', 'Nos atendimentos pagos na chegada, a comissão é faturada para pagamento via PIX no ciclo informado.'], ['O que acontece se eu atrasar?', 'A conta pode ser bloqueada até regularizar e deixa de receber novos chamados.'], ['Posso aumentar meu raio depois?', 'Sim. A área de atendimento pode ser ajustada no painel.'], ['Como funciona o PIX?', 'O repasse é enviado à chave PIX cadastrada e validada no painel.'], ['Tem taxa de adesão?', 'Não. A entrada no programa não tem taxa de adesão.'], ['Como funciona a reputação?', 'Avaliações, aceites e cancelamentos compõem o histórico operacional.'], ['Como entro em contato com o suporte?', 'Use os canais disponibilizados no painel para suporte sobre operação, pagamentos e chamados.']];
$schemas = [['@context'=>'https://schema.org','@type'=>'Organization','name'=>'GuinchaFácil','url'=>'https://guinchafacil.com.br/','logo'=>'https://guinchafacil.com.br/public/assets/img/logo-48.png'], ['@context'=>'https://schema.org','@type'=>'Service','name'=>'Programa Guincho Parceiro GuinchaFácil','provider'=>['@type'=>'Organization','name'=>'GuinchaFácil'],'areaServed'=>['@type'=>'Country','name'=>'Brasil'],'serviceType'=>'Conexão de guinchos parceiros a oportunidades de reboque'], ['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>array_map(static fn(array $faq): array => ['@type'=>'Question','name'=>$faq[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>$faq[1]]], $faqs)]];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Seja um Guincho Parceiro | GuinchaFácil</title><meta name="description" content="<?= $e($description) ?>"><link rel="canonical" href="<?= $canonical ?>"><meta property="og:type" content="website"><meta property="og:locale" content="pt_BR"><meta property="og:site_name" content="GuinchaFácil"><meta property="og:title" content="Seja um Guincho Parceiro | GuinchaFácil"><meta property="og:description" content="<?= $e($description) ?>"><meta property="og:image" content="https://guinchafacil.com.br/public/assets/img/landing/roadside-help.jpg"><meta property="og:url" content="<?= $canonical ?>"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/tokens.css"><link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-landing.css"><?php foreach ($schemas as $schema): ?><script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script><?php endforeach; ?><style>
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
</style><style>
.gf-hero{background:linear-gradient(135deg,#041a09 0%,#0a3d1a 45%,#000 100%)!important;color:#fff}
.gf-hero .gf-eyebrow{color:#7dff96!important}
.gf-hero h1{color:#fff!important}
.gf-hero h1 em{color:#2fb34a!important;font-style:normal}
.gf-hero .gf-lead{color:rgba(255,255,255,.78)!important}
.gf-hero .gf-cta{background:linear-gradient(135deg,#2fb34a,#1f8a36)!important;color:#fff!important}
</style></head><body class="gf-landing"><header class="gf-nav"><a class="gf-brand" href="<?= $e($bp) ?>/"><img src="<?= $e($bp) ?>/public/assets/img/logo-48.png" alt="Logotipo GuinchaFácil" width="40" height="40"><span>Guincha<strong>Fácil</strong></span></a><nav aria-label="Navegação principal"><a href="<?= $e($bp) ?>/parceiros">Programa de parceiros</a><a class="gf-login" href="<?= $e($bp) ?>/login">Entrar</a></nav></header>
<main><section class="gf-hero" aria-labelledby="partner-title"><div><p class="gf-eyebrow">Para empresas de guincho</p><h1 id="partner-title">Receba chamados de reboque 24h. <em>Sem mensalidade.</em></h1><p class="gf-lead">Amplie sua operação com oportunidades encaminhadas para sua região e capacidade. Você conhece as condições antes de entrar na rede.</p><a class="gf-cta" href="<?= $e($bp) ?>/registro/guincho"><span><b>Quero ser guincho parceiro</b><small>Envie seu interesse para análise</small></span><span class="gf-arrow">→</span></a></div><div class="gf-card"><div class="gf-card-head"><span class="gf-dot"></span><b>Parceria sem atalhos</b></div><div class="gf-location"><span>1</span><div><small>Você informa sua operação</small><b>Região, capacidade e contato</b></div></div><div class="gf-location"><span>2</span><div><small>A equipe avalia o perfil</small><b>Entrada sujeita à aprovação</b></div></div><div class="gf-location"><span>3</span><div><small>Oportunidades compatíveis</small><b>Conexão organizada por demanda</b></div></div></div></section>
<section class="gf-section" aria-labelledby="como-funciona"><p class="gf-eyebrow">Como funciona</p><h2 id="como-funciona">Uma jornada simples para entrar na rede</h2><div class="gf-process"><article><i>01</i><h3>Envie seus dados</h3><p>Conte quem é sua empresa e como falar com você.</p></article><article><i>02</i><h3>Alinhe sua capacidade</h3><p>A equipe entende região, horários e tipo de atendimento.</p></article><article><i>03</i><h3>Receba a proposta</h3><p>As condições são explicadas com clareza antes da adesão.</p></article><article><i>04</i><h3>Atenda oportunidades</h3><p>Após aprovação, fique disponível para chamados compatíveis.</p></article></div></section>
<section class="gf-trust" aria-label="Vantagens de ser parceiro"><div><b>Mais oportunidades</b><span>Amplie sua presença sem investir em uma nova vitrine digital.</span></div><div><b>Operação orientada</b><span>Receba direcionamento sobre a jornada do motorista.</span></div><div><b>Sem mensalidade</b><span>Conheça a proposta comercial antes de decidir.</span></div><div><b>Rede confiável</b><span>Participe de uma plataforma focada em assistência veicular.</span></div></section>
<section class="gf-section" aria-labelledby="payment-title"><p class="gf-eyebrow">Pagamento</p><h2 id="payment-title">Como você é pago</h2><div class="gf-card" style="transform:none;padding:0;overflow:auto"><table class="gf-table"><thead><tr><th>Situação</th><th>O que acontece</th></tr></thead><tbody><tr><td>Cliente paga online</td><td>Você recebe via PIX em D+7 após a conclusão, descontadas a comissão da plataforma e a reserva de gateway.</td></tr><tr><td>Cliente paga na chegada</td><td>Você recebe o valor cheio do cliente. A comissão fica faturada e você paga em D+7 via PIX.</td></tr><tr><td>Cliente cancela antes do aceite</td><td>Sem penalidade para você.</td></tr><tr><td>Cliente cancela durante o atendimento</td><td>Retenção conforme regra da plataforma, para cobrir seu deslocamento já feito.</td></tr><tr><td>Você cancela sem motivo</td><td>Penalidade de reputação e pedido reaberto para outro parceiro.</td></tr></tbody></table></div></section>
<section class="gf-section" aria-labelledby="scenarios-title"><p class="gf-eyebrow">Cenários reais</p><h2 id="scenarios-title">E se...</h2><div class="gf-process gf-process-4"><article><i>01</i><h3>Eu não conseguir atender?</h3><p>Você pode recusar sem penalidade até aceitar. Depois do aceite, cancelar afeta a reputação.</p></article><article><i>02</i><h3>O cliente não pagar?</h3><p>A plataforma intermedia o pagamento online; o repasse segue o fluxo de PIX após a conclusão.</p></article><article><i>03</i><h3>O destino mudar?</h3><p>O valor é recalculado pela distância real e o cliente aprova a diferença antes.</p></article><article><i>04</i><h3>Eu não puder atender no horário?</h3><p>Você controla sua disponibilidade online/offline no painel.</p></article><article><i>05</i><h3>A fatura semanal não for paga?</h3><p>Sua conta é bloqueada até regularizar e não recebe novos chamados.</p></article><article><i>06</i><h3>Quanto custa para entrar?</h3><p>Zero. Sem taxa de adesão e sem mensalidade.</p></article></div></section>
<section class="gf-section" aria-labelledby="simulation-title">
  <p class="gf-eyebrow">Simulador</p>
  <h2 id="simulation-title">Quanto vocÃª pode ganhar?</h2>
  <p class="gf-muted">Ajuste os campos abaixo e veja a estimativa de repasse lÃ­quido. Os valores consideram a comissÃ£o da plataforma e a reserva de gateway.</p>
  <div class="gf-card" style="transform:none">
    <div class="gf-simulator" id="gfSimulatorGuincho">
      <div class="gf-sim-row">
        <label for="gfSimChamados"><b>Chamados por semana</b><small>Quantos reboques vocÃª atende em mÃ©dia?</small></label>
        <output id="gfSimChamadosOut">3</output>
        <input type="range" id="gfSimChamados" min="1" max="30" step="1" value="3" aria-labelledby="gfSimChamadosOut">
      </div>
      <div class="gf-sim-row">
        <label for="gfSimTicket"><b>Ticket mÃ©dio (R$)</b><small>Valor mÃ©dio do reboque na sua regiÃ£o.</small></label>
        <input type="number" id="gfSimTicket" min="50" max="800" step="10" value="180">
      </div>
      <div class="gf-sim-result">
        <div><small>LÃ­quido por chamado</small><b id="gfSimPorChamado">R$ 135,90</b></div>
        <div><small>LÃ­quido por semana</small><b id="gfSimSemana">R$ 407,70</b></div>
        <div><small>LÃ­quido por mÃªs</small><b id="gfSimMes">R$ 1.767,00</b></div>
      </div>
      <p class="gf-muted gf-sim-note">Valores ilustrativos. Variam por regiÃ£o, demanda e tipo de atendimento. NÃ£o hÃ¡ garantia de renda.</p>
    </div>
  </div>
</section>
<section class="gf-section" aria-labelledby="inside-title"><p class="gf-eyebrow">Operação</p><h2 id="inside-title">Como funciona por dentro</h2><div class="gf-process gf-process-4"><article><i>01</i><h3>Notificação</h3><p>Você recebe a notificação no painel e no celular.</p></article><article><i>02</i><h3>Conferência</h3><p>Vê distância, valor e destino antes de decidir.</p></article><article><i>03</i><h3>Aceite e status</h3><p>Aceita ou recusa; se aceitar, atualiza a caminho, no local e finalizado.</p></article><article><i>04</i><h3>Avaliação e PIX</h3><p>O cliente avalia e o repasse cai em D+7 na chave PIX cadastrada.</p></article></div></section>
<section class="gf-section" aria-labelledby="faq-title"><p class="gf-eyebrow">Dúvidas frequentes</p><h2 id="faq-title">Perguntas antes de participar</h2><div class="gf-process gf-process-4"><?php foreach ($faqs as $index => $faq): ?><article><i><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></i><h3><?= $e($faq[0]) ?></h3><p><?= $e($faq[1]) ?></p></article><?php endforeach; ?></div></section>
<section class="gf-section" id="cadastro" aria-labelledby="cadastro-title"><p class="gf-eyebrow">Próximo passo</p><h2 id="cadastro-title">Pronto para começar?</h2><p class="gf-muted">Crie sua conta de guincho parceiro em segundos. Depois de entrar, vocé completa os dados do veículo e envia a documentação.</p><a class="gf-cta" href="<?= $e($bp) ?>/registro/guincho"><span><b>Criar conta de guincho</b><small>Com Google ou e-mail</small></span><span class="gf-arrow">&rarr;</span></a></section></main><footer class="gf-footer"><span>&copy; <?= date('Y') ?> GuinchaFácil</span><div><a href="<?= $e($bp) ?>/parceiros">Programa de parceiros</a><a href="<?= $e($bp) ?>/">Página inicial</a></div></footer><script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
  var elChamados = document.getElementById('gfSimChamados');
  var elTicket = document.getElementById('gfSimTicket');
  var elChamadosOut = document.getElementById('gfSimChamadosOut');
  var elPorChamado = document.getElementById('gfSimPorChamado');
  var elSemana = document.getElementById('gfSimSemana');
  var elMes = document.getElementById('gfSimMes');
  if (!elChamados || !elTicket) return;

  var COMISSAO = 0.20;
  var RESERVA_GATEWAY = 0.045;

  function fmt(v){
    return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function calc(){
    var chamados = parseInt(elChamados.value, 10) || 0;
    var ticket = parseFloat(elTicket.value) || 0;

    var liquidoPorChamado = ticket * (1 - RESERVA_GATEWAY) * (1 - COMISSAO);
    var liquidoSemana = liquidoPorChamado * chamados;
    var liquidoMes = liquidoSemana * 4.33;

    elChamadosOut.textContent = chamados;
    elPorChamado.textContent = fmt(liquidoPorChamado);
    elSemana.textContent = fmt(liquidoSemana);
    elMes.textContent = fmt(liquidoMes);
  }

  elChamados.addEventListener('input', calc);
  elTicket.addEventListener('input', calc);
  calc();
})();
</script></body></html>
