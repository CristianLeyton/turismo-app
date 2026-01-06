<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RouteStop extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'route_id',
        'location_id',
        'stop_order',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}