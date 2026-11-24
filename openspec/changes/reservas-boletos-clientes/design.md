# Diseño

## Contexto

- Unas 50 lecturas de `tickets` (saldos de Ventas, "Mis ventas", lista de pasajeros, PDF, link público, auditoría, exportaciones, `Trip::isFull()`, "Mis boletos") asumen que todo boleto vigente está vendido y cobrado. Ver proposal.md, "Por qué".
- `seat_reservations` tiene un índice único por (viaje, asiento). Unas 15 consultas (venta, reprogramación, "Elegir fecha", API del selector, `Ticket::validateTicketCreation`, `Trip`) ya descartan los asientos con `expires_at > now()` y `user_session_id` distinto del propio. `SeatReservation::reserveSeats()` borra los bloqueos vencidos del asiento antes de insertar. No hay cron: la limpieza solo corre a mano.
- La venta del mostrador vive en `CreateTicket` (≈1900 líneas de Livewire): precio con fotografía, bloqueos por sesión y entrega del PDF están mezclados en la página. No hay un servicio de emisión reutilizable.
- Precio fijo: `FixedTicketPrice::forPassenger()`. Anticipación del cliente: `ClientAdvanceNotice`. Días de servicio: `ServiceDays`. Duplicados por viaje: `TripPassengerConflicts`. Conflicto de asiento: `SeatConflict`. Modal de entrega: `TicketDelivery` + `TicketDeliveryAction`, que hoy distinguen `KIND_SALE` y la reprogramación.
- El panel de clientes (`ClientPanelProvider`) tiene "Mis boletos" y la página de contraseña, y el middleware persistente `EnsureClientPanelIsEnabled` da 404 con el panel apagado.

## Objetivos / Fuera de alcance

**Objetivos:**
- Ninguna lectura existente de `tickets` o `sales` cambia: hasta confirmar, una reserva no es un boleto.
- Apagar las reservas no cambia ninguna pantalla existente, salvo la leyenda del asiento reservado mientras queden bloqueos vigentes.
- Las reglas de la venta (precio, duplicados, baneo, conflicto de asiento) se reutilizan desde sus clases, no se copian.

**Fuera de alcance:**
- Reservas sin fecha: el modelo de datos las admite, pero no hay interfaz, parámetro ni permiso.
- Comprobantes subidos, notificaciones por correo o push, pagos en línea.
- Editar una reserva: se cancela o rechaza y se hace otra.
- Que `Trip::isFull()` y los conteos de libres de la lista de viajes cuenten los bloqueos: el asiento igual no se puede vender, la diferencia es solo visual.

## Decisiones

**1. Tablas propias, no un estado en `tickets`.**
- `ticket_bookings`: `id` (el número de reserva), `client_id`, `kind` (`with_date`/`without_date`), `status` (`pending`, `confirmed`, `rejected`, `cancelled`), `is_round_trip`, ida (`trip_id`, `schedule_id`, `trip_date`, `origin_location_id`, `destination_location_id`), vuelta (los mismos campos con prefijo `return_`, nulos si es solo ida), `payment_method`, `total_amount`, `expires_at`, `sale_id` (al confirmar), `confirmed_by`, `confirmed_at`, `rejected_by`, `rejected_at`, `rejection_reason`, `cancelled_at` y timestamps. Los campos de viaje son nulos para `without_date`.
- `ticket_booking_passengers`: `ticket_booking_id`, `position`, DNI, apellido, nombre, teléfono, `travels_with_child` y datos del menor, `travels_with_pets`, `pet_names`, `pet_count`, `seat_id`, `return_seat_id` y `price`, que se congela.
- "Vencida" no es un estado guardado: es `pending` con `expires_at <= now()`. Así no hace falta cron, y el listado, el widget y el cliente la calculan igual. Un scope `Booking::pendingActive()` es la única definición.
- *Alternativa descartada:* `tickets.status`. Obligaría a filtrar en todas las lecturas existentes, y una sola que se escape muestra una reserva sin pagar como vendida.

