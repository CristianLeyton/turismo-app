# Tasks

## 1. Actualizar los tests del motivo de eliminación

- [x] 1.1 En `test_el_detalle_del_historial_muestra_el_motivo_de_la_eliminacion`, reemplazar `assertSee('Motivo:')` y `assertSee('Doble carga de la venta 42')` por `assertSeeInOrder(['Motivo', 'Doble carga de la venta 42'])`; verificar con `php artisan test --compact --filter=test_el_detalle_del_historial_muestra_el_motivo_de_la_eliminacion`
- [x] 1.2 En `test_un_boleto_eliminado_sin_motivo_historico_muestra_guion`, reemplazar `assertSee('Motivo:')` por `assertSeeInOrder(['Motivo', '—'])` y mantener `assertDontSee('Venta duplicada')`; verificar con `php artisan test --compact --filter=test_un_boleto_eliminado_sin_motivo_historico_muestra_guion`
- [x] 1.3 Correr `vendor/bin/pint --dirty --format agent` y `php artisan test --compact tests/Feature/TicketDeleteReasonTest.php`; verificar que los 7 tests del archivo pasan

## Workflow follow-up

- Archivar el cambio cuando el usuario lo revise.
