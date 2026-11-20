# Tareas

## 1. Registro: tipo y motivo de cada cambio de fecha

- [x] 1.1 Crear con `php artisan make:migration` la migración que agrega a `ticket_date_changes` `kind` (string 20, NOT NULL, default `reschedule`) y `reason` (text nullable), y sumar a `TicketDateChange` las constantes `KIND_RESCHEDULE`/`KIND_OPEN_DATE`, `kind`/`reason` en `$fillable` y un helper `isOpenDate()`. La reprogramación existente pasa `kind` explícito al crear la fila. Verificar en `TicketRescheduleServiceTest` que una reprogramación normal queda con `kind = reschedule` y sin motivo, y que una fila creada sin `kind` toma el default.

## 2. Servicio: dejar con fecha abierta

- [x] 2.1 Agregar `TicketRescheduleService::openDate()` (diseño, decisiones 1 a 4): validaciones en orden (boleto activo y con fecha, los dos permisos, contraseña, padrón, alcance) y transacción con lock, UPDATE condicionado que pone `trip_id`/`seat_id` en NULL, `return_trip_id` de la ida en NULL al abrir la vuelta, y una fila `open_date` por tramo con `from_*`, motivo y autor. Verificar con tests en `TicketRescheduleServiceTest`: solo ida, solo vuelta, ambos, ambos con la vuelta ya pendiente (un registro), boleto ya sin fecha rechazado, viaje pasado permitido, número/venta/precio/colectivo conservados, asiento liberado y vendible de nuevo, fuera de la lista de pasajeros, sin `vender_sin_fecha` o sin `reschedule` rechazado sin cambios, contraseña incorrecta, pasajero fuera del padrón con el mensaje "Agregalo desde Clientes", motivo guardado y vacío.
- [x] 2.2 Verificar con tests que, después de `openDate()`, la reasignación funciona por los dos caminos: el personal reprograma con `reschedule()` (incluido el rechazo por orden ida → vuelta con la vuelta fechada), y el cliente con `ClientTicketAssignmentService::assign()` (registro como cliente, y bloqueado otra vez después).

## 3. Pantalla "Reprogramar boleto"

- [x] 3.1 Siguiendo el skill `filament-ui`, agregar a `TicketRescheduleForm` el selector "Nueva fecha" / "Dejar con fecha abierta" (solo con los dos permisos, con el criterio de "algún rol lo tiene" de la venta sin fecha, y solo si el boleto tiene fecha). Con fecha abierta se ocultan y dejan de ser obligatorios fecha, horario y asientos, y se muestra "Motivo" opcional con límite de longitud. Verificar en `RescheduleTicketPageTest`: visibilidad por cada combinación de permisos, oculto en un boleto sin fecha, y campos ocultos y no requeridos en modo fecha abierta.
- [x] 3.2 Derivar `confirmReschedule()` a `openDate()` en modo fecha abierta, con el mismo manejo de errores (notificación persistente, form conservado), la liberación de reservas temporales de la sesión y la entrega (PDF o `TicketDelivery` con el título "Boleto N°{id} quedó con fecha abierta"). Verificar en `RescheduleTicketPageTest`: éxito con descarga automática activa (PDF en sesión) y desactivada (redirección al detalle con el modal y su título), y el rechazo por padrón mostrado como notificación sin cambiar el boleto.

## 4. Historial de boletos

- [x] 4.1 En `ticket-audit-detail.blade.php`, mostrar los cambios `open_date` como "Dejó el boleto con fecha abierta", con viaje, horario y asiento de origen, "Realizado por" y el motivo solo si existe (respetando el modo oscuro). Verificar en `TicketAuditResourceTest`: el texto y el motivo, sin renglón de motivo cuando falta, una reprogramación con el viaje de destino borrado que no se muestra como fecha abierta, y que el boleto aparece en el filtro de reprogramados y en "Vendedores" y no en "Clientes".

## 5. Panel de clientes

- [x] 5.1 Sin cambios de código previstos. Verificar en `ClientPanelTicketsTest` que un boleto dejado con fecha abierta aparece en "Pendientes" con "Elegir fecha", suma en el conteo y en el aviso, sale de "Próximos" y de la tarjeta "Próximo viaje", y que la vuelta abierta rechaza una fecha anterior a la ida con el formulario abierto.

## 6. Integración

- [x] 6.1 Correr `vendor/bin/pint --dirty --format agent` y, con `php artisan test --compact`, los tests de los archivos tocados más los de reprogramación, Historial y panel de clientes que ya existían (`TicketRescheduleServiceTest`, `RescheduleTicketPageTest`, `RescheduleDoesNotAffectOtherSeatsTest`, `LineaRescheduleTest`, `TicketAuditResourceTest`, `ClientPanelTicketsTest`), y verificar que todo pasa. Después, preguntarle al usuario si quiere correr la suite completa.

## Workflow follow-up

- Deploy: correr `php artisan migrate`. No hay permisos nuevos que sincronizar.
- Archivar el cambio con `/opsx:archive` cuando esté implementado y revisado.
