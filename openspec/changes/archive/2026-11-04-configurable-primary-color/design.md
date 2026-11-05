# Design

## Context

Ver proposal.md (Why). Lo que condiciona el enfoque, relevado en el código:

- Los dos paneles fijan `->colors(['primary' => Color::Fuchsia])`. `Panel::colors()` acepta `array | Closure`, así que la paleta puede resolverse en cada request.
- Filament imprime la paleta como variables `--primary-50` … `--primary-950`, y su tema define `--color-primary-N: var(--primary-N)` (`vendor/filament/support/resources/css/index.css`). Por eso, en las vistas que compila el tema del panel, `text-primary-600`, `bg-primary-50`, `dark:text-primary-400`, etc. ya son clases Tailwind válidas y dinámicas.
- `Color::generatePalette()` (y `Color::hex()`, que lo llama) conserva solo el tono: luminosidad y saturación de cada escalón son constantes. Para `#10288c` el 600 queda `oklch(0.60 0.17 h)`, bastante más claro y vivo que el color real.
- `ButtonComponent` de Filament ya elige el color de texto de sus botones según el contraste. El logo (`.logo-personalizado`), los elementos propios de las vistas con `bg-fuchsia-* text-white` y los PDFs no tienen esa lógica.
- `boletos/share.blade.php` no está dentro de un panel: ya imprime a mano las variables de `FilamentColor::getColors()` con `primary` fijo en `Color::Fuchsia`.
- Los PDFs se generan con dompdf, que no entiende `var()` ni `oklch()`: necesitan hex literales.
- Los parámetros viven en `Setting` (clave/valor con caché por request) y se editan en `TicketSettings`. El patrón de "pestaña con permiso propio + guardado que ignora el valor sin permiso" ya existe para el panel de clientes (`CLIENT_PANEL_PERMISSION`).

## Goals / Non-Goals

**Goals:**
- Una única fuente del color principal (`Setting`) y un único generador de paleta usado por paneles, página pública y PDFs.
- Cambiar el color sin recompilar assets.
- Migrar una sola vez todos los colores de marca fijos y evitar que vuelvan.

**Non-Goals:**
- Paleta manual escalón por escalón (puede sumarse después como modo avanzado sin cambiar la fuente).
- Configurar el gris u otros colores de Filament (`danger`, `success`…).
- Cachear la paleta entre requests: generarla son unas pocas operaciones aritméticas.

## Decisions

### 1. Generador propio, con el color exacto en el escalón 600

Clase en `app/Support` (por ejemplo `BrandPalette`, creada con `make:class`) con:
- `fromHex(string $hex): array<int, string>` → paleta oklch (para Filament y CSS).
- `fromHexAsHex(string $hex): array<int, string>` → la misma paleta en hex (para PDFs).
- `textColorOn(string $hex): string` → `#ffffff` u oscuro según contraste.

Algoritmo: convertir el color a oklch (`L`, `C`, `h`). El 600 es el color tal cual. Los escalones 50–500 interpolan `L` desde el valor del 600 hasta una luminosidad casi blanca (≈0.97 en el 50) y reducen `C` proporcionalmente. Los escalones 700–950 interpolan `L` hacia ≈0.20 en el 950 y reducen `C`. El tono `h` se mantiene. Si `C < 0.03` (gris), `C = 0` en toda la paleta, igual que Filament. Si el color es tan oscuro que el 600 queda por debajo de los objetivos oscuros (o tan claro que queda por encima de los claros), los objetivos se ajustan para que los once escalones sigan estrictamente ordenados. Los valores se recortan a la gama sRGB al convertir a hex. Con negro o blanco puros no hay lugar para escalones más oscuros (o más claros) que el 600 y esos escalones quedan iguales; no aplica a un color de marca real.

Reutilizamos `Color::convertToOklch()`, `Color::convertToRgb()` y `Color::isTextContrastRatioAccessible()` de Filament para las conversiones y el contraste, en lugar de escribirlas.

*Alternativas:* `Color::generatePalette()` (descartado: no respeta el color de marca); poner el color en el 500 como en el ejemplo del usuario (descartado: Filament usa el 600 para botones y links en modo claro, que es lo más visible); paleta manual de 11 campos (descartado por ahora: pesada y fácil de romper).

### 2. Fuente única en `Setting`, con fucsia por defecto

`Setting::PRIMARY_COLOR` (`appearance.primary_color`) y `Setting::primaryColor(): string`, que devuelve el valor guardado si es un hex válido y si no `#c026d3`. Un valor corrupto en la base nunca rompe el sitio.

Para no generar la paleta varias veces por request, un helper `BrandPalette::current()` la calcula a partir de `Setting::primaryColor()` y la memoriza en una propiedad estática **por hex**: como la clave es el color, cambiar el parámetro nunca devuelve una paleta vieja y no hace falta limpiarla junto con `Setting::flushCache()`.

### 3. Aplicación por capa

