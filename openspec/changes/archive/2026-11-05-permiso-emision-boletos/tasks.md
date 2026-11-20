# Tasks

## 1. Permiso

- [x] 1.1 Agregar `settings.ticket_emission` con la etiqueta "Configurar emisión al finalizar la venta" en el módulo `settings` de `Permissions::catalog()`, después de `sale_selectors`, sin sumarlo a `defaultsFor()` de ningún rol. Verificar con un test en `RolesAndPermissionsTest`, junto a los de `client_panel`, `appearance` y `sale_selectors`, que después de sincronizar el catálogo el permiso existe y el rol Administrador no lo tiene.

## 2. Sección en Parámetros > Boleto

- [x] 2.1 En `TicketSettings`, agregar `TICKET_EMISSION_PERMISSION` y `canManageTicketEmission()`, y poner `->visible(fn () => $this->canManageTicketEmission())` en la sección "Emisión al finalizar la venta", sin tocar el `visible()` propio de `whatsapp_mensaje`. Verificar con tests: con el permiso se ven el interruptor y "Líneas de contacto"; sin el permiso, la pestaña Boleto muestra solo "Líneas de contacto".
- [x] 2.2 En `save()`, guardar `descarga_automatica_pdf` solo si se cumple `canManageTicketEmission() && array_key_exists('descarga_automatica_pdf', $data)`, sin el `?? true`, y sumar `canManageTicketEmission()` a la condición de `whatsapp_mensaje`. Verificar con tests:
  - Con el permiso, desactivar la descarga y cargar "Tu pasaje: {link}" guarda los dos valores.
  - Sin el permiso, con la descarga desactivada y el mensaje "Tu pasaje: {link}", cambiar las horas de venta a 8 y guardar guarda las horas y conserva la descarga desactivada y el mensaje.
  - Sin el permiso, un pedido manipulado que manda la descarga activada y el mensaje "Otro: {link}" guarda los demás parámetros y no cambia ninguno de los dos.
- [x] 2.3 Actualizar los tests existentes de `TicketSettingsTest` que cambian `descarga_automatica_pdf` o `whatsapp_mensaje` para que actúen con un usuario con `settings.ticket_emission`, usando `adminWithPermissions()`, sin cambiar sus aserciones. Correr `php artisan test --compact tests/Feature/TicketSettingsTest.php` y verificar que pasa completo.

## 3. Cierre

- [x] 3.1 Correr `vendor/bin/pint --dirty --format agent` y, después, `php artisan test --compact --filter="TicketSettings|RolesAndPermissions|TicketDelivery|TicketSale"`, y verificar que todo pasa.
- [x] 3.2 Verificar en el navegador que la sección "Emisión al finalizar la venta" aparece solo con el permiso y que, sin el permiso, guardar Parámetros con la emisión por link configurada la deja como estaba.

## Workflow follow-up

- Deploy: `php artisan permissions:sync` y asignar `settings.ticket_emission` desde la matriz de roles a quien corresponda (el Administrador y el Colaborador dejan de ver la sección hasta entonces).
- Actualizar la memoria de pendientes de deploy con este cambio.
- Archivar el cambio con `/opsx:archive` una vez revisado.
