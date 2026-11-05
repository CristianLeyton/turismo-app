# Spec Delta

## Purpose

Bloqueo temporal de asientos mientras un vendedor completa una venta, para que dos vendedores no elijan el mismo asiento, y verificación final de disponibilidad al emitir los boletos.

## ADDED Requirements

### Requirement: Reserva temporal por sesión
Al seleccionar asientos en un viaje, el sistema SHALL reservarlos a nombre de la sesión del navegador del vendedor por 5 minutos. Todas las pestañas de la misma sesión comparten reservas. Cada nueva selección de una sesión para un viaje MUST reemplazar sus reservas anteriores en ese viaje.

#### Scenario: Primera selección
- **WHEN** el vendedor elige los asientos 3 y 4 de un viaje
- **THEN** ambos quedan reservados para su sesión con vencimiento a los 5 minutos

#### Scenario: Cambio de selección
- **WHEN** la misma sesión pasa a elegir solo el asiento 7 en ese viaje
- **THEN** se eliminan sus reservas de 3 y 4 y queda reservado el 7

#### Scenario: Deseleccionar todo
- **WHEN** la sesión envía una selección vacía para el viaje
- **THEN** se liberan todas sus reservas de ese viaje

### Requirement: Asientos no reservables
Una sesión MUST NOT reservar un asiento que tenga un boleto vigente en ese viaje, que tenga una reserva vigente de otra sesión, que esté inactivo o que no pertenezca al colectivo del viaje. Ante un conflicto, el sistema responde con error de conflicto, informa los números vendidos y los reservados por otros, y no reserva ninguno de los pedidos.

#### Scenario: Asiento vendido
- **WHEN** se pide reservar el asiento 5 y ya tiene un boleto vigente en el viaje
- **THEN** la reserva se rechaza con "Algunos asientos ya no están disponibles. Asientos vendidos: 5."

#### Scenario: Reservado por otro vendedor
- **WHEN** se pide reservar el asiento 6 y otra sesión lo tiene reservado sin vencer
- **THEN** la reserva se rechaza informando que el asiento 6 está reservado por otros usuarios

#### Scenario: Asiento de boleto anulado
- **WHEN** el único boleto del asiento en ese viaje fue eliminado (baja lógica)
- **THEN** el asiento puede reservarse

### Requirement: Unicidad de la reserva por asiento y viaje
Cada asiento de un viaje MUST tener como máximo una fila de reserva, haya vencido o no. Si otra sesión intenta reservar un asiento cuya reserva vencida todavía no se eliminó, la operación falla con "Algunos asientos ya están reservados por otro usuario".

#### Scenario: Carrera entre dos vendedores
- **WHEN** dos sesiones intentan reservar el mismo asiento casi al mismo tiempo
- **THEN** solo una lo consigue y la otra recibe el error de conflicto

#### Scenario: Reserva vencida sin limpiar
- **WHEN** la reserva de otra sesión venció pero sigue guardada, y una nueva sesión pide ese asiento
- **THEN** la reserva falla con "Algunos asientos ya están reservados por otro usuario"

### Requirement: Reservas vencidas no cuentan como ocupación
Las reservas cuyo vencimiento ya pasó MUST ignorarse al calcular disponibilidad, al mostrar el estado de los asientos, al consultar las reservas de una sesión y al verificar en la emisión.

#### Scenario: Selector con reserva vencida de otro
- **WHEN** otra sesión tenía reservado el asiento 8 y su reserva venció
- **THEN** el selector muestra el asiento 8 como disponible

### Requirement: Renovación de reservas
Las reservas vigentes de una sesión SHALL renovarse a 5 minutos desde el momento actual cada vez que el selector de asientos se vuelve a mostrar con asientos todavía válidos, y cuando la sesión pide una renovación explícita. Las reservas ya vencidas MUST NOT renovarse.

#### Scenario: Renovación explícita
- **WHEN** una sesión con reservas vigentes pide renovarlas
- **THEN** su vencimiento pasa a 5 minutos desde ahora y se informa la cantidad renovada y el nuevo vencimiento

#### Scenario: Renovación con todo vencido
- **WHEN** todas las reservas de la sesión ya vencieron
- **THEN** no se renueva ninguna y el vencimiento informado es nulo

### Requirement: Vencimiento visible para el vendedor
El selector SHALL mostrar una cuenta regresiva de la reserva. Al llegar a cero espera 10 segundos, deselecciona los asientos, libera todas las reservas de la sesión y muestra "Asientos expirados: Los asientos han sido liberados. Por favor, seleccionalos nuevamente si aún los necesitas."

#### Scenario: Se agota el tiempo
- **WHEN** la cuenta regresiva llega a cero
- **THEN** 10 segundos después los asientos quedan deseleccionados y liberados, y aparece el aviso de asientos expirados

### Requirement: Detección de asientos expirados al volver al selector
Al volver a mostrar el selector, si algún asiento elegido ya no está reservado por la sesión ni vendido, el sistema MUST quitarlo de la selección y avisar sus números. También descarta los asientos de vuelta y los datos de pasajeros ya cargados.

