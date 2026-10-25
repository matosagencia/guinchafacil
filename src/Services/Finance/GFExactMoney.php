<?php
declare(strict_types=1);

/** Valores monetarios canonicos, sem conversao via float. */
final class GFExactMoney
{
    public const MAX_CENTS = 9999999999; // DECIMAL(10,2)

    public static function cents(string $value): int
    {
        if (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new InvalidArgumentException('FIN_MONEY_NON_CANONICAL');
        }
        $parts = explode('.', $value, 2);
        $whole = (int)$parts[0];
        $fraction = (int)str_pad($parts[1] ?? '0', 2, '0');
        $result = $whole * 100 + $fraction;
        if ($result > self::MAX_CENTS) throw new OverflowException('FIN_MONEY_OVERFLOW');
        return $result;
    }

    public static function decimal(int $cents): string
    {
        if ($cents < 0 || $cents > self::MAX_CENTS) throw new InvalidArgumentException('FIN_MONEY_RANGE');
        return intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Pontos-base (800 = 8%); half-up. */
    public static function percent(int $cents, int $basisPoints): int
    {
        if ($cents < 0 || $basisPoints < 0 || $basisPoints > 10000) throw new InvalidArgumentException('FIN_RATE_RANGE');
        $result = intdiv($cents, 10000) * $basisPoints
            + intdiv(($cents % 10000) * $basisPoints + 5000, 10000);
        if ($result > self::MAX_CENTS) throw new OverflowException('FIN_RATE_OVERFLOW');
        return $result;
    }

    /** Ajuste municipal em pontos-base assinados: -200 = -2pp. */
    public static function rate(int $baseBp, int $deltaMunicipalBp): int
    {
        $rate = $baseBp + $deltaMunicipalBp;
        if ($rate < 0 || $rate > 10000) throw new InvalidArgumentException('FIN_CITY_RATE_RANGE');
        return $rate;
    }

    public static function split(string $gross, string $actualFee, int $baseBp, int $cityDeltaBp): array
    {
        $grossCents = self::cents($gross);
        $feeCents = self::cents($actualFee);
        if ($grossCents < 1 || $feeCents > $grossCents) throw new InvalidArgumentException('FIN_FEE_EXCEEDS_GROSS');
        $rate = self::rate($baseBp, $cityDeltaBp);
        $commission = self::percent($grossCents, $rate);
        if ($commission > $grossCents - $feeCents) throw new InvalidArgumentException('FIN_NEGATIVE_PARTNER');
        $partner = $grossCents - $commission - $feeCents;
        if ($partner + $feeCents + $commission !== $grossCents) throw new LogicException('FIN_SPLIT_DRIFT');
        return [
            'gross' => self::decimal($grossCents), 'partner' => self::decimal($partner),
            'platform' => self::decimal($commission), 'gateway' => self::decimal($feeCents),
            'rate_bp' => $rate,
        ];
    }
}
