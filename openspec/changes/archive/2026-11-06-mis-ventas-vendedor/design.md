# Design

## Context

- El detalle por vendedor es el componente Livewire `App\Livewire\SalesUserTickets` (tabla Blade a mano, resumen, desglose, filtros propios y exportación PDF/Excel vía `SalesTicketsPdfService` / `SalesTicketsExcelService`). Hoy solo se monta dentro del modal "Ver detalle" de `SalesResource`, a través de `resources/views/filament/resources/sales/tickets-modal.blade.php`.
- `SalesUserTickets::$userId` es una propiedad pública sin `#[Locked]` y el componente no autoriza nada: cualquiera que lo tenga renderizado puede cambiar `userId` desde el navegador (`$set`) y ver o exportar a otro vendedor.
- `SalesResource` (model `User`) filtra fechas con dos `whereHas('sales')` independientes, exige ventas para listar a un vendedor, no aplica el método de pago a los pagos y suma en PHP sobre `sales.tickets` y `payments` cargados con eager loading.
- El Super Administrador pasa todas las habilidades por `Gate::before`; la matriz de roles solo deja asignar permisos que el editor posee.
- Ver `proposal.md` para la motivación y `specs/` para los requisitos.

## Goals / Non-Goals

**Goals:**
- Un solo componente de detalle (el actual) usado en dos contenedores: el modal del admin y la página "Mis ventas".
- Una sola regla de cálculo de ventas/pagos/saldo compartida por las filas, el orden, la tarjeta de resumen y el detalle.

**Non-Goals:**
- Migrar el detalle a una tabla Filament (`HasTable`): se decidió reutilizar el componente tal cual.
- Deduplicar las acciones escritorio/mobile de `SalesResource` ni tocar la exclusión del usuario id=1.

## Decisions

### 1. "Mis ventas" como página Filament del clúster que monta el componente
Página `App\Filament\Clusters\Sales\Pages\MySales` (slug `mis-ventas`; creada a mano porque `make:filament-page --cluster --no-interaction` falla con un `TypeError` al preguntar la ubicación de la vista), con `$cluster = SalesCluster::class`, `canAccess()` = `auth()->user()?->can('sales.view_own')` y `shouldRegisterNavigation()` = `canAccess()` (mismo patrón que `TicketSettings`). Su vista monta `SalesUserTickets` con `getDetailParameters()`: el usuario logueado y el mes actual. El mes lo pasa la página y no el componente, para no cambiar el modal del admin cuando la tabla no tiene fechas (ahí sigue mostrando todo el historial).
- Alternativa descartada: una `Resource` con model `Sale` y tabla Filament (opción A de la exploración); no reutiliza el detalle ya probado y obliga a separar ventas y pagos.

### 2. Autorización en el componente, no en el contenedor
- `#[Locked] public int $userId`: Livewire rechaza cualquier intento del cliente de modificarlo.
- Método `authorizeViewer()` llamado en `mount()`, `exportPdf()` y `exportExcel()`: permite si `can('sales.view')`, o si `can('sales.view_own')` y `$userId === auth()->id()`; si no, `abort(403)`. Las exportaciones re-autorizan porque son peticiones independientes y los permisos pueden cambiar con el detalle abierto.
- Se autoriza en el componente (y no solo en la página o el modal) porque es el único punto por el que pasan los dos contenedores y las exportaciones.

### 3. Filtro de método múltiple en el detalle
`public string $payment = 'all'` pasa a `public array $payments = []` (vacío = todos). El selector nativo se reemplaza por un desplegable (Alpine) con casillas `wire:model.live="payments"`: ocupa lo mismo con 2 o con 10 métodos, funciona en mobile y se parece al filtro de métodos de la tabla de Ventas. El botón nombra hasta dos métodos y con más muestra "N métodos"; `updatedPayments()` normaliza lo que llega del navegador. Se descartaron los chips en un control segmentado: parecían de selección única y con varios métodos desarmaban el bloque de filtros, que quedó en dos filas (fechas + método; ver + orden + restablecer). Las consultas usan `whereIn` cuando no está vacío. El contexto de exportación expone `payment_label` como la lista de etiquetas unidas por coma (las vistas PDF/Excel ya muestran ese campo) y el nombre del archivo concatena los códigos.
- El modal de `SalesResource` pasa `payments` = los métodos del filtro de la tabla (`payment_method.methods`) además de `from`/`to`.

### 4. Regla única de cálculo en `SalesResource`
- `applySalesTotals()` agrega al `SELECT` (vía `selectSub`) los alias `tickets_count`, `total_amount`, `payments_count`, `payments_total` y `saldo`, a partir de dos subconsultas base (`ticketsBaseSubquery`, `paymentsBaseSubquery`) que reciben el rango y la lista de métodos. Las columnas leen esos atributos y el `sortable(query:)` ordena por el alias. Se elimina el eager loading de `sales.tickets` y `payments` de la tabla.
- Se aplica en `modifyQueryUsing()` de la tabla, no en el `query()` de los filtros: Filament aplica los filtros dentro de un `where` anidado, donde Eloquent conserva los `with()` pero descarta los selects agregados. El estado de los filtros se lee de `$livewire->tableFilters` (`salesFilterState()`), y los filtros quedan solo con su formulario e indicadores.
- La tarjeta de resumen (`mobileSummaryTotals`) suma esos mismos alias sobre la query filtrada, así que no puede diferir de las filas. Se eliminan `sumTicketsForFooter` y `paymentsForFooter`.
- Criterio de "aparece en el período": `whereExists` sobre la subconsulta base de boletos **o** sobre la de pagos (rango y métodos juntos en una sola condición). Una venta sin boletos vigentes no hace aparecer al vendedor (coincide con lo que ya muestra el detalle, que se arma desde los boletos).
- Alternativa descartada: extraer un servicio nuevo de cálculo. Las reglas ya viven en estos helpers; agregar el parámetro de métodos y unificar el uso alcanza sin una capa extra.

### 5. Permiso `sales.view_own`
Se agrega como acción del módulo `sales` en `Permissions::catalog()` (label "Ver mis ventas") y a `defaultsFor(ROLE_SELLER)`. El Administrador no lo trae: como la matriz solo deja asignar lo que el editor posee, solo el Super Administrador puede dárselo a roles nuevos. `permissions:sync` (aditivo) lo agrega al rol Vendedor existente.

## Risks / Trade-offs

- [El Super Administrador ve "Mis ventas" por `Gate::before`] → Aceptado: ve sus propias ventas (su usuario es el de pruebas id=1).
- [Cambiar `payment` (string) por `payments` (array) rompe a quien monte el componente con el nombre viejo] → Solo lo monta `tickets-modal.blade.php`; se actualiza en el mismo cambio.
- [Un vendedor con ventas vacías (boletos todos eliminados) deja de aparecer en la tabla] → Es el comportamiento correcto: no tiene montos en el período; el detalle ya lo mostraba vacío.
- [Ordenar por alias de `addSelect` puede chocar con el `count` de la paginación] → La tabla no pagina (`paginated(false)`); se cubre con tests de ordenamiento.
- [Aplicar el método a los pagos cambia el saldo que veían los admins con el filtro activo] → Cambio buscado; se comunica en el deploy.

## Migration Plan

1. Deploy del código.
2. `php artisan permissions:sync` para crear `sales.view_own` y asignarlo al rol Vendedor.
3. Rollback: revertir el código; el permiso extra queda inerte en la base.
