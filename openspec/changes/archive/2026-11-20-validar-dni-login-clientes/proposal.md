# Proposal

## Why

El login de clientes (`/clientes/login`) y el de administración (`/admin/login`) están en el mismo dominio, y Chrome guarda y ofrece las contraseñas por dominio, sin distinguir la ruta. Por eso en el campo DNI del login de clientes puede quedar un nombre de usuario del admin, y el ingreso responde con el mensaje genérico "El DNI o la contraseña son incorrectos.", que no explica lo que pasó. Hoy no es un riesgo de seguridad: al normalizar, el DNI queda vacío y nunca coincide con un cliente. Solo le pasa a quien usa los dos paneles en el mismo navegador, porque los clientes entran desde sus propios celulares.

## What Changes

- El campo DNI del login de clientes acepta solo dígitos, puntos, espacios y guiones, con al menos un dígito (sigue entrando "11.223.344").
- Si trae cualquier otro carácter, el ingreso se rechaza antes de buscar al cliente, con el mensaje "Ingresá solo los números de tu DNI." debajo del campo.
- Este mensaje depende solo del formato de lo que se escribió, así que no revela si un DNI está en el padrón. Un DNI bien escrito que no existe, o con contraseña incorrecta, sigue recibiendo el mensaje genérico.
- Fuera de alcance: evitar que Chrome sugiera las credenciales del admin. Eso pediría separar los paneles en subdominios, y se descartó porque el caso real es poco frecuente.

## Capabilities

### New Capabilities

_Ninguna._

### Modified Capabilities

- `client-access`: suma el requisito "Formato del DNI en el ingreso" (validación de formato y mensaje propio). "Ingreso con DNI y contraseña" no cambia.

## Impact

- `app/Filament/Client/Pages/Auth/ClientLogin.php` (campo DNI del formulario).
- Tests de ingreso en `tests/Feature/ClientPanelTicketsTest.php`.
- Sin migraciones, permisos, assets ni pasos de deploy.
