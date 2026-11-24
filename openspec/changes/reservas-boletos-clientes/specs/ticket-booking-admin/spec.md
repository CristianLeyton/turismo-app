## Purpose

Define cómo el personal revisa, confirma y rechaza las reservas que hacen los clientes, cómo se entera de las pendientes y cómo ve el vendedor los asientos que una reserva tiene guardados.

## ADDED Requirements

### Requirement: Listado de reservas
El panel de administración SHALL ofrecer el listado "Reservas", visible solo con el permiso "Ver reservas" (`bookings.view_any`), con número, cliente que reservó, recorrido, fecha y horario de cada tramo, pasajeros, monto, método de pago, estado y hora límite. Por defecto MUST mostrar primero las pendientes, ordenadas por la hora límite más próxima, y permitir filtrar por estado.

#### Scenario: Sin el permiso
- **WHEN** un usuario sin "Ver reservas" abre el panel
- **THEN** no ve "Reservas" en el menú y abrir la URL se rechaza

#### Scenario: Orden de las pendientes
- **GIVEN** dos reservas pendientes que vencen a las 14:00 y a las 10:00
- **WHEN** el usuario abre "Reservas"
- **THEN** ve primero la que vence a las 10:00

#### Scenario: Vencida
- **GIVEN** una reserva pendiente cuya hora límite pasó
- **WHEN** el usuario abre "Reservas"
- **THEN** la ve como "Vencida"

### Requirement: Listado disponible con la función apagada
El listado y el widget SHALL seguir disponibles con las reservas o el panel de clientes apagados, para resolver las reservas que quedaron pendientes.

#### Scenario: Función apagada con pendientes
- **GIVEN** una reserva pendiente y las reservas apagadas
- **WHEN** un usuario con el permiso abre "Reservas"
- **THEN** la ve y puede confirmarla o rechazarla

### Requirement: Confirmar una reserva
La acción "Confirmar", visible solo con el permiso "Confirmar reservas" (`bookings.confirm`) en reservas pendientes o vencidas sin procesar, SHALL crear en una sola operación la venta y los boletos de la reserva, a nombre de quien confirma, con los pasajeros, tramos, asientos, acompañantes, método de pago y precio guardados en la reserva. La reserva pasa a "Confirmada" y queda registrado quién y cuándo la confirmó.

#### Scenario: Confirmar a tiempo
- **GIVEN** una reserva pendiente de 2 pasajeros, solo ida, con asientos 3 y 4
- **WHEN** un usuario con el permiso la confirma
- **THEN** se crea una venta a su nombre con 2 boletos en los asientos 3 y 4, y la reserva queda "Confirmada"

#### Scenario: Aparece en Mis ventas
- **WHEN** un vendedor confirma una reserva
- **THEN** la venta aparece en su "Mis ventas" y suma a su saldo como cualquier venta suya

#### Scenario: Sin el permiso
- **WHEN** un usuario con "Ver reservas" pero sin "Confirmar reservas" abre una reserva pendiente
- **THEN** no ve "Confirmar"

#### Scenario: Ya procesada
- **WHEN** se intenta confirmar una reserva confirmada, rechazada o cancelada
- **THEN** se rechaza y no se crea nada

### Requirement: Cambiar asientos al confirmar
Al confirmar, el modal SHALL mostrar los asientos de la reserva en cada tramo y permitir cambiarlos por otros libres del mismo viaje. Si algún asiento de la reserva ya no está libre, MUST obligar a elegir otro antes de confirmar.

#### Scenario: Cambiar un asiento
- **WHEN** el usuario cambia el asiento 3 por el 7 y confirma
- **THEN** el boleto queda en el asiento 7

#### Scenario: Asiento ocupado
- **GIVEN** una reserva vencida cuyo asiento 3 se vendió
- **WHEN** el usuario abre "Confirmar"
- **THEN** ve que el asiento 3 ya no está libre y tiene que elegir otro para confirmar

### Requirement: Confirmar después del vencimiento
Una reserva vencida SHALL poder confirmarse mientras el primer tramo no haya salido y haya asientos libres para todos sus pasajeros en cada tramo. Si no los hay, MUST NOT confirmarse y el modal MUST indicar que solo se puede rechazar.

