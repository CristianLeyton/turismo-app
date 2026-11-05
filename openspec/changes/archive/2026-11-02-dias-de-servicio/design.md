# Design

## Context

- La única regla de días que existe hoy es `TicketForm::getDisabledTravelDates()` (TF:2801): arma la lista de domingos desde un año atrás hasta dos años adelante, salvo el 2026-09-13, y la pasa a `disabledDates()` de los dos selectores de la venta (TF:531 y 992). No hay control en el servidor.
- La venta fuera de término ya resolvió un problema parecido con `App\Support\SaleCutoff`: una clase de soporte con la regla, que consumen el calendario (`minDate`, opciones), el paso 1 (`Halt`) y el control final de `CreateTicket`. Este diseño copia ese patrón.
- La configuración vive en `settings` (clave/valor, `value` de tipo `text`) y se edita en `TicketSettings` ("Parametros"). `Setting` tiene una caché por request y getters con valor por defecto cuando falta la clave.
- Los permisos están en el catálogo de `App\Support\Permissions`. Hay permisos "habilitables por bandera" (`vender_sin_fecha`, `vender_pasado_limite`) que no vienen en los roles por defecto y que `syncToDatabase()` nunca revoca. El superadmin pasa todo por `Gate::before`.
- La reprogramación del admin y la asignación del panel de clientes comparten `TicketRescheduleService::rescheduleForActor()`. El panel de clientes no usa el guard `web`: el cliente se identifica por DNI (`ClientSession`). Pero `Auth::user()` puede devolver un administrador logueado en el mismo navegador.
- **Crear viaje está deshabilitado hoy:** `CreateAction::make()` está comentado en `ManageTrips.php:17`, así que el `DatePicker` de `trip_date` (`TripResource.php:87`, `visibleOn('create')`) no se muestra en ningún lado. Los viajes se crean solos desde la venta y la reprogramación (`Trip::findOrCreateForBooking`).

## Goals / Non-Goals

**Goals:**
- Una sola fuente de verdad para "¿esta fecha tiene servicio?" y "¿este usuario puede operar en días sin servicio?".
- Que el calendario y el servidor nunca diverjan: los dos consultan la misma clase.
- Que, con la configuración inicial, el comportamiento sea idéntico al actual.

**Non-Goals:**
- Servicio por colectivo, ruta u horario: la configuración es global.
- Reabrir el formulario de crear viaje: queda como está; solo se le aplica la regla.
- Mostrar en el calendario los días sin servicio con otro color o estilo: el `DatePicker` de Filament solo permite habilitar o deshabilitar días. El aviso va como texto junto al campo.
- Revalidar boletos o viajes existentes.

## Decisions

### D1. Clase de soporte `App\Support\ServiceDays`, al estilo de `SaleCutoff`
Concentra: `hasService(date)`, `exceptionFor(date)` (para el motivo), `disabledDatesBetween(from, to)` para los calendarios, `userCanBypass()` (`can('tickets.vender_dia_sin_servicio')`) y `blockReason(date, allowBypass)`, que devuelve el mensaje o `null`.
- *Alternativa:* un servicio inyectable con interfaz. Se descartó porque no hay otra implementación prevista y `SaleCutoff` ya fija la convención estática del proyecto.

### D2. Almacenamiento en `settings` como JSON, sin tabla nueva
Dos claves: `service_days.weekdays` (lista de enteros ISO 1-7) y `service_days.exceptions` (lista de `{date, has_service, reason}`). `Setting` suma getters `serviceWeekdays()` y `serviceExceptions()` que, si falta la clave, devuelven la configuración inicial (lunes a sábado y la excepción 2026-09-13 con servicio).
- *Por qué:* respeta la decisión de guardar todo en `Setting`, no necesita migración y la cantidad de excepciones es chica (decenas por año).
- *Alternativa:* tabla `service_day_exceptions`. Da unicidad por índice y consultas por rango, pero agrega modelo, migración y factory para pocos datos. Si las excepciones crecen mucho, se puede migrar sin cambiar la spec.
- La unicidad por fecha se valida en el formulario (Repeater) antes de guardar.
- La configuración inicial va en el código (default del getter) y no en una migración de datos. Así un entorno nuevo arranca igual sin seeders, y guardar la página persiste los valores visibles.

### D3. Interfaz en "Parametros"
Nueva sección "Días de servicio" en `TicketSettings`: `CheckboxList` con los 7 días (lunes primero) y `Repeater` de excepciones (`DatePicker` de fecha, `ToggleButtons` o `Select` "Con servicio"/"Sin servicio", `TextInput` de motivo). Hay validación de fecha repetida y se ordena por fecha al guardar.

