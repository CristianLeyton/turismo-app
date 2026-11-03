<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bus extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'plate',
        'seat_count',
        'floors',
        'linea_id',
        'is_reinforcement',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_reinforcement' => 'boolean',
        ];
    }

    /**
     * Línea a la que pertenece el colectivo, si tiene una asignada.
     */
    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class);
    }

    /**
     * Ids de los colectivos que comparten recorrido con este: los de su línea,
     * o solo su propio id si no tiene línea.
     *
     * @return array<int, int>
     */
    public function busIdsInSameLine(): array
    {
        if ($this->linea_id === null) {
            return [(int) $this->getKey()];
        }

        return Bus::query()
            ->where('linea_id', $this->linea_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Ids de los colectivos que comparten línea con el dado, o solo ese id si no
     * tiene línea. Vacío si no hay colectivo.
     *
     * @return array<int, int>
     */
    public static function idsInSameLineAs(mixed $busId): array
    {
        if (blank($busId)) {
            return [];
        }

        return static::find($busId)?->busIdsInSameLine() ?? [(int) $busId];
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Rutas asignadas a este colectivo.
     */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    /**
     * Asientos activos del colectivo
     */
    public function activeSeats()
    {
        return $this->seats()->where('is_active', true);
    }

    /**
     * Áreas especiales del layout del bus
     */
    public function layoutAreas()
    {
        return $this->hasMany(BusLayoutArea::class);
    }

    /**
     * Obtener áreas especiales por piso
     */
    public function layoutAreasByFloor(?string $floor = null)
    {
        $query = $this->layoutAreas();
        if ($floor !== null) {
            $query->where('floor', $floor);
        }

        return $query->orderBy('row_start')->orderBy('column_start');
    }
}