**2. Bloqueo de asiento con filas de `seat_reservations`.** Por cada asiento y tramo, una fila con `user_session_id = "booking:{id}"` y `expires_at = booking.expires_at`.
- Todas las consultas existentes ya lo cuentan como "reservado por otro". `reserveSeats()` lo pisa cuando vence, y la limpieza manual lo borra si está vencido. Ninguna sesión real puede llamarse `booking:…`, así que `releaseBySession()` de un vendedor nunca lo toca.
- Al crear la reserva, el bloqueo se inserta en la misma transacción que la reserva, después de controlar los boletos vigentes con `lockForUpdate()` sobre los viajes, igual que `CreateTicket::lockSaleTrips`. El índice único resuelve la carrera contra otra sesión: la violación se traduce con `SeatConflict` a "asiento no disponible".
- Confirmar, rechazar o cancelar borran las filas `booking:{id}` dentro de su transacción.
- *Alternativa descartada:* una consulta nueva que sume reservas de clientes a la disponibilidad. Habría que tocar las 15 consultas.

**3. Leyenda en el selector.** La API del selector (`SeatReservationController`) y el estado inicial de la vista (`seat-selector.blade.php`) separan, dentro de "reservados por otros", los que tienen `user_session_id` con prefijo `booking:`, y devuelven su `expires_at`. La vista les aplica otro color (tonos `amber`, que no es color de marca) y el tooltip "Reservado por cliente, vence dd/mm HH:mm". La lógica de elegibilidad no cambia.

**4. Viajes al reservar.** La reserva guarda `schedule_id` y `trip_date`, y obtiene el viaje con `Trip::findOrCreateForBooking()`, como la venta, para tener un `trip_id` donde bloquear el asiento. Crear un viaje vacío no afecta nada: es lo mismo que pasa cuando un vendedor busca un horario.

**5. Servicios.**
- `ClientBookingService::create(Clients $client, array $data)`: única entrada del lado del cliente. Valida función activa, baneo del cliente, máximo 2 pendientes, límite de pasajeros, días de servicio (`ServiceDays::blockReason(..., allowBypass: false)`), anticipación para reservar, corte, métodos habilitados, acompañantes, DNI baneados y duplicados (`TripPassengerConflicts`, más las reservas pendientes). Después calcula precios con `FixedTicketPrice` y `expires_at`, y crea la reserva y los bloqueos en una transacción.
- `ClientBookingService::cancel()`.
- `TicketBookingConfirmationService::confirm(Booking, User, array $seatOverrides)`: bloquea los viajes, revalida asientos (ignorando los bloqueos de la propia reserva), duplicados contra boletos y baneos, y crea `Sale::createNew($user->id)`, pasajeros y boletos con la misma forma que `CreateTicket` (ida y vuelta con `is_return_leg`, vuelta a precio 0, menor y mascotas, `payment_method`). Después recalcula el total, borra los bloqueos y marca la reserva confirmada.
- `reject()` con el motivo.
- *Por qué no extraer la emisión de `CreateTicket`:* es la pantalla más usada y sin un servicio intermedio el refactor sería grande y riesgoso. El servicio nuevo replica solo la forma de los registros. Un test de paridad compara un boleto confirmado con uno vendido en el mostrador con los mismos datos: mismos campos, `is_return_leg`, precios y `share_token`.

**6. Hora límite.** `expires_at = min(now + horas, salida_origen(primer tramo) − corte)`, con `ClientAdvanceNotice::departureAt()` para la salida del origen del servicio. Una clase `BookingWindow`, como `ClientAdvanceNotice`, concentra la anticipación para reservar, el corte y el cálculo, y la usan el selector, el servidor y el texto del cliente.

**7. Parámetros.** Claves en `Setting`:
- `bookings.activas` (bool, apagado)
- `bookings.anticipacion_horas` (24, de 0 a 720)
- `bookings.horas_reserva` (24, de 1 a 720)
- `bookings.corte_horas` (1, de 0 a 72)
- `bookings.metodos_pago` (JSON con códigos)
- `bookings.transferencia` (JSON con alias, CBU, titular y banco)

