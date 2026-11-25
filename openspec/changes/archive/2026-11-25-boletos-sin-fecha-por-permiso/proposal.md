# Proposal

## Why

Hoy cualquier usuario con acceso a Boletos ve el switch "Ver únicamente boletos sin fecha", aunque no pueda vender boletos sin fecha ni asignarles fecha. Es un atajo que solo le sirve a quien gestiona esos boletos.

## What Changes

- El switch "Ver únicamente boletos sin fecha" se muestra solo a quien tiene "Vender sin fecha/horario" y "Reprogramar" a la vez, y solo si la función "vender sin fecha" está activada, es decir, si algún rol que no sea SUPER tiene asignado "Vender sin fecha/horario". Es la misma condición que ya usa el switch de venta sin fecha.
- Si el switch queda oculto, también deja de aplicarse un filtro que haya quedado guardado en la sesión.
- Los boletos sin fecha siguen viéndose para todos los usuarios con acceso a Boletos: en el listado, en la búsqueda por N° y en el detalle. Así el vendedor puede atender al pasajero que pregunta por su boleto abierto y no le vende otro por error.
- Asignarles fecha sigue exigiendo "Reprogramar", como hoy. No cambian los permisos para ver, descargar, enviar, editar ni eliminar un boleto.

## Capabilities

### New Capabilities
- `ticket-undated-visibility`: quién ve los boletos sin fecha en el panel del personal y quién ve el switch para filtrarlos.

### Modified Capabilities
_Ninguna._

## Impact

- `app/Filament/Resources/Tickets/Tables/TicketsTable.php`: el filtro `trip_id` agrega `->visible()` con la condición de los dos permisos y la función activada.
- Tests nuevos para la visibilidad del switch y para comprobar que los boletos sin fecha siguen viéndose sin esos permisos.
- No cambian la consulta del recurso, las policies ni el catálogo de permisos.
