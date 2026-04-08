<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'label',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zip',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function formatted(): string
    {
        $parts = ["{$this->street}, {$this->number}"];

        if ($this->complement) {
            $parts[] = $this->complement;
        }

        $parts[] = "{$this->neighborhood} — {$this->city}/{$this->state}";
        $parts[] = $this->zip;

        return implode(', ', $parts);
    }
}
