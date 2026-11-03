# Design

## Context

La motivación está en proposal.md y los requisitos en `specs/trip-list/spec.md`.

Estado actual relevante:

- **Tabla de viajes:** `TripResource::table()` arma la tabla de `ManageTrips`. Tiene los filtros Colectivo, "Viaje Nº", "Fecha de salida", Horario y Ruta, arriba del contenido, aplicados en vivo (`deferFilters(false)`) y guardados en la sesión (`persistFiltersInSession()`). Las filas de viajes sin boletos ya se pintan en gris con `recordClasses()` (`$record->tickets()->count() === 0`).
- **Switch de referencia:** la lista de boletos usa `Filter::make('trip_id')->label('Ver únicamente boletos sin fecha')->toggle()`.
- **Comportamiento de Filament:** `InteractsWithTableQuery::apply()` saltea la consulta de un filtro cuando `$data['isActive']` es falso. Un `Filter` con `->toggle()` solo filtra encendido, y acá hace falta lo contrario: filtrar con el switch apagado.
- **Boletos borrados:** `Ticket` usa `SoftDeletes`, así que `whereHas('tickets')` ya excluye los borrados.

## Goals / Non-Goals

**Goals:**
- Ocultar por defecto los viajes sin boletos activos, con un switch con el mismo estilo que el de la lista de boletos.
- Mantener el criterio de "sin pasajeros" igual al de las filas grises, para que no haya dos definiciones.

**Non-Goals:**
- Borrar o archivar viajes vacíos.
- Cambiar los reportes, los PDF y los Excel de viajes, o la lista de boletos.

## Decisions

### 1. Filtro con un `Toggle` propio, que se aplica siempre

```php
Filter::make('include_empty_trips')
    ->schema([
        Toggle::make('include_empty')
            ->label('Ver también viajes sin pasajeros')
            ->default(false),
    ])
    ->columnSpan(2)
    ->query(fn (Builder $query, array $data): Builder => ...)
```

Con un `schema` propio, el estado no trae `isActive`, así que Filament aplica la consulta siempre (`$data['isActive'] ?? true`). La consulta decide:

- Con `include_empty` encendido, no toca la consulta.
- Con el filtro "Viaje Nº" cargado, tampoco la toca. Lo lee de la tabla con `$livewire->getTableFilterState('trip_id')`, o con lo que exponga el componente en la versión instalada.
- En cualquier otro caso, aplica `$query->whereHas('tickets')`.

**Alternativa descartada: `->toggle()` con la etiqueta "Ocultar viajes sin pasajeros" y `->default(true)`.** Filtraría encendido, pero el switch leería al revés de lo pedido ("dejar un switch que los muestre") y del switch de la lista de boletos, donde encender agrega una condición visible.

**Alternativa descartada: excluirlos en `modifyQueryUsing()` leyendo el estado del filtro.** Funciona, pero separa la condición del filtro que la controla y complica el reinicio de filtros.

### 2. "Sin pasajeros" se define con `whereHas('tickets')`

Es la misma relación que usa `recordClasses()`. Excluye los boletos borrados por `SoftDeletes`. Los menores no tienen boleto propio, así que no cambian el resultado. Los boletos sin fecha no tienen `trip_id` y no ubican a nadie en un viaje.

`recordClasses()` sigue igual. Para no hacer una consulta por fila, la tabla suma `withCount('tickets')` en `modifyQueryUsing()`, y la clase gris usa `tickets_count`.

### 3. El switch se guarda en la sesión

Como el resto de los filtros (`persistFiltersInSession()`), el switch queda como el usuario lo dejó al volver a la lista. "Restablecer filtros" lo apaga.

## Risks / Trade-offs

- **[El usuario no encuentra un viaje recién creado sin ventas]** → El switch está a la vista junto a los filtros, y la búsqueda por "Viaje Nº" lo muestra igual.
- **[`whereHas` en listas grandes]** → `tickets.trip_id` está indexado. `withCount` reemplaza el conteo por fila que hoy hace `recordClasses()`, así que la tabla hace menos consultas que antes.
- **[Clave del estado de "Viaje Nº"]** → La lectura del filtro depende de la API de la tabla en Filament 4. Se verifica con un test que busca un viaje vacío por número con el switch apagado.

## Migration Plan

Sin migraciones. Al desplegar, la lista arranca ocultando los viajes vacíos. Para volver atrás alcanza con revertir el commit.
