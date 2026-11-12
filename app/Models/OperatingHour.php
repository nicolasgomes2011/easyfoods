<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'weekday',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'weekday'   => 'integer',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function weekdayName(): string
    {
        return match($this->weekday) {
            0 => 'Domingo',
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
            default => '?',
        };
    }

    /** Whether the store opens on this day: not marked closed and with both times set. */
    public function isOpenDay(): bool
    {
        return ! $this->is_closed && filled($this->opens_at) && filled($this->closes_at);
    }

    /** Opening time as HH:MM (the time column comes back as HH:MM or HH:MM:SS depending on the driver). */
    public function opensAt(): ?string
    {
        return filled($this->opens_at) ? substr($this->opens_at, 0, 5) : null;
    }

    /** Closing time as HH:MM. */
    public function closesAt(): ?string
    {
        return filled($this->closes_at) ? substr($this->closes_at, 0, 5) : null;
    }

    /** "HH:MM–HH:MM", as shown in the menu header. */
    public function rangeLabel(): string
    {
        return $this->opensAt().'–'.$this->closesAt();
    }
}