### D4. Selectores: `disabledDates` condicionado al permiso, más aviso
Los 6 selectores usan `disabledDates(fn () => ServiceDays::userCanBypass() ? [] : ServiceDays::disabledDatesBetween(...))`, en el mismo rango que hoy. En el panel de clientes nunca se omite la lista (D6). Con permiso, el aviso se muestra con `hint`/`helperText` reactivo según la fecha elegida, usando `blockReason`.

**Ajuste durante la implementación:** los selectores de la reprogramación y del panel de clientes eran nativos (`<input type="date">`), y en ese modo Filament ignora `disabledDates`. Se pasaron a `native(false)` con `displayFormat('d/m/Y')`, igual que los de la venta. Cambia el aspecto del calendario en esas dos pantallas, también en mobile para los clientes.

### D5. Control en el servidor en cada punto de confirmación
- **Venta:** en el paso 1, los selectores de ida y de vuelta llevan una regla de validación propia (`serviceDayRule`), así que el wizard no avanza y el error aparece debajo del campo. Se eligió en lugar de `Halt` más una notificación en `afterValidation` porque la misma regla vuelve a correr al enviar el formulario y deja el mensaje junto a la fecha. En `CreateTicket`, `assertServiceDay()` (análogo a `assertDepartureWithinLimit()`) se aplica a cada tramo con fecha; los tramos sin fecha no se evalúan.
- **Reprogramación:** `TicketRescheduleService::rescheduleForActor()` valida la fecha de ida y la de vuelta, cuando vienen, con `ValidationException`.
- **Panel de clientes:** `ClientTicketAssignmentService::assign()` valida antes de delegar y avisa al servicio que no hay bypass (D6).
- **Crear viaje:** una regla de validación en el `DatePicker` de `trip_date` (una closure `rule`), porque no hay una página propia donde enganchar el control.

### D6. Sin bypass en el panel de clientes, explícito y no por omisión
`rescheduleForActor()` recibe un parámetro `allowServiceDayBypass` (por defecto `true` desde el admin). `ClientTicketAssignmentService` pasa `false`, y también filtra el calendario sin consultar el permiso. Así un administrador logueado en el mismo navegador no habilita días sin servicio en el panel de clientes.
- *Alternativa:* deducir el contexto del panel actual (`Filament::getCurrentPanel()`). Es más frágil, porque el servicio se usa fuera de Filament y en tests.

### D7. Permiso nuevo en el módulo `tickets`
Se agrega `vender_dia_sin_servicio => 'Vender en días sin servicio'` al catálogo, junto a `vender_pasado_limite`, y se lo excluye de los defaults de los roles, igual que los otros permisos por bandera. Aunque también habilita crear viajes, va en `tickets` porque es la operación principal y así lo decidió el usuario.

## Risks / Trade-offs

- [Los tests que usan fechas relativas a hoy pueden fallar si se corren un domingo, porque ahora el servidor rechaza esa fecha] → Fijar la fecha con `travelTo` en un día hábil, o configurar los 7 días con servicio en el `setUp` de los tests de venta y reprogramación existentes. Los tests nuevos deben fijar la fecha.
- [El JSON en `settings` no garantiza unicidad por fecha si alguien escribe la clave a mano] → `ServiceDays` toma la última excepción de cada fecha. La UI valida antes de guardar.
- [La lista de `disabledDates` cubre 3 años (~1100 días evaluados por render)] → Es el mismo costo que hoy. Las claves se leen una vez por request gracias a la caché de `Setting`.
- [Cambiar la configuración mientras un vendedor tiene el wizard abierto] → El control en el servidor del paso 1 y de la emisión usa la configuración vigente, así que el calendario desactualizado no permite vender en un día bloqueado.
- [El formulario de crear viaje no es accesible hoy, así que su regla no se puede probar en el navegador] → Cubrirla con un test del formulario del resource. Queda documentado que hoy está deshabilitado.

## Migration Plan

1. Deploy normal. No hay migraciones: sin las claves nuevas, rige la configuración inicial, igual al comportamiento actual.
2. Ejecutar `php artisan permissions:sync`, o el mecanismo actual, para registrar `tickets.vender_dia_sin_servicio`. No se asigna a ningún rol.
3. Asignar el permiso por rol desde la matriz, si hace falta.
4. Rollback: revertir el código. Las claves de `settings` quedan sin uso y no afectan nada.
