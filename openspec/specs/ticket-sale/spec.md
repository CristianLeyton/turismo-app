# ticket-sale Specification

## Purpose

Emisión de boletos desde el panel de administración: cómo un vendedor busca un viaje, elige asientos, carga pasajeros y genera una venta con sus boletos, incluyendo ida y vuelta, venta sin fecha y venta fuera de término.

## Requirements

### Requirement: Wizard de venta en pasos secuenciales
El sistema SHALL emitir boletos mediante un asistente con los pasos, en orden: "Buscar viaje", "Asientos (Ida)", "Asientos (Vuelta)", "Pasajeros" y "Resumen". Los pasos MUST completarse en orden, sin saltear ninguno. El paso de asientos de ida se omite si la ida se vende sin fecha, y el de vuelta se muestra solo en ventas de ida y vuelta con fecha de vuelta.

#### Scenario: Venta solo ida con fecha
- **WHEN** el vendedor arma una venta de solo ida con fecha
- **THEN** el asistente muestra "Buscar viaje", "Asientos (Ida)", "Pasajeros" y "Resumen"

#### Scenario: Ida y vuelta con ambas fechas
- **WHEN** el vendedor activa "Diferido" y elige fecha y horario de vuelta
- **THEN** el asistente incluye además el paso "Asientos (Vuelta)"

#### Scenario: Intento de saltear un paso
- **WHEN** el vendedor intenta avanzar a un paso posterior sin completar el actual
- **THEN** el asistente no lo permite

### Requirement: Cantidad de pasajeros por venta
El sistema SHALL permitir entre 1 y 4 pasajeros por venta (por defecto 1). Al cambiar la cantidad, la lista de pasajeros MUST ajustarse a exactamente esa cantidad, y se descartan el viaje buscado y la configuración de ida y vuelta.

#### Scenario: Cantidad fuera de rango
- **WHEN** se envía una cantidad distinta de 1, 2, 3 o 4
- **THEN** el sistema rechaza el valor con el mensaje "La cantidad de pasajeros debe ser entre 1 y 4"

#### Scenario: Reducir pasajeros
- **WHEN** el vendedor tenía 3 pasajeros cargados y cambia la cantidad a 2
- **THEN** se conservan los 2 primeros pasajeros y se descarta el tercero

### Requirement: Colectivo obligatorio
Toda venta MUST tener un colectivo seleccionado, también cuando algún tramo se vende sin fecha. El selector de colectivo MUST listar los colectivos regulares, es decir, sin incluir los de refuerzo de una línea, y los colectivos sin línea. Por defecto se preselecciona el primer colectivo por nombre. Cambiar el colectivo reinicia origen, destino, horario, viaje e ida y vuelta. El colectivo del boleto MUST ser el del horario elegido, que puede ser un colectivo de refuerzo de la línea del colectivo seleccionado.

#### Scenario: Emisión sin colectivo
- **WHEN** llega una emisión sin colectivo, aunque los tramos vayan sin fecha
- **THEN** la venta se rechaza con "Seleccione el colectivo antes de completar la venta." y no se crea nada

#### Scenario: Selector sin refuerzos
- **WHEN** el vendedor abre el selector de colectivo y la línea tiene un regular y un refuerzo
- **THEN** el selector no muestra el refuerzo

### Requirement: Horarios ofrecidos para el tramo de ida
El sistema SHALL ofrecer como horarios de ida solo los activos de rutas activas de todos los colectivos de la línea del colectivo elegido (incluido el propio colectivo) en los que origen y destino forman un tramo válido de la ruta. Cada opción MUST mostrar el nombre del colectivo al que pertenece el horario. MUST excluir los bloqueados por el corte de venta y ordenarlos por nombre en orden natural. Si hay un único horario posible, queda preseleccionado.

#### Scenario: Un único horario disponible
- **WHEN** para la línea, origen, destino y fecha elegidos existe un solo horario ofrecible
- **THEN** ese horario queda seleccionado automáticamente

#### Scenario: Ningún horario disponible
- **WHEN** no hay horarios ofrecibles para la combinación elegida
- **THEN** el selector muestra "No hay horarios disponibles para esta ruta"

