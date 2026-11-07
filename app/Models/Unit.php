<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['allows_decimal' => 'boolean']; }

    /** Produits dont c'est l'unité de base. */
    public function products(): HasMany { return $this->hasMany(Product::class, 'base_unit_id'); }

    /** Conditionnements qui utilisent cette unité. */
    public function productUnits(): HasMany { return $this->hasMany(ProductUnit::class); }
}
