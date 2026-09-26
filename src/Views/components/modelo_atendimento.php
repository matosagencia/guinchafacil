<?php
$decisionAudience = $decisionAudience ?? 'cliente';
$decisionTitle = $decisionAudience === 'admin' ? 'Motor de atendimento do pedido' : 'Você escolhe o caminho mais adequado';
$decisionLead = $decisionAudience === 'admin'
    ? 'A mesma sequência orienta cliente, operação e parceiros sem misturar diagnóstico, reboque e cobrança.'
    : 'A GuinchaFácil organiza o chamado em quatro passos claros, do endereço à solução.';
?>
<section class="gf-decision-model" aria-label="Motor de atendimento">
    <div class="gf-decision-model__intro">
        <span class="gf-decision-model__eyebrow"><i class="fas fa-route me-1"></i>Fluxo GuinchaFácil</span>
        <h2><?= htmlspecialchars($decisionTitle, ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($decisionLead, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="gf-decision-model__steps">
        <article><span>1</span><div><strong>Localização</strong><small>Rua, número e cidade confirmados no mapa.</small></div></article>
        <article><span>2</span><div><strong>Situação</strong><small>O cliente explica o que precisa sem procurar termos técnicos.</small></div></article>
        <article><span>3</span><div><strong>Veículo</strong><small>Carro, moto, SUV ou utilitário para escolher o prestador certo.</small></div></article>
        <article><span>4</span><div><strong>Confirmar</strong><small>Atendimento local, oficina parceira ou reboque com cotação.</small></div></article>
    </div>
</section>
<style>
.gf-decision-model{margin:1rem 0;padding:1rem 1.1rem;border:1px solid rgba(249,115,22,.35);border-radius:16px;background:linear-gradient(135deg,rgba(249,115,22,.09),rgba(255,255,255,.04))}.gf-decision-model__intro{margin-bottom:.85rem}.gf-decision-model__eyebrow{font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;color:#d85c0b;font-weight:700}.gf-decision-model h2{font-size:1.1rem;margin:.3rem 0}.gf-decision-model p{margin:0;color:var(--theme-muted,#667085);font-size:.88rem}.gf-decision-model__steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}.gf-decision-model__steps article{display:flex;gap:.55rem;padding:.7rem;border-radius:12px;background:rgba(255,255,255,.75);border:1px solid rgba(0,0,0,.08)}.gf-decision-model__steps article>span{display:grid;place-items:center;flex:0 0 1.55rem;height:1.55rem;border-radius:50%;background:#f97316;color:#fff;font-weight:700}.gf-decision-model__steps strong,.gf-decision-model__steps small{display:block}.gf-decision-model__steps strong{font-size:.86rem}.gf-decision-model__steps small{margin-top:.2rem;color:var(--theme-muted,#667085);line-height:1.35}@media(max-width:900px){.gf-decision-model__steps{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:767px){.gf-decision-model__steps{grid-template-columns:1fr}}
</style>