#### Scenario: Horario de un refuerzo de la línea
- **WHEN** el colectivo elegido es "Oran - Salta" y el refuerzo "Oran - Salta (Refuerzo)" tiene un horario ofrecible para el tramo
- **THEN** ese horario aparece entre las opciones con el texto que incluye "Oran - Salta (Refuerzo)"

#### Scenario: Colectivo sin línea
- **WHEN** el colectivo elegido no tiene línea
- **THEN** los horarios ofrecidos son solo los de ese colectivo, como antes

### Requirement: Fechas de viaje seleccionables
Los selectores de fecha de ida y de vuelta MUST deshabilitar las fechas sin servicio según la configuración de días de servicio, salvo para un usuario con el permiso para operar en días sin servicio. Con ese permiso, esas fechas se pueden elegir y se avisa que no tienen servicio. La fecha mínima de ida es hoy, o un año atrás si al usuario no se le aplica el corte de venta. La fecha mínima de vuelta es la fecha de ida.

#### Scenario: Domingo
- **WHEN** con la configuración inicial, un vendedor sin el permiso abre el calendario de ida
- **THEN** los domingos aparecen deshabilitados, excepto el 13/09/2026

#### Scenario: Feriado configurado
- **WHEN** el 25/12/2026 está configurado "sin servicio" y un vendedor sin el permiso abre el calendario de vuelta
- **THEN** el 25/12/2026 aparece deshabilitado

#### Scenario: Avanzar el paso 1 con fecha sin servicio
- **WHEN** un vendedor sin el permiso intenta avanzar del paso 1 con una fecha de ida o de vuelta sin servicio
- **THEN** no puede avanzar y se le informa que la fecha es un día sin servicio

### Requirement: Resolución del viaje para la venta
Al buscar un tramo, el sistema SHALL reutilizar el viaje existente para ese horario y fecha. Si no existe, lo crea, salvo que la ruta o el horario estén inactivos. El viaje creado usa el colectivo de la ruta, si no el de un viaje previo de la ruta y si no el primer colectivo con asientos. Rutas u horarios inactivos solo bloquean viajes nuevos.

#### Scenario: Ruta inactiva sin viaje previo
- **WHEN** se busca un horario de una ruta inactiva para una fecha que no tiene viaje creado
- **THEN** la búsqueda falla con "Esta ruta u horario no está disponible para nuevas ventas."

#### Scenario: Ruta inactiva con viaje ya creado
- **WHEN** el viaje para ese horario y fecha ya existía
- **THEN** se reutiliza normalmente

#### Scenario: Tramo inválido
- **WHEN** origen y destino no forman un tramo válido de la ruta del horario
- **THEN** la búsqueda falla con "El segmento origen-destino no es válido para esta ruta."

### Requirement: Disponibilidad suficiente para avanzar
El paso "Buscar viaje" MUST impedir avanzar si el viaje de ida (o el de vuelta, en ida y vuelta con fecha) tiene menos asientos libres que pasajeros. Los asientos libres se calculan igual que al reservar: asientos activos del colectivo del viaje, sin boleto vigente y sin reserva vigente de otra sesión. Las reservas de la propia sesión cuentan como libres.

#### Scenario: Asientos insuficientes
- **WHEN** el viaje tiene 2 asientos libres y la venta es de 3 pasajeros
- **THEN** se avisa "El viaje tiene 2 asiento(s) disponible(s), pero necesita 3." y el asistente no avanza

#### Scenario: Asientos inactivos o reservados por otros
- **WHEN** el colectivo tiene 4 asientos sin boleto, pero uno está inactivo y otro está reservado por otra sesión, y la venta es de 3 pasajeros
- **THEN** se avisa "El viaje tiene 2 asiento(s) disponible(s), pero necesita 3." y el asistente no avanza

#### Scenario: Reservas propias al volver al paso 1
- **WHEN** el vendedor reservó 2 asientos de un viaje que no tiene otros libres, vuelve al paso 1 y avanza con 2 pasajeros sin cambiar el viaje
- **THEN** el asistente avanza

### Requirement: Corte de venta por fecha anterior
Sin el permiso "Vender después del límite de salida" (`tickets.vender_pasado_limite`), el sistema MUST rechazar la venta de un tramo cuyo viaje es de un día anterior a hoy. La regla queda desactivada cuando el límite de horas configurado es 0.

