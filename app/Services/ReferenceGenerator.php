<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Numéros de document séquentiels par année : V-2026-000001, REC-2026-0001...
 * Sûr en concurrence grâce au verrou sur la ligne du compteur.
 */
class ReferenceGenerator
{
    public static function next(string $prefix, int $padding = 4): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($prefix, $year, $padding) {
            DB::table('reference_sequences')->insertOrIgnore([
                'prefix' => $prefix, 'year' => $year, 'last_number' => 0,
            ]);

            $row = DB::table('reference_sequences')
                ->where('prefix', $prefix)->where('year', $year)
                ->lockForUpdate()->first();

            $number = $row->last_number + 1;

            DB::table('reference_sequences')->where('id', $row->id)->update(['last_number' => $number]);

            return sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $number, $padding, '0', STR_PAD_LEFT));
        });
    }
}
