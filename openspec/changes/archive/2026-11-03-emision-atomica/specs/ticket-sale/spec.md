# Spec Delta

## MODIFIED Requirements

### Requirement: Cierre de una venta exitosa
Tras emitir correctamente, el sistema SHALL liberar todas las reservas temporales de asientos de la sesión del vendedor y entregar los boletos según el parámetro "Descargar PDF automáticamente al vender" (activado por defecto). Activado, abre la descarga en una pestaña nueva y vuelve al inicio del panel. Desactivado, presenta la previsualización con link compartible en lugar de descargar. La entrega MUST ocurrir después de confirmada la emisión: si falla, la venta y sus boletos siguen emitidos y el vendedor ve el error de la entrega.

#### Scenario: Venta completada con descarga automática
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está activado
- **THEN** las reservas de la sesión se eliminan y se abre la descarga de los boletos en otra pestaña

#### Scenario: Venta completada sin descarga automática
- **WHEN** la emisión termina sin errores y el parámetro de descarga automática está desactivado
- **THEN** las reservas de la sesión se eliminan y no se descarga ningún PDF: se presenta la previsualización con link compartible

#### Scenario: Falla la generación del PDF
- **WHEN** la emisión se confirmó pero falla la generación del PDF
- **THEN** la venta y sus boletos quedan emitidos y el vendedor ve que no se pudo generar el PDF
