<?php
declare(strict_types=1);

/** Fonte de verdade para preços ON_SITE; nunca é usada no fluxo de guincho. */
final class EspecialistaPricingService
{
    private const MAP = ['JUMP_START'=>'BATTERY_JUMP','BATTERY_TEST'=>'BATTERY_DIAG','BATTERY_REPLACEMENT'=>'BATTERY_REPLACE','AUTOMOTIVE_LOCKSMITH'=>'LOCKOUT','ELECTRICAL_DIAGNOSIS'=>'ELECTRICAL_DIAG'];

    public static function calcular(string $codigo, float $distanciaKm = 0, ?DateTimeInterface $quando = null): ?array
    {
        $codigo = strtoupper(trim($codigo));
        $codigo = self::MAP[$codigo] ?? $codigo;
        $st = getPDO()->prepare('SELECT * FROM servicos_especialista WHERE codigo=? AND ativo=1 LIMIT 1');
        $st->execute([$codigo]);
        $servico = $st->fetch(PDO::FETCH_ASSOC);
        if (!$servico) return null;
        $quando = $quando ?? new DateTimeImmutable();
        $noturno = (int)$quando->format('H') >= 20 || (int)$quando->format('H') < 7;
        $distanciaKm = max(0, round($distanciaKm, 2));
        $extraDistancia = max(0, ceil($distanciaKm - (float)$servico['raio_incluso_km'])) * 2.00;
        $base = (float)$servico['preco_atendimento'];
        $adicional = (float)$servico['preco_adicional'];
        $noturnoValor = $noturno ? (float)$servico['adicional_noturno'] : 0.0;
        $cliente = round($base + $adicional + $noturnoValor + $extraDistancia, 2);
        $provider = round($cliente * 0.75, 2);
        return ['codigo'=>$codigo, 'servico_id'=>(int)$servico['id'], 'customer_amount'=>$cliente, 'provider_amount'=>$provider,
            'platform_amount'=>round($cliente-$provider,2), 'detalhe'=>['base'=>$base,'adicional'=>$adicional,'noturno'=>$noturnoValor,'distancia'=>$extraDistancia]];
    }
}
