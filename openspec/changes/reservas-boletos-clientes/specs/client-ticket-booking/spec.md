## Purpose

Define cómo un cliente reserva boletos con fecha desde su panel, con los asientos guardados hasta que el personal verifica el pago, y cómo ve, paga y cancela sus reservas, sin que una reserva cuente como boleto vendido hasta su confirmación.

## ADDED Requirements

### Requirement: Reservas disponibles solo con la función activa
El cliente SHALL ver "Mis reservas" y poder reservar solo si el panel de clientes, el interruptor "Reservas de clientes activas" y el precio fijo están prendidos a la vez. Si alguno está apagado, la página MUST NOT figurar en el menú y abrirla por URL MUST responder 404, con o sin reservas guardadas.

#### Scenario: Todo prendido
- **GIVEN** el panel de clientes, las reservas y el precio fijo prendidos
- **WHEN** un cliente con sesión abre el panel
- **THEN** ve "Mis reservas" en el menú

#### Scenario: Reservas apagadas
- **GIVEN** las reservas apagadas
- **WHEN** un cliente abre `/clientes/mis-reservas`
- **THEN** recibe un 404 y no ve "Mis reservas" en el menú

#### Scenario: Precio fijo apagado
- **GIVEN** las reservas prendidas y el precio fijo apagado
- **WHEN** un cliente abre el panel
- **THEN** no ve "Mis reservas"

#### Scenario: Mis boletos no cambia
- **GIVEN** las reservas apagadas
- **WHEN** un cliente abre "Mis boletos"
- **THEN** ve sus boletos y puede elegir fecha como hasta ahora

### Requirement: Página "Mis reservas"
"Mis reservas" SHALL listar las reservas que hizo el cliente con sesión, de la más nueva a la más vieja, con su número, recorrido, fechas y horarios, pasajeros, monto, estado y, si está pendiente, su hora límite. MUST NOT mostrar reservas hechas por otro cliente, aunque él sea pasajero de ellas. Sin reservas, MUST mostrar un mensaje que invita a reservar.

#### Scenario: Lista propia
- **GIVEN** un cliente con dos reservas propias y otro cliente que lo cargó como pasajero en una reserva
- **WHEN** abre "Mis reservas"
- **THEN** ve solo sus dos reservas

#### Scenario: Sin reservas
- **WHEN** un cliente que nunca reservó abre "Mis reservas"
- **THEN** ve que no tiene reservas y el botón "Reservar"

### Requirement: Estados de una reserva
Una reserva SHALL estar en uno de estos estados: "Pendiente de pago", "Confirmada", "Rechazada", "Cancelada" o "Vencida". Una reserva pendiente cuya hora límite ya pasó MUST mostrarse como "Vencida" aunque nadie la haya procesado. Una rechazada MUST mostrar el motivo que cargó el personal. Una confirmada MUST indicar que sus boletos están en "Mis boletos".

#### Scenario: Vencida sin procesar
- **GIVEN** una reserva pendiente con hora límite 17/11 14:30
- **WHEN** el cliente abre "Mis reservas" el 17/11 a las 15:00
- **THEN** la ve como "Vencida"

#### Scenario: Rechazada
- **GIVEN** una reserva rechazada con el motivo "El monto transferido no coincide"
- **WHEN** el cliente abre "Mis reservas"
- **THEN** la ve como "Rechazada" con ese motivo

#### Scenario: Confirmada
- **GIVEN** una reserva confirmada
- **WHEN** el cliente abre "Mis reservas"
- **THEN** la ve como "Confirmada" con un acceso a "Mis boletos"

### Requirement: Reservar con fecha
"Reservar" SHALL abrir un asistente donde el cliente elige origen, destino, fecha y horario del tramo de ida, opcionalmente un tramo de vuelta con su fecha y horario, la cantidad de pasajeros y sus asientos, los datos de cada pasajero y el método de pago, y termina en un resumen con el monto, la hora límite y "Confirmar reserva". La reserva MUST crearse solo al confirmar el resumen.

