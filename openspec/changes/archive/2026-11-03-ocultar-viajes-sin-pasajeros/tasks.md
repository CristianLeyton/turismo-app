# Tasks

## 1. Filtro de la lista de viajes

- [x] 1.1 En `TripResource::table()`, agregar el filtro `include_empty_trips` con el `Toggle` "Ver también viajes sin pasajeros", apagado por defecto. Aplicar `whereHas('tickets')` salvo que el switch esté encendido o haya un "Viaje Nº" cargado. Se verifica con tests nuevos en `TripListEmptyTripsTest` (`Livewire::test(ManageTrips::class)`):
  - por defecto se ve el viaje con boleto y no el vacío;
  - un viaje con su único boleto borrado no aparece;
  - con el switch encendido se ven los dos;
  - con el switch encendido y una fecha se ven los dos viajes de esa fecha;
  - con el switch apagado, buscar por "Viaje Nº" un viaje vacío lo muestra.
- [x] 1.2 Sumar `withCount('tickets')` a la consulta de la tabla y usar `tickets_count` en `recordClasses()` para pintar en gris los viajes sin pasajeros. Se verifica con un test que, con el switch encendido, la fila del viaje vacío lleva la clase gris y la del viaje con boleto no.

## 2. Cierre

- [x] 2.1 Ejecutar `vendor/bin/pint --dirty --format agent` y los tests de `TripListEmptyTripsTest` y `ServiceDaysTripFormTest`. Todos tienen que pasar.
- [x] 2.2 Prueba manual: abrir Viajes, confirmar que los vacíos no aparecen, encender el switch, ver los vacíos en gris, salir y volver a la lista con el switch como se dejó.

## Workflow follow-up

- Ofrecer ejecutar la suite completa antes de archivar.
- Archivar el cambio con `/opsx:archive` una vez revisado.
