# Proposal

## Why

El sistema va a instalarse para más de una empresa (una instalación y una base de datos por empresa) y cada una necesita ver su propio color de marca. Hoy el color principal está fijo en el código (`Color::Fuchsia` en los dos paneles) y, además, unas 25 vistas Blade, el tema CSS y los PDFs usan fucsia o violeta escritos a mano, así que cambiar el color de Filament solo cambiaría una parte del sitio.

## What Changes

- Nuevo parámetro "Color principal" (un color base en hex) en una pestaña nueva "Apariencia" de Configuración > Parámetros, con vista previa de la paleta generada. Sin configurar, el sistema sigue en fucsia.
- Nuevo permiso "Cambiar apariencia" del módulo Configuración: solo quien lo tiene ve la pestaña y puede cambiar el color. No se asigna por defecto al rol Administrador.
- A partir del color base se genera una paleta completa (50 a 950) que conserva el color exacto en el escalón 600 (el de botones y links en modo claro). El generador es propio, porque `Color::generatePalette()` de Filament conserva solo el tono y desvirtúa colores oscuros o apagados.
- El panel de administración, el panel de clientes y la página pública del boleto (`/boletos/{token}`) toman esa paleta en cada request, sin recompilar CSS.
- Migración única de los colores de marca fijos a la paleta: clases `fuchsia-*`/`purple-*` de las vistas Blade pasan a `primary-*`; los hex de marca de `theme.css` pasan a `var(--primary-*)`; los hex de marca de los PDFs (boleto, ventas, viajes, pagos) pasan a la paleta en hex. Los colores de estado (error, éxito, aviso) y los grises no cambian.
- El texto que va sobre el color principal (logo, botones) se elige automáticamente entre claro y oscuro según el contraste, para que un color muy claro no deje texto ilegible.
- Un test impide volver a escribir colores de marca fijos en las vistas.

## Capabilities

### New Capabilities
- `brand-color`: color principal configurable de la instalación, la paleta que se genera a partir de él y dónde se aplica (paneles, página pública del boleto, PDFs, texto sobre el color).

### Modified Capabilities
- `settings-page`: la página suma la pestaña "Apariencia", visible solo con el permiso de apariencia, y cambia el orden de las pestañas.

## Impact

- `app/Providers/Filament/AdminPanelProvider.php`, `ClientPanelProvider.php`: `->colors()` pasa a una closure que lee el parámetro.
- `app/Models/Setting.php`: constante y getter del color principal.
- `app/Support/`: clase nueva generadora de la paleta (oklch para web, hex para PDF) y del color de texto por contraste.
- `app/Support/Permissions.php`: permiso `settings.appearance`. Requiere correr la sincronización de permisos en el deploy.
- `app/Filament/Pages/TicketSettings.php`: pestaña "Apariencia", `TAB_FIELDS`, guardado con control de permiso.
- `resources/css/filament/admin/theme.css`: hex de marca reemplazados por variables.
- Unas 25 vistas en `resources/views` (tickets, filament, livewire, buses, boletos) y `TicketForm.php` (una clase `fuchsia`).
- PDFs: `resources/views/tickets/pdf/passenger-tickets.blade.php`, `resources/views/pdf/{sales-tickets,trip-details,payments}.blade.php`.
- Deploy: `npm run build` (cambian clases de las vistas) y sincronización de permisos.
- Fuera de alcance: logo e imagen propios por empresa, otros colores configurables (gris, acento), multi-tenant en una sola instalación, y `welcome.blade.php` (no tiene ruta).
