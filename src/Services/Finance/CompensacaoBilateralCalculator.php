<?php
declare(strict_types=1);
namespace App\Services\Finance;

/** Motor matemático puro: nenhum SQL, cobrança, repasse ou bloqueio. Valores em centavos. */
final class CompensacaoBilateralCalculator
{
    /**
     * @param array<int,array{id:string,canal:string,bruto_centavos:int,comissao_centavos:int,taxa_parceiro_centavos?:int}> $movimentos
     * @return array<string,int|string>
     */
    public static function apurar(array $movimentos): array
    {
        $creditos = 0; $debitos = 0; $vistos = [];
        foreach ($movimentos as $m) {
            if (!isset($m['id'], $m['canal'], $m['bruto_centavos'], $m['comissao_centavos'])) {
                throw new \InvalidArgumentException('Movimento incompleto');
            }
            $id = (string)$m['id'];
            if ($id === '' || isset($vistos[$id])) { throw new \InvalidArgumentException('ID duplicado ou vazio'); }
            $vistos[$id] = true;
            foreach (['bruto_centavos', 'comissao_centavos'] as $campo) {
                if (!is_int($m[$campo]) || $m[$campo] < 0) { throw new \InvalidArgumentException('Centavos invalidos'); }
            }
            $bruto = $m['bruto_centavos']; $comissao = $m['comissao_centavos'];
            $taxa = $m['taxa_parceiro_centavos'] ?? 0;
            if (!is_int($taxa) || $taxa < 0 || $comissao + $taxa > $bruto) {
                throw new \InvalidArgumentException('Deducoes invalidas');
            }
            switch ($m['canal']) {
                case 'plataforma':
                    $creditos += $bruto - $comissao - $taxa;
                    break;
                case 'parceiro':
                    // Cliente pagou ao parceiro: so nasce divida de comissao e ajustes aprovados.
                    if ($taxa !== 0) { throw new \InvalidArgumentException('Taxa de gateway nao cabe no recebimento local'); }
                    $debitos += $comissao;
                    break;
                default:
                    throw new \InvalidArgumentException('Canal invalido');
            }
        }
        $saldo = $creditos - $debitos;
        return [
            'creditos_centavos' => $creditos,
            'debitos_centavos' => $debitos,
            'saldo_centavos' => $saldo,
            'resultado' => $saldo > 0 ? 'repassar' : ($saldo < 0 ? 'cobrar' : 'compensado'),
            'quantidade' => count($vistos),
        ];
    }
}