#### Scenario: Reserva de ida
- **WHEN** el cliente completa el asistente con un tramo de ida, 2 pasajeros y sus asientos, y toca "Confirmar reserva"
- **THEN** se crea una reserva pendiente con esos datos y la ve en "Mis reservas"

#### Scenario: Ida y vuelta
- **WHEN** el cliente elige ida y vuelta, con fecha y horario en los dos tramos, y asientos en cada uno
- **THEN** la reserva guarda los dos tramos con sus asientos

#### Scenario: Cerrar sin confirmar
- **WHEN** el cliente cierra el asistente antes de confirmar
- **THEN** no se crea ninguna reserva ni queda ningún asiento bloqueado

### Requirement: Sin reservas sin fecha
El asistente MUST exigir fecha y horario en cada tramo reservado. Un pedido sin fecha u horario en un tramo MUST rechazarse sin crear la reserva.

#### Scenario: Pedido sin fecha
- **WHEN** llega un pedido de reserva sin horario en el tramo de ida
- **THEN** se rechaza y no se crea nada

### Requirement: Horarios que se pueden reservar
El asistente SHALL ofrecer solo horarios de días con servicio cuya salida desde el origen del servicio queda al menos la "Anticipación mínima para reservar" después de ahora y cuya salida menos el "Corte antes de la salida" es posterior a ahora. El servidor MUST volver a controlarlo al confirmar y rechazar con un mensaje que diga la anticipación exigida.

#### Scenario: Horario muy cercano
- **GIVEN** una anticipación para reservar de 24 horas
- **WHEN** el cliente elige un día cuyo único horario sale en 10 horas
- **THEN** no se ofrece ese horario y se le indica que elija otro día

#### Scenario: Anticipación en cero y corte de 1 hora
- **GIVEN** anticipación 0 y corte de 1 hora
- **WHEN** el cliente elige un horario que sale en 30 minutos
- **THEN** no se ofrece

#### Scenario: Pedido manipulado
- **WHEN** se confirma una reserva con un horario fuera de la anticipación, saltando el selector
- **THEN** se rechaza con el mensaje de la anticipación y no se crea nada

#### Scenario: Día sin servicio
- **WHEN** el cliente elige un día sin servicio
- **THEN** no puede reservar ese día

### Requirement: Pasajeros de la reserva
La reserva SHALL admitir entre 1 y el límite de "Pasajeros por venta" pasajeros, con DNI, apellido, nombre y teléfono, validados como en la venta del mostrador. El primer pasajero MUST venir precargado con los datos del cliente y MUST poder cambiarse: el cliente no tiene que viajar. Los DNI de una misma reserva MUST ser distintos.

#### Scenario: Precarga
- **WHEN** el cliente llega al paso de pasajeros
- **THEN** el primer pasajero tiene su DNI, apellido, nombre y teléfono

#### Scenario: Reserva para otra persona
- **WHEN** el cliente reemplaza el primer pasajero por su hijo y confirma
- **THEN** la reserva se crea con el hijo como único pasajero

#### Scenario: Más que el límite
- **GIVEN** el límite de pasajeros por venta en 4
- **WHEN** llega un pedido de reserva con 5 pasajeros
- **THEN** se rechaza sin crear nada

#### Scenario: DNI repetido
- **WHEN** el cliente carga el mismo DNI en dos pasajeros
- **THEN** ve el error en el segundo y no puede avanzar

### Requirement: Acompañantes con las reglas del mostrador
Con "Vender boletos con acompañante" prendido, cada pasajero SHALL poder indicar que viaja con un menor, con sus datos, o con mascotas, como en la venta del mostrador. Apagado, MUST NOT ofrecerse ni aceptarse.

#### Scenario: Con menor
- **WHEN** un pasajero indica que viaja con un menor
- **THEN** se piden los datos del menor y el monto suma el seguro

#### Scenario: Acompañantes apagados
- **GIVEN** "Vender boletos con acompañante" apagado
- **WHEN** llega un pedido con un menor
- **THEN** se rechaza sin crear nada

