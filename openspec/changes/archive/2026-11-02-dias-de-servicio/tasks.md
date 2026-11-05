# Tasks

## 1. Regla y configuración

- [x] 1.1 Agregar a `Setting` las claves `service_days.weekdays` y `service_days.exceptions` y sus getters con la configuración inicial como default (lunes a sábado, más la excepción 2026-09-13 con servicio). Verificar con tests unitarios de `Setting`: sin claves devuelve la configuración inicial; con claves guardadas devuelve lo guardado.
- [x] 1.2 Crear `App\Support\ServiceDays` (`hasService`, `exceptionFor`, `disabledDatesBetween`, `userCanBypass`, `blockReason`) según design D1. Verificar con tests de los 3 escenarios de "Fecha con servicio", la configuración inicial (domingos sin servicio salvo 13/09/2026) y la excepción repetida (gana la última).
- [x] 1.3 Agregar el permiso `tickets.vender_dia_sin_servicio` ("Vender en días sin servicio") al catálogo de `Permissions`, fuera de los defaults de roles. Verificar con un test (en `RolesAndPermissionsTest` o uno nuevo) que `permissions:sync` lo registra, que ningún rol lo recibe y que el superadmin lo tiene por el bypass.
- [x] 1.4 Agregar la sección "Días de servicio" a `TicketSettings` (días de la semana más el Repeater de excepciones con fecha, tipo y motivo), con validación de fecha repetida. Verificar en `TicketSettingsTest`: se cargan los valores actuales, se guarda un feriado y deja de haber servicio, y una fecha repetida se rechaza sin cambiar nada.

## 2. Venta

- [x] 2.1 Reemplazar `getDisabledTravelDates()` en los selectores de ida y de vuelta de `TicketForm` por `ServiceDays`, sin lista para quien tiene el permiso y con aviso cuando la fecha elegida no tiene servicio. Eliminar el método viejo. Verificar con un test de Livewire del wizard que, sin permiso, un feriado configurado no se puede elegir y que, con permiso, se elige y se muestra el aviso con el motivo.
- [x] 2.2 Bloquear el avance del paso 1 cuando la fecha de ida o de vuelta no tiene servicio y el usuario no tiene el permiso. Verificar con un test del wizard que no avanza e informa el día sin servicio.
- [x] 2.3 Agregar en `CreateTicket` el control final por tramo con fecha (análogo a `assertDepartureWithinLimit`). Verificar con tests: una emisión con fecha sin servicio y sin permiso se rechaza sin crear la venta; con permiso se emite; un tramo sin fecha no se evalúa.

## 3. Reprogramación y panel de clientes

- [x] 3.1 Aplicar `ServiceDays` a los selectores de nueva fecha de ida y de vuelta de `TicketRescheduleForm`. Verificar con un test de la página de reprogramación que, sin permiso, la fecha sin servicio queda deshabilitada.
- [x] 3.2 Validar en `TicketRescheduleService::rescheduleForActor()` las fechas de ida y de vuelta con el parámetro `allowServiceDayBypass` (design D6). Verificar en `TicketRescheduleServiceTest`: sin permiso se rechaza aunque sea la misma fecha del boleto; con permiso se reprograma.
- [x] 3.3 Aplicar la regla en el panel de clientes (`ClientTicketAssignForm`, calendario siempre filtrado, y `ClientTicketAssignmentService` pasando `allowServiceDayBypass = false`). Verificar en `ClientPanelTicketsTest` que la asignación en día sin servicio se rechaza aunque haya un superadmin autenticado en la misma sesión.

## 4. Crear viaje

- [x] 4.1 Aplicar `ServiceDays` al `DatePicker` de `trip_date` en `TripResource` (fechas deshabilitadas más una regla de validación con bypass por permiso). Verificar con un test del formulario del resource. El formulario no es accesible hoy porque `CreateAction` está comentado en `ManageTrips`.

## 5. Cierre

- [x] 5.1 Revisar los tests de venta y reprogramación existentes que usan fechas relativas a hoy, y fijar la fecha en un día hábil o configurar los 7 días con servicio para que no fallen los domingos. Verificar corriendo `php artisan test --compact` con la fecha simulada en un domingo y en un día hábil.
  - Resultado: ningún test usa fechas de viaje relativas a hoy. Las únicas fechas relativas son `sale_date` y `expires_at`, que la regla no evalúa, y todas las fechas de viaje son fijas. Por eso el resultado no depende del día en que se corra la suite, y no hizo falta simular un domingo. La suite completa pasa (267 tests). Además, `Tests\TestCase::setUp` limpia la caché estática de `Setting` para que la configuración no se filtre entre tests.
- [x] 5.2 Verificar que no se aplicó la regla fuera de alcance: los filtros por fecha de `TicketsTable` y `TripResource`, las fechas de pago y la fecha de venta siguen sin restricciones (revisión del diff y test de un filtro en domingo).
- [x] 5.3 Ejecutar `vendor/bin/pint --dirty --format agent` y los tests afectados (`php artisan test --compact --filter="ServiceDays|TicketSettings|TicketSale|Reschedule|ClientPanel|RolesAndPermissions"`), y verificar que pasan.
- [x] 5.4 Probar en el navegador: con la configuración inicial, el calendario de la venta queda igual que hoy (domingos deshabilitados, 13/09 habilitado). Con un feriado agregado en Parametros, se bloquea para un vendedor y se permite con aviso para el superadmin.
  - Hecho (2026-11-02, como superadmin): la sección "Días de servicio" de Parametros muestra la configuración inicial (lunes a sábado más la excepción 13/09/2026 con servicio). En la venta, el domingo 08/11/2026 se puede elegir y debajo aparece "El 08/11/2026 es un día sin servicio. Se permite por el permiso…".
  - Vista de vendedor (sin el permiso): verificada en el navegador por el usuario (2026-11-02).

## Workflow follow-up

- Archivar con `/opsx:archive`. El MODIFIED de `ticket-sale` reemplaza "Fechas de viaje seleccionables" y se crea `openspec/specs/service-days/spec.md`.
- En el deploy, ejecutar `php artisan permissions:sync` y asignar el permiso a los roles que corresponda.
