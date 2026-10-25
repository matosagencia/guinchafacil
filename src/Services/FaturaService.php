<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/PixService.php';
require_once __DIR__ . '/PixFaturaService.php';
require_once __DIR__ . '/Logger.php';

final class FaturaService
{
    public static function registrarRecebimentoDiretoExato(object $receipt): array
    {
        $data=method_exists($receipt,'data')?$receipt->data():null; $pdo=getPDO();
        if (!is_array($data) || count($data)!==5) return ['ok'=>false,'erro'=>'FIN_DIRECT_RECEIPT_CONTRACT_REQUIRED'];
        [$order,$partner,$amount,$reference,$receivedAt]=$data; $order=(int)$order; $partner=(int)$partner; $amount=number_format((float)$amount,2,'.',''); $reference=trim((string)$reference);
        if ($order<1 || $partner<1 || (float)$amount<=0 || $reference==='') return ['ok'=>false,'erro'=>'FIN_DIRECT_RECEIPT_INVALID'];
        try { $pdo->beginTransaction();
            $q=$pdo->prepare('SELECT p.*,o.guincho_id,o.status order_status,o.forma_pagamento_escolhida,o.attendance_mode,o.fatura_id,o.comissao_tipo,o.comissao_valor,o.custo_final,o.custo_estimado FROM pagamentos p JOIN pedidos o ON o.id=p.pedido_id WHERE o.id=? FOR UPDATE');$q->execute([$order]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['guincho_id']!==$partner || $row['order_status']!=='concluido' || ($row['attendance_mode']??'')!=='TOWING' || ($row['forma_pagamento_escolhida']??'')!=='pago_na_chegada' || $row['metodo']!=='dinheiro' || $row['status']!=='pendente' || $row['fatura_id']!==null || $row['comissao_valor']!==null) throw new DomainException('FIN_DIRECT_NOT_ELIGIBLE');
            if (number_format((float)$row['valor_total'],2,'.','')!==$amount) throw new DomainException('FIN_DIRECT_AMOUNT_MISMATCH');
            $seen=$pdo->prepare('SELECT pedido_id FROM pagamentos WHERE id_externo=? FOR UPDATE');$seen->execute([$reference]);$used=$seen->fetchColumn();
            if ($used!==false && (int)$used!==$order) throw new DomainException('FIN_DIRECT_REFERENCE_REUSED');
            if ($used!==false) {$pdo->commit();return ['ok'=>true,'duplicate_verified'=>true,'order_id'=>$order];}
            $commission=number_format(round((float)$amount*0.08,2),2,'.','');
            $pdo->prepare("UPDATE pedidos SET custo_final=?,valor_final=?,comissao_tipo='faturada',comissao_valor=?,valor_liquido_parceiro=? WHERE id=?")->execute([$amount,$amount,$commission,$amount,$order]);
            $pdo->prepare("UPDATE pagamentos SET status='aprovado',id_externo=?,valor_guincho=?,valor_plataforma=?,pago_guincho=1,data_pagamento=?,data_pagamento_guincho=?,payload_webhook=? WHERE id=?")->execute([$reference,$amount,$commission,$receivedAt,$receivedAt,json_encode(['version'=>'R11AO','receipt'=>$data,'rate_bp'=>800],JSON_THROW_ON_ERROR),$row['id']]);
            $pdo->prepare("INSERT INTO fin_bilateral_eventos(idempotency_key,parceiro_tipo,parceiro_id,pedido_id,pagamento_id,tipo_evento,canal,valor_centavos,referencia_tipo,referencia_id,estado,competencia_em,confirmado_em) VALUES (?,'guincho',?,?,?,'debito_comissao','parceiro',?,'r11ao_direct',?,'confirmado',?,NOW())")->execute(['r11ao_direct:'.$order,$partner,$order,$row['id'],(int)round((float)$commission*100),$reference,$receivedAt]);
            $pdo->commit(); return ['ok'=>true,'duplicate_verified'=>false,'order_id'=>$order];
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['ok'=>false,'erro'=>$e->getMessage()];}
    }

    public static function fecharCicloParceiroExato(int $partner,DateTimeImmutable $start): array
    {
        $pdo=getPDO();$end=$start->modify('+7 days');
        try{$pdo->beginTransaction();$q=$pdo->prepare("SELECT p.id,p.custo_final,p.custo_estimado,p.comissao_tipo,p.comissao_valor,p.valor_liquido_parceiro FROM pedidos p WHERE p.guincho_id=? AND p.status='concluido' AND p.fatura_id IS NULL AND p.atualizado_em>=? AND p.atualizado_em<? FOR UPDATE");$q->execute([$partner,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')]);$items=$q->fetchAll(PDO::FETCH_ASSOC);if(!$items){$pdo->commit();return ['ok'=>true,'created'=>false,'invoice_id'=>null];}$gross=0;$total=0;foreach($items as $i){$gross+=(float)($i['custo_final']??$i['custo_estimado']);$total+=(float)($i['comissao_tipo']==='faturada'?$i['comissao_valor']:$i['valor_liquido_parceiro']);}$ins=$pdo->prepare("INSERT INTO faturas_parceiro(parceiro_tipo,parceiro_id,ciclo_inicio,ciclo_fim,total_faturado,total_repasse,saldo,vencimento_em) VALUES('guincho',?,?,?,?,?,?,?)");$ins->execute([$partner,$start->format('Y-m-d H:i:s'),$end->modify('-1 second')->format('Y-m-d H:i:s'),$total,$gross,$total-$gross,$end->modify('+7 days')->format('Y-m-d H:i:s')]);$id=(int)$pdo->lastInsertId();$add=$pdo->prepare('INSERT INTO faturas_itens(fatura_id,pedido_id,comissao_tipo,valor_total,comissao_valor,valor_liquido_parceiro,saldo_item) VALUES(?,?,?,?,?,?,?)');foreach($items as $i){$add->execute([$id,$i['id'],$i['comissao_tipo'],(float)($i['custo_final']??$i['custo_estimado']),$i['comissao_valor'],$i['valor_liquido_parceiro'],(float)($i['comissao_tipo']==='faturada'?-($i['comissao_valor']):$i['valor_liquido_parceiro'])]);$pdo->prepare('UPDATE pedidos SET fatura_id=? WHERE id=?')->execute([$id,$i['id']]);}$pdo->commit();return ['ok'=>true,'created'=>true,'invoice_id'=>$id,'amount'=>number_format(abs($total-$gross),2,'.','')];}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['ok'=>false,'erro'=>$e->getMessage()];}
    }
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
