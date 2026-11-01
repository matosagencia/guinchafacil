<?php
declare(strict_types=1);
/** Diagnostico administrativo read-only. Nao interfere no matching/aceite. */
require_once __DIR__ . '/../PartnerLocationService.php';
final class AdminNetworkDiagnostic
{
    private static function distance(float $a, float $b, float $c, float $d): float
    {
        $x = deg2rad($c - $a); $y = deg2rad($d - $b);
        $h = sin($x/2)**2 + cos(deg2rad($a))*cos(deg2rad($c))*sin($y/2)**2;
        return round(2*6371.0088*asin(min(1.0, sqrt(max(0.0,$h)))), 2);
    }
    private static function valid($a, $b): bool
    {
        return is_numeric($a) && is_numeric($b) && is_finite((float)$a) && is_finite((float)$b)
            && abs((float)$a) <= 90 && abs((float)$b) <= 180 && !((float)$a == 0.0 && (float)$b == 0.0);
    }
    public static function build(PDO $pdo, array $rings, float $lat, float $lng, float $radiusKm): array
    {
        $tow=[];
        foreach ($rings as $ring) foreach (($ring['candidatos'] ?? []) as $g) {
            $id=(int)($g['id']??0);
            if ($id>0) $tow[$id]=['id'=>$id,'dist_km'=>(float)($g['dist_km']??0),
                'aprovado'=>true,'elegivel_candidato'=>true,'online'=>'nao_verificado',
                'observacao'=>'Disponivel no matching; frescor do GPS/heartbeat nao verificado'];
        }
        $workshops=[];
        foreach (PartnerLocationService::listarWorkshopsParaDiagnostico() as $r) {
            $approved=$r['approval_status']==='APPROVED' && (int)$r['active']===1;
            $partner=$r['status_parceria']==='ATIVO';
            $yard=(int)$r['recebe_veiculo_patio']===1;
            $native=self::valid($r['p_lat'],$r['p_lng']);
            $linked=$r['oficina_id']!==null && self::valid($r['o_lat'],$r['o_lng']);
            $hasGeo=$native||$linked;
            $distance=$hasGeo ? self::distance($lat,$lng,(float)($native?$r['p_lat']:$r['o_lat']),(float)($native?$r['p_lng']:$r['o_lng'])) : null;
            $reasons=[];
            if (!$approved) $reasons[]='Cadastro nao aprovado/ativo';
            if (!$partner) $reasons[]='Parceria nao ativa';
            if (!$yard) $reasons[]='Nao recebe no patio';
            if (!$hasGeo) $reasons[]='Sem GPS valido ou vinculo';
            if ($distance!==null && $distance>$radiusKm) $reasons[]='Fora dos '.$radiusKm.' km de exibicao';
            $workshops[]=['tipo'=>'provider','id'=>(int)$r['id'],
              'nome'=>(string)($r['trade_name']?:$r['legal_name']?:'Oficina'),
              'aprovado'=>$approved,'parceria_ativa'=>$partner,'recebe_patio'=>$yard,
              'resgate_direto'=>(int)$r['faz_resgate_direto']===1,
              'vinculo_oficina_id'=>$r['oficina_id']===null?null:(int)$r['oficina_id'],
              'gps'=>$hasGeo,'fonte_gps'=>$native?'provider':($linked?'oficina_vinculada':'ausente'),
              'dist_km'=>$distance,'dentro_raio'=>$distance!==null && $distance<=$radiusKm,
              'elegivel_exibicao'=>count($reasons)===0,'motivos'=>$reasons,
              'online'=>'nao_verificado'];
        }
        $legacy=[];
        $q="SELECT o.id,o.nome,o.latitude,o.longitude,l.provider_id
              FROM oficinas o LEFT JOIN oficina_provider_links l ON l.oficina_id=o.id ORDER BY o.id";
        foreach ($pdo->query($q)->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $valid=self::valid($o['latitude'],$o['longitude']);
            $d=$valid?self::distance($lat,$lng,(float)$o['latitude'],(float)$o['longitude']):null;
            $legacy[]=['tipo'=>'legado','id'=>(int)$o['id'],'nome'=>(string)$o['nome'],
                'vinculo_provider_id'=>$o['provider_id']===null?null:(int)$o['provider_id'],
                'gps'=>$valid,'dist_km'=>$d,'online'=>'nao_verificado',
                'observacao'=>$o['provider_id']===null?'Sem vinculo operacional':'Vinculo cadastrado; elegibilidade depende do provider'];
        }
        return ['guinchos'=>array_values($tow),'providers_oficina'=>$workshops,'oficinas_legadas'=>$legacy,
          'nota_online'=>'Presenca online/heartbeat nao verificada neste endpoint; nao confundir com disponivel=1'];
    }
}
