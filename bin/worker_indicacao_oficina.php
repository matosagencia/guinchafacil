<?php
declare(strict_types=1);
// Worker cron: reconcilia pedidos concluídos que possuem indicação de oficina.
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../src/Services/IndicacaoOficinaService.php';
$pdo=getPDO();
if(!IndicacaoOficinaService::ativo()){echo "monetizacao_oficinas_ativo=0\n";exit(0);}
$rows=$pdo->query("SELECT i.pedido_id FROM pedido_indicacoes_oficina i JOIN pedidos p ON p.id=i.pedido_id WHERE p.status='concluido' AND i.status IN ('SELECIONADA','EM_TRANSITO') ORDER BY i.id ASC LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
$ok=0;$fail=0;foreach($rows as $pedidoId){try{IndicacaoOficinaService::processarEntrega((int)$pedidoId);$ok++;}catch(Throwable $e){$fail++;error_log('[IndicacaoOficinaWorker] pedido '.$pedidoId.': '.$e->getMessage());}}
echo "processados={$ok} falhas={$fail}\n";