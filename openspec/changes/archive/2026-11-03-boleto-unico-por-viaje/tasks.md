# Tasks

## 1. Consulta de conflictos y error tipado

- [x] 1.1 Crear `App\Support\TripPassengerConflicts::find(array $dnis, array $tripIds, array $excludeTicketIds = [])`, según la decisión 1 del diseño. Coincidencias por DNI del titular o de su menor, boletos activos con viaje, DNI con `trim()` y vacíos descartados, eager loading de `passenger.children`, `trip.schedule` y `trip.bus`. Se verifica con los tests de 1.3.
- [x] 1.2 Crear `DuplicatePassengerOnTripException extends ValidationException`, que lleva los conflictos y cuyos `errors()` traen "Ya tenés un boleto en ese viaje. Elegí otra fecha u horario.". Se verifica con un test que la lanza y lee `conflicts()` y `errors()`.
- [x] 1.3 Crear el test (`php artisan make:test --phpunit TripPassengerConflictsTest`) con estos casos:
  - adulto contra adulto,
  - adulto contra menor y menor contra adulto,
  - menor contra menor,
  - boleto borrado no cuenta,
  - boleto sin fecha no cuenta,
  - DNI vacío ignorado,
  - boleto excluido no cuenta,
  - otro viaje del mismo día (refuerzo) no cuenta.

  Ejecutar `php artisan test --compact tests/Feature/TripPassengerConflictsTest.php` y que pase.

## 2. Alerta de administración

- [x] 2.1 Crear `App\Support\DuplicateTicketNotification::send(Collection $conflicts, string $backLabel)`, según la decisión 3 del diseño:
  - persistente y `danger`,
  - una línea por DNI con boleto, fecha, hora y colectivo,
  - una acción "Ver boleto N°X" por boleto, que abre en pestaña nueva la vista del boleto,
  - la acción de volver con `close()`.

  Se verifica con un test que envía la notificación con dos conflictos y comprueba título, cuerpo, URL de cada acción y apertura en pestaña nueva.

## 3. Venta

- [x] 3.1 En `TicketForm`, agregar al `dni` del pasajero y a `child_data.dni` la regla de DNI distintos dentro de la venta, sumando adultos y menores, con el mensaje "Este DNI ya está cargado en otro pasajero de la venta.". Se verifica con tests en `TicketSaleDniValidationTest`: dos adultos iguales, un menor igual a un adulto y DNI distintos válidos.
- [x] 3.2 En el paso "Pasajeros" de `TicketForm`, agregar un `afterValidation` que, si algún DNI ya tiene boleto en los viajes con fecha, abre el modal `CreateTicket::duplicatePassengerAction()` (título, conflictos, "Ver boleto N°X" en otra pestaña y "Volver") y detiene el avance. El campo DNI no muestra este aviso. Se verifica con tests en `TicketSaleDniValidationTest`: el modal se abre con sus acciones, no se abre sin duplicados y el campo DNI no marca error.
- [x] 3.3 En `CreateTicket::handleRecordCreation()`, antes del `try`, agregar dos guards:
  - DNI repetidos en la venta, con alerta y `halt()`;
  - conflictos en los viajes de ida y vuelta con fecha, con `DuplicateTicketNotification` ("Volver a la venta") y `halt()`.

  Se verifica con un test nuevo `TicketSaleDuplicatePassengerTest`:
  - duplicado en la ida y duplicado solo en la vuelta, sin venta, pasajeros ni boletos creados;
  - emisión forzada con DNI repetidos, rechazada;
  - usuario con todos los permisos, también bloqueado;
  - venta válida sin conflicto, se emite.
- [x] 3.4 En `CreateTicket`, dentro de `DB::transaction` y después de `lockSaleTrips()`, repetir el chequeo. Si hay conflictos, lanzar la excepción tipada y capturarla antes del `catch` genérico: alerta, liberación de reservas y `halt()`. Se verifica con un test que crea el boleto en conflicto después del guard previo y comprueba que no se crea nada y que se ve la alerta.