### Requirement: Precio congelado al reservar
El monto de cada pasajero SHALL calcularse con el precio fijo vigente al confirmar la reserva, igual que en el mostrador: precio del boleto más el seguro si viaja con menor, por 2 si es ida y vuelta, sin sumar mascotas. La reserva MUST guardar ese monto y MUST NOT recalcularlo si el precio fijo cambia después.

#### Scenario: Monto de la reserva
- **GIVEN** precio del boleto $5.000 y seguro $800
- **WHEN** el cliente reserva ida y vuelta para un pasajero con menor
- **THEN** el monto es $11.600

#### Scenario: Cambio de precio posterior
- **GIVEN** una reserva pendiente de $5.000
- **WHEN** el precio fijo sube a $6.000
- **THEN** la reserva sigue siendo de $5.000

### Requirement: Asientos guardados hasta el vencimiento
Al crearse la reserva, sus asientos SHALL quedar bloqueados en cada tramo hasta la hora límite. Si algún asiento elegido está vendido o bloqueado al confirmar, la reserva MUST NOT crearse y el cliente MUST ver qué asientos ya no están disponibles para elegir otros.

#### Scenario: Asiento tomado mientras reservaba
- **WHEN** el cliente confirma y el asiento 5 se vendió un momento antes
- **THEN** no se crea la reserva y se le avisa que el asiento 5 ya no está disponible

### Requirement: Hora límite de la reserva
La hora límite SHALL ser la más temprana entre la creación más las "Horas de reserva del asiento" y la salida desde el origen del servicio del primer tramo menos el "Corte antes de la salida". Se calcula al crear la reserva y MUST NOT cambiar si después cambian los parámetros. El cliente MUST verla en el resumen antes de confirmar y en "Mis reservas", con el aviso de que tiene que abonar antes de esa hora.

#### Scenario: Corte por horas de reserva
- **GIVEN** horas de reserva 24, corte 1 hora, y un viaje que sale en 3 días
- **WHEN** el cliente reserva el 16/11 a las 10:00
- **THEN** la hora límite es el 17/11 a las 10:00

#### Scenario: Corte por salida
- **GIVEN** horas de reserva 24, corte 1 hora, y un viaje que sale el 16/11 a las 20:00
- **WHEN** el cliente reserva el 16/11 a las 10:00
- **THEN** la hora límite es el 16/11 a las 19:00 y el aviso dice que tiene que abonar antes de esa hora

#### Scenario: Cambio de parámetros posterior
- **GIVEN** una reserva con hora límite 17/11 10:00
- **WHEN** las horas de reserva bajan a 6
- **THEN** la hora límite sigue siendo 17/11 10:00

### Requirement: Método de pago
El cliente SHALL elegir un método de pago entre los habilitados para reservas que estén activos. Sin métodos habilitados y activos, MUST NOT poder reservar y MUST ver que las reservas no están disponibles por el momento. Un método no habilitado enviado en el pedido MUST rechazarse.

#### Scenario: Métodos ofrecidos
- **GIVEN** "Transferencia" y "Efectivo" habilitados para reservas, y "Tarjeta" no
- **WHEN** el cliente llega al paso de pago
- **THEN** puede elegir Transferencia o Efectivo, y no Tarjeta

#### Scenario: Método manipulado
- **WHEN** llega un pedido con "Tarjeta" como método
- **THEN** se rechaza sin crear nada

### Requirement: Datos para pagar
Una reserva pendiente SHALL mostrar su número, el monto exacto, la hora límite y, si están cargados, los datos para transferir (alias, CBU, titular y banco si lo tiene), sin importar el método elegido. Debajo MUST mostrar las líneas de contacto con sus botones de llamar y WhatsApp; el de WhatsApp MUST abrir la conversación con el mensaje "Hola, les envío el comprobante de la reserva N°{número} por ${monto}.".