| Capa | Cómo |
|---|---|
| Paneles admin y clientes | `->colors(fn (): array => ['primary' => BrandPalette::current()])` |
| Página pública del boleto | reemplazar `Color::Fuchsia` por `BrandPalette::current()` en el `<style>` que ya existe |
| Páginas de descarga del boleto (`tickets/download*`, con `app.css`) | `app.css` define `--color-primary-N: var(--primary-N)` y la página imprime `BrandPalette::cssVariables()` |
| Vistas Blade | `fuchsia-N` → `primary-N`. El asiento seleccionado (antes `purple-500`) pasa a `primary-800` con hover `primary-900`: es de la marca pero más oscuro que el principal, para no confundirse con botones y acentos. `purple`/`pink` del diseñador de colectivos quedan: distinguen categorías de áreas |
| "Limpiar filtros" en oscuro | borde y texto `primary-400` y hover translúcido (`color-mix` del 400): los tonos 50/300 quedan chillones sobre fondo oscuro |
| `theme.css` | hex de marca → `var(--primary-N)`; el texto del logo pasa a `var(--primary-text)` |
| Texto sobre el color | los paneles y la página pública imprimen `--primary-text` (de `textColorOn()`) por render hook / en el `<style>`; vistas propias con `bg-primary-* text-white` pasan a `text-[var(--primary-text)]` donde el fondo es el 600 |
| PDFs | cada vista de PDF arranca con `$brand = $brand ?? BrandPalette::currentAsHex()` (cubre todo lugar que la renderice, no solo los servicios); los hex de marca se reemplazan por `{{ $brand[600] }}`, etc., mapeando cada hex fucsia anterior al escalón equivalente (`#fdf4ff`/`#faf5ff`→50, `#f3e8ff`/`#f5d0fe`→100/200, `#d946ef`→500, `#c026d3`→600, `#a21caf`→700, `#86198f`→800) |

La variable `--primary-text` se imprime con un `renderHook(PanelsRenderHook::STYLES_AFTER, …)` en ambos paneles (hook confirmado en `vendor/filament/filament/src/View/PanelsRenderHook.php`).

*Alternativa:* compilar un CSS por empresa en el deploy (descartado: obliga a recompilar en cada cambio y no cubre los PDFs).

### 4. Pestaña "Apariencia" con permiso propio

Igual que la pestaña Clientes: `APPEARANCE_PERMISSION = 'settings.appearance'`, `Tab::make('Apariencia')->visible(...)`, entrada en `TAB_FIELDS`, y en `save()` solo se escribe el color si `canManageAppearance()`. Campo `ColorPicker::make('primary_color')` de Filament con regla `regex:/^#[0-9a-fA-F]{6}$/` y mensaje en voseo. La vista previa es un `View`/`Html` que lee el estado con `->live()` y dibuja los once escalones generados con `BrandPalette::fromHex()` (desde el estado del formulario, no desde `Setting`). El permiso va en `Permissions::catalog()` sin sumarse a `defaultsFor(ROLE_ADMIN)`.

### 5. Guardia contra colores fijos

Un test (`BrandColorUsageTest`) recorre `resources/views` (excluyendo `vendor/` y `welcome.blade.php`), `resources/css/filament` y `app/Filament`, y falla si encuentra `\bfuchsia-\d{2,3}\b` o alguno de los hex de la paleta fucsia anterior, listando archivo y línea. `purple` y `pink` no se bloquean, porque ahí son colores de estado o de categoría.

## Risks / Trade-offs

- [El generador da escalones feos para colores extremos] → Tests con colores límite (negro casi puro, blanco casi puro, gris, amarillo, el `#10288c` del usuario) que verifiquen orden estricto y 600 exacto, más revisión visual en la vista previa.
- [La migración de ~25 vistas cambia el diseño sin querer, por ejemplo dos tonos de marca que colapsan en uno] → Migrar archivo por archivo mapeando escalón por escalón y comprobar a ojo las pantallas principales (venta, detalle de boleto, página pública, PDF) con fucsia y con un color distinto.
- [Clases `primary-*` en vistas que no compila el tema del panel] → Todas las vistas afectadas ya están en los `@source` de `theme.css`. `welcome.blade.php` (con `app.css`) queda fuera de alcance.
- [Texto blanco sobre un color claro en elementos propios] → `--primary-text` más el test de `textColorOn()` con un amarillo claro.
- [Un color principal muy claro usado **como texto** (links y títulos `text-primary-600`) sobre fondo blanco queda poco legible] → No lo resuelve este cambio: el spec cubre solo el texto *sobre* el color. Se vio en la revisión con `#fde047`. Opciones para un cambio posterior: avisar en la vista previa cuando el contraste con blanco es bajo, o derivar un tono más oscuro para texto.
- [Un PDF olvidado con hex fijo] → La guardia del punto 5 cubre también `resources/views/pdf` y `resources/views/tickets/pdf`.

## Migration Plan

1. Deploy del código con `npm run build` (cambian las clases de las vistas) y `php artisan permissions:sync` (permiso nuevo).
2. Sin configurar, todo queda en fucsia: no hay cambio visible para la instalación actual.
3. Asignar el permiso "Cambiar apariencia" al rol que corresponda y elegir el color en Parámetros > Apariencia.
4. Rollback: revertir el deploy. La clave `appearance.primary_color` puede quedar en `settings` sin efecto.
