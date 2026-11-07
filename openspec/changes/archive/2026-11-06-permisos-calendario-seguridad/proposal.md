# Proposal

## Why

Hoy cualquier usuario con `settings.manage` ve y cambia las pestañas "Calendario" (días con servicio y excepciones) y "Seguridad" (confirmación de contraseña) de Configuración > Parámetros. Son parámetros sensibles —uno corta la venta en fechas enteras y el otro afloja controles sobre borrar, reprogramar y editar boletos—, así que deben quedar reservados a quien tenga un permiso explícito, como ya pasa con Clientes, Apariencia y las secciones opcionales de Venta y Boleto.

## What Changes

- Nuevo permiso `settings.calendar` ("Configurar calendario de servicio") en el módulo Configuración: sin él no se ve la pestaña Calendario y guardar Parámetros no modifica los días de servicio ni las excepciones, aunque el pedido traiga otros valores.
- Nuevo permiso `settings.security` ("Configurar confirmación de contraseña") en el módulo Configuración: sin él no se ve la pestaña Seguridad y guardar no modifica los tres interruptores de confirmación de contraseña.
- Ninguno de los dos se asigna por defecto a Administrador ni a Vendedor; el Super Administrador los tiene por su bypass. Se asignan a mano desde la matriz de roles.
- Sin el permiso de calendario, guardar no limpia las excepciones vencidas ni lo menciona en la notificación.
- **BREAKING (operativo)**: tras el deploy y `permissions:sync`, los Administradores existentes dejan de ver Calendario y Seguridad hasta que se les asignen los permisos nuevos.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `settings-page`: el orden y la visibilidad de las pestañas pasan a depender de los permisos de calendario y seguridad; se agregan requisitos de permiso para ambas pestañas, con pedido manipulado y permisos no asignados por defecto; los escenarios que asumían Calendario y Seguridad siempre visibles se ajustan.
- `service-days`: la configuración de días de servicio requiere el permiso de calendario, y la limpieza de excepciones vencidas al guardar solo ocurre con ese permiso.

## Impact

- `app/Support/Permissions.php`: dos acciones nuevas en el módulo `settings`, fuera de los defaults.
- `app/Filament/Pages/TicketSettings.php`: constantes de permiso, visibilidad de las pestañas Calendario y Seguridad, guardado condicionado y notificación de excepciones vencidas.
- Tests: `tests/Feature/TicketSettingsTest.php` (los que usan Calendario o Seguridad con el admin pasan a otorgar los permisos, más escenarios nuevos) y `tests/Feature/RolesAndPermissionsTest.php`.
- Deploy: correr `php artisan permissions:sync` y asignar los dos permisos a los roles que correspondan.
