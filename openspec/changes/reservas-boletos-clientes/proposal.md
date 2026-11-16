# Propuesta

## Por qué

Hoy un cliente solo puede usar el panel para ver los boletos que le vendieron y elegir la fecha de los que compró sin fecha. Para viajar tiene que llamar o ir a boletería. La empresa quiere que el cliente **reserve** boletos desde su panel, con el asiento guardado unas horas, mientras alguien del personal verifica el pago (transferencia a la cuenta o efectivo en boletería). No es una compra: el boleto existe recién cuando el personal confirma que llegó el pago.

Es una función delicada. Activarla, desactivarla o dejar reservas pendientes no puede cambiar nada de lo que ya funciona: venta del mostrador, saldos de vendedores, listas de pasajeros, PDF, link público ni "Mis boletos".

## Qué cambia

**Panel de clientes**
- Página nueva "Mis reservas", con el botón "Reservar" y la lista de reservas del cliente con su estado (pendiente, confirmada, rechazada con su motivo, cancelada, vencida).
- Asistente para reservar **con fecha**: ida, o ida y vuelta con las dos fechas; elección de asientos; pasajeros hasta el límite de "Pasajeros por venta", con el cliente precargado como primer pasajero (no es obligatorio que viaje); menor y mascotas con las mismas reglas del mostrador; método de pago entre los habilitados para reservas.
- Precio obligatoriamente fijo: la reserva solo se ofrece con el precio fijo activo y guarda el precio calculado al reservar.
- Asientos garantizados hasta el vencimiento: `mín(reservado + horas de reserva, salida del primer tramo − corte)`. El cliente ve la hora límite antes de confirmar y en su lista, con el aviso de que tiene que abonar antes de esa hora.
- Datos para pagar: número de reserva, monto exacto, los datos de la cuenta (si están cargados) y las líneas de contacto, con WhatsApp precargado con el número de reserva y el monto.
- El cliente puede cancelar una reserva pendiente, con confirmación. Hasta 2 reservas pendientes a la vez.
- Un cliente baneado entra al panel pero no puede reservar ("No podés hacer reservas desde el panel. Nos reservamos el derecho de admisión."). Un pasajero baneado bloquea la reserva con un mensaje genérico, sin el motivo, que termina en "comunicate con nosotros".
- Las reservas sin fecha quedan preparadas en los datos, pero no se ofrecen.

**Panel de administración**
- Listado nuevo "Reservas", con acciones "Confirmar" y "Rechazar".
- Confirmar crea la venta y los boletos con el precio congelado, a nombre de quien confirma, y abre el modal de entrega. Se puede confirmar después del vencimiento si los asientos siguen libres, y elegir otros asientos libres del mismo viaje.
- Rechazar exige un motivo, que el cliente ve.
- Widget "Reservas por verificar" en el inicio, con la cantidad pendiente y la más próxima a vencer.
- El selector de asientos de la venta muestra los asientos de reservas de clientes con otro color y la leyenda "Reservado por cliente, vence …".

**Configuración**
- Pestaña nueva "Reservas" en Parámetros, oculta si el panel de clientes está apagado, con: interruptor de reservas, anticipación mínima para reservar (24 h), horas de reserva del asiento (24 h), corte antes de la salida (1 h), métodos de pago habilitados y datos para transferir (alias, CBU, titular y banco opcional). Con el precio fijo apagado se puede prender, con un aviso.
- Permisos nuevos, ninguno por defecto: módulo "Reservas" (ver, confirmar, rechazar) y "Configurar reservas de clientes" en Configuración.
- Apagar las reservas oculta todo al cliente. Las pendientes siguen vivas hasta vencer y el personal las resuelve.

## Capacidades

### Capacidades nuevas
- `client-ticket-booking`: cómo el cliente reserva, ve, paga y cancela reservas desde su panel, y qué reglas las limitan.
- `ticket-booking-admin`: cómo el personal ve, confirma y rechaza reservas, el widget de pendientes y qué ve el vendedor de los asientos reservados por clientes.

### Capacidades modificadas
- `settings-page`: pestaña nueva "Reservas" en el orden de pestañas, con sus parámetros, validaciones, visibilidad y permiso.
- `seat-reservation`: un asiento puede quedar bloqueado por una reserva de cliente hasta su vencimiento, además del bloqueo de 5 minutos por sesión.
- `ticket-fixed-price`: el boleto que nace de confirmar una reserva toma el precio guardado en la reserva, no el vigente.
- `passenger-trip-uniqueness`: la regla de una persona por viaje también aplica al reservar y al confirmar, contando las reservas pendientes.
- `role-permissions`: los permisos de reservas no vienen por defecto en ningún rol.

## Impacto

- **Datos**: tablas nuevas para las reservas y sus pasajeros. `tickets`, `sales` y `seat_reservations` no cambian de estructura; las reservas usan filas de `seat_reservations` como bloqueo de asiento.
- **Código nuevo**: modelos, servicios de reserva y de confirmación, página del panel de clientes, recurso y widget del panel de administración, pestaña de Parámetros.
- **Código existente que se toca**: `Setting` y `TicketSettings` (parámetros), `Permissions` (catálogo), API y vista del selector de asientos (leyenda del asiento reservado por cliente), `TicketDelivery`/`TicketDeliveryAction` (modal al confirmar), `ClientPanelProvider` (página nueva), `CustomDashboard` (widget).
- **Depende de** `limite-pasajeros-por-venta`, ya implementado.
- **Deploy**: `php artisan migrate`, `php artisan permissions:sync`, asignar los permisos y `npm run build`. La función arranca apagada.
