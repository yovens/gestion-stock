<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function sessions(): HasMany { return $this->hasMany(CashSession::class); }

    public function openSession(): ?CashSession
    {
        return $this->sessions()->where('status', 'open')->latest('id')->first();
    }
}
