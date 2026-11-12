# Proposal

## Why

La primera pantalla que ve un cliente al ingresar al panel (`/clientes`) es "Elegir contraseña". Hoy lo saluda por su nombre pero solo le habla de la contraseña: no sabe para qué sirve el panel ni qué va a poder hacer después. Como lo usa sobre todo desde el celular, una primera impresión que se siente como un trámite aumenta la chance de que abandone o llame a boletería por algo que el panel resuelve solo.

## What Changes

- La pantalla de primer ingreso pasa a ser una bienvenida: mantiene el saludo por nombre y suma un resumen breve de lo que el cliente podrá hacer en el panel (ver sus boletos, elegir fecha y asiento de los que no tienen fecha, ver o descargar el boleto en PDF).
- El pedido de contraseña sigue siendo el paso obligatorio, pero se presenta como "el primer paso" dentro de esa bienvenida.
- Diseño responsive: en celular el resumen es una lista vertical compacta y el formulario queda a mano sin scroll excesivo; en escritorio el resumen se acomoda en columnas y el contenido no se estira a todo el ancho. Con soporte de modo oscuro y color de la empresa (`primary`).
- No cambia la lógica de guardado, la redirección ni quién ve esta pantalla.

## Capabilities

### New Capabilities

### Modified Capabilities
- `client-access`: se agrega el requisito de que la bienvenida del primer ingreso explique brevemente qué se puede hacer en el panel y sea legible en celular y escritorio.

## Impact

- `app/Filament/Client/Pages/SetPassword.php` (encabezado de la página y orden del contenido).
- `resources/views/filament/client/set-password.blade.php` (nuevo contenido de la bienvenida).
- `tests/Feature/ClientPanelProfileTest.php` (cobertura del texto de bienvenida).
- Requiere `npm run build` si se usan clases Tailwind nuevas.
- Sin cambios de base de datos, permisos ni dependencias. Fuera de alcance: tour interactivo, onboarding por pasos, cambios en "Mis boletos".
