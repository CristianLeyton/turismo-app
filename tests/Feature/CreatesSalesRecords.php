<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;

/**
 * Helpers para armar ventas, boletos y pagos de un vendedor en los tests
 * del clúster Ventas (no hay factories para estos modelos).
 */
trait CreatesSalesRecords
{
    private ?Bus $salesTestBus = null;

    private ?Location $salesTestOrigin = null;

    private ?Location $salesTestDestination = null;

    /**
     * Crea una venta del vendedor con un boleto por cada precio indicado.
     *
     * @param  list<float|int>  $prices
     */
    protected function createSaleFor(User $seller, string $date, array $prices = [10000], string $paymentMethod = 'cash'): Sale
    {
        $this->salesTestBus ??= Bus::create(['name' => 'Linea Test', 'plate' => 'TEST11', 'seat_count' => 40, 'floors' => 1]);
        $this->salesTestOrigin ??= Location::create(['name' => 'Tartagal', 'is_active' => true]);
        $this->salesTestDestination ??= Location::create(['name' => 'Salta', 'is_active' => true]);

        $sale = Sale::create([
            'user_id' => $seller->id,
            'sale_date' => $date,
            'total_amount' => array_sum($prices),
        ]);

        foreach ($prices as $price) {
            $passenger = Passenger::create([
                'first_name' => 'Pasajero',
                'last_name' => (string) fake()->unique()->numberBetween(1, 999999),
                'dni' => (string) fake()->unique()->numberBetween(10000000, 99999999),
                'passenger_type' => 'adult',
            ]);

            Ticket::create([
                'sale_id' => $sale->id,
                'trip_id' => null,
                'seat_id' => null,
                'bus_id' => $this->salesTestBus->id,
                'passenger_id' => $passenger->id,
                'is_round_trip' => false,
                'is_return_leg' => false,
                'origin_location_id' => $this->salesTestOrigin->id,
                'destination_location_id' => $this->salesTestDestination->id,
                'price' => $price,
                'payment_method' => $paymentMethod,
            ]);
        }

        return $sale;
    }

    protected function createPaymentFor(User $seller, string $date, float|int $amount, string $paymentMethod = 'cash'): Payment
    {
        return Payment::create([
            'user_id' => $seller->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_date' => $date,
        ]);
    }

    /**
     * Vendedor con solo los permisos del rol Vendedor (el factory vendedor()
     * ya lo trae así).
     */
    protected function createSeller(): User
    {
        return User::factory()->vendedor()->create();
    }

    /**
     * Administrador sin el rol Vendedor: el factory admin() conserva el rol
     * Vendedor que el observer asigna al crear el usuario.
     */
    protected function createAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->removeRole(\App\Support\Permissions::ROLE_SELLER);

        return $admin->fresh();
    }
}
