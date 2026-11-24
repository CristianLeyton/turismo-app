# Tareas

## 1. Datos, parámetros y permisos

- [ ] 1.1 Crear las migraciones de `ticket_bookings` y `ticket_booking_passengers` (diseño, decisión 1) con `php artisan make:migration`, más los modelos `TicketBooking` y `TicketBookingPassenger` con relaciones, casts, factories y el scope `pendingActive()` (pendiente con `expires_at > now`). Verificar con un test que una reserva pendiente vencida no entra en `pendingActive()` y se informa como "Vencida".
- [ ] 1.2 Agregar a `Setting` las claves, valores por defecto, topes y getters de reservas (decisión 7), incluido `reservasDisponibles()`, más los métodos de pago habilitados filtrados por activos y los datos para transferir. Verificar con tests de los getters: valores por defecto, acotado y un método inactivo que no se ofrece.
- [ ] 1.3 Agregar al catálogo de `Permissions` el módulo `bookings` (`view_any`, `confirm`, `reject`) y `settings.client_bookings`, sin defaults, y crear `TicketBookingPolicy`. Verificar en `RolesAndPermissionsTest` que existen después de `permissions:sync` y que ni Administrador ni Vendedor los tienen.

## 2. Parámetros > Reservas

- [ ] 2.1 Agregar a `TicketSettings` la pestaña "Reservas" después de "Clientes", con su permiso, visible según el interruptor del panel (del formulario si se ve Clientes, o el guardado si no), y con sus campos, validaciones en voseo, aviso del precio fijo apagado, `TAB_FIELDS` y guardado condicionado al permiso y a `array_key_exists`. Verificar en `TicketSettingsTest`: orden de pestañas, valores iniciales, aparece y desaparece con el interruptor, guardar con la pestaña oculta conserva los valores, valores inválidos (horas, corte y datos bancarios incompletos o CBU de 20 dígitos), aviso del precio fijo, sin el permiso no se ve y un pedido manipulado no cambia nada.

## 3. Bloqueo de asientos y ventana de reserva

- [ ] 3.1 Crear `BookingWindow` (anticipación para reservar, corte, `allows()`, `expiresAt()` y mensajes) usando `ClientAdvanceNotice::departureAt()`. Verificar con tests: un horario dentro de la anticipación se excluye; con anticipación 0 y corte 1 h no se ofrece uno que sale en 30 min; la hora límite queda cortada por horas de reserva o por la salida menos el corte.
- [ ] 3.2 Agregar a `SeatReservation` la creación y liberación de bloqueos `booking:{id}` y el prefijo como constante. Verificar con tests de `seat-reservation`: el bloqueo cuenta como reservado por otro para un vendedor; `releaseBySession()` de un vendedor no lo toca; vencido, `reserveSeats()` lo pisa; la limpieza borra solo los vencidos y no cambia el estado de las reservas.
- [ ] 3.3 Marcar los asientos de reservas de clientes en la API del selector y en `seat-selector.blade.php`, con otro color (`amber`, con su variante `dark:`) y el tooltip "Reservado por cliente, vence dd/mm HH:mm", sin cambiar qué se puede elegir. Verificar con un test de la API (lista separada con el vencimiento) y un test de la vista con el texto de la leyenda.

## 4. Crear y cancelar reservas (servicio)

- [ ] 4.1 Crear `ClientBookingService::create()` con todos los controles (decisión 5), precios congelados, `expires_at` y bloqueos en una transacción con los viajes bloqueados. Verificar con tests de servicio: reserva de ida y de ida y vuelta, monto con menor y con ida y vuelta, mascota sin costo, límite de pasajeros, DNI repetidos, tercera pendiente rechazada (una vencida no cuenta), cliente baneado, pasajero o menor baneado con el mensaje genérico, DNI con boleto o con otra reserva pendiente en el viaje, método no habilitado, acompañantes apagados, sin fecha rechazado, día sin servicio, anticipación y corte, asiento vendido o bloqueado, y función no disponible.
- [ ] 4.2 Agregar `ClientBookingService::cancel()` (solo la reserva pendiente propia, libera los bloqueos). Verificar con tests: cancelar libera los asientos; no se puede cancelar una confirmada, una rechazada, una vencida ni una ajena.

## 5. Panel de clientes: "Mis reservas"

