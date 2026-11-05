# Design

## Context

La motivación está en proposal.md y los requisitos en `specs/passenger-trip-uniqueness/spec.md` y `specs/ticket-sale/spec.md`.

Estado actual relevante:

- **Venta:** `CreateTicket::handleRecordCreation()` crea un `Passenger` nuevo por pasajero y por venta, sin buscar por DNI. El menor es otro `Passenger` con `parent_passenger_id`, sin boleto propio. Cada tramo es un `Ticket` con su propio `trip_id`, y la vuelta es otra fila con `is_return_leg = true`. Las validaciones bloqueantes envían una `Notification` persistente y llaman a `$this->halt()` antes del `try`. Dentro de la transacción, `lockSaleTrips()` bloquea los viajes con `lockForUpdate`.
- **Reprogramación:** `TicketRescheduleService::rescheduleForActor()` la usan `RescheduleTicket` (boletería) y `ClientTicketAssignmentService` (panel de clientes). Ambas capturan `ValidationException` y muestran sus mensajes en una notificación. La transacción bloquea el viaje destino en `moveLeg()`.
- **Edición:** `EditTicket::performSave()` actualiza el `Passenger` y su menor. En un ida y vuelta, los dos boletos comparten el mismo `passenger_id`.
- **Índices:** `tickets.trip_id` está indexado por su FK. `passengers.dni` no tiene índice propio.

## Goals / Non-Goals

**Goals:**
- Una sola consulta de conflictos, reutilizada en venta, reprogramación y edición, para que la regla no diverja entre caminos.
- Que el chequeo definitivo corra con los viajes bloqueados, sin carreras entre operaciones simultáneas.
- Que el panel de clientes reciba el rechazo sin cambios en su manejo de errores.

**Non-Goals:**
- Unificar los registros de `Passenger` por DNI o deduplicar datos históricos.
- Una restricción a nivel de base de datos.
- Cambios en `Clients` o en el autocompletado del padrón.

## Decisions

### 1. Consulta compartida en `App\Support\TripPassengerConflicts`

Es una clase de soporte, siguiendo `SaleCutoff`, `ServiceDays` y `SeatConflict`. Tiene un método principal:

```php
/**
 * @param  list<string>  $dnis
 * @param  list<int>  $tripIds
 * @param  list<int>  $excludeTicketIds
 * @return Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>
 */
public static function find(array $dnis, array $tripIds, array $excludeTicketIds = []): Collection
```

- Busca `Ticket` sin borrar (`SoftDeletes` ya excluye los borrados) con `trip_id` en `$tripIds` y no en `$excludeTicketIds`.
- La condición es `whereHas('passenger', dni in $dnis)` OR `whereHas('passenger.children', dni in $dnis)`, con eager loading de `passenger.children`, `trip.schedule` y `trip.bus`.
- Por cada boleto devuelve los DNI que coincidieron, sea el del titular o el del menor.
- Antes de comparar, normaliza los DNI con `trim()` y descarta los vacíos.

Parte de `tickets.trip_id` (indexado), así que trabaja sobre los pocos boletos de uno o dos viajes. No hace falta un índice nuevo.

**Alternativa descartada: índice único en la base.** El DNI vive en `passengers` y el viaje en `tickets`, y el menor no tiene boleto. Un índice no puede expresar la regla, y los duplicados históricos harían fallar la migración.

### 2. Error tipado `DuplicatePassengerOnTripException extends ValidationException`

La lanzan el servicio de reprogramación y los chequeos internos, y lleva la colección de conflictos.

- Sus `errors()` traen el mensaje genérico para el cliente: "Ya tenés un boleto en ese viaje. Elegí otra fecha u horario." Así `MyTickets`, que ya captura `ValidationException`, muestra el rechazo correcto sin cambios y sin enlaces de administración.
- Las páginas de administración (`RescheduleTicket`, `EditTicket`, `CreateTicket`) la capturan antes que la `ValidationException` genérica y arman la alerta con los conflictos.

