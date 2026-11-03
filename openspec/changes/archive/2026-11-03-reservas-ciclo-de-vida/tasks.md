# Tasks

## 1. Modelo y API de reservas

- [x] 1.1 Agregar `SeatConflict::isReservationViolation()` (design D4), que reconozca la violación de `unique_trip_seat_reservation` en MySQL (nombre del índice) y en SQLite (columnas `seat_reservations.trip_id` y `seat_reservations.seat_id`), y usarlo en `SeatReservation::reserveSeats`. Verificar con tests en un archivo nuevo `tests/Feature/SeatReservationLifecycleTest.php`: el mensaje de MySQL como fixture y una violación real en SQLite.
- [x] 1.2 En `SeatReservation::reserveSeats`, eliminar antes del insert las reservas vencidas de los asientos pedidos, de cualquier sesión (design D4). Verificar con tests: una reserva vencida de otra sesión no impide reservar ese asiento (antes: 500 en SQLite, 409 en MySQL); una reserva vigente de otra sesión sigue rechazándose con 409; las vencidas de otros asientos no se tocan.
- [x] 1.3 Agregar el parámetro opcional `?array $tripIds` a `SeatReservation::releaseBySession` y `extendReservationTime`, y aceptar `trip_ids` (array de enteros, opcional) en `POST /api/seat-reservations/release` y `/keep-alive` (design D1). Verificar con tests de los endpoints: con `trip_ids` solo se liberan o renuevan las reservas de esos viajes; sin el parámetro, todas las de la sesión (compatibilidad); las reservas vencidas no se renuevan.

## 2. Disponibilidad del paso 1

- [x] 2.1 Agregar `Trip::availableSeatsCountForSession(string $sessionId): int` (design D5). Verificar con tests: descuenta asientos inactivos, boletos vigentes y reservas vigentes de otra sesión; cuenta como libres las reservas propias y las vencidas de otros; ignora boletos dados de baja.
- [x] 2.2 Usar ese cálculo en `TicketForm` para el paso 1 (ida y vuelta, TF:125, 262, 804, 918) y para la visibilidad del selector y del aviso de asientos insuficientes en los pasos 2 y 3. Verificar con un test Livewire del asistente: con 4 asientos sin boleto, uno inactivo y otro reservado por otra sesión, una venta de 3 pasajeros no avanza y avisa "El viaje tiene 2 asiento(s) disponible(s), pero necesita 3."; con las reservas propias, avanza.

## 3. Liberación acotada a la venta

- [x] 3.1 Eliminar `CreateTicket::__destruct` y agregar `saleTripIds()`, que devuelve `trip_id`, `return_trip_id`, `reserved_trip_id` y `reserved_return_trip_id` sin nulos ni repetidos (design D1). Verificar con un test: cargar la página de venta (GET) con reservas vigentes de la sesión no las elimina.
- [x] 3.2 Acotar a `saleTripIds()` la liberación en la cancelación, en el rechazo por asiento tomado (CT:183, 240), en el catch de la emisión y después de una emisión exitosa. Verificar con tests: cancelar libera las reservas de la ida y la vuelta de la venta y conserva las de la sesión en otro viaje; una emisión exitosa y una fallida hacen lo mismo. Actualizar los tests existentes que esperaban la liberación total, si los hay.
- [x] 3.3 En el selector, hacer que `releaseReservations()` mande `trip_ids: [this.tripId]` con `keepalive: true`, y que cada selector libere su viaje al recibir `seatReservationExpired` (design D1). Verificar en el navegador con dos pestañas de la misma sesión y viajes distintos: cerrar una (confirmando la salida) y dejar vencer su cuenta regresiva no borra las reservas de la otra (comprobar en `seat_reservations`).
  - Verificado en Chrome con tres pestañas de la misma sesión (viajes 704 y 712). Abrir una pestaña nueva no borró la reserva de otra; el cierre (simulado con `beforeunload` + `pagehide`, para no disparar el diálogo de salida) y el vencimiento forzado de la cuenta regresiva liberaron solo el 712 y conservaron el 704, con el aviso "Asientos expirados".
  - Hallazgo durante la verificación: Chrome 156 no ejecuta listeners de `unload` (`document.featurePolicy.allowsFeature('unload')` es `false`), así que el cierre de página nunca liberaba. Se cambió a `pagehide` (design D1).
  - Segundo hallazgo: los listeners de `window` de un selector destruido (por ejemplo, el del viaje anterior tras cambiar la búsqueda) seguían vivos y, al vencer la cuenta regresiva, liberaban también ese viaje viejo, que era el de otra pestaña. Ahora se registran por componente y se quitan en `destroy()`. Verificado de nuevo con dos pestañas nuevas (D pasó del 704 al 712, E en el 704): el vencimiento y el cierre de D liberaron solo el 712.