#### Scenario: Reserva vencida al volver
- **WHEN** el vendedor vuelve al paso de asientos y la reserva del asiento 12 venció
- **THEN** el 12 se quita de la selección con el aviso "Los siguientes asientos expiraron: 12", y se descartan los asientos de vuelta

### Requirement: Liberación de reservas
El sistema SHALL liberar las reservas de la sesión cuando el vendedor cancela la venta, la emisión termina bien o con error, se cierra la página tras confirmar la salida, o se carga la página de venta (también desde otra pestaña de la misma sesión). La liberación explícita elimina todas las reservas de la sesión, en todos los viajes. Cambiar la búsqueda del paso 1 MUST NOT liberar las reservas del viaje anterior.

#### Scenario: Cancelación de la venta
- **WHEN** el vendedor tiene reservas en dos viajes y cancela la venta confirmando el aviso
- **THEN** se eliminan todas las reservas de su sesión y otra sesión ya puede reservar esos asientos

#### Scenario: Página de venta abierta en otra pestaña
- **WHEN** el vendedor tiene una venta a medio hacer con asientos reservados y abre la página de venta en otra pestaña
- **THEN** se eliminan todas las reservas de su sesión, también las de la venta en curso

#### Scenario: Cambio de búsqueda
- **WHEN** el vendedor reservó asientos en un viaje, vuelve al paso 1 y cambia a otro horario
- **THEN** las reservas del viaje anterior se conservan hasta vencer, o hasta que se cancele o se emita la venta

#### Scenario: Cierre de la página
- **WHEN** el vendedor cierra la página del asistente y confirma la salida
- **THEN** se liberan todas las reservas de su sesión

### Requirement: Limpieza de reservas vencidas a demanda
El sistema SHALL ofrecer la eliminación de todas las reservas vencidas mediante el comando `app:cleanup-expired-reservations` y mediante un endpoint de mantenimiento, informando cuántas eliminó. La limpieza MUST NOT programarse automáticamente: solo ocurre cuando alguien la ejecuta.

#### Scenario: Ejecución del comando
- **WHEN** se ejecuta `php artisan app:cleanup-expired-reservations` con 3 reservas vencidas
- **THEN** se eliminan las 3 y se informa "Successfully deleted 3 expired reservations"

#### Scenario: Sin ejecución
- **WHEN** nadie ejecuta la limpieza
- **THEN** las reservas vencidas permanecen guardadas

### Requirement: API de reservas para el selector
El sistema SHALL exponer bajo `/api/seat-reservations/` las operaciones POST `reserve`, `release`, `keep-alive`, `status` y `cleanup`. Requieren token CSRF de una sesión web, pero MUST NOT exigir usuario autenticado. Las operaciones de una sesión se identifican por el identificador de sesión enviado en el pedido.

#### Scenario: Consulta de estado
- **WHEN** se consulta el estado con un identificador de sesión y un viaje
- **THEN** se devuelven los asientos con reserva vigente de esa sesión en ese viaje, su cantidad y el vencimiento más lejano

#### Scenario: Pedido sin login
- **WHEN** una página con token CSRF válido pero sin usuario autenticado llama a `reserve`
- **THEN** el pedido se procesa igual

### Requirement: Verificación final de disponibilidad al emitir
Al emitir, el sistema MUST volver a verificar, con el viaje bloqueado, que cada asiento siga activo, sin boleto vigente y sin reserva vigente de otra sesión. Antes de crear cada boleto repite el control, ya sin el bloqueo. Las reservas de la propia sesión no lo impiden.

#### Scenario: Vendido en el último momento
- **WHEN** entre la selección y la emisión otra venta tomó uno de los asientos
- **THEN** la emisión se rechaza informando el número de asiento y se liberan las reservas de la sesión

#### Scenario: Reserva propia
- **WHEN** los asientos están reservados por la misma sesión que emite
- **THEN** la emisión continúa

### Requirement: Unicidad de boletos vigentes solo por control de la aplicación
Que un asiento tenga a lo sumo un boleto vigente por viaje lo garantizan únicamente los controles de la emisión. La base de datos MUST NOT rechazar un segundo boleto vigente para el mismo viaje y asiento: el índice único incluye la fecha de baja, que es nula en los boletos vigentes.

#### Scenario: Dos emisiones simultáneas del mismo asiento
- **WHEN** dos emisiones pasan el control de disponibilidad del mismo asiento antes de que cualquiera cree su boleto
- **THEN** ambas pueden crear un boleto vigente para ese viaje y asiento

### Requirement: Emisión no atómica ante fallos tardíos
La emisión de una venta MUST NOT ejecutarse en una única transacción. Si un fallo de asientos ocurre después de crear la venta, los pasajeros o parte de los boletos, lo ya creado permanece. Al vendedor se le muestra el error.

#### Scenario: Falla la vuelta después de la ida
- **WHEN** los boletos de ida se crearon y la creación de los de vuelta falla por un asiento ocupado
- **THEN** la venta y los boletos de ida siguen existiendo, y el vendedor ve el error de la vuelta