#### Scenario: Viaje de ayer sin permiso
- **WHEN** un vendedor sin el permiso intenta vender un tramo de un viaje de ayer, con el límite de horas mayor que 0
- **THEN** la venta se bloquea con un mensaje que indica que la fecha es anterior a hoy

#### Scenario: Límite en cero
- **WHEN** el límite de horas configurado es 0
- **THEN** se permite vender viajes de días anteriores sin el permiso

### Requirement: Corte de venta por horas desde la salida
Sin el permiso `tickets.vender_pasado_limite`, el sistema MUST rechazar la venta de un tramo cuando ya pasaron más horas que el límite configurado desde la salida del colectivo en la parada donde sube el pasajero: el origen en la ida y el destino de la ida en la vuelta. El límite por defecto es 5 horas; 0 lo desactiva.

#### Scenario: Pasado el límite
- **WHEN** el límite es 5 horas y el colectivo salió de la parada de subida hace 6 horas
- **THEN** un vendedor sin permiso no puede vender ese tramo

#### Scenario: Dentro del límite
- **WHEN** el colectivo salió de la parada de subida hace 4 horas, con límite de 5
- **THEN** la venta se permite

#### Scenario: Con permiso
- **WHEN** un usuario con el permiso vende un tramo fuera del límite
- **THEN** la venta se permite

#### Scenario: Parada de subida sin elegir
- **WHEN** todavía no hay parada de subida definida
- **THEN** solo se controla la fecha, no las horas

### Requirement: Puntos de control del corte de venta
El corte de venta MUST aplicarse de forma idéntica en tres puntos: al avanzar del paso "Buscar viaje", al listar horarios (los bloqueados no aparecen) y al emitir. Ningún rol del sistema trae el permiso por defecto. El corte aplica solo a ventas nuevas, no a la edición de boletos existentes.

#### Scenario: Horario vencido en el listado
- **WHEN** un vendedor sin permiso abre los horarios de hoy y uno salió hace más horas que el límite
- **THEN** ese horario no aparece entre las opciones

#### Scenario: Emisión forzada
- **WHEN** se fuerza la emisión de un tramo fuera de término sin el permiso
- **THEN** la emisión se rechaza con el título "Venta fuera de término"

### Requirement: Venta de ida y vuelta ("Diferido")
Con el interruptor "Diferido", el sistema SHALL vender ida y vuelta, siendo la vuelta el tramo inverso (destino → origen). Activarlo exige el tramo de ida completo y disponible, y una ruta activa que contenga ambas paradas. La fecha de vuelta MUST ser igual o posterior a la de ida.

#### Scenario: Ida incompleta
- **WHEN** el vendedor activa "Diferido" sin haber completado los datos de la ida
- **THEN** se avisa "Complete todos los campos del viaje de ida antes de activar Diferido." y el interruptor vuelve a apagarse

#### Scenario: Sin ruta de vuelta
- **WHEN** no existe ninguna ruta activa con ambas paradas
- **THEN** se avisa "Ruta de vuelta inexistente" y el interruptor vuelve a apagarse

#### Scenario: Fecha de vuelta anterior
- **WHEN** la fecha de vuelta es anterior a la de ida
- **THEN** se rechaza con "La fecha de vuelta debe ser igual o posterior a la fecha de ida."

### Requirement: Horarios ofrecidos para el tramo de vuelta
Los horarios de vuelta SHALL incluir los horarios activos de rutas activas de cualquier colectivo donde el tramo inverso es válido, no solo los del colectivo de la ida. Si la vuelta es el mismo día que la ida, MUST ofrecer solo horarios con salida posterior a la del horario de ida. También se aplica el corte de venta usando como parada de subida el destino de la ida.

#### Scenario: Vuelta en otro colectivo
- **WHEN** otro colectivo tiene un horario activo que recorre el tramo inverso
- **THEN** ese horario aparece entre las opciones de vuelta

#### Scenario: Vuelta el mismo día
- **WHEN** la ida sale a las 08:00 y la vuelta es el mismo día
- **THEN** solo se ofrecen horarios de vuelta con salida posterior a las 08:00

