# Proposal

## Why

La lista de viajes muestra todos los viajes, también los que se crearon al buscar un horario y nunca vendieron un boleto. Esos viajes vacíos (hoy pintados en gris) se mezclan con los que importan y obligan a buscar entre filas que no tienen pasajeros.

## What Changes

- La lista de viajes oculta por defecto los viajes sin pasajeros, es decir, sin ningún boleto activo.
- Un switch "Ver también viajes sin pasajeros", junto a los demás filtros, los vuelve a mostrar. Sigue el estilo del switch "Ver únicamente boletos sin fecha" de la lista de boletos.
- Al buscar por "Viaje Nº", el viaje buscado aparece aunque no tenga pasajeros.
- Los viajes sin pasajeros siguen viéndose en gris cuando el switch está encendido.
- No se borra ni se modifica ningún viaje: es solo un filtro de la lista.

## Capabilities

### New Capabilities
- `trip-list`: qué viajes muestra la lista de viajes del panel y cómo se filtran.

### Modified Capabilities

## Impact

- **Lista de viajes:** `app/Filament/Resources/Trips/TripResource.php`, con el filtro nuevo en la tabla de `ManageTrips`.
- **Tests:** feature test de la tabla de viajes.
- **Sin migraciones, dependencias ni cambios en otros listados.** La lista de boletos y los reportes de viajes no cambian.
