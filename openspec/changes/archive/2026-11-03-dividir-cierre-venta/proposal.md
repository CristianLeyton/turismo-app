# Proposal

## Why

El requisito "Cierre de una venta exitosa" de `ticket-sale` tiene una descripción de más de 500 caracteres y junta tres comportamientos distintos. Por eso `openspec validate ticket-sale --strict` falla, y además cuesta leerlo y mantenerlo.

## What Changes

- "Cierre de una venta exitosa" queda solo con la liberación de las reservas temporales de la sesión en los viajes de la venta. Conserva su nombre y sus cuatro escenarios.
- Se agrega el requisito "Entrega de los boletos al cerrar la venta": la entrega depende del parámetro "Descargar PDF automáticamente al vender" (descarga o previsualización con link compartible).
- Se agrega el requisito "Falla de la entrega sin revertir la venta": la entrega ocurre después de confirmada la emisión, y si falla la venta sigue emitida y el vendedor ve el error.
- El comportamiento del sistema no cambia: es la misma regla, dividida en requisitos más cortos.

## Capabilities

### New Capabilities

_Ninguna._

### Modified Capabilities

- `ticket-sale`: el requisito "Cierre de una venta exitosa" se divide en tres requisitos, uno por comportamiento, sin cambiar su significado.

## Impact

- Solo `openspec/specs/ticket-sale/spec.md`, al archivar.
- Sin cambios de código, tests, migraciones ni deploy.
