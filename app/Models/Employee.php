<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'end_date' => 'date',
            'salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function salaryPayments(): HasMany { return $this->hasMany(SalaryPayment::class); }

    public function fullName(): string { return trim("{$this->first_name} {$this->last_name}"); }

    /** Avances non encore déduites pour une période (AAAA-MM). */
    public function advancesFor(string $period): float
    {
        return (float) $this->salaryPayments()->where('type', 'advance')->where('period', $period)->sum('amount');
    }
}