### Requirement: Selección exacta de asientos por tramo
En cada paso de asientos el vendedor MUST elegir exactamente un asiento por pasajero. Al avanzar, el sistema verifica que ninguno se haya vendido ni esté reservado por otra sesión. Si alguno ya no está disponible, informa sus números y no avanza.

#### Scenario: Cantidad incorrecta
- **WHEN** hay 2 pasajeros y se eligió 1 asiento de ida
- **THEN** se avisa "Debe seleccionar exactamente 2 asiento(s) de ida." y no se avanza

#### Scenario: Asiento vendido mientras se elegía
- **WHEN** al avanzar, uno de los asientos elegidos ya fue vendido
- **THEN** se informa el número de asiento y el vendedor debe elegir otro

### Requirement: Datos obligatorios del pasajero
Por cada pasajero se MUST cargar DNI numérico de 7 u 8 dígitos, apellido y nombre de 2 a 80 caracteres (letras, espacios, apóstrofo o guion). El teléfono es opcional; si se carga, MUST tener entre 7 y 12 dígitos. El DNI se valida al completarlo, no solo al avanzar.

#### Scenario: DNI corto
- **WHEN** el DNI tiene 6 dígitos
- **THEN** se rechaza con "El DNI debe tener entre 7 y 8 dígitos."

#### Scenario: Nombre con números
- **WHEN** el nombre contiene dígitos
- **THEN** se rechaza con "El nombre solo puede contener letras y espacios."

### Requirement: Autocompletado desde el padrón de clientes
Cuando el DNI cargado tiene al menos 7 dígitos y coincide con un cliente del padrón, el sistema SHALL completar apellido y nombre, y el teléfono si el del cliente tiene entre 7 y 12 dígitos. Con menos de 7 dígitos no se busca ni se valida.

#### Scenario: DNI del padrón
- **WHEN** se ingresa el DNI de un cliente registrado
- **THEN** apellido, nombre y teléfono se completan con los datos del cliente

### Requirement: Bloqueo de clientes con prohibición de compra
El sistema MUST rechazar la venta si algún pasajero tiene un DNI de un cliente marcado sin permiso de compra. El control se hace al validar el DNI y otra vez al emitir. El mensaje incluye nombre, DNI y el motivo, si está cargado.

#### Scenario: Pasajero baneado
- **WHEN** un pasajero tiene el DNI de un cliente marcado sin permiso de compra
- **THEN** la venta no se emite y el mensaje muestra nombre, DNI y motivo del bloqueo

### Requirement: Acompañantes del pasajero
Si la configuración permite acompañantes y el pasajero tiene DNI, nombre y apellido, el vendedor SHALL poder indicar que viaja con un menor o con mascotas, pero no ambos. El menor exige apellido, nombre y DNI. Las mascotas exigen descripción y cantidad de 1 a 5.

#### Scenario: Menor y mascotas a la vez
- **WHEN** el vendedor marca "¿Viaja con mascotas?" con "¿Viaja con un menor?" ya marcado
- **THEN** se desmarca el menor y se borran sus datos

#### Scenario: Acompañantes desactivados
- **WHEN** la configuración no permite acompañantes
- **THEN** las opciones de menor y mascotas no se muestran

### Requirement: Menor acompañante sin asiento ni boleto propio
Un menor acompañante SHALL registrarse como pasajero vinculado al adulto, pero MUST NOT ocupar asiento ni generar boleto propio. Su presencia queda marcada en el boleto del adulto.

#### Scenario: Adulto con menor
- **WHEN** se emite una venta de 1 pasajero que viaja con un menor
- **THEN** se crean dos pasajeros (adulto y menor), pero un solo boleto de ida con un solo asiento

### Requirement: Precio y método de pago por pasajero
Cada pasajero MUST tener precio (entre 0 y 999999) y un método de pago activo. Al emitir, el sistema rechaza métodos inexistentes o inactivos. Quien tiene el permiso `payment_methods.create` puede crear un método de pago nuevo desde el mismo selector, que queda activo y seleccionado.

#### Scenario: Método inactivo
- **WHEN** se emite una venta con un método de pago inactivo
- **THEN** se rechaza con "Hay un método de pago inválido o inactivo en la venta. Recargá el formulario e intentá de nuevo."

