# Spec Delta

## MODIFIED Requirements

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

## ADDED Requirements

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

### Requirement: Falla de la entrega sin revertir la venta
La entrega de los boletos MUST ocurrir después de confirmada la emisión. Si la entrega falla, la venta y sus boletos siguen emitidos y el vendedor ve el error de la entrega.

#### Scenario: Falla la generación del PDF al entregar
- **WHEN** la emisión se confirmó pero falla la generación del PDF de los boletos
- **THEN** la venta y sus boletos quedan emitidos y el vendedor ve que no se pudo generar el PDF