#### Scenario: Con datos bancarios
- **GIVEN** alias, CBU y titular cargados
- **WHEN** el cliente mira una reserva pendiente con método Efectivo
- **THEN** ve igual los datos para transferir

#### Scenario: Sin datos bancarios
- **GIVEN** que no hay datos para transferir cargados
- **WHEN** el cliente mira una reserva pendiente
- **THEN** no ve esa sección y sí el número, el monto y la hora límite

#### Scenario: WhatsApp con el comprobante
- **GIVEN** la reserva N°123 por $25.000 y una línea de contacto
- **WHEN** el cliente toca WhatsApp en la reserva
- **THEN** se abre la conversación con "Hola, les envío el comprobante de la reserva N°123 por $25.000."

### Requirement: Cancelar una reserva pendiente
El cliente SHALL poder cancelar una reserva pendiente propia, confirmándolo en un modal. Al cancelar, la reserva pasa a "Cancelada" y sus asientos MUST liberarse en el momento. MUST NOT poder cancelar una reserva confirmada, rechazada, vencida o ajena.

#### Scenario: Cancelar
- **WHEN** el cliente cancela una reserva pendiente y confirma
- **THEN** la reserva queda "Cancelada" y sus asientos se pueden vender

#### Scenario: Reserva confirmada
- **WHEN** el cliente intenta cancelar una reserva confirmada
- **THEN** no puede

### Requirement: Límite de reservas pendientes
Un cliente MUST NOT tener más de 2 reservas pendientes sin vencer a la vez. Con 2, el botón "Reservar" MUST mostrarse deshabilitado con un aviso que lo explique, y el servidor MUST rechazar una tercera.

#### Scenario: Tercera reserva
- **GIVEN** un cliente con 2 reservas pendientes sin vencer
- **WHEN** intenta reservar otra
- **THEN** no puede y ve que tiene que esperar a que se resuelva alguna o cancelarla

#### Scenario: Una vencida no cuenta
- **GIVEN** un cliente con 2 reservas pendientes, una ya vencida
- **WHEN** reserva otra
- **THEN** puede

### Requirement: Cliente baneado
Un cliente baneado SHALL poder entrar al panel y usar sus boletos, pero MUST NOT poder reservar para nadie. En "Mis reservas", el botón "Reservar" MUST mostrarse deshabilitado con el aviso "No podés hacer reservas desde el panel. Nos reservamos el derecho de admisión." y el servidor MUST rechazar cualquier pedido suyo.

#### Scenario: Baneado
- **GIVEN** un cliente baneado
- **WHEN** abre "Mis reservas"
- **THEN** ve el aviso y no puede reservar

#### Scenario: Pedido forzado
- **GIVEN** un cliente baneado
- **WHEN** envía un pedido de reserva saltando la interfaz
- **THEN** se rechaza sin crear nada

### Requirement: Pasajero baneado
Si el DNI de un pasajero o de un menor de la reserva es de un cliente baneado, la reserva MUST NOT crearse y ese DNI MUST mostrar el mensaje "No podemos reservar para este DNI. Comunicate con nosotros.", sin el motivo del baneo.

#### Scenario: Pasajero baneado
- **WHEN** el cliente carga como pasajero un DNI baneado
- **THEN** ve el mensaje genérico debajo de ese DNI y no puede confirmar

### Requirement: Reservas separadas de los boletos
Hasta que se confirma, una reserva MUST NOT aparecer en "Mis boletos", ni en los boletos, ventas, saldos, listas de pasajeros, PDF ni links del panel de administración. Al confirmarse, sus boletos SHALL aparecer en "Mis boletos" de cada pasajero, como cualquier boleto.

#### Scenario: Pendiente
- **GIVEN** una reserva pendiente
- **WHEN** el cliente abre "Mis boletos" y un vendedor abre la lista de pasajeros del viaje
- **THEN** ninguno ve boletos de esa reserva

#### Scenario: Confirmada
- **WHEN** el personal confirma la reserva
- **THEN** sus boletos aparecen en "Mis boletos"
