# Proposal

## Why

Al volver atrás con el navegador a un login (`/admin/login` o `/clientes/login`), el campo de usuario o DNI muestra texto, pero al enviar el formulario se rechaza con "El campo nombre de usuario es obligatorio." (o "El DNI es obligatorio."). Livewire marca sus páginas con `Cache-Control: no-store`, así que Chrome no usa el caché de historial (bfcache): vuelve a pedir la página y después restaura lo que había escrito el usuario en los inputs sin disparar el evento `input`. El componente arranca con el campo en `null` y el servidor recibe el campo vacío. Se reprodujo yendo de `/admin/login` al panel de clientes con el enlace "¿Sos cliente?" y volviendo atrás.

## What Changes

- En las páginas de ingreso de los dos paneles (administración y clientes), al llegar por "Atrás" o "Adelante" del navegador, los valores que el navegador restauró en los campos pasan al estado del formulario antes de que el usuario lo envíe. Así lo que se ve coincide con lo que se envía.
- No cambia el contenido ni la validación de los formularios, ni el comportamiento al entrar a la página de forma normal.
- Fuera de alcance: que el campo DNI del login de clientes use `autocomplete="username"` y Chrome ofrezca ahí las credenciales del panel de administración. Queda para un cambio aparte.

## Capabilities

### New Capabilities
- `login-back-navigation`: comportamiento de las páginas de ingreso de los paneles cuando se llega a ellas por la navegación de historial del navegador (Atrás/Adelante).

### Modified Capabilities
(ninguna)

## Impact

- `app/Providers/Filament/AdminPanelProvider.php` y `app/Providers/Filament/ClientPanelProvider.php`: un script en un render hook, limitado a la página de ingreso de cada panel.
- Sin cambios en dependencias, base de datos ni rutas.
- Tests de feature que comprueban que el script se incluye en las dos páginas de ingreso y solo ahí.
