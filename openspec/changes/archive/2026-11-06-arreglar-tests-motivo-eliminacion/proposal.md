# Proposal

## Why

Dos tests de `tests/Feature/TicketDeleteReasonTest.php` fallan desde el rediseño del detalle de auditoría (0600fef). En el aviso de "Boleto eliminado", la etiqueta pasó de `Motivo:` a `Motivo` (título en mayúsculas sobre el valor), y los tests siguen buscando el texto viejo. La pantalla se comporta bien y los tests quedaron desactualizados. Con la suite en rojo es difícil distinguir un fallo real.

## What Changes

- `test_el_detalle_del_historial_muestra_el_motivo_de_la_eliminacion`: buscar la etiqueta actual `Motivo` seguida del motivo cargado, en ese orden, en vez de `Motivo:`.
- `test_un_boleto_eliminado_sin_motivo_historico_muestra_guion`: buscar `Motivo` seguido del guion `—`. Hoy el test no comprueba el guion que anuncia su nombre, solo que no aparezca otro motivo.
- No cambia código de la aplicación ni comportamiento visible.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

Ninguna. Es un arreglo de tests sin cambio de comportamiento (`skip_specs: true`).

## Impact

- Solo `tests/Feature/TicketDeleteReasonTest.php`.
- Vista de referencia, sin tocar: `resources/views/filament/resources/ticket-audit/ticket-audit-detail.blade.php`.
