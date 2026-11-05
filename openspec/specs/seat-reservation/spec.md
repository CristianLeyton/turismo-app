# seat-reservation Specification

## Purpose

Bloqueo temporal de asientos mientras un vendedor completa una venta, para que dos vendedores no elijan el mismo asiento, y verificación final de disponibilidad al emitir los boletos.

## Requirements

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
Cada asiento de un viaje MUST tener como máximo una fila de reserva. Al reservar, una reserva vencida de ese asiento, de cualquier sesión, MUST eliminarse y no impedir la nueva reserva, haya corrido o no la limpieza. Si dos sesiones intentan reservar el mismo asiento libre a la vez, solo una lo consigue y la otra recibe el error de conflicto "Algunos asientos ya están reservados por otro usuario", con el mismo resultado en cualquier motor de base de datos.

#### Scenario: Carrera entre dos vendedores
- **WHEN** dos sesiones intentan reservar el mismo asiento casi al mismo tiempo
- **THEN** solo una lo consigue y la otra recibe el error de conflicto, nunca un error interno

#### Scenario: Reserva vencida sin limpiar
- **WHEN** la reserva de otra sesión venció pero sigue guardada, y una nueva sesión pide ese asiento
- **THEN** la reserva vencida se elimina y el asiento queda reservado para la nueva sesión

#### Scenario: Reserva vigente de otra sesión
- **WHEN** otra sesión tiene el asiento reservado sin vencer y una nueva sesión lo pide
- **THEN** la reserva se rechaza informando que el asiento está reservado por otros usuarios

### Requirement: Reservas vencidas no cuentan como ocupación
Las reservas cuyo vencimiento ya pasó MUST ignorarse al calcular disponibilidad, al mostrar el estado de los asientos, al consultar las reservas de una sesión y al verificar en la emisión.

#### Scenario: Selector con reserva vencida de otro
- **WHEN** otra sesión tenía reservado el asiento 8 y su reserva venció
- **THEN** el selector muestra el asiento 8 como disponible

### Requirement: Renovación de reservas
Las reservas vigentes de la sesión en los viajes de una venta SHALL renovarse a 5 minutos desde el momento actual cada vez que el vendedor interactúa con el asistente de venta (cambiar de paso, cargar o modificar datos), cada vez que el selector de asientos se vuelve a mostrar con asientos todavía válidos, y cuando la sesión pide una renovación explícita. Las reservas ya vencidas MUST NOT renovarse. Una venta sin interacción vence a los 5 minutos de la última.

#### Scenario: Vendedor cargando pasajeros
- **WHEN** el vendedor reservó asientos y pasa 4 minutos en el paso de pasajeros completando datos
- **THEN** cada interacción vuelve a fijar el vencimiento a 5 minutos y las reservas siguen vigentes al emitir

#### Scenario: Venta abandonada
- **WHEN** el vendedor reservó asientos y deja la pestaña abierta sin interactuar durante más de 5 minutos
- **THEN** las reservas vencen y otro vendedor puede reservar esos asientos

#### Scenario: Renovación explícita
- **WHEN** una sesión con reservas vigentes pide renovarlas
- **THEN** su vencimiento pasa a 5 minutos desde ahora y se informa la cantidad renovada y el nuevo vencimiento

#### Scenario: Renovación con todo vencido
- **WHEN** todas las reservas de la sesión ya vencieron
- **THEN** no se renueva ninguna y el vencimiento informado es nulo

### Requirement: Vencimiento visible para el vendedor
El selector SHALL mostrar una cuenta regresiva hasta el vencimiento de las reservas, que se actualiza cada vez que las reservas se renuevan. Al llegar a cero espera 10 segundos, deselecciona los asientos, libera las reservas de la sesión en los viajes de esa venta y muestra "Asientos expirados: Los asientos han sido liberados. Por favor, seleccionalos nuevamente si aún los necesitas."

#### Scenario: Se agota el tiempo
- **WHEN** la cuenta regresiva llega a cero
- **THEN** 10 segundos después los asientos quedan deseleccionados y liberados, y aparece el aviso de asientos expirados

#### Scenario: Renovación por actividad
- **WHEN** el vendedor interactúa con el asistente y las reservas se renuevan
- **THEN** la cuenta regresiva vuelve a 5 minutos

### Requirement: Detección de asientos expirados al volver al selector
Al volver a mostrar el selector, si algún asiento elegido ya no está reservado por la sesión ni vendido, el sistema MUST quitarlo de la selección y avisar sus números. También descarta los asientos de vuelta y los datos de pasajeros ya cargados. Si el viaje del tramo cambió en el paso 1, la selección anterior de ese tramo MUST descartarse sin el aviso de asientos expirados.

#### Scenario: Reserva vencida al volver
- **WHEN** el vendedor vuelve al paso de asientos y la reserva del asiento 12 venció
- **THEN** el 12 se quita de la selección con el aviso "Los siguientes asientos expiraron: 12", y se descartan los asientos de vuelta

#### Scenario: Cambio de viaje
- **WHEN** el vendedor eligió el asiento 13 de un viaje, vuelve al paso 1, cambia la fecha y avanza
- **THEN** el selector del viaje nuevo aparece sin asientos elegidos y sin el aviso de asientos expirados

### Requirement: Liberación de reservas
El sistema SHALL liberar las reservas de la sesión en los viajes de una venta cuando el vendedor la cancela, cuando la emisión termina bien o con error, y cuando se cierra la página tras confirmar la salida. Al cambiar en el paso 1 el viaje de un tramo, MUST liberar las reservas de la sesión en el viaje abandonado. Cargar o recargar la página de venta MUST NOT liberar reservas, y las reservas de la sesión en otros viajes MUST NOT tocarse.

