# Proposal

## Why

Hoy cualquiera con `settings.manage` puede cambiar cómo se entregan los boletos al finalizar la venta: descarga automática del PDF o entrega por link con el modal y el mensaje de WhatsApp. Es una decisión de negocio que afecta a todas las ventas, así que la empresa quiere que solo la cambie quien tenga un permiso específico, como ya pasa con el panel de clientes, la apariencia y los selectores de origen y destino.

## What Changes

- Permiso nuevo "Configurar emisión al finalizar la venta" (`settings.ticket_emission`) en el módulo Configuración. No se asigna por defecto a ningún rol: el Super Administrador lo tiene por el bypass y a los demás roles se les da a mano desde la matriz.
- La sección "Emisión al finalizar la venta" de la pestaña "Boleto", con el interruptor "Descargar PDF automáticamente al vender" y el mensaje de WhatsApp, se muestra solo con ese permiso. Sin el permiso, la pestaña "Boleto" sigue mostrando "Líneas de contacto".
- Al guardar Parámetros sin el permiso, el interruptor y el mensaje quedan como estaban, aunque el pedido traiga otros valores. Esto corrige también el guardado actual: si el interruptor no llega en el pedido, hoy se vuelve a activar la descarga automática.
- **BREAKING** (permisos): con el deploy, los roles Administrador y Colaborador dejan de ver la sección hasta que se les asigne el permiso desde la matriz de roles. El valor guardado del parámetro no cambia.

## Capabilities

### New Capabilities

<!-- Ninguna -->

### Modified Capabilities

- `settings-page`: la pestaña Boleto muestra la sección de emisión solo con el permiso nuevo, y se suma un requisito para el permiso y el guardado protegido del interruptor y del mensaje de WhatsApp.

## Impact

- `app/Support/Permissions.php`: permiso nuevo `settings.ticket_emission` en el catálogo, sin sumarlo a los defaults de ningún rol.
- `app/Filament/Pages/TicketSettings.php`: constante del permiso, `canManageTicketEmission()`, visibilidad de la sección y guardado condicionado de `descarga_automatica_pdf` y `whatsapp_mensaje`.
- Tests: `tests/Feature/TicketSettingsTest.php`.
- Deploy: `php artisan permissions:sync` y asignar el permiso desde la matriz a quien corresponda. No hay migraciones y el valor guardado del parámetro no cambia.
- Sin cambios en la venta, la reprogramación, el detalle del boleto ni el panel de clientes: siguen leyendo el mismo parámetro.
