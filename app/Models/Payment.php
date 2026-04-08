<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'method',
        'status',
        'amount',
        'amount_tendered',
        'change_due',
        'gateway',
        'gateway_transaction_id',
        'gateway_status',
        'gateway_payload',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'method'          => PaymentMethod::class,
        'status'          => PaymentStatus::class,
        'amount'          => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_due'      => 'decimal:2',
        'gateway_payload' => 'array',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPaid(): bool
    {
        return $this->status->isPaid();
    }

    public function isCash(): bool
    {
        return $this->method === PaymentMethod::Cash;
    }

    public function scopePaid($query)
    {
        return $query->where('status', PaymentStatus::Paid->value);
    }
}
