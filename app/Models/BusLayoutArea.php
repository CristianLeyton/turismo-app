<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusLayoutArea extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bus_id',
        'floor',
        'area_type',
        'label',
        'row_start',
        'row_end',
        'column_start',
        'column_end',
        'span_rows',
        'span_columns',
    ];

    protected $casts = [
        'row_start' => 'integer',
        'row_end' => 'integer',
        'column_start' => 'integer',
        'column_end' => 'integer',
        'span_rows' => 'integer',
        'span_columns' => 'integer',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }
}