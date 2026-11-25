# Tasks

## 1. Boletos sin fecha visibles para todo el personal

- [x] 1.1 Crear `tests/Feature/TicketUndatedVisibilityTest.php` con `php artisan make:test --phpunit` y escribir los escenarios de "Boletos sin fecha visibles para todo el personal" y "Asignar fecha exige 'Reprogramar'": listado, búsqueda por N°, detalle y reprogramación rechazada. Verificar que pasan sin cambiar código, porque es el comportamiento actual.

## 2. Switch "Ver únicamente boletos sin fecha"

- [x] 2.1 Agregar a `TicketUndatedVisibilityTest` los escenarios del switch: con los dos permisos y la función activada, sin permisos, con un solo permiso, y SUPER con la función desactivada. Agregar también el de "Filtro guardado sin el switch". Verificar que fallan.
- [x] 2.2 Agregar `->visible()` al filtro `trip_id` de `TicketsTable.php` según design.md (Decisión 1). Verificar que pasa el archivo de test completo.
- [x] 2.3 Correr los tests existentes del listado de Boletos y de los boletos sin fecha, y verificar que siguen pasando: `TicketsListTableTest`, `TableRowClickTest`, `TicketEditTest`, `TicketFixedPriceEditTest` y `TicketLegDisplayTest`.

## 3. Cierre

- [x] 3.1 Correr `vendor/bin/pint --dirty --format agent` y verificar que no quedan cambios de formato pendientes.
- [x] 3.2 Correr `openspec validate boletos-sin-fecha-por-permiso --strict` y verificar que pasa.

## Workflow follow-up

- Preguntar si se quiere correr la suite completa.
- Archivar el change una vez revisado.
