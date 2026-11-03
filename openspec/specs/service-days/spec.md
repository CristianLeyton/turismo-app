# service-days Specification

## Purpose

Define en qué fechas hay servicio de colectivos, para que solo se asignen viajes en días con servicio. La regla se configura una vez y se aplica igual en la venta, la reprogramación, el panel de clientes y la creación de viajes.

## Requirements

### Requirement: Fecha con servicio
Una fecha SHALL tener servicio cuando su día de la semana está configurado con servicio, salvo que exista una excepción para esa fecha, que manda sobre el día de la semana. Una excepción "con servicio" habilita la fecha y una excepción "sin servicio" la bloquea.

#### Scenario: Día de la semana con servicio
- **WHEN** los lunes tienen servicio y no hay excepción para el lunes 16/11/2026
- **THEN** el 16/11/2026 tiene servicio

#### Scenario: Excepción con servicio
- **WHEN** los domingos no tienen servicio y existe una excepción "con servicio" para el domingo 13/09/2026
- **THEN** el 13/09/2026 tiene servicio

#### Scenario: Excepción sin servicio
- **WHEN** los miércoles tienen servicio y existe una excepción "sin servicio" para el miércoles 25/12/2026
- **THEN** el 25/12/2026 no tiene servicio

### Requirement: Configuración de días de servicio
Un usuario con acceso a Configuración > Parametros SHALL poder elegir qué días de la semana tienen servicio y administrar las excepciones por fecha (fecha, tipo "con servicio" o "sin servicio" y motivo opcional). Cada fecha MUST tener como máximo una excepción. Los cambios se aplican desde que se guardan.

#### Scenario: Agregar un feriado
- **WHEN** el administrador agrega la excepción 25/12/2026 "sin servicio" con motivo "Navidad" y guarda
- **THEN** el 25/12/2026 deja de tener servicio

#### Scenario: Fecha repetida
- **WHEN** el administrador intenta guardar dos excepciones para la misma fecha
- **THEN** el guardado se rechaza con un error de validación y la configuración no cambia

### Requirement: Configuración inicial
Mientras nadie guarde una configuración de días de servicio, el sistema SHALL usar lunes a sábado con servicio, domingo sin servicio y una excepción "con servicio" para el 13/09/2026.

#### Scenario: Sistema recién actualizado
- **WHEN** todavía no se guardó ninguna configuración de días de servicio
- **THEN** los domingos no tienen servicio, salvo el 13/09/2026, y los demás días sí

### Requirement: Selectores de fecha de viaje
Los selectores que asignan la fecha de un viaje (ida y vuelta de la venta, ida y vuelta de la reprogramación, asignación de fecha del panel de clientes y creación de un viaje) MUST deshabilitar las fechas sin servicio, salvo para un usuario con el permiso para operar en días sin servicio, según el requisito correspondiente.

#### Scenario: Calendario de la venta
- **WHEN** un vendedor sin el permiso abre el calendario de ida y el 25/12/2026 no tiene servicio
- **THEN** el 25/12/2026 aparece deshabilitado

#### Scenario: Calendario del panel de clientes
- **WHEN** un cliente abre el calendario para asignar fecha a su boleto
- **THEN** las fechas sin servicio aparecen deshabilitadas

### Requirement: Validación en el servidor
Al emitir una venta, reprogramar un boleto, asignar fecha desde el panel de clientes o crear un viaje, el sistema MUST rechazar una fecha sin servicio aunque el pedido no haya pasado por el calendario, salvo que se aplique el permiso para operar en días sin servicio. El rechazo informa la fecha y que es un día sin servicio, y no crea ni modifica datos.

#### Scenario: Pedido con fecha sin servicio
- **WHEN** llega una emisión con fecha de ida 25/12/2026, que no tiene servicio, de un vendedor sin el permiso
- **THEN** la emisión se rechaza indicando que el 25/12/2026 es un día sin servicio, y no se crea la venta

### Requirement: Permiso para operar en días sin servicio
El sistema SHALL ofrecer el permiso `tickets.vender_dia_sin_servicio` ("Vender en días sin servicio"). Ningún rol lo trae por defecto y el superadmin lo tiene siempre. Quien lo tenga MUST poder elegir y confirmar fechas sin servicio en la venta, la reprogramación y la creación de viajes.

#### Scenario: Vendedor con permiso
- **WHEN** un vendedor con el permiso elige el 25/12/2026, que no tiene servicio, y emite la venta
- **THEN** la venta se emite normalmente

#### Scenario: Roles por defecto
- **WHEN** se sincronizan los permisos de los roles del sistema
- **THEN** ningún rol recibe `tickets.vender_dia_sin_servicio`

### Requirement: Aviso al elegir un día sin servicio
Cuando un usuario con el permiso elige una fecha sin servicio, el formulario SHALL mostrar un aviso junto al selector que indique que la fecha no tiene servicio y, si existe, el motivo de la excepción.

#### Scenario: Feriado con motivo
- **WHEN** un vendedor con el permiso elige el 25/12/2026, configurado "sin servicio" con motivo "Navidad"
- **THEN** junto a la fecha ve un aviso de día sin servicio que menciona "Navidad"

### Requirement: El panel de clientes nunca opera en días sin servicio
La asignación de fecha desde el panel de clientes MUST rechazar siempre las fechas sin servicio, aunque en el mismo navegador haya un usuario del panel de administración con el permiso.

#### Scenario: Cliente con administrador logueado
- **WHEN** un cliente asigna el 25/12/2026, que no tiene servicio, desde un navegador donde también hay una sesión de superadmin
- **THEN** la asignación se rechaza

### Requirement: Datos existentes en días sin servicio
Los boletos y viajes que ya tienen una fecha sin servicio MUST seguir siendo válidos: se pueden ver, editar, imprimir y dar de baja. La regla solo se evalúa en las operaciones que eligen una fecha (venta, reprogramación, asignación desde el panel de clientes y creación de viaje), aunque se elija la misma fecha que ya tenía el boleto.

#### Scenario: Boleto en un día que pasa a no tener servicio
- **WHEN** existe un boleto para el 25/12/2026 y después se configura esa fecha "sin servicio"
- **THEN** el boleto sigue vigente y se puede editar sin cambiar la fecha

#### Scenario: Reprogramar otro dato sin cambiar la fecha
- **WHEN** se reprograma un boleto del 25/12/2026, sin servicio, manteniendo la fecha pero cambiando de asiento, por alguien sin el permiso
- **THEN** la reprogramación se rechaza, porque se confirma una fecha sin servicio

### Requirement: Alcance de la regla
La regla de días de servicio MUST NOT aplicarse a los filtros por fecha de los listados, a las fechas de pago ni a la fecha de venta.

#### Scenario: Filtro del listado de boletos
- **WHEN** se filtra el listado de boletos por un domingo sin servicio
- **THEN** la fecha se puede elegir y el filtro funciona normalmente
