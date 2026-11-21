# Propuesta: precios del boleto por período

## Por qué

El precio fijo hoy es un único monto de boleto y uno de seguro. Para cambiar el precio en una temporada (por ejemplo, 70.000 hasta el 01/12 y 160.000 desde el 02/12) alguien tiene que acordarse de entrar a Configuración ese mismo día y cambiarlo a mano. Con una grilla de períodos, los precios se cargan por adelantado y el sistema cambia solo el día indicado.

## Qué cambia

- La sección "Precio de los boletos" de Configuración reemplaza los campos "Precio del boleto" y "Seguro para menores" por una grilla de **períodos de precio**. Cada período tiene **Desde**, **Precio del boleto** y **Seguro para menores**. El **Hasta** no se carga: se muestra calculado como el día anterior al período siguiente, y el último período dice "en adelante". **BREAKING** (de configuración): desaparecen los dos campos de monto.
- El precio de una venta lo decide la **fecha de venta** (el día de la emisión): rige el período más reciente cuyo "Desde" es hoy o anterior. La fecha del viaje, la fecha abierta, la ida y vuelta y la reprogramación no influyen.
- Por cómo se cargan los períodos no puede haber huecos ni superposiciones. Dos períodos no pueden tener el mismo "Desde". Después del último período rige su monto.
- Con el precio fijo prendido, la configuración siempre tiene que cubrir el día de hoy: no se puede prender ni guardar si no queda ningún período con "Desde" hoy o anterior.
- Al editar el menor de un boleto, el seguro que se suma o se resta es el del período vigente **en la fecha de la venta original**, ya no el vigente al editar. Si el boleto se vendió antes del primer período, se usa el período más antiguo.
- La fotografía de la venta (aviso "Cambió la configuración de precios…") sigue funcionando igual, ahora con los montos del período de hoy. Si una venta abierta cruza la medianoche y cambia el período, se rechaza con ese mismo aviso.
- Migración: si hoy hay montos cargados, pasan a ser el primer período, con "Desde" igual al día del deploy.
- Los boletos ya vendidos no se recalculan.

## Capacidades

### Capacidades nuevas

Ninguna.

### Capacidades modificadas

- `ticket-fixed-price`: el precio se toma del período vigente en la fecha de venta. El ajuste del seguro al editar el menor usa el período de la venta original. Se agrega el rechazo de la emisión si no hay un período vigente.
- `settings-page`: la sección "Precio de los boletos" pasa a tener el interruptor y la grilla de períodos, con sus validaciones (cobertura de hoy, "Desde" sin repetir, montos). Se reemplazan los requisitos de los dos campos de monto.

## Impacto

- `app/Support/FixedTicketPrice.php`: resolución del período por fecha. `basePrice()` y `childInsurance()` pasan a depender de la fecha, y la fotografía usa el período de hoy.
- `app/Models/Setting.php`: nuevo parámetro con la lista de períodos (JSON, como las excepciones del calendario). Las claves `sales.precio_fijo_boleto` y `sales.precio_fijo_seguro_menor` dejan de usarse.
- `app/Filament/Pages/TicketSettings.php`: grilla (Repeater), validaciones y guardado.
- `app/Filament/Resources/Tickets/Pages/EditTicket.php`: el ajuste del seguro usa la fecha de la venta del boleto.
- `app/Filament/Resources/Tickets/Pages/CreateTicket.php`: rechazo si no hay un período vigente (caso defensivo).
- Migración de datos de los montos actuales.
- Tests: `FixedTicketPriceTest`, `TicketFixedPriceSaleTest`, `TicketFixedPriceEditTest`, `TicketSettingsTest`.
- Fuera de alcance: el cambio pendiente `reservas-boletos-clientes`. Los precios por fecha de viaje, ruta o tipo de pasajero.
