<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/PixService.php';
require_once __DIR__ . '/PixFaturaService.php';
require_once __DIR__ . '/Logger.php';

final class FaturaService
{
    public static function fecharCiclo(DateTimeImmutable $cicloInicio): int
    {
        $inicio = $cicloInicio->setTime(0, 0); $fim = $inicio->modify('+7 days'); $pdo = getPDO();
        $q = $pdo->prepare("SELECT guincho_id parceiro_id, SUM(CASE WHEN comissao_tipo='faturada' THEN comissao_valor ELSE 0 END) total_faturado, SUM(CASE WHEN comissao_tipo='descontada' THEN valor_liquido_parceiro ELSE 0 END) total_repasse FROM pedidos WHERE status='concluido' AND guincho_id IS NOT NULL AND comissao_valor IS NOT NULL AND fatura_id IS NULL AND atualizado_em >= ? AND atualizado_em < ? GROUP BY guincho_id");
        $q->execute([$inicio->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s')]); $criados = 0;
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $grupo) {
            $pdo->beginTransaction();
            try {
                $saldo = round((float)$grupo['total_repasse'] - (float)$grupo['total_faturado'], 2);
                $insert = $pdo->prepare("INSERT INTO faturas_parceiro (parceiro_tipo,parceiro_id,ciclo_inicio,ciclo_fim,total_faturado,total_repasse,saldo,vencimento_em) VALUES ('guincho',?,?,?,?,?,?,?)");
                $insert->execute([(int)$grupo['parceiro_id'],$inicio->format('Y-m-d H:i:s'),$fim->modify('-1 second')->format('Y-m-d H:i:s'),$grupo['total_faturado'],$grupo['total_repasse'],$saldo,$fim->modify('+7 days')->format('Y-m-d H:i:s')]);
                $faturaId=(int)$pdo->lastInsertId();
                $itens=$pdo->prepare("SELECT id,custo_final,custo_estimado,comissao_tipo,comissao_valor,valor_liquido_parceiro FROM pedidos WHERE status='concluido' AND guincho_id=? AND comissao_valor IS NOT NULL AND fatura_id IS NULL AND atualizado_em >= ? AND atualizado_em < ?");
                $itens->execute([(int)$grupo['parceiro_id'],$inicio->format('Y-m-d H:i:s'),$fim->format('Y-m-d H:i:s')]);
                $add=$pdo->prepare('INSERT INTO faturas_itens (fatura_id,pedido_id,comissao_tipo,valor_total,comissao_valor,valor_liquido_parceiro,saldo_item) VALUES (?,?,?,?,?,?,?)'); $link=$pdo->prepare('UPDATE pedidos SET fatura_id=? WHERE id=? AND fatura_id IS NULL');
                foreach($itens->fetchAll(PDO::FETCH_ASSOC) as $i){$itemSaldo=$i['comissao_tipo']==='faturada'?-(float)$i['comissao_valor']:(float)$i['valor_liquido_parceiro'];$add->execute([$faturaId,$i['id'],$i['comissao_tipo'],(float)($i['custo_final']??$i['custo_estimado']),$i['comissao_valor'],$i['valor_liquido_parceiro'],$itemSaldo]);$link->execute([$faturaId,$i['id']]);}
                $pdo->commit(); $criados++;
                $pix=PixService::gerar($faturaId,abs($saldo),$saldo>0?'plataforma_paga_parceiro':'parceiro_paga_plataforma');
                if($pix['ok']??false){$pdo->prepare('UPDATE faturas_parceiro SET pix_qrcode=?,pix_copia_cola=?,pix_transacao_id=? WHERE id=? AND pix_qrcode IS NULL')->execute([$pix['qrcode'],$pix['copia_cola'],$pix['transacao_id'],$faturaId]);}else{Logger::log(Logger::LEVEL_ERROR,__CLASS__,__FUNCTION__,'pix_fatura','FATURA-PIX-001: falha ao gerar PIX.',['fatura_id'=>$faturaId,'erro'=>$pix['erro']??'']);}
            } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Logger::exception(__CLASS__,__FUNCTION__,'fechar_ciclo',$e,['parceiro_id'=>$grupo['parceiro_id']]);throw $e;}
        } return $criados;
    }
    public static function marcarPaga(int $faturaId,string $transacaoId,string $metodo): bool
    { if($faturaId<1||trim($transacaoId)==='')return false;$pdo=getPDO();try{$pdo->beginTransaction();$q=$pdo->prepare('SELECT saldo FROM faturas_parceiro WHERE id=?'.self::lock($pdo));$q->execute([$faturaId]);$f=$q->fetch(PDO::FETCH_ASSOC);if(!$f){$pdo->rollBack();return false;}$seen=$pdo->prepare('SELECT id FROM faturas_pagamentos WHERE transacao_id=?');$seen->execute([$transacaoId]);if($seen->fetchColumn()){$pdo->rollBack();return true;}$pdo->prepare('INSERT INTO faturas_pagamentos (fatura_id,transacao_id,metodo,valor) VALUES (?,?,?,?)')->execute([$faturaId,$transacaoId,$metodo,abs((float)$f['saldo'])]);$pdo->prepare("UPDATE faturas_parceiro SET status='paga',paga_em=NOW(),pix_transacao_id=? WHERE id=? AND status<>'paga'")->execute([$transacaoId,$faturaId]);$pdo->commit();return true;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();Logger::exception(__CLASS__,__FUNCTION__,'marcar_paga',$e,['fatura_id'=>$faturaId]);return false;} }
    public static function bloquearPorVencimento(): int {$s=getPDO()->prepare("UPDATE faturas_parceiro SET status='bloqueada' WHERE status='aberta' AND vencimento_em<NOW()");$s->execute();return $s->rowCount();}
    public static function desbloquear(int $faturaId): bool {$s=getPDO()->prepare("UPDATE faturas_parceiro SET status='aberta' WHERE id=? AND status='bloqueada'");$s->execute([$faturaId]);return $s->rowCount()===1;}
    private static function lock(PDO $pdo):string{return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';}
}
