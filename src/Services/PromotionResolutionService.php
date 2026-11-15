<?php
declare(strict_types=1);
final class PromotionResolutionService
{
    public static function resolve(string $code, array $context = []): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') return ['ok'=>false,'status'=>'empty','message'=>'Informe um código de desconto.'];
        try {
            $stmt=getPDO()->prepare('SELECT * FROM marketing_promotion_rules WHERE code = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$code]); $rule=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rule) return ['ok'=>false,'status'=>'not_found','message'=>'Cupom não existe. Confira o código digitado.'];
            if (($rule['status']??'')!=='ativo') return ['ok'=>false,'status'=>'paused','message'=>'Este cupom está pausado e não pode ser utilizado no momento.'];
            if (($rule['starts_at']??null)!==null && strtotime((string)$rule['starts_at'])>time()) return ['ok'=>false,'status'=>'unavailable','message'=>'Este cupom ainda não está disponível.'];
            if (($rule['ends_at']??null)!==null && strtotime((string)$rule['ends_at'])<time()) return ['ok'=>false,'status'=>'expired','message'=>'Este cupom está fora da validade.'];
            if ($rule['max_uses']!==null && (int)$rule['used_count'] >= (int)$rule['max_uses']) return ['ok'=>false,'status'=>'limit','message'=>'Este código atingiu o limite de utilizações.'];
            foreach(['partner_profile','vehicle_type','vehicle_make','region','service_type_id'] as $field) if($rule[$field]!==null && $rule[$field]!=='' && (string)$rule[$field] !== (string)($context[$field]??'')) return ['ok'=>false,'status'=>'not_eligible','message'=>'Este cupom não é válido para este atendimento.'];
            return ['ok'=>true,'status'=>'accepted','rule'=>$rule,'message'=>'Cupom aceito.'];
        } catch(Throwable $e) { error_log('[promotion_resolve] '.$e->getMessage()); return ['ok'=>false,'status'=>'error','message'=>'Cupom indisponível no momento.']; }
    }
    public static function consume(int $id): void { if($id<=0)return; try{getPDO()->prepare('UPDATE marketing_promotion_rules SET used_count=used_count+1 WHERE id=? AND (max_uses IS NULL OR used_count<max_uses)')->execute([$id]);}catch(Throwable $e){error_log('[promotion_consume] '.$e->getMessage());} }
    public static function apply(float $gross,array $resolved): array { $rule=$resolved['rule']??[];$value=(float)($rule['discount_value']??0);$discount=(($rule['discount_type']??'percent')==='fixed')?$value:$gross*$value/100;$discount=round(min(max(0,$discount),max(0,$gross)),2);return ['discount'=>$discount,'net'=>round(max(0,$gross-$discount),2)]; }
}

