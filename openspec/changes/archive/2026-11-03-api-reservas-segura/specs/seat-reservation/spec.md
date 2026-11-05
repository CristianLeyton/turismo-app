# Spec Delta

## MODIFIED Requirements

### Requirement: Limpieza de reservas vencidas a demanda
El sistema SHALL ofrecer la eliminación de todas las reservas vencidas mediante el comando `app:cleanup-expired-reservations`, informando cuántas eliminó. No hay endpoint HTTP para la limpieza. La limpieza MUST NOT programarse automáticamente: solo ocurre cuando alguien la ejecuta.

#### Scenario: Ejecución del comando
- **WHEN** se ejecuta `php artisan app:cleanup-expired-reservations` con 3 reservas vencidas
- **THEN** se eliminan las 3 y se informa "Successfully deleted 3 expired reservations"

#### Scenario: Sin ejecución
- **WHEN** nadie ejecuta la limpieza
- **THEN** las reservas vencidas permanecen guardadas

#### Scenario: Sin endpoint de limpieza
- **WHEN** un usuario autenticado llama a `POST /api/seat-reservations/cleanup`
- **THEN** responde 404 y no se elimina ninguna reserva

### Requirement: API de reservas para el selector
El sistema SHALL exponer bajo `/api/seat-reservations/` las operaciones POST `reserve`, `release`, `keep-alive` y `status`. Requieren token CSRF y un usuario autenticado en cualquiera de los paneles. Operan siempre sobre la sesión del pedido: un identificador de sesión enviado en el cuerpo MUST ignorarse. Ante un error inesperado, la respuesta MUST NOT incluir detalles internos.

#### Scenario: Consulta de estado
- **WHEN** un usuario autenticado consulta el estado de un viaje
- **THEN** se devuelven los asientos con reserva vigente de su sesión en ese viaje, su cantidad y el vencimiento más lejano

#### Scenario: Pedido sin login
- **WHEN** un pedido sin usuario autenticado llama a cualquiera de las operaciones
- **THEN** responde 401 en JSON y no crea, renueva ni libera reservas

#### Scenario: Pedido de un cliente del panel de clientes
- **WHEN** un cliente con sesión iniciada en `/clientes` reserva asientos al asignar fecha a un boleto
- **THEN** el pedido se procesa y las reservas quedan a nombre de su sesión

#### Scenario: Identificador de sesión ajeno
- **WHEN** un usuario autenticado manda en el cuerpo el identificador de sesión de otro vendedor para liberar o renovar reservas
- **THEN** la operación actúa solo sobre las reservas de su propia sesión y las del otro vendedor no cambian

#### Scenario: Reserva a nombre de la sesión del pedido
- **WHEN** un usuario autenticado reserva asientos
- **THEN** las reservas quedan a nombre de su sesión, aunque el cuerpo indique otro identificador

#### Scenario: Error inesperado al reservar
- **WHEN** la reserva falla por un error no previsto
- **THEN** responde 500 con el mensaje "No se pudo reservar los asientos. Intentá de nuevo." y el detalle queda solo en el log

#### Scenario: El selector no expone la sesión
- **WHEN** se muestra el selector de asientos
- **THEN** el HTML de la página no incluye el identificador de sesión