#### Scenario: Vencida con asientos libres
- **GIVEN** una reserva vencida hace dos horas cuyos asientos siguen libres
- **WHEN** el usuario la confirma
- **THEN** se crean la venta y los boletos

#### Scenario: Viaje ya salido
- **GIVEN** una reserva vencida cuyo viaje de ida ya salió
- **WHEN** el usuario intenta confirmarla
- **THEN** no puede y se le indica que la rechace

### Requirement: Controles al confirmar
Al confirmar, el sistema MUST volver a controlar, con los viajes bloqueados, que cada asiento esté libre, que ningún DNI de la reserva tenga ya un boleto activo en esos viajes y que ningún pasajero esté baneado. Si algún control falla, MUST NOT crearse nada y el usuario MUST ver el motivo. Los días de servicio, la anticipación y el límite de venta MUST NOT volver a controlarse.

#### Scenario: Pasajero con boleto en el viaje
- **GIVEN** que después de reservar alguien le vendió en el mostrador un boleto del mismo viaje a un pasajero de la reserva
- **WHEN** el usuario confirma la reserva
- **THEN** se rechaza indicando qué pasajero ya tiene boleto, y no se crea nada

### Requirement: Modal de entrega al confirmar
Al confirmar correctamente, el sistema SHALL ofrecer la entrega de los boletos igual que al terminar una venta: con "Descargar PDF automáticamente al vender" desactivado, abre el modal de entrega con el título "Reserva N°{reserva} confirmada"; activado, abre el PDF.

#### Scenario: Entrega por link
- **GIVEN** la descarga automática desactivada
- **WHEN** el usuario confirma la reserva N°15
- **THEN** se abre el modal de entrega "Reserva N°15 confirmada" con una fila por pasajero

### Requirement: Rechazar una reserva
La acción "Rechazar", visible solo con el permiso "Rechazar reservas" (`bookings.reject`) en reservas pendientes o vencidas sin procesar, SHALL pedir un motivo obligatorio de hasta 280 caracteres, con la aclaración de que el cliente lo va a leer. Al rechazar, la reserva pasa a "Rechazada", guarda el motivo, quién y cuándo, y sus asientos MUST liberarse.

#### Scenario: Rechazar con motivo
- **WHEN** el usuario rechaza con "El monto transferido no coincide"
- **THEN** la reserva queda "Rechazada", el cliente ve el motivo y los asientos se liberan

#### Scenario: Sin motivo
- **WHEN** el usuario intenta rechazar sin motivo
- **THEN** ve "Ingresá el motivo del rechazo." y la reserva sigue igual

### Requirement: Widget de reservas por verificar
El inicio del panel SHALL mostrar el widget "Reservas por verificar" a quien tiene "Ver reservas", con la cantidad de reservas pendientes sin vencer, la hora límite más próxima y un acceso al listado. Sin pendientes sin vencer, el widget MUST NOT mostrarse. El menú "Reservas" MUST mostrar esa cantidad como indicador.

#### Scenario: Con pendientes
- **GIVEN** 3 reservas pendientes, la más próxima vence a las 14:30
- **WHEN** un usuario con el permiso abre el inicio
- **THEN** ve "Reservas por verificar: 3" con "La próxima vence 16/11 14:30"

#### Scenario: Sin el permiso
- **WHEN** un usuario sin "Ver reservas" abre el inicio
- **THEN** no ve el widget

#### Scenario: Sin pendientes
- **WHEN** no hay reservas pendientes sin vencer
- **THEN** el widget no se muestra

### Requirement: Asientos reservados por clientes en la venta
En el selector de asientos de la venta, la reprogramación y "Elegir fecha", un asiento bloqueado por una reserva de cliente SHALL verse con un color distinto del bloqueo de otro vendedor y la leyenda "Reservado por cliente, vence {dd/mm HH:mm}", y MUST NOT poder elegirse.

#### Scenario: Vendedor ve un asiento reservado
- **GIVEN** el asiento 5 de un viaje reservado por un cliente hasta el 17/11 14:30
- **WHEN** un vendedor abre el selector de asientos de ese viaje
- **THEN** ve el asiento 5 con el color de reserva de cliente y "Reservado por cliente, vence 17/11 14:30", y no puede elegirlo

#### Scenario: Reserva vencida
- **GIVEN** la reserva del asiento 5 venció
- **WHEN** el vendedor abre el selector
- **THEN** el asiento 5 aparece libre
