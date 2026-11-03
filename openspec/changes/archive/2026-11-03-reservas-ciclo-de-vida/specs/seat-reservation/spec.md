# Spec Delta

## MODIFIED Requirements

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
