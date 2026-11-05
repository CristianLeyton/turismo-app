# Design

## Context

Ver proposal.md - Why. Hay tres parámetros con permiso propio en `TicketSettings` (`client_panel`, `appearance` y `sale_selectors`) que resuelven lo mismo: una constante `*_PERMISSION`, un `canManage…()`, `->visible()` en la pestaña o sección, y un guardado en `save()` condicionado a `canManage…() && array_key_exists(...)`.

La sección "Emisión al finalizar la venta" (`ticketComponents()`) tiene dos campos:
- `descarga_automatica_pdf`, con `live()`.
- `whatsapp_mensaje`, visible solo con la descarga automática desactivada. Ya se guarda con `array_key_exists`, así que conserva su valor cuando está oculto.

`save()` guarda hoy `descarga_automatica_pdf` sin condición y con `?? true`. Si la sección se oculta, la clave no llega en `getState()` y el parámetro se vuelve a activar. Esto deshace en silencio la emisión por link.

## Goals / Non-Goals

**Goals:**
- Que la sección y su guardado dependan del mismo control, para que ocultar la sección nunca cambie el valor guardado.

**Non-Goals:**
- Cambiar cómo la venta, la reprogramación, el detalle o el panel de clientes leen `Setting::descargaAutomaticaPdf()`.
- Mostrar el modo vigente en modo solo lectura a quien no tiene el permiso.
- Proteger "Líneas de contacto", que sigue dependiendo de `settings.manage`.

## Decisions

### El permiso sigue el patrón de `sale_selectors`
- El permiso `settings.ticket_emission` con la etiqueta "Configurar emisión al finalizar la venta" se agrega en el módulo `settings` de `Permissions::catalog()`, después de `sale_selectors`. No se agrega a `defaultsFor()` de ningún rol. El Super Administrador lo recibe por `all()` y por el bypass de `Gate::before`.
- `TicketSettings::TICKET_EMISSION_PERMISSION` y `canManageTicketEmission()`.
- `->visible(fn () => $this->canManageTicketEmission())` va en la `Section` y no en cada campo. Así el `visible()` propio de `whatsapp_mensaje`, que depende del toggle, queda igual y se evalúa solo dentro de la sección visible.
- Alternativa descartada: incluirlo en los defaults del Administrador. Lo decidió el usuario: el permiso se habilita a mano.

### El guardado de los dos campos depende del permiso
- `descarga_automatica_pdf` se guarda solo si se cumple `canManageTicketEmission() && array_key_exists('descarga_automatica_pdf', $data)`. Se elimina el `?? true`.
- `whatsapp_mensaje` suma `canManageTicketEmission()` al `array_key_exists` actual. Sin el permiso, el campo no se renderiza y Livewire lo descarta. El chequeo de permiso cubre el caso de un pedido manipulado y deja la regla explícita, igual que en los otros parámetros con permiso.
- Alternativa descartada: conservar solo el `array_key_exists`. Funciona mientras la sección esté oculta, pero la seguridad quedaría atada a un detalle de Filament (descartar estado de campos ocultos) y no a la autorización.

### Mapa de pestañas sin cambios
- `'Boleto' => ['descarga_automatica_pdf', 'whatsapp_mensaje', 'contact_lines']` se queda igual. Si la sección está oculta, esos campos no tienen errores y el conteo sigue siendo correcto. La pestaña Boleto siempre está visible por "Líneas de contacto".

## Risks / Trade-offs

- [Con el deploy, el Administrador y el Colaborador dejan de ver la sección] → Es lo que se decidió. Se avisa en el plan de deploy que hay que asignar el permiso desde la matriz a quien corresponda.
- [Un usuario sin el permiso no ve que la empresa entrega por link] → Queda fuera de alcance. El modo se nota en la venta (modal de entrega o descarga) y en el detalle del boleto.
- [Tests actuales de `TicketSettingsTest` que cambian `descarga_automatica_pdf` con un admin sin el permiso nuevo] → Se actualizan para actuar con un usuario que tenga `settings.ticket_emission`, con el helper `adminWithPermissions()` que ya existe.

## Migration Plan

1. Deploy del código. No hay migraciones y el valor guardado del parámetro no cambia.
2. `php artisan permissions:sync` crea `settings.ticket_emission`.
3. Asignar el permiso desde la matriz de roles a quien tenga que configurar la emisión.
4. Rollback: revertir el commit. El parámetro sigue con el valor guardado.
