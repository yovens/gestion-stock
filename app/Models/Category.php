<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    protected static function booted(): void
    {
        static::saving(fn (Category $c) => $c->slug = $c->slug ?: Str::slug($c->name));
    }

    public function parent(): BelongsTo { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Category::class, 'parent_id'); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
}