#### Scenario: Cancelación de la venta
- **WHEN** el vendedor tiene reservas en la ida y la vuelta de su venta y cancela confirmando el aviso
- **THEN** se eliminan sus reservas en esos dos viajes y otra sesión ya puede reservar esos asientos

#### Scenario: Página de venta abierta en otra pestaña
- **WHEN** el vendedor tiene una venta a medio hacer con asientos reservados y abre la página de venta en otra pestaña
- **THEN** las reservas de la venta en curso se conservan

#### Scenario: Cancelación con otra venta en curso
- **WHEN** el vendedor tiene en otra pestaña una venta con reservas en un viaje distinto y cancela la venta de esta pestaña
- **THEN** las reservas de la otra venta se conservan

#### Scenario: Cambio de búsqueda
- **WHEN** el vendedor reservó asientos en un viaje, vuelve al paso 1 y cambia a otro horario
- **THEN** se eliminan sus reservas en el viaje anterior y otra sesión ya puede reservar esos asientos

#### Scenario: Paso 1 sin cambio de viaje
- **WHEN** el vendedor vuelve al paso 1 y avanza de nuevo sin cambiar el viaje
- **THEN** sus reservas en ese viaje se conservan

#### Scenario: Cierre de la página
- **WHEN** el vendedor cierra la página del asistente y confirma la salida
- **THEN** se liberan las reservas de la sesión en los viajes de esa venta

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

### Requirement: Verificación final de disponibilidad al emitir
Al emitir, el sistema MUST verificar, con los viajes de la venta bloqueados hasta que la emisión se confirme, que cada asiento siga activo, sin boleto vigente y sin reserva vigente de otra sesión. Los boletos se crean bajo ese mismo bloqueo. Las reservas de la propia sesión no lo impiden.

#### Scenario: Vendido en el último momento
- **WHEN** entre la selección y la emisión otra venta tomó uno de los asientos
- **THEN** la emisión se rechaza informando el número de asiento y se liberan las reservas de la sesión en los viajes de esa venta

#### Scenario: Reserva propia
- **WHEN** los asientos están reservados por la misma sesión que emite
- **THEN** la emisión continúa

#### Scenario: Dos emisiones simultáneas del mismo asiento
- **WHEN** dos vendedores emiten al mismo tiempo ventas que incluyen el mismo asiento del mismo viaje
- **THEN** solo una emisión se confirma, y la otra se rechaza informando el número de asiento sin crear nada

### Requirement: Un solo boleto vigente por asiento y viaje
La base de datos MUST rechazar un segundo boleto vigente para el mismo viaje y asiento, venga de la venta, de la reprogramación, de la restauración de un boleto o de cualquier otro camino de escritura. Los boletos dados de baja no ocupan el asiento, y los boletos sin fecha (sin viaje ni asiento) no participan de la restricción.

#### Scenario: Escritura directa duplicada
- **WHEN** se intenta guardar un boleto vigente en un viaje y asiento que ya tienen otro boleto vigente
- **THEN** la base rechaza la escritura

#### Scenario: Asiento de un boleto dado de baja
- **WHEN** el único boleto de un asiento en un viaje está dado de baja y se emite un boleto nuevo para ese asiento
- **THEN** el boleto nuevo se crea

#### Scenario: Varios boletos sin fecha
- **WHEN** existen varios boletos vigentes sin viaje ni asiento
- **THEN** la restricción no los afecta

### Requirement: Migración protegida ante duplicados existentes
Al incorporar la restricción, el sistema MUST verificar primero que no existan boletos vigentes duplicados por viaje y asiento. Si existen, la migración se detiene sin modificar la base y lista cada viaje, asiento y boleto en conflicto.

#### Scenario: Base con duplicados
- **WHEN** se ejecuta la migración y hay dos boletos vigentes en el mismo viaje y asiento
- **THEN** la migración falla, informa el viaje, el asiento y los identificadores de ambos boletos, y la tabla queda como estaba

### Requirement: Emisión atómica
La emisión de una venta SHALL ejecutarse como una unidad: la venta, los pasajeros, los boletos de ida y los de vuelta se confirman juntos o no se crea ninguno. Si cualquier parte falla, el vendedor ve el motivo y no queda ningún dato de esa venta.

#### Scenario: Falla la vuelta después de la ida
- **WHEN** los boletos de ida se pudieron crear pero la creación de los de vuelta falla por un asiento ocupado
- **THEN** no queda la venta, ni los pasajeros, ni los boletos de ida, y el vendedor ve el error de la vuelta

#### Scenario: Venta mixta con tramo sin fecha
- **WHEN** en una venta con la ida fechada y la vuelta sin fecha falla la creación de los boletos de ida
- **THEN** no queda ningún boleto, tampoco el de la vuelta pendiente

### Requirement: Restauración de un boleto con el asiento ocupado
Al restaurar un boleto dado de baja cuyo asiento ya tiene otro boleto vigente en el mismo viaje, el sistema MUST rechazar la restauración con un mensaje que indique el asiento ocupado, sin error interno y sin modificar el boleto.

#### Scenario: Asiento revendido
- **WHEN** se restaura un boleto dado de baja del asiento 5 y ese asiento ya se vendió a otro pasajero en el mismo viaje
- **THEN** la restauración se rechaza informando que el asiento 5 está ocupado, y el boleto sigue dado de baja
