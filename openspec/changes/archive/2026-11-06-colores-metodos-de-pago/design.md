# Design

## Context

- `payment_methods.color` (string, default `gray`) guarda un nombre de color de Filament. Hoy solo Efectivo (`success`) y Transferencia (`info`) tienen color; los métodos creados desde el panel quedan en `gray` (incluidos dos eliminados que siguen apareciendo en boletos viejos).
- `PaymentMethod::color()` devuelve ese nombre para los badges de Filament (tabla de Pagos) y `PaymentMethod::badgeClasses()` lo traduce a clases Tailwind fijas para las vistas Blade (detalle de ventas / "Mis ventas", detalle del boleto). `tickets/summary.blade.php` deduce el color buscando `emerald`/`sky` dentro de esas clases.
- El formulario de `PaymentMethodResource` solo tiene el nombre; código y orden se generan en `booted()`.
- `BrandColorUsageTest` prohíbe `fuchsia-*`/`purple-*` y exige `dark:text-primary-*` junto a cada `text-primary-*`.

## Goals / Non-Goals

**Goals:**
- Una única definición de la paleta (clave → label, color de Filament, clases Tailwind) en el modelo, consumida por Filament, Blade, el formulario y la asignación automática.
- Compatibilidad con los valores guardados (`success`, `info`).

**Non-Goals:**
- Colores en PDF y Excel (hoy el método va sin color).
- Colores libres (hex): la paleta fija garantiza contraste en claro y oscuro y clases que Tailwind compila.

## Decisions

### 1. Paleta en `PaymentMethod::PALETTE`
Constante `clave => ['label', 'filament', 'classes']` en este orden de asignación: `success` (Verde), `info` (Celeste), `warning` (Ámbar), `teal` (Verde azulado), `indigo` (Índigo), `orange` (Naranja), `rose` (Rosa), `cyan` (Cian), `lime` (Lima).
- `success`/`info`/`warning` conservan los nombres de Filament (ya guardados y registrados en el panel); el resto usa las constantes `Filament\Support\Colors\Color::Teal`, etc. `->color()` de Filament acepta un array de tonos, así que no hace falta registrar colores en el panel.
- Las clases siguen el patrón existente `bg-{tono}-500/10 text-{tono}-700 dark:text-{tono}-400`, escritas completas en el modelo (`app/Models` ya está en los `@source` del tema) para que Tailwind las compile.
- `color()` y `badgeClasses()` resuelven por la paleta; una clave desconocida (incluidas `gray`, `danger`, `primary` viejas) cae en gris neutro.
- Alternativa descartada: registrar colores extra en `AdminPanelProvider::colors()`. Agrega estado global por un solo uso y obliga a sincronizar dos listas.

### 2. Asignación automática en `creating`
`PaymentMethod::suggestedColor(): string` cuenta los colores de los métodos no eliminados y devuelve la primera clave de la paleta con cero usos; si no hay, la de menor cantidad (empate: orden de la paleta). En `booted()::creating`, si `color` está vacío o no es de la paleta, se le asigna `suggestedColor()`. Así funciona también para altas fuera del panel.

### 3. Formulario y tabla de "Métodos de pago"
- `Select::make('color')` (o `Radio`/`ToggleButtons` según lo que renderice mejor el badge; preferencia: `Select` con `allowHtml()` y cada opción como badge con sus clases) con opciones de la paleta, `->default(fn () => PaymentMethod::suggestedColor())`, `->required()`, `->in(array_keys(PALETTE))` y mensajes en voseo.
- La columna Nombre pasa a `->badge()->color(fn ($record) => PaymentMethod::color($record->code))`.
- Como el color es parte del alta y se edita, la columna `color` se agrega a la validación; `code` y `sort_order` siguen inmutables.

### 4. Resumen de la venta
`tickets/summary.blade.php` deja de deducir el color con `str_contains` y usa `PaymentMethod::badgeClasses()` como el resto. Cambia levemente el aspecto actual (fondo gris + texto lima/celeste) por el badge estándar.

### 5. Migración de datos
Migración que asigna a cada método con `color` fuera de la paleta (hoy `gray`) el color menos usado entre los ya coloreados: primero los vigentes y después los eliminados (siguen apareciendo en boletos viejos), cada grupo por `sort_order`. Así los métodos en uso toman los primeros colores de la paleta. Es idempotente. Se implementa con el query builder y una copia local de la paleta para no depender de que el modelo cambie en el futuro. `down()` no revierte (no hay forma de saber qué era gris a propósito); queda vacío y documentado.

## Risks / Trade-offs

- [Clases Tailwind nuevas no compiladas en el servidor] → `npm run build` en el deploy (ya es parte del flujo desde que `public/build` no se versiona).
- [`BrandColorUsageTest` revisa vistas y `app/Filament`] → la paleta no usa `fuchsia`/`purple` ni `text-primary-*`.
- [Dos métodos con el mismo color cuando la paleta se agota] → Aceptado: 9 colores; con más métodos se repite el menos usado y el usuario puede cambiarlo.
- [El resumen de la venta cambia de aspecto] → Se alinea con los demás badges; se revisa en el navegador.

## Migration Plan

1. Deploy del código.
2. `php artisan migrate` (colorea los métodos en gris).
3. `npm run build`.
4. Rollback: revertir el código; los colores asignados quedan guardados y el código viejo los mostraría en gris salvo `success`/`info`/`warning`.
