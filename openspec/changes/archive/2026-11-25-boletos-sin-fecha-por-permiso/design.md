# Design

## Context

- **El switch hoy:** es el filtro `trip_id` de `TicketsTable` (`whereNull('trip_id')`) y no tiene condición de visibilidad.
- **El switch de venta:** `TicketForm.php:487` ya combina `can('tickets.vender_sin_fecha')` con `Permissions::permissionGrantedToAnyRole()`. Esa función responde si algún rol que no sea SUPER tiene el permiso y guarda el resultado en caché 60 segundos.
- **SUPER:** tiene todos los permisos por un `Gate::before`, así que solo la condición de la función activada le oculta el switch.
- **Filtros ocultos:** en Filament 4, `getFilters()` devuelve solo los filtros visibles, y `applyFiltersToTableQuery()` recorre esa lista. Un filtro oculto no se aplica aunque su estado haya quedado guardado en la sesión.
- **Reprogramar:** `TicketPolicy::reschedule()` ya exige `tickets.reschedule`, así que asignarle fecha a un boleto sin fecha ya está restringido. Este cambio no lo toca; solo lo cubre con un test.

## Goals / Non-Goals

**Goals:**
- Ocultar el switch con la misma forma que el switch de venta, sin código extra para el estado guardado en la sesión.

**Non-Goals:**
- Ocultar los boletos sin fecha o restringir otras acciones sobre ellos.
- Cambiar policies o el catálogo de permisos.

## Decisions

### 1. `->visible()` en el filtro `trip_id`

```php
->visible(fn (): bool => (bool) Auth::user()?->can('tickets.vender_sin_fecha')
    && (bool) Auth::user()?->can('tickets.reschedule')
    && Permissions::permissionGrantedToAnyRole('tickets.vender_sin_fecha'))
```

- **Alternativa descartada:** ocultar los boletos sin fecha a quien no tiene ninguno de los dos permisos. El vendedor no podría encontrar el boleto abierto de un pasajero y podría venderle otro. Además, el rol Vendedor, tal como viene configurado, no tiene ninguno de los dos permisos, así que se quedaría sin ver ningún boleto sin fecha.

## Risks / Trade-offs

- **[Riesgo] El caché de 60 s de `permissionGrantedToAnyRole`** puede hacer que el switch tarde hasta un minuto en aparecer o desaparecer después de cambiar la matriz de roles.
  → **Mitigación:** es el mismo comportamiento que ya tiene el formulario de venta. Se acepta.
- **[Riesgo] Una versión futura de Filament podría aplicar los filtros ocultos.**
  → **Mitigación:** un test cubre el escenario "Filtro guardado sin el switch".
