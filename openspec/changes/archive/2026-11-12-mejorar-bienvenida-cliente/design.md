# Design

## Context

La bienvenida ya existe: es la página `SetPassword` (`/clientes/clave`), a la que `EnsureClientPasswordIsSet` redirige mientras el cliente tenga la contraseña inicial. Su título saluda por nombre y su contenido es un `View` (`filament.client.set-password`) con dos párrafos, seguido del formulario de Filament. Ver proposal.md (Why) para la motivación. Restricciones: panel Filament 4 con `topNavigation()`, uso mayoritario en celular, Tailwind v4 con modo oscuro obligatorio y color de marca solo vía `primary` (`BrandColorUsageTest` lo vigila).

## Goals / Non-Goals

**Goals:**
- Explicar en 3 ítems breves qué hace el panel, sin duplicar la lógica de "Mis boletos".
- Que se vea bien de 360 px a escritorio.

**Non-Goals:**
- Tour, pasos múltiples o recordar que el cliente ya vio la bienvenida (la pantalla solo aparece una vez, hasta elegir la contraseña).
- Rediseñar el formulario o el flujo de guardado.

## Decisions

1. **Reutilizar `SetPassword` y su vista Blade** en vez de crear una página nueva o un modal en "Mis boletos". Alternativa descartada: modal de bienvenida en el dashboard; exigiría persistir "ya vio la bienvenida" y el cliente no podría usar el panel hasta cambiar la contraseña de todos modos.
2. **Estructura de la vista:** párrafo de contexto (se mantiene el texto del DNI como contraseña inicial) → bloque "Qué vas a poder hacer" con 3 ítems (ícono `x-filament::icon` Heroicon, título corto, una línea) → formulario. Ítems: *Ver tus boletos* (próximos e historial), *Elegir fecha y asiento* (los boletos sin fecha), *Ver o descargar el boleto* (PDF). Voseo y vocabulario del negocio.
3. **Responsive:** una columna en celular (`grid grid-cols-1 gap-3`), tres columnas desde `sm:`; cada ítem es una fila ícono+texto en celular y se apila en columnas anchas. Ancho de lectura acotado en escritorio con `getMaxContentWidth()` (p. ej. `Width::ThreeExtraLarge`) en la página.
4. **Color y oscuro:** burbuja del ícono `bg-primary-50 text-primary-600 dark:bg-primary-400/15 dark:text-primary-400`; tarjetas `rounded-lg bg-gray-50 p-2.5 dark:bg-white/5`; textos `text-gray-950 dark:text-white` y `text-gray-600 dark:text-gray-300`. Sin colores fijos de marca.
5. **Texto fijo en la vista**, sin parámetros: no hace falta pasar datos desde la página. El saludo con nombre se mantiene en `getTitle()`.

## Risks / Trade-offs

- [Clases Tailwind nuevas no aparecen en la UI] → correr `npm run build`; la carpeta `resources/views/filament/client` ya está cubierta por el `@source` del tema.
- [Texto desactualizado si el panel suma funciones] → los ítems se mantienen cortos y genéricos; el spec los fija como resumen, no como lista cerrada.
- [Prometer algo que el cliente no puede hacer] → solo se mencionan acciones existentes hoy (ver boletos, elegir fecha/asiento, ver/descargar PDF).