### Requirement: Venta sin fecha
Con el permiso `tickets.vender_sin_fecha`, el vendedor SHALL poder vender la ida, la vuelta o ambas sin fecha ni horario. Esos tramos quedan como boletos pendientes, sin viaje ni asiento, que conservan el colectivo. Las opciones solo se muestran si el usuario tiene el permiso y al menos un rol lo tiene asignado.

#### Scenario: Sin permiso
- **WHEN** un vendedor sin el permiso fuerza una venta sin fecha
- **THEN** se rechaza con "No tenés permiso para vender boletos sin fecha/horario."

#### Scenario: Ningún rol con el permiso
- **WHEN** ningún rol tiene asignado el permiso, aunque el usuario sea superadministrador
- **THEN** las opciones de venta sin fecha no se muestran

#### Scenario: Solo ida sin fecha
- **WHEN** se vende solo ida sin fecha
- **THEN** se crea un boleto pendiente por pasajero, sin viaje ni asiento, que no es de ida y vuelta

### Requirement: Venta sin fecha solo a pasajeros del padrón
Si algún tramo se vende sin fecha, todos los pasajeros MUST estar registrados en el padrón de clientes. El control se hace al validar el DNI y otra vez al emitir. Las ventas con fecha no tienen esta exigencia.

#### Scenario: DNI fuera del padrón
- **WHEN** en una venta sin fecha un pasajero tiene un DNI no registrado
- **THEN** la venta se rechaza indicando los DNIs sin registrar

#### Scenario: Venta con fecha
- **WHEN** la venta es con fecha y el DNI no está en el padrón
- **THEN** la venta se emite normalmente

### Requirement: Ventas mixtas con un tramo sin fecha
En ida y vuelta con un solo tramo sin fecha, el tramo con fecha SHALL emitirse con viaje y asiento, y el otro como boleto pendiente. Con la vuelta pendiente, la ida queda sin viaje de vuelta y la vuelta queda marcada como tramo de vuelta. Con la ida pendiente, la ida MUST quedar ligada al viaje de vuelta ya emitido.

#### Scenario: Ida con fecha, vuelta pendiente
- **WHEN** se vende ida con fecha y vuelta sin fecha
- **THEN** la ida tiene viaje y asiento, y la vuelta es un boleto pendiente marcado como tramo de vuelta, con precio 0

#### Scenario: Ida pendiente, vuelta con fecha
- **WHEN** se vende ida sin fecha y vuelta con fecha
- **THEN** el boleto de ida pendiente queda ligado al viaje de vuelta emitido

### Requirement: Estructura de la venta emitida
Al emitir, el sistema SHALL crear una venta con el usuario vendedor y la fecha y hora de emisión. Por cada pasajero adulto y por cada tramo crea un boleto. El total de la venta MUST ser la suma de los precios de sus boletos.

#### Scenario: Dos pasajeros solo ida
- **WHEN** se emite una venta de 2 pasajeros, solo ida, con precios 1000 y 1500
- **THEN** se crea una venta con 2 boletos y total 2500

### Requirement: Boletos de vuelta
En ida y vuelta, el precio completo SHALL quedar en el boleto de ida, y el boleto de vuelta MUST tener precio 0 y origen y destino invertidos. El boleto de ida con vuelta fechada guarda la referencia al viaje de vuelta. Los boletos de vuelta repiten el método de pago y los acompañantes del pasajero.

#### Scenario: Ida y vuelta con fechas
- **WHEN** se emite ida y vuelta de 1 pasajero, Salta → Cafayate, a 5000
- **THEN** el boleto de ida (Salta → Cafayate) vale 5000 y referencia al viaje de vuelta, y el de vuelta (Cafayate → Salta) vale 0

### Requirement: Identificación del tramo de vuelta
Un boleto se considera tramo de vuelta solo si está marcado explícitamente como tal. La emisión MUST marcar cada boleto como ida o como vuelta, en la venta con fecha, sin fecha y mixta. Si quien crea un boleto no indica la marca, el sistema la completa con la convención histórica (ida y vuelta, sin viaje de vuelta y precio 0). Una marca explícita de ida MUST NOT cambiarse por esa convención, y el precio no interviene al identificar la vuelta de un boleto existente.

