# Propuesta: ciclo de vida de las reservas temporales

## Why

Las reservas temporales de asientos fallan en los dos sentidos. Algunas se pierden sin que el vendedor haga nada: abrir o recargar la página de venta borra todas las reservas de la sesión, también las de otra pestaña con una venta en curso (H10), y nada las renueva mientras el vendedor carga pasajeros (H6). Otras quedan bloqueando asientos que ya nadie usa: cambiar de viaje en el paso 1 no libera las reservas del viaje anterior (H3), y una reserva vencida impide que otro vendedor tome el asiento hasta que pase el cron del servidor (H1). Además, el paso 1 cuenta como libres asientos inactivos o reservados por otros, así que puede dejar avanzar a un viaje en el que no alcanzan los asientos (H7).

## What Changes

- **BREAKING (comportamiento):** cargar la página de venta **ya no borra reservas**. Cancelar la venta, cerrar la página, que venza la cuenta regresiva y terminar la emisión (bien o con error) liberan **solo las reservas de los viajes de esa venta** (ida y vuelta), no todas las de la sesión.
- **Renovación por actividad:** mientras el vendedor trabaja en el asistente (cambia de paso, carga datos), las reservas de la venta vuelven a 5 minutos y la cuenta regresiva se actualiza. Una venta abandonada vence a los 5 minutos de la última actividad. Se elimina el keep-alive por intervalo, que nunca se ejecutaba.
- **Cambio de viaje en el paso 1:** si el viaje de ida o de vuelta resuelto es distinto al anterior, se liberan las reservas de la sesión en el viaje abandonado y se descarta la selección de asientos de ese tramo, sin mostrar el aviso de "asientos expirados".
- **Reservas vencidas sin limpiar:** al reservar, las reservas vencidas de esos asientos se eliminan antes de insertar las nuevas. Una reserva vencida ya no bloquea a otro vendedor, haya corrido o no el cron. El conflicto de unicidad se reconoce también en SQLite (hoy devuelve 500).
- **Disponibilidad del paso 1:** los asientos libres pasan a calcularse igual que en la reserva: asientos activos del colectivo, sin boleto vigente y sin reserva vigente de otra sesión.
- Se elimina código muerto: `app/Console/Kernel.php` y `config/schedule.php` (la programación que describen nunca se carga). La limpieza programada sigue a cargo del cron del servidor.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `seat-reservation`:
  - "Renovación de reservas" pasa a renovar por actividad del vendedor.
  - "Liberación de reservas" deja de liberar al cargar la página, limita la cancelación y el cierre a los viajes de la venta y libera el viaje abandonado al cambiar la búsqueda.
  - "Unicidad de la reserva por asiento y viaje" deja de bloquear con reservas vencidas sin limpiar.
  - "Detección de asientos expirados al volver al selector" no avisa cuando lo que cambió fue el viaje.
  - "Vencimiento visible para el vendedor" y "Verificación final de disponibilidad al emitir" liberan solo los viajes de la venta.
- `ticket-sale`:
  - "Disponibilidad suficiente para avanzar" usa el mismo cálculo de asientos libres que la reserva.
  - "Cierre de una venta exitosa" y "Cancelación de la venta" liberan solo los viajes de la venta.

## Impact

- **Código:**
  - `app/Filament/Resources/Tickets/Pages/CreateTicket.php`: se elimina `__destruct`; la cancelación libera solo los viajes de la venta; renovación por actividad.
  - `app/Filament/Resources/Tickets/Schemas/TicketForm.php`: detección del cambio de viaje en el paso 1 (reemplaza la variable `static`), cálculo de disponibilidad de los pasos 1 a 3.
  - `resources/views/tickets/seat-selector.blade.php`: liberación al cerrar acotada a los viajes, actualización de la cuenta regresiva tras la renovación, borrado de `startKeepAlive`/`stopKeepAlive`.
  - `app/Http/Controllers/Api/SeatReservationController.php` y `app/Models/SeatReservation.php`: `release` acepta viajes opcionales; `reserve` elimina las vencidas de los asientos pedidos; detección del conflicto independiente del motor.
  - `app/Models/Trip.php`: cálculo de asientos libres para una sesión.
  - Se borran `app/Console/Kernel.php` y `config/schedule.php`.
- **API:** `POST /api/seat-reservations/release` acepta `trip_ids` opcional. Sin ese parámetro se comporta como hoy (compatibilidad con la reprogramación y la asignación de clientes, que usan el mismo selector).
- **Base de datos:** sin cambios.
- **Fuera de alcance:** la seguridad de la API de reservas (H5, change `api-reservas-segura`) y la marca de tramo de vuelta (H2).
- **Tests:** nuevos para la carga de la página, la cancelación acotada, el cambio de viaje, la renovación por actividad, la reserva sobre una vencida (también en SQLite) y la disponibilidad del paso 1.
