<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'effective_date' => 'date'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Taux actif : le plus récent jusqu'à aujourd'hui, sinon le paramètre par défaut. */
    public static function current(string $currency = 'USD'): float
    {
        $rate = static::where('currency', $currency)
            ->whereDate('effective_date', '<=', today())
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->value('rate');

        return (float) ($rate ?? Setting::get('default_usd_rate', 132));
    }
}
