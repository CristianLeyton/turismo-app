# Proposal

## Why

En `7c9fecf` cambió cómo se ven los selectores "Origen" y "Destino" del primer paso de la venta: ahora son selects de Filament con buscador, cada parada va numerada ("3 · Tolosa") y los destinos se agrupan por sentido ("Hacia Salta"). La empresa quiere volver a los selects de antes. Revertir el commit no sirve, porque en el mismo cambio entró la búsqueda por línea (refuerzos), que tiene que seguir funcionando. Por eso la presentación pasa a ser un parámetro que se prende y se apaga, y que solo puede cambiar quien tenga un permiso específico.

## What Changes

- Parámetro nuevo "Selectores de origen y destino detallados", con un interruptor en la pestaña "Venta" de Parámetros.
  - **Apagado (valor por defecto)**: estilo anterior. Select nativo del navegador, sin buscador, lista plana de localidades sin numerar; los destinos se ordenan por posición de la parada, sin agrupar por sentido.
  - **Prendido**: estilo actual. Select de Filament, buscador cuando hay más de 10 opciones, paradas numeradas y destinos agrupados por sentido "Hacia X".
- Permiso nuevo "Configurar selectores de origen y destino" en el módulo Configuración. Sin el permiso, la sección no aparece y guardar no cambia el parámetro aunque el pedido lo incluya. No se asigna por defecto a ningún rol.
- Con el interruptor prendido o apagado, se mantiene igual:
  - la búsqueda por línea: el origen y el destino incluyen las rutas de los colectivos de refuerzo de la línea;
  - la preselección del horario cuando hay uno solo;
  - las reglas de qué origen y qué destino son válidos.
- **BREAKING** (visual): con el deploy, como el parámetro arranca apagado, la venta vuelve a mostrar los selects anteriores hasta que alguien con el permiso lo prenda.

## Capabilities

### New Capabilities

<!-- Ninguna -->

### Modified Capabilities

- `ticket-sale`: requisito nuevo sobre la presentación de los selectores Origen y Destino según el parámetro.
- `settings-page`: la pestaña Venta suma la sección de los selectores, visible solo con el permiso nuevo, y hay un requisito nuevo para el interruptor y su permiso.

## Impact

- `app/Filament/Resources/Tickets/Schemas/TicketForm.php`: los selects `origin_location_id` y `destination_location_id`, y los métodos `originOptions` y `destinationOptions`, pasan a depender del parámetro.
- `app/Models/Setting.php`: constante y método de lectura del parámetro nuevo.
- `app/Filament/Pages/TicketSettings.php`: sección e interruptor en la pestaña Venta, control del permiso y guardado.
- `app/Support/Permissions.php`: permiso nuevo `settings.sale_selectors`.
- Tests: `tests/Feature/LineaSaleSearchTest.php` y `tests/Feature/TicketSettingsTest.php`.
- Deploy: `php artisan permissions:sync`, asignar el permiso a mano desde la matriz de roles. No hay migraciones ni datos que cambien.
