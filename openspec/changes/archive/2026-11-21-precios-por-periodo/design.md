# Diseño: precios del boleto por período

## Contexto

- Todo el cálculo del precio fijo pasa por `App\Support\FixedTicketPrice`: la vista previa de la venta (`TicketForm::fixedPriceLabel`, `tickets/summary.blade.php`), la emisión (`CreateTicket::applyFixedPrice`) y la edición (`EditTicket::fixedPriceAdjustment`). Ningún consumidor lee los montos de `Setting` por su cuenta.
- Hoy los montos son dos claves de `Setting` (`sales.precio_fijo_boleto`, `sales.precio_fijo_seguro_menor`). La venta guarda al abrirse una fotografía `activo|base|seguro` y la emisión la compara con la configuración vigente.
- Hay un precedente de lista editable en Configuración: las excepciones del calendario se guardan como JSON en una sola clave de `Setting` (`service_days.exceptions`) y se editan con un `Repeater` en `TicketSettings`, con `distinct` y guardado todo o nada.
- La zona horaria de la app es `America/Argentina/Salta`. "Hoy" es `today()` en esa zona.

## Objetivos / No objetivos

**Objetivos:**
- Cambiar la fuente de los montos sin cambiar la interfaz que usan los consumidores, salvo la fecha que necesita la edición.
- Mantener la fotografía y el aviso de cambio de precios tal como están.

**No objetivos:**
- Un ABM separado (Resource) para los períodos.
- Precios que dependan de la fecha del viaje, la ruta o el tipo de pasajero.
- Recalcular boletos vendidos.

## Decisiones

### 1. Períodos como JSON en `Setting`, no en una tabla nueva

Se agrega la clave `sales.precio_fijo_periodos` con una lista `[{desde: 'Y-m-d', boleto: int, seguro: int}]`, ordenada por `desde`. `Setting::precioFijoPeriodos()` la decodifica, descarta filas inválidas (sin fecha válida o con montos no numéricos) y la devuelve ordenada.

*Por qué:* son pocas filas, se editan siempre juntas desde una sola página y el guardado todo o nada ya existe. Es el mismo patrón que las excepciones del calendario.
*Alternativa descartada:* una tabla `price_periods` con modelo, factory y Resource. Da más trazabilidad, pero agrega una migración de esquema, otro ABM y transacciones entre la tabla y `settings` sin una ventaja concreta para este uso.

### 2. Resolución del período en `FixedTicketPrice`

- `periodFor(CarbonInterface $date): ?array`: el último período con `desde <= $date` (comparación de strings `Y-m-d`), o null.
- `hasCurrentPeriod(): bool`: `periodFor(today()) !== null`.
- `basePrice()` y `childInsurance()` pasan a devolver los montos del período de hoy (0 si no hay). `forPassenger()` y `snapshot()` siguen igual, así que la venta, la vista previa y el resumen no cambian.
- La fotografía conserva el formato `activo|base|seguro`. Si una venta cruza la medianoche y cambia de período, cambian los montos, la fotografía deja de coincidir y salta el aviso que ya existe. Si cruza la medianoche dentro del mismo período, la fotografía coincide y la venta se emite.

*Alternativa descartada:* incluir la fecha en la fotografía. Rechazaría ventas que cruzan la medianoche sin cambio de precio.

### 3. Seguro de la edición según la fecha de la venta

`insuranceDelta(bool $hadChild, bool $hasChild, CarbonInterface $saleDate)` usa `periodFor($saleDate)`. Si es null porque la venta es anterior al primer período, usa el período más antiguo. `EditTicket::fixedPriceAdjustment` pasa `$ticket->sale->created_at` (y `$ticket->created_at` si no tiene venta). La ida y la vuelta comparten la venta, así que da igual desde qué tramo se edite.

### 4. Venta sin período vigente

En `CreateTicket::applyFixedPrice`, si el precio fijo está prendido y `! hasCurrentPeriod()`, la emisión se corta antes de comparar la fotografía, con el aviso del spec. Es defensivo: la página de Configuración no permite guardar ese estado y el tiempo solo avanza, así que un período vigente nunca deja de estarlo solo.

### 5. Grilla en `TicketSettings`

- `Repeater::make('precio_fijo_periodos')` dentro de la sección "Precio de los boletos", visible solo con el interruptor prendido. Columnas: `DatePicker desde` (formato `d/m/Y`, `distinct`), y `boleto` y `seguro` con el mismo `fixedPriceAmountInput` actual (enteros, rangos, `$`).
- El "Hasta" y la marca "Vigente" se muestran en el `itemLabel` de cada fila. Se calculan con la lista del formulario ordenada por `desde`, así que reflejan lo que el usuario está editando aunque todavía no lo haya guardado.
- `save()`: con el permiso y el interruptor prendido, valida la cobertura de hoy (`ValidationException` sobre `data.precio_fijo_periodos`), ordena por `desde` y guarda el JSON. Con el interruptor apagado, no valida ni pisa la lista. Sin el permiso, no toca nada (como hoy).
- Los períodos pasados no se depuran al guardar, a diferencia de las excepciones del calendario: la edición del menor los necesita.
- Las claves `precio_fijo_boleto` y `precio_fijo_seguro_menor` salen del formulario, de `TAB_FIELDS` y de `save()`.

### 6. Migración de datos

Una migración `convert_fixed_price_to_periods` lee `sales.precio_fijo_boleto` y `sales.precio_fijo_seguro_menor`. Si hay precio cargado y todavía no existe `sales.precio_fijo_periodos`, escribe un único período con `desde = today()` (el día del deploy). Las dos claves viejas se dejan en la tabla para poder volver atrás. `down()` borra `sales.precio_fijo_periodos`. Las constantes y los accesores viejos de `Setting` se eliminan del código.

Si alguien edita el menor de un boleto vendido antes del deploy, se usa el período más antiguo (el migrado), es decir, el mismo seguro que regía antes. El comportamiento queda igual.

## Riesgos / Compromisos

- [El "Hasta" de la grilla depende del orden de la lista sin guardar] → se calcula siempre sobre la lista ordenada por `desde`, y al guardar se persiste ordenada.
- [Editar un período pasado cambia el seguro que se ajusta en boletos viejos] → es el comportamiento acordado: el período manda. Los boletos vendidos no se recalculan solos.
- [Zona horaria] → todas las comparaciones usan strings `Y-m-d` de `today()` y de `created_at` en la zona de la app, nunca en UTC.
- [Tests existentes que cargan `PRECIO_FIJO_BOLETO`] → se migran a un helper de test que carga un período desde hoy, para que los escenarios de cálculo no cambien.

## Plan de migración

1. Deploy del código y `php artisan migrate`: el precio fijo actual pasa a ser el período del día del deploy y la venta cobra lo mismo.
2. Rollback: `migrate:rollback` del paso y volver al código anterior. Las claves viejas siguen en la tabla con los valores originales.
