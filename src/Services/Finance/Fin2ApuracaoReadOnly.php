<?php
declare(strict_types=1);

namespace App\Services\Finance;

/** Projeção FIN.2 por parceiro; não persiste fechamento nem dispara cobrança. */
final class Fin2ApuracaoReadOnly
{
    public static function porGuincho(\PDO $db, ?int $guinchoId = null): array
    {
        $movimentos = Fin2ReadOnlySource::movimentosPlataforma($db, $guinchoId);
        $grupos = [];
        foreach ($movimentos as $movimento) {
            $id = (int)$movimento['guincho_id'];
            $grupos[$id][] = $movimento;
        }
        $resultado = [];
        foreach ($grupos as $id => $itens) {
            $apuracao = CompensacaoBilateralCalculator::apurar(array_map(
                static fn(array $item): array => array_diff_key($item, ['guincho_id' => true]),
                $itens
            ));
            $resultado[] = ['guincho_id' => $id, 'movimentos' => count($itens)] + $apuracao;
        }
        return $resultado;
    }
}
