# Propuesta

## Por qué

En el editor de layout del colectivo, la única forma de cambiar un asiento de lugar es eliminarlo y crearlo de nuevo. Los asientos con boletos vendidos no se pueden eliminar (y eso es correcto), así que hoy no hay manera de reacomodarlos en la grilla aunque el cambio sea solo visual.

## Qué cambia

- Se agrega un tercer modo de edición, **"Mover"**, junto a "Asientos" y "Zonas".
- En modo "Mover", un clic en un asiento lo marca; un segundo clic en el destino lo reubica:
  - **Celda vacía**: el asiento se mueve ahí.
  - **Otro asiento**: los dos asientos intercambian su ubicación.
  - **Celda de una zona**: no se mueve y se avisa.
- Tocar de nuevo el asiento marcado, apretar Esc, cambiar de modo o cambiar de piso cancela la selección.
- Se pueden mover todos los asientos, incluidos los que tienen boletos vendidos y los inactivos. Solo cambia la ubicación en la grilla (fila y columna): el asiento conserva su identidad, número, tipo, estado y boletos.
- Como el resto del editor, el movimiento queda en borrador hasta "Guardar layout" y se revierte con "Descartar cambios".
- El modo "Asientos" sigue igual (clic en celda vacía agrega, clic en asiento quita).

## Capacidades

### Capacidades nuevas
- `bus-layout-editor`: edición del layout de asientos y zonas de un colectivo desde el panel. Este cambio cubre la reubicación de asientos.

### Capacidades modificadas
<!-- Ninguna: no existe una spec previa del editor de layout. -->

## Impacto

- `app/Filament/Resources/Buses/Pages/DesignBusLayout.php`: el método `moveSeat()` ya existe pero ninguna parte de la pantalla lo llama. Se le suma el intercambio cuando el destino tiene un asiento.
- `resources/views/buses/design-layout.blade.php`: nuevo modo "Mover", estado de selección en Alpine y resaltado del asiento marcado.
- `BusLayoutService::saveLayout()` no cambia: ya actualiza el mismo registro de asiento (por id) con la nueva fila y columna, y los boletos apuntan a `seat_id`.
- Sin migraciones ni cambios en la venta de boletos ni en el selector de asientos.
- Tests nuevos de Livewire sobre `DesignBusLayout`.
