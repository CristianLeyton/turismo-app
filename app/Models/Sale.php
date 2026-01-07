<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;
    
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

    public static function createNew(int $userId): self
    {
        return self::create([
            'user_id' => $userId,
            'sale_date' => now(),
            'total_amount' => 0,
        ]);
    }

    public function addTicket(array $ticketData): Ticket
    {
        $ticket = $this->tickets()->create($ticketData);
        $this->recalculateTotal();
        return $ticket;
    }
}
