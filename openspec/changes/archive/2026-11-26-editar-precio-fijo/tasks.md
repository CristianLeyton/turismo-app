# Tasks

## 1. Permiso "Editar precio fijo"

- [x] 1.1 Agregar `'edit_fixed_price' => 'Editar precio fijo'` al catálogo de Boletos en `app/Support/Permissions.php`, al lado de `edit`, sin sumarlo a `defaultsFor()` del Administrador ni del Vendedor. Verificar con un test en `tests/Feature/RolesAndPermissionsTest.php`, siguiendo el patrón de `test_permiso_de_precio_fijo_se_registra_sin_asignarse_a_ningun_rol`, que compruebe que después de `syncToDatabase()` el permiso existe, Administrador y Vendedor no lo tienen y el Super sí.

## 2. Destrabar el precio con el permiso

- [x] 2.1 En `EditTicket::isPriceLockedByFixedPrice()`, sumar la condición `! Auth::user()?->can('tickets.edit_fixed_price')`. Verificar con tests en `tests/Feature/TicketFixedPriceEditTest.php`: con el permiso, el campo "Precio" de un boleto "fijo" y de uno sin modo se puede editar y el precio guardado es el cargado; sin el permiso, los tests actuales "Campo bloqueado" y "Pedido manipulado" siguen en verde; la vuelta sigue bloqueada aunque el usuario tenga el permiso.
- [x] 2.2 En `EditTicketForm`, para quien tiene el permiso, con el precio fijo prendido y un boleto que no es "manual", mostrar debajo del campo "Precio" (`helperText`) "Precio fijo. Si lo cambiás, el boleto pasa a precio manual.". Mantener los `hint` actuales. Verificar con un test que el texto aparece con el permiso y no aparece sin él, ni en un boleto "manual", ni con el precio fijo apagado.
- [x] 2.3 Verificar con un test que un usuario con "Editar precio fijo" y sin `tickets.edit` no puede abrir la edición de un boleto.

## 3. El precio editado pasa a "manual"

- [x] 3.1 En `EditTicket::applyChanges()`, si `$changes` trae `price`, poner `price_mode = manual` en el boleto editado antes del `save()`, sin tocar `child_insurance`. Verificar con tests: un boleto "fijo" editado con el permiso queda "manual" y conserva su seguro registrado; con el precio fijo apagado, un boleto "fijo" y uno sin modo quedan "manual"; guardar el mismo precio cambiando el teléfono lo deja "fijo"; agregar solo el menor lo deja "fijo" y suma el seguro.
- [x] 3.2 Verificar con tests el comportamiento después del cambio: ese boleto, ya "manual", lo edita un usuario sin "Editar precio fijo", y agregarle el menor no cambia el precio.

## 4. El precio a mano gana sobre el ajuste del seguro

- [x] 4.1 En `EditTicket::save()`, no aplicar el ajuste del seguro si `$changes` trae `price`. En el resumen de confirmación, calcular primero el cambio manual del precio y omitir la línea del ajuste cuando exista. Verificar con un test: con el permiso, en un boleto solo ida "fijo" de 70000 con seguro 5000, agregar el menor y cargar 72000 guarda 72000, deja una sola fila de "Precio" (70000 → 72000) en `ticket_edits` y el resumen muestra "Precio: $70.000 → $72.000" una sola vez. Verificar también que el ajuste desde la vuelta sigue funcionando: los tests `test_agregar_el_menor_en_la_vuelta_ajusta_la_ida` y `test_fijo_agregar_el_menor_en_la_vuelta_ajusta_la_ida_con_su_seguro` siguen en verde.

## 5. Ventas e integración

- [x] 5.1 Verificar con un test que, después de que un usuario con el permiso edita el precio de un boleto "fijo", el total de la venta (`sales.total`) y el total de ventas del vendedor en la fecha de la venta original reflejan el precio nuevo, sin sumar nada en la fecha de la edición.
- [x] 5.2 Correr `php artisan test --compact` con `TicketFixedPriceEditTest`, `TicketPriceModeTest`, `TicketEditTest`, `TicketEditHistorySharedTest` y `RolesAndPermissionsTest`, y `vendor/bin/pint --dirty --format agent`. Todo en verde.
- [x] 5.3 Probar en el navegador, con el precio fijo prendido y un usuario con el permiso: editar el precio de un boleto "fijo", ver el aviso, revisar el resumen de confirmación, el "Historial de ediciones" y el total del vendedor en Ventas del día de la venta.

## Workflow follow-up

- Deploy: `php artisan permissions:sync` y asignar "Editar precio fijo" a mano desde la matriz de Roles. Sin migraciones.
- Al mergear `precio-por-boleto` con `desarrollo`: conservar la condición del permiso en su versión de `isPriceLockedByFixedPrice()` y la regla "precio a mano gana sobre el ajuste del seguro" en su edición por pasaje.
- Archivar el cambio cuando el usuario lo apruebe.
