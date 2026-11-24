# Tareas

## 1. Movimiento e intercambio en el componente

- [x] 1.1 Escribir tests en `tests/Feature/BusLayoutMoveSeatTest.php` (PHPUnit + `Livewire::test(DesignBusLayout::class)`): mover a celda vacía, intercambiar dos asientos, rechazar destino en zona con aviso, mover a la misma celda sin cambios; verificar que fallan
- [x] 1.2 Modificar `DesignBusLayout::moveSeat()` para intercambiar cuando el destino tiene un asiento, buscando índices por piso/fila/columna; verificar que pasan los tests de 1.1
- [x] 1.3 Agregar tests de persistencia: mover un asiento con boleto vendido, guardar, y comprobar que el boleto conserva `seat_id` y número 12 con la nueva fila/columna; mover un inactivo y que siga inactivo; mover y "Descartar cambios" restaura la ubicación. Verificar con `php artisan test --compact tests/Feature/BusLayoutMoveSeatTest.php`

## 2. Modo "Mover" en la vista

- [x] 2.1 Agregar el botón "Mover" al selector de modo y su texto de ayuda en `resources/views/buses/design-layout.blade.php`; verificar en el navegador que el modo se activa
- [x] 2.2 Agregar `selected` al componente Alpine con `selectSeat`, `clickTarget` y limpieza en `cancelDrag`/Esc, `setMode` y el cambio de piso; verificar en el navegador que Esc y cambiar de piso deseleccionan
- [x] 2.3 Reemplazar el `wire:click` de asientos y zonas por manejadores Alpine que, según el modo, quiten (modo actual) o seleccionen/muevan (modo "Mover"); verificar en el navegador que el modo "Asientos" sigue quitando y que "Mover" no quita nada
- [x] 2.4 Resaltar el asiento seleccionado (anillo de color con variante `dark:`); verificar visualmente en tema claro y oscuro
- [x] 2.5 Ajustar el `title` del asiento vendido para no decir solo "no se puede quitar" cuando está en modo "Mover"; verificar en el navegador

## 3. Verificación final

- [x] 3.1 Correr `php artisan test --compact tests/Feature/BusLayoutMoveSeatTest.php tests/Feature/BusLayoutAreaTypesTest.php tests/Feature/BusLayoutTemplatesTest.php` y verificar que todo pasa
- [x] 3.2 Correr `vendor/bin/pint --dirty --format agent` y verificar que no queden cambios de formato
- [x] 3.3 Probar en el navegador un colectivo con boletos vendidos: mover e intercambiar, guardar, y comprobar que en la venta el asiento aparece en la nueva ubicación y sigue ocupado

## Seguimiento del flujo

- Archivar el cambio cuando esté revisado.
