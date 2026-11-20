# Design

## Context

Los selects `origin_location_id` y `destination_location_id` de `TicketForm` toman sus opciones de `originOptions()` y `destinationOptions()`, que ya buscan en todos los colectivos de la línea (`lineBusIds()`) y arman las etiquetas con `stopOptionLabel()` ("3 · Tolosa") y `routeGroupLabel()` ("Hacia Salta"). Los dos selects usan `native(false)`, `searchable` cuando hay más de 10 opciones y `optionsLimit(100)`.

Antes de `7c9fecf`, los selects eran nativos (el default de Filament), sin buscador. Las opciones de origen eran las paradas sin repetir, ordenadas por `route_id` y `stop_order`. Las de destino eran una lista plana ordenada por `destination.stop_order`. Las dos se filtraban por `routes.bus_id` del colectivo elegido.

Hay dos parámetros con permiso propio (`settings.client_panel` y `settings.appearance`) que ya resuelven el mismo problema: una constante y un método de lectura en `Setting`, una constante `*_PERMISSION` y un `can…()` en `TicketSettings`, y un guardado condicionado en `save()`.

## Goals / Non-Goals

**Goals:**
- Un único punto que decida el estilo, para que el select y sus opciones no queden con estilos distintos.
- Que el modo simple reproduzca la presentación anterior a `7c9fecf` sin perder la búsqueda por línea.

**Non-Goals:**
- Cambiar los selectores de horario, colectivo o vuelta.
- Cambiar la reprogramación o el formulario de clientes (`ClientTicketAssignForm`), que tienen sus propios selects.
- Separar el parámetro en varios (numeración, grupos y buscador por separado).

## Decisions

### Parámetro booleano en `Setting`, apagado por defecto
- Clave `sales.selectores_detallados`, constante `Setting::SELECTORES_DETALLADOS` y lectura `Setting::selectoresDetallados(): bool` con `getBool(..., false)`, como `panelClientesActivo()`.
- Arranca apagado porque lo que se busca es volver al estilo anterior sin configurar nada.

### La decisión del estilo se lee en un solo lugar del formulario
- Hay un helper privado `detailedSelectors(): bool` en `TicketForm` que lee el `Setting`, que ya tiene caché.
- Con ese valor se configuran `native()` y `searchable()`, y se elige qué arma las opciones.
- `optionsLimit(100)` se deja igual: no tiene efecto en un select nativo y es inofensivo.
- Alternativa descartada: tener dos `Select::make()` por campo y mostrar uno u otro con `visible()`. Duplica estado y `afterStateUpdated`, y Livewire tendría dos componentes con el mismo nombre.

### El filtro se reutiliza y solo cambia el armado de las etiquetas
- `originOptions($busId, bool $detailed)` y `destinationOptions($busId, $originId, bool $detailed)` hacen la misma consulta en los dos modos, con `lineBusIds()`, rutas activas, localidades activas y `stop_order` mayor que el del origen.
- Solo cambia la salida:
  - **Detallado**: la de hoy, con numeración y grupos por ruta.
  - **Simple, origen**: `[location_id => nombre]` en el mismo orden de hoy (rutas por id, paradas en orden), sin número.
  - **Simple, destino**: lista plana `[location_id => nombre]`, sin repetir, ordenada por `stop_order` y en caso de empate por `route_id`. Es el orden anterior a `7c9fecf`. El desempate deja el orden fijo cuando hay varias rutas en la línea.
- Así, "mismas localidades en los dos estilos" se cumple por construcción. Un test compara las claves de las dos salidas.
- Alternativa descartada: recuperar el código anterior a `7c9fecf` tal cual. Filtraba por `bus_id`, no por línea, y dejaría de mostrar los refuerzos.

### El permiso sigue el patrón de apariencia
- El permiso `settings.sale_selectors` con la etiqueta "Configurar selectores de origen y destino" se agrega en el módulo `settings` de `Permissions::catalog()`. No se agrega a `defaultsFor(ROLE_ADMIN)`. El Super Administrador lo recibe por `all()`.
- `TicketSettings::SALE_SELECTORS_PERMISSION` y `canManageSaleSelectors()`.
- En `saleComponents()` se agrega la sección "Selectores de origen y destino" con `->visible(fn () => $this->canManageSaleSelectors())`. El toggle tiene un helper corto de una oración, y el resto va en un tooltip, como pide "Texto visible limitado a lo esencial".
- En `save()`, el valor se guarda solo si se cumple `canManageSaleSelectors() && array_key_exists('selectores_detallados', $data)`.
- La clave `selectores_detallados` se agrega al mapa de pestañas en `'Venta'`, para que el indicador de errores la cuente, y a `mount()` / `fill()`.
- Va como sección de Venta y no en una pestaña nueva porque es un parámetro de venta y la pestaña ya existe. El requisito de pestañas solo suma una sección condicional.

## Risks / Trade-offs

- [En modo simple, una línea con ida y vuelta mezcla destinos de los dos sentidos sin decir hacia dónde va cada uno] → Ya pasaba antes de `7c9fecf` con un colectivo de ida y vuelta, y el usuario lo aceptó. El modo detallado sigue disponible para quien lo necesite.
- [En modo simple, una línea con muchas paradas no tiene buscador] → El select nativo permite ir a una opción escribiendo su inicial. Ese era el comportamiento anterior.
- [Livewire podría cachear opciones de un modo mientras se cambia el parámetro] → El parámetro se lee en cada render del formulario. Un formulario de venta ya abierto toma el cambio en su próximo render, y eso es aceptable.
- [Los tests de `LineaSaleSearchTest` llaman a `originOptions` y `destinationOptions` por reflexión con la firma actual] → Se actualizan para pasar el modo explícitamente y se agregan casos del modo simple.

## Migration Plan

1. Deploy del código. No hay migraciones.
2. `php artisan permissions:sync` crea `settings.sale_selectors`.
3. Desde el deploy, la venta muestra los selects simples.
4. Si se quiere el estilo detallado, asignar el permiso desde la matriz de roles y prender el interruptor en Parámetros > Venta.
5. Rollback: prender el interruptor da el estilo actual sin tocar código. Revertir el commit solo hace falta si falla el código nuevo.