#### Scenario: Boleto histórico sin marca
- **WHEN** un boleto anterior a la marca explícita es de ida y vuelta, sin viaje de vuelta y con precio 0, vigente o dado de baja
- **THEN** después de la migración de datos queda marcado como tramo de vuelta y el sistema lo trata como tal

#### Scenario: Creación con precio 0 sin viaje de vuelta
- **WHEN** se crea un boleto de ida y vuelta, sin viaje de vuelta y con precio 0, sin indicar si es tramo de vuelta
- **THEN** queda marcado como tramo de vuelta
- **AND** si se indica explícitamente que no es tramo de vuelta, queda como ida

#### Scenario: Ida a precio 0 en venta mixta
- **WHEN** se emite una venta de ida y vuelta con la ida con fecha, la vuelta sin fecha y precio 0
- **THEN** el boleto de ida queda como ida, con viaje y asiento, y solo el boleto pendiente queda como tramo de vuelta

#### Scenario: Ambos tramos sin fecha a precio 0
- **WHEN** se emite una venta de ida y vuelta con ambos tramos sin fecha y precio 0
- **THEN** cada pasajero tiene un boleto de ida y uno de vuelta, y solo el de vuelta queda marcado como tramo de vuelta

#### Scenario: Venta con fecha a precio 0
- **WHEN** se emite una venta de ida y vuelta con ambos tramos con fecha y precio 0
- **THEN** el boleto de ida queda como ida y el boleto de vuelta queda marcado como tramo de vuelta

#### Scenario: La ida a precio 0 se trata como ida después de la venta
- **WHEN** se consulta, reprograma o edita la ida a precio 0 de una venta de ida y vuelta
- **THEN** se muestra y se reprograma como ida, se identifica su vuelta por la marca, y su precio se puede editar como el de cualquier ida

#### Scenario: Corrección de ventas ya afectadas
- **WHEN** antes del cambio quedó una venta donde un pasajero tiene dos boletos de ida y vuelta marcados como tramo de vuelta y ninguno como ida
- **THEN** la migración de datos deja como ida el boleto creado primero y como vuelta el otro

### Requirement: Cierre de una venta exitosa
Tras emitir correctamente, el sistema SHALL liberar las reservas temporales de asientos de la sesión del vendedor en los viajes de esa venta.

#### Scenario: Venta completada con descarga automática
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está activado
- **THEN** las reservas de la sesión en los viajes de la venta se eliminan y se abre la descarga de los boletos en otra pestaña

#### Scenario: Venta completada sin descarga automática
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está desactivado
- **THEN** las reservas de la sesión en los viajes de la venta se eliminan y no se descarga ningún PDF: se presenta la previsualización con link compartible

#### Scenario: Falla la generación del PDF
- **WHEN** la emisión se confirmó pero falla la generación del PDF
- **THEN** la venta y sus boletos quedan emitidos y el vendedor ve que no se pudo generar el PDF

#### Scenario: Otra venta en curso
- **WHEN** el vendedor emite una venta mientras tiene en otra pestaña una venta con reservas en un viaje distinto
- **THEN** las reservas de la otra venta se conservan

### Requirement: Entrega de los boletos al cerrar la venta
Tras emitir correctamente, el sistema SHALL entregar los boletos según el parámetro "Descargar PDF automáticamente al vender" (activado por defecto). Activado, abre la descarga en una pestaña nueva y vuelve al inicio del panel. Desactivado, presenta la previsualización con link compartible en lugar de descargar.

#### Scenario: Descarga automática activada
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está activado
- **THEN** se abre la descarga de los boletos en una pestaña nueva y el vendedor vuelve al inicio del panel

#### Scenario: Descarga automática desactivada
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está desactivado
- **THEN** no se descarga ningún PDF y se presenta la previsualización con link compartible

#### Scenario: Parámetro sin configurar
- **WHEN** la emisión termina sin errores y nadie cambió el parámetro de descarga automática
- **THEN** se abre la descarga de los boletos en una pestaña nueva, como con el parámetro activado

### Requirement: Notificación de la entrega por link
Con el parámetro "Descargar PDF automáticamente al vender" desactivado, el vendedor MUST ver al cerrar la venta una notificación con estas acciones sobre el link entregado: abrir el link, copiar el link, descargar el PDF y, si la venta tiene teléfonos, compartir por WhatsApp.

