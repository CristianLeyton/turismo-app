# Tasks

## 1. Bienvenida

- [x] 1.1 Reescribir `resources/views/filament/client/set-password.blade.php` con el párrafo de contexto y el bloque "Qué vas a poder hacer" (3 ítems con ícono Heroicon, 1 columna en celular y 3 desde `sm:`), con `dark:` y `primary`; verificar con un test que el texto de los 3 ítems aparece en la página.
- [x] 1.2 En `SetPassword` limitar el ancho de contenido con `getMaxContentWidth()`; verificar que el formulario sigue debajo del resumen y que los tests existentes de `ClientPanelProfileTest` siguen en verde.
- [x] 1.3 Agregar a `tests/Feature/ClientPanelProfileTest.php` los casos de los escenarios del spec (resumen visible junto al saludo por nombre, sin mencionar "boletería") y correr `php artisan test --compact --filter=ClientPanelProfileTest`.

## 2. Verificación visual y estilo

- [x] 2.1 Correr `vendor/bin/pint --dirty --format agent` y `php artisan test --compact --filter=BrandColorUsageTest` para confirmar que no hay colores de marca fijos ni `text-primary-*` sin `dark:`.
- [x] 2.2 Compilar con `npm run build` y revisar `/clientes/clave` en 360 px, 768 px y escritorio, en claro y oscuro, sin scroll horizontal.
