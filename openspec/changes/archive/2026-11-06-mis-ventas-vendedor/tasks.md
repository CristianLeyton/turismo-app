# Tasks

## 1. Permiso "Ver mis ventas"

- [x] 1.1 Agregar la acción `view_own` ("Ver mis ventas") al módulo `sales` en `Permissions::catalog()` y a `defaultsFor(ROLE_SELLER)` (no al Administrador); verificar con tests en `RolesAndPermissionsTest` que el Vendedor la trae, el Administrador no, y que `syncToDatabase()` la agrega a un rol Vendedor existente sin quitarle permisos
- [x] 1.2 Actualizar el test existente "Vendedor sin clientes ni ventas" para que acepte solo `sales.view_own` dentro del módulo Ventas; verificar con `php artisan test --compact tests/Feature/RolesAndPermissionsTest.php`

## 2. Seguridad del componente de detalle

- [x] 2.1 Marcar `SalesUserTickets::$userId` con `#[Locked]` y agregar `authorizeViewer()` (permite `sales.view`, o `sales.view_own` con `userId === auth()->id()`, si no `abort(403)`) llamado en `mount()`, `exportPdf()` y `exportExcel()`; verificar con un test nuevo (`SalesUserTicketsTest`) que el admin con `sales.view` monta cualquier vendedor, el vendedor con `view_own` monta solo el suyo, montar a otro devuelve 403, `set('userId', otro)` lanza la excepción de propiedad bloqueada y exportar sin permiso devuelve 403

## 3. Filtro de método múltiple en el detalle

- [x] 3.1 Reemplazar `public string $payment` por `public array $payments` (vacío = todos) en `SalesUserTickets`, con `whereIn` en `ticketQuery()`/`paymentQuery()`, reseteo a página 1 al cambiarlo, `resetFilters()` vaciándolo, contexto de exportación con las etiquetas unidas y nombre de archivo con los códigos; verificar con tests del componente que filtrar por dos métodos suma solo esas ventas y pagos y que "Restablecer" vuelve a todos
- [x] 3.2 Reemplazar el `<select>` del Blade `sales-user-tickets` por un control de selección múltiple (chips/casillas con `wire:model.live="payments"`, con soporte de modo oscuro y mobile) y mostrar los métodos elegidos en la línea de resultados; verificar con un test `assertSee` de las etiquetas y revisión visual en el navegador
- [x] 3.3 Hacer que el modal "Ver detalle" de `SalesResource` (ambas acciones, escritorio y mobile) y `tickets-modal.blade.php` pasen `payments` con los métodos del filtro de la tabla; verificar con un test que el detalle abre con el rango y los métodos de la tabla

## 4. Cálculos de la tabla de Ventas

- [x] 4.1 Corregir el criterio de "aparece en el período": un solo `whereHas` con las dos fechas sobre boletos vigentes (y métodos filtrados) **o** pagos vigentes del rango (y métodos); verificar con tests de `ManageSales` que un vendedor con ventas antes y después del rango no aparece, uno con ventas dentro sí, y uno con solo pagos aparece con saldo negativo
- [x] 4.2 Pasar la lista de métodos a `paymentsCountSubquery`/`paymentsSumSubquery`/`paymentsForFooter` y aplicarla también en `mobileSummaryTotals()`; verificar con tests que con "Efectivo" la fila y la tarjeta de resumen cuentan solo los pagos en efectivo
- [x] 4.3 Reemplazar el eager loading y las sumas en PHP de las columnas por `addSelect` de las subconsultas (boletos, total ventas, pagos, total pagos) y ordenar por esos alias, incluido el saldo con métodos; verificar con tests que los montos por fila coinciden con el detalle, que el orden con método filtrado coincide con lo mostrado y que la cantidad de consultas no crece al agregar ventas (`DB::enableQueryLog`)

## 5. Página "Mis ventas"

- [x] 5.1 Crear la página con `php artisan make:filament-page MySales --cluster=...` (o equivalente vigente, consultando `search-docs`) en el clúster `SalesCluster`, título "Mis ventas", descripción "Tus boletos vendidos, pagos realizados y saldo", `canAccess()`/`shouldRegisterNavigation()` con `sales.view_own`, y vista que monta `SalesUserTickets` con `userId = auth()->id()` siguiendo la skill `filament-ui`; verificar con tests que el Vendedor abre la página y ve sus datos y no los de otro, el Administrador por defecto recibe 403 y no la ve en la navegación, y un Vendedor sin otros permisos de Ventas entra al clúster y llega a "Mis ventas"
- [x] 5.2 Verificar en un test que "Mis ventas" abre con el mes actual y exporta PDF y Excel solo con datos propios (respuesta de descarga con el nombre del vendedor logueado)

## 6. Integración

- [x] 6.1 Correr `vendor/bin/pint --dirty --format agent` y los tests afectados (`RolesAndPermissionsTest`, `SalesUserTicketsTest`, tests de `ManageSales` y de "Mis ventas"); revisar en el navegador como Vendedor y como Administrador que el clúster, el modal y la página muestran los mismos números con el mismo filtro

## Workflow follow-up

- En el deploy correr `php artisan permissions:sync` para crear `sales.view_own` y asignarlo al rol Vendedor; avisar que el saldo filtrado por método ahora también filtra los pagos.
- Archivar el cambio con `/opsx:archive` cuando esté implementado y revisado.
