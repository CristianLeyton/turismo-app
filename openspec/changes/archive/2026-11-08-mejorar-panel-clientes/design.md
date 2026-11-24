# Design

## Context

Ver `proposal.md` para la motivación. Estado actual:

- `MyTickets` es el dashboard del panel `clientes`: una tabla de Filament sobre `ClientTicketAssignmentService::ticketsForCurrentClient()` con una propiedad `$mostrarFechados` y un switch HTML en `my-tickets.blade.php`.
- Los boletos eliminados no entran en la consulta porque `Ticket` usa SoftDeletes con el scope global.
- Un boleto está "pendiente" cuando `trip_id` es nulo (`isPendingDate()`); la fecha real está en `trips.trip_date` y el horario en `schedules.departure_time`.
- `Setting::contactLines()` ya devuelve `name`, `phone` y `dial` (para `tel:`); `login-contact-lines.blade.php` las muestra en el ingreso.
- `SetPassword` es una `Page` con propiedades Livewire y una vista de ~100 líneas con HTML y SVG propios.
- El panel de clientes comparte el tema compilado del admin; las clases Tailwind nuevas requieren `npm run build`.

## Goals / Non-Goals

**Goals:**
- Que "Mis boletos" use componentes de Filament y las convenciones del skill `filament-ui` (pestañas, acciones responsive, `primary`, dark mode).
- Que las pestañas, los conteos, el aviso y la tarjeta salgan de las mismas consultas, sin duplicar reglas.
- Que ningún error inesperado muestre `getMessage()` al cliente.

**Non-Goals:**
- Cambiar o cancelar un boleto que ya tiene fecha: sigue siendo solo del personal.
- Notificaciones, recordatorios o compra desde el panel.
- Cambios en el panel de administración, en permisos o en la base de datos.

## Decisions

**1. Pestañas con `Filament\Schemas\Components\Tabs`/tabs de la página, no con la propiedad `$mostrarFechados`.**
Cada pestaña modifica la consulta de la tabla y su badge sale de un conteo. Se prefieren las pestañas nativas de la tabla (`getTabs()` con `Filament\Schemas\Components\Tab`), que ya integran la consulta, el badge y la pestaña activa. Alternativa descartada: mantener el switch y agregar más filtros, porque mezcla tres estados en un on/off y obliga a mantener HTML propio.

**2. Reglas de pestañas en un solo lugar, en el servicio.**
`ClientTicketAssignmentService` expone consultas con nombre: boletos pendientes, próximos, historial, y el próximo viaje. Pendiente = `trip_id` nulo; Próximos = viaje con `trip_date >= hoy`; Historial = viaje con `trip_date < hoy`. El conteo de las pestañas, el aviso y la tarjeta reutilizan esas consultas. Los conteos se piden con `count()` por pestaña en una sola consulta agrupada si hace falta, nunca por fila. Alternativa descartada: calcular en la vista con colecciones, porque duplica reglas y trae todos los boletos a memoria.

**3. Próximo viaje: orden por fecha y horario del viaje, desempate por número.**
Se ordena por `trips.trip_date` y `schedules.departure_time` con subconsultas (como ya hace la columna "Fecha y horario"), y luego por `tickets.id`. Un viaje de hoy cuenta aunque su horario ya pasó: el spec lo define por fecha, no por hora, para no ocultar el viaje en curso. La tarjeta es una vista Blade propia (`filament.client.next-trip`) con `primary` y `dark:`.

**4. Aviso de pendientes con un `Action` de Filament.**
El botón del aviso monta la misma acción `asignar_fecha` sobre el boleto pendiente de menor número (`mountTableAction('asignar_fecha', $id)`), para no duplicar el formulario ni la defensa server-side. El aviso es una vista Blade con `x-filament::button`, visible solo con pendientes.

**5. Acciones de fila en las dos variantes.**
Se aplica el patrón obligatorio del skill: un `ActionGroup` con `md:hidden` y botones de ícono con `hidden md:inline-flex`. Cada acción (`asignar_fecha`, `boleto_pdf`) se envuelve en un método para llamarla en las dos variantes. Se mantienen `recordUrl(null)` y `recordAction(null)`.

