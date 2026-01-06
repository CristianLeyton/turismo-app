<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'sale_date',
        'total_amount',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Recalcular total automáticamente
     */
    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->tickets()->sum('price'),
        ]);
    }
}
