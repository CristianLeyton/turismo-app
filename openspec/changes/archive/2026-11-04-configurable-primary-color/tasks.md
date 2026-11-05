# Tasks

## 1. Parámetro y generador de paleta

- [x] 1.1 Agregar `Setting::PRIMARY_COLOR` (`appearance.primary_color`) y `Setting::primaryColor()`, que devuelve `#c026d3` sin clave guardada o con un valor que no sea `#RRGGBB`; verificar con un test que cubre sin clave, con `#10288c` y con un valor corrupto
- [x] 1.2 Crear `App\Support\BrandPalette` con `php artisan make:class` con `fromHex()` (oklch), `fromHexAsHex()`, `textColorOn()`, `current()` y `currentAsHex()` memorizados (y limpiados con `Setting::flushCache()`), usando las conversiones y el contraste de `Filament\Support\Colors\Color`; verificar con un test unitario que con `#10288c`, `#0a0a2a`, `#fde047`, un gris y `#c026d3` el 600 es el color exacto, los once escalones están estrictamente ordenados por luminosidad, el gris da croma 0, el 50 de `#0a0a2a` es casi blanco y `textColorOn()` da blanco para `#10288c` y oscuro para `#fde047`

## 2. Permiso y pestaña "Apariencia"

- [x] 2.1 Agregar `'appearance' => 'Cambiar apariencia'` al módulo `settings` de `Permissions::catalog()`, sin sumarlo a `defaultsFor(ROLE_ADMIN)`; verificar en `RolesAndPermissionsTest` que `syncToDatabase()` crea `settings.appearance`, que el Administrador no lo recibe y que el Super Administrador sí
- [x] 2.2 Agregar a `TicketSettings` la constante `APPEARANCE_PERMISSION`, la pestaña "Apariencia" después de Clientes (visible solo con el permiso), su entrada en `TAB_FIELDS`, el `ColorPicker` `primary_color` obligatorio con regex `#RRGGBB` y mensaje en voseo, y la carga del valor en el `fill`; verificar en `TicketSettingsTest` el orden de las pestañas con cada combinación de permisos, el valor inicial `#c026d3` y el error en español con "azul" y `#12345` sin guardar ningún parámetro
- [x] 2.3 Agregar la vista previa `live` de la paleta debajo del campo, dibujada con `BrandPalette::fromHex()` desde el estado del formulario; verificar en `TicketSettingsTest` que al hacer `set('data.primary_color', '#10288c')` la vista previa muestra el 600 `#10288c` mientras `Setting::primaryColor()` sigue en el valor guardado
- [x] 2.4 En `save()`, escribir el color solo si el usuario tiene `settings.appearance`; verificar en `TicketSettingsTest` que con el permiso se guarda `#10288c` y que sin el permiso, enviando `#10288c` con `set()`, los demás parámetros se guardan y el color sigue en `#c026d3`

## 3. Paneles y página pública del boleto

- [x] 3.1 Confirmar con `search-docs` el render hook de Filament 4 para estilos en el `<head>` (`PanelsRenderHook::STYLES_AFTER` o equivalente) y anotarlo en design.md si difiere
- [x] 3.2 Cambiar `->colors()` de `AdminPanelProvider` y `ClientPanelProvider` a `fn (): array => ['primary' => BrandPalette::current()]` y agregar el render hook que imprime `--primary-text`; verificar con un test (`BrandColorTest`) que con `#10288c` guardado `/admin/login` y `/clientes/login` (con el panel de clientes activo) contienen la variable `--primary-600` con el oklch de `#10288c` y `--primary-text`, y que sin configurar contienen el fucsia
- [x] 3.3 En `boletos/share.blade.php`, reemplazar `Color::Fuchsia` por `BrandPalette::current()` en el `<style>` de variables y sumar `--primary-text`; verificar en `BrandColorTest` que `/boletos/{token}` imprime la paleta del color guardado

## 4. Migración de colores de marca en CSS y vistas

- [x] 4.1 Crear `BrandColorUsageTest`, que recorre `resources/views` (sin `vendor/` ni `welcome.blade.php`), `resources/css/filament` y `app/Filament` y falla ante `\bfuchsia-\d{2,3}\b` (no `purple`/`pink`, que ahí son colores de estado o categoría) o los hex de la paleta fucsia (`#c026d3`, `#d946ef`, `#d87fee`, `#f5ecf7`, `#a21caf`, `#86198f`, `#f5d0fe`, `#fdf4ff`, `#faf5ff`, `#f3e8ff`), listando archivo y línea; verificar que hoy falla y lista los archivos conocidos
- [x] 4.2 En `resources/css/filament/admin/theme.css`, reemplazar los hex de marca (botón "Limpiar filtros", toggle, logo claro y oscuro) por `var(--primary-N)`, y el color del texto del logo por `var(--primary-text)`; verificar que `BrandColorUsageTest` ya no lista `theme.css`
- [x] 4.3 Migrar a `primary-N` las vistas de `resources/views/tickets` (incluido `partials/`), mapeando escalón por escalón y usando `text-[var(--primary-text)]` donde haya texto blanco sobre el 600; verificar que `BrandColorUsageTest` ya no las lista y que los tests de venta y detalle de boleto (`SeatReservationWizardTest`, `TicketLegDisplayTest`, `TicketEditTest`) pasan
- [x] 4.4 Migrar `resources/views/filament/**`, `resources/views/livewire/**`, `resources/views/buses/**`, `resources/views/boletos/share.blade.php` y la clase de `TicketForm.php`; verificar que `BrandColorUsageTest` ya no los lista y que `TicketShareLinkModeTest`, `ClientPanelTicketsTest` y `TripListEmptyTripsTest` pasan
- [x] 4.5 Correr `npm run build` y revisar a ojo, con el color por defecto y con `#10288c`, en claro y oscuro: venta, detalle de boleto, listado de viajes, login de admin y de clientes, y la página pública del boleto; verificar que no queda ningún acento fucsia y que los estados (error, pagado) conservan su color

## 5. PDFs

- [x] 5.1 Calcular `$brand = $brand ?? BrandPalette::currentAsHex()` al comienzo de cada vista de PDF (cubre todos los servicios que las renderizan) y reemplazar en `tickets/pdf/passenger-tickets.blade.php` y `pdf/{sales-tickets,trip-details,payments}.blade.php` cada hex de marca por su escalón (`#fdf4ff`/`#faf5ff`→50, `#f3e8ff`→100, `#f5d0fe`→200, `#d946ef`→500, `#c026d3`→600, `#a21caf`→700, `#86198f`→800); verificar que `BrandColorUsageTest` pasa en verde y, con un test por PDF, que con `#0e7490` guardado el HTML renderizado contiene algún escalón de esa paleta y ningún hex fucsia anterior
- [x] 5.2 Verificar que `TicketPdfConsistencyTest`, `AdminTicketPdfTest`, `PendingTicketPdfTest`, `TicketContactLinesPdfTest` y `TripExportTest` siguen pasando

## 6. Cierre

- [x] 6.1 Correr `vendor/bin/pint --dirty --format agent` y los tests de los archivos tocados (`BrandPalette`, `BrandColorTest`, `BrandColorUsageTest`, `TicketSettingsTest`, `RolesAndPermissionsTest` y los de las tareas 4 y 5); verificar que todos pasan
- [x] 6.2 Correr `openspec validate configurable-primary-color --strict`; verificar que no hay errores

## Workflow follow-up

- Deploy: `npm run build` y `php artisan permissions:sync`; después asignar "Cambiar apariencia" al rol que corresponda.
- Archivar el cambio cuando esté implementado y revisado.