## 4. Reprogramación y panel de clientes

- [x] 4.1 En `TicketRescheduleService::rescheduleForActor()`, dentro de la transacción y antes de cualquier `moveLeg()`:
  - bloquear los viajes destino ordenados por id,
  - llamar a `find()` con los DNI del titular y de su menor,
  - excluir los boletos que se mueven,
  - si hay conflicto, lanzar la excepción tipada.

  Se verifica con tests en `TicketRescheduleServiceTest`:
  - viaje destino con el mismo DNI, rechazado y boleto sin cambios;
  - cambio de asiento en el mismo viaje, permitido;
  - alcance `both` con conflicto solo en la vuelta, sin mover ningún tramo;
  - asignación del primer viaje a un boleto sin fecha, rechazada;
  - menor que ya viaja, rechazado.
- [x] 4.2 En `RescheduleTicket::confirmReschedule()`, capturar primero la excepción tipada y mostrar `DuplicateTicketNotification` con "Volver", conservando el formulario. Se verifica con un test en `RescheduleTicketPageTest` que comprueba la notificación con la acción "Ver boleto N°X".
- [x] 4.3 Verificar que el panel de clientes muestra el rechazo sin cambios de código. Se verifica con un test en `ClientPanelTicketsTest`: el cliente elige un viaje donde ya tiene boleto, el boleto sigue sin fecha y ve "Ya tenés un boleto en ese viaje. Elegí otra fecha u horario." sin enlaces de administración.

## 5. Edición de boletos

- [x] 5.1 En `EditTicket::performSave()`, detectar si cambió el DNI del titular, si cambió el DNI del menor o si se agregó un menor. Solo en ese caso:
  - dentro de la transacción, bloquear los viajes de los boletos activos del pasajero,
  - llamar a `find()` con los DNI nuevos, excluyendo esos boletos,
  - ante un conflicto, revertir y mostrar `DuplicateTicketNotification` con "Volver".

  Se verifica con tests en `TicketEditTest`:
  - cambio de DNI a uno que ya viaja, rechazado sin cambios guardados;
  - conflicto en el viaje de vuelta del pasajero, rechazado;
  - agregar un menor que ya viaja, rechazado;
  - editar solo el teléfono de un boleto ya duplicado, se guarda.

## 6. Cierre

- [x] 6.1 Ejecutar `vendor/bin/pint --dirty --format agent`. Ejecutar los archivos de test tocados: `TripPassengerConflictsTest`, `TicketSaleDniValidationTest`, `TicketSaleDuplicatePassengerTest`, `TicketRescheduleServiceTest`, `RescheduleTicketPageTest`, `ClientPanelTicketsTest`, `TicketEditTest`, `LineaRescheduleTest` y `LineaSaleSearchTest`. Todos tienen que pasar.
- [x] 6.2 Prueba manual en el navegador:
  - venta duplicada con la alerta, abrir "Ver boleto existente" en otra pestaña y volver a la venta con los datos intactos;
  - reprogramación duplicada;
  - edición de DNI duplicada.

## 7. Ajustes tras la prueba manual

- [x] 7.1 Rediseñar el modal del paso "Pasajeros" y la alerta: título según cantidad de personas, indicación de qué hacer, una tarjeta por persona (vista `filament.tickets.duplicate-passenger-modal`) con "Ver boleto" y "Volver a pasajeros". Se verifica con `DuplicateTicketNotificationTest` y `TicketSaleDniValidationTest`.
- [x] 7.2 Avisar al presionar "Finalizar" si faltan los asientos de ida o de vuelta porque la reserva venció, con el texto que indica volver con «Anterior». Se verifica con `SaleExpiredSeatsNotificationTest`.
- [x] 7.3 Ejecutar la suite completa con `php artisan test --compact` y que pase.

## Workflow follow-up

- Archivar el cambio con `/opsx:archive` una vez revisado.