#### Scenario: Notificación de la entrega por link
- **WHEN** la emisión termina sin errores con el parámetro desactivado
- **THEN** el vendedor ve una notificación de venta completada con "Abrir link", "Copiar link" y "Descargar PDF" del link entregado, y "Compartir por WhatsApp" solo si la venta tiene algún teléfono válido

#### Scenario: Copiar el link desde la notificación
- **WHEN** el vendedor toca "Copiar link" en la notificación
- **THEN** la URL del link de la venta o del boleto queda en el portapapeles, sin abrir ni descargar nada

### Requirement: Falla de la entrega sin revertir la venta
La entrega de los boletos MUST ocurrir después de confirmada la emisión. Si la entrega falla, la venta y sus boletos siguen emitidos y el vendedor ve el error de la entrega.

#### Scenario: Falla la generación del PDF al entregar
- **WHEN** la emisión se confirmó pero falla la generación del PDF de los boletos
- **THEN** la venta y sus boletos quedan emitidos y el vendedor ve que no se pudo generar el PDF

### Requirement: Cancelación de la venta
La acción "Cancelar" SHALL pedir confirmación ("Los cambios se perderán"). Al confirmar, MUST liberar las reservas de asientos de la sesión en los viajes de esa venta y volver al inicio del panel.

#### Scenario: Cancelar con asientos elegidos
- **WHEN** el vendedor con asientos reservados confirma "Cancelar"
- **THEN** sus reservas en los viajes de la venta se liberan y vuelve al inicio del panel

### Requirement: DNI únicos dentro de la venta
Una venta MUST NOT incluir el mismo DNI más de una vez, sumando los DNI de los pasajeros adultos y de sus menores acompañantes. Al validarse, un campo DNI cuyo valor ya está cargado en otro campo DNI de la venta SHALL mostrar "Este DNI ya está cargado en otro pasajero de la venta.". La emisión MUST rechazar la venta si llega con DNI repetidos, sin crear nada.

#### Scenario: Dos adultos con el mismo DNI
- **WHEN** el vendedor carga el mismo DNI en dos pasajeros de la venta
- **THEN** el campo DNI que se está cargando muestra "Este DNI ya está cargado en otro pasajero de la venta." y no se puede finalizar

#### Scenario: Menor con el DNI de un adulto de la venta
- **WHEN** el DNI del menor acompañante de un pasajero coincide con el DNI de otro pasajero de la venta
- **THEN** el campo DNI del menor muestra el error y no se puede finalizar

#### Scenario: Emisión forzada con DNI repetidos
- **WHEN** llega una emisión con dos pasajeros con el mismo DNI, sin pasar por la validación del formulario
- **THEN** la venta se rechaza y no se crea venta, pasajeros ni boletos

### Requirement: Aviso de asientos liberados al finalizar
Si al presionar "Finalizar" la venta no tiene los asientos de ida o de vuelta, por ejemplo porque la reserva venció y se liberaron, el sistema SHALL mostrar una notificación persistente. Su título es "Faltan los asientos de ida", "Faltan los asientos de vuelta" o "Faltan los asientos" si faltan ambos. Su texto explica que la reserva venció e indica presionar «Anterior» hasta el paso de asientos, elegirlos de nuevo y volver a finalizar. No se emite nada.

#### Scenario: Reserva de ida vencida
- **WHEN** los asientos de ida se liberaron y el vendedor presiona "Finalizar"
- **THEN** ve "Faltan los asientos de ida" con el texto "La reserva de los asientos venció y se liberaron. Presioná «Anterior» hasta el paso «Asientos (Ida)», elegí los asientos de nuevo y volvé a finalizar la venta." y no se crea la venta

#### Scenario: Solo la vuelta liberada
- **WHEN** solo los asientos de vuelta se liberaron y el vendedor presiona "Finalizar"
- **THEN** ve "Faltan los asientos de vuelta" y el texto nombra el paso «Asientos (Vuelta)»

#### Scenario: Asientos reservados
- **WHEN** los asientos siguen reservados y el vendedor presiona "Finalizar"
- **THEN** no se muestra este aviso