- [ ] 5.1 Crear la página `MyBookings` y registrarla en `ClientPanelProvider`, visible y accesible solo con `Setting::reservasDisponibles()` (404 si no), con la lista propia, los estados (incluida "Vencida"), el motivo de rechazo, el acceso a "Mis boletos" en las confirmadas y el estado vacío. Verificar con tests de acceso (cada condición apagada da 404 y no figura en el menú; "Mis boletos" no cambia) y de contenido (solo reservas propias, aunque el cliente sea pasajero de otra).
- [ ] 5.2 Agregar el asistente "Reservar" (tramos, asientos con el selector del panel de clientes, pasajeros con el cliente precargado, acompañantes, método de pago y resumen con monto y hora límite) que llama al servicio, más el botón deshabilitado con su aviso para cliente baneado o con 2 pendientes. Verificar con tests de Livewire: precarga, reserva completa, cerrar sin confirmar no crea nada, errores del servicio debajo del campo correspondiente, y los dos avisos.
- [ ] 5.3 Mostrar en cada reserva pendiente el número, el monto, la hora límite con el aviso de abonar, los datos para transferir si están cargados y las líneas de contacto con WhatsApp precargado, más la acción "Cancelar" con confirmación (responsive). Verificar con tests: con y sin datos bancarios, el link de WhatsApp con el mensaje exacto, y cancelar desde la página.

## 6. Panel de administración: revisar reservas

- [ ] 6.1 Crear `TicketBookingResource` (`ManageRecords`, sin crear, editar ni borrar) con columnas, orden de pendientes por hora límite, filtro por estado, badge de navegación y acceso solo con `bookings.view_any`, disponible aunque la función esté apagada. Verificar con tests: acceso con y sin permiso, orden, "Vencida" calculada, y la tabla con la función apagada.
- [ ] 6.2 Crear `TicketBookingConfirmationService` (decisión 5) y su test de paridad contra una venta del mostrador con los mismos datos. Verificar con tests: venta a nombre de quien confirma, precio congelado aunque el precio fijo cambió o se apagó, ida y vuelta con la vuelta a 0, bloqueos borrados, vencida confirmable con asientos libres, viaje ya salido rechazado, asiento ocupado exige otro, DNI con boleto vendido después de reservar rechazado sin crear nada, pasajero baneado, y una reserva ya procesada no se confirma dos veces.
- [ ] 6.3 Agregar las acciones "Confirmar" (modal con los asientos de cada tramo para cambiarlos) y "Rechazar" (motivo obligatorio de hasta 280 caracteres), responsive y con permiso, y el modal de entrega con `KIND_BOOKING` en `TicketDelivery`/`TicketDeliveryAction` (o el PDF con la descarga automática). Verificar con tests: visibilidad por permiso y estado, cambiar asiento, rechazar sin motivo muestra "Ingresá el motivo del rechazo.", rechazar libera los asientos y el cliente ve el motivo, y el modal "Reserva N°{id} confirmada".
- [ ] 6.4 Crear el widget "Reservas por verificar" en `CustomDashboard` (cantidad, próxima hora límite y acceso al listado, solo con el permiso y con pendientes). Verificar con tests: con pendientes, sin pendientes y sin el permiso.

## 7. Integración

- [ ] 7.1 Test de extremo a extremo: el cliente reserva, el vendedor ve el asiento con la leyenda y no lo puede vender, el personal confirma, y los boletos aparecen en "Mis boletos", en la lista de pasajeros del viaje y en "Mis ventas" de quien confirmó. Con la reserva pendiente, nada de eso muestra boletos. Verificar que pasa.
- [ ] 7.2 Correr `vendor/bin/pint --dirty --format agent` y los tests de los archivos tocados con `php artisan test --compact` (más los de venta, reprogramación, "Elegir fecha" y Parámetros que ya existían) y verificar que todo pasa. Después, preguntarle al usuario si quiere correr la suite completa y avisarle que hace falta `npm run build`.
- [ ] 7.3 Actualizar la memoria de pendientes con los pasos de deploy (migrate, `permissions:sync`, asignar permisos, `npm run build`) y verificar que quedó anotado.

## Workflow follow-up

- Probar en el navegador el flujo completo, en celular y escritorio, con modo oscuro.
- Archivar el cambio con `/opsx:archive` una vez revisado.
