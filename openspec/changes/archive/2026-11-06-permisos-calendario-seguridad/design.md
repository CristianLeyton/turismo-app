# Design

## Context

`TicketSettings` ya resuelve permisos por pestaña y por sección con el mismo patrón: una constante `*_PERMISSION`, un helper `canManage…()`, `->visible()` en el `Tab` o la `Section` y, en `save()`, un `if ($this->canManage…() && array_key_exists(...))` que evita escribir lo que el usuario no ve (Clientes, Apariencia, selectores, observaciones, emisión, precio fijo). Calendario y Seguridad hoy se guardan siempre: `save()` escribe los tres `REQUIRE_PASSWORD_*`, `SERVICE_WEEKDAYS` y `SERVICE_EXCEPTIONS` sin condición, y cuenta las excepciones vencidas para la notificación antes de reescribirlas.

Los permisos del catálogo viven en `Permissions::catalog()` y el Super Administrador los tiene todos por `Gate::before`.

## Goals / Non-Goals

**Goals:**
- Aplicar a Calendario y Seguridad el mismo patrón que las demás pestañas con permiso, sin abstracciones nuevas.
- Que un pedido Livewire manipulado no pueda cambiar esos parámetros sin el permiso.

**Non-Goals:**
- No cambia quién puede vender en días sin servicio (`tickets.vender_dia_sin_servicio`) ni cómo se pide la contraseña al borrar, reprogramar o editar.
- No se agrega migración de datos que asigne los permisos a roles existentes.

## Decisions

1. **Dos permisos (`settings.calendar`, `settings.security`) en el módulo `settings`, fuera de `defaultsFor()`.** Decisión del usuario: se asignan a mano, como los demás permisos de Configuración. Alternativa descartada: un permiso único para ambas pestañas (menos granular).

2. **Ocultar la pestaña completa con `->visible()` en el `Tab`**, igual que Clientes y Apariencia. Al estar oculta, Filament no incluye sus campos en `getState()` ni los valida, así que un día de servicio vacío o una excepción inválida en el estado no bloquean el guardado de quien no ve la pestaña.

3. **Guardado condicionado por permiso, no solo por presencia en el estado.** Los `Setting::set` de Seguridad y de Calendario pasan a estar dentro de `if ($this->canManageSecurity())` / `if ($this->canManageCalendar())`. Se mantiene también `array_key_exists` como en los otros bloques. Así el pedido manipulado (spec `settings-page`) queda cubierto aunque el estado traiga las claves.

4. **El conteo de excepciones vencidas se hace solo con el permiso de calendario.** Si no se reescriben las excepciones, no se limpia nada y la notificación no debe decir que se quitaron (spec `service-days`). El conteo queda en 0 sin el permiso.

5. **`TAB_FIELDS` no cambia.** El badge y la notificación de error ya se basan en las claves con error; una pestaña oculta no genera errores, por lo que nunca aparece nombrada.

## Risks / Trade-offs

- [Los Administradores existentes pierden Calendario y Seguridad tras el deploy] → Paso explícito en el deploy: `php artisan permissions:sync` y asignar los permisos desde la matriz de roles a quien corresponda.
- [Tests existentes que usan el admin sin roles para Calendario/Seguridad fallarían] → Esos tests otorgan los permisos nuevos al usuario (o usan el Super Administrador), y se agregan tests de la ausencia de permiso.
- [El skill `filament-ui` lista las pestañas sin aclarar el permiso] → Se actualiza la tabla de pestañas del skill para indicar `settings.calendar` y `settings.security`.

## Migration Plan

1. Deploy del código.
2. `php artisan permissions:sync` (crea los dos permisos; no los asigna a Administrador ni Vendedor).
3. Asignar `settings.calendar` y `settings.security` desde la matriz de roles a los roles que deban configurarlos.

Rollback: revertir el código; los permisos sobrantes en la base no afectan nada.
