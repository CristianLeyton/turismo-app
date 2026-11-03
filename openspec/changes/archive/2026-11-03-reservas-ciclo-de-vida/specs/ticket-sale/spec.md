# Spec Delta

## MODIFIED Requirements

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

### Requirement: Cierre de una venta exitosa
Tras emitir correctamente, el sistema SHALL liberar las reservas temporales de asientos de la sesión del vendedor en los viajes de esa venta y entregar los boletos según el parámetro "Descargar PDF automáticamente al vender" (activado por defecto). Activado, abre la descarga en una pestaña nueva y vuelve al inicio del panel. Desactivado, presenta la previsualización con link compartible en lugar de descargar. La entrega MUST ocurrir después de confirmada la emisión: si falla, la venta y sus boletos siguen emitidos y el vendedor ve el error de la entrega.

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

### Requirement: Cancelación de la venta
La acción "Cancelar" SHALL pedir confirmación ("Los cambios se perderán"). Al confirmar, MUST liberar las reservas de asientos de la sesión en los viajes de esa venta y volver al inicio del panel.

#### Scenario: Cancelar con asientos elegidos
- **WHEN** el vendedor con asientos reservados confirma "Cancelar"
- **THEN** sus reservas en los viajes de la venta se liberan y vuelve al inicio del panel