`Setting::reservasDisponibles()` = panel activo, reservas activas y precio fijo activo, y es la única condición del lado del cliente. Los métodos se filtran contra `PaymentMethod::options()` (activos) al leerlos, así un método desactivado deja de ofrecerse sin tocar el parámetro.
- *Supuestos propios, no discutidos:* tope del corte en 72 h, CBU de 22 dígitos, alias de 6 a 20 caracteres.

**8. Permisos.** Módulo `bookings`: `view_any`, `confirm`, `reject`, sin `create`, `update` ni `delete`, porque las reservas no se crean ni se borran desde el panel de administración. `settings.client_bookings`. Ninguno en `defaultsFor()`. `BookingPolicy` con `AuthorizesModule` para `viewAny`, y acciones con `can('bookings.confirm')`/`can('bookings.reject')`.

**9. Panel de administración.** `TicketBookingResource` en `app/Filament/Resources/TicketBookings/`, con `ManageRecords`, sin crear, editar ni borrar, acciones responsive (menú en celular y botones en escritorio) y `recordUrl(null)`. "Confirmar" abre un modal con el selector de asientos de cada tramo (los asientos ocupados se marcan y obligan a cambiar). Al confirmar, `TicketDelivery` suma `KIND_BOOKING` con el título "Reserva N°{id} confirmada". El listado lo abre como `defaultAction`, igual que `CreateTicket`. Con la descarga automática activada se abre el PDF de la venta, como en el mostrador. Widget `PendingBookingsWidget` en `CustomDashboard::getHeaderWidgets()`, con `canView()` por permiso y cantidad mayor a 0. Badge de navegación con la misma consulta.

**10. Panel de clientes.**
- Página `MyBookings` registrada en `ClientPanelProvider`. Su `canAccess()` y `shouldRegisterNavigation()` usan `Setting::reservasDisponibles()`, y su `mount` aborta con 404 si no.
- El asistente es una acción con `Wizard` en un modal, como "Elegir fecha", y reutiliza el selector de asientos del panel de clientes (`ClientTicketAssignForm`) para cada tramo. Los bloqueos temporales de 5 minutos de la sesión del cliente se usan mientras elige, como hoy en "Elegir fecha", y se reemplazan por los de la reserva al confirmar.
- Las acciones (cancelar, WhatsApp) siguen el patrón responsive.
- La identidad del cliente sale siempre de `ClientSession`: nunca de un id que llegue del navegador.

## Riesgos / Compromisos

- [El servicio de confirmación se desfasa de la venta del mostrador si mañana cambia cómo se crean los boletos] → El test de paridad falla ante cualquier diferencia de campos. Además, `design.md` lo deja escrito para quien cambie `CreateTicket`.
- [Un cliente bloquea asientos sin pagar] → Máximo 2 pendientes, horas de reserva configurables, cancelación por el cliente y rechazo por el personal liberan los asientos. Los bloqueos vencen solos.
- [Un viaje se borra en cascada y se llevan los bloqueos] → La reserva guarda su propio viaje y sus asientos. Confirmar revalida y ofrece otros asientos.
- [Leyenda del selector mientras la función está apagada] → Solo aparece si hay bloqueos vigentes. Es correcto mostrarla, porque el asiento sigue guardado.
- [`isFull()` no cuenta los bloqueos] → Aceptado: puede mostrar "hay lugar" cuando todos los libres están guardados. La venta igual no deja elegirlos.
- [El cliente cierra el navegador a mitad del asistente] → Solo quedan bloqueos de 5 minutos de su sesión, que vencen solos, como en "Elegir fecha".

## Plan de despliegue

1. `php artisan migrate`: dos tablas nuevas, sin tocar las existentes.
2. `php artisan permissions:sync` y asignar los permisos a mano.
3. `npm run build`, por las vistas nuevas y la leyenda del selector.
4. La función arranca apagada. Para usarla hay que prender el panel de clientes, el precio fijo y las reservas, y elegir los métodos de pago.

Rollback: apagar el interruptor. Si hace falta revertir el código, primero hay que resolver las pendientes. Las tablas pueden quedar.

## Preguntas abiertas

- Texto exacto de los mensajes y del aviso de abonar antes de la hora límite: se ajusta en la implementación, siguiendo las specs.
