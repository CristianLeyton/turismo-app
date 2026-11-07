# Tasks

## 1. Datos de la lista de pasajeros

- [x] 1.1 Crear `tests/Feature/TripPassengerListTest.php` (`php artisan make:test --phpunit TripPassengerListTest --no-interaction`) con una ruta de 3 paradas (Orán → Pichanal → Salta), boletos en asientos 2 y 10, un titular con menor y mascota, y casos que fallen hoy: orden por parada, asiento numérico, acompañantes debajo del titular, parada de origen eliminada (aparece al final, una vez), origen que nunca fue parada (antes desaparecía) y parada eliminada y restaurada (sin duplicar; la UNIQUE `(route_id, location_id)` incluye eliminadas, así que "volver a agregar" es restaurar). Verificar que fallan con `php artisan test --compact tests/Feature/TripPassengerListTest.php`.
- [x] 1.2 En `Trip::getPassengersWithDetails()`: sin `join` a `route_stops` (el orden sale del mapa ubicación → posición de `passengerListStops()`, que ya excluye paradas eliminadas), eager loading `passenger.children`, y orden en PHP (fuera del recorrido al final, `stop_order`, asiento como entero, `tickets.id`). Verificar que pasan los casos de 1.1.
- [x] 1.3 Agregar a cada fila `origin_location_id`, `destination_location_id`, `origin_order`, `destination_order` (posición 1..n del recorrido vigente), `phone_e164` (vía `WhatsAppShare::normalizePhone()`), `search` (minúsculas sin tildes) y, en la fila `adult`, `companions`; conservar `parent_name`. Agregar `Trip::passengerListStops()` con `order` y `name`. Cubrir con tests de datos (incluido 'gomez' encuentra 'Gómez' en `search` y `543815550001` en `phone_e164`).
- [x] 1.4 Correr `TripPassengerListObservationTest`, `TripExportTest` y verificar que el Excel sigue igual (columna "Viaja con" con "(Menor)"/"(Mascota)").

## 2. Acción "Ver detalles"

- [x] 2.1 Unificar las dos `ViewAction::make('view_details')` de `TripResource` en un método estático `viewDetailsAction()` usado en la variante de escritorio y en el menú de celular, pasando `stops` a la vista. Verificar con un test Livewire que monta `ListTrips`, monta la acción `view_details` sobre un viaje y ve la lista de pasajeros.

## 3. Modal rediseñado

- [x] 3.2 Agrupar las filas por parada de origen con encabezado numerado ("1. Orán" + cantidad que sube) y grupo final sin número para orígenes fuera del recorrido. Test con `assertSeeInOrder` de encabezados y que no aparece el de una parada sin subidas.
- [x] 3.3 Agregar componente Alpine con filtros Origen/Destino ("Todas" + paradas numeradas en orden del recorrido), buscador (nombre, DNI, asiento, N° boleto, sin tildes) filtrando por boleto completo, contador "N de M" y estado vacío "Ningún pasajero coincide con los filtros." con botón "Limpiar filtros". Tests: opciones en orden del recorrido en el HTML y atributos `data-*` de origen/destino/búsqueda por fila; verificación manual en navegador de filtrar y buscar.
- [x] 3.4 Mover "Viaja con: …" a la fila del titular (menores "(menor)", mascotas "(mascota)" con cantidad) y mostrar las filas de acompañantes como "Menor"/"Mascota" sin el nombre del titular. Test: la fila de Ana contiene "Viaja con: Tomás Gómez (menor)" y el HTML no contiene "Viaja con: Ana Gómez".
- [x] 3.5 Teléfono: cuando `$showPhone`, `<a href="tel:…">` y botón icono WhatsApp `https://wa.me/{phone_e164}` (`target="_blank" rel="noopener"`, `title="Escribir por WhatsApp"`); sin teléfono, "—" sin links. Tests: con observaciones apagadas aparecen `tel:3815550001` y `wa.me/543815550001`; sin teléfono no hay links; con observaciones prendidas no aparece `tel:`.
- [x] 3.6 Ajustar diseño a filament-ui: badges N° `gray`, asiento `primary` con `dark:text-primary-400`, origen/destino `amber` (warning), vacíos "—" (la observación vacía conserva "-" por spec), soporte dark, tarjetas debajo de `md` y tabla desde `md` sin `max-w-[83dvw]`. Verificar `BrandColorUsageTest`, `TripPassengerListObservationTest` (actualizando lo que dependa del HTML viejo sin perder lo que fija su spec) y revisión visual en celular y escritorio, claro y oscuro.

- [x] 3.7 Quitar el resumen de subidas/bajadas por parada (vista, `TripResource` y `Trip::passengerStopSummary()` con sus tests): queda para repensarlo en otro cambio. Verificar que el modal ya no muestra "Subidas y bajadas por parada".
- [x] 3.8 Switch "Agrupar por parada" en el modal, encendido por defecto y recordado en el navegador con `$persist` de Alpine (clave `trip-passengers-group-by-stop`); apagado oculta los encabezados de grupo sin cambiar el orden. Tests: el HTML tiene el switch, el estado persistido y los encabezados con `x-show="groupByStop"`; revisión en el navegador de que se recuerda al recargar y en otro viaje.
- [x] 3.9 Tarjeta de celular: DNI al lado del nombre, N° y asiento juntos, teléfono en su línea y recorrido completo (origen → destino) siempre al final; botón de WhatsApp con el ícono de WhatsApp (el SVG que usaba la página pública del boleto). Tests de las tres cosas y revisión en el navegador a 390 px.

## 4. PDF

- [x] 4.1 En `TripPdfService` pasar `stops` (sin consultas en el Blade) y en `pdf/trip-details.blade.php`: "Viaja con: …" en el titular, acompañantes como "Menor"/"Mascota", fila separadora por parada ("1. Orán — suben N"), vacíos "—". Tests: el HTML del PDF contiene "Viaja con: Tomás Gómez (menor)" en el titular, no contiene "Viaja con: Ana Gómez", tiene los separadores en orden, y el PDF con 280 caracteres de observación sigue generándose.

## 5. Cierre

- [x] 5.1 Correr `vendor/bin/pint --dirty --format agent`, `npm run build` y `php artisan test --compact --filter="TripPassengerList|TripExport|BrandColorUsage"`; todo en verde.
- [x] 5.2 Revisión en navegador del modal "Ver detalles" en un viaje real con varias paradas (celular y escritorio): filtros, buscador, llamar y WhatsApp.

## Workflow follow-up

- Preguntar si correr la suite completa (`php artisan test --compact`).
- Actualizar la memoria de pendientes con los pasos de deploy (`npm run build`, `php artisan view:clear`).
- Archivar el cambio con `/opsx:archive` cuando esté revisado.
