# Design

## Context

- El paso "Resumen" del asistente es un único `ViewField` que renderiza `tickets.summary`, con el resumen del viaje y los pasajeros en la misma vista. Para poner un campo editable entre esas dos partes hay que dividirla.
- `CreateTicket` crea los boletos en varios lugares: ida, vuelta, pendientes sin fecha (`createPendingTickets`) y combinaciones de ida y vuelta. Cada uno arma su propio array de atributos (ver los distintos `'pet_names' => ...`).
- El modal "Ver detalles", el PDF y el Excel del viaje se alimentan de `Trip::getPassengersWithDetails()`, que devuelve una fila por adulto, por menor y por mascota.
- `EditTicket` ya compara campo por campo, guarda una fila en `TicketEdit` por cada cambio y usa `EditTicketForm::fieldLabel()` para las etiquetas. Además, solo modifica el boleto editado.
- La reprogramación (`TicketRescheduleService`) actualiza `trip_id` y `seat_id` del boleto existente, sin recrearlo.

## Goals / Non-Goals

**Goals:**
- Un único punto de carga del dato en las tres salidas del viaje.
- Ocultar Teléfono sin borrar su markup, para poder hacer las columnas configurables más adelante.

**Non-Goals:**
- Que las columnas de la lista sean configurables por el usuario, o agregarle filtros (queda para después).
- Mostrar la observación en los reportes de ventas o pagos (`SalesTickets*`, `Payments*`).
- Guardar la observación a nivel de venta (`sales`).

## Decisions

### La observación se guarda en `tickets`, no en `sales`
Columna `observation` (`text`, nullable) en `tickets`. La venta la copia a cada boleto al emitir.
- *Alternativa*: guardarla en `sales` y leerla desde el boleto. Se descarta porque la edición tiene que cambiar un solo boleto, y con el dato en la venta eso obligaría a manejar un valor heredado y otro sobrescrito.
- `text` en vez de `varchar(280)`: el límite vive en la validación (`maxLength(280)` en el formulario y una regla en el servidor), así que si se sube el límite no hace falta otra migración.

### Normalización en un único lugar
Un helper de modelo (por ejemplo un mutator `observation` en `Ticket`) hace `trim` y convierte el vacío en `null`. Así la venta, la edición y cualquier escritura futura se comportan igual, sin repetir el `trim(...) ?: null` en cada array de creación.

### Validación del lado del servidor al emitir y al editar
Además del `maxLength` del `Textarea`, `CreateTicket` y `EditTicket` vuelven a validar el largo antes de escribir, siguiendo el patrón de los controles que el asistente ya repite al emitir (corte de venta, DNIs bloqueados). Esto cubre el caso de quien saltea la interfaz.

### Dividir la vista del resumen en dos `ViewField`
`tickets.summary` se separa en `tickets.summary-trip` y `tickets.summary-passengers` (o queda una sola vista con un parámetro `section`), con el `Textarea::make('observation')` entre las dos dentro del `Step::make('Resumen')`.
- *Alternativa*: dibujar el textarea dentro del Blade con `wire:model`. Se descarta porque se saltea la validación y el estado del schema de Filament.
- El `Textarea` lleva `->rows(3)`, `->maxLength(280)`, el contador de caracteres y el helper text "Se guarda en todos los boletos de esta venta. Opcional."

### `getPassengersWithDetails()` agrega `observation` solo en la fila del adulto
La fila `adult` incluye `'observation' => $ticket->observation`. Las filas `child` y `pet` no llevan la clave, o la llevan en `null`, y las vistas muestran celda vacía. Así las tres salidas reciben el mismo dato sin hacer consultas nuevas.

### Teléfono oculto con un flag en la vista
En `filament/trips/trip-details` y en `pdf/trip-details`, el `<th>` y el `<td>` de Teléfono quedan dentro de `@if ($showPhone)`, con `$showPhone = false` definido arriba de la vista (o pasado por `viewData`, para que mañana lo pueda controlar un toggle de columnas). En el Excel no cambia nada.

### Parámetro "Observaciones en la venta" y permiso propio
Se copia el esquema de los selectores de origen y destino: `Setting::OBSERVACIONES_VENTA = 'sales.observaciones'` con el helper `Setting::observacionesActivas()` (en `false` por defecto), una sección "Observaciones" en la pestaña Venta visible con `settings.sale_observations`, `observaciones_activas` en `TAB_FIELDS['Venta']` y un guardado que solo escribe si quien guarda tiene el permiso y el campo llegó. El permiso va en `Permissions::catalog()` y no se agrega a `defaultsFor()`.

