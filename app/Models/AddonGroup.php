<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddonGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'required',
        'min_choices',
        'max_choices',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'required'    => 'boolean',
        'is_active'   => 'boolean',
        'min_choices' => 'integer',
        'max_choices' => 'integer',
        'sort_order'  => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(AddonOption::class)->orderBy('sort_order');
    }

    public function activeOptions(): HasMany
    {
        return $this->hasMany(AddonOption::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function allowsMultiple(): bool
    {
        return $this->max_choices > 1;
    }
}
