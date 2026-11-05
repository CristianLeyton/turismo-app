# Tasks

## 1. Permisos por defecto

- [x] 1.1 Agregar `standardActionsWithoutForceDelete()` en `app/Support/Permissions.php` y usarlo en `defaultsFor()`; verificar con `php artisan test --compact tests/Feature/RolesAndPermissionsTest.php`
- [x] 1.2 Quitar Clientes y todo `force_delete` de los defaults del Administrador; verificar con `test_administrador_no_tiene_clientes_ni_force_delete_por_defecto`
- [x] 1.3 Quitar Clientes, Ventas y `trips.force_delete` de los defaults del Vendedor; verificar con `test_vendedor_no_tiene_clientes_ventas_ni_force_delete_por_defecto`
- [x] 1.4 Ajustar los tests que dependían de los defaults viejos (`test_la_matriz_de_permisos_muestra_los_modulos_del_catalogo`, `ClientsTableTest` con Super Administrador) y verificar que pasan

## 2. Documentación

- [x] 2.1 Actualizar la tabla de roles de `docs/roles-y-permisos.md` y el párrafo de `clients.reset_password` ("Lo trae Administrador por defecto"); verificar que no queden menciones a clientes o borrado definitivo en los defaults
- [x] 2.2 Actualizar la tabla de roles de `docs/manual-funcional.md`; verificar que coincida con `defaultsFor()`
- [x] 2.3 Agregar la entrada en `docs/changelog.md`, aclarando que las instalaciones existentes no cambian solas; verificar leyendo la entrada

## 3. Verificación

- [x] 3.1 Correr la suite completa y confirmar que solo fallan los dos tests previos ajenos al cambio

## Workflow follow-up

- Archivar el cambio cuando la documentación esté actualizada.