**Alternativa descartada: devolver los conflictos como array.** Obligaría a cambiar la firma del servicio y a los dos llamadores. Heredar de `ValidationException` mantiene compatible a cualquier llamador existente.

### 3. Alerta única en `App\Support\DuplicateTicketNotification`

Tiene un método `send(Collection $conflicts, string $backLabel)`, al estilo de `ShareLinkNotification`.

- Arma una `Notification` persistente y `danger`. El título sale de `title()`: "Este pasajero ya tiene boleto en este viaje", o "N pasajeros ya tienen boleto en este viaje" cuando hay varios DNI.
- `rows()` prepara cada conflicto para mostrar: nombre (del titular o del menor, con la marca de menor), DNI, boleto, URL, tramo, fecha, hora, asiento y colectivo. Para eso `find()` también carga `seat`, `origin` y `destination`.
- El cuerpo tiene una línea por persona: "Ana Pérez (DNI 30123456) · boleto N°812 · Tartagal → Salta · 04/11/2026 08:00".
- Las acciones son "Ver boleto N°X", una por boleto, con `->url(TicketResource::getUrl('view', ...), shouldOpenInNewTab: true)`, y la acción de volver con `->close()`.
- El texto de volver es "Volver a la venta" en la venta y "Volver" en reprogramación y edición.

Como el componente no se redirige ni se resetea, cerrar la alerta deja los datos cargados.

### 4. Venta: chequeo previo y re-chequeo en la transacción

En `handleRecordCreation()`:

- **Antes del `try`**, junto a los otros guards:
  1. DNI repetidos dentro de la venta (adultos y menores), con alerta simple y `halt()`.
  2. `TripPassengerConflicts::find()` con todos los DNI de la venta y los viajes `trip_id` y `return_trip_id` (si es ida y vuelta con fecha), con `DuplicateTicketNotification` y `halt()`.
- **Dentro de `DB::transaction`**, justo después de `lockSaleTrips()`, el mismo `find()`. Si hay conflictos, lanza `DuplicatePassengerOnTripException`, que revierte la transacción. Un `catch` específico, antes del `catch` genérico existente, envía la alerta y llama a `halt()`. A diferencia del `catch` genérico, no libera las reservas de asientos: el vendedor vuelve a la venta con sus asientos para corregir el DNI.

Los tramos sin fecha no aportan `trip_id`, así que se excluyen naturalmente.

### 5. Formulario de venta: regla de DNI distintos y modal del paso "Pasajeros"

- **DNI distintos:** es una regla nueva en los campos `dni` del pasajero y `child_data.dni` de `TicketForm`, con `LiveValidation` al salir del campo, como las reglas de baneados y de venta sin fecha. Junta los DNI de todos los pasajeros y menores (`TripPassengerConflicts::saleDniSlots()`) y falla si el valor está en cualquier otro campo DNI de la venta. Se usa una regla propia y no `distinct()`, porque la comparación cruza las rutas `passengers.*.dni` y `passengers.*.child_data.dni`. Marca cualquier campo repetido, no solo el segundo: con "solo el segundo", un menor que repite el DNI de un adulto cargado después no quedaba marcado. Como la validación en vivo valida un solo campo, en pantalla se marca el que se está editando.
- **Modal de duplicado:** el paso "Pasajeros" tiene un `afterValidation`. Al presionar "Siguiente", si `find()` encuentra conflictos con los DNI de la venta y los viajes con fecha (`saleTripIds()`), monta la acción `CreateTicket::duplicatePassengerAction()` con esos DNI y viajes como argumentos y lanza `Halt`, así la venta no avanza. El modal recalcula los conflictos y usa `title()`, la indicación "Una persona no puede tener dos boletos en el mismo viaje. Corregí el DNI o elegí otro viaje." y la vista `filament.tickets.duplicate-passenger-modal`. Esa vista muestra una tarjeta por persona, con el estilo de "Paradas y horarios", con "Ver boleto" en otra pestaña. El modal no tiene botón de envío; el de cancelar dice "Volver a pasajeros".

