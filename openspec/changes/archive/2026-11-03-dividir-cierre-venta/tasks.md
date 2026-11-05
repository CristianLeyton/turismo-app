# Tasks

> Cambio solo de specs: no se toca código, tests ni configuración.

## 1. Revisión del delta

- [x] 1.1 Comparar el requisito "Cierre de una venta exitosa" actual de `openspec/specs/ticket-sale/spec.md` con los tres requisitos del delta y confirmar que cada frase de la descripción original quedó en uno de ellos sin cambiar su significado: liberación de reservas, entrega según el parámetro (incluido "activado por defecto") y entrega después de confirmada la emisión sin revertir la venta. Verificar también que los cuatro escenarios originales siguen textuales en el requisito modificado.
- [x] 1.2 Correr `openspec validate dividir-cierre-venta --strict` y verificar que da válido y que ninguna descripción de requisito del delta pasa de 500 caracteres.

## Workflow follow-up

- Archivar con `/opsx:archive dividir-cierre-venta` y sincronizar la spec `ticket-sale`.
- Después de archivar, verificar que `openspec validate ticket-sale --strict` da válido.
