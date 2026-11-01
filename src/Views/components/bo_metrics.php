<?php
/**
 * Barra com as oito métricas da Central Lovable.
 * O CONTROLLER deve atribuir $metricasOperacionais (array), sem dados mockados.
 * Valores ausentes aparecem como travessão — nunca como 0 ou "online" presumido.
 */
if (!function_exists('gfBoH') || !function_exists('gfBoIcon')) {
    throw new RuntimeException('bo_metrics requer backoffice_shell.php ou seus helpers');
}
$metricasOperacionais = $metricasOperacionais ?? [];
$campos = [
  'pedidos_ativos' => ['Pedidos ativos','layout-dashboard'],
  'sob_prestador' => ['Sob prestador','user-round'],
  'a_caminho' => ['A caminho','truck'],
  'em_atendimento' => ['Em atendimento','wrench'],
  'alertas_criticos' => ['Alertas críticos','bell'],
  'guinchos_online' => ['Guinchos online','truck'],
  'receita_hoje' => ['Receita hoje','wallet'],
  'eta_medio' => ['ETA médio','history'],
];
?>
<section class="bo-metrics" aria-label="Indicadores operacionais">
<?php foreach ($campos as $chave => [$titulo,$icone]):
  $item = $metricasOperacionais[$chave] ?? [];
  $valor = is_array($item) ? ($item['valor'] ?? null) : $item;
  $texto = $valor === null ? '—' : (string)$valor;
  $detalhe = is_array($item) ? (string)($item['detalhe'] ?? '') : '';
  // Se não houve prova de heartbeat, não apresentar online nem número herdado.
  if ($chave === 'guinchos_online' && empty($item['heartbeat_verificado'])) {
      $texto = '—'; $detalhe = 'Presença não verificada';
  }
?>
  <div class="bo-metric<?= $chave === 'alertas_criticos' ? ' bo-alert-metric' : '' ?>">
    <span><?= gfBoH($titulo) ?><?= gfBoIcon($icone,14) ?></span>
    <strong><?= gfBoH($texto) ?></strong>
    <small><?= gfBoH($detalhe) ?></small>
  </div>
<?php endforeach; ?>
</section>
