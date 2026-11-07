# Tasks

## 1. Permisos

- [x] 1.1 Agregar `settings.calendar` ("Configurar calendario de servicio") y `settings.security` ("Configurar confirmación de contraseña") al módulo `settings` en `app/Support/Permissions.php`, fuera de los defaults de Administrador y Vendedor; verificar con tests en `RolesAndPermissionsTest` que la sincronización crea los dos permisos, no los asigna a esos roles, el Administrador no los tiene y el Super Administrador sí

## 2. Página de Parámetros

- [x] 2.1 En `TicketSettings`, agregar `CALENDAR_PERMISSION` y `SECURITY_PERMISSION` con sus helpers `canManageCalendar()` / `canManageSecurity()`, ocultar las pestañas Calendario y Seguridad con `->visible()` y actualizar el PHPDoc de la clase; ajustar en `TicketSettingsTest` los tests existentes que usan esas pestañas con `$this->admin` (otorgarle los permisos) y las listas de pestañas esperadas, y agregar tests de los escenarios de orden y visibilidad del spec `settings-page` (sin permisos de pestañas, con calendario y seguridad, con uno solo, con todos)
- [x] 2.2 En `save()`, escribir los `REQUIRE_PASSWORD_*` solo con el permiso de seguridad y `SERVICE_WEEKDAYS`/`SERVICE_EXCEPTIONS` solo con el de calendario (con `array_key_exists`, como los demás bloques), y contar las excepciones vencidas solo con el permiso de calendario; verificar en `TicketSettingsTest` los escenarios de guardado sin permiso, pedido manipulado de calendario y de seguridad, la excepción vencida sin el permiso (no se borra ni se menciona en la notificación) y la pestaña oculta que no aporta errores ni se nombra en la notificación
- [x] 2.3 Actualizar la tabla de pestañas de Parámetros en `.claude/skills/filament-ui/SKILL.md` para indicar que Calendario requiere `settings.calendar` y Seguridad `settings.security`, y verificar que la tabla queda coherente con el orden del spec

## 3. Cierre

- [x] 3.1 Correr `php artisan test --compact tests/Feature/TicketSettingsTest.php tests/Feature/RolesAndPermissionsTest.php` y los tests de días de servicio y de confirmación de contraseña que pasen por Parámetros, y verificar que pasan
- [x] 3.2 Correr `vendor/bin/pint --dirty --format agent` y verificar que no quedan cambios de formato
- [x] 3.3 Correr `openspec validate permisos-calendario-seguridad --strict` y verificar que pasa sin errores ni advertencias

## Workflow follow-up

- En el deploy: correr `php artisan permissions:sync` y asignar `settings.calendar` y `settings.security` desde la matriz de roles a quien corresponda (si no, los Administradores dejan de ver Calendario y Seguridad).
- Archivar el cambio después de revisarlo.
