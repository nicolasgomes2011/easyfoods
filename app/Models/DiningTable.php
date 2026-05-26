<?php

namespace App\Models;

use App\Enums\DiningTableStatus;
use Illuminate\Database\Eloquent\Model;

class DiningTable extends Model
{
    protected $fillable = [
        'number',
        'capacity',
        'status',
        'notes',
    ];

    protected $casts = [
        'status'   => DiningTableStatus::class,
        'capacity' => 'integer',
    ];

    public function isFree(): bool
    {
        return $this->status === DiningTableStatus::Free;
    }
}
