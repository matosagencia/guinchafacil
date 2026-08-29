<?php
declare(strict_types=1);

final class FinancialAttributionReportService
{
    private static function range(string $desde, string $ate): array
    {
        $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) ? $desde : date('Y-m-01');
        $a = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) ? $ate : date('Y-m-d');
        return [$d, $a];
    }

    public static function resumo(string $desde, string $ate): array
    {
        [$desde,$ate]=self::range($desde,$ate); $pdo=getPDO();
        $s=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN pg.status='aprovado' THEN 1 ELSE 0 END),0) pedidos_pagos,COALESCE(SUM(CASE WHEN pg.status='aprovado' THEN pg.valor_total ELSE 0 END),0) bruto_aprovado,COALESCE(SUM(CASE WHEN pg.status='aprovado' THEN pg.valor_guincho ELSE 0 END),0) valor_guincho_operacional,COALESCE(SUM(CASE WHEN pg.status='aprovado' THEN pg.valor_plataforma ELSE 0 END),0) plataforma_operacional,COALESCE(SUM(CASE WHEN pg.status='estornado' THEN pg.valor_total ELSE 0 END),0) estornos FROM pagamentos pg WHERE pg.status IN ('aprovado','estornado') AND DATE(COALESCE(pg.data_pagamento,pg.criado_em)) BETWEEN ? AND ?"); $s->execute([$desde,$ate]); $r=$s->fetch(PDO::FETCH_ASSOC) ?: [];
        $s=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN entry_type='credito_guincho' THEN valor WHEN entry_type='estorno_credito_guincho' THEN -valor ELSE 0 END),0) guincho,COALESCE(SUM(CASE WHEN entry_type='credito_plataforma' THEN valor WHEN entry_type='estorno_credito_plataforma' THEN -valor ELSE 0 END),0) plataforma,COALESCE(SUM(CASE WHEN entry_type='debito_repasse_guincho' THEN valor ELSE 0 END),0) repassado FROM payout_ledger_entries WHERE DATE(criado_em) BETWEEN ? AND ?"); $s->execute([$desde,$ate]); $l=$s->fetch(PDO::FETCH_ASSOC) ?: [];
        $s=$pdo->prepare("SELECT COALESCE(SUM(valor_gasto),0) FROM gastos_marketing WHERE ativo=1 AND data BETWEEN ? AND ?"); $s->execute([$desde,$ate]); $marketing=(float)$s->fetchColumn();
        $s=$pdo->prepare("SELECT COALESCE(SUM(pl.taxas_gateway),0),COALESCE(SUM(pl.valor_liquido),0),COUNT(pl.id) FROM pagamento_liquidacoes pl JOIN pagamentos pg ON pg.id=pl.pagamento_id WHERE DATE(COALESCE(pg.data_pagamento,pg.criado_em)) BETWEEN ? AND ?"); $s->execute([$desde,$ate]); $g=$s->fetch(PDO::FETCH_NUM) ?: [0,0,0];
        $plataforma=(float)($l['plataforma'] ?? $r['plataforma_operacional'] ?? 0); $estornos=(float)($r['estornos'] ?? 0); $taxas=(float)$g[0];
        return ['desde'=>$desde,'ate'=>$ate,'pedidos_pagos'=>(int)($r['pedidos_pagos']??0),'bruto_aprovado'=>(float)($r['bruto_aprovado']??0),'liquido_gateway'=>(int)$g[2]>0?(float)$g[1]:null,'taxas_gateway'=>$taxas,'credito_guincho'=>(float)($l['guincho']??0),'repassado'=>(float)($l['repassado']??0),'comissao_plataforma'=>$plataforma,'estornos'=>$estornos,'gasto_marketing'=>$marketing,'margem_liquida'=>round($plataforma-$marketing-$estornos-$taxas,2),'liquidacoes_confirmadas'=>(int)$g[2]];
    }

    public static function porCanal(string $desde,string $ate): array
    {
        [$desde,$ate]=self::range($desde,$ate); $s=getPDO()->prepare("SELECT COALESCE(NULLIF(p.canal_aquisicao,''),'organico') canal,COUNT(*) pedidos,COALESCE(SUM(pg.valor_total),0) bruto,COALESCE(SUM((SELECT SUM(CASE WHEN l.entry_type='credito_plataforma' THEN l.valor WHEN l.entry_type='estorno_credito_plataforma' THEN -l.valor ELSE 0 END) FROM payout_ledger_entries l WHERE l.pagamento_id=pg.id)),0) comissao FROM pagamentos pg JOIN pedidos p ON p.id=pg.pedido_id WHERE pg.status='aprovado' AND DATE(COALESCE(pg.data_pagamento,pg.criado_em)) BETWEEN ? AND ? GROUP BY canal ORDER BY bruto DESC"); $s->execute([$desde,$ate]); $rows=$s->fetchAll(PDO::FETCH_ASSOC); $g=self::gastosAgrupados($desde,$ate); foreach($rows as &$row){$canal=$row['canal'];$row['gasto_marketing']=$g[$canal]??0;$row['margem']=round((float)$row['comissao']-(float)$row['gasto_marketing'],2);$row['cac']=(int)$row['pedidos']>0?round((float)$row['gasto_marketing']/(int)$row['pedidos'],2):0;} unset($row); return $rows;
    }
    private static function gastosAgrupados(string $d,string $a): array { $s=getPDO()->prepare("SELECT canal,SUM(valor_gasto) total FROM gastos_marketing WHERE ativo=1 AND data BETWEEN ? AND ? GROUP BY canal");$s->execute([$d,$a]);$o=[];foreach($s as $r)$o[$r['canal']]=(float)$r['total'];return $o; }
    public static function porCelula(string $desde,string $ate): array
    { [$desde,$ate]=self::range($desde,$ate);$s=getPDO()->prepare("SELECT COALESCE(NULLIF(p.canal_aquisicao,''),'organico') canal,COALESCE(c.nome,'Sem cidade') cidade,COALESCE(st.name,'Reboque') servico,COALESCE(v.tipo,'não informado') categoria,COUNT(*) pedidos,COALESCE(SUM(pg.valor_total),0) bruto,COALESCE(SUM(pg.valor_plataforma),0) comissao FROM pagamentos pg JOIN pedidos p ON p.id=pg.pedido_id LEFT JOIN cidades c ON c.id=p.cidade_id LEFT JOIN service_types st ON st.id=p.service_type_id LEFT JOIN veiculos v ON v.id=p.veiculo_id WHERE pg.status='aprovado' AND DATE(COALESCE(pg.data_pagamento,pg.criado_em)) BETWEEN ? AND ? GROUP BY canal,cidade,servico,categoria ORDER BY comissao DESC");$s->execute([$desde,$ate]);return $s->fetchAll(PDO::FETCH_ASSOC); }
}
