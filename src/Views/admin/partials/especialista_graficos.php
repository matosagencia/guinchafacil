<?php
$finRows = $especialista['financeiro'] ?? [];
$finTipos = ['repasse_especialista'=>0.0,'cobranca_cliente'=>0.0,'repasse_guincho'=>0.0,'outros'=>0.0];
$finStatus = ['confirmado'=>0.0,'pendente'=>0.0,'processando'=>0.0,'outros'=>0.0];
foreach ($finRows as $row) {
    $tipo=(string)($row['tipo']??'outros'); $valor=(float)($row['valor']??0);
    $finTipos[$tipo]=($finTipos[$tipo]??0)+$valor;
    $status=(string)($row['status']??'outros'); $finStatus[$status]=($finStatus[$status]??0)+$valor;
}
$finMax=max(1.0,...array_values($finTipos));
$evResumo=[]; foreach(($especialista['evidencias']??[]) as $ev){$k=(string)($ev['evento']??'outro');$evResumo[$k]=($evResumo[$k]??0)+1;}
$evMax=max(1,...array_values($evResumo));
?>
<section class="border rounded p-3 mb-3" aria-label="Indicadores do especialista" style="color:#263238;background:#fff">
  <div class="d-flex align-items-center gap-2 mb-3"><span style="color:#f0a83b;font-size:1.35rem;letter-spacing:2px"><?= str_repeat('★',(int)round((float)($especialista['reputacao']??0))) . str_repeat('☆',5-(int)round((float)($especialista['reputacao']??0))) ?></span><strong><?= number_format((float)($especialista['reputacao']??0),1,',','.') ?>/5</strong><span class="small text-muted">(<?= (int)($especialista['total_avaliacoes']??0) ?> avaliações)</span></div>
  <div class="d-flex justify-content-between align-items-center mb-2"><strong class="small">Indicadores inteligentes</strong><span class="small text-muted">Atualizado ao abrir o perfil</span></div>
  <div class="row g-2 small mb-3">
    <div class="col-4"><div class="border rounded p-2"><span class="text-muted d-block">Movimentado</span><strong>R$ <?= number_format(array_sum($finTipos),2,',','.') ?></strong></div></div>
    <div class="col-4"><div class="border rounded p-2"><span class="text-muted d-block">Confirmado</span><strong>R$ <?= number_format($finStatus['confirmado']??0,2,',','.') ?></strong></div></div>
    <div class="col-4"><div class="border rounded p-2"><span class="text-muted d-block">Evidências</span><strong><?= array_sum($evResumo) ?></strong></div></div>
  </div>
  <?php foreach($finTipos as $tipo=>$valor): if($valor<=0)continue; ?><div class="small mb-2"><div class="d-flex justify-content-between"><span><?= htmlspecialchars(ucwords(str_replace('_',' ',$tipo))) ?></span><strong>R$ <?= number_format($valor,2,',','.') ?></strong></div><div style="height:6px;background:#e9ecef;border-radius:6px"><div style="width:<?= (int)round($valor/$finMax*100) ?>%;height:100%;background:#f0a83b;border-radius:6px"></div></div></div><?php endforeach; ?>
  <?php if($evResumo): ?><div class="small text-muted mt-2 mb-1">Distribuição de evidências</div><?php foreach($evResumo as $evento=>$qtd): ?><div class="small mb-1"><div class="d-flex justify-content-between"><span><?= htmlspecialchars(str_replace('_',' ',$evento)) ?></span><strong><?= (int)$qtd ?></strong></div><div style="height:5px;background:#e9ecef;border-radius:5px"><div style="width:<?= (int)round($qtd/$evMax*100) ?>%;height:100%;background:#6f42c1;border-radius:5px"></div></div></div><?php endforeach; ?><?php endif; ?>
</section>
