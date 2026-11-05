# Tasks

## 1. Parámetro y permiso

- [x] 1.1 Agregar `Setting::PANEL_CLIENTES_ACTIVO` (`clients.panel_activo`) y `Setting::panelClientesActivo()` con valor por defecto `false`; verificar con un test que sin clave guardada devuelve `false` y que con `Setting::set(..., '1')` devuelve `true`
- [x] 1.2 Agregar `'client_panel' => 'Activar/desactivar panel de clientes'` al módulo `settings` de `Permissions::catalog()`, sin sumarlo a `defaultsFor(ROLE_ADMIN)`; verificar en `RolesAndPermissionsTest` que `syncToDatabase()` crea el permiso, que el rol Administrador no lo recibe y que el Super Administrador sí

## 2. 404 del panel y de las rutas sueltas

- [x] 2.1 Confirmar con `search-docs` la firma de `Panel::middleware()` (parámetro `isPersistent`) en Filament 4 y el nombre de la ruta de ingreso del panel con `list-routes` (`filament.clientes.auth.login`); verificar que la respuesta quede anotada en el design si difiere
- [x] 2.2 Crear el middleware `EnsureClientPanelIsEnabled` con `php artisan make:middleware` (devuelve 404 con el panel desactivado) y registrarlo como middleware persistente en `ClientPanelProvider`; verificar con un test nuevo (`ClientPanelToggleTest`) que `/clientes/login` y `/clientes` dan 404 sin configurar y con el panel desactivado, y responden normalmente con el panel activado
- [x] 2.3 Agrupar en `routes/web.php` las rutas `clientes/ingresar`, `clientes/salir`, `clientes/boletos/{ticket}` y `clientes/boletos/{ticket}/pdf` bajo el middleware; verificar en `ClientPanelToggleTest` que las cuatro dan 404 con el panel desactivado, incluido un cliente con sesión pidiendo un PDF de su DNI
- [x] 2.4 Verificar en `ClientPanelToggleTest` que las acciones Livewire de "Mis boletos" no se ejecutan con el panel desactivado (cliente con sesión, panel apagado después de montar), y que `/boletos/{token}` sigue respondiendo 200 con el panel apagado
- [x] 2.5 Activar el parámetro en `setUp()` de `ClientAuthenticationTest`, `ClientPanelProfileTest`, `ClientPanelTicketsTest` y en los tests de `TicketPdfConsistencyTest` que usen rutas de clientes; verificar que esos cuatro archivos pasan con `php artisan test --compact`

## 3. Pestaña "Clientes" en Parámetros

- [x] 3.1 Agregar a `TicketSettings` la pestaña "Clientes" (después de Seguridad) con el interruptor `panel_clientes_activo`, visible solo con `settings.client_panel`, más su entrada en `TAB_FIELDS` y la carga del valor en el `fill`; verificar en `TicketSettingsTest` que con el permiso se ven las cinco pestañas en orden con el interruptor desactivado por defecto, y que sin el permiso se ven solo cuatro
- [x] 3.2 En `save()`, guardar el parámetro solo si el usuario tiene `settings.client_panel`; verificar en `TicketSettingsTest` que con el permiso se activa, y que sin el permiso, enviando `panel_clientes_activo = true` con `set()`, los demás parámetros se guardan y el panel sigue desactivado

## 4. Aviso en el login de administración

- [x] 4.1 Descomentar el render hook `AUTH_LOGIN_FORM_AFTER` de `AdminPanelProvider` para que devuelva `''` con el panel apagado y enlace a la ruta de ingreso de clientes con el panel activo; verificar con un test que `/admin/login` muestra "¿Sos cliente?" y el enlace a `/clientes/login` solo con el panel activo

## 5. Logo del boleto público

- [x] 5.1 En `resources/views/boletos/share.blade.php`, cambiar solo el `href` del logo para que lleve a la ruta de ingreso de clientes con el panel activo y a `url('')` si no, sin tocar los cambios locales del usuario en el archivo; verificar en `TicketShareLinkModeTest` (o en un test nuevo de la página) que el `href` del logo cambia según el parámetro

## 6. Textos de la venta sin fecha

- [x] 6.1 Extraer en `TicketForm` el texto de ayuda de `sell_without_date_ida` y `sell_without_date_vuelta` a un método que sume "o el pasajero los elige desde el panel de clientes" solo con el panel activo; verificar con un test del formulario de venta que la ayuda cambia según el parámetro
- [x] 6.2 En `CreateTicket`, agregar la oración sobre el panel de clientes al rechazo por DNIs fuera del padrón solo con el panel activo; verificar con un test que la venta se rechaza con y sin el panel, y que el texto lo menciona solo con el panel activo

## 7. Cierre

- [x] 7.1 Correr `vendor/bin/pint --dirty --format agent` y los tests de los archivos tocados (`ClientPanelToggleTest`, `TicketSettingsTest`, `RolesAndPermissionsTest`, `TicketShareLinkModeTest`, los de clientes y los de venta sin fecha); verificar que todos pasan
- [x] 7.2 Correr `openspec validate client-panel-toggle --strict`; verificar que no hay errores

## Workflow follow-up

- Al desplegar: correr `php artisan permissions:sync` y asignar "Activar/desactivar panel de clientes" a los roles que corresponda.
- Archivar el cambio después de la revisión.