**Alternativa descartada: aviso en el campo DNI.** La primera versión fallaba el campo con "Este DNI ya tiene el boleto N°X en este viaje.". Se reemplazó por el modal porque el mensaje en el campo no permitía abrir el boleto y era difícil de leer con varios pasajeros.

### 6. Reprogramación: chequeo dentro de la transacción del servicio

En `rescheduleForActor()`, dentro de `DB::transaction`, antes de mover cada tramo:

- Arma los DNI del boleto que se mueve, el titular y su menor.
- Llama a `find()` con los viajes destino de los tramos que se mueven y excluye los ids de los boletos que se mueven (el principal y su vuelta si el alcance es `both`).
- Si hay conflicto, lanza `DuplicatePassengerOnTripException` antes de cualquier `moveLeg()`, así no se aplica ningún tramo.
- Bloquea los viajes destino con `lockForUpdate` antes del chequeo, en el mismo orden por id que usa la venta, para que sea definitivo frente a una venta simultánea.

`RescheduleTicket::confirmReschedule()` captura primero la excepción tipada y usa `DuplicateTicketNotification` con "Volver". `ClientTicketAssignmentService` no cambia.

### 7. Edición: chequeo solo si cambia un DNI o se agrega un menor

En `EditTicket::performSave()`:

- Calcula si cambió el DNI del titular, si cambió el DNI del menor o si se agregó un menor. Si no, no hay chequeo.
- Si hay chequeo:
  - Los viajes son los `trip_id` no nulos de los boletos activos de ese `passenger_id`, es decir, ida y vuelta.
  - Los excluidos son esos mismos boletos.
  - Los DNI son solo los nuevos.
- Corre dentro de la transacción existente, con `lockForUpdate` de esos viajes. Si hay conflicto, lanza la excepción tipada, se revierte y se muestra la alerta con "Volver" y el formulario intacto.

### 8. Aviso de asientos liberados al finalizar

Al presionar "Finalizar", el asistente valida todos los pasos. Si la reserva de asientos venció, el selector de asientos ya descartó la selección y falla el campo oculto `seat_ids` o `return_seat_ids`, que está en un paso anterior y no se ve: el botón parecía no hacer nada. `CreateTicket::onValidationError()` detecta esos errores y envía una notificación persistente que explica el problema e indica presionar «Anterior» hasta el paso de asientos.

**Alternativa descartada: botón para volver al paso.** Se probó un botón "Elegir asientos" que movía el asistente con un evento de navegador. No funcionó de forma confiable: con `->close()` Filament descarta el evento del botón, y sin él el evento no llegó a mover el asistente en el navegador. Se quitó y el texto indica cómo volver.

## Risks / Trade-offs

- **[DNI con formato histórico distinto (puntos, espacios)]** → La venta ya valida DNI numérico de 7 u 8 dígitos. Se normaliza con `trim()`. Los DNI viejos con puntos no van a coincidir, y es un caso aceptado porque la regla mira operaciones nuevas.
- **[Bloqueos extra en la reprogramación]** → Se bloquean solo los viajes destino, ordenados por id, igual que la venta, para no crear interbloqueos.
- **[El modal consulta la base al avanzar del paso "Pasajeros"]** → Es una consulta acotada a uno o dos viajes por `trip_id` indexado, y corre una vez por clic en "Siguiente".
- **[Duplicados históricos visibles en la alerta de otro boleto]** → Si un DNI ya estaba duplicado, la alerta lista todos sus boletos en el viaje, y es información útil para el vendedor.
- **[Mensaje genérico de la excepción]** → El texto de `errors()` está pensado para el cliente. Si otra página de administración captura solo la `ValidationException` base, verá ese texto, que sigue siendo correcto aunque no traiga los enlaces.

## Migration Plan

Sin migraciones ni cambios de configuración. Se despliega código y la regla aplica desde ese momento. Para volver atrás alcanza con revertir el commit.
