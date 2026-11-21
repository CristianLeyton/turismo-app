<?php

namespace Tests\Feature;

use App\Models\Setting;

/**
 * Helpers para cargar el precio fijo en los tests: el interruptor y los
 * períodos de precio, como los guarda la página de Parámetros.
 */
trait ConfiguresFixedPrice
{
    /**
     * Prende o apaga el precio fijo con un único período que rige desde hoy
     * (o desde `$from`, en formato `Y-m-d`).
     */
    private function configureFixedPrice(bool $active, int $base, int $insurance, ?string $from = null): void
    {
        Setting::set(Setting::PRECIO_FIJO_ACTIVO, $active ? '1' : '0');
        $this->setFixedPricePeriods([
            ['desde' => $from ?? today()->format('Y-m-d'), 'boleto' => $base, 'seguro' => $insurance],
        ]);
    }

    /**
     * Guarda los períodos tal cual, sin validar (como un dato cargado por fuera).
     *
     * @param  list<array{desde: string, boleto: int, seguro: int}>  $periods
     */
    private function setFixedPricePeriods(array $periods): void
    {
        Setting::set(Setting::PRECIO_FIJO_PERIODOS, json_encode($periods));
    }
}