Un único helper decide en todas partes. Así no se dibujan dos condiciones distintas para la misma regla:
- **Venta y edición**: el `Textarea` lleva `->visible(fn (): bool => Setting::observacionesActivas())`. Del lado del servidor, `CreateTicket` fuerza `observation = null` con el parámetro apagado, y `EditTicket` no compara, no registra en el historial y no guarda la observación (el boleto conserva la que tenía).
- **Lista del viaje**: las tres vistas reciben `$showObservation` (por defecto `Setting::observacionesActivas()`). `$showPhone` pasa a valer por defecto `! $showObservation`: con el parámetro apagado la tabla vuelve a quedar como antes. Los dos flags se pueden pasar por `viewData` para el futuro selector de columnas. En el Excel, `columnWidths()` y `styles()` solo configuran la columna I cuando se muestra.
- **Detalle y auditoría**: el bloque se dibuja con `filled($record->observation) && Setting::observacionesActivas()`.
- *Alternativa*: ocultar solo la carga y seguir mostrando lo guardado. El usuario la descartó: apagado tiene que ser como si la funcionalidad no existiera.

### Presentación
- **Modal**: la celda de Observación tiene un ancho mínimo (`min-w-56`). Adentro, la observación va en un recuadro con el mismo estilo del detalle del boleto: `rounded-lg border-l-4 border-amber-400 bg-amber-50 px-3 py-2 dark:border-amber-500 dark:bg-amber-900/20`, con texto `text-sm` y `whitespace-pre-line break-words`. Sin observación, un "-" gris.
- **PDF (A4 vertical)**: anchos Boleto 7%, Nombre 20%, DNI 11%, Asiento 8%, Origen 13%, Destino 13%, Observación 28%. La observación va en un `<div>` con `border-radius: 6px`, fondo `#eeeeee` y borde izquierdo gris (dompdf dibuja el `border-radius` en bloques), que se distingue también impreso en blanco y negro, y `word-wrap: break-word`. Con el parámetro apagado se usan los anchos anteriores, con Teléfono.
- **Excel**: columna "Observaciones" al final, con `wrapText` y un ancho fijo cómodo (por ejemplo 50) en `TripExcelExport::styles()` o `columnWidths()`. El `colspan` de la fila "No hay pasajeros" pasa de 8 a 9.

### Detalle del boleto y auditoría
- En `tickets/infolist-sale-details`, un bloque "Observación" que ocupa todo el ancho, debajo de los tiles, y solo aparece si `filled($record->observation)`. La vista previa del PDF no se toca.
- En `ticket-audit-detail`, el mismo bloque, siguiendo el estilo de "cajas" que ya usa esa vista.

### Edición
`EditTicketForm` suma `Textarea::make('observation')` con las mismas reglas que en la venta. `EditTicket` la carga en `fillForm`, la compara normalizada (antes y después de `trim`, con el vacío como `null`) y, si cambió, la agrega a `$changes` como `['observation', $old, $new]`. `fieldLabel()` devuelve `'Observación'` para ese campo.

## Risks / Trade-offs

- [Filas altas en el PDF con observaciones largas] → Ancho del 28% y fuente igual a la de la fila. Una observación de 280 caracteres ocupa unas 5 o 6 líneas, lo cual se acepta a cambio de ver el texto completo.
- [Olvidar algún punto de creación de boletos en `CreateTicket`] → Tests que cubren ida simple, ida y vuelta, vuelta pendiente e ida pendiente, y verifican la observación en todos los boletos.
- [Que la observación se filtre al pasajero en un cambio futuro del PDF o de la página pública] → Tests que verifican que el PDF del boleto, la página pública y "Mis boletos" no contienen el texto.
- [Las clases Tailwind nuevas no aparecen en producción] → Correr `npm run build` como parte del despliegue.

## Migration Plan

1. Migración que agrega `observation` nullable a `tickets`. Los boletos existentes quedan sin observación.
2. Desplegar el código y correr `npm run build`.
3. Rollback: revertir el código y hacer `migrate:rollback` de esa migración (se pierden las observaciones cargadas).
