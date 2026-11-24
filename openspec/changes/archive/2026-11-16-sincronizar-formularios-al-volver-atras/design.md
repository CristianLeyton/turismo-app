# Design

## Context

Ver proposal.md - Why. Datos medidos en Chrome al volver atrás a `/admin/login`:

- `performance.getEntriesByType('navigation')[0].type === 'back_forward'`.
- `notRestoredReasons`: `response-cache-control-no-store`. El header lo pone `Livewire\Features\SupportDisablingBackButtonCache\DisableBackButtonCacheMiddleware` (`no-cache, must-revalidate, no-store, max-age=0, private`) en toda página con un componente Livewire.
- El input muestra el texto, pero el componente `custom-login` tiene `data.username === null`.
- La contraseña no se restaura: Chrome no restaura campos `password`, y el gestor de contraseñas los completa disparando eventos. Por eso el error aparece en usuario/DNI.

Los dos paneles ya tienen un script inline en `PanelsRenderHook::BODY_END` (la ruedita sobre inputs numéricos). Ninguno de los dos paneles tiene un asset JS propio.

## Goals / Non-Goals

**Goals:**
- Que el estado de Livewire refleje los valores que restauró el navegador en las páginas de ingreso de los dos paneles.

**Non-Goals:**
- No cubrir otros formularios (edición de boletos, parámetros, etc.). Ahí sincronizar los valores restaurados interactúa con `unsavedChangesAlerts()` y con campos controlados por Alpine (selects, fechas), y nadie reportó el problema.
- No tocar el header `no-store` de Livewire. Sacarlo cambiaría la semántica de "Atrás" en todo el panel.
- No cambiar el `autocomplete` del DNI (cambio aparte).

## Decisions

1. **Sincronizar disparando `input` (opción A)**, en lugar de vaciar el formulario (B) o usar `autocomplete="off"` (C). Conserva lo que el usuario escribió. `wire:model` (diferido) escucha `input` y actualiza el estado local, que viaja en el próximo request ("Entrar"). B obliga a reescribir. C rompe el autocompletado y los navegadores no lo respetan de forma pareja.

2. **Cuándo correr**: solo si la entrada de navegación es `back_forward`, y en el evento `pageshow`, que llega después de `load`, cuando Chrome ya restauró los controles. Si `event.persisted` es `true` (restauración desde bfcache, posible si algún día cambia el header), no hace falta nada: el estado JS sobrevivió. Como resguardo, si Livewire todavía no inicializó los componentes, se espera a `livewire:initialized`.
   - Alternativa descartada: `DOMContentLoaded`. Puede correr antes de que el navegador restaure los valores.

3. **Qué campos**: inputs de texto y similares (`input` que no sean `hidden`, `password`, `checkbox`, `radio` ni `file`) con atributo `wire:model*` y valor no vacío. Se despacha `new Event('input', { bubbles: true })`. La contraseña se excluye: no se restaura y la maneja el gestor. El checkbox "Recordarme" queda fuera: Chrome lo restaura, pero si se desincroniza solo cambia la duración de la sesión.

4. **Dónde**: un render hook `PanelsRenderHook::BODY_END` en cada panel con `scopes:` limitado a su página de ingreso (`CustomLogin::class` / `ClientLogin::class`), igual que el `AUTH_LOGIN_FORM_AFTER` de clientes. El script es el mismo en los dos paneles. Para no duplicarlo, va en una vista Blade compartida (`resources/views/filament/login-back-navigation-sync.blade.php`) que ambos providers renderizan.

## Risks / Trade-offs

- [El navegador restaura los valores después de `pageshow`] → En Chrome y Firefox la restauración ocurre antes de `load`/`pageshow`. Se verifica a mano en Chrome repitiendo el recorrido de la reproducción.
- [Safari/iOS usa bfcache más agresivamente] → Si restaura desde bfcache (`persisted`), el estado JS ya es correcto y el script no hace nada. Si no, aplica la misma sincronización.
- [El comportamiento JS no se puede probar con PHPUnit] → Los tests de feature verifican que el script esté en las dos páginas de ingreso y no en otras. El recorrido real se valida a mano en el navegador.
