# Proposal

## Why

Con los selectores detallados, el select Destino nunca mostraba el buscador aunque hubiera muchas paradas. Las opciones vienen agrupadas por sentido y se contaban los grupos (1 o 2) en vez de las paradas, así que nunca superaban 10. En recorridos largos el vendedor tenía que scrollear la lista para encontrar el destino.

## What Changes

- El select Destino muestra el buscador cuando tiene más de 10 paradas para elegir, sumando las de todos los grupos por sentido.
- Origen usa la misma regla de conteo (su lista no tiene grupos, así que su comportamiento no cambia).
- Sin cambios en el estilo simple: sigue siendo un select nativo sin buscador.

## Capabilities

### New Capabilities
<!-- Ninguna. -->

### Modified Capabilities
- `ticket-sale`: el requisito "Selectores de origen y destino detallados" aclara que el umbral de 10 opciones cuenta las paradas dentro de los grupos y suma escenarios para Destino.

## Impact

- `app/Filament/Resources/Tickets/Schemas/TicketForm.php`: nuevo helper `hasEnoughOptionsToSearch()`, usado en `searchable()` de Origen y Destino.
- `tests/Feature/LineaSaleSearchTest.php`: tres tests nuevos.
