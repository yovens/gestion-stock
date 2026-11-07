<?php

namespace App\Support;

/** Formatage français/haïtien pour les vues : 12 345,50 HTG */
class Format
{
    public static function money(float|int|string|null $value, bool $withCurrency = true): string
    {
        $s = number_format((float) $value, 2, ',', "\u{202F}");
        return $withCurrency ? $s."\u{00A0}HTG" : $s;
    }

    /** Montant compact pour les tuiles : 1,2 M · 45,3 k */
    public static function compact(float|int|string|null $value): string
    {
        $v = (float) $value;
        $abs = abs($v);
        if ($abs >= 1_000_000) return number_format($v / 1_000_000, 1, ',', "\u{202F}")."\u{00A0}M";
        if ($abs >= 10_000) return number_format($v / 1_000, 1, ',', "\u{202F}")."\u{00A0}k";
        return number_format($v, 0, ',', "\u{202F}");
    }

    public static function qty(float|int|string|null $value): string
    {
        $s = number_format((float) $value, 3, ',', "\u{202F}");
        return rtrim(rtrim($s, '0'), ',');
    }

    public static function percent(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 1, ',', '')."\u{00A0}%";
    }
}