**6. Badges con `TextColumn::badge()`.**
Reemplazan a `BadgeColumn` (deprecada en Filament 4) con los mismos textos y colores del skill: pendiente `warning`, confirmado `success`.

**7. Errores sin excepción visible.**
En `MyTickets` y `SetPassword`, el `catch (Throwable)` hace `report($e)` y notifica un texto fijo en voseo. Las `ValidationException` conservan su mensaje porque son errores esperados y entendibles.

**8. "Elegir contraseña" con un `Schema` de Filament.**
Se reemplaza la vista por un formulario del panel con `TextInput::password()->revealable()` y `same()`/`minLength(6)`, con mensajes de validación en voseo, y un botón "Guardar contraseña". Se mantiene la ruta `clave`, el middleware `EnsureClientPasswordIsSet` y la acción "Salir". Alternativa descartada: conservar la vista y solo cambiar el texto, porque deja el HTML y los SVG duplicados.

**9. WhatsApp: normalización a la numeración argentina.**
Se agrega un método en `Setting` (junto a `dialablePhone()`) que devuelve el número para `wa.me`. Con "+" se usan los dígitos tal cual; sin "+" se quita el "0" inicial y el "15" posterior al código de área y se antepone `549`. La separación del código de área no se puede deducir de la cadena, por lo que el "15" solo se quita si el teléfono lo trae como `(área) 15…` o `área-15…`; si no se puede reconocer, se deja el número sin tocar y se antepone `549`. Alternativa descartada: pedir un campo nuevo "WhatsApp" por línea, que cambia Parámetros y la base de datos, y que se puede sumar después si hace falta.

**10. Contacto en una vista propia.**
El bloque de ayuda es una vista (`filament.client.help-contact-lines`) distinta de la del ingreso, porque lleva dos botones por línea. Ambas usan `Setting::contactLines()` para no divergir.

**11. Reconfirmación como un asistente de dos pasos en el mismo modal.**
`ClientTicketAssignForm::steps()` devuelve dos pasos: "Elegí tu viaje" (el formulario de siempre) y "Confirmá tu elección" (la vista `assign-confirmation`, con el resumen y el aviso). El action usa `steps()` y su botón final dice "Sí, confirmar fecha". La falta de asiento se detiene al salir del paso 1 con `Halt` en `afterValidation`, con la misma notificación de antes. Se descartaron una confirmación aparte, que apila un segundo modal y pierde el estado ya cargado, y un checkbox, que agrega fricción sin dar más información: el asistente reutiliza lo elegido y trae "Volver" sin código extra. La guarda del servidor de `assign()` no cambia.

## Risks / Trade-offs

- [El número de WhatsApp puede quedar mal armado si el teléfono cargado es fijo o tiene un formato raro] → Se documenta la regla en el spec, con escenarios para los formatos habituales; la línea sigue mostrando "Llamar", y el administrador puede cargar el teléfono con "+54 9" para forzar el destino.
- [Un viaje de hoy cuyo horario ya pasó sigue como "Próximo viaje" hasta medianoche] → Es una decisión explícita; un cambio a "por hora" se puede hacer después sin tocar las pestañas.
- [Con `Halt`, Filament avanza el índice del paso en el servidor pero no dispara el evento que mueve la pantalla] → El cliente se queda en el paso 1; los tests comprueban que no se emite `next-wizard-step`, no el índice del paso.
- [Los tests actuales usan `mostrarFechados`] → Se reemplazan por tests de pestañas; el resto de `ClientPanelTicketsTest` no cambia.
- [Clases Tailwind nuevas no se ven sin compilar] → Se avisa al terminar que hay que correr `npm run build`.
- [Tres consultas de conteo por carga] → Son baratas y filtran por DNI con índices existentes; si pesara, se pueden combinar en una agrupada.

## Open Questions

- Conviene revisar con el administrador los teléfonos cargados en Parámetros para confirmar que los números de WhatsApp se arman bien; no cambia los specs ni las tareas.
