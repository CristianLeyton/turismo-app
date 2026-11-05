# Proposal

## Why

El panel de clientes (`/clientes`) ya funciona, pero el negocio todavía no lo ofrece a los pasajeros: hoy el aviso del login de administración está comentado a mano y el logo del boleto público lleva al inicio del sitio. Hace falta poder encender y apagar el panel desde Parámetros, sin tocar código, y que todo lo que lleva a él siga ese estado.

## What Changes

- Nuevo parámetro "Panel de clientes activo", **desactivado por defecto** (mientras nadie lo configure).
- Nuevo permiso de Configuración para activar o desactivar el panel de clientes. No se asigna por defecto a ningún rol; el Super Administrador lo tiene por bypass.
- Nueva pestaña "Clientes" en Parámetros, después de "Seguridad", con el interruptor. Quien no tiene el permiso no ve la pestaña, y guardar la página no cambia el parámetro aunque el pedido lo incluya.
- **BREAKING**: con el panel desactivado, todo `/clientes` (ingreso incluido) y las rutas sueltas `clientes/ingresar`, `clientes/salir`, `clientes/boletos/{boleto}` y `clientes/boletos/{boleto}/pdf` responden 404. Como el valor por defecto es desactivado, al desplegar el panel deja de estar disponible hasta que alguien lo active. Hoy nadie lo usa.
- El aviso "¿Sos cliente?…" del login de administración se muestra solo con el panel activado, y lleva al ingreso de clientes.
- El logo del boleto público lleva al ingreso de clientes con el panel activado y al inicio del sitio con el panel desactivado.
- Los textos de la venta sin fecha mencionan el panel de clientes solo si está activado. La regla de que los boletos sin fecha se venden solo a pasajeros del padrón se mantiene siempre.

## Capabilities

### New Capabilities

_Ninguna._

### Modified Capabilities

- `client-access`: el panel de clientes y sus rutas existen solo con el parámetro activado; el aviso de acceso en el login de administración depende del parámetro.
- `settings-page`: pestaña "Clientes" visible solo con el permiso nuevo, y el guardado respeta ese permiso.
- `ticket-share-page`: destino del logo según el parámetro.
- `ticket-sale`: textos de ayuda y de rechazo de la venta sin fecha según el parámetro.

## Impact

- `app/Models/Setting.php`: clave y lectura del parámetro.
- `app/Support/Permissions.php`: permiso nuevo en el catálogo, fuera de los defaults de los roles.
- `app/Filament/Pages/TicketSettings.php`: pestaña, carga y guardado protegido.
- Middleware nuevo en `app/Http/Middleware`, aplicado en `ClientPanelProvider` y en las rutas `clientes/*` de `routes/web.php`.
- `AdminPanelProvider`: render hook del aviso, condicionado.
- `resources/views/boletos/share.blade.php`: enlace del logo. El archivo tiene cambios locales sin commitear del usuario.
- `TicketForm.php` y `CreateTicket.php`: textos de la venta sin fecha.
- Tests: los del panel de clientes tienen que activar el parámetro, y hacen falta tests nuevos para el 404, el permiso, el aviso, el logo y los textos.
- Despliegue: correr `permissions:sync` para crear el permiso y asignarlo a mano.
