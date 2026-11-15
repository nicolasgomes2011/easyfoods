<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person at the table (comanda), added by staff on the tab of a table
 * session. Items of the session's orders can be assigned to them
 * (OrderItem::guest); items without a person are shared.
 */
class TableSessionGuest extends Model
{
    public const NAME_MAX = 60;

    protected $fillable = [
        'restaurant_id',
        'table_session_id',
        'name',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'table_session_guest_id');
    }
}