## 4. Cambio de viaje en el paso 1

- [x] 4.1 Agregar los campos ocultos `reserved_trip_id` y `reserved_return_trip_id` y, en el `afterValidation` del paso 1, liberar el viaje de reserva abandonado de cada tramo y limpiar sus asientos y datos de pasajeros, eliminando `static $lastSearchHash` (design D3). Verificar con tests Livewire del asistente: cambiar el horario de la ida libera las reservas del viaje anterior y deja vacía la selección; volver al paso 1 sin cambiar el viaje conserva las reservas y la selección; pasar la vuelta a "sin fecha" libera la reserva de la vuelta.
  - Se limpia solo la selección de asientos del tramo. Los datos de pasajeros se conservan: viven en el repeater `passengers`, que no depende del asiento (`passenger_data`, citado en el diseño, es una clave que no existe en el formulario).
- [x] 4.2 Verificar en el navegador el escenario de H3: reservar un asiento, volver al paso 1, cambiar la fecha y avanzar. La reserva del viaje anterior desaparece de `seat_reservations` y no aparece el aviso de "asientos expirados".
  - Se reservó el asiento 13 del viaje 704 (20/11), se volvió al paso 1, se cambió la fecha al 21/11 y se avanzó: la reserva del 704 desapareció de `seat_reservations`, el selector del viaje nuevo (712, creado por el paso 1) apareció sin asientos elegidos y no hubo aviso de "asientos expirados".

## 5. Renovación por actividad

- [x] 5.1 Eliminar `startKeepAlive`, `stopKeepAlive` y `keepAliveInterval` del selector y agregar la escucha de `input`, `change`, `click` y `keydown` sobre el documento, que con asientos elegidos llame a `keep-alive` con `trip_ids: [this.tripId]` como mucho una vez cada 60 segundos (límite global en `window`) y actualice la cuenta regresiva con el `expires_at` de la respuesta (design D2). Correr `npm run build`.
- [x] 5.2 Verificar en el navegador: con asientos reservados, pasar más de 5 minutos en el paso de pasajeros cargando datos; la cuenta regresiva se renueva, hay pedidos a `keep-alive` (no más de uno por minuto) y la emisión pasa. Sin interactuar, la reserva vence a los 5 minutos con el aviso de asientos expirados.
  - Pestaña E: asiento elegido a las 01:25:27 en el viaje 704; en el paso de pasajeros se tipeó un carácter cada ~66 s. Hubo 4 pedidos a `keep-alive` (uno por minuto como máximo; un segundo tipeo dentro del mismo minuto no generó pedido) y la reserva siguió vigente después del vencimiento original (01:30:27). La venta 14682 (boleto 27274) se emitió a las 01:32:15 y liberó la reserva.
  - Sin actividad: la pestaña A, sin tocar desde las 01:17, venció sola a las 01:22 con el aviso "Asientos expirados".
  - La cuenta regresiva del navegador puede quedar unos segundos por encima del vencimiento real: al re-renderizarse, el selector reinicia su temporizador local a 5 minutos (comportamiento previo a este change).
- [x] 5.3 Verificar en el navegador que la reprogramación de un boleto sigue funcionando con el selector modificado: elegir asiento, reprogramar y comprobar que la reserva de ese viaje se libera o vence.
  - Boleto 27274 reprogramado del viaje 704 (asiento 15) al 712 (asiento 20): el selector reservó el asiento 20 y, al confirmar, la reserva se liberó.

## 6. Limpieza de código muerto

- [x] 6.1 Borrar `app/Console/Kernel.php` y `config/schedule.php` (design D4). Verificar que `php artisan schedule:list` sigue respondiendo "No scheduled tasks have been defined." y que `php artisan app:cleanup-expired-reservations` sigue funcionando.

## 7. Cierre

- [x] 7.1 Ejecutar `vendor/bin/pint --dirty --format agent` y la suite completa (`php artisan test --compact`), y verificar que pasa.
- [x] 7.2 Actualizar en `docs/deploy.md` la sección 7 con un aviso para este deploy: requiere `npm run build` (cambia el selector) y no tiene migraciones. Verificar que el aviso está.

## Workflow follow-up

- Archivar con `/opsx:archive` y sincronizar las specs.
- Siguen pendientes los changes `marca-tramo-vuelta` (H2) y `api-reservas-segura` (H5).
